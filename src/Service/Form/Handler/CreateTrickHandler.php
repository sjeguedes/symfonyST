<?php

declare(strict_types = 1);

namespace App\Service\Form\Handler;

use App\Domain\DTOToEmbed\ImageToCropDTO;
use App\Domain\DTOToEmbed\VideoInfosDTO;
use App\Domain\Entity\Image;
use App\Domain\Entity\MediaOwner;
use App\Domain\Entity\MediaSource;
use App\Domain\Entity\MediaType;
use App\Domain\Entity\Trick;
use App\Domain\Entity\User;
use App\Domain\ServiceLayer\ImageManager;
use App\Domain\ServiceLayer\MediaManager;
use App\Domain\ServiceLayer\TrickManager;
use App\Domain\ServiceLayer\VideoManager;
use App\Service\Form\Collection\DTOCollection;
use App\Service\Form\Type\Admin\CreateTrickType;
use App\Service\Medias\Upload\ImageUploader;
use App\Utils\Traits\CSRFTokenHelperTrait;
use App\Utils\Traits\StringHelperTrait;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Class CreateTrickHandler.
 *
 * Handle the form request when a member tries to create a new trick.
 * Call any additional validations and actions.
 */
final class CreateTrickHandler extends AbstractUploadFormHandler
{
    use CSRFTokenHelperTrait;
    use LoggerAwareTrait;
    use StringHelperTrait;

    /**
     * @var csrfTokenManagerInterface
     */
    private $csrfTokenManager;

    /*
     * @var Trick
     */
    private $newTrick;

    /**
     * @var Security
     */
    private $security;

    /**
     * CreateTrickHandler constructor.
     *
     * @param CsrfTokenManagerInterface $csrfTokenManager
     * @param FlashBagInterface         $flashBag
     * @param FormFactoryInterface      $formFactory
     * @param RequestStack              $requestStack
     * @param LoggerInterface           $logger
     * @param Security                  $security
     */
    public function __construct(
        csrfTokenManagerInterface $csrfTokenManager,
        FlashBagInterface $flashBag,
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        LoggerInterface $logger,
        Security $security
    ) {
        parent::__construct($flashBag, $formFactory, CreateTrickType::class, $requestStack);
        $this->csrfTokenManager = $csrfTokenManager;
        $this->customError = null;
        $this->newTrick = null;
        $this->setLogger($logger);
        $this->security = $security;
    }

    /**
     * Add custom validation to check once form constraints are validated.
     *
     * @param array $actionData some data to handle
     *
     * @return bool
     *
     * @throws \Exception
     *
     * @see AbstractFormHandler::processFormRequest()
     */
    protected function addCustomValidation(array $actionData) : bool
    {
        $csrfToken = $this->request->request->get('create_trick')['token'];
        // CSRF token is not valid.
        if (false === $this->isCSRFTokenValid('create_trick_token', $csrfToken)) {
            throw new \Exception('Security error: CSRF form token is invalid!');
        }
        // Check TrickManager instance in passed data
        $this->checkNecessaryData($actionData);
        /** @var TrickManager $trickService */
        $trickService = $actionData['trickService'];
        // DTO is in valid state but filled in trick name (title) already exist in database: it must be unique!
        $isTrickNameUnique = \is_null($trickService->findSingleByName($this->form->getData()->getName())) ? true : false; // or $this->form->get('name')->getData()
        if (!$isTrickNameUnique) {
            $trickNameError = nl2br('Please check chosen title!' . "\n" .
                'A trick with the same name already exists.'
            );
            $this->customError = $trickNameError;
            $this->flashBag->add(
                'danger',
                nl2br('Trick creation failed!' . "\n" .
                'Try to request again by checking the form fields.'
                )
            );
            return false;
        }
        return true;
    }

    /**
     * Add custom action once form is validated.
     *
     * @param array $actionData some data to handle
     *
     * @return void
     *
     * @throws \Exception
     *
     * @see AbstractFormHandler::processFormRequest()
     */
    protected function addCustomAction(array $actionData) : void
    {
        // Check Managers instances in passed data
        $this->checkNecessaryData($actionData);
        // Start process by persisting (this the defined state at this time) a new Trick entity (with a media owner)
        // which shall be removed later as a kind of rollback!
        $newTrick = $this->startTrickCreationProcess($actionData);
        // Get data model collections
        $createTrickDTO = $this->form->getData();
        $imagesDTOCollection = $createTrickDTO->getImages();
        $videosDTOCollection = $createTrickDTO->getVideos();
        // Add collections items corresponding Media entities to Trick entity
        $this->addTrickMediasFromCollections($newTrick, $imagesDTOCollection, $videosDTOCollection, $actionData);
        // Finish process by trying to save trick and inform about state (success or error notification message)
        $this->terminateTrickCreationProcess($newTrick, $actionData);
    }

    /**
     * Add Media collections to Trick with loop.
     *
     * Please note Trick creation stop process "rollback" can be called if an issue occurred on item.
     *
     * @param Trick         $newTrick
     * @param DTOCollection $imagesDTOCollection
     * @param DTOCollection $videosDTOCollection
     * @param array         $actionData
     *
     * @return void
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    private function addTrickMediasFromCollections(
        Trick $newTrick,
        DTOCollection $imagesDTOCollection,
        DTOCollection $videosDTOCollection,
        array $actionData
    ) : void
    {
        // Loop on existing form images collection to create images and merge corresponding medias with the new trick
        /** @var ImageToCropDTO $imageToCropDTO */
        foreach ($imagesDTOCollection as $imageToCropDTO) {
            // Create image with corresponding Image and Media entities
            $isImageAdded = $this->addTrickImageFromCollection($newTrick, $imageToCropDTO, $actionData);
            if (!$isImageAdded) {
                // Call a "rollback" to cancel Trick creation process.
                // CAUTION! Here a exception will be thrown!
                $this->cancelTrickCreationProcess($imageToCropDTO, $newTrick, $actionData);
                break;
            }
        }
        // Loop on existing form videos collection to create videos and merge corresponding medias with the new trick
        /** @var VideoInfosDTO $videoInfosDTO */
        foreach ($videosDTOCollection as $videoInfosDTO) {
            // Create video with corresponding Video and Media entities
            $isVideoAdded = $this->addTrickVideoFromCollection($newTrick, $videoInfosDTO, $actionData);
            if (!$isVideoAdded) {
                // Call a "rollback" to cancel Trick creation process.
                // CAUTION! Here a exception will be thrown!
                $this->cancelTrickCreationProcess($videoInfosDTO, $newTrick, $actionData);
                break;
            }
        }
    }

    /**
     * Add the three expected images (for one uploaded image)
     * and create/update Image/Media entities from trick image collection.
     *
     * Please note this information:
     * - 3 formats are finally generated after handling: 1600x900, 880x495, 400x225.
     * - The identifier name used corresponds to a slug based on trick name which will be created with the root form.
     * - A hash is automatically added to make image name unique
     * - Images names respects this principle: identifierName-hash-format.extension (e.g. mctwist-b1853337-880x495.jpeg)
     *
     * @param Trick          $newTrick
     * @param ImageToCropDTO $imageToCropDTO
     * @param array          $actionData
     *
     * @return bool
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    private function addTrickImageFromCollection(
        Trick $newTrick,
        ImageToCropDTO $imageToCropDTO,
        array $actionData
    ) : bool
    {
        /** @var ImageManager $imageService */
        $imageService = $actionData['imageService'];
        // Get trick media owner
        $newTrickMediaOwner = $newTrick->getMediaOwner();
        // Retrieve big image Image and Media entities thanks to saved image name with loop
        // which was already uploaded on server during form validation thanks to corresponding DTO with its "savedImageName" property.
        // 1. Update base big image
        $bigImageEntity = $imageService->findSingleByName($imageToCropDTO->getSavedImageName());
        // Rename (with trick name slug) big image which is used in corresponding Image entity
        $newImageName = $this->renameTrickBigImage($bigImageEntity, $newTrick, $actionData);
        if (\is_null($newImageName)) {
            return false;
        }
        // Add Media entity in trick medias Collection (which can be removed in case of failure!)
        $bigImageMediaEntity = $bigImageEntity->getMediaSource()->getMedia();
        // Modify MediaOwner entity: "direct upload" forced media owner to be null before and must be updated here!
        $bigImageMediaEntity->modifyMediaOwner($newTrickMediaOwner);
        $newTrickMediaOwner->addMedia($bigImageMediaEntity);
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
            $newTrick,
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
        $newTrickMediaOwner->addMedia($thumbImageMediaEntity);
        // 3. Normal image (intermediate format)
        $normalImageEntity = $this->createTrickImageWithMandatoryFormat(
            $newTrick,
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
        $newTrickMediaOwner->addMedia($normalImageMediaEntity);
        return true;
    }

    /**
     * Add a Video entity from trick video collection.
     *
     * @param Trick         $newTrick
     * @param VideoInfosDTO $videoInfosDTO
     * @param array         $actionData
     *
     * @return bool
     *
     * @throws \Exception
     */
    private function addTrickVideoFromCollection(
        Trick $newTrick,
        VideoInfosDTO $videoInfosDTO,
        array $actionData
    ) : bool
    {
        /** @var VideoManager $videoService */
        $videoService = $actionData['videoService'];
        /** @var MediaManager $mediaService */
        $mediaService = $actionData['mediaService'];
        // Get trick media owner
        $newTrickMediaOwner = $newTrick->getMediaOwner();
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
        $newMediaOwnerEntity = $newTrickMediaOwner;
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
        $newTrickMediaOwner->addMedia($newVideoMediaEntity);
        return true;
    }

    /**
     * Cancel Trick creation process as a kind of "rollback".
     *
     * @param object|null $collectionItemDataModel
     * @param Trick       $newTrick
     * @param array       $actionData
     * @param bool        $isExceptionThrown
     *
     * @throws \Exception
     *
     * @return bool
     */
    private function cancelTrickCreationProcess(
        ?object $collectionItemDataModel,
        Trick $newTrick,
        array $actionData,
        bool $isExceptionThrown = true
    ) : bool
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
        /** @var TrickManager $trickService */
        $trickService = $actionData['trickService'];
        /** @var ImageManager $imageService */
        $imageService = $actionData['imageService'];
        /** @var MediaManager $mediaService */
        $mediaService = $actionData['mediaService'];
        // Initialize state
        $isProcessCorrectlyCanceled = true;
        // Delete all images physically created during process
        // thanks to Trick entity for both cases (image or video issue)
        $imageService->deleteAllTrickImagesFiles($newTrick);
        // Remove each media entity (Image or Video) and its dependencies thanks to cascade option
        // Flush is made at the end of process and not in a loop
        foreach ($newTrick->getMediaOwner()->getMedias() as $media) {
            if (!$isMediaRemoved = $mediaService->removeMedia($media, false)) {
                $this->logger->critical(
                    sprintf(
                        "[trace app snowTricks] CreateTrickHandler/cancelTrickCreationProcess => error: " .
                        "images removal was not performed correctly!"
                    )
                );
                $isProcessCorrectlyCanceled = false;
                break;
            }
        }
        // Remove all previous created images with this fallback
        // by removing Trick entity (and also Media Collection with cascade)!
        if (!$isTrickRemoved = $trickService->removeTrick($newTrick)) {
            $isProcessCorrectlyCanceled = false;
        }
        // Choice is made to create a exception!
        if ($isExceptionThrown) {
            // Throw an exception if at least one collection item failed to be created!
            $exceptionMessage = !\is_null($collectionItemDataModel)
                ? $this->manageTrickMediaCreationError($collectionItemDataModel) : null;
            // Add trick removal issue to exception
            if (!$isTrickRemoved) {
                $exceptionMessage = $this->manageTrickRemovalError($newTrick, $exceptionMessage);
            }
            throw new \Exception($exceptionMessage);
        }
        return $isProcessCorrectlyCanceled;
    }

    /**
     * Create 2 other formats based on a saved image (direct upload) during root form validation.
     *
     * Please note base (big) image is the highest available format, so the two others are created with resize operation.
     *
     * @param Trick          $newTrick
     * @param ImageToCropDTO $imageToCropDTO
     * @param Image          $baseImageEntity
     * @param string         $mediaTypeKey
     * @param array          $actionData
     *
     * @return Image|null
     *
     * @throws \Exception
     */
    private function createTrickImageWithMandatoryFormat(
        Trick $newTrick,
        ImageToCropDTO $imageToCropDTO,
        Image $baseImageEntity,
        string $mediaTypeKey,
        array $actionData
    ) : ?Image
    {
        /** @var ImageManager $imageService */
        $imageService = $actionData['imageService'];
        /** @var MediaManager $mediaService */
        $mediaService = $actionData['mediaService'];
        // Get trick media owner
        $newTrickMediaOwner = $newTrick->getMediaOwner();
        // Get identifier name (included format will be replaced depending on new format to generate)
        $baseImageNameWithExtension = $baseImageEntity->getName() . '.' . $baseImageEntity->getFormat();
        // Create image file
        // IMPORTANT! Here, identifier name option is used to pass new name directly,
        // so thanks to this option, there is no need to update Image entity name
        // and rename physical image after ImageManager::createTrickImage() method call!
        $newTrickImageFile = $imageService->generateTrickImageFile(
            $imageToCropDTO,
            $mediaTypeKey,
            false,
            $baseImageNameWithExtension // identifier name
        );
        if (\is_null($newTrickImageFile)) {
            return null;
        }
        // Create mandatory Image entity
        $newImageEntity = $imageService->createTrickImage(
            $imageToCropDTO,
            $newTrickImageFile,
            false
        );
        if (\is_null($newImageEntity)) {
            // Remove physically image which was previously created due to this failure!
            $imageService->removeOneImageFile(
                null,
                ImageUploader::TRICK_IMAGE_DIRECTORY_KEY,
                $newTrickImageFile->getFilename()
            );
            return null;
        }
        // Create mandatory Media entity which references corresponding entities:
        // MediaOwner is the attachment (it is a trick here), MediaSource is a image.
        /** @var MediaOwner|null $newMediaOwnerEntity */
        $newMediaOwnerEntity = $newTrickMediaOwner;
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
     * Get new created trick.
     *
     * @return Trick|null
     */
    public function getNewTrick() : ?Trick
    {
        return $this->newTrick;
    }

    /**
     * Get the trick creation error.
     *
     * @return string|null
     */
    public function getTrickCreationError() : ?string
    {
        return $this->customError;
    }

    /**
     * Manage media collection item creation error context.
     *
     * Please note this method adds a user notification message, logs error and prepares exception message.
     *
     * @param object $collectionItemDataModel
     *
     * @return string
     *
     * @throws \Exception
     */
    private function manageTrickMediaCreationError(object $collectionItemDataModel) : string
    {
        switch ($collectionItemDataModel) {
            // Image messages
            case $collectionItemDataModel instanceof ImageToCropDTO:
                $imageIdentifierName = $collectionItemDataModel->getSavedImageName();
                $errorMessage = nl2br('Sorry, expected trick was not created!' . "\n" .
                    'An error occurred during image(s) medias handling.'
                );
                $loggerMessage = sprintf(
                    "[trace app snowTricks] CreateTrickHandler/cancelTrickCreationProcess => " .
                    "Trick image issue with identifier: %s",
                    $imageIdentifierName
                );
                $exceptionMessage = sprintf(
                    'An error occurred due to an image with identifier name "%s" which was not created from collection!',
                    $imageIdentifierName
                );
                break;
            //Video messages
            case $collectionItemDataModel instanceof VideoInfosDTO:
                $videoURL = $collectionItemDataModel->getUrl();
                $errorMessage = nl2br(
                    'Sorry, expected trick was not created!' . "\n" .
                    'An error occurred during' . "\n" . 'video(s) medias management.'
                );
                $loggerMessage = sprintf(
                    "[trace app snowTricks] CreateTrickHandler/cancelTrickCreationProcess => " .
                    "Trick video issue with: %s",
                    $videoURL
                );
                $exceptionMessage = sprintf(
                    'An error occurred due to a video with URL "%s" which was not created from collection!',
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
     * Manage trick removal error context.
     *
     * Please note this method logs error and update final exception message.
     *
     * @param Trick       $newTrick
     * @param string|null $exceptionMessage
     *
     * @return string
     *
     * @see manageTrickMediaCreationError() method
     */
    private function manageTrickRemovalError(Trick $newTrick, ?string $exceptionMessage) : string
    {
        $trickUuid = $newTrick->getUuid()->toString();
        $trickName = addslashes($newTrick->getName());
        $loggerMessage = sprintf(
            "[trace app snowTricks] CreateTrickHandler/cancelTrickCreationProcess => " .
            "Trick removal issue with \"%s\" which has uuid: %s",
            $trickName,
            $trickUuid
        );
        $this->logger->error($loggerMessage);
        if (!\is_null($exceptionMessage)) {
            $exceptionMessage .= ' Also, trick was not correctly removed as a second issue!';
        } else {
            $exceptionMessage = 'An error happened! Trick was not correctly removed.';
        }
        return $exceptionMessage;
    }

    /**
     * Rename a collection item which corresponds to trick big image with validated root form and update necessary data.
     *
     * Please not image is also physically renamed.
     *
     * @param Image $bigImageEntity
     * @param Trick $newTrick
     * @param array $actionData
     *
     * @return string|null
     *
     * @throws \Exception
     */
    private function renameTrickBigImage(
        Image $bigImageEntity,
        Trick $newTrick,
        array $actionData
    ) : ?string
    {
        /** @var ImageManager $imageService */
        $imageService = $actionData['imageService'];
        // Update big image Image entity (image name corresponds to "saveImageName" ImageToCropDTO property)
        // which was already uploaded without form validation
        $imageName = $bigImageEntity->getName();
        // Rename image name with trick slug (by preserving hash and dimensions infos):
        // the new name will be used for the 3 image formats (thumb , normal, big).
        $newImageName = $imageService->prepareImageName(
            $imageName, $newTrick->getSlug(),
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
     * Start trick creation process by generating a new Trick entity.
     *
     * Please not choice was made to flush entity here to perform removal easily later!
     *
     * @param array $actionData
     *
     * @return Trick|null
     *
     * @throws \Exception
     */
    private function startTrickCreationProcess(array $actionData) : ?Trick
    {
        /** @var TrickManager $trickService */
        $trickService = $actionData['trickService'];
        /** @var MediaManager $mediaService */
        $mediaService = $actionData['mediaService'];
        /** @var User|UserInterface $authenticatedUser */
        $authenticatedUser = $this->security->getUser();
        // Get form data
        $createTrickDTO = $this->form->getData();
        // Create a new Trick entity and save it immediately
        // Persist it without flush (must be called at the end of process)
        $newTrick = $trickService->createTrick($createTrickDTO, $authenticatedUser, true);
        if (\is_null($newTrick)) {
            return null;
        }
        // Create a trick media owner (with media service shortcut) to bind future medias
        // Persist it without flush (must be called at the end of process)
        $mediaOwner = $mediaService->getMediaOwnerManager()->createMediaOwner($newTrick, true);
        if (\is_null($mediaOwner)) {
            return null;
        }
        return $newTrick;
    }

    /**
     * End trick creation process by trying to save all data and showing state notification message.
     *
     * @param Trick $newTrick
     * @param array $actionData
     *
     * @return void
     *
     * @throws \Exception
     *
     * @see https://paragonie.com/blog/2015/06/preventing-xss-vulnerabilities-in-php-everything-you-need-know
     */
    private function terminateTrickCreationProcess(Trick $newTrick, array $actionData) : void
    {
        /** @var TrickManager $trickService */
        $trickService = $actionData['trickService'];
        // Save collections data when flushing Trick entity thanks to cascade operations
        $savedTrick = $trickService->addAndSaveTrick($newTrick, false, true);
        // Create success notification message
        $state = 'success';
        $message = nl2br(
            sprintf(
            'A new trick called' . "\n" . '"%s"' . "\n" . 'was created successfully!' . "\n" .
            'Please check trick detail below to look at content.',
            // Can also be escaped with htmlspecialchars()
            htmlentities($newTrick->getName(), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        ));
        // Create failure notification message
        if (\is_null($savedTrick)) {
            // Delete all images physically created during process
            // and remove Trick and its associated (image or video) medias entities
            $isProcessCorrectlyCanceled = $this->cancelTrickCreationProcess(
                null,
                $newTrick,
                $actionData,
                false
            );
            $state = 'error';
            $message = nl2br(
                'Sorry, trick creation failed' . "\n" .
                'due a technical error!' . "\n" .
                'Please try again with new data' . "\n" .
                'or contact us if necessary.'
            );
            if (!$isProcessCorrectlyCanceled) {
                $loggerMessage = sprintf(
                    "[trace app snowTricks] CreateTrickHandler/terminateTrickCreationProcess =>" .
                    "Trick or associated medias removal issue with \"%s\" which has uuid: %s",
                    $newTrick->getName(),
                    $newTrick->getUuid()->toString()
                );
                $this->logger->error($loggerMessage);
            }
        } else {
            // Feed property to use new trick data for redirection in controller (action)
            $this->newTrick = $newTrick;
        }
        $this->flashBag->add($state, $message);
    }
}
