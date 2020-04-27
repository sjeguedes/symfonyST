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
        '/^https?:\/\/www\.youtube\.com\/embed\/[a-zA-Z0-9_]+$/', // more explicit but video ID can be "simplified" to [\w-]+
        '/^https?:\/\/player\.vimeo\.com\/video\/[0-9]+$/',
        '/^https?:\/\/www\.dailymotion\.com\/embed\/video\/[a-zA-Z0-9]+$/'
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
        $url = null;
        if (!\is_null($request->attributes->get('url'))) {
            $url = $request->attributes->get('url');
        }
        return $url;
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
            if (preg_match( $patterns[$i], $url)) {
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
     * @see https://aboutssl.org/fix-ssl-certificate-problem-unable-to-get-local-issuer-certificate/
     * @see https://blog.petehouston.com/fix-ssl-certificate-problem-with-php-curl/
     * @see https://stackoverflow.com/questions/50948387/curl-error-ssl-certificate-error-self-signed-certificate-in-certificate-chain
     * @see https://www.php.net/manual/en/function.curl-error.php
     */
    public function isContent(?string $url) : bool
    {
        if (\is_null($url)) {
            return false;
        }
        // Use cURL
        $handle = curl_init($url);
        curl_setopt($handle, CURLOPT_RETURNTRANSFER, 1);
        // Avoid content loading by getting the headers only
        curl_setopt($handle, CURLOPT_NOBODY, 1);
        // Request with cURL
        curl_exec($handle);
        $httpCode = curl_getinfo($handle, CURLINFO_HTTP_CODE);
        // Check resource availability with HTTP response status code which is not a 404 value and also an empty error string returned
        $isContentFound = 404 !== $httpCode && 0 === strlen(curl_error($handle)) ? true : false;
        curl_close($handle);
        return $isContentFound;
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
        return $this->isAllowed($url) && $this->isContent($url) ? ['status' => 1] : ['status' => 0];
    }
}
