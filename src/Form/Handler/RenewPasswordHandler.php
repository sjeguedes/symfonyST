<?php

declare(strict_types = 1);

namespace App\Form\Handler;

use App\Action\Admin\RenewPasswordAction;
use App\Domain\DTO\RenewPasswordDTO;
use App\Domain\Entity\User;
use App\Domain\ServiceLayer\UserManager;
use App\Form\Type\Admin\RenewPasswordType;
use App\Service\Mailer\Email\EmailConfigFactory;
use App\Service\Mailer\Email\EmailConfigFactoryInterface;
use App\Service\Mailer\SwiftMailerManager;
use App\Utils\Traits\CSRFTokenHelperTrait;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Class RenewPasswordHandler.
 *
 * Handle the form request when a user wants to renew his password.
 * Call any additional validations and actions.
 */
final class RenewPasswordHandler extends AbstractFormHandler implements InitModelDataInterface
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
     * @var SwiftMailerManager
     */
    private $mailer;

    /**
     * @var User|null
     */
    private $userToUpdate;

    /**
     * RenewPasswordHandler constructor.
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
        parent::__construct($flashBag, $formFactory,RenewPasswordType::class, $requestStack);
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
        $csrfToken = $this->request->request->get('renew_password')['token'];
        // CSRF token is not valid.
        if (false === $this->isCSRFTokenValid('renew_password_token', $csrfToken)) {
            throw new \Exception('Security error: CSRF form token is invalid!');
        }
        // Get allowed user who asks for a new password.
        // Check User instance in passed data
        $identifiedUser = $this->checkUserInstance($actionData);
        // Validate user matching only if username field is not disabled.
        if (!$this->form->get('userName')->isDisabled()) {
            $isUserInFormMatched = $this->isIdentifiedUserMatchedInForm($identifiedUser);
            // DTO is in valid state but filled in username does not match identified user's username.
            if (!$isUserInFormMatched) {
                $userNameError = 'Please check your credentials!<br>Your username is not allowed!';
                $this->customError = $userNameError;
                $this->flashBag->add('danger','Authentication failed!<br>Try to request again by checking the form fields.');
                return false;
            }
        }
        $this->userToUpdate = $identifiedUser;
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
        // Check UserManager instance in passed data
        $userService = $this->checkUserServiceInstance($actionData);
        $user = $this->userToUpdate;
        // Save data
        $updatedUser = $userService->renewPassword($user, $this->form->getData()->getPasswords()); // or $this->form->get('passwords')->getData()
        // Send email notification
        $emailParameters = [
            'receiver'     => [$updatedUser->getEmail() => $updatedUser->getFirstName() . ' ' . $updatedUser->getFamilyName()],
            'templateData' => ['user' => $updatedUser],
        ];
        $emailConfig = $this->emailConfigFactory->createFromActionContext(
            RenewPasswordAction::class,
            EmailConfigFactory::USER_RENEW_PASSWORD,
            $emailParameters
        );
        $isEmailSent = $this->mailer->notify($emailConfig);
        // Technical error when trying to send
        if (!$isEmailSent) {
            $this->flashBag->add(
                'info',
                'Your password renewal is successfully saved!<br>However, confirmation email was not sent<br>due to technical reasons...<br>Please contact us if necessary.'
            );
        } else {
            $this->flashBag->add(
                'success',
                'An email was sent successfully!<br>Please check your box<br>to look at your password renewal confirmation.'
            );
        }
    }

    /**
     * Get the filled in username error.
     *
     * @return string|null
     */
    public function getUserNameError()
    {
        return $this->customError;
    }

    /**
     * {@inheritDoc}
     *
     * @return object|RenewPasswordDTO
     *
     * @throws \Exception
     */
    public function initModelData(array $data) : object
    {
        $user = $this->checkUserInstance($data);
        return new RenewPasswordDTO($user->getEmail());
    }

    /**
     * Is username filled in form the same as identified user one.
     *
     * @param User $identifiedUser
     *
     * @return bool
     */
    private function isIdentifiedUserMatchedInForm(User $identifiedUser) : bool
    {
        $matchedNickNames = $identifiedUser->getNickName() === $this->form->get('userName')->getData();
        $matchedEmails =  $identifiedUser->getEmail() === $this->form->get('userName')->getData();
        return  $matchedNickNames || $matchedEmails;
    }
}
