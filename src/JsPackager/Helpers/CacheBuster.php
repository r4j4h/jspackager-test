<?php

/**
 * The CacheBuster class takes a path to a Js or Css file, and depending on configuration, returns a Url.
 *
 * @package JsPackager
 */

namespace JsPackager\Helpers;

class CacheBuster
{

    /**
     * Takes a path to a file, and configuration, passes back a URL (path).
     * @param $src string
     * @param $config {object} Uses $config->shared_path $config->use_cdn_for_shared $config->cdn
     * @return string
     */
    public function srcToSharedUrl($src, $config)
    {
        $baseUrl = $this->getBaseUrl();

        if ( !isset( $config->cdn ) )
        {
            throw new \Exception('Config is missing a CDN option block.');
        }

        $path = null;

        // Determine if $src already starts with $baseUrl
        if ( $baseUrl !== '' && substr( $src, 0, strlen($baseUrl) ) === $baseUrl )
        {
            // It already has baseUrl so we lets clean it out, because
            // if we are shared then we may want something inbetween baseUrl and the real src
            $src = str_replace( $baseUrl . '/', '', $src );
        }
        else if ( $baseUrl === '' )
        {
            // If baseUrl === '', Zend's function prepends a '/', so we need to remove it
            $src = ltrim( $src, '/' );
        }

        $cdnPath = $this->getCurrentCdnPath($config);
        $path = $cdnPath . '/' . $src;


        return $path;
    }

    /**
     * Returns the baseUrl for building paths to stylesheet URL.
     * @return string
     */
    public function getBaseUrl()
    {
        $basePath = '';

        return $basePath;
    }

    public function getCurrentCdnPath($config)
    {
        if (!$config->use_cdn_for_shared)
        {
            return $this->getDevelopmentCdnPath($config);
        }
        else
        {
            return $this->getProductionCdnPath($config);
        }
    }

    public function getDevelopmentCdnPath($config)
    {
        /** In developmentMode, the /shared subfolder goes between the baseUrl and the src. */

        $baseUrl = $this->getBaseUrl();

        // If baseUrl is not set, then we don't want to make a relative path absolute,
        // but baseUrls do not contain trailing slashes so otherwise we want one.
        if ( $baseUrl === '' )
        {
            $path = '/';
        }
        else
        {
            $path = $baseUrl . '/';
        }

        $path .= $config->cdn->cdn_shared_path;

        return $path;
    }

    public function getProductionCdnPath($config)
    {
        $cdnUrl = $config->cdn->url;
        $sharedPath = $config->cdn->cdn_shared_path;
        return $cdnUrl . '/' . $sharedPath;
    }

    public static function getCacheBustString($src, $filePath = '', $config = array())
    {
        // Sanity check against absence of usage flag
        if ( !isset( $config->use_cache_busting ) ) {
            // Default to off
            $usingCacheBuster = false;
        } else {
            $usingCacheBuster = $config->use_cache_busting;
        }

        // Respect usage flag
        if ( !$usingCacheBuster ) {
            return $src;
        }

        list($cacheBustingConfig, $cacheBustKey, $cacheBustStrategy) = self::getCacheBustSettings($config);

        // do cache bust strategy
        $cacheBustStrategies = array(
            'mtime' => function($filePath, $cacheBustingConfig) {
                $fileModifiedTime = filemtime($filePath);
                return $fileModifiedTime;
            },
            'constant' => function($filePath, $cacheBustingConfig) {
                $constantValueForCacheBust = isset( $cacheBustingConfig->constant_value ) ? $cacheBustingConfig->constant_value : '123';
                return $constantValueForCacheBust;
            }
        );

        if ( array_key_exists( $cacheBustStrategy, $cacheBustStrategies ) )
        {
            $cacheBustValue = call_user_func($cacheBustStrategies[$cacheBustStrategy], $filePath, $cacheBustingConfig);

            $fragment = parse_url($src, PHP_URL_FRAGMENT);
            $separator = (parse_url($src, PHP_URL_QUERY) == NULL) ? '?' : '&';

            if ( $fragment ) {
                $src = str_replace('#' . $fragment, '', $src);
            }

            while ( strpos($src, $cacheBustKey) !== false ) {
                $cacheBustKey = $cacheBustKey . 'z';
            }

            $cacheBustedSrc = $src . $separator . $cacheBustKey . '=' . $cacheBustValue;

            if ( $fragment ) {
                $cacheBustedSrc .= '#' . $fragment;
            }
            return $cacheBustedSrc;

        }

        return $src;
    }

    /**
     * @param $config
     * @return array
     */
    protected static function getCacheBustSettings($config)
    {
        $cacheBustingConfig = $config->cache_busting;

        if (!isset($config->key_string)) {
//            throw new \Exception('Missing cache bust key configuration value!');
        }
        if (!isset($config->cache_buster_strategy)) {
//            throw new \Exception('Missing cache bust strategy!');
        }

        $cacheBustKey = isset($cacheBustingConfig->key_string) ? $cacheBustingConfig->key_string : '_cachebust';
        $cacheBustStrategy = isset($cacheBustingConfig->strategy) ? $cacheBustingConfig->strategy : 'constant';
        return array($cacheBustingConfig, $cacheBustKey, $cacheBustStrategy);
    }

}