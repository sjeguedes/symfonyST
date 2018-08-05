<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Class Trick
 * This class defines Trick entity schema in database
 *
 * @ORM\Entity(repositoryClass="App\Repository\TrickRepository")
 * @ORM\Table(name="tricks")
 */
class Trick
{
    /**
     * The unique auto incremented primary key.
     *
     * @var int
     *
     * @ORM\Id()
     * @ORM\GeneratedValue()
     * @ORM\Column(type="integer", options={"unsigned": true})
     */
    private $id;

    /**
     * The internal primary identity key.
     *
     * @var UuidInterface
     *
     * @ORM\Column(type="uuid")
     */
    private $uuid;

    /**
     *
     * @var string
     *
     * @ORM\Column(type="string", length=255)
     */
    private $name;

    /**
     *
     * @var string
     *
     * @ORM\Column(type="text")
     */
    private $description;

    /**
     *
     * @var \DateTimeInterface
     *
     * @ORM\Column(type="datetime")
     */
    private $creationDate;

    /**
     *
     * @var \DateTimeInterface|null
     *
     * @ORM\Column(type="datetime", nullable=true)
     */
    private $updateDate;

    /**
     *
     * @var int
     *
     * @ORM\Column(type="integer")
     */
    private $trickGroupId;

    /**
     * Trick constructor.
     * @throws \Exception
     */
    public function __construct()
    {
        $this->uuid = Uuid::uuid4();
    }

    /**
     * @return int|null
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @return UuidInterface
     */
    public function getUuid()
    {
        return $this->uuid;
    }

    /**
     * @return null|string
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return Trick
     */
    public function setName(string $name): self
    {
        $this->name = $name;

        return $this;
    }

    /**
     * @return null|string
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return Trick
     */
    public function setDescription(string $description): self
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getCreationDate(): ?\DateTimeInterface
    {
        return $this->creationDate;
    }

    /**
     * @param \DateTimeInterface $creationDate
     * @return Trick
     */
    public function setCreationDate(\DateTimeInterface $creationDate): self
    {
        $this->creationDate = $creationDate;

        return $this;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getUpdateDate(): ?\DateTimeInterface
    {
        return $this->updateDate;
    }

    /**
     * @param \DateTimeInterface $updateDate
     * @return Trick
     */
    public function setUpdateDate(\DateTimeInterface $updateDate): self
    {
        $this->updateDate = $updateDate;

        return $this;
    }

    /**
     * @return int|null
     */
    public function getTrickGroupId(): ?int
    {
        return $this->trickGroupId;
    }

    /**
     * @param int $trickGroupId
     * @return Trick
     */
    public function setTrickGroupId(int $trickGroupId): self
    {
        $this->trickGroupId = $trickGroupId;

        return $this;
    }
}
