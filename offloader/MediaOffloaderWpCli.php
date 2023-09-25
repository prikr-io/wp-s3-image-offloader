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

class s3MediaOffloaderCLI extends WP_CLI_Command
{

    private $s3Client;
    private $offloader;

    public function __construct()
    {
        $options = get_option('wps3_image_offloader');
        $bucket_name = $options['wps3_bucket_name'];
        $bucket_region = $options['wps3_bucket_region'];
        $aws_key = $options['wps3_aws_key'];
        $aws_secret = $options['wps3_aws_secret'];
        $s3Path = 'images/';

        if (empty($bucket_region) || empty($bucket_name) || empty($aws_key) || empty($aws_secret)) {
            error_log('wps3 Error: Missing bucket region, bucket name, aws key or aws secret. Please check your settings.');
            return false;
        }

        $this->s3Client = new S3Client([
            'region' => $bucket_region,
            'version' => 'latest',
            'credentials' => [
                'key' => $aws_key,
                'secret' => $aws_secret
            ],
        ]);

        $this->offloader = new s3MediaOffloader($this->s3Client, $bucket_name, $s3Path);
    }
    /**
     * WP CLI COMMAND
     * wp offload-images --batch-size=20 --timeout=10
     */
    public function offload($args, $assoc_args)
    {
        $batch_size = isset($assoc_args['batch-size']) ? intval($assoc_args['batch-size']) : 10;
        $timeout = isset($assoc_args['timeout']) ? intval($assoc_args['timeout']) : 5;
        $offset = 0;


        do {
            // Get a batch of images without 's3_url' post meta.
            $images = $this->list_missing_images_batch($batch_size, $offset);

            if (!empty($images)) {
                // Log the IDs of the images.
                foreach ($images as $image) {
                    WP_CLI::log("Image ID: {$image->ID}");
                    $this->offloader->offloadMedia($image->ID);
                }
                $offset += $batch_size;
            } else {
                WP_CLI::success('All images processed.');
            }

            // Sleep for the specified timeout.
            WP_CLI::log("Sleep for {$timeout}s, so we dont go full hiroshima on the server");
            sleep($timeout);
        } while (!empty($images));
    }

    /**
     * Get a batch of images without 's3_url' post meta.
     *
     * @param int $batch_size The number of images to retrieve.
     * @param int $offset The offset for retrieving images.
     *
     * @return array|null List of image objects or null if no images found.
     */
    private function list_missing_images_batch($batch_size, $offset)
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
     * Delete postmeta records.
     *
     * ## OPTIONS
     *
     * [--dry-run]
     * : Perform a dry run without actually deleting records.
     *
     * @alias delete-s3url
     * @param array $args
     * @param array $assoc_args
     */
    public function delete_s3_url($args, $assoc_args)
    {
        global $wpdb;

        $dry_run = isset($assoc_args['dry-run']) ? true : false;

        if ($dry_run) {
            WP_CLI::line('Running in dry-run mode. No records will be deleted.');
        }

        // Create a temporary table to store post_ids to delete
        $wpdb->query("CREATE TEMPORARY TABLE tmp_post_ids AS
                SELECT post_id
                FROM {$wpdb->postmeta}
                WHERE meta_key = '_wp_attached_file'
                AND meta_value LIKE '%-scaled%';");

        // Delete records from wp_postmeta using a join with the temporary table
        $query = "
                DELETE pm
                FROM {$wpdb->postmeta} pm
                INNER JOIN tmp_post_ids tmp
                ON pm.post_id = tmp.post_id
                WHERE pm.meta_key = 's3_url';
            ";

        if ($dry_run) {
            WP_CLI::line('Dry run query: ' . $query);
        } else {
            $wpdb->query($query);
            WP_CLI::success('Postmeta records deleted successfully.');
        }

        // Drop the temporary table
        $wpdb->query('DROP TEMPORARY TABLE IF EXISTS tmp_post_ids;');
    }
}

if (defined('WP_CLI') && WP_CLI) {

    WP_CLI::add_command(
        WPS3_CLI_COMMAND,
       's3MediaOffloaderCLI',
        [
            'shortdesc' => 'Offload all images to S3333.'
        ]
    );
}
