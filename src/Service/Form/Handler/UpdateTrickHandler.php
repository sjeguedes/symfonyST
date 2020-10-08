<?php

declare(strict_types=1);

namespace App\Service\Form\Handler;

use App\Domain\DTO\UpdateTrickDTO;
use App\Domain\DTOToEmbed\ImageToCropDTO;
use App\Domain\DTOToEmbed\VideoInfosDTO;
use App\Domain\Entity\Image;
use App\Domain\Entity\Media;
use App\Domain\Entity\MediaType;
use App\Domain\Entity\Trick;
use App\Domain\Entity\User;
use App\Domain\Entity\Video;
use App\Domain\ServiceLayer\ImageManager;
use App\Domain\ServiceLayer\MediaManager;
use App\Domain\ServiceLayer\TrickManager;
use App\Domain\ServiceLayer\VideoManager;
use App\Service\Form\Collection\DTOCollection;
use App\Service\Form\Type\Admin\UpdateTrickType;
use App\Service\Medias\Upload\ImageUploader;
use App\Utils\Traits\CSRFTokenHelperTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use http\Exception\RuntimeException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Class UpdateTrickHandler.
 *
 * Handle the form request when a member tries to update a new trick.
 * Call any additional validations and actions.
 *
 * Please note authenticated member must be trick author or administrator
 * to be able to update a trick.
 */
final class UpdateTrickHandler extends AbstractTrickFormHandler implements InitModelDataInterface
{
    use CSRFTokenHelperTrait;

    /**
     * @var csrfTokenManagerInterface
     */
    private $csrfTokenManager;

    /**
     * DTOCollection|ImageToCropDTO[]|null
     */
    private $initialImagesDTOCollection;

    /**
     * DTOCollection|VideoInfosDTO[]|null
     */
    private $initialVideosDTOCollection;

    /**
     * @var Collection|Media[]|null
     */
    private $initialTrickMediaList;

    /**
     * @var string|null
     */
    private $initialTrickName;

    /*
     * @var Trick|null
     */
    private $updatedTrick;

    /**
     * RegisterHandler constructor.
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
        parent::__construct(
            $flashBag,
            $formFactory,
            UpdateTrickType::class,
            $requestStack,
            $logger,
            $security
        );
        $this->csrfTokenManager = $csrfTokenManager;
        $this->customError = null;
        // Prepare initial DTO collections properties
        $this->initialImagesDTOCollection = null;
        $this->initialVideosDTOCollection = null;
        // Will store Trick to update, its initial name and  associated Media as Doctrine collection
        $this->initialTrickMediaList = null;
        $this->initialTrickName = null;
        $this->updatedTrick = null;
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
    protected function addCustomValidation(array $actionData): bool
    {
        $csrfToken = $this->request->request->get('update_trick')['token'];
        // CSRF token is not valid.
        if (false === $this->isCSRFTokenValid('update_trick_token', $csrfToken)) {
            throw new \Exception('Security error: CSRF form token is invalid!');
        }
        // Check TrickManager et Trick instances in passed data
        $this->checkNecessaryData($actionData);
        /** @var TrickManager $trickService */
        $trickService = $actionData['trickService'];
        /** @var Trick $trickToUpdate */
        $trickToUpdate = $actionData['trickToUpdate'];
        // DTO is in valid state but:
        // Each video URL must be unique (This avoids issue with Javascript!).
        if (!$isEachVideoURLUnique = $this->checkUniqueVideoUrl($this->form->getData()->getVideos())) {
            $uniqueVideoURLError = 'Please check all videos URL!' . "\n" . 'Each one must be unique!';
            $this->customError = $uniqueVideoURLError;
            $this->flashBag->add(
                'danger',
                'Trick update failed!' . "\n" .
                         'Try to request again by checking the form fields.'
            );
            return false;
        }
        // DTO is in valid state but:
        // Filled in trick name (title) already exist in database for another trick!
        // It can obviously be the same or similar as previous trick name or must be unique.
        $submittedName = $this->form->getData()->getName(); // or $this->form->get('name')->getData()
        // First, check only current trick to update
        if (!$trickService->checkSameOrSimilarTrickName($submittedName, $trickToUpdate, true)) {
            // Is submitted trick name (or similar name) not used by existing ones? (trick to update is excluded)?
            if ($isSubmittedNameNotUnique = $trickService->checkSameOrSimilarTrickName($submittedName, $trickToUpdate)) {
                $trickNameError = 'Please check chosen title!' . "\n" .
                                  'Another trick with the same name' . "\n" .
                                  '(or similar name) already exists.';
                $this->customError = $trickNameError;
                $this->flashBag->add(
                    'danger',
                    'Trick update failed!' . "\n" .
                             'Try to request again by checking the form fields.'
                );
                return false;
            }
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
    protected function addCustomAction(array $actionData): void
    {
        // Check Managers instances in passed data
        $this->checkNecessaryData($actionData);
        // Start process by simply retrieving trick to update in data
        $trickToUpdate = $this->startTrickUpdateProcess($actionData);
        // Get data model collections
        $updateTrickDTO = $this->form->getData();
        $imagesDTOCollection = $updateTrickDTO->getImages();
        $videosDTOCollection = $updateTrickDTO->getVideos();
        // Refresh collections items corresponding Media (images or videos) entities to update Trick entity
        $this->refreshTrickMediasFromCollections($trickToUpdate, $imagesDTOCollection, $videosDTOCollection, $actionData);
        // Finish process by trying to save trick changes and inform about state (success or error notification message)
        $this->terminateTrickUpdateProcess($trickToUpdate, $actionData);
    }

    /**
     * Add new images or update existing ones depending on new images collection provided by the form.
     *
     * @param Trick         $trickToUpdate
     * @param DTOCollection $imagesDTOCollection
     * @param array         $actionData
     *
     * @return DTOCollection a DTO removal collection to delete images which does not exist anymore
     *
     * @throws \Exception
     */
    private function addOrUpdateImagesDTOCollection(
        Trick $trickToUpdate,
        DTOCollection $imagesDTOCollection,
        array $actionData
    ): DTOCollection {
        /** @var ImageManager $imageService */
        $imageService = $actionData['imageService'];
        /** @var DTOCollection $previousImagesDTOCollection */
        $previousImagesDTOCollection = $this->initialImagesDTOCollection;
        $nextImagesDTOCollection = $imagesDTOCollection;
        // Prepare a potential images collection to remove if each item does not exist in new images collection
        $imagesDTOCollectionToRemove = new DTOCollection($previousImagesDTOCollection->getAll());
        /** @var ImageToCropDTO $nextImageToCropDTO */
        foreach ($nextImagesDTOCollection as $nextImageToCropDTO) {
            // Check if image is temporary!
            $isImageTemporary = $this->checkTemporaryImageName($nextImageToCropDTO->getSavedImageName());
            // Add temporary image by creating image corresponding Image and Media entities
            if ($isImageTemporary) {
                $isImageAdded = $this->addTrickImageFromCollection($trickToUpdate, $nextImageToCropDTO, $actionData);
                // CAUTION! Here a exception will be thrown!
                if (!$isImageAdded) {
                    // CAUTION! Here a exception will be thrown! Call a "rollback" to cancel Trick update process.
                    $this->cancelTrickUpdateProcess($nextImageToCropDTO, $trickToUpdate, $actionData);
                    break;
                }
                // Temporary image was possibly associated to trick during update form validation!
                if ($previousImagesDTOCollection->has($nextImageToCropDTO)) {
                    // Exclude temporary image correctly from deletion
                    $imagesDTOCollectionToRemove->delete($nextImageToCropDTO);
                }
                // Checking update or removal actions is useless!
                continue;
            }
            // Update existing Image and corresponding Media entities, if it is found in new collection.
            if ($previousImagesDTOCollection->has($nextImageToCropDTO)) {
                // CAUTION: This image to crop DTO still exist and must excluded from images collection to remove!
                $imagesDTOCollectionToRemove->delete($nextImageToCropDTO);
                // Update image
                $this->updateTrickImageFromCollection($nextImageToCropDTO);
            }
        }
        // Remove any empty temporary directory
        $imageService->removeEmptyTemporaryDirectory(ImageUploader::TRICK_IMAGE_DIRECTORY_KEY);
        return $imagesDTOCollectionToRemove;
    }

    /**
     * Add new videos or update existing ones depending on new videos collection provided by the form.
     *
     * @param Trick         $trickToUpdate
     * @param DTOCollection $videosDTOCollection
     * @param array         $actionData
     *
     * @return DTOCollection a DTO removal collection to delete videos which does not exist anymore
     *
     * @throws \Exception
     */
    private function addOrUpdateVideosDTOCollection(
        Trick $trickToUpdate,
        DTOCollection $videosDTOCollection,
        array $actionData
    ): DTOCollection {
        /** @var DTOCollection $previousVideosDTOCollection */
        $previousVideosDTOCollection = $this->initialVideosDTOCollection;
        $nextVideosDTOCollection = $videosDTOCollection;
        // Prepare a potential images collection to remove if each item does not exist in new images collection
        $videosDTOCollectionToRemove = new DTOCollection($previousVideosDTOCollection->getAll());
        /** @var VideoInfosDTO  $nextVideoInfosDTO */
        foreach ($nextVideosDTOCollection as $nextVideoInfosDTO) {
            // Update existing Video and corresponding Media entities, if it is found in new collection.
            if ($previousVideosDTOCollection->has($nextVideoInfosDTO)) {
                // CAUTION: This video DTO still exist and must be excluded from videos collection to remove!
                $videosDTOCollectionToRemove->delete($nextVideoInfosDTO);
                // Update video
                $this->updateTrickVideoFromCollection($nextVideoInfosDTO);
                // Create video with corresponding Video and Media entities
            } else {
                // Create video with corresponding Video and Media entities
                $isVideoAdded = $this->addTrickVideoFromCollection($trickToUpdate, $nextVideoInfosDTO, $actionData);
                if (!$isVideoAdded) {
                    // CAUTION! Here a exception will be thrown! Call a "rollback" to cancel Trick update process.
                    $this->cancelTrickUpdateProcess($nextVideoInfosDTO, $trickToUpdate, $actionData);
                    break;
                }
            }
        }
        return $videosDTOCollectionToRemove;
    }

    /**
     * Cancel Trick update process as a kind of "rollback".
     *
     * @param object|null $collectionItemDataModel
     * @param Trick       $trick
     * @param array       $actionData
     * @param bool        $isExceptionThrown
     *
     * @throws \Exception
     *
     * @return bool
     */
    private function cancelTrickUpdateProcess(
        ?object $collectionItemDataModel,
        Trick $trick,
        array $actionData,
        bool $isExceptionThrown = true
    ): bool {
        /** @var ImageManager $imageService */
        $imageService = $actionData['imageService'];
        /** @var MediaManager $mediaService */
        $mediaService = $actionData['mediaService'];
        // Initialize state
        $isProcessCorrectlyCanceled = true;
        // thanks to Trick entity for both cases (image or video issue):
        // CAUTION: Filter Trick entity Media list to remove only new added Media!
        $trickMediaListToRemove = $this->setTrickMediaListToRemove($trick, $this->initialTrickMediaList);
        // Remove each media entity (Image or Video) and its dependencies thanks to cascade option
        // Flush is made at the end of process and not in a loop
        foreach ($trickMediaListToRemove as $media) {
            // Filter Image entities to get their names
            $imageFullName = null;
            /** @var Image $imageEntity */
            if (!\is_null($imageEntity = $media->getMediaSource()->getImage())) {
                $imageFullName = $imageEntity->getName() . '.' . $imageEntity->getFormat();
            }
            if (!$isMediaRemoved = $mediaService->removeMedia($media, false)) {
                $this->logger->critical(
                    sprintf(
                        "[trace app SnowTricks] UpdateTrickHandler/cancelTrickUpdateProcess => error: " .
                        "images removal was not performed correctly!"
                    )
                );
                $isProcessCorrectlyCanceled = false;
                break;
            }
            // Delete all images physically created during process
            // Here it is better to delete image one by one after entities correct removal!
            if (!\is_null($imageFullName)) {
                $imageService->deleteOneImageFile(
                    null,
                    ImageUploader::TRICK_IMAGE_DIRECTORY_KEY,
                    $imageFullName
                );
            }
        }
        // Choice is made to create an exception!
        if ($isExceptionThrown) {
            // Throw an exception if at least one collection item failed to be created!
            $this->throwTrickProcessException(
                $collectionItemDataModel,
                AbstractTrickFormHandler::TRICK_UPDATE_LABEL,
                $imageService
            );
        }
        return $isProcessCorrectlyCanceled;
    }

    /**
     * Check if an image is temporary depending on its identifier name.
     *
     * @param string $savedImageName
     *
     * @return bool
     */
    private function checkTemporaryImageName(string $savedImageName): bool
    {
        // Get default temporary name as pattern
        $temporaryIdentifier = ImageManager::DEFAULT_IMAGE_IDENTIFIER_NAME;
        return $isImageTemporary = (bool) preg_match('/' . $temporaryIdentifier . '/', $savedImageName);
    }

    /**
     * Get updated trick.
     *
     * @return Trick|null
     */
    public function getUpdatedTrick(): ?Trick
    {
        return $this->updatedTrick;
    }

    /**
     * Get the trick update error.
     *
     * @return string|null
     */
    public function getTrickUpdateError(): ?string
    {
        return $this->customError;
    }

    /**
     * Initialize a set of images and videos DTO instances as DTOCollection instance.
     * to be used for images and videos collections.
     *
     * @param Trick  $trick
     * @param string $type
     *
     * @return DTOCollection
     *
     * @throws \Exception
     */
    private function initTrickMediasCollectionDataBySourceType(Trick $trick, string $type): DTOCollection
    {
        if (!preg_match('/^image|video$/', $type)) {
            throw new RuntimeException('Media source type label to create collection is unknown!');
        }
        $trickMedias = $trick->getMediaOwner()->getMedias();
        // Prepare a new DTOCollection instance
        $DTOCollection = new DTOCollection();
        foreach ($trickMedias as $media) {
            if ($type === $media->getMediaType()->getSourceType()) {
                switch ($type) {
                    case 'image':
                        // Keep only big images to retrieve saved image name in form
                        if (MediaType::TYPE_CHOICES['trickBig'] !== $media->getMediaType()->getType()) {
                            continue;
                        }
                        $image = $media->getMediaSource()->getImage();
                        $DTOCollection->add(
                            new ImageToCropDTO(
                                null, // No uploaded file exists for "image to crop" initial data model!
                                $image->getDescription(),
                                null, // This is null (feed with JS only) to avoid validation issue.
                                null, // This is null (feed with JS only) to avoid validation issue.
                                $image->getName(),
                                $media->getShowListRank(),
                                $media->getIsMain()
                            )
                        );
                        break;
                    case 'video':
                        $video = $media->getMediaSource()->getVideo();
                        $DTOCollection->add(
                            new VideoInfosDTO(
                                $video->getUrl(),
                                $video->getDescription(),
                                $video->getName(),
                                $media->getShowListRank()
                            )
                        );
                        break;
                }
            }
        }
        return $DTOCollection;
    }

    /**
     * {@inheritDoc}
     *
     * @return object a UpdateTrickDTO instance
     *
     * @throws \Exception
     */
    public function initModelData(array $data): object
    {
        // Check Trick instance in passed data
        $this->checkNecessaryData($data);
        /** @var Trick $trick */
        $trick = $data['trickToUpdate'];
        // Store initial images and videos DTO collections for future comparison
        $this->initialImagesDTOCollection = $this->initTrickMediasCollectionDataBySourceType($trick, 'image');
        $this->initialVideosDTOCollection = $this->initTrickMediasCollectionDataBySourceType($trick, 'video');
        // Get initial form data model
        return new UpdateTrickDTO(
            $trick->getTrickGroup(),
            $trick->getName(),
            $trick->getDescription(),
            $this->initialImagesDTOCollection,
            $this->initialVideosDTOCollection,
            $trick->getIsPublished()
        );
    }

    /**
     * Set a list of Trick Media entities to remove.
     *
     * @param Trick                   $trick
     * @param Collection|Media[]|null $excludedMediasList a Media list to exclude which as Doctrine Collection
     *
     * @return Collection
     */
    public function setTrickMediaListToRemove(Trick $trick, Collection $excludedMediasList = null): Collection
    {
        // Get current trick media list
        $currentList = $trick->getMediaOwner()->getMedias();
        if (\is_null($excludedMediasList)) {
            return $currentList;
        }
        $removalList = new ArrayCollection();
        // Loop on current list (images and videos)
        foreach ($currentList as $mediaEntity) {
            // Filter images:
            if (!\is_null($imageEntity = $mediaEntity->getMediaSource()->getImage())) {
                // Check if image is temporary!
                $isImageTemporary = $this->checkTemporaryImageName($imageEntity->getName());
                // Add corresponding media if true
                !$isImageTemporary ?: $removalList->add($mediaEntity);
                continue;
            }
            // Loop on media list to exclude which is in this case an initial list before update
            $mustMediaBeRemoved = true;
            foreach ($excludedMediasList as $mediaEntityToExclude) {
                // Compare medias one by one from both lists to check exclusion
                if ($mediaEntity->getUuid() === $mediaEntityToExclude->getUuid()) {
                    $mustMediaBeRemoved = false;
                    break;
                }
            }
            // Add media to removal list to perform action later
            if ($mustMediaBeRemoved) {
                $removalList->add($mediaEntity);
            }
        }
        return $removalList;
    }

    /**
     * Refresh Media collections from DTO collections to update Trick with loop.
     *
     * Please note Trick update stop process "rollback" can be called if an issue occurred on item.
     * Medias can be added, updated or removed from trick image/videos collections.
     *
     * @param Trick         $trickToUpdate
     * @param DTOCollection $imagesDTOCollection
     * @param DTOCollection $videosDTOCollection
     * @param array         $actionData
     *
     * @return void
     *
     * @throws \Exception
     */
    private function refreshTrickMediasFromCollections(
        Trick $trickToUpdate,
        DTOCollection $imagesDTOCollection,
        DTOCollection $videosDTOCollection,
        array $actionData
    ): void {
        // Loop on new form images collection to create images and merge corresponding medias with trick to update
        $imagesDTOCollectionToRemove = $this->addOrUpdateImagesDTOCollection(
            $trickToUpdate,
            $imagesDTOCollection,
            $actionData
        );
        // Loop on new form videos collection to create videos and merge corresponding medias with trick to update
        $videosDTOCollectionToRemove = $this->addOrUpdateVideosDTOCollection(
            $trickToUpdate,
            $videosDTOCollection,
            $actionData
        );
        // if "allow_delete"is used in collection form type configuration and set to "true", they are mandatory!
        // Remove images (and also physical files) not present in new images collection thanks to their unique names
        $isImagesDeletionConfigAllowed = $this->form->get('images')->getConfig()->getOption('allow_delete');
        if ($isImagesDeletionConfigAllowed) {
            $this->removeExistingTrickImagesNotFoundInCollection($imagesDTOCollectionToRemove, $actionData);
        }
        // Remove videos not present in new videos collection thanks to their unique names
        $isVideosDeletionConfigAllowed = $this->form->get('videos')->getConfig()->getOption('allow_delete');
        if ($isVideosDeletionConfigAllowed) {
            $this->removeExistingTrickVideosNotFoundInCollection($videosDTOCollectionToRemove, $actionData);
        }
    }

    /**
     * Remove Image entities if they are not present in new images collection.
     *
     * Please corresponding image files are also deleted from server!
     *
     * @param DTOCollection $imagesDTOCollectionToRemove
     * @param array         $actionData
     *
     * @return void
     *
     * @throws \Exception
     */
    private function removeExistingTrickImagesNotFoundInCollection(DTOCollection $imagesDTOCollectionToRemove, array $actionData): void
    {
        /** @var ImageManager $imageService */
        $imageService = $actionData['imageService'];
        // Get initial trick media list before update process
        foreach ($this->initialTrickMediaList as $mediaEntity) {
            /** @var Image|null $imageEntity */
            $imageEntity = $mediaEntity->getMediaSource()->getImage();
            // Media must reference a Image entity!
            if (\is_null($imageEntity)) {
                continue;
            }
            // Define an initial state
            $isExistingImageMustBeDeleted = false;
            foreach ($imagesDTOCollectionToRemove as $imageToCropDTO) {
                // Take into account the 3 image versions with the same identifier but depending on different formats
                $imageEntityName = $imageEntity->getName();
                $imageDTOName = $imageToCropDTO->getSavedImageName();
                $imageEntityNameWithoutFormat = preg_replace('/(\d+x\d+)$/', '', $imageEntityName);
                $imageToCropDTOWithoutFormat = preg_replace('/(\d+x\d+)$/', '', $imageDTOName);
                // Image exists in images collection to remove, so it must be deleted!
                if ($imageToCropDTOWithoutFormat === $imageEntityNameWithoutFormat) {
                    $isExistingImageMustBeDeleted = true;
                    break;
                }
            }
            // Image must be removed and its physical file must be also deleted!
            if ($isExistingImageMustBeDeleted) {
                // Delete image file
                $imageService->deleteOneImageFile($imageEntity, ImageUploader::TRICK_IMAGE_DIRECTORY_KEY);
                // Remove Image, MediaSource and Media entities thanks to cascade
                $imageService->removeImage($imageEntity, false);
            }
        }
    }

    /**
     * Remove Video entities if they are not present in new videos collection.
     *
     * @param DTOCollection $videosDTOCollectionToRemove
     * @param array         $actionData
     *
     * @return void
     */
    private function removeExistingTrickVideosNotFoundInCollection(DTOCollection $videosDTOCollectionToRemove, array $actionData): void
    {
        /** @var VideoManager $videoService */
        $videoService = $actionData['videoService'];
        // Get initial trick media list before update process
        foreach ($this->initialTrickMediaList as $mediaEntity) {
            /** @var Video|null $videoEntity */
            $videoEntity = $mediaEntity->getMediaSource()->getVideo();
            // Media must reference a Video entity!
            if (\is_null($videoEntity)) {
                continue;
            }
            // Define an initial state to "false" not to remove entity by default
            $isExistingVideoMustBeDeleted = false;
            foreach ($videosDTOCollectionToRemove as $videoInfosDTO) {
                // Video still exists in new videos collection, so it must be preserved!
                if ($videoInfosDTO->getSavedVideoName() === $videoEntity->getName()) {
                    $isExistingVideoMustBeDeleted = true;
                    break;
                }
            }
            // Video must be removed and also its physical file!
            if ($isExistingVideoMustBeDeleted) {
                // Remove Video, MediaSource and Media entities thanks to cascade
                $videoService->removeVideo($videoEntity, false);
            }
        }
    }

    /**
     * Rename all trick images files and Image entities names, after Trick update success,
     * according to updated trick modified name (title).
     *
     * @param Trick $updatedTrick
     * @param array $actionData
     *
     * @return void
     *
     * @throws \Exception
     */
    private function renameAllUpdatedImagesWithNewTrickName(Trick $updatedTrick, array $actionData): void
    {
        /** @var ImageManager $imageService */
        $imageService = $actionData['imageService'];
        /** @var TrickManager $trickService */
        $trickService = $actionData['trickService'];
        // Loop on existing updated trick Media list
        foreach ($updatedTrick->getMediaOwner()->getMedias() as $mediaEntity) {
            /** @var Image|null $imageEntity */
            $imageEntity = $mediaEntity->getMediaSource()->getImage();
            // Media must reference a Image entity!
            if (\is_null($imageEntity)) {
                continue;
            }
            // Get necessary data to make changes
            $updatedTrickSlug = $this->makeSlug($updatedTrick->getName()); // At ths time slug is based on trick name.
            $currentImageName = $imageEntity->getName() . '.' . $imageEntity->getFormat();
            // Extract current (previous) image name
            preg_match('/^(.*)-[a-z0-9]*-\d+x\d+\.[a-z]{3,4}$/', $currentImageName, $matches, PREG_UNMATCHED_AS_NULL);
            // Replace previous trick slug name in group 1 by updated trick slug name
            $newImageName = preg_replace('/' . $matches[1] . '/', $updatedTrickSlug, $currentImageName);
            $imageService->renameImage(
                $currentImageName,
                $newImageName,
                ImageManager::TRICK_IMAGE_TYPE_KEY,
                false
            );
            // Update Image entity name without extension (format)
            $imageExtension = preg_quote('.' .$imageEntity->getFormat());
            $newImageNameWithoutExtension = preg_replace('/' . $imageExtension . '$/', '', $newImageName);
            $imageEntity->modifyName($newImageNameWithoutExtension);
        }
        // Save all images name changes in database
        $trickService->addAndSaveTrick($updatedTrick, false, true);
    }

    /**
     * Start trick update process by retrieving Trick entity to update
     * and refresh simple data if necessary (group, title, description, slug, and publication state for administrator).
     *
     * Please note this first update do not take into account images and videos collections changes.
     * Collections are managed later in this trick update process!
     *
     * @param array $actionData
     *
     * @return Trick
     *
     * @throws \Exception
     */
    private function startTrickUpdateProcess(array $actionData): Trick
    {
        /** @var TrickManager $trickService */
        $trickService = $actionData['trickService'];
        /** @var Trick $trickToUpdate */
        $trickToUpdate = $actionData['trickToUpdate'];
        /** @var User|UserInterface $authenticatedUser */
        $authenticatedUser = $this->security->getUser();
        // Get form data
        $updateTrickDTO = $this->form->getData();
        // Store previous trick media list before collections update
        $this->initialTrickMediaList = $trickToUpdate->getMediaOwner()->getMedias();
        // Store previous trick name
        $this->initialTrickName = $trickToUpdate->getName();
        // Get trick to update with simple modified data without collections changes (without flush for roll-back)
        $trickToUpdate = $trickService->updateTrick($updateTrickDTO, $trickToUpdate, $authenticatedUser, false);
        // Nothing to process here!
        return $trickToUpdate;
    }

    /**
     * End trick update process by trying to save all data and showing state notification message.
     *
     * @param Trick $trick
     * @param array $actionData
     *
     * @return void
     *
     * @throws \Exception
     *
     * @see https://paragonie.com/blog/2015/06/preventing-xss-vulnerabilities-in-php-everything-you-need-know
     */
    private function terminateTrickUpdateProcess(Trick $trick, array $actionData): void
    {
        /** @var TrickManager $trickService */
        $trickService = $actionData['trickService'];
        // Save collections data when flushing Trick entity thanks to cascade operations
        $updatedTrick = $trickService->addAndSaveTrick($trick, false, true);
        // Create success notification message
        $state = 'success';
        $message = sprintf(
            'Trick called' . "\n" . '"%s"' . "\n" . 'was updated successfully!' . "\n" .
            'Please check trick detail below to look at content.',
            // Can also be escaped with htmlspecialchars()
            htmlentities($trick->getName(), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );
        // Create failure notification message
        if (\is_null($updatedTrick)) {
            // Delete all images physically created during process
            // and remove Trick new associated (image or video) medias entities during update
            $isProcessCorrectlyCanceled = $this->cancelTrickUpdateProcess(
                null,
                $trick,
                $actionData,
                false
            );
            $state = 'error';
            $message = 'Sorry, trick update failed' . "\n" .
                       'due a technical error!' . "\n" .
                       'Please try again with new data' . "\n" .
                       'or contact us if necessary.';
            if (!$isProcessCorrectlyCanceled) {
                $loggerMessage = sprintf(
                    "[trace app SnowTricks] UpdateTrickHandler/terminateTrickUpdateProcess =>" .
                    "Trick associated medias removal issue with \"%s\" which has uuid: %s",
                    $trick->getName(),
                    $trick->getUuid()->toString()
                );
                $this->logger->error($loggerMessage);
            }
        } else {
            // Rename updated images with new potential trick name (title) which is used for images naming.
            if ($this->initialTrickName !== $updatedTrick->getName()) {
                $this->renameAllUpdatedImagesWithNewTrickName($updatedTrick, $actionData);
            }
            // Store property to use updated trick data for redirection in controller (action)
            $this->updatedTrick = $updatedTrick;
        }
        $this->flashBag->add($state, $message);
    }

    /**
     * Update an existing Trick Image entity with corresponding DTO in images DTO collection.
     *
     * @param ImageToCropDTO $imageToCropDTO
     * @param int            $versionLength  a length to define how many versions of the same image exist
     *
     * @return bool
     *
     * @throws \Exception
     */
    private function updateTrickImageFromCollection(ImageToCropDTO $imageToCropDTO, int $versionLength = 3): bool
    {
        /** @var User|UserInterface $authenticatedUser */
        // $authenticatedUser = $this->security->getUser();
        // Define an update state
        $isImageNew = true;
        $count = 0;
        // Get initial trick media list before update process
        foreach ($this->initialTrickMediaList as $mediaEntity) {
            /** @var Image|null $imageEntity */
            $imageEntity = $mediaEntity->getMediaSource()->getImage();
            // Media must reference a Image entity!
            if (\is_null($imageEntity)) {
                continue;
            }
            // Image exists and must be updated: so take into account the 3 images versions
            // with the same identifier but depending on different formats
            $imageEntityName = $imageEntity->getName();
            $imageDTOName = $imageToCropDTO->getSavedImageName();
            $imageEntityNameWithoutFormat = preg_replace('/(\d+x\d+)$/', '', $imageEntityName);
            $imageToCropDTOWithoutFormat = preg_replace('/(\d+x\d+)$/', '', $imageDTOName);
            // Image exists and must be updated!
            if ($imageToCropDTOWithoutFormat === $imageEntityNameWithoutFormat) {
                $count++;
                $isImageNew = false;
                $now = new \DateTime('now');
                // Update corresponding Image entity (image name and physical file cannot be changed!)
                $imageEntity
                    ->modifyDescription($imageToCropDTO->getDescription())
                    ->modifyUpdateDate($now);
                // Update corresponding Media entity
                $mediaEntity
                    ->modifyIsMain($imageToCropDTO->getIsMain())
                    ->modifyShowListRank($imageToCropDTO->getShowListRank())
                    // ->modifyUser($authenticatedUser) // At this time user author is not changed after media creation.
                    // ->modifyIsPublished(true) // At this time a media is always published.
                    ->modifyUpdateDate($now);
            }
            // Stop Loop to optimize, since it is unnecessary to continue: image version length is reached!
            if ($versionLength === $count) {
                break;
            }
        }
        // Return this comparison result to know if checked image already exists!
        return false === $isImageNew;
    }

    /**
     * Update an existing Trick Video entity with corresponding DTO in videos DTO collection.
     *
     * @param VideoInfosDTO $videoInfosDTO
     *
     * @return bool
     *
     * @throws \Exception
     */
    private function updateTrickVideoFromCollection(VideoInfosDTO $videoInfosDTO): bool
    {
        /** @var User|UserInterface $authenticatedUser */
        // $authenticatedUser = $this->security->getUser();
        // Define an update state
        $isVideoNew = true;
        // Get initial trick media list before update process
        foreach ($this->initialTrickMediaList as $mediaEntity) {
            /** @var Video|null $videoEntity */
            $videoEntity = $mediaEntity->getMediaSource()->getVideo();
            // Media must reference a Video entity!
            if (\is_null($videoEntity)) {
                continue;
            }
            // Video exists and must be updated!
            if ($videoInfosDTO->getSavedVideoName() === $videoEntity->getName()) {
                $isVideoNew = false;
                $now = new \DateTime('now');
                // Update corresponding Video entity (video name can be changed due to URL update!)
                $videoEntity
                    ->modifyName($videoInfosDTO->getSavedVideoName())
                    ->modifyUrl($videoInfosDTO->getUrl())
                    ->modifyDescription($videoInfosDTO->getDescription())
                    ->modifyUpdateDate($now);
                // Update corresponding Media entity
                $mediaEntity
                    ->modifyIsMain(false) // At this time there is no main video.
                    ->modifyShowListRank($videoInfosDTO->getShowListRank())
                    // ->modifyUser($authenticatedUser) // At this time user author is not changed after media creation.
                    // ->modifyIsPublished(true) // At this time a media is always published.
                    ->modifyUpdateDate($now);
                break;
            }
        }
        // Return this comparison result to know if checked video already exists!
        return false === $isVideoNew;
    }
}
