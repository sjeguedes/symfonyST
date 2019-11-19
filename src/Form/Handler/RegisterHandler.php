<?php

declare(strict_types = 1);

namespace App\Form\Handler;

use App\Domain\ServiceLayer\UserManager;
use App\Form\Type\Admin\RegisterType;
use App\Service\Mailer\Email\EmailConfigFactoryInterface;
use App\Service\Mailer\SwiftMailerManager;
use App\Utils\Traits\CSRFTokenHelperTrait;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Class RegisterHandler.
 *
 * Handle the form request when a new user tries to register.
 * Call any additional validations and actions.
 */
final class RegisterHandler extends AbstractFormHandler
{
    use CSRFTokenHelperTrait;

    /**
     * @var csrfTokenManagerInterface
     */
    private $csrfTokenManager;

    /**
     * @var EmailConfigFactoryInterface
     */
    private $emailConfigFactory;

    /**
     * @var string|null
     */
    private $customError;

    /**
     * @var SwiftMailerManager
     */
    private $mailer;

    /**
     * @var null
     */
    private $userToCreate;

    /**
     * RegisterHandler constructor.
     *
     * @param CsrfTokenManagerInterface   $csrfTokenManager
     * @param EmailConfigFactoryInterface $emailConfigFactory
     * @param FlashBagInterface           $flashBag
     * @param FormFactoryInterface        $formFactory
     * @param SwiftMailerManager          $mailer
     * @param RequestStack                $requestStack
     */
    public function __construct(
        csrfTokenManagerInterface $csrfTokenManager,
        EmailConfigFactoryInterface $emailConfigFactory,
        FlashBagInterface $flashBag,
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        SwiftMailerManager $mailer
    ) {
        parent::__construct($flashBag, $formFactory,RegisterType::class, $requestStack);
        $this->csrfTokenManager = $csrfTokenManager;
        $this->customError = null;
        $this->emailConfigFactory = $emailConfigFactory;
        $this->mailer = $mailer;
        $this->userToCreate = null;
    }

    /**
     * Add custom validation to check once form constraints are validated.
     *
     * @return bool
     *
     * @throws \Exception
     *
     * @see AbstractFormHandler::processFormRequest()
     */
    protected function addCustomValidation() : bool
    {
        $csrfToken = $this->request->request->get('register')['token'];
        // CSRF token is not valid.
        if (false === $this->isCSRFTokenValid('register_token', $csrfToken)) {
            throw new \Exception('Security error: CSRF form token is invalid!');
        }
        // DTO is in valid state but with custom validation error.
        // TODO: add custom validations here with false return if necessary (check unique nickname and unique email address).
        return true;
    }

    /**
     * Add custom action once form is validated.
     *
     * @param array $actionData some data to handle
     *
     * @return void
     *
     * @throws \Exception
     *
     * @see AbstractFormHandler::processFormRequest()
     */
    protected function addCustomAction(array $actionData) : void
    {
        $userService = $actionData['userService'] ?? null;
        if (!$userService instanceof UserManager || \is_null($userService)) {
            throw new \InvalidArgumentException('A instance of UserManager must be set first!');
        }
        // TODO: add custom actions here (sending email to activate account).
    }

    /**
     * Get the authentication error.
     *
     * @return string|null
     */
    public function getCustomError()
    {
        return $this->customError;
    }
}
