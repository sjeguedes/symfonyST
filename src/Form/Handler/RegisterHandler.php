<?php

declare(strict_types = 1);

namespace App\Form\Handler;

use App\Action\Admin\RegisterAction;
use App\Domain\ServiceLayer\UserManager;
use App\Form\Type\Admin\RegisterType;
use App\Service\Mailer\Email\EmailConfigFactory;
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
        $userService = $actionData['userService'] ?? null;
        if (!$userService instanceof UserManager || \is_null($userService)) {
            throw new \InvalidArgumentException('A instance of UserManager must be set first!');
        }
        // DTO is in valid state:
        // but filled in email already exists in database.
        $emailToCheck = $this->form->getData()->getEmail(); // or $this->form->get('email')->getData()
        $isEmailUnique = $this->isUserUnique('email', $emailToCheck, $userService);
        $isUsernameUnique = false;
        // Execute second database query only if email is unique.
        if ($isEmailUnique) {
            // but filled in username already exists in database.
            $userNameToCheck = $this->form->getData()->getUserName(); // or $this->form->get('userName')->getData()
            $isUsernameUnique = $this->isUserUnique('username', $userNameToCheck, $userService);
        }
        if (false === $isEmailUnique || false === $isUsernameUnique) {
            $this->flashBag->add('danger','Form registration failed!<br>Try to request again by checking the fields.');
            return false;
        }
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
        $userService = $actionData['userService'];
        if (!$userService instanceof UserManager || \is_null($userService)) {
            throw new \InvalidArgumentException('A instance of UserManager must be set first!');
        }
        // Create a new user in database with the validated DTO
        $newUser = $userService->createUser($this->form->getData());
        // Save data
        $userService->getEntityManager()->persist($newUser);
        $userService->getEntityManager()->flush();
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
            $this->flashBag->add('info','Your account is successfully created!<br>However, confirmation email was not sent<br>due to technical reasons...<br>Please contact us if necessary.');
        } else {
            $this->flashBag->add('success','An email was sent successfully!<br>Please check your box<br>and look at your registration confirmation<br>to <strong>validate your account</strong>.');
        }
    }

    /**
     * Get the authentication error.
     *
     * @return string|null
     */
    public function getUniqueUserError()
    {
        return $this->customError;
    }

    /**
     * Check if a user is unique according to a type property.
     *
     * @param string      $type
     * @param string      $value
     * @param UserManager $userService
     *
     * @return bool
     *
     * @throws \Exception
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function isUserUnique(string $type, string $value, UserManager $userService) : bool
    {
        if (!\in_array($type, ['email', 'username'])) {
            throw new \InvalidArgumentException('Type of value is unknown!');
        }
        $isUniqueUser = \is_null($userService->getRepository()->loadUserByUsername($value)) ? true : false;
        if (false === $isUniqueUser) {
            switch ($type) {
                case 'email':
                    $uniqueEmailError = 'Please choose another email address!<br>It is already used!';
                    $this->customError = ['email' => $uniqueEmailError];
                    break;
                case 'username':
                    $uniqueUserNameError = 'Please choose another username!<br>Your nickname is already used!';
                    $this->customError =  ['username' => $uniqueUserNameError];
                    break;
            }
        }
        return $isUniqueUser;
    }
}
