<?php

/**
 * Project: Prikr image offloader
 * File: $(fileName)
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

        add_filter('wp_get_attachment_image_attributes', [$this, 'modifyImageAttributes'], 6, 3);
    }

    public function replaceImageUrl($originalUrl, $width, $height)
    {
        if (empty($this->bucketName)) {
            return $originalUrl;
        }

        $pattern = '/^https:\/\/(.+?)\/images\/(.+)$/i';
        $replacement = "https://{$this->bucketName}/fit-in/{$width}x{$height}/images/$2";

        $resizedUrl = preg_replace($pattern, $replacement, $originalUrl);

        return $resizedUrl;
    }

    public function modifyImageAttributes($attributes, $attachment, $size)
    {
        $imageUrl = $attributes['src'];
        if (!empty($attributes['s3width']) || !empty($attributes['s3height'])) {
            $width = !empty($attributes['s3width']) ? $attributes['s3width'] : null;
            $height = !empty($attributes['s3height']) ? $attributes['s3height'] : null;
            $attributes['src'] = $this->replaceImageUrl($imageUrl, $width, $height);
        } else {
            // If the s3 image sizes are not set, we want to get the original image size and set it as the s3 image size.
            $thumbnail_image = wp_get_attachment_image_src($attachment->ID, $size);
            if ($thumbnail_image) {
                $attributes['src'] = $this->replaceImageUrl($imageUrl, $thumbnail_image[1], $thumbnail_image[2]);
            }
        }
        return $attributes;
    }
}

new s3CustomSizes();
