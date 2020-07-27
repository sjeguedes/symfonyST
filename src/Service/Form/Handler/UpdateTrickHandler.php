<?php

declare(strict_types = 1);

namespace App\Service\Form\Handler;

use App\Domain\DTO\UpdateTrickDTO;
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
use App\Service\Form\Type\Admin\UpdateTrickType;
use App\Service\Medias\Upload\ImageUploader;
use App\Utils\Traits\CSRFTokenHelperTrait;
use App\Utils\Traits\StringHelperTrait;
use http\Exception\RuntimeException;
use Psr\Log\LoggerAwareTrait;
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
final class UpdateTrickHandler extends AbstractUploadFormHandler implements InitModelDataInterface
{
    use CSRFTokenHelperTrait;
    use LoggerAwareTrait;
    use StringHelperTrait;

    /**
     * @var csrfTokenManagerInterface
     */
    private $csrfTokenManager;

    /**
     * @var Security
     */
    private $security;

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
        parent::__construct($flashBag, $formFactory, UpdateTrickType::class, $requestStack);
        $this->csrfTokenManager = $csrfTokenManager;
        $this->customError = null;
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
        $csrfToken = $this->request->request->get('update_trick')['token'];
        // CSRF token is not valid.
        if (false === $this->isCSRFTokenValid('update_trick_token', $csrfToken)) {
            throw new \Exception('Security error: CSRF form token is invalid!');
        }
        // Check TrickManager instance in passed data
        $this->checkNecessaryData($actionData);
        /** @var TrickManager $trickService */
        $trickService = $actionData['trickService'];
        // DTO is in valid state but filled in trick name (title) already exist in database: it must be unique!
        $trickWithSameName = $trickService->findSingleByName($this->form->getData()->getName()); // or $this->form->get('name')->getData()
        $isTrickNameUnique = \is_null($trickWithSameName) ? true : false;
        if (!$isTrickNameUnique) {
            $trickNameError = nl2br('Please check chosen title!' . "\n" .
                'A trick with the same name already exists.'
            );
            $this->customError = $trickNameError;
            $this->flashBag->add(
                'danger',
                nl2br('Trick update failed!' . "\n" .
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
        // TODO: start trick update process here with other methods!
        // Start process by simply retrieving trick to update in data (This method is not necessary!)
        $trickToUpdate = $this->startTrickUpdateProcess($actionData);
        // Get data model collections
        $updateTrickDTO = $this->form->getData();
        $imagesDTOCollection = $updateTrickDTO->getImages();
        $videosDTOCollection = $updateTrickDTO->getVideos();
        // Add collections items corresponding Media entities to Trick entity
        $this->updateTrickMediasFromCollections($trickToUpdate, $imagesDTOCollection, $videosDTOCollection, $actionData);
    }

    /**
     * Get the trick update error.
     *
     * @return string|null
     */
    public function getTrickUpdateError() : ?string
    {
        return $this->customError;
    }

    /**
     * Initialize a set of images and videos DTO instances as array
     * to be used in collections.
     *
     * @param Trick  $trick
     * @param string $type
     *
     * @return array
     *
     * @throws \Exception
     */
    public function initTrickMediasDataBySourceType(Trick $trick, string $type) : array
    {
        if (!preg_match('/^image|video$/', $type)) {
            throw new RuntimeException('Media source type label to create collection is unknown!');
        }
        $trickMedias = $trick->getMediaOwner()->getMedias();
        $dtoArray = [];
        foreach ($trickMedias as $media) {
            if ($type === $media->getMediaType()->getSourceType()) {
                switch ($type) {
                    case 'image':
                        // Keep only big images to retrieve saved image name in form
                        if (MediaType::TYPE_CHOICES['trickBig'] !== $media->getMediaType()->getType()) {
                            continue;
                        }
                        $image = $media->getMediaSource()->getImage();
                        $dtoArray[] = new ImageToCropDTO(
                            null, // No uploaded file exists for image to crop initial data model!
                            $image->getDescription(),
                            // TODO: need to change JSON data structure in JS and PHP methods to avoid XSSI issue!
                            // TODO: need to change -> '[{}]' to simple '{}'
                            '[{}]', // An empty JSON is set (feed with JS only) to avoid validation issue.
                            null, // This is set to null (feed with JS only)
                            $image->getName(),
                            $media->getShowListRank(),
                            $media->getIsMain()
                        );
                        break;
                    case 'video':
                        $video = $media->getMediaSource()->getVideo();
                        $dtoArray[] = new VideoInfosDTO(
                            $video->getUrl(),
                            $video->getDescription(),
                            $video->getName(),
                            $media->getShowListRank()
                        );
                        break;
                }
            }
        }
        return $dtoArray;
    }

    /**
     * {@inheritDoc}
     *
     * @return object a UpdateTrickDTO instance
     *
     * @throws \Exception
     */
    public function initModelData(array $data) : object
    {
        // Check Trick instance in passed data
        $this->checkNecessaryData($data);
        /** @var Trick $trick */
        $trick = $data['trickToUpdate'];
        return new UpdateTrickDTO(
            $trick->getTrickGroup(),
            $trick->getName(),
            $trick->getDescription(),
            $this->initTrickMediasDataBySourceType($trick, 'image'),
            $this->initTrickMediasDataBySourceType($trick, 'video'),
            $trick->getIsPublished()
        );
    }

    /**
     * Start trick update process by retrieving Trick entity to update.
     *
     * Please note this method is not necessary but is convenient
     * to follow the same process as trick creation.
     *
     * @param array $actionData
     *
     * @return Trick
     *
     * @throws \Exception
     */
    private function startTrickUpdateProcess(array $actionData) : Trick
    {
        // Get Trick entity to update
        $trickToUpdate = $actionData['trickToUpdate'];
        // Nothing to process here!
        return $trickToUpdate;
    }

    /**
     * Update Media collections to Trick with loop.
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
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    private function updateTrickMediasFromCollections(
        Trick $trickToUpdate,
        DTOCollection $imagesDTOCollection,
        DTOCollection $videosDTOCollection,
        array $actionData
    ) : void
    {
        // TODO: continue trick update process here step by step!
        // Loop on existing form images collection to create images and merge corresponding medias with trick to update
        /** @var ImageToCropDTO $imageToCropDTO */
        /*foreach ($imagesDTOCollection as $imageToCropDTO) {
            // Create image with corresponding Image and Media entities
            $isImageAdded = $this->addTrickImageFromCollection($trickToUpdate, $imageToCropDTO, $actionData);
            if (!$isImageAdded) {
                // Call a "rollback" to cancel Trick update process.
                // CAUTION! Here a exception will be thrown!
                $this->cancelTrickUpdateProcess($imageToCropDTO, $trickToUpdate, $actionData);
                break;
            }
        }*/
        // Loop on existing form videos collection to create videos and merge corresponding medias with trick to update
        /** @var VideoInfosDTO $videoInfosDTO */
        /*foreach ($videosDTOCollection as $videoInfosDTO) {
            // Create video with corresponding Video and Media entities
            $isVideoAdded = $this->addTrickVideoFromCollection($trickToUpdate, $videoInfosDTO, $actionData);
            if (!$isVideoAdded) {
                // Call a "rollback" to cancel Trick update process.
                // CAUTION! Here a exception will be thrown!
                $this->cancelTrickUpdateProcess($videoInfosDTO, $trickToUpdate, $actionData);
                break;
            }
        }*/
    }
}
