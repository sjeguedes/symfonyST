<?php

declare(strict_types = 1);

namespace App\Action\Admin;

use App\Form\Handler\FormHandlerInterface;
use App\Responder\Admin\LoginResponder;
use App\Responder\Redirection\RedirectionResponder;
use Symfony\Component\Form\Form;
use Symfony\Component\Security\Core\Security;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Class LoginAction.
 *
 * Manage login form authentication.
 */
class LoginAction
{
    use LoggerAwareTrait;

    /**
     * @var AuthenticationUtils
     */
    private $authenticationUtils;

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
     * LoginAction constructor.
     *
     * @param AuthenticationUtils  $authenticationUtils
     * @param FlashBagInterface    $flashBag
     * @param FormHandlerInterface $formHandler
     * @param LoggerInterface      $logger
     * @param Security             $security
     *
     * @return void
     */
    public function __construct(
        AuthenticationUtils $authenticationUtils,
        FlashBagInterface $flashBag,
        FormHandlerInterface $formHandler,
        LoggerInterface $logger,
        Security $security
    ) {
        $this->authenticationUtils = $authenticationUtils;
        $this->flashBag = $flashBag;
        $this->formHandler = $formHandler;
        $this->setLogger($logger);
        $this->security = $security;
    }

    /**
     *  Show login form (user connection) and validation or authentication errors.
     *
     * @Route("/{_locale}/login", name="connection")
     *
     * @param RedirectionResponder $redirectionResponder
     * @param LoginResponder $responder
     * @param Request $request
     *
     * @return Response
     *
     * @throws \Exception
     */
    public function __invoke(RedirectionResponder $redirectionResponder, LoginResponder $responder, Request $request) : Response
    {
        // Deny access if a user is already authenticated.
        if (!\is_null($this->security->getUser())) {
            $this->flashBag->add('danger', 'User is already authenticated!<br>Please logout first.');
            return $redirectionResponder('home');
        }
        /** @var Form $loginForm */
        $loginForm = $this->formHandler->bindRequest($request);
        // Get the last authentication error
        $authenticationError = $this->authenticationUtils->getLastAuthenticationError();
        // Process only on submit
        if ($loginForm->isSubmitted()) {
            // Constraints validation
            $isFormRequestValid = $this->formHandler->processFormRequestOnSubmit($request);
            if ($isFormRequestValid) {
                // DTO is in valid state but with authentication error.
               if (!\is_null($authenticationError)) {
                   $this->flashBag->add('danger', 'Authentication failed!<br>Try to login again by checking the fields.');
               }
            } else {
                // Validation failed.
                $this->flashBag->add('danger', 'Form validation failed!<br>Try to login again by checking the fields.');
            }
        }
        $data = [
            'lastAuthenticationError' => $authenticationError ?? null,
            'loginForm'               => $loginForm->createView()
        ];
        return $responder($data);
    }
}
