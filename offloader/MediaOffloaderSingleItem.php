<?php

/**
 * Project: prikr-image-offloader
 * Author: Koen Dolron
 * Copyright © Prikr 
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

require 'vendor/autoload.php';

use Aws\S3\S3Client;
use Aws\Credentials\Credentials;
use GuzzleHttp\Promise\Promise;

class s3MediaOffloaderSingleItem
{
    private $s3Client;
    private $offloader;

    private function custom_wp_database_credentials_provider()
    {
        return function () {
            $promise = new Promise(function () use (&$promise) {
                $options = get_option('wps3_image_offloader');
                $aws_key = $options['wps3_aws_key'];
                $aws_secret = $options['wps3_aws_secret'];

                if (empty($aws_key) || empty($aws_secret)) {
                    $promise->reject('AWS credentials are not set in the WP database.');
                } else {
                    $promise->resolve(new Credentials($aws_key, $aws_secret));
                }
            });

            return $promise;
        };
    }

    public function __construct()
    {
        $options = get_option('wps3_image_offloader');
        $bucket_name = $options['wps3_bucket_name'];
        $bucket_region = $options['wps3_bucket_region'];
        $aws_key = $options['wps3_aws_key'];
        $aws_secret = $options['wps3_aws_secret'];
        $s3Path = 'images/';

        if (empty($bucket_region) || empty($bucket_name) || empty($aws_key) || empty($aws_secret)) {
            error_log('wps3 Error: Missing bucket region, bucket name, aws key, or aws secret. Please check your settings.');
            return false;
        }

        /**
         * Disable AWS looking for the '/.aws/config' file which gives a million notices: is_readable(): open_basedir restriction in effect
         * This is a dirty hack, but it works.
         * @see https://github.com/aws/aws-sdk-php/issues/1931
         */
        putenv('AWS_CONFIG_FILE='.__DIR__.'/fakeConfigFile.ini');

        $this->s3Client = new S3Client([
            'region' => $bucket_region,
            'version' => 'latest',
            'credentials' => $this->custom_wp_database_credentials_provider()
        ]);

        $this->offloader = new s3MediaOffloader($this->s3Client, $bucket_name, $s3Path);

        // Hook the function to run on admin_init
        add_action('admin_init', array($this, 'get_offload_id_from_url'));
    }

    public function get_offload_id_from_url()
    {
        $current_url = $_SERVER['REQUEST_URI'];
        $url_parts = parse_url($current_url);
        // Check if the "query" key is set
        if (isset($url_parts['query'])) {
            parse_str($url_parts['query'], $query_params);
            // Check if the "offload" parameter is set
            if (isset($query_params['offload'])) {
                $offload_id = $query_params['offload'];
                $this->offloader->offloadMedia($offload_id);
            }
        }
    }
}

// Instantiate the class
$s3MediaOffloaderSingleItem = new s3MediaOffloaderSingleItem();
