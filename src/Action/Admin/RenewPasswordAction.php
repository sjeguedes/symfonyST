<?php

declare(strict_types = 1);

namespace App\Action\Admin;

use App\Domain\ServiceLayer\UserManager;
use App\Form\Handler\FormHandlerInterface;
use App\Responder\Admin\RenewPasswordResponder;
use App\Responder\Redirection\RedirectionResponder;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class RenewPasswordAction.
 *
 * Manage password renewal form.
 */
class RenewPasswordAction
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
     *
     */
    public function __construct(UserManager $userService, FlashBagInterface $flashBag, FormHandlerInterface $formHandler) {
        $this->userService = $userService;
        $this->flashBag = $flashBag;
        $this->formHandler = $formHandler;
    }

    /**
     * Show password renewal form and validation.
     *
     * @Route({
     *     "en": "/{_locale<en>}/renew-password/{userId}/{renewalToken}"
     * }, name="renew_password_with_personal_link")
     *
     * @param RedirectionResponder   $redirectionResponder
     * @param RenewPasswordResponder $responder
     * @param Request                $request
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function __invoke(RedirectionResponder $redirectionResponder, RenewPasswordResponder $responder, Request $request) : Response
    {
        // Try to identify user in personal link
        $identifiedUser = $this->userService->getUserFoundInPasswordRenewalRequest();
        // User can not be retrieved or
        // personal requested token does not match user token or renewal request date (forgotten password process) is outdated.
        if (\is_null($identifiedUser) || !$this->userService->isPasswordRenewalRequestTokenAllowed($identifiedUser)) {
            $this->flashBag->add(
                'danger',
                nl2br('You are not allowed to access' . "\n" .
                    'password renewal process!' . "\n" .
                    'Please ask for a new request.'
                )
            );
            // Redirect to new password request page
            return $redirectionResponder('request_new_password');
        }
        // Set form with initial username model data and set the request by binding it
        $renewPasswordForm = $this->formHandler->initForm(['userToUpdate' => $identifiedUser])->bindRequest($request);
        // Process only on submit
        if ($renewPasswordForm->isSubmitted()) {
            // Constraints and custom validation: call actions to perform if necessary on success
            $isFormRequestValid = $this->formHandler->processFormRequest(['userService' => $this->userService, 'userToUpdate' => $identifiedUser]);
            if ($isFormRequestValid) {
                // Redirect to login page
                return $redirectionResponder('connect');
            }
        }
        $data = [
            'userNameError'     => $this->formHandler->getUserNameError() ?? null,
            'renewPasswordForm' => $renewPasswordForm->createView()
        ];
        return $responder($data);
    }
}
