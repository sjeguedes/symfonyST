<?php

declare(strict_types = 1);

namespace App\Domain\DTO;

use Ramsey\Uuid\UuidInterface;

/**
 * Class AjaxDeleteImageDTO.
 *
 * Data Transfer Object used to delete a particular image.
 *
 * @see validation constraints AjaxDeleteImageDTO.yaml file
 */
final class AjaxDeleteImageDTO
{
    /**
     * @var UuidInterface|null
     */
    private $uuid;

    /**
     * @var string|null
     */
    private $name;

    /**
     * @var string|null
     */
    private $mediaOwnerType;

    /**
     * AjaxDeleteImageDTO constructor.
     *
     * @param UuidInterface|null $uuid
     * @param string|null        $name
     * @param string             $mediaOwnerType
     */
    public function __construct(
        ?UuidInterface $uuid,
        ?string $name,
        ?string $mediaOwnerType
    ) {
        $this->uuid = $uuid;
        $this->name = $name;
        $this->mediaOwnerType = $mediaOwnerType;
    }

    /**
     * @return UuidInterface|null
     */
    public function getUuid() : ?UuidInterface
    {
        return $this->uuid;
    }

    /**
     * @return string|null
     */
    public function getName() : ?string
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function getMediaOwnerType() : ?string
    {
        return $this->mediaOwnerType;
    }

    /**
     * @param UuidInterface|null $uuid
     *
     * @return AjaxDeleteImageDTO
     */
    public function setUuid(?UuidInterface $uuid) : self
    {
        $this->uuid = $uuid;
        return $this;
    }

    /**
     * @param string|null $name
     *
     * @return AjaxDeleteImageDTO
     */
    public function setName(?string $name) : self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @param string|null $mediaOwnerType
     *
     * @return AjaxDeleteImageDTO
     */
    public function setMediaOwnerType(?string $mediaOwnerType) : self
    {
        $this->mediaOwnerType = $mediaOwnerType;
        return $this;
    }
}
