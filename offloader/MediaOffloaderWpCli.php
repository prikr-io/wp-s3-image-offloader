<?php
require 'MediaOffloaderClass.php';
if (defined('WP_CLI') && WP_CLI) {
    class WPCLI_Custom_Command
    {


        /**
         * Get images without 's3_url' post meta and log their IDs.
         *
         * ## OPTIONS
         *
         * [--batch-size=<batch_size>]
         * : The number of images to process in each batch. Default is 10.
         *
         * [--timeout=<timeout>]
         * : The number of seconds to wait between batches. Default is 5 seconds.
         *
         * ## EXAMPLES
         *
         *     wp custom-command get-images-without-s3-url
         *
         * @param array $args Command arguments.
         * @param array $assoc_args Command associative arguments.
         */
        public function get_images_without_s3_url($args, $assoc_args)
        {
            $batch_size = isset($assoc_args['batch-size']) ? intval($assoc_args['batch-size']) : 10;
            $timeout = isset($assoc_args['timeout']) ? intval($assoc_args['timeout']) : 5;
            $offset = 0;

            // Init the offloader
            $options = get_option('wps3_image_offloader_option_name'); // Array of All Options
            $bucket_name = $options['wps3_bucket_name']; // Bucket name
            $bucket_region = $options['wps3_bucket_region']; // Bucket region
            $s3Offloader = new s3MediaOffloader($bucket_name, $bucket_region);

            do {
                // Get a batch of images without 's3_url' post meta.
                $images = $this->get_images_without_s3_url_batch($batch_size, $offset);

                if (!empty($images)) {
                    // Log the IDs of the images.
                    foreach ($images as $image) {
                        WP_CLI::log("Image ID: {$image->ID}");
                        $s3Offloader->offloadMedia($image->ID);
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
        private function get_images_without_s3_url_batch($batch_size, $offset)
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
    }

    WP_CLI::add_command('custom-command', 'WPCLI_Custom_Command');
}