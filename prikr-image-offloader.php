<?php
/*
Plugin Name: Prikr Image offloader
Plugin URI: https://prikr.io
Description: Offload WP images to S3.
Version: 1.0.0
Author: Prikr
Author URI: https://prikr.io/
License: GPLv2 or later
*/


/**
 * WordPress settings page
 */
require_once(__DIR__ . '/admin/settingspage.php');

/**
 * Check wether the plugin is activated through it's own options page.
 */
$activate_s3_offloading = get_option('image_offloader_option_name')['activate_s3_offloading']; // Activate bucket
if (!$activate_s3_offloading) return;

/**
 * Media offloader to AWS S3.
 */
require_once(__DIR__ . '/offloader/MediaOffloaderClass.php');
