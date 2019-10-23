<?php

declare(strict_types = 1);

namespace App\Action\Admin;

use App\Domain\Service\UserManager;
use App\Form\Handler\FormHandlerInterface;
use App\Responder\Admin\RequestNewPasswordResponder;
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
 * Class RequestNewPasswordAction.
 *
 * Manage password renewal request form to receive a token by email.
 * User has forgotten his personal password.
 */
class RequestNewPasswordAction
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
     * @var SwiftMailerManager
     */
    private $mailer;

    /**
     * RequestNewPasswordAction constructor.
     *
     * @param UserManager          $userService
     * @param FlashBagInterface    $flashBag
     * @param FormHandlerInterface $formHandler
     * @param SwiftMailerManager   $mailer
     * @param LoggerInterface      $logger
     */
    public function __construct(
        UserManager $userService,
        FlashBagInterface $flashBag,
        FormHandlerInterface $formHandler,
        SwiftMailerManager $mailer,
        LoggerInterface $logger
    ) {
        $this->userService = $userService;
        $this->flashBag = $flashBag;
        $this->formHandler = $formHandler;
        $this->mailer = $mailer;
        $this->setLogger($logger);
    }

    /**
     * Show password renewal request form and validation.
     *
     * @Route("/{_locale}/request-new-password", name="request_new_password")
     *
     * @param RedirectionResponder        $redirectionResponder
     * @param RequestNewPasswordResponder $responder
     * @param Request                     $request
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function __invoke(RedirectionResponder $redirectionResponder, RequestNewPasswordResponder $responder, Request $request) : Response
    {
        /** @var Form $requestNewPasswordForm */
        $requestNewPasswordForm = $this->formHandler->bindRequest($request);
        // Process only on submit
        if ($requestNewPasswordForm->isSubmitted()) {
            // Constraints validation
            $isFormRequestValid = $this->formHandler->processFormRequestOnSubmit($request);
            if (!$isFormRequestValid) {
                $this->flashBag->add('danger', 'Form validation failed!<br>Try to request again by checking the fields.');
            } else {
                $user = $this->userService->getRepository()->loadUserByUsername($requestNewPasswordForm->getData()->getUserName());
                // Error: no user is found.
                if (!$user) {
                    $this->flashBag->add('danger', 'Form authentication failed!<br>Try to request again by checking the fields.');
                    $userError = 'Please check your credentials!<br>User can not be found.';
                } else {
                    // Save data
                    $this->userService->generatePasswordRenewalToken($user);
                    // Send email
                    $isEmailSent = $this->formHandler->executeFormRequestActionOnSuccess(['userToUpdate' => $user], $request);
                    // Technical error when trying to send
                    if (!$isEmailSent) {
                        /** @var SwiftMailerManager $mailer */
                        $mailer = $this->formHandler->getMailer();
                        $this->logger->error("[trace app snowTricks] RequestNewPasswordAction/__invoke => email not sent: " . $mailer->getLoggerPlugin()->dump());
                        throw new \Exception('Request failed: email was not sent due to technical error or wrong parameters!');
                    }
                    $this->flashBag->add('success', 'An email was sent successfully!<br>Please check your box and<br>use your personalized link<br>to renew your password.');
                    return $redirectionResponder('request_new_password');
                }
            }
        }
        $data = [
            'userError'              => $userError ?? null,
            'requestNewPasswordForm' => $requestNewPasswordForm->createView()
        ];
        return $responder($data);
    }
}
