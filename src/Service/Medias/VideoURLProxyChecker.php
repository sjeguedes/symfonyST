<?php

declare(strict_types = 1);

namespace App\Service\Medias;

use Symfony\Component\HttpFoundation\Request;

/*
 * Class VideoURLProxyChecker.
 *
 * Check if a video URL can be correctly loaded.
 * .
 */
class VideoURLProxyChecker
{
    // CAUTION: these iframe URL patterns should certainly be improved and are very important for a quite "secure" use!
    // Even more, they can evolve, so it is preferable to use providers APIs!
    const ALLOWED_URL_PATTERNS = [
        '/^https?:\/\/www\.youtube\.com\/embed\/[a-zA-Z0-9_-]+$/', // [\w-]+
        '/^https?:\/\/player\.vimeo\.com\/video\/[0-9]+$/',
        '/^https?:\/\/www\.dailymotion\.com\/embed\/video\/[a-zA-Z0-9]+$/'
    ];

    /**
     * Filter provided URL.
     *
     * @param Request $request
     * @param bool    $isDecoded
     *
     * @return null|string
     */
    public function filterURLAttribute(Request $request, bool $isDecoded = false) : ?string
    {
        // Get URL to check
        $url = null;
        if (!\is_null($request->attributes->get('url'))) {
            $url = $request->attributes->get('url');
        }
        return $isDecoded ? $url : urldecode($url);
    }

    /**
     * Check if URL format is allowed.
     *
     * @param string|null $url
     *
     * @return bool
     */
    public function isAllowed(?string $url) : bool
    {
        if (\is_null($url)) {
            return false;
        }
        $patterns = self::ALLOWED_URL_PATTERNS;
        // Use of "array_filter" would be more appropriate here!
        for ($i = 0; $i < count($patterns); $i ++) {
            if (preg_match( $patterns[$i], urldecode($url))) {
                return true;
            }
        }
        return false;
    }

    /**
     * Request URL to check if a content can be loaded. Choice is made to use cURL here.
     *
     * CAUTION: do not use this method alone because of potential "SSRF" attacks! At least use isAllowed() before...
     * @link https://www.vaadata.com/blog/understanding-web-vulnerability-server-side-request-forgery-1/
     *
     * @param string|null $url
     *
     * @return bool
     *
     * @see https://stackoverflow.com/questions/408405/easy-way-to-test-a-url-for-404-in-php
     * @see https://css-tricks.com/snippets/php/check-if-website-is-available/
     * @see https://aboutssl.org/fix-ssl-certificate-problem-unable-to-get-local-issuer-certificate/
     * @see https://blog.petehouston.com/fix-ssl-certificate-problem-with-php-curl/
     * @see https://stackoverflow.com/questions/50948387/curl-error-ssl-certificate-error-self-signed-certificate-in-certificate-chain
     * @see https://flaviocopes.com/http-curl/
     * @see https://www.php.net/manual/en/function.curl-error.php
     */
    public function isContent(?string $url) : bool
    {
        if (\is_null($url)) {
            return false;
        }
        // Youtube particular case to check availability correctly
        // otherwise HTTP code is always 200!
        if (preg_match( '/youtube/', $url)) {
            $url = $this->prepareAccessToYoutubeVideoContent($url);
        }
        // Use cURL
        $handle = curl_init(urldecode($url));
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
        // Avoid content loading by getting the headers only
        curl_setopt($handle, CURLOPT_NOBODY, 1);
        // Request with cURL
        curl_exec($handle);
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        // Check resource availability with HTTP code 200
        // $isContentFound = 404 !== $httpCode && 0 === strlen(curl_error($handle)) ? true : false;
        // Checking only HTTP code 200 is better!
        $isContentFound = 200 === $httpCode ? true : false;
        curl_close($handle);
        return $isContentFound;
    }

    /**
     * Check particular youtube video availability.
     *
     * @link Particular case for youtube video:
     * https://stackoverflow.com/questions/29166402/verify-if-video-exist-with-youtube-api-v3
     *
     * @param $url
     *
     * @return string the correct url to use to check availability
     */
    private function prepareAccessToYoutubeVideoContent($url) : string
    {
        // Extract video id and use correct URL
        preg_match( '/embed\/(.+)/', urldecode($url), $matches);
        $videoID = $matches[1];
        $url ='https://www.youtube.com/oembed?url=http://www.youtube.com/watch?v=' . $videoID;
        return $url;
    }

    /**
     * Return a status code to be converted later in JSON string.
     *
     * Value 1 means URL can be loaded and value 0 means error context must be used!
     *
     * @param string|null $url
     *
     * @return array
     *
     * @see https://symfony.com/doc/current/controller.html#returning-json-response
     */
    public function verify(?string $url) : array
    {
        // Prepare array to be converted in JSON string with Symfony JsonResponse object (no need to use "json_encode" here)
        if (\is_null($url)) {
            return ['status' => 0];
        }
        $url = urldecode($url);
        return $this->isAllowed($url) && $this->isContent($url) ? ['status' => 1] : ['status' => 0];
    }
}
