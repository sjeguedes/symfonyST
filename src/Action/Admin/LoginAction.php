<?php

declare(strict_types = 1);

namespace App\Action\Admin;

use App\Form\Type\Admin\LoginType;
use App\Responder\Admin\LoginResponder;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Security;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryInterface;
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
     * @var FormFactoryInterface
     */
    private $formFactory;

    /**
     * @var Security
     */
    private $security;

    /**
     * LoginAction constructor.
     *
     * @param AuthenticationUtils  $authenticationUtils
     * @param FlashBagInterface    $flashBag
     * @param FormFactoryInterface $formFactory
     * @param LoggerInterface      $logger
     * @param Security             $security
     *
     * @return void
     */
    public function __construct(
        AuthenticationUtils $authenticationUtils,
        FlashBagInterface $flashBag,
        FormFactoryInterface $formFactory,
        LoggerInterface $logger,
        Security $security
    ) {
        $this->authenticationUtils = $authenticationUtils;
        $this->flashBag = $flashBag;
        $this->formFactory = $formFactory;
        $this->setLogger($logger);
        $this->security = $security;
    }

    /**
     *  Show login form (user connection) and validation or authentication errors.
     *
     * @Route("/{_locale}/login", name="connection")
     *
     * @param LoginResponder $responder
     * @param Request        $request
     *
     * @return Response
     */
    public function __invoke(LoginResponder $responder, Request $request) : Response
    {
        // Deny access if a user is already authenticated.
        if (!\is_null($this->security->getUser())) {
            throw new AccessDeniedException('User is already authenticated.');
        }
        // Get login form with a form factory and handle request
        $loginFormType = $this->formFactory->create(LoginType::class)->handleRequest($request);
        // Get the last authentication error
        $authenticationError = $this->authenticationUtils->getLastAuthenticationError();
        if ($loginFormType->isSubmitted() && !\is_null($authenticationError)) {
            // DTO is in valid state but with authentication error.
            if ($loginFormType->isValid()) {
                $this->flashBag->add('danger', 'Authentication failure!<br>Try to login again by checking the fields.');
            // Validation failed.
            } else {
                $this->flashBag->add('danger', 'Form validation failure!<br>Try to login again by checking the fields.');
            }
        }
        $data = [
            'lastAuthenticationError' => $authenticationError,
            'loginForm'               => $loginFormType->createView()
        ];
        return $responder($data);
    }
}