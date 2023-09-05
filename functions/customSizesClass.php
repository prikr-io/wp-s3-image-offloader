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

    public function __construct()
    {
        add_filter('wp_get_attachment_image_attributes', [$this, 'modifyImageAttributes'], 6, 3);
    }

    public function replaceImageUrl($originalUrl, $width, $height)
    {
        $pattern = '/^(https:\/\/cdn.twentebestrating.nl\/)images\/(.+)$/i';

        $replacement = "$1fit-in/{$width}x{$height}/images/$2";

        $resizedUrl = preg_replace($pattern, $replacement, $originalUrl);

        return $resizedUrl;
    }

    public function modifyImageAttributes($attributes, $attachment, $size)
    {
        if (isset($attributes['s3width']) || isset($attributes['s3height'])) {
            $imageUrl = $attributes['src'];
            $width = isset($attributes['s3width']) ? $attributes['s3width'] : null;
            $height = isset($attributes['s3height']) ? $attributes['s3height'] : null;

            $attributes['src'] = $this->replaceImageUrl($imageUrl, $width, $height);
        }
        return $attributes;
    }
}

new s3CustomSizes();
