<?php
/*
Plugin Name: Prikr Image offloader
Plugin URI: https://prikr.io
Description: Offload images to AWS S3, including WP CLI and WPAI support.
Version: 2.6.4
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
$wps3_activate_offloading = get_option('wps3_image_offloader'); // Activate bucket
if (!isset($wps3_activate_offloading['wps3_activate_offloading'])) return;

define('WPS3_CLI_COMMAND', 'media-offloader');
define('WPS3_PATH', plugin_dir_path(__FILE__));
define('WPS3_BASENAME', plugin_basename(__FILE__));


// Admin attachment fields
require_once(__DIR__ . '/admin/mediaSettings.php');

// Offload images on upload
require_once(__DIR__ . '/offloader/MediaOffloaderClass.php');
// Offload images on upload
require_once(__DIR__ . '/offloader/MediaOffloaderInit.php');
// Offload images using the CLI
require_once(__DIR__ . '/offloader/MediaOffloaderWpCli.php');
// Offload single images via the Admin screen
require_once(__DIR__ . '/offloader/MediaOffloaderSingleItem.php');
// Offload images using AJAX calls
require_once(__DIR__ . '/offloader/MediaOffloaderBatchProcessing.php');


if (!isset($wps3_activate_offloading['wps3_activate_cdn'])) return;
/**
 * Filter the Image Attribute functions, to add custom sizes
 */
require_once(__DIR__ . '/functions/customSizesClass.php');
