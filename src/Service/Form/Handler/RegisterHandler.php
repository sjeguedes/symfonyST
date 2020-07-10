<?php

declare(strict_types = 1);

namespace App\Service\Form\Handler;

use App\Action\Admin\RegisterAction;
use App\Domain\ServiceLayer\UserManager;
use App\Service\Form\Type\Admin\RegisterType;
use App\Service\Mailer\Email\EmailConfigFactory;
use App\Service\Mailer\Email\EmailConfigFactoryInterface;
use App\Service\Mailer\SwiftMailerManager;
use App\Utils\Traits\CSRFTokenHelperTrait;
use App\Utils\Traits\UserHandlingHelperTrait;
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
    use UserHandlingHelperTrait;

    /**
     * @var csrfTokenManagerInterface
     */
    private $csrfTokenManager;

    /**
     * @var EmailConfigFactoryInterface
     */
    private $emailConfigFactory;

    /**
     * @var SwiftMailerManager
     */
    private $mailer;

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
        parent::__construct($flashBag, $formFactory, RegisterType::class, $requestStack);
        $this->csrfTokenManager = $csrfTokenManager;
        $this->customError = null;
        $this->emailConfigFactory = $emailConfigFactory;
        $this->mailer = $mailer;
    }

    /**
     * Add custom validation to check once form constraints are validated.
     *
     * @param array $actionData some data to handle
     *
     * @return bool
     *
     * @throws \Exception
     *
     * @see AbstractFormHandler::processFormRequest()
     */
    protected function addCustomValidation(array $actionData) : bool
    {
        $csrfToken = $this->request->request->get('register')['token'];
        // CSRF token is not valid.
        if (false === $this->isCSRFTokenValid('register_token', $csrfToken)) {
            throw new \Exception('Security error: CSRF form token is invalid!');
        }
        // Check UserManager instance in passed data
        $this->checkNecessaryData($actionData);
        $userService = $actionData['userService'];
        // DTO is in valid state but chosen email or username (nickname) must not exist in database.
        return $this->checkUserUniqueData($userService);
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
        // Check UserManager instance in passed data
        $this->checkNecessaryData($actionData);
        /** @var UserManager $userService */
        $userService = $actionData['userService'];
        // Create a new user in database with the validated DTO
        $newUser = $userService->createUser($this->form->getData());
        // Send email notification
        $emailParameters = [
            'receiver'     => [$newUser->getEmail() => $newUser->getFirstName() . ' ' . $newUser->getFamilyName()],
            'templateData' => ['user' => $newUser],
        ];
        $emailConfig = $this->emailConfigFactory->createFromActionContext(
            RegisterAction::class,
            EmailConfigFactory::USER_REGISTER,
            $emailParameters
        );
        $isEmailSent = $this->mailer->notify($emailConfig);
        // Technical error when trying to send
        if (!$isEmailSent) {
            $this->flashBag->add(
                'info',
                nl2br('Your account is successfully created!' . "\n" .
                'However, confirmation email was not sent' . "\n" .
                'due to technical reasons...' . "\n" . 'Please contact us if necessary.')
            );
        } else {
            $this->flashBag->add(
                'success',
                nl2br('An email was sent successfully!' . "\n" .
                'Please check your box' . "\n" .
                'and look at your registration confirmation' . "\n" .
                'to "validate your account".')
            );
        }
    }

    /**
     * Get the unique user error.
     *
     * @return array|null
     */
    public function getUniqueUserError() : ?array
    {
        return $this->customError;
    }
}
