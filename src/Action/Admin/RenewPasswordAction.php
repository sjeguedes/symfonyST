<?php

declare(strict_types = 1);

namespace App\Action\Admin;

use App\Domain\Service\UserManager;
use App\Form\Handler\FormHandlerInterface;
use App\Responder\Admin\RenewPasswordResponder;
use App\Responder\Redirection\RedirectionResponder;
use App\Service\Mailer\SwiftMailerManager;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\Form;
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
    use LoggerAwareTrait;

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
     * @param LoggerInterface      $logger
     *
     */
    public function __construct(
        UserManager $userService,
        FlashBagInterface $flashBag,
        FormHandlerInterface $formHandler,
        LoggerInterface $logger
    ) {
        $this->userService = $userService;
        $this->flashBag = $flashBag;
        $this->formHandler = $formHandler;
        $this->setLogger($logger);
    }

    /**
     * Show password renewal form and validation.
     *
     * @Route("/{_locale}/renew-password", name="renew_password")
     * @Route("/{_locale}/renew-password/{userId}/{renewalToken}", name="renew_password_with_personal_link")
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
        $user = $this->userService->getUserFoundInPasswordRenewalRequest($request);
        // User can not be retrieved.
        // Personal requested token does not match user token or renewal request date (forgotten password process) is outdated.
        if (\is_null($user) || false === $this->userService->isPasswordRenewalRequestTokenAllowed($user, $request)) {
            $this->flashBag->add('danger', 'You are not allowed to access<br>password renewal process!<br>Please ask for a new request.');
            return $redirectionResponder('request_new_password');
        }
        /** @var Form $renewPasswordForm */
        $renewPasswordForm = $this->formHandler->bindRequest($request);
        // Process only on submit
        if ($renewPasswordForm->isSubmitted()) {
            // Constraints validation
            $isFormRequestValid = $this->formHandler->processFormRequestOnSubmit($request);
            if (!$isFormRequestValid) {
                $this->flashBag->add('danger', 'Form validation failed!<br>Try to request again by checking the fields.');
            } else {
                // Error: userName filled in form does not match user properties from request parameter.
                if ($user->getNickName() !== $renewPasswordForm->get('userName')->getData() && $user->getEmail() !== $renewPasswordForm->get('userName')->getData()) {
                    $this->flashBag->add('danger', 'Form authentication failed!<br>Try to request again by checking the fields.');
                    $userNameError = 'Please check your credentials!<br>Your username is not allowed!';
                } else {
                    // Save data
                    $this->userService->renewPassword($user, $renewPasswordForm->get('passwords')->getData());
                    // Send email
                    $isEmailSent = $this->formHandler->executeFormRequestActionOnSuccess(['userToUpdate' => $user], $request);
                    // Technical error when trying to send
                    if (!$isEmailSent) {
                        /** @var SwiftMailerManager $mailer */
                        $mailer = $this->formHandler->getMailer();
                        $this->logger->error("[trace app snowTricks] RenewPasswordAction/__invoke => email not sent: " . $mailer->getLoggerPlugin()->dump());
                        throw new \Exception('Request failed: email was not sent due to technical error or wrong parameters!');
                    }
                    $this->flashBag->add('success', 'An email was sent successfully!<br>Please check your box<br>to look at your password renewal confirmation.');
                    return $redirectionResponder('connection');
                }
            }
        }
        $data = [
            'userNameError'     => $userNameError ?? null,
            'renewPasswordForm' => $renewPasswordForm->createView()
        ];
        return $responder($data);
    }
}
