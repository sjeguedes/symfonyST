<?php

declare(strict_types = 1);

namespace App\Action\Admin;

use App\Domain\Service\UserManager;
use App\Form\Handler\FormHandlerInterface;
use App\Form\Type\Admin\RenewPasswordType;
use App\Responder\Admin\RenewPasswordResponder;
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
     * RenewPasswordAction constructor.
     *
     * @param UserManager                  $userService
     * @param FlashBagInterface            $flashBag
     * @param FormFactoryInterface         $formFactory
     * @param FormHandlerInterface         $formHandler
     * @param SwiftMailerManager           $mailer
     * @param ContainerInterface           $container
     * @param LoggerInterface              $logger
     *
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
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function __invoke(RedirectionResponder $redirectionResponder, RenewPasswordResponder $responder, Request $request) : Response
    {
        $user = $this->userService->getUserFoundInPasswordRenewalRequest($request);
        //dd(!$this->userService->isPasswordRenewalRequestTokenAllowed($user, $request));
        // User can not be retrieved.
        // Personal requested token does not match user token or  or renewal request date (forgotten password process) is outdated.
        if (\is_null($user) || false === $this->userService->isPasswordRenewalRequestTokenAllowed($user, $request)) {
            $this->flashBag->add('danger', 'You are not allowed to access<br>password renewal process!<br>Please ask for a new request.');
            return $redirectionResponder('request_new_password');
        }
        // Get password renewal request form with a form factory and handle request
        $renewPasswordFormType = $this->formFactory->create(renewPasswordType::class)->handleRequest($request);
        if ($request->isMethod('POST')) {
            $csrfToken = $request->request->get('renew_password')['token'];
            // CSRF token is not valid.
            if (false === $this->formHandler->isCSRFTokenValid('renew_password_token', $csrfToken)) {
                throw new \Exception('Security error: CSRF form token is invalid!');
            }
        }
        if ($renewPasswordFormType->isSubmitted()) {
            if (!$renewPasswordFormType->isValid()) {
                $this->flashBag->add('danger', 'Form validation failed!<br>Try to request again by checking the fields.');
            } else {
                // Save data
                $this->userService->renewPassword($user, $renewPasswordFormType->get('passwords')->getData());
                // Send email
                $sender = [$this->container->getParameter('app.swiftmailer.website.email') => 'SnowTricks - Member service'];
                $receiver = [$user->getEmail() => $user->getFirstName() . ' ' . $user->getFamilyName()];
                $emailHtmlBody = $this->mailer->createEmailBody(self::class, ['_locale' => $request->get('_locale'), 'user' => $user]);
                $isEmailSent = $this->mailer->sendEmail($sender, $receiver, 'Password renewal confirmation', $emailHtmlBody);
                // Technical error when trying to send
                if (!$isEmailSent) {
                    $this->logger->error("[trace app snowTricks] RenewPasswordAction/__invoke => email not sent: " . $this->mailer->getLoggerPlugin()->dump());
                    throw new \Exception('Request failed: email was not sent due to technical error or wrong parameters!');
                }
                $this->flashBag->add('success', 'An email was sent successfully!<br>Please check your box<br>to look at your password renewal confirmation.');
                return $redirectionResponder('connection');
            }
        }
            $data = [
            'renewPasswordForm' => $renewPasswordFormType->createView()
        ];
        return $responder($data);
    }
}
