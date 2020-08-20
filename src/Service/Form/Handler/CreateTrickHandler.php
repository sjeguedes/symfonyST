<?php

declare(strict_types = 1);

namespace App\Service\Form\Handler;

use App\Domain\DTOToEmbed\ImageToCropDTO;
use App\Domain\DTOToEmbed\VideoInfosDTO;
use App\Domain\Entity\Trick;
use App\Domain\Entity\User;
use App\Domain\ServiceLayer\ImageManager;
use App\Domain\ServiceLayer\MediaManager;
use App\Domain\ServiceLayer\TrickManager;
use App\Service\Form\Collection\DTOCollection;
use App\Service\Form\Type\Admin\CreateTrickType;
use App\Service\Medias\Upload\ImageUploader;
use App\Utils\Traits\CSRFTokenHelperTrait;
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
final class CreateTrickHandler extends AbstractTrickFormHandler
{
    use CSRFTokenHelperTrait;

    /**
     * @var csrfTokenManagerInterface
     */
    private $csrfTokenManager;

    /*
     * @var Trick|null
     */
    private $newTrick;

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
        parent::__construct(
            $flashBag,
            $formFactory,
            CreateTrickType::class,
            $requestStack,
            $logger,
            $security
        );
        $this->csrfTokenManager = $csrfTokenManager;
        $this->customError = null;
        $this->newTrick = null;
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
        // DTO is in valid state but:
        // Each video URL must be unique (This avoids issue with Javascript!).
        if (!$isEachVideoURLUnique = $this->checkUniqueVideoUrl($this->form->getData()->getVideos())) {
            $uniqueVideoURLError = 'Please check all videos URL!' . "\n" . 'Each one must be unique!';
            $this->customError = $uniqueVideoURLError;
            $this->flashBag->add(
                'danger',
                'Trick creation failed!' . "\n" .
                         'Try to request again by checking the form fields.'
            );
            return false;
        }
        // DTO is in valid state but:
        // Filled in trick name (title) already exist in database: it must be unique!
        $submittedName = $this->form->getData()->getName(); // or $this->form->get('name')->getData()
        // Is submitted trick name (or similar name) not used by existing ones?
        if ($isSubmittedNameNotUnique = $trickService->checkSameOrSimilarTrickName($submittedName)) {
            $trickNameError = 'Please check chosen title!' . "\n" .
                              'Another trick with the same name' . "\n" .
                              '(or similar name) already exists.';
            $this->customError = $trickNameError;
            $this->flashBag->add(
                'danger',
                'Trick creation failed!' . "\n" .
                'Try to request again by checking the form fields.'
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
        // Add collections items corresponding Media (images or videos) entities to Trick entity
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
     * @throws \Exception
     */
    private function addTrickMediasFromCollections(
        Trick $newTrick,
        DTOCollection $imagesDTOCollection,
        DTOCollection $videosDTOCollection,
        array $actionData
    ) : void
    {
        /** @var ImageManager $imageService */
        $imageService = $actionData['imageService'];
        // Loop on existing form images collection to create images and add corresponding medias to new trick
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
        // Remove any empty temporary directory
        $imageService->removeEmptyTemporaryDirectory(ImageUploader::TRICK_IMAGE_DIRECTORY_KEY);
        // Loop on existing form videos collection to create videos and add corresponding medias to new trick
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
        // Control expected collection instance type
        $this->checkCollectionInstanceType($collectionItemDataModel);
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
        // Choice is made to create an exception!
        if ($isExceptionThrown) {
            // Throw an exception if at least one collection item failed to be created!
            $this->throwTrickProcessException(
                $collectionItemDataModel,
                AbstractTrickFormHandler::TRICK_CREATION_LABEL,
                $imageService,
                !$isTrickRemoved ? [$this, 'manageTrickRemovalError'] : null,
                !$isTrickRemoved ? [$newTrick] : null
            );
        }
        return $isProcessCorrectlyCanceled;
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
     * Manage trick removal error context as a callable public method.
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
    public function manageTrickRemovalError(Trick $newTrick, ?string $exceptionMessage) : string
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
     * Start trick creation process by generating a new Trick entity.
     *
     * Please note choice was made not to flush (only persist) entity here!
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
        $message = sprintf(
            'A new trick called' . "\n" . '"%s"' . "\n" . 'was created successfully!' . "\n" .
            'Please check trick detail below to look at content.',
            // Can also be escaped with htmlspecialchars()
            htmlentities($newTrick->getName(), ENT_QUOTES | ENT_HTML5, 'UTF-8')
        );
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
            $message = 'Sorry, trick creation failed' . "\n" .
                       'due a technical error!' . "\n" .
                       'Please try again with new data' . "\n" .
                       'or contact us if necessary.';
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
