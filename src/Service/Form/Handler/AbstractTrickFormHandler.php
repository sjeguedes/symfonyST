<?php

declare(strict_types=1);

namespace App\Service\Form\Handler;

use App\Domain\DTOToEmbed\ImageToCropDTO;
use App\Domain\DTOToEmbed\VideoInfosDTO;
use App\Domain\Entity\Image;
use App\Domain\Entity\MediaOwner;
use App\Domain\Entity\MediaSource;
use App\Domain\Entity\MediaType;
use App\Domain\Entity\Trick;
use App\Domain\ServiceLayer\ImageManager;
use App\Domain\ServiceLayer\MediaManager;
use App\Domain\ServiceLayer\VideoManager;
use App\Service\Form\Collection\DTOCollection;
use App\Service\Medias\Upload\ImageUploader;
use App\Utils\Traits\StringHelperTrait;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Security\Core\Security;

/**
 * Class AbstractTrickFormHandler.
 *
 * Define Form Handler essential responsibilities for Trick entity processes.
 */
class AbstractTrickFormHandler extends AbstractUploadFormHandler
{
    use LoggerAwareTrait;
    use StringHelperTrait;

    /**
     * @var Security
     */
    protected $security;

    /**
     * Define a label for trick creation.
     */
    const TRICK_CREATION_LABEL = 'CreateTrick';

    /**
     * Define a label for trick update.
     */
    const TRICK_UPDATE_LABEL = 'UpdateTrick';

    /**
     * Define texts for new media creation error based on context.
     */
    const TRICK_NEW_MEDIA_ERROR_TEXTS = [
        self::TRICK_CREATION_LABEL => [
        'text'   => 'created',
        'action' => 'CreateTrickHandler/cancelTrickCreationProcess'
        ],
        self::TRICK_UPDATE_LABEL => [
        'text'   => 'updated',
        'action' => 'UpdateTrickHandler/cancelTrickUpdateProcess'
        ],
    ];

    /**
     * AbstractTrickFormHandler constructor.
     *
     * @param FlashBagInterface    $flashBag
     * @param FormFactoryInterface $formFactory
     * @param string               $formType
     * @param RequestStack         $requestStack
     * @param LoggerInterface      $logger
     * @param Security             $security
     */
    public function __construct(
        FlashBagInterface $flashBag,
        FormFactoryInterface $formFactory,
        string $formType,
        RequestStack $requestStack,
        LoggerInterface $logger,
        Security $security
    ) {
        parent::__construct($flashBag, $formFactory, $formType, $requestStack);
        $this->setLogger($logger);
        $this->security = $security;
    }

    /**
     * Add the three expected images (for one uploaded image)
     * and create/update Image/Media entities from trick image collection.
     *
     * IMPORTANT! This method is publicly accessible due to its possible use in "ImageToCropType" class
     * for direct upload in case of trick update.
     *
     * Please note this information:
     * - 3 formats are finally generated after handling: 1600x900, 880x495, 400x225.
     * - The identifier name used corresponds to a slug based on trick name which will be created with the root form.
     * - A hash is automatically added to make image name unique
     * - Images names respects this principle: identifierName-hash-format.extension (e.g. mctwist-b1853337-880x495.jpeg)
     *
     * @param Trick          $trick
     * @param ImageToCropDTO $imageToCropDTO
     * @param array          $actionData
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function addTrickImageFromCollection(
        Trick $trick,
        ImageToCropDTO $imageToCropDTO,
        array $actionData
    ): bool {
        /** @var ImageManager $imageService */
        $imageService = $actionData['imageService'];
        // Get trick media owner
        $trickMediaOwner = $trick->getMediaOwner();
        // Retrieve big image Image and Media entities thanks to saved image name with loop
        // which was already uploaded on server during form validation thanks to corresponding DTO with its "savedImageName" property.
        // 1. Update base big image
        $bigImageEntity = $imageService->findSingleByName($imageToCropDTO->getSavedImageName());
        // Rename (with trick name slug) big image which is used in corresponding Image entity
        $newImageName = $this->renameTrickBigImage($bigImageEntity, $trick, $actionData);
        if (\is_null($newImageName)) {
            return false;
        }
        // Add Media entity in trick medias Collection (which can be removed in case of failure!)
        $bigImageMediaEntity = $bigImageEntity->getMediaSource()->getMedia();
        // Modify MediaOwner entity: "direct upload" forced media owner to be null before and must be updated here!
        $bigImageMediaEntity->modifyMediaOwner($trickMediaOwner);
        $trickMediaOwner->addMedia($bigImageMediaEntity);
        // Update big image corresponding Image and Media entities with validated form data
        // Update will be flushed at the end of process!
        $isBigImageUpdated = $imageService->updateTrickBigImage($bigImageEntity, $imageToCropDTO, $newImageName, false);
        if (!$isBigImageUpdated) {
            return false; // Big image update failed!
        }
        // Create physically small and medium ("normal") images files,
        // and then, add also corresponding Image and Media entities
        // 2. Small image (thumbnail)
        $thumbImageEntity = $this->createTrickImageWithMandatoryFormat(
            $trick,
            $imageToCropDTO,
            $bigImageEntity,
            'trickThumbnail',
            $actionData
        );
        if (\is_null($thumbImageEntity)) {
            return false;
        }
        // Add Media entity in trick medias Collection (which can be removed in case of failure!)
        $thumbImageMediaEntity = $thumbImageEntity->getMediaSource()->getMedia();
        $trickMediaOwner->addMedia($thumbImageMediaEntity);
        // 3. Normal image (intermediate format)
        $normalImageEntity = $this->createTrickImageWithMandatoryFormat(
            $trick,
            $imageToCropDTO,
            $bigImageEntity,
            'trickNormal',
            $actionData
        );
        if (\is_null($normalImageEntity)) {
            return false;
        }
        // Add Media and MediaOwner entities from trick media Collection (which can be removed in case of failure!)
        $normalImageMediaEntity = $normalImageEntity->getMediaSource()->getMedia();
        $trickMediaOwner->addMedia($normalImageMediaEntity);
        return true;
    }

    /**
     * Add a Video entity from trick video collection.
     *
     * @param Trick         $trick
     * @param VideoInfosDTO $videoInfosDTO
     * @param array         $actionData
     *
     * @return bool
     *
     * @throws \Exception
     */
    protected function addTrickVideoFromCollection(
        Trick $trick,
        VideoInfosDTO $videoInfosDTO,
        array $actionData
    ): bool {
        /** @var VideoManager $videoService */
        $videoService = $actionData['videoService'];
        /** @var MediaManager $mediaService */
        $mediaService = $actionData['mediaService'];
        // Get trick media owner
        $trickMediaOwner = $trick->getMediaOwner();
        // Prepare a dynamic pattern "youtube|vimeo|dailymotion|..."
        $allowedVideoTypes = MediaType::ALLOWED_VIDEO_TYPES;
        $pattern = str_replace('_', '|', preg_quote(implode('_', $allowedVideoTypes)));
        // Determine video media type thanks to allowed video URL
        preg_match("/{$pattern}/", $videoInfosDTO->getUrl(), $matches, PREG_UNMATCHED_AS_NULL);
        $mediaTypeKey = 'trick' . ucfirst($matches[0]); // e.g. "trickYoutube"
        // Create Video entity
        $newVideoEntity = $videoService->createTrickVideo($videoInfosDTO);
        if (\is_null($newVideoEntity)) {
            return false;
        }
        // Create mandatory Media entity which references corresponding entities:
        // MediaOwner is the attachment (it is a trick here), MediaSource is a video.
        /** @var MediaOwner|null $newMediaOwner */
        $newMediaOwnerEntity = $trickMediaOwner;
        /** @var MediaSource|null $newMediaSource */
        $newMediaSourceEntity = $mediaService->getMediaSourceManager()->createMediaSource($newVideoEntity);
        if (\is_null($newMediaSourceEntity)) {
            return false;
        }
        // Create mandatory Media entity which references corresponding Video entity
        $newVideoMediaEntity = $mediaService->createTrickMedia(
            $newMediaOwnerEntity,
            $newMediaSourceEntity,
            $videoInfosDTO,
            $mediaTypeKey
        );
        if (\is_null($newVideoMediaEntity)) {
            return false;
        }
        // Save video data
        $newVideoEntity = $videoService->addAndSaveVideo($newVideoEntity, $newVideoMediaEntity, true);
        if (\is_null($newVideoEntity)) {
            return false;
        }
        // Add Media and MediaOwner entities from trick media Collection (which can be removed in case of failure!)
        $trickMediaOwner->addMedia($newVideoMediaEntity);
        return true;
    }

    /**
     * Check trick images and videos collection instance type.
     *
     * @param object|null $collectionItemDataModel
     *
     * @return void
     */
    protected function checkCollectionInstanceType(?object $collectionItemDataModel): void
    {
        $condition = !\is_null($collectionItemDataModel) &&
            !$collectionItemDataModel instanceof ImageToCropDTO &&
            !$collectionItemDataModel instanceof VideoInfosDTO;
        if ($condition) {
            throw new \InvalidArgumentException(
                sprintf(
                    '"%s" data model must be an ImageToCropDTO or VideoInfosDTO instance!',
                    $collectionItemDataModel
                )
            );
        }
    }

    /**
     * Check if each video URL is unique for a particular trick to create or update.
     *
     * @param DTOCollection|VideoInfosDTO[] $videosCollection
     *
     * @return bool
     */
    protected function checkUniqueVideoUrl(DTOCollection $videosCollection): bool
    {
        $urls = [];
        foreach ($videosCollection as $videoInfos) {
            $urls[] = $videoInfos->getUrl();
        }
        return \count(\array_unique($urls)) === $videosCollection->count();
    }

    /**
     * Create 2 other formats based on a saved image (direct upload) during root form validation.
     *
     * Please note base (big) image is the highest available format, so the two others are created with resize operation.
     *
     * @param Trick          $trick
     * @param ImageToCropDTO $imageToCropDTO
     * @param Image          $baseImageEntity
     * @param string         $mediaTypeKey
     * @param array          $actionData
     *
     * @return Image|null
     *
     * @throws \Exception
     */
    protected function createTrickImageWithMandatoryFormat(
        Trick $trick,
        ImageToCropDTO $imageToCropDTO,
        Image $baseImageEntity,
        string $mediaTypeKey,
        array $actionData
    ): ?Image {
        /** @var ImageManager $imageService */
        $imageService = $actionData['imageService'];
        /** @var MediaManager $mediaService */
        $mediaService = $actionData['mediaService'];
        // Get trick media owner
        $trickMediaOwner = $trick->getMediaOwner();
        // Get identifier name (included format will be replaced depending on new format to generate)
        $baseImageNameWithExtension = $baseImageEntity->getName() . '.' . $baseImageEntity->getFormat();
        // Create image file
        // IMPORTANT! Here, identifier name option is used to pass new name directly,
        // so thanks to this option, there is no need to update Image entity name
        // and rename physical image after ImageManager::createTrickImage() method call!
        $trickImageFile = $imageService->generateTrickImageFile(
            $imageToCropDTO,
            $mediaTypeKey,
            false,
            $baseImageNameWithExtension // identifier name
        );
        if (\is_null($trickImageFile)) {
            return null;
        }
        // Create mandatory Image entity
        $newImageEntity = $imageService->createTrickImage(
            $imageToCropDTO,
            $trickImageFile,
            false
        );
        if (\is_null($newImageEntity)) {
            // Delete physically image which was previously created due to this failure!
            $imageService->deleteOneImageFile(
                null,
                ImageUploader::TRICK_IMAGE_DIRECTORY_KEY,
                $trickImageFile->getFilename()
            );
            return null;
        }
        // Create mandatory Media entity which references corresponding entities:
        // MediaOwner is the attachment (it is a trick here), MediaSource is a image.
        /** @var MediaOwner|null $newMediaOwnerEntity */
        $newMediaOwnerEntity = $trickMediaOwner;
        /** @var MediaSource|null $newMediaSourceEntity */
        $newMediaSourceEntity = $mediaService->getMediaSourceManager()->createMediaSource($newImageEntity);
        if (\is_null($newMediaSourceEntity)) {
            return null;
        }
        // Create mandatory Media entity which references corresponding Image entity
        $newImageMediaEntity = $mediaService->createTrickMedia(
            $newMediaOwnerEntity,
            $newMediaSourceEntity,
            $imageToCropDTO,
            $mediaTypeKey
        );
        if (\is_null($newImageMediaEntity)) {
            return null;
        }
        // Save image data and corresponding media
        $newImageEntity = $imageService->addAndSaveImage($newImageEntity, $newImageMediaEntity, true);
        if (\is_null($newImageEntity)) {
            return null;
        }
        return $newImageEntity;
    }

    /**
     * Manage new trick media collection item creation error context.
     *
     * Please note this method adds a user notification message, and prepares log and exception messages.
     *
     * @param object $collectionItemDataModel
     * @param string $contextKey
     *
     * @return string
     */
    protected function manageTrickMediaCreationError(object $collectionItemDataModel, string $contextKey): string
    {
        // Prepare adapted texts depending on context.
        $text = self::TRICK_NEW_MEDIA_ERROR_TEXTS[$contextKey]['text'];
        $action = self::TRICK_NEW_MEDIA_ERROR_TEXTS[$contextKey]['action'];
        // Prepare log and exception messages
        switch ($collectionItemDataModel) {
            // Image messages
            case $collectionItemDataModel instanceof ImageToCropDTO:
                $imageIdentifierName = $collectionItemDataModel->getSavedImageName();
                $errorMessage = 'Sorry, expected trick was not ' . $text . '!' . "\n" .
                                'An error occurred during image(s) medias handling.';
                $loggerMessage = sprintf(
                    "[trace app SnowTricks] ' . $action . ' => " .
                    "Trick image issue with identifier: %s",
                    $imageIdentifierName
                );
                $exceptionMessage = sprintf(
                    'An error occurred due to an image with identifier name "%s" which was not generated from collection!',
                    $imageIdentifierName
                );
                break;
            // Video messages
            case $collectionItemDataModel instanceof VideoInfosDTO:
                $videoURL = $collectionItemDataModel->getUrl();
                $errorMessage = 'Sorry, expected trick was not ' . $text . '!' . "\n" .
                                'An error occurred during' . "\n" . 'video(s) medias management.';
                $loggerMessage = sprintf(
                    "[trace app SnowTricks] ' . $action . ' => " .
                    "Trick video issue with: %s",
                    $videoURL
                );
                $exceptionMessage = sprintf(
                    'An error occurred due to a video with URL "%s" which was not generated from collection!',
                    $videoURL
                );
                break;
            default:
                $errorMessage = null;
                $loggerMessage = null;
                $exceptionMessage = "An error occurred due to unknown collection item data model!";
        }
        \is_null($errorMessage) ?: $this->flashBag->add('error', $errorMessage);
        \is_null($loggerMessage) ?: $this->logger->error($loggerMessage);
        return $exceptionMessage;
    }

    /**
     * Rename a collection item which corresponds to trick big image with validated root form and update necessary data.
     *
     * Please not image is also physically renamed.
     *
     * @param Image $bigImageEntity
     * @param Trick $trick
     * @param array $actionData
     *
     * @return string|null
     *
     * @throws \Exception
     */
    protected function renameTrickBigImage(
        Image $bigImageEntity,
        Trick $trick,
        array $actionData
    ): ?string {
        /** @var ImageManager $imageService */
        $imageService = $actionData['imageService'];
        // Update big image Image entity (image name corresponds to "saveImageName" ImageToCropDTO property)
        // which was already uploaded without form validation
        $imageName = $bigImageEntity->getName();
        // Rename image name with trick slug (by preserving hash and dimensions infos):
        // the new name will be used for the 3 image formats (thumb , normal, big).
        $newImageName = $imageService->prepareImageName(
            $imageName, $trick->getSlug(),
            ImageManager::TRICK_IMAGE_TYPE_KEY
        );
        // Rename image physically in directory
        $imageNameWithExtension =  $bigImageEntity->getName() . '.' . $bigImageEntity->getFormat();
        $newImageNameWithExtension = $newImageName . '.' . $bigImageEntity->getFormat();
        $isImageRenamed = $imageService->renameImage(
            $imageNameWithExtension,
            $newImageNameWithExtension,
            ImageManager::TRICK_IMAGE_TYPE_KEY,
            true
        );
        if (!$isImageRenamed) {
            return null;
        }
        return $newImageName;
    }

    /**
     * Throw an exception during trick process handling canceling.
     *
     * @param object|null   $collectionItemDataModel
     * @param string        $contextKey
     * @param ImageManager  $imageService
     * @param array|null    $callable                a callable method from a class which extends this abstract class
     * @param array|null    $args                    some arguments to pass to callable method
     *
     * @return void
     *
     * @throws \Exception
     */
    protected function throwTrickProcessException(
        ?object $collectionItemDataModel,
        string $contextKey,
        ImageManager $imageService,
        array $callable = null,
        array $args = null
    ): void {
        // Control expected collection instance type
        $this->checkCollectionInstanceType($collectionItemDataModel);
        // Purge any potential orphaned images physical files
        $imageService->purgeOrphanedImagesFiles(
            ImageUploader::TRICK_IMAGE_DIRECTORY_KEY,
            $imageService->getRepository()->findAll()
        );
        $exceptionMessage = !\is_null($collectionItemDataModel)
            ? $this->manageTrickMediaCreationError($collectionItemDataModel, $contextKey): null;
        // Add possibly some text to exception message with a callable
        if (!\is_null($callable) && !\is_null($args)) {
            array_push($args, $exceptionMessage);
            $exceptionMessage = $callable(...$args);
        }
        throw new \Exception($exceptionMessage);
    }
}
