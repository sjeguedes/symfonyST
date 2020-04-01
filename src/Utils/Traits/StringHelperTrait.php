<?php

declare(strict_types = 1);

namespace App\Utils\Traits;

/**
 * Trait StringHelperTrait.
 *
 * Create string special formats.
 */
trait StringHelperTrait
{
    /**
     * Make a slug based on a string with custom parameters.
     *
     * @param string $string
     * @param array  $replace
     * @param string $delimiter
     *
     * @return string
     *
     * @throws \Exception
     */
    public function makeSlug(string $string, array $replace = [], string $delimiter = '-') : string
    {
        if (!extension_loaded('iconv')) {
          throw new \Exception('Sorry, "iconv" module is not loaded!');
        }
        // Save the old locale and set the new locale to UTF-8
        $oldLocale = setlocale(LC_ALL, '0');
        setlocale(LC_ALL, 'en_US.UTF-8');
        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
        // Not very useful but kept!
        if (!empty($replace)) {
          $clean = str_replace((array) $replace, ' ', $clean);
        }
        // Delete all characters which are not in this list (delimiter is excluded.)
        $clean = preg_replace("/[^a-z0-9\/_|+-]/i", '', $clean);
        // Format string with lowercase letters
        $clean = strtolower($clean);
        // Replace this characters list with delimiter
        $clean = preg_replace('/[\/_|+\s-]+/', $delimiter, $clean);
        // Trim delimiter (left and right)
        $clean = trim($clean, $delimiter);
        // Revert back to the old locale
        setlocale(LC_ALL, $oldLocale);
        return $clean;
    }

    /**
     * Sanitize a string with transliterator and replace some characters to make a slug.
     *
     * @param string $string
     * @param string $delimiter
     *
     * @return string|null
     *
     * @see https://www.php.net/manual/en/transliterator.transliterate.php
     */
    public function sanitizeString(string $string, string $delimiter = '-') : ?string
    {
        // Replace latin characters and lower-case string, remove non spacing mark but not punctuation...
        $string = transliterator_transliterate("Any-Latin; Latin-ASCII; NFD; [:Nonspacing Mark:] Remove; NFC; Lower();", $string);
        // Delete all characters which are not in this list (delimiter is excluded.)
        $string = preg_replace('/[^a-z0-9\/_|+\s-]/i', '', $string);
        // Replace this characters list with delimiter
        $string = preg_replace('/[\/_|+\s-]+/', $delimiter, $string);
        // Trim delimiter (left and right)
        return trim($string, $delimiter);
    }
}
