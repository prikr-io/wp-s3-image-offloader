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

class s3MediaOffloaderInit
{
    private $s3Client;
    private $offloader;


    public function __construct()
    {
    }

    public function custom_wp_database_credentials_provider()
    {
        $provider = function () {
            $promise = new Promise(function () use (&$promise) {
                $options = get_option('wps3_image_offloader');
                $aws_key = $options['wps3_aws_key'];
                $aws_secret = $options['wps3_aws_secret'];

                if (empty($aws_key) || empty($aws_secret)) {
                    $promise->reject('No AWS credentials found in the database.');
                } else {
                    $promise->resolve(new Credentials($aws_key, $aws_secret));
                }
            });

            return $promise;
        };

        return $provider;
    }

    public function init()
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

        $customProvider = $this->custom_wp_database_credentials_provider();

        $this->s3Client = new S3Client([
            'region' => $bucket_region,
            'version' => 'latest',
            'use_aws_shared_config_files' => false,
            'credentials' => $customProvider
        ]);

        $this->offloader = new s3MediaOffloader($this->s3Client, $bucket_name, $s3Path);
        $this->offloader->init();
        return $this->offloader;
    }
}
