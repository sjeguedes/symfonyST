<?php

declare(strict_types = 1);

namespace App\Form\Handler;

use App\Domain\DTOToEmbed\ImageToCropDTO;
use App\Domain\Entity\User;
use App\Domain\ServiceLayer\ImageManager;
use App\Domain\ServiceLayer\TrickManager;
use App\Form\Type\Admin\CreateTrickType;
use App\Utils\Traits\CSRFTokenHelperTrait;
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
     * @param CsrfTokenManagerInterface   $csrfTokenManager
     * @param FlashBagInterface           $flashBag
     * @param FormFactoryInterface        $formFactory
     * @param RequestStack                $requestStack
     * @param Security                    $security
     */
    public function __construct(
        csrfTokenManagerInterface $csrfTokenManager,
        FlashBagInterface $flashBag,
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        Security $security
    ) {
        parent::__construct($flashBag, $formFactory, CreateTrickType::class, $requestStack);
        $this->csrfTokenManager = $csrfTokenManager;
        $this->customError = null;
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
            $trickNameError = 'Please check chosen title!<br>A trick with the same name already exists.';
            $this->customError = $trickNameError;
            $this->flashBag->add('danger', 'Trick creation failed!<br>Try to request again by checking the form fields.');
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
        // Check UserManager instance in passed data
        $this->checkNecessaryData($actionData);
        /** @var ImageManager $imageService */
        $imageService = $actionData['imageService'];
        /** @var TrickManager $trickService */
        $trickService = $actionData['trickService'];
        // Create a new trick in database with the validated DTO(s)

        // Loop on existing form images collection
        /** @var User|UserInterface $authenticatedUser */
        $authenticatedUser = $this->security->getUser();
        $imagesDTOCollection = $this->form->getData()->getImages();
        foreach ($imagesDTOCollection as $imageToCropDTO) {
            // 1. Retrieve big image Image and Media entities thanks to saved image name with loop
            // Get big image entity which was already uploaded on server during form validation thanks to corresponding DTO with its "savedImageName" property.
            /** @var ImageToCropDTO $imageToCropDTO */
            $bigImageEntity = $imageService->findSingleByName($imageToCropDTO->getSavedImageName());
            // Update big image Image entity (image name corresponds to "saveImageName" ImageToCropDTO property) which was already uploaded without form validation
            // TODO: update image name by making a slug with Trick name (method to create with Regex!)
            $bigImageEntity->modifyDescription($imageToCropDTO->getDescription());
            $bigImageEntity->modifyUpdateDate(new \DateTime('now'));
            // TODO: get corresponding image Media entity and update it with $imageToCropDTO data (isMain and showListRank)

            // 2. Create physically small and medium images with corresponding Image and Media entities
            // Here is made image physical creation, Image and Media entities generation with form data with createTrickImage(...)!
            $normalImageEntity = $imageService->createTrickImage($imageToCropDTO, 'trickNormal', $authenticatedUser, false);
            $thumbImageEntity = $imageService->createTrickImage($imageToCropDTO, 'trickThumbnail', $authenticatedUser, false);
            // TODO: update only image name by making a slug with Trick name (method to create with Regex!)
        }

        // Loop on existing form videos collection
        $videosDTOCollection = $this->form->getData()->getVideos();
        foreach ($videosDTOCollection as $videoInfosDTO) {
            // 3. Create videos with corresponding Video and Media entities
            // TODO: do stuff for video creation (add VideoManager method)!
            //$videoEntity = $videoService->createTrickVideo(...);
        }

        // 4. Create Trick entity by merging root form data ($this->form->getData()), authenticated user, images et videos entities
        // TODO: do stuff for trick creation (add TrickManager method)!
        //$newTrick = $trickService->createTrick(...);

        // 5. Creation success/failure notification
        // TODO: do stuff for trick creation success/failure flashbag!
        /*if ($isTrickCreated) {
            $this->flashBag->add(
                'success',
                'The trick was created successfully!<br>Please check trick list on website to look at content.');
        } else {...}*/
        // 6. TODO: do stuff for this form handler methods refactoring!
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
}
