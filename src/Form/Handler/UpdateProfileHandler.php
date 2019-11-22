<?php

declare(strict_types = 1);

namespace App\Form\Handler;

use App\Domain\DTO\UpdateProfileDTO;
use App\Form\Type\Admin\UpdateProfileType;
use App\Service\Mailer\Email\EmailConfigFactoryInterface;
use App\Service\Mailer\SwiftMailerManager;
use App\Service\Medias\Upload\ImageUploader;
use App\Utils\Traits\CSRFTokenHelperTrait;
use Symfony\Component\Form\FormFactoryInterface;
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
        parent::__construct($flashBag, $formFactory,UpdateProfileType::class, $requestStack);
        $this->csrfTokenManager = $csrfTokenManager;
        $this->customError = null;
        $this->emailConfigFactory = $emailConfigFactory;
        $this->imageUploader = $imageUploader;
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
        $csrfToken = $this->request->request->get('update_profile')['token'];
        // CSRF token is not valid.
        if (false === $this->isCSRFTokenValid('update_profile_token', $csrfToken)) {
            throw new \Exception('Security error: CSRF form token is invalid!');
        }
        // Check UserManager instance in passed data
        $userService = $this->checkUserServiceInstance($actionData);
        // Check User instance in passed data
        $identifiedUser = $this->checkUserInstance($actionData);
        // DTO is in valid state: chosen email or username (nickname) must not equals existing values to be checked.
        $isEmailToCheck = $identifiedUser->getEmail() !== $this->form->getData()->getEmail(); // or $this->form->get('email')->getData()
        $isUserNameToCheck = $identifiedUser->getNickName() !== $this->form->getData()->getUserName(); // or $this->form->get('userName')->getData()
        if ($isEmailToCheck || $isUserNameToCheck) {
            // Chosen email or username (nickname) must not exist in database.
            $check = true;
            switch ($check) {
                case $isEmailToCheck && $isUserNameToCheck:
                    $this->checkUserUniqueData($userService);
                    break;
                case $isEmailToCheck:
                    $this->checkUserUniqueData($userService,'email');
                    break;
                case $isUserNameToCheck && $isUserNameToCheck:
                    $this->checkUserUniqueData($userService,'username');
                    break;
            }
            return $check;
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
        // Check UserManager instance in passed data
        $userService = $this->checkUserServiceInstance($actionData);
        // Check User instance in passed data
        $identifiedUser = $this->checkUserInstance($actionData);
        // Update a user in database with the validated DTO
        $userService->updateUserProfile($this->form->getData(), $identifiedUser, $this->imageUploader, $this->imageUploader->getMediaTypeService());
        $this->flashBag->add(
            'success',
            sprintf(
                'That\'s Cool <strong>%s</strong>,<br>Your account was updated successfully!<br>Please consider your new credentials<br>if you changed them.',
                $identifiedUser->getNickName()
            )
        );
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
     * @return object|UpdateProfileDTO
     *
     * @throws \Exception
     */
    public function initModelData(array $data) : object
    {
        $user = $this->checkUserInstance($data);
        return new UpdateProfileDTO(
            $user->getFamilyName(),
            $user->getFirstName(),
            $user->getNickName(),
            $user->getEmail(),
            null,
            null //$user->getAvatar()
        );
    }
}
