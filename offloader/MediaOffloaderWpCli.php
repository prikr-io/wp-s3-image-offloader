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

if (defined('WP_CLI') && WP_CLI) {

    class s3MediaOffloaderCLI extends WP_CLI_Command
    {

        private $mediaOffloader;
        private $s3Client;

        public function __construct()
        {
            $offloaderClass = new s3MediaOffloaderInit();
            $this->mediaOffloader = $offloaderClass->init();
            if(!$this->mediaOffloader) return;
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
                $images = $this->mediaOffloader->listMissingImagesBatch($batch_size, $offset);

                if (!empty($images)) {
                    // Log the IDs of the images.
                    foreach ($images as $image) {
                        WP_CLI::log("Image ID: {$image->ID}");
                        $this->mediaOffloader->offloadMedia($image->ID);
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
        public function delete($args, $assoc_args)
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
                WHERE meta_key = 's3_url';");

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


    WP_CLI::add_command(
        WPS3_CLI_COMMAND,
        's3MediaOffloaderCLI',
        [
            'shortdesc' => 'Offload all images to S3333.'
        ]
    );
}
