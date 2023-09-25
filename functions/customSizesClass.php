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


        add_filter(
            'intermediate_image_sizes_advanced',
            [$this, 'unsetDefaultWPImageSizes']
        );
        add_action('init', [$this, 'removeExtraImageSizes'], 11);
        add_filter('image_downsize', [$this, 'disableImageDownsize'], 10, 3);
    }

    public function replaceImageUrl($originalUrl, $width, $height, $dpr = 1)
    {
        if (empty($this->bucketName)) {
            return $originalUrl;
        }
        $dimensionsPattern = '/-\d+x\d+(?=\.[a-zA-Z]+$)/i';
        $originalUrl = preg_replace($dimensionsPattern, '', $originalUrl);

        $width = $width * $dpr;
        $height = $height * $dpr;


        $pattern = '/^https:\/\/(.+?)\/images\/(.+)$/i';
        $replacement = "https://{$this->bucketName}/fit-in/filters:no_upscale()/{$width}x{$height}/images/$2";

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


    public function unsetDefaultWPImageSizes($sizes)
    {
        $image_sizes = [
            'thumbnail',
            'medium',
            'medium_large',
            'large',
            '1920x1080', //custom
            'card_thumbnails', // custom
            'navigation_thumbnails', //custom
            'small', //custom
            'normal',  //custom
            '1536x1536',
            '2048x2048',
            'woocommerce_thumbnail',
            'woocommerce_single',
            'woocommerce_gallery_thumbnail',
        ];
        foreach ($image_sizes as $image_size) {
            unset($sizes[$image_size]);
        }
        return $sizes;
    }

    public function removeExtraImageSizes()
    {
        $image_sizes = [
            'thumbnail',
            'medium',
            'medium_large',
            'large',
            '1920x1080', //custom
            'card_thumbnails', // custom
            'navigation_thumbnails', //custom
            'small', //custom
            'normal',  //custom
            '1536x1536',
            '2048x2048',
            'woocommerce_thumbnail',
            'woocommerce_single',
            'woocommerce_gallery_thumbnail',
        ];
        foreach (get_intermediate_image_sizes() as $size) {
            if (in_array($size, $image_sizes)) {
                remove_image_size($size);
            }
        }
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
