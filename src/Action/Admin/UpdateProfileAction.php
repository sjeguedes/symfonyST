<?php

declare(strict_types = 1);

namespace App\Action\Admin;

use App\Domain\ServiceLayer\UserManager;
use App\Form\Handler\FormHandlerInterface;
use App\Responder\Admin\UpdateProfileResponder;
use App\Responder\Redirection\RedirectionResponder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class UpdateProfileAction.
 *
 * Manage user registration form.
 */
class UpdateProfileAction
{
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
     * RenewPasswordAction constructor.
     *
     * @param UserManager          $userService
     * @param FlashBagInterface    $flashBag
     * @param FormHandlerInterface $formHandler
     */
    public function __construct(UserManager $userService, FlashBagInterface $flashBag, FormHandlerInterface $formHandler) {
        $this->userService = $userService;
        $this->flashBag = $flashBag;
        $this->formHandler = $formHandler;
    }

    /**
     *  Show profile update form (user account) and validation errors.
     *
     * @Route("/{_locale}/update-profile/{userId}", name="update_profile")
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
        $userId = $request->attributes->get('userId'); // TODO: create a common method between handlers to check user in UserManager?
        $identifiedUser = $this->userService->findSingleByEncodedUuid($userId);
        if (\is_null($identifiedUser)) {
            throw new NotFoundHttpException();
        }
        // Set form without initial model data and set the request by binding it
        $updateProfileForm = $this->formHandler->initForm(['userToUpdate' => $identifiedUser])->bindRequest($request);
        // Process only on submit
        if ($updateProfileForm->isSubmitted()) {
            // Constraints and custom validation: call actions to perform if necessary on success
            $isFormRequestValid = $this->formHandler->processFormRequest(['userService' => $this->userService, 'userToUpdate' => $identifiedUser]);
            if ($isFormRequestValid) {
                return $redirectionResponder('home');
            }
        }
        $data = [
            'uniqueUserError'  => $this->formHandler->getUniqueUserError() ?? null,
            'updateProfileForm' => $updateProfileForm->createView()
        ];
        return $responder($data);
    }

    /**
     * Update user avatar using ajax.
     *
     * @Route("/{_locale}/update-avatar/{userId}", name="update_avatar")
     *
     * @param RedirectionResponder $redirectionResponder
     * @param Request              $request
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function EditUserAvatar(RedirectionResponder $redirectionResponder, Request $request) : Response
    {
        $userId = $request->attributes->get('userId');
        $isUpdated = $this->userService->activateAccount($userId);
        if (!$isUpdated) {
            $this->flashBag->add('danger','Sorry, an error happened!<br>Try again or use another file to update your avatar.');
        } else {
            $this->flashBag->add('success','Good job!<br>Your avatar is successfully updated.');
        }
        return $redirectionResponder('update_profile', ['userId' => $userId]);
    }
}
