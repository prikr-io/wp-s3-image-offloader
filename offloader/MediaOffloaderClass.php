<?php

/**
 * Project: prikr-image-offloader
 * Author: Koen Dolron
 * Copyright © Prikr 
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;

class s3MediaOffloader
{
  private $bucketName;
  private $s3Client;
  private $s3Path;

  public function __construct(S3Client $s3Client, $bucketName, $s3Path)
  {
    $this->s3Path = $s3Path;
    $this->bucketName = $bucketName;
    $this->s3Client = $s3Client;
  }

  /**
   * Init the plugin and call all filters & actions
   */
  public function init()
  {
    add_action('add_attachment', [$this, 'offloadMedia']);
    add_action('pmxi_attachment_uploaded', [$this, 'offloadWpaiMediaOnUpload'], 10, 3);
    add_action('pmxi_gallery_image', [$this, 'offloadWpaiMediaOnUpload'], 10, 3);
    add_action('delete_attachment', [$this, 'deleteMedia']);
  }

  /**
   * On upload of an image, we get the file path and run the image upload to S3.
   */
  public function offloadMedia($attachment_id)
  {
    $file_path = wp_get_original_image_path($attachment_id);
    $file_path = preg_replace('/-scaled/', '', $file_path); // In some cases even the original_image_path contains -scaled. So remove it to be sure.
    $this->offloadToS3($file_path, $attachment_id);
  }

  /**
   * SPECIFIC FOR WP ALL IMPORT
   * On upload of an image, we get the file path and run the image upload to S3.
   */
  public function offloadWpaiMediaOnUpload($post_id, $attachment_id, $file_path)
  {
    error_log('wps3 Hook WPAI gallery image');
    $this->offloadToS3($file_path, $attachment_id);
  }

  /**
   * Uploads the file to S3 and updates the attachment meta to the S3 URL.
   */
  public function offloadToS3($file_path, $attachment_id)
  {

    try {
      if (!is_readable($file_path)) {
        error_log('wps3 File does not exist: ' . $file_path);
        return false;
      }

      $key = $this->s3Path . basename($file_path);

      $result = $this->s3Client->putObject([
        'Bucket' => $this->bucketName,
        'Key' => $key,
        'SourceFile' => $file_path,
        'ACL' => 'public-read' // Optional: Set the desired object access permissions
      ]);

      // The original S3 url is found in $result['ObjectURL']. But this contains something like s3.eu-central-1.amazonaws.com, instead of our custom CDN domain name.
      // We want to replace this with our custom domain name.
      $url = 'https://' . $this->bucketName . '/' . $key;

      update_post_meta($attachment_id, 's3_url', $url);
      error_log('wps3 Uploaded file to S3: ' . $url);

      add_action('admin_notices', function () use ($url) {
        echo '<div class="notice notice-success is-dismissible"><p>';
        echo 'Offloaded image to: <a href="' . esc_html($url) . '" target="_blank">' . esc_html($url) . '</a>';
        echo '</p></div>';
      });

      /**
       * Check if the user wants to remove the images from the server.
       */
      $wps3_remove_images = get_option('wps3_image_offloader');
      if (isset($wps3_remove_images['wps3_remove_images'])) {
        // Temporarily remove the delete_attachment action hook, if not, we'll instantly delete the image from S3 after upload.
        remove_action('delete_attachment', [$this, 'deleteMedia']);

        error_log('wps3 Automatic removal of image from WP server: ' . $file_path);
        unlink($file_path);

        // Re-add the delete_attachment action hook.
        add_action('delete_attachment', [$this, 'deleteMedia']);
      }

      return $url;
    } catch (S3Exception $e) {
      add_action('admin_notices', function () use ($e) {
        echo '<div class="notice notice-error is-dismissible"><p>';
        echo 'Error offloading: ' . esc_html($e->getMessage());
        echo '</p></div>';
      });
      error_log('wps3 Error uploading file to S3: ' . $e->getMessage());
      error_log($e);
      error_log($e->getMessage());
      return false;
    }
  }

  /**
   * Deletes the file from S3 when the attachment is deleted.
   */
  public function deleteMedia($attachment_id)
  {
    $s3_url = get_post_meta($attachment_id, 's3_url', true);
    if ($s3_url) {
      $key = $this->s3Path . basename($s3_url);
      try {
        $result = $this->s3Client->deleteObject([
          'Bucket' => $this->bucketName,
          'Key' => $key
        ]);

        $statusCode = $result['@metadata']['statusCode'];

        if ($statusCode == '204' || $statusCode == '200') {
          error_log('wps3 statuscode: ' . $statusCode . '. ' . $key . ' was deleted.');
        } else {
          error_log('wps3 Error: ' . $key . ' was not deleted. Or we did not receive an 204 or 200 status code.');
        }
      } catch (S3Exception $e) {
        error_log('wps3 Error deleting file from S3: ' . $e->getMessage());
      }
    }
  }


  /**
   * Get a batch of images without 's3_url' post meta.
   *
   * @param int $batch_size The number of images to retrieve.
   * @param int $offset The offset for retrieving images.
   *
   * @return array|null List of image objects or null if no images found.
   */
  public function listMissingImagesBatch($batch_size, $offset)
  {
    global $wpdb;

    $query = $wpdb->prepare(
      "SELECT ID FROM {$wpdb->posts}
                LEFT JOIN {$wpdb->postmeta} ON ({$wpdb->posts}.ID = {$wpdb->postmeta}.post_id AND {$wpdb->postmeta}.meta_key = 's3_url')
                WHERE {$wpdb->posts}.post_type = 'attachment' AND {$wpdb->postmeta}.meta_id IS NULL
                LIMIT %d OFFSET %d",
      $batch_size,
      $offset
    );

    return $wpdb->get_results($query);
  }

  /**
   * Query all images that have 's3_url' post meta.
   */
  public function queryToDeleteAllS3Meta()
  {
    global $wpdb;
    $temp_table = 'tmp_post_ids';
    // Create a temporary table to store post_ids to delete
    $wpdb->query(
      "CREATE TEMPORARY TABLE {$temp_table} AS
      SELECT post_id
      FROM {$wpdb->postmeta}
      WHERE meta_key = 's3_url';"
    );

    // Delete records from wp_postmeta using a join with the temporary table
    $query = "
        DELETE pm
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$temp_table} tmp
        ON pm.post_id = tmp.post_id
        WHERE pm.meta_key = 's3_url';
    ";

    // return both the tmp_post_ids and the query
    $output = new stdClass;
    $output->temp_table = $temp_table;
    $output->query = $query;

    return $output;
  }
}



/**
 * Note, the bucket name should be equal to the domain name.
 * This is manually set like this, as we want to use a custom domain name for the CDN.
 * 
 * We only load this AFTER setup_theme, as the options are not loaded before this.
 */
add_action('after_setup_theme', 'init_mediaoffloader_class');
function init_mediaoffloader_class()
{
  $offloaderClass = new s3MediaOffloaderInit();
  $init = $offloaderClass->init();
  if (!$init) {
    error_log('wps3 Error: Could not initialize s3MediaOffloaderInit class.');
  };
}
