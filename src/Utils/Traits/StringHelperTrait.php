<?php

declare(strict_types=1);

namespace App\Utils\Traits;

/**
 * Trait StringHelperTrait.
 *
 * Create string special formats.
 */
trait StringHelperTrait
{
    /**
     * Slugify a string with custom parameters.
     *
     * @param        $string
     * @param array  $replace
     * @param string $delimiter
     *
     * @return null|string
     *
     * @throws \Exception
     */
    public function stringToSlug(string $string, array $replace = [], string $delimiter = '-') : ?string
    {
        if (!extension_loaded('iconv')) {
          throw new \Exception('Sorry, iconv module is not loaded!');
        }
        // Save the old locale and set the new locale to UTF-8
        $oldLocale = setlocale(LC_ALL, '0');
        setlocale(LC_ALL, 'en_US.UTF-8');
        $clean = iconv('UTF-8', 'ASCII//TRANSLIT', $string);
        if (!empty($replace)) {
          $clean = str_replace((array) $replace, ' ', $clean);
        }
        $clean = preg_replace("/[^a-zA-Z0-9\/_|+\s-]/", '', $clean);
        $clean = strtolower($clean);
        $clean = preg_replace("/[\/_|+\s-]+/", $delimiter, $clean);
        $clean = trim($clean, $delimiter);
        // Revert back to the old locale
        setlocale(LC_ALL, $oldLocale);
        return $clean;
    }

}