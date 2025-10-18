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
    private $bucketScheme = 'https';
    private $enablePlaceholders = false;
    private $enableSoftTransition = false;

    public function __construct()
    {
        $options = get_option('wps3_image_offloader');
        $rawBucketName = isset($options['wps3_bucket_name']) ? $options['wps3_bucket_name'] : '';
        $this->bucketScheme = $this->detectBucketScheme($rawBucketName);
        $this->bucketName = $this->sanitizeBucketName($rawBucketName);
        $this->enablePlaceholders = isset($options['wps3_enable_lqip']) && $options['wps3_enable_lqip'] === 'wps3_enable_lqip';
        $this->enableSoftTransition = $this->enablePlaceholders
            && isset($options['wps3_enable_lqip_transition'])
            && $options['wps3_enable_lqip_transition'] === 'wps3_enable_lqip_transition';

        add_filter('wp_get_attachment_url', [$this, 'replaceAttachmentUrl'], 10, 2);
        add_filter('wp_get_attachment_image_src', [$this, 'alwaysReturnFullImageSrc'], 10, 4);
        add_filter('wp_get_attachment_image_attributes', [$this, 'buildImageAttributes'], 6, 3);
        add_filter('image_downsize', [$this, 'disableImageDownsize'], 11, 3);
        add_filter('wp_prepare_attachment_for_js', [$this, 'replaceAttachmentUrlsForJSimages'], 10, 3);
        add_filter('wp_calculate_image_srcset', [$this, 'overrideSrcset'], 10, 5);
        add_filter('admin_post_thumbnail_html', [$this, 'filterAdminPostThumbnailHtml'], 10, 3);
        if ($this->enablePlaceholders) {
            add_action('wp_enqueue_scripts', [$this, 'enqueueProgressiveImageScript']);
            add_action('wp_head', [$this, 'outputLqipStyles'], 20);
        }
        add_action('wp_head', [$this, 'outputPreconnectTag'], 1);
    }

    /**
     * Filter the admin post thumbnail HTML to add the S3 URL as a data attribute.
     * This will allow us to use the S3 URL in the media library.
     */
    public function filterAdminPostThumbnailHtml($content, $post_id, $thumbnail_id)
    {
        $url = wp_get_attachment_url($thumbnail_id);
        $s3  = get_post_meta($thumbnail_id, 's3_url', true);

        if ($s3) {
            $thumb_url = $this->replaceImageUrl($s3, 150, 150);
            $content = str_replace($url, $thumb_url, $content);
        }

        return $content;
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

        $imageUrl = wp_get_attachment_url($attachment_id);
        if (!$imageUrl) {
            return $sources;
        }

        $generatedSources = $this->buildSrcsetCandidates(
            $attachment_id,
            $imageUrl,
            isset($size_array[0]) ? (int) $size_array[0] : null,
            isset($size_array[1]) ? (int) $size_array[1] : null
        );

        if (empty($generatedSources)) {
            return $sources;
        }

        $new_sources = [];

        foreach ($generatedSources as $width => $url) {
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
        if (!$imageUrl) {
            return $attributes;
        }

        unset($attributes['data-placeholder'], $attributes['data-full-src'], $attributes['data-full-srcset']);

        $dimensions = $this->determineTargetDimensions($attributes, $attachment->ID, $size);
        $width = $dimensions['width'];
        $height = $dimensions['height'];

        if (!$width || !$height) {
            return $attributes;
        }

        $fullImageUrl = $this->replaceImageUrl($imageUrl, $width, $height);
        if (!$fullImageUrl) {
            return $attributes;
        }

        $srcsetCandidates = $this->buildSrcsetCandidates($attachment->ID, $imageUrl, $width, $height);
        $srcsetString = '';
        if (!empty($srcsetCandidates)) {
            $srcsetString = $this->formatSrcsetString($srcsetCandidates);
        }

        if ($this->enablePlaceholders) {
            $placeholderUrl = $this->buildPlaceholderUrl($imageUrl, $width, $height);

            if ($placeholderUrl && $placeholderUrl !== $fullImageUrl) {
                $attributes['src'] = esc_url($placeholderUrl);
                $attributes['data-placeholder'] = esc_url($placeholderUrl);
                $attributes['data-full-src'] = esc_url($fullImageUrl);
                if ($srcsetString) {
                    $attributes['srcset'] = $srcsetString;
                    $attributes['data-full-srcset'] = $srcsetString;
                } else {
                    unset($attributes['srcset']);
                }

                $attributes['class'] = isset($attributes['class'])
                    ? trim($attributes['class'] . ' s3-image--lqip')
                    : 's3-image--lqip';

                return $attributes;
            }
        }

        $attributes['src'] = esc_url($fullImageUrl);
        if ($srcsetString) {
            $attributes['srcset'] = $srcsetString;
        } else {
            unset($attributes['srcset']);
        }

        if (isset($attributes['class']) && strpos($attributes['class'], 's3-image--lqip') !== false) {
            $attributes['class'] = trim(str_replace(['s3-image--lqip-ready', 's3-image--lqip'], '', $attributes['class']));
            $attributes['class'] = trim(preg_replace('/\s+/', ' ', $attributes['class']));
            if ($attributes['class'] === '') {
                unset($attributes['class']);
            }
        }

        return $attributes;
    }

    /**
     * Build the image URL based on the width, height and DPR.
     * This will result in an AWS thumbor filter.
     */
    public function replaceImageUrl($originalUrl, $width = null, $height = null, $dpr = 1, array $extraFilters = [])
    {
        if (empty($originalUrl)) {
            return $originalUrl;
        }

        $bucketName = $this->bucketName;

        if (empty($bucketName) || empty($width) || empty($height)) {
            return $originalUrl;
        }

        $calculatedWidth = max(1, (int) round($width * $dpr));
        $calculatedHeight = max(1, (int) round($height * $dpr));

        $filterParts = array_merge(['quality(96)', 'no_upscale()'], array_filter($extraFilters));
        $filterString = 'filters:' . implode(':', $filterParts);

        $pattern = '/^https?:\/\/(.+?)\/images\/(.+)$/i';
        $baseUrl = $this->buildBucketBaseUrl();
        if (empty($baseUrl)) {
            return $originalUrl;
        }
        $replacement = sprintf(
            '%s/fit-in/%s/%dx%d/images/$2',
            $baseUrl,
            $filterString,
            $calculatedWidth,
            $calculatedHeight
        );

        return preg_replace($pattern, $replacement, $originalUrl);
    }

    /**
     * Determine which width and height should be used for the current image.
     */
    private function determineTargetDimensions($attributes, $attachmentId, $size)
    {
        $width = null;
        $height = null;

        if (!empty($attributes['s3width']) || !empty($attributes['s3height'])) {
            $width = !empty($attributes['s3width']) ? (int) $attributes['s3width'] : null;
            $height = !empty($attributes['s3height']) ? (int) $attributes['s3height'] : null;
        } else {
            $thumbnailImage = wp_get_attachment_image_src($attachmentId, $size);
            if ($thumbnailImage && is_array($thumbnailImage) && count($thumbnailImage) >= 3) {
                $width = (int) $thumbnailImage[1];
                $height = (int) $thumbnailImage[2];
            }
        }

        if ((!$width || !$height)) {
            $imageMeta = wp_get_attachment_metadata($attachmentId);
            if ($imageMeta) {
                if (!$width && !empty($imageMeta['width'])) {
                    $width = (int) $imageMeta['width'];
                }
                if (!$height && !empty($imageMeta['height'])) {
                    $height = (int) $imageMeta['height'];
                }
            }
        }

        return [
            'width'  => $width ?: null,
            'height' => $height ?: null,
        ];
    }

    /**
     * Build the srcset candidates with S3 URLs.
     */
    private function buildSrcsetCandidates($attachmentId, $imageUrl, $fallbackWidth = null, $fallbackHeight = null)
    {
        $sources = [];
        $imageMeta = wp_get_attachment_metadata($attachmentId);

        if ($imageMeta && !empty($imageMeta['sizes']) && is_array($imageMeta['sizes'])) {
            foreach ($imageMeta['sizes'] as $sizeInfo) {
                if (empty($sizeInfo['width']) || empty($sizeInfo['height'])) {
                    continue;
                }

                $width = (int) $sizeInfo['width'];
                $height = (int) $sizeInfo['height'];
                $variantUrl = $this->replaceImageUrl($imageUrl, $width, $height);

                if ($variantUrl) {
                    $sources[$width] = $variantUrl;
                }
            }
        }

        if ($imageMeta && !empty($imageMeta['width']) && !empty($imageMeta['height'])) {
            $originalWidth = (int) $imageMeta['width'];
            $originalHeight = (int) $imageMeta['height'];
            $sources[$originalWidth] = $this->replaceImageUrl($imageUrl, $originalWidth, $originalHeight);
        }

        if ($fallbackWidth && $fallbackHeight) {
            $fallbackWidth = (int) $fallbackWidth;
            $fallbackHeight = (int) $fallbackHeight;
            $sources[$fallbackWidth] = $this->replaceImageUrl($imageUrl, $fallbackWidth, $fallbackHeight);
        }

        ksort($sources, SORT_NUMERIC);

        return $sources;
    }

    /**
     * Format the srcset string from the provided candidates.
     */
    private function formatSrcsetString($sources)
    {
        if (empty($sources)) {
            return '';
        }

        $entries = [];
        foreach ($sources as $width => $url) {
            if (empty($url) || empty($width)) {
                continue;
            }
            $entries[] = esc_url($url) . ' ' . (int) $width . 'w';
        }

        return implode(', ', $entries);
    }

    /**
     * Provide a low-fidelity placeholder URL for progressive loading.
     */
    private function buildPlaceholderUrl($imageUrl, $width, $height)
    {
        if (!$this->enablePlaceholders) {
            return '';
        }

        $width = (int) $width;
        $height = (int) $height;

        if ($width <= 0 || $height <= 0) {
            return '';
        }

        $placeholderWidth = max(20, (int) round($width * 0.07));
        $placeholderHeight = max(20, (int) round($height * 0.07));

        return $this->replaceImageUrl(
            $imageUrl,
            $placeholderWidth,
            $placeholderHeight,
            1,
            ['blur(30)']
        );
    }

    /**
     * Determine which scheme should be used for the CDN endpoint.
     */
    private function detectBucketScheme($bucketName)
    {
        if (preg_match('#^(https?):\/\/#i', $bucketName, $matches)) {
            return strtolower($matches[1]);
        }

        return 'https';
    }

    /**
     * Ensure the bucket name is stored without protocol or trailing slash.
     */
    private function sanitizeBucketName($bucketName)
    {
        $bucketName = trim((string) $bucketName);
        if ($bucketName === '') {
            return '';
        }

        $bucketName = preg_replace('#^(https?:)?\/\/#i', '', $bucketName);
        if ($bucketName === null) {
            $bucketName = '';
        }

        return trim($bucketName, '/');
    }

    /**
     * Provide the base URL for the CDN using the stored configuration.
     */
    private function buildBucketBaseUrl()
    {
        if (empty($this->bucketName)) {
            return '';
        }

        return sprintf('%s://%s', $this->bucketScheme, $this->bucketName);
    }

    /**
     * Ensure the placeholder is replaced with the high-quality asset where needed.
     */
    public function enqueueProgressiveImageScript()
    {
        if (!$this->enablePlaceholders) {
            return;
        }

        if (is_admin()) {
            return;
        }

        $handle = 'prikr-image-offloader-lqip';

        if (wp_script_is($handle, 'enqueued')) {
            return;
        }

        wp_register_script($handle, '', [], null, true);
        wp_enqueue_script($handle);

        $script = <<<'JS'
(function () {
    if (typeof window === 'undefined' || typeof document === 'undefined') {
        return;
    }

    var supportsSrcset = 'srcset' in document.createElement('img');

    var onReady = function (callback) {
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', callback);
        } else {
            callback();
        }
    };

    onReady(function () {
        var images = document.querySelectorAll('img.s3-image--lqip');
        for (var i = 0; i < images.length; i++) {
            (function (img) {
                if (!supportsSrcset) {
                    var fullSrc = img.getAttribute('data-full-src');
                    if (fullSrc) {
                        img.setAttribute('src', fullSrc);
                    }
                    var fullSrcset = img.getAttribute('data-full-srcset');
                    if (fullSrcset) {
                        img.setAttribute('srcset', fullSrcset);
                    }
                }

                var onLoad = function () {
                    var placeholderSrc = img.getAttribute('data-placeholder');
                    var currentSrc = img.currentSrc || img.src;

                    if (placeholderSrc && currentSrc && placeholderSrc === currentSrc) {
                        return;
                    }

                    if (img.classList && img.classList.add) {
                        img.classList.add('s3-image--lqip-ready');
                    } else {
                        var existing = img.getAttribute('class') || '';
                        if (existing.indexOf('s3-image--lqip-ready') === -1) {
                            img.setAttribute('class', (existing ? existing + ' ' : '') + 's3-image--lqip-ready');
                        }
                    }

                    img.removeEventListener('load', onLoad);
                };

                img.addEventListener('load', onLoad);

                if (img.complete) {
                    onLoad();
                }
            })(images[i]);
        }
    });
})();
JS;

        wp_add_inline_script($handle, $script);
    }

    /**
     * Optionally output inline CSS to smooth the transition from placeholder to full image.
     */
    public function outputLqipStyles()
    {
        if (!$this->enablePlaceholders || !$this->enableSoftTransition) {
            return;
        }

        if (is_admin()) {
            return;
        }

        echo '<style id="prikr-image-offloader-lqip-css">img.s3-image--lqip{filter:blur(12px);opacity:0.65;transition:filter 0.4s ease,opacity 0.4s ease;}img.s3-image--lqip.s3-image--lqip-ready{filter:blur(0);opacity:1;}</style>' . PHP_EOL;
    }

    /**
     * Output a preconnect tag for the configured CDN domain.
     */
    public function outputPreconnectTag()
    {
        if (is_admin()) {
            return;
        }

        $options = get_option('wps3_image_offloader');
        $cdnActive = isset($options['wps3_activate_cdn']) && $options['wps3_activate_cdn'] === 'wps3_activate_cdn';

        if (!$cdnActive) {
            return;
        }

        $baseUrl = $this->buildBucketBaseUrl();
        if (empty($baseUrl)) {
            return;
        }

        printf('<link rel="preconnect" href="%s" crossorigin />' . PHP_EOL, esc_url($baseUrl));
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
