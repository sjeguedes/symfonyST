<?php

declare(strict_types = 1);

namespace App\Form\Handler;

use App\Domain\DTO\UpdateProfileDTO;
use App\Domain\Entity\User;
use App\Domain\ServiceLayer\UserManager;
use App\Form\Type\Admin\UpdateProfileType;
use App\Service\Mailer\Email\EmailConfigFactoryInterface;
use App\Service\Mailer\SwiftMailerManager;
use App\Service\Medias\Upload\ImageUploader;
use App\Utils\Traits\CSRFTokenHelperTrait;
use App\Utils\Traits\UserHandlingHelperTrait;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Class UpdateProfileHandler.
 *
 * Handle the form request when a user tries to update his profile (account).
 * Call any additional validations and actions.
 */
final class UpdateProfileHandler extends AbstractFormHandler implements InitModelDataInterface
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
     * @var ImageUploader
     */
    private $imageUploader;

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
     * @param ImageUploader               $imageUploader
     * @param SwiftMailerManager          $mailer
     * @param RequestStack                $requestStack
     */
    public function __construct(
        csrfTokenManagerInterface $csrfTokenManager,
        EmailConfigFactoryInterface $emailConfigFactory,
        FlashBagInterface $flashBag,
        FormFactoryInterface $formFactory,
        ImageUploader $imageUploader,
        RequestStack $requestStack,
        SwiftMailerManager $mailer
    ) {
        parent::__construct($flashBag, $formFactory, UpdateProfileType::class, $requestStack);
        $this->csrfTokenManager = $csrfTokenManager;
        $this->customError = null;
        $this->emailConfigFactory = $emailConfigFactory;
        $this->imageUploader = $imageUploader;
        $this->mailer = $mailer;
        $this->requestStack = $requestStack;
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
        $csrfToken = $this->request->request->get('update_profile')['token'];
        // CSRF token is not valid.
        if (false === $this->isCSRFTokenValid('update_profile_token', $csrfToken)) {
            throw new \Exception('Security error: CSRF form token is invalid!');
        }
        // Check UserManager and User instances in passed data
        $this->checkNecessaryData($actionData);
        $userService = $actionData['userService'];
        $identifiedUser = $actionData['userToUpdate'];
        // DTO is in valid state: chosen email or username (nickname) must be unique in database.
        return $this->checkUniqueFormData($identifiedUser, $userService);
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
        // Check UserManager, User, and ImageManager instances in passed data
        $this->checkNecessaryData($actionData);
        $userService = $actionData['userService'];
        $identifiedUser = $actionData['userToUpdate'];
        $imageService = $actionData['imageService'];
        // Update a user in database with the validated DTO
        $userService->updateUserProfile(
            $this->form->getData(),
            $identifiedUser,
            $imageService
        );
        $this->flashBag->add(
            'success',
            sprintf(
                'That\'s Cool <strong>%s</strong>,<br>Your account was updated successfully!<br>Please consider your new credentials<br>if you changed them.',
                $identifiedUser->getNickName()
            )
        );
    }

    /**
     * Check data in form to be unique as expected in database.
     *
     * @param User        $identifiedUser
     * @param UserManager $userService
     *
     * @return bool
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function checkUniqueFormData(User $identifiedUser, UserManager $userService) : bool
    {
        // Chosen email or username (nickname) must not equals existing values, to be effectively checked.
        $isEmailToCheck = $identifiedUser->getEmail() !== $this->form->getData()->getEmail(); // or $this->form->get('email')->getData()
        // Enable the possibility for a user to be able to update the same nickname with a new mix of lowercase and/or uppercase letters!
        $isUserNameToCheck = strtolower($identifiedUser->getNickName()) !== strtolower($this->form->getData()->getUserName()); // or $this->form->get('userName')->getData()
        $check = true;
        if ($isEmailToCheck || $isUserNameToCheck) {
            // Chosen email or username (nickname) must not exist in database.
            switch ($check) {
                case $isEmailToCheck && $isUserNameToCheck:
                    $check = $this->checkUserUniqueData($userService);
                    break;
                case $isEmailToCheck:
                    $check = $this->checkUserUniqueData($userService, 'email');
                    break;
                case $isUserNameToCheck:
                    $check = $this->checkUserUniqueData($userService, 'username');
                    break;
            }
        }
        return $check;
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
     * {@inheritDoc}
     *
     * @return object a UpdateProfileDTO instance
     *
     * @throws \Exception
     */
    public function initModelData(array $data) : object
    {
        // Check User instance in passed data
        $this->checkNecessaryData($data);
        $user = $data['userToUpdate'];
        return new UpdateProfileDTO(
            $user->getFamilyName(),
            $user->getFirstName(),
            $user->getNickName(),
            $user->getEmail()
            // Password has to be set to null in pre-populated data: it cannot be retrieved (and must not be!).
            // Avatar must be null in pre-populated data: after form submit it can be an Uploaded file instance.
            // Avatar removal is set to false by default.
        );
    }
}
