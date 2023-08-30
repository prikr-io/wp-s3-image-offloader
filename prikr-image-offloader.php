<?php
/*
Plugin Name: Prikr Image offloader
Plugin URI: https://prikr.io
Description: Offload WP images to S3, inclusding WPAI support.
Version: 1.0
Author: Prikr
Author URI: https://prikr.io/
License: GPLv2 or later
*/
defined('ABSPATH') || exit;

function wps3_get_plugin_info()
{
    $info = [
        'slug' => plugin_basename(__DIR__),
        'filename' => plugin_basename(__FILE__),
    ];
    return $info;
}
/**
 * Updater file for private repo
 */
require_once(__DIR__ . '/updater/UpdaterClass.php');

/**
 * WordPress settings page
 */
require_once(__DIR__ . '/admin/settingspage.php');

/**
 * Check wether the plugin is activated through it's own options page.
 */
$wps3_activate_offloading = get_option('wps3_image_offloader_option_name')['wps3_activate_offloading']; // Activate bucket
if (!$wps3_activate_offloading) return;

/**
 * Media offloader to AWS S3.
 */
require_once(__DIR__ . '/offloader/MediaOffloaderClass.php');
