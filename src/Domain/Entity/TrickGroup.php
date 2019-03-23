<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * TrickGroup entity.
 *
 * Define TrickGroup entity schema in database, its initial state and behaviors.
 *
 * @ORM\Entity(repositoryClass=TrickGroupRepository::class)
 * @ORM\Table(name="trick_groups")
 */
class TrickGroup
{
    /**
     * The internal primary identity key.
     *
     * @var UuidInterface
     *
     * @ORM\Id()
     * @ORM\Column(type="uuid_binary", unique=true)
     */
    private $uuid;

    /**
     * @var string
     *
     * @ORM\Column(type="string", unique=true)
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
     * @var \DateTimeInterface
     *
     * @ORM\Column(type="datetime")
     */
    private $creationDate;

    /**
     * @var \DateTimeInterface
     *
     * @ORM\Column(type="datetime")
     */
    private $updateDate;

    /**
     * @var Collection (inverse side of entity relation)
     *
     * @ORM\OneToMany(targetEntity=Trick::class, mappedBy="trickGroup")
     */
    private $tricks;

    /**
     * TrickGroup constructor.
     *
     * @param string                  $name
     * @param string                  $description
     * @param \DateTimeInterface|null $creationDate
     * @param \DateTimeInterface|null $updateDate
     *
     * @return void
     *
     * @throws \Exception
     */
    public function __construct(
        string $name,
        string $description,
        \DateTimeInterface $creationDate = null,
        \DateTimeInterface $updateDate = null
    ) {
        $this->uuid = Uuid::uuid4();
        assert(!empty($name),'TrickGroup name can not be empty!');
        $this->name = $name;
        assert(!empty($description),'TrickGroup description can not be empty!');
        $this->description = $description;
        $createdAt = !\is_null($creationDate) ? $creationDate : new \DateTime('now');
        $this->creationDate = $createdAt;
        $updatedAt = !\is_null($updateDate) ? $updateDate : $this->creationDate;
        assert($updatedAt >= $this->creationDate,'TrickGroup can not be created after update date!');
        $this->updateDate = $updatedAt;
        $this->tricks = new ArrayCollection();
    }

    /**
     * Change name after creation.
     *
     * @param string $name
     *
     * @return TrickGroup
     */
    public function modifyName(string $name) : self
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('TrickGroup name can not be empty!');
        }
        $this->name = $name;
        return $this;
    }

    /**
    * Change description after creation.
    *
    * @param string $description
    *
    * @return TrickGroup
    */
    public function modifyDescription(string $description) : self
    {
        if (empty($description)) {
            throw new \InvalidArgumentException('TrickGroup description can not be empty!');
        }
        $this->description = $description;
        return $this;
    }

    /**
     * Change update date after creation.
     *
     * @param \DateTimeInterface $updateDate
     *
     * @return TrickGroup
     */
    public function modifyUpdateDate(\DateTimeInterface $updateDate) : self
    {
        if ($this->creationDate > $updateDate) {
            throw new \RuntimeException('Update date is not logical: TrickGroup can not be created after modified update date!');
        }
        $this->updateDate = $updateDate;
        return $this;
    }

    /**
     * Add Trick entity to collection.
     *
     * @param Trick $trick
     *
     * @return TrickGroup
     */
    public function addTrick(Trick $trick) : self
    {
        if (!$this->tricks->contains($trick)) {
            $this->tricks->add($trick);
            $trick->modifyTrickGroup($this);
        }
        return $this;
    }

    /**
     * Remove Trick entity from collection.
     *
     * @param Trick $trick
     *
     * @return TrickGroup
     */
    public function removeTrick(Trick $trick) : self
    {
        if ($this->tricks->contains($trick)) {
            $this->tricks->removeElement($trick);
        }
        return $this;
    }

    /**
     * @return UuidInterface
     */
    public function getUuid() : UuidInterface
    {
        return $this->uuid;
    }

    /**
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getDescription() : string
    {
        return $this->description;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getCreationDate() : \DateTimeInterface
    {
        return $this->creationDate;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getUpdateDate() : \DateTimeInterface
    {
        return $this->updateDate;
    }

    /**
     * @return Collection|Trick[]
     */
    public function getTricks() : Collection
    {
        return $this->tricks;
    }
}
