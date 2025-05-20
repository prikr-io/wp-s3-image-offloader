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
        add_filter('wp_calculate_image_srcset', [$this, 'overrideSrcset'], 10, 5);
        add_filter('admin_post_thumbnail_html', function ($content, $post_id, $thumbnail_id) {
            $url = wp_get_attachment_url($thumbnail_id);
            $s3 = get_post_meta($thumbnail_id, 's3_url', true);
            if ($s3) {
                $thumb_url = (new s3CustomSizes)->replaceImageUrl($s3, 150, 150); // bijv. thumbnail
                $content = str_replace($url, $thumb_url, $content);
            }
            return $content;
        }, 10, 3);
    }

    /**
     * Override the srcset attribute for images in the media library.
     * This will replace the srcset with the S3 URL and the respective width and height values.
     * This is important for responsive images, as the srcset attribute is used to load different image sizes based on the screen size.
     * @param array $sources
     * @param array $size_array
     * @param string $image_src
     * @param array $image_meta
     * @param int $attachment_id
     */
    public function overrideSrcset($sources, $size_array, $image_src, $image_meta, $attachment_id)
    {
        $s3_url = get_post_meta($attachment_id, 's3_url', true);
        if (!$s3_url || empty($image_meta['sizes'])) {
            return $sources;
        }

        $new_sources = [];

        foreach ($image_meta['sizes'] as $size_name => $size_info) {
            $width = $size_info['width'];
            $height = $size_info['height'];
            $url = $this->replaceImageUrl($s3_url, $width, $height);

            $new_sources[$width] = [
                'url'        => $url,
                'descriptor' => 'w',
                'value'      => $width,
            ];
        }

        return $new_sources;
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
        $s3_url = get_post_meta($attachment->ID, 's3_url', true);
        if (!empty($s3_url)) {
            foreach ($response['sizes'] as $key => $size) {
                $response['sizes'][$key]['url'] = $this->replaceImageUrl($size['url'], $size['width'], $size['height']);
                // It is important to remove the dimensions from the URL, as LAST. Else JS will take over and add the dimensions again.
                $dimensionsPattern = '/-\d+x\d+(?=\.[a-zA-Z]+$)/i';
                $response['sizes'][$key]['url'] = preg_replace($dimensionsPattern, '', $response['sizes'][$key]['url']);
            }
            return $response;
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
        if (is_array($image) && isset($image[0])) {
            // TODO perhaps use an WP function instead of a regex..
            $dimensionsPattern = '/-\d+x\d+(?=\.[a-zA-Z]+$)/i';
            // Zorg ervoor dat $image[0] een string is voordat preg_replace wordt toegepast
            if (is_string($image[0])) {
                $image[0] = preg_replace($dimensionsPattern, '', $image[0]);
            }
        }
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

            $attributes['srcset'] = $this->replaceImageUrl($imageUrl, $width, $height, 2) . ' 2x';
        } else {
            // If the s3 image sizes are not set, we want to get the original image size and set it as the s3 image size.
            $thumbnail_image = wp_get_attachment_image_src($attachment->ID, $size);
            if ($thumbnail_image && is_array($thumbnail_image) && count($thumbnail_image) >= 3) {
                $width = $thumbnail_image[1];
                $height = $thumbnail_image[2];
                $attributes['src'] = $this->replaceImageUrl($imageUrl, $width, $height);
                $attributes['srcset'] = $this->replaceImageUrl($imageUrl, $width, $height, 2) . ' 2x';
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
        // Bepaal de originele URL
        $original_image_url = wp_get_attachment_url($attachment_id);

        // Als het een array is (zoals [400, 300]) → zoals je al had
        if (is_array($size)) {
            return [$original_image_url, $size[0], $size[1], false];
        }

        // Als het een string is (zoals 'thumbnail', 'medium' etc.)
        if (is_string($size)) {
            $image_meta = wp_get_attachment_metadata($attachment_id);
            if (!isset($image_meta['sizes'][$size])) {
                return false;
            }

            $width  = $image_meta['sizes'][$size]['width'];
            $height = $image_meta['sizes'][$size]['height'];

            return [$this->replaceImageUrl($original_image_url, $width, $height), $width, $height, false];
        }

        return false;
    }
}

new s3CustomSizes();
