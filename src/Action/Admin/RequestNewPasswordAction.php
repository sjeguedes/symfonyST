<?php

declare(strict_types = 1);

namespace App\Action\Admin;

use App\Domain\Service\UserManager;
use App\Form\Handler\FormHandlerInterface;
use App\Form\Type\Admin\RequestNewPasswordType;
use App\Responder\Admin\RequestNewPasswordResponder;
use App\Responder\Redirection\RedirectionResponder;
use App\Service\Mailer\SwiftMailerManager;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Form\FormFactoryInterface;
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
     * @var FormFactoryInterface
     */
    private $formFactory;

    /**
     * @var FormHandlerInterface
     */
    private $formHandler;

    /**
     * @var SwiftMailerManager
     */
    private $mailer;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * RequestNewPasswordAction constructor.
     *
     * @param UserManager          $userService
     * @param FlashBagInterface    $flashBag
     * @param FormFactoryInterface $formFactory
     * @param FormHandlerInterface $formHandler
     * @param SwiftMailerManager   $mailer
     * @param ContainerInterface   $container
     * @param LoggerInterface      $logger
     */
    public function __construct(
        UserManager $userService,
        FlashBagInterface $flashBag,
        FormFactoryInterface $formFactory,
        FormHandlerInterface $formHandler,
        SwiftMailerManager $mailer,
        ContainerInterface $container,
        LoggerInterface $logger
    ) {
        $this->userService = $userService;
        $this->flashBag = $flashBag;
        $this->formFactory = $formFactory;
        $this->formHandler = $formHandler;
        $this->mailer = $mailer;
        $this->container = $container;
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
        // Get password renewal request form with a form factory and handle request
        $requestNewPasswordFormType = $this->formFactory->create(requestNewPasswordType::class)->handleRequest($request);
        if ($request->isMethod('POST')) {
            $csrfToken = $request->request->get('request_new_password')['token'];
            // CSRF token is not valid.
            if (false === $this->formHandler->isCSRFTokenValid('request_new_password_token', $csrfToken)) {
                throw new \Exception('Security error: CSRF form token is invalid!');
            }
        }
        if ($requestNewPasswordFormType->isSubmitted()) {
            if (!$requestNewPasswordFormType->isValid()) {
                $this->flashBag->add('danger', 'Form validation failed!<br>Try to request again by checking the fields.');
            } else {
                $user = $this->userService->getRepository()->loadUserByUsername($requestNewPasswordFormType->getData()->getUserName());
                // No user found.
                if (!$user) {
                    $this->flashBag->add('danger', 'Form authentication failed!<br>Try to request again by checking the fields.');
                    $error = 'Please check your credentials!<br>User was not found.';
                } else {
                    // Save data
                    $user->updateRenewalRequestDate(new \Datetime('now'));
                    $user->updateRenewalToken($this->userService->generateCustomToken($user->getNickName()));
                    $this->userService->getEntityManager()->flush();
                    // Send email
                    $sender = [$this->container->getParameter('app.swiftmailer.website.email') => 'SnowTricks - Member service'];
                    $receiver = [$user->getEmail() => $user->getFirstName() . ' ' . $user->getFamilyName()];
                    $emailHtmlBody = $this->mailer->createEmailBody(self::class, ['_locale' => $request->get('_locale'), 'user' => $user]);
                    $isEmailSent = $this->mailer->sendEmail($sender, $receiver, 'Password renewal request', $emailHtmlBody);
                    // Technical error when trying to send
                    if (!$isEmailSent) {
                        $this->logger->error("[trace app snowTricks] RequestNewPasswordAction/__invoke => email not sent: " . $this->mailer->getLoggerPlugin()->dump());
                        throw new \Exception('Request failed: email was not sent due to technical error or wrong parameters!');
                    }
                    $this->flashBag->add('success', 'An email was sent successfully!<br>Please check your box and<br>use your personalized link<br>to renew your password.');
                    return $redirectionResponder('request_new_password');
                }
            }
        }
        $data = [
            'userError'              => $error ?? null,
            'requestNewPasswordForm' => $requestNewPasswordFormType->createView()
        ];
        return $responder($data);
    }
}
