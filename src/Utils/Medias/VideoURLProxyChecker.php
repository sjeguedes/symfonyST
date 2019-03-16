<?php

declare(strict_types=1);

namespace App\Utils\Medias;

use Symfony\Component\HttpFoundation\Request;

/*
 * Class VideoURLProxyChecker.
 *
 * Check if a video URL can be correctly loaded.
 * .
 */
class VideoURLProxyChecker
{
    const ALLOWED_URL_PATTERNS = [
        '/^https?\:\/\/www\.youtube\.com\/embed\/.+$/',
        '/^https?\:\/\/player\.vimeo\.com\/video\/.+$/',
        '/^https?\:\/\/www\.dailymotion\.com\/embed\/video\/.+$/'
    ];

    /**
     * Filter provided URL.
     *
     * @param Request $request
     *
     * @return null|string
     */
    public function filterURLAttribute(Request $request) : ?string
    {
        // Get URL to check
        if ($request->attributes->has('url')) {
            $url = $request->attributes->get('url');
        } elseif ($request->query->has('url')) {
            $url = $request->query->get('url');
        } else {
            $url = null;
        }
        return $url;
    }

    /**
     * Check if URL format is allowed.
     *
     * @param string $url
     *
     * @return bool
     */
    private function allow(string $url) : bool
    {
        $patterns = self::ALLOWED_URL_PATTERNS;
        for ($i = 0; $i < count($patterns); $i ++) {
            if (preg_match( $patterns[$i], $url)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get URL as resource to check if a content can be loaded.
     *
     * @param string $url
     *
     * @return bool
     */
    private function isContent(string $url) : bool
    {
        $resource = @fopen($url,'r');
        $isResource = is_resource($resource) ? true : false;
        fclose($resource);
        return $isResource;
    }

    /**
     * Return a status code to be converted later in JSON string
     *
     * value 1 means URL can be loaded and value 0 means error context must be used!
     *
     * @param string $url
     *
     * @return array
     *
     * @see https://symfony.com/doc/current/controller.html#returning-json-response
     */
    public function verify(string $url) : array
    {
        // Prepare array to be converted in JSOn string with Symfony JsonResponse object (no need to use "json_encode" here)
        if (empty($url)) {
            return ['status' => 0];
        }
        return $this->allow($url) && $this->isContent($url) ?  ['status' => 1] :  ['status' => 0];
    }

}