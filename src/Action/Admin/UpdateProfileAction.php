<?php

declare(strict_types = 1);

namespace App\Action\Admin;

use App\Domain\Entity\User;
use App\Domain\ServiceLayer\ImageManager;
use App\Domain\ServiceLayer\UserManager;
use App\Form\Handler\FormHandlerInterface;
use App\Responder\Admin\UpdateProfileResponder;
use App\Responder\Redirection\RedirectionResponder;
use Symfony\Component\HttpFoundation\File\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class UpdateProfileAction.
 *
 * Manage user registration form.
 */
class UpdateProfileAction
{
    /**
     * @var ImageManager $imageService
     */
    private $imageService;

    /**
     * @var UserManager $userService
     */
    private $userService;

    /**
     * @var FlashBagInterface
     */
    private $flashBag;

    /**
     * @var FormHandlerInterface
     */
    private $formHandler;

    /**
     * @var Security
     */
    private $security;

    /**
     * RenewPasswordAction constructor.
     *
     * @param ImageManager         $imageService
     * @param UserManager          $userService
     * @param FlashBagInterface    $flashBag
     * @param FormHandlerInterface $formHandler
     * @param Security             $security
     */
    public function __construct(
        ImageManager $imageService,
        UserManager $userService,
        FlashBagInterface $flashBag,
        FormHandlerInterface $formHandler,
        Security $security
    ) {
        $this->imageService = $imageService;
        $this->userService = $userService;
        $this->flashBag = $flashBag;
        $this->formHandler = $formHandler;
        $this->security = $security;
    }

    /**
     *  Show profile update form (user account) and validation errors.
     *
     * @Route("/{_locale}/{mainRoleLabel}/update-profile", name="update_profile", requirements={"mainRoleLabel":"admin|member"})
     *
     * @param RedirectionResponder   $redirectionResponder
     * @param UpdateProfileResponder $responder
     * @param Request                $request
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function __invoke(RedirectionResponder $redirectionResponder, UpdateProfileResponder $responder, Request $request) : Response
    {
        // Get user from symfony security context: access is controlled by ACL.
        /** @var UserInterface|User $identifiedUser */
        $identifiedUser = $this->security->getUser();
        // Set form without initial model data and set the request by binding it
        $updateProfileForm = $this->formHandler->initForm(['userToUpdate' => $identifiedUser])->bindRequest($request);
        // Process only on submit
        if ($updateProfileForm->isSubmitted()) {
            // Constraints and custom validation: call actions to perform if necessary on success
            $isFormRequestValid = $this->formHandler->processFormRequest([
                'imageService' => $this->imageService,
                'userService'  => $this->userService,
                'userToUpdate' => $identifiedUser
            ]);
            if ($isFormRequestValid) {
                return $redirectionResponder('home');
            }
        }
        $data = [
            'uniqueUserError'   => $this->formHandler->getUniqueUserError() ?? null,
            'userAvatarImage'   => $this->imageService->getUserAvatarImage($identifiedUser),
            'updateProfileForm' => $updateProfileForm->createView()
        ];
        return $responder($data);
    }
}
