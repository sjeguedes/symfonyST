<?php

declare(strict_types = 1);

namespace App\Domain\ServiceLayer;

use App\Domain\DTO\UpdateProfileAvatarDTO;
use App\Domain\DTOToEmbed\ImageToCropDTO;
use App\Domain\DTOToEmbed\VideoInfosDTO;
use App\Domain\Entity\Image;
use App\Domain\Entity\Media;
use App\Domain\Entity\MediaType;
use App\Domain\Entity\User;
use App\Domain\Entity\Video;
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
     * @param EntityManagerInterface $entityManager,
     * @param MediaRepository        $repository
     * @param MediaTypeManager       $mediaTypeManager
     * @param LoggerInterface        $logger
     * @param Security               $security
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        MediaRepository $repository,
        MediaTypeManager $mediaTypeManager,
        LoggerInterface $logger,
        Security $security
    ) {
        parent::__construct($entityManager, $logger);
        $this->entityManager = $entityManager;
        $this->mediaTypeManager = $mediaTypeManager;
        $this->repository = $repository;
        $this->setLogger($logger);
        $this->security = $security;
    }

    /**
     * Create user a Media entity reference which
     *
     * Please note Media corresponds to an Image or Video entity at this time (can be another source).
     *
     * Please note combinations:
     * - $isPersisted = false, $isFlushed = false means Media entity must be instantiated only.
     * - $isPersisted = true, $isFlushed = true means Media entity is added to unit of work and saved in database.
     * - $isPersisted = true, $isFlushed = false means Media entity is added to unit of work only.
     * - $isPersisted = false, $isFlushed = true means Media entity is saved in database only with possible change(s) in unit of work.
     *
     * @param Media     $newMedia
     * @param bool      $isPersisted
     * @param bool      $isFlushed
     *
     * @return Media
     *
     * @throws \Exception
     */
    private function addAndSaveMediaWithType(
        Media $newMedia,
        bool $isPersisted,
        bool $isFlushed
    ) : Media
    {
        $object = $this->addAndSaveEntity($newMedia, $isPersisted, $isFlushed);
        return \is_null($object) ? null : $newMedia;
    }

    /**
     * Create user avatar media reference which corresponds to user avatar created image.
     *
     * @param Image                  $image
     * @param UpdateProfileAvatarDTO $dataModel
     * @param string                 $mediaTypeKey
     * @param bool                   $isPersisted
     * @param bool                   $isFlushed
     *
     * @return Media|null
     *
     * @throws \Exception
     *
     * TODO: review this method if database "medias" table schema is improved:
     * TODO: delete image and video foreign keys, keep reference to media in Image or Video tables by modifying relationships)
     * TODO: create a public constructor with Image or Video entity reference (delete named constrcutors)
     * TODO: delete trick foreign key, because a media source is not necessarily to trick , that's the casr for avatar or media document source
     */
    public function createUserAvatarMedia(
        Image $image,
        UpdateProfileAvatarDTO $dataModel,
        string $mediaTypeKey,
        bool $isPersisted = false,
        bool $isFlushed = false
    ) : ?Media
    {
        // Get Authenticated user
        /** @var User|UserInterface $authenticatedUser */
        $authenticatedUser = $this->security->getUser();
        // Select a media type
        $mediaType = $this->mediaTypeManager->findSingleByUniqueType(MediaType::TYPE_CHOICES[$mediaTypeKey]); // 'userAvatar'
        // Create media with necessary associated instances and parameters
        $isMain = !method_exists($dataModel, 'getIsMain') ? false : $dataModel->getIsMain();
        $isPublished = !method_exists($dataModel, 'getIsPublished') ? true : $dataModel->getIsPublished();
        $showListRank = !method_exists($dataModel, 'getShowListRank') ? null : $dataModel->getShowListRank();
        $newMedia = Media::createNewInstanceWithImage($image, $mediaType, null, $authenticatedUser, $isMain, $isPublished, null); // $showListRank is unnecessary here!
        // Save data in database
        $newMedia = $this->addAndSaveMediaWithType($newMedia, $isPersisted, $isFlushed); // null or the entity
        return $newMedia;
    }

    /**
     * Save a trick media reference which corresponds to one created image or video which will be dedicated to trick entity.
     *
     * Please not this method can create a standalone trick media (e.g. which appears in gallery), or a media effectively associated to a trick entity!
     *
     * @param object $entityType
     * @param object $dataModel
     * @param string $mediaTypeKey
     * @param bool   $isPersisted
     * @param bool   $isFlushed
     *
     * @return Media|null
     *
     * @throws \Exception
     */
    public function createTrickMedia(
        object $entityType,
        object $dataModel,
        string $mediaTypeKey,
        bool $isPersisted = false,
        bool $isFlushed = false
    ) : ?Media
    {
        if (!$entityType instanceof Image && !$entityType instanceof Video) {
            throw new \InvalidArgumentException('Entity type must be an instance of "Image" or "Video"!');
        }
        if (($entityType instanceof Image && !$dataModel instanceof ImageToCropDTO) ||
            ($entityType instanceof Video && !$dataModel instanceof VideoInfosDTO)) {
            throw new \InvalidArgumentException('Data model must be an instance of "ImageToCropDTO" or "VideoInfosDTO"!');
        }
        // Get Authenticated user
        /** @var User|UserInterface $authenticatedUser */
        $authenticatedUser = $this->security->getUser();
        // Select a media type
        $mediaTypeReference = MediaType::TYPE_CHOICES[$mediaTypeKey];
        $mediaType = $this->mediaTypeManager->findSingleByUniqueType($mediaTypeReference);
        if (\is_null($mediaType)) {
            throw new \RuntimeException(sprintf('"%s" media type is unknown!', $mediaTypeKey));
        }
        $mediaTypeReferenceWithoutPrefix = str_replace(MediaType::TYPE_PREFIXES['trick'], '', $mediaTypeReference);
        $isVideoMediaTypeKeyAllowed = \in_array($mediaTypeReferenceWithoutPrefix, MediaType::ALLOWED_VIDEO_TYPES);
        // No need to check Image entity case, because there is only 2 entity types at this time!
        if ($entityType instanceof Video && !$isVideoMediaTypeKeyAllowed) {
            throw new \RuntimeException(sprintf('"%s" is not coherent with "%s" entity type!', $mediaTypeKey, $entityType));
        }
        // Get Media source type entity (At this time, it can be an Image or Media entity!)
        $objectType = (new \ReflectionClass($entityType))->getShortName();
        $methodNameToCall = "createNewInstanceWith{$objectType}";
        // Create media with necessary associated instances and parameters by calling corresponding method
        $isMain = !method_exists($dataModel, 'getIsMain') ? false : $dataModel->getIsMain();
        $isPublished = !method_exists($dataModel, 'getIsPublished') ? true : $entityType->getMedia()->getIsPublished();
        $showListRank = !method_exists($dataModel, 'getShowListRank') ? null : $dataModel->getShowListRank();
        $newMedia = Media::$methodNameToCall($entityType, $mediaType, null, $authenticatedUser, $isMain, $isPublished, $showListRank);
        // Save data in database
        $newMedia = $this->addAndSaveMediaWithType($newMedia, $isPersisted, $isFlushed); // null or the entity
        return $newMedia;
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
     * Get Media entity repository.
     *
     * @return MediaRepository
     */
    public function getRepository() : MediaRepository
    {
        return $this->repository;
    }
}
