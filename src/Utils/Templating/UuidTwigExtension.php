<?php

declare(strict_types=1);

namespace App\Utils\Templating;

use App\Utils\Traits\UuidHelperTrait;
use Ramsey\Uuid\UuidInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/*
 * Class UuidTwigExtension.
 *
 * Create a Twig filter extension to encode uuid directly in template.
 *
 * @see encoded uuid inspired from:
 * https://medium.com/@galopintitouan/auto-increment-is-the-devil-using-uuids-in-symfony-and-doctrine-71763721b9a9
 */
class UuidTwigExtension extends AbstractExtension
{
    use UuidHelperTrait;

    /**
     * Get Twig filter.
     *
     * @return array
     */
    public function getFilters(): array
    {
        return [
            new TwigFilter(
                'uuid_encode',
                [$this, 'encodeUuid'],
                ['is_safe' => ['html']]
            ),
        ];
    }

    /**
     * Encode a uuid as string.
     *
     * @param UuidInterface $uuid
     *
     * @return string
     */
    public function encodeUuid(UuidInterface $uuid) : string
    {
        return $this->encode($uuid);
    }
}