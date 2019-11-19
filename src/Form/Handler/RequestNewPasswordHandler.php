<?php

declare(strict_types = 1);

namespace App\Form\Handler;

use App\Action\Admin\RequestNewPasswordAction;
use App\Domain\Entity\User;
use App\Domain\ServiceLayer\UserManager;
use App\Form\Type\Admin\RequestNewPasswordType;
use App\Service\Mailer\Email\EmailConfigFactory;
use App\Service\Mailer\Email\EmailConfigFactoryInterface;
use App\Service\Mailer\SwiftMailerManager;
use App\Utils\Traits\CSRFTokenHelperTrait;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Class RequestNewPasswordHandler.
 *
 * Handle the form request when a user asks for reset his password.
 * Call any additional validations and actions.
 */
final class RequestNewPasswordHandler extends AbstractFormHandler
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
     * @var string
     */
    private $customError;

    /**
     * @var SwiftMailerManager
     */
    private $mailer;

    /**
     * @var User|null
     */
    private $userToUpdate;

    /**
     * RequestNewPasswordHandler constructor.
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
        parent::__construct($flashBag, $formFactory,RequestNewPasswordType::class, $requestStack);
        $this->csrfTokenManager = $csrfTokenManager;
        $this->customError = null;
        $this->emailConfigFactory = $emailConfigFactory;
        $this->mailer = $mailer;
        $this->userToUpdate = null;

    }

    /**
     * Add custom validation to check once form constraints are validated.
     *
     * @param array $actionData some data to handle
     *
     * @return bool
     *
     * @throws \Exception
     */
    protected function addCustomValidation(array $actionData) : bool
    {
        $csrfToken = $this->request->request->get('request_new_password')['token'];
        // CSRF token is not valid.
        if (false === $this->isCSRFTokenValid('request_new_password_token', $csrfToken)) {
            throw new \Exception('Security error: CSRF form token is invalid!');
        }
        // Find user who asks for a new password by using a user service
        $userService = $actionData['userService'] ?? null;
        if (!$userService instanceof UserManager || \is_null($userService)) {
            throw new \InvalidArgumentException('A instance of UserManager must be set first!');
        }
        $loadedUser = $userService->getRepository()->loadUserByUsername($this->form->getData()->getUserName()); // or $this->form->get('userName')->getData()
        // DTO is in valid state but user can not be found.
        if (\is_null($loadedUser)) {
            $userError = 'Please check your credentials!<br>User can not be found.';
            $this->customError = $userError;
            $this->flashBag->add('danger', 'Form authentication failed!<br>Try to request again by checking the fields.');
            return false;
        }
        $this->userToUpdate = $loadedUser;
        return true;
    }

    /**
     * Add custom action once form is validated.
     *
     * @param array $actionData some data to handle
     *
     * @return void
     *
     * @throws \ReflectionException
     * @throws \Exception
     * @see AbstractFormHandler::processFormRequest()
     */
    protected function addCustomAction(array $actionData) : void
    {
        $userService = $actionData['userService'];
        if (!$userService instanceof UserManager || \is_null($userService)) {
            throw new \InvalidArgumentException('A instance of UserManager must be set first!');
        }
        $user = $this->userToUpdate;
        // Save data
        /** @var User $updatedUser */
        $updatedUser = $userService->generatePasswordRenewalToken($user);
        // Send email notification
        $emailParameters = [
            'receiver'     => [$updatedUser->getEmail() => $updatedUser->getFirstName() . ' ' . $updatedUser->getFamilyName()],
            'templateData' => ['user' => $updatedUser]
        ];
        // Use a factory to configure the email
        $emailConfig = $this->emailConfigFactory->createFromActionContext(
            RequestNewPasswordAction::class,
            EmailConfigFactory::USER_ASK_FOR_RENEW_PASSWORD,
            $emailParameters
        );
        $isEmailSent = $this->mailer->notify($emailConfig);
        // Technical error when trying to send
        if (!$isEmailSent) {
            throw new \Exception(sprintf('Notification failed: email was not sent to %s due to technical error or wrong parameters!', $updatedUser->getEmail()));
        }
        $this->flashBag->add('success','An email was sent successfully!<br>Please check your box and<br>use your personalized link<br>to renew your password.');
    }

    /**
     * Get the user not found error.
     *
     * @return string|null
     */
    public function getUserError()
    {
        return $this->customError;
    }
}
