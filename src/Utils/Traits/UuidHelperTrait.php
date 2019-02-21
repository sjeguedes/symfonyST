<?php

declare(strict_types=1);

namespace App\Utils\Traits;

use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/*
 * Trait UuidHelperTrait.
 *
 * Enable uuid management to change its format.
 *
 * @see encoded uuid inspired from:
 * https://medium.com/@galopintitouan/auto-increment-is-the-devil-using-uuids-in-symfony-and-doctrine-71763721b9a9
 * https://medium.com/@huntie/representing-a-uuid-as-a-base-62-hash-id-for-short-pretty-urls-c30e66bf35f9
 */
trait UuidHelperTrait
{
    /**
     * Encode a base62 uuid string.
     *
     * @param UuidInterface $uuid
     *
     * @return string
     */
    public function encode(UuidInterface $uuid) : string
    {
        // Convert GMP number to base62 string
        return gmp_strval(
            // Create a base16 GMP number and remove "-" from uuid string
            gmp_init( // gmp_base_convert(str_replace('-', '', $uuid->toString()), 62, 16) can be used instead of this
                str_replace('-', '', $uuid->toString()),
                16
            ),
            62
        );
    }

    /**
     * Decode a uuid from base62 uuid string.
     *
     * @param string $encoded
     *
     * @return null|UuidInterface
     */
    public function decode(string $encoded) : ?UuidInterface
    {
        try {
            return Uuid::fromString(array_reduce(
                [20, 16, 12, 8],
                function ($uuid, $offset) {
                    // Add "-" to separate the 4 sets of 4 characters
                    return substr_replace($uuid, '-', $offset, 0);
                },
                // Complete base16 GMP string to 32 characters
                str_pad(
                    // Convert base62 GMP number to base16 encoded uuid string
                    gmp_strval(
                        // Create a base62 GMP number (gmp_base_convert($encoded, 62, 16) can be used instead of this)
                        gmp_init($encoded, 62),
                        16
                    ),
                    32,
                    '0',
                    STR_PAD_LEFT
                )
            ));
        } catch (\Throwable $e) {
            return null;
        }
    }
}