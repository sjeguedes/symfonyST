<?php

declare(strict_types = 1);

namespace App\Service\Form\Handler;

use App\Domain\DTO\UpdateProfileInfosDTO;
use App\Domain\Entity\User;
use App\Domain\ServiceLayer\UserManager;
use App\Service\Form\Type\Admin\UpdateProfileInfosType;
use App\Utils\Traits\CSRFTokenHelperTrait;
use App\Utils\Traits\UserHandlingHelperTrait;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Class UpdateProfileInfosHandler.
 *
 * Handle the form request when a user tries to update his profile infos (without avatar).
 * Call any additional validations and actions.
 */
final class UpdateProfileInfosHandler extends AbstractFormHandler implements InitModelDataInterface
{
    use CSRFTokenHelperTrait;
    use UserHandlingHelperTrait;

    /**
     * @var csrfTokenManagerInterface
     */
    private $csrfTokenManager;

    /**
     * UpdateProfileInfosHandler constructor.
     *
     * @param CsrfTokenManagerInterface   $csrfTokenManager
     * @param FlashBagInterface           $flashBag
     * @param FormFactoryInterface        $formFactory
     * @param RequestStack                $requestStack
     */
    public function __construct(
        csrfTokenManagerInterface $csrfTokenManager,
        FlashBagInterface $flashBag,
        FormFactoryInterface $formFactory,
        RequestStack $requestStack
    ) {
        parent::__construct($flashBag, $formFactory, UpdateProfileInfosType::class, $requestStack);
        $this->csrfTokenManager = $csrfTokenManager;
        $this->customError = null;
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
        $csrfToken = $this->request->request->get('update_profile_infos')['token'];
        // CSRF token is not valid.
        if (false === $this->isCSRFTokenValid('update_profile_infos_token', $csrfToken)) {
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
        // Check UserManager and User instances in passed data
        $this->checkNecessaryData($actionData);
        $userService = $actionData['userService'];
        $identifiedUser = $actionData['userToUpdate'];
        // Update a user in database with the validated DTO
        /** @var UserManager $userService */
        $userService->updateUserProfileInfos(
            $this->form->getData(),
            $identifiedUser
        );
        $this->flashBag->add(
            'success',
            sprintf(
                nl2br('That\'s Cool "%s",' . "\n" .
                'Your account was updated successfully!' . "\n" .
                'Please consider your new credentials' . "\n" .
                'if you changed them.'),
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
     * Get the non unique user error.
     *
     * @return array|null
     */
    public function getUniqueUserError() : ?array
    {
        return $this->customError;
    }

    /**
     * {@inheritDoc}
     *
     * @return object a UpdateProfileInfosDTO instance
     *
     * @throws \Exception
     */
    public function initModelData(array $data) : object
    {
        // Check User instance in passed data
        $this->checkNecessaryData($data);
        $user = $data['userToUpdate'];
        return new UpdateProfileInfosDTO(
            $user->getFamilyName(),
            $user->getFirstName(),
            $user->getNickName(),
            $user->getEmail()
            // Password has to be set to null in pre-populated data: it cannot be retrieved (and must not be!).
        );
    }
}
