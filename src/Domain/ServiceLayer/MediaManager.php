<?php

declare(strict_types = 1);

namespace App\Domain\ServiceLayer;

use App\Domain\Entity\Image;
use App\Domain\Entity\Media;
use App\Domain\Entity\MediaType;
use App\Domain\Entity\User;
use App\Domain\Repository\ImageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Class MediaManager.
 *
 * Manage images to handle, and retrieve as a "service layer".
 */
class MediaManager
{
    use LoggerAwareTrait;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var MediaTypeManager
     */
    private $mediaTypeManager;

    /**
     * @var ImageRepository
     */
    private $repository;

    /**
     * ImageManager constructor.
     *
     * @param EntityManagerInterface $entityManager,
     * @param ImageRepository        $repository
     * @param MediaTypeManager       $mediaTypeManager
     * @param LoggerInterface        $logger
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        ImageRepository $repository,
        MediaTypeManager $mediaTypeManager,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->mediaTypeManager = $mediaTypeManager;
        $this->repository = $repository;
        $this->setLogger($logger);
    }

    /**
     * Create user avatar media reference which corresponds to user avatar created image.
     *
     * @param Image     $image
     * @param MediaType $mediaType
     * @param User      $user
     * @param bool      $isMain
     * @param bool      $isPublished
     * @param int       $showListRank
     *
     * @return Media
     *
     * @throws \Exception
     */
    private function createAndSaveImageMediaWithType(
        Image $image,
        MediaType $mediaType,
        User $user,
        bool $isMain,
        bool $isPublished,
        int $showListRank = null
    ) : Media
    {
        // Create media with necessary associated instances and parameters
        $media = Media::createNewInstanceWithImage($image, $mediaType, null, $user, $isMain, $isPublished, $showListRank);
        // Persist media and its dependencies thanks to cascade option
        $this->entityManager->persist($media);
        // Save data
        $this->entityManager->flush();
        return $media;
    }

    /**
     * Create user avatar media reference which corresponds to user avatar created image.
     *
     * @param Image $image
     * @param User  $user
     * @param bool  $isMain
     * @param bool  $isPublished
     *
     * @return Media
     *
     * @throws \Exception
     */
    public function createUserAvatarMedia(
        Image $image,
        User $user, bool
        $isMain,
        bool $isPublished
    ) : Media
    {
        // Select a media type
        $mediaType = $this->mediaTypeManager->findSingleByUniqueType(MediaType::TYPE_CHOICES['userAvatar']);
        // Create and save the needed media
        $media = $this->createAndSaveImageMediaWithType($image, $mediaType, $user, $isMain, $isPublished); // $showListRank is unnecessary here!
        return $media;
    }

    /**
     * Save a trick media reference which corresponds to one created image which will dedicated to trick entity.
     *
     * Please not this method can create a standalone trick media (e.g. which appears in gallery), or a media effectively associated to a trick entity!
     *
     * @param Image  $image
     * @param string $mediaTypeKey
     * @param User   $user
     * @param bool   $isMain
     * @param bool   $isPublished
     * @param int    $showListRank
     *
     * @return Media
     *
     * @throws \Exception
     */
    public function createTrickMedia(
        Image $image,
        string $mediaTypeKey,
        User $user,
        bool $isMain,
        bool $isPublished,
        int $showListRank = null
    ) : Media
    {
        // Select a media type
        $mediaType = $this->mediaTypeManager->findSingleByUniqueType(MediaType::TYPE_CHOICES[$mediaTypeKey]);
        // Create and save the needed media
        $media = $this->createAndSaveImageMediaWithType($image, $mediaType, $user, $isMain, $isPublished, $showListRank);
        return $media;
    }

    /**
     * Get entity manager.
     *
     * @return EntityManagerInterface
     */
    public function getEntityManager() : EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * Get User entity repository.
     *
     * @return ImageRepository
     */
    public function getRepository() : ImageRepository
    {
        return $this->repository;
    }
}
