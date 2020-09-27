<?php

declare(strict_types=1);

namespace App\Domain\ServiceLayer;

use App\Domain\DTO\UpdateProfileAvatarDTO;
use App\Domain\DTOToEmbed\ImageToCropDTO;
use App\Domain\DTOToEmbed\VideoInfosDTO;
use App\Domain\Entity\Media;
use App\Domain\Entity\MediaOwner;
use App\Domain\Entity\MediaSource;
use App\Domain\Entity\MediaType;
use App\Domain\Entity\User;
use App\Domain\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class MediaManager.
 *
 * Manage images to handle, and retrieve as a "service layer".
 */
class MediaManager extends AbstractServiceLayer
{
    use LoggerAwareTrait;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var MediaOwnerManager
     */
    private $mediaOwnerManager;

    /**
     * @var MediaSourceManager
     */
    private $mediaSourceManager;

    /**
     * @var MediaTypeManager
     */
    private $mediaTypeManager;

    /**
     * @var MediaRepository
     */
    private $repository;

    /**
     * @var Security
     */
    private $security;

    /**
     * ImageManager constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param MediaRepository        $repository
     * @param MediaOwnerManager      $mediaOwnerManager
     * @param MediaSourceManager     $mediaSourceManager
     * @param MediaTypeManager       $mediaTypeManager
     * @param LoggerInterface        $logger
     * @param Security               $security
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        MediaRepository $repository,
        MediaOwnerManager $mediaOwnerManager,
        MediaSourceManager $mediaSourceManager,
        MediaTypeManager $mediaTypeManager,
        LoggerInterface $logger,
        Security $security
    ) {
        parent::__construct($entityManager, $logger);
        $this->entityManager = $entityManager;
        $this->mediaOwnerManager = $mediaOwnerManager;
        $this->mediaSourceManager = $mediaSourceManager;
        $this->mediaTypeManager = $mediaTypeManager;
        $this->repository = $repository;
        $this->setLogger($logger);
        $this->security = $security;
    }

    /**
     * Get Media instance arguments from data model.
     *
     * @param object $mediaDataModel
     *
     * @return array
     */
    private function getMediaParametersByCheckingDataModelMethods(object $mediaDataModel): array
    {
        // Create media with necessary associated instances and parameters
        $isMain = !method_exists($mediaDataModel, 'getIsMain') ? false : $mediaDataModel->getIsMain();
        // At this time in application, a media is always published automatically!
        $isPublished = !method_exists($mediaDataModel, 'getIsPublished') ? true : $mediaDataModel->getIsPublished();
        $showListRank = !method_exists($mediaDataModel, 'getShowListRank') ? null : $mediaDataModel->getShowListRank();
        return [
            'isMain'       => $isMain,
            'isPublished'  => $isPublished,
            'showListRank' => $showListRank,
        ];
    }

    /**
     * Create user avatar media reference which corresponds to user avatar created image.
     *
     * @param MediaOwner             $mediaOwner     a media attachment
     * @param MediaSource            $mediaSource    a kind of media
     * @param UpdateProfileAvatarDTO $mediaDataModel
     * @param string                 $mediaTypeKey
     * @param bool                   $isPersisted
     * @param bool                   $isFlushed
     *
     * @return Media|null
     *
     * @throws \Exception
     */
    public function createUserAvatarMedia(
        MediaOwner $mediaOwner,
        MediaSource $mediaSource,
        UpdateProfileAvatarDTO $mediaDataModel,
        string $mediaTypeKey,
        bool $isPersisted = false,
        bool $isFlushed = false
    ): ?Media {
        // Get Authenticated user
        /** @var User|UserInterface $authenticatedUser */
        $authenticatedUser = $this->security->getUser();
        // Select a media type ('userAvatar')
        $mediaTypeReference = MediaType::TYPE_CHOICES[$mediaTypeKey];
        $mediaType = $this->mediaTypeManager->findSingleByUniqueType($mediaTypeReference);
        if (\is_null($mediaType)) {
            throw new \RuntimeException(sprintf('"%s" media type is unknown!', $mediaTypeKey));
        }
        // Check media data model and coherence with expected media type
        $mediaTypeReferenceWithoutPrefix = str_replace(MediaType::TYPE_PREFIXES['user'], '', $mediaTypeReference);
        $isMediaTypeKeyAllowed = \in_array($mediaTypeReferenceWithoutPrefix, MediaType::ALLOWED_IMAGE_TYPES);
        if (!$isMediaTypeKeyAllowed) {
            throw new \RuntimeException(
                sprintf(
                    '"%s" media type reference is not coherent with "%s" media data model!',
                    $mediaTypeKey, $mediaDataModel)
            );
        }
        // Get arguments which are not objects
        $arguments = $this->getMediaParametersByCheckingDataModelMethods($mediaDataModel);
        // Create media with necessary associated instances and parameters by calling corresponding method
        $newMedia = new Media(
            $mediaOwner,
            $mediaSource,
            $mediaType,
            $authenticatedUser,
            $arguments['isMain'],
            $arguments['isPublished'], // $showListRank is null here!
            $arguments['showListRank']
        );
        // Save data in database
        /** @var Media|null $newMedia */
        $newMedia = $this->addAndSaveNewEntity($newMedia, $isPersisted, $isFlushed); // null or the entity
        return $newMedia;
    }

    /**
     * Save a trick media reference which corresponds to one created image or video which will be dedicated to trick entity.
     *
     * Please not this method can create a standalone trick media (e.g. which appears in gallery), or a media effectively associated to a trick entity!
     *
     * @param MediaOwner|null $mediaOwner     a media attachment which can be null in case of direct upload
     * @param MediaSource     $mediaSource    a kind of media
     * @param object          $mediaDataModel
     * @param string          $mediaTypeKey
     * @param bool            $isPersisted
     * @param bool            $isFlushed
     *
     * @return Media|null
     *
     * @throws \Exception
     */
    public function createTrickMedia(
        ?MediaOwner $mediaOwner,
        MediaSource $mediaSource,
        object $mediaDataModel,
        string $mediaTypeKey,
        bool $isPersisted = false,
        bool $isFlushed = false
    ): ?Media {
        // Select a media type
        $mediaTypeReference = MediaType::TYPE_CHOICES[$mediaTypeKey];
        $mediaType = $this->mediaTypeManager->findSingleByUniqueType($mediaTypeReference);
        if (\is_null($mediaType)) {
            throw new \RuntimeException(sprintf('"%s" media type is unknown!', $mediaTypeKey));
        }
        // Check media data model and coherence with expected media type
        switch ($mediaDataModel) {
            case $mediaDataModel instanceof ImageToCropDTO:
                $mediaTypeReferenceWithoutPrefix = str_replace(MediaType::TYPE_PREFIXES['trick'], '', $mediaTypeReference);
                $isMediaTypeKeyAllowed = \in_array($mediaTypeReferenceWithoutPrefix, MediaType::ALLOWED_IMAGE_TYPES);
                break;
            case $mediaDataModel instanceof VideoInfosDTO:
                $mediaTypeReferenceWithoutPrefix = str_replace(MediaType::TYPE_PREFIXES['trick'], '', $mediaTypeReference);
                $isMediaTypeKeyAllowed = \in_array($mediaTypeReferenceWithoutPrefix, MediaType::ALLOWED_VIDEO_TYPES);
                break;
            default:
                throw new \InvalidArgumentException('Media data model type is not allowed!');
        }
        if (!$isMediaTypeKeyAllowed) {
            throw new \RuntimeException(
                sprintf(
                    '"%s" media type reference is not coherent with "%s" media data model!',
                    $mediaTypeKey, $mediaDataModel)
            );
        }
        // Get Authenticated user
        /** @var User|UserInterface $authenticatedUser */
        $authenticatedUser = $this->security->getUser();
        // Get arguments which are not objects
        $arguments = $this->getMediaParametersByCheckingDataModelMethods($mediaDataModel);
        // Create media with necessary associated instances and parameters by calling corresponding method
        $newMedia = new Media(
            $mediaOwner,
            $mediaSource,
            $mediaType,
            $authenticatedUser,
            $arguments['isMain'],
            $arguments['isPublished'],
            $arguments['showListRank']
        );
        // Save data in database
        /** @var Media|null $newMedia */
        $newMedia = $this->addAndSaveNewEntity($newMedia, $isPersisted, $isFlushed); // null or the entity
        return $newMedia;
    }

    /**
     * Get entity manager.
     *
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * Get media owner manager.
     *
     * @return MediaOwnerManager
     */
    public function getMediaOwnerManager(): MediaOwnerManager
    {
        return $this->mediaOwnerManager;
    }

    /**
     * Get media source manager.
     *
     * @return MediaSourceManager
     */
    public function getMediaSourceManager(): MediaSourceManager
    {
        return $this->mediaSourceManager;
    }

    /**
     * Get Media entity repository.
     *
     * @return MediaRepository
     */
    public function getRepository(): MediaRepository
    {
        return $this->repository;
    }

    /**
     * Remove a media and all associated entities depending on cascade operations.
     *
     * @param Media $media
     * @param bool  $isFlushed
     *
     * @return bool
     */
    public function removeMedia(Media $media, bool $isFlushed = true): bool
    {
        // Proceed to removal in database
        return $this->removeAndSaveNoMoreEntity($media, $isFlushed);
    }
}
