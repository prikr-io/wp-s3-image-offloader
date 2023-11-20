<?php

/**
 * Project: prikr-image-offloader
 * Author: Koen Dolron
 * Copyright © Prikr 
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly


class s3CustomSizes
{
    private $bucketName;

    public function __construct()
    {
        $options = get_option('wps3_image_offloader');
        $this->bucketName = isset($options['wps3_bucket_name']) ? $options['wps3_bucket_name'] : '';

        add_filter('wp_get_attachment_url', [$this, 'replaceAttachmentUrl'], 10, 2);
        add_filter('wp_get_attachment_image_src', [$this, 'alwaysReturnFullImageSrc'], 10, 4);
        add_filter('wp_get_attachment_image_attributes', [$this, 'buildImageAttributes'], 6, 3);
        add_filter('image_downsize', [$this, 'disableImageDownsize'], 11, 3);
        add_filter('wp_prepare_attachment_for_js', [$this, 'replaceAttachmentUrlsForJSimages'], 10, 3);
    }

    /**
     * Replaces the Image URL with the S3 URL if available. 
     * So that we can actually load in the image within WP.
     */
    public function replaceAttachmentUrl($url, $attachment_id)
    {
        if ($attachment_id) {
            $s3_url = get_post_meta($attachment_id, 's3_url', true);
            if ($s3_url) {
                $url = $s3_url;
            }
        }
        return $url;
    }

    /**
     * WordPress uses the wp_prepare_attachment_for_js filter to prepare the attachment data for the media library.
     * We will filter each image size and re-format the URL to the S3 URL, with the respective width and height values
     */
    public function replaceAttachmentUrlsForJSimages($response, $attachment, $meta)
    {
        foreach ($response['sizes'] as $key => $size) {
            $response['sizes'][$key]['url'] = $this->replaceImageUrl($size['url'], $size['width'], $size['height']);
            // It is important to remove the dimensions from the URL, as LAST. Else JS will take over and add the dimensions again.
            $dimensionsPattern = '/-\d+x\d+(?=\.[a-zA-Z]+$)/i';
            $response['sizes'][$key]['url'] = preg_replace($dimensionsPattern, '', $response['sizes'][$key]['url']);
        }
        return $response;
    }

    /**
     * Disregard the image size used in a image function and always return the full image src.
     * get_the_post_thumbnail($id, 'thumbnail'), wp_get_attachment_image($id, 'thumbnail')
     * Those will all return full URL.
     */
    function alwaysReturnFullImageSrc($image, $attachment_id, $size, $icon)
    {
        // TODO perhaps use an WP function instead of a regex..
        $dimensionsPattern = '/-\d+x\d+(?=\.[a-zA-Z]+$)/i';
        $image[0] = preg_replace($dimensionsPattern, '', $image[0]);
        return $image;
    }

    /**
     * Build the image attributes using the width and height attributes.
     */
    public function buildImageAttributes($attributes, $attachment, $size)
    {
        $imageUrl = wp_get_attachment_url($attachment->ID);
        if (!empty($attributes['s3width']) || !empty($attributes['s3height'])) {
            $width = !empty($attributes['s3width']) ? $attributes['s3width'] : null;
            $height = !empty($attributes['s3height']) ? $attributes['s3height'] : null;
            $attributes['src'] = $this->replaceImageUrl($imageUrl, $width, $height);

            $attributes['srcset'] = $this->replaceImageUrl($imageUrl, $width, $height, 2) . ' 2x, ' . $this->replaceImageUrl($imageUrl, $width, $height, 3) . ' 3x';
        } else {
            // If the s3 image sizes are not set, we want to get the original image size and set it as the s3 image size.
            $thumbnail_image = wp_get_attachment_image_src($attachment->ID, $size);
            if ($thumbnail_image) {
                $width = $thumbnail_image[1];
                $height = $thumbnail_image[2];
                $attributes['src'] = $this->replaceImageUrl($imageUrl, $width, $height);
                $attributes['srcset'] = $this->replaceImageUrl($imageUrl, $width, $height, 2) . ' 2x, ' . $this->replaceImageUrl($imageUrl, $width, $height, 3) . ' 3x';
            }
        }
        return $attributes;
    }

    /**
     * Build the image URL based on the width, height and DPR.
     * This will result in an AWS thumbor filter.
     */
    public function replaceImageUrl($originalUrl, $width, $height, $dpr = 1)
    {
        if (empty($this->bucketName)) {
            return $originalUrl;
        }

        $width = $width * $dpr;
        $height = $height * $dpr;


        $pattern = '/^https:\/\/(.+?)\/images\/(.+)$/i';
        $replacement = "https://{$this->bucketName}/fit-in/filters:no_upscale()/{$width}x{$height}/images/$2";

        $resizedUrl = preg_replace($pattern, $replacement, $originalUrl);

        return $resizedUrl;
    }

    /**
     * Disable default image downsizing.
     * When a custom $size attribute is used in the wp_get_attachment_image function, it will automatically try to downsize to a default image size.
     * For example: wp_get_attachment_image($id, [400, 600]) will automatically downsize to the 'thumbnail' size, instead of using the actual 400x600 sizes.
     */
    public function disableImageDownsize($downsize, $attachment_id, $size)
    {
        // Check if the $size attribute is array. So only run on [400, 600] and not on 'thumbnail' or 'medium_large' etc.
        if (isset($size) && is_array($size)) {
            // Return the original image data without downsizing
            $original_image_url = wp_get_attachment_url($attachment_id);
            $downsize = array($original_image_url, $size[0], $size[1], false);
        }

        return $downsize;
    }
}

new s3CustomSizes();
