<?php

declare(strict_types = 1);

namespace App\Form\Handler;

use App\Form\Type\Admin\LoginType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

/**
 * Class LoginHandler.
 *
 * Handle the form request when a user tries to login.
 * Call any additional validations and actions.
 */
final class LoginHandler extends AbstractFormHandler
{
    /**
     * @var AuthenticationUtils
     */
    private $authenticationUtils;

    /**
     * LoginHandler constructor.
     *
     * @param AuthenticationUtils  $authenticationUtils
     * @param FlashBagInterface    $flashBag
     * @param FormFactoryInterface $formFactory
     * @param RequestStack         $requestStack
     */
    public function __construct(
        AuthenticationUtils $authenticationUtils,
        FlashBagInterface $flashBag,
        FormFactoryInterface $formFactory,
        RequestStack $requestStack
    ) {
        parent::__construct($flashBag, $formFactory, LoginType::class, $requestStack);
        $this->authenticationUtils = $authenticationUtils;
        $this->customError = null;
    }

    /**
     * Add custom validation to check once form constraints are validated.
     *
     * CSRF token is checked in App\Service\Security\LoginFormAuthenticationManager::getUser()
     * Success flash message is made in App\Event\Subscriber\UserSubscriber::onSecurityInteractiveLogin()
     * Success redirection is made in App\Service\Security\LoginFormAuthenticationManager::onAuthenticationSuccess()
     *
     * @return bool
     *
     * @see AbstractFormHandler::processFormRequest()
     */
    protected function addCustomValidation() : bool
    {
        // Get the last authentication error
        $authenticationError = $this->authenticationUtils->getLastAuthenticationError();
        // DTO is in valid state but with authentication error.
        if (!\is_null($authenticationError)) {
            $this->customError = $authenticationError;
            $this->flashBag->add('danger', 'Authentication failed!<br>Try to login again by checking the form fields.');
            return false;
        }
        return true;
    }

    /**
     * Get the authentication error.
     *
     * @return string|null
     */
    public function getAuthenticationError() : ?string
    {
        return $this->customError;
    }
}
