<?php

declare(strict_types = 1);

namespace App\Form\Handler;

use App\Domain\DTO\UpdateProfileAvatarDTO;
use App\Domain\ServiceLayer\UserManager;
use App\Form\Type\Admin\UpdateProfileAvatarType;
use App\Utils\Traits\CSRFTokenHelperTrait;
use App\Utils\Traits\UserHandlingHelperTrait;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Class UpdateProfileAvatarHandler.
 *
 * Handle the form request when a user tries to update his profile avatar.
 * Call any additional validations and actions.
 */
final class UpdateProfileAvatarHandler extends AbstractUploadFormHandler
{
    use CSRFTokenHelperTrait;
    use UserHandlingHelperTrait;

    /**
     * @var csrfTokenManagerInterface
     */
    private $csrfTokenManager;

    /**
     * UpdateProfileAvatarHandler constructor.
     *
     * @param CsrfTokenManagerInterface $csrfTokenManager
     * @param FlashBagInterface         $flashBag
     * @param FormFactoryInterface      $formFactory
     * @param RequestStack              $requestStack
     */
    public function __construct(
        csrfTokenManagerInterface $csrfTokenManager,
        FlashBagInterface $flashBag,
        FormFactoryInterface $formFactory,
        RequestStack $requestStack
    ) {
        parent::__construct($flashBag, $formFactory, UpdateProfileAvatarType::class, $requestStack);
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
        $csrfToken = $this->request->request->get('update_profile_avatar')['token'];
        // CSRF token is not valid.
        if (false === $this->isCSRFTokenValid('update_profile_avatar_token', $csrfToken)) {
            throw new \Exception('Security error: CSRF form token is invalid!');
        }
        // Check UserManager, User, and ImageManager instances in passed data
        $this->checkNecessaryData($actionData);
        // DTO is in valid state: crop data must be coherent with uploaded image size.
        if (!\is_null($this->form->getData()->getCropJSONData())) { /// or $this->form->get('cropJSONData')->getData()
            $coherentCropData = $this->checkAvatarCropData($this->form->getData());
            // Avoid user input tampered data or evaluate technical error by checking coherent crop data
            if (!$coherentCropData) {
                $cropDataError = nl2br('Image upload can not be performed' . "\n" . 'due to incoherent crop data');
                // Do not create a flash message in case of ajax form validation
                $message = nl2br('Form validation failed!' . "\n" . 'A technical error happened.');
                if (!$this->request->isXmlHttpRequest()) {
                    $this->customError = $cropDataError;
                    $this->flashBag->add('danger', $message);
                } else {
                    $this->customError = [
                        'formError' => ['notification' => sprintf(nl2br('%s' . "\n" . '%s'), $message, $cropDataError)]
                    ];
                }
            }
            return $coherentCropData;
        }
        // Crop JSON data is null (when existing avatar is only removed or with unchanged form), so no need to check data.
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
        // Check UserManager, User, and ImageManager instances in passed data
        $this->checkNecessaryData($actionData);
        $userService = $actionData['userService'];
        $identifiedUser = $actionData['userToUpdate'];
        $imageService = $actionData['imageService'];
        $mediaService = $actionData['mediaService'];
        // Update (including removal action) a user in database with the validated DTO
        /** @var UserManager $userService */
        $isAvatarUpdated = $userService->updateUserProfileAvatar(
            $this->form->getData(),
            $identifiedUser,
            $imageService,
            $mediaService
        );
        if (!$isAvatarUpdated) {
            $updateError = nl2br('Your image was not uploaded!' . "\n" . 'Try again later or use another file.');
            // Do not create a flash message in case of ajax form action
            $message = nl2br('Avatar update failed!' . "\n" . 'A technical error happened.');
            if (!$this->request->isXmlHttpRequest()) {
                $this->customError = $updateError;
                $this->flashBag->add('danger', $message);
            } else {
                $this->customError = [
                    'formError' => ['notification' => sprintf(nl2br('%s' . "\n" . '%s'), $message, $updateError)]
                ];
            }
        } else {
            // Adapt message depending on avatar update or removal
            $info = false === $this->form->getData()->getRemoveAvatar()
                ? nl2br('your avatar was updated successfully!' . "\n" . 'It appears when you post comments on website.')
                : 'your avatar was removed successfully!';
            $this->flashBag->add(
                'success',
                sprintf(nl2br('Action is done "%s",' . "\n" . '%s'), $identifiedUser->getNickName(), $info)
            );
        }
    }

    /**
     * Check if user avatar JSON crop data are valid.
     *
     * @param UpdateProfileAvatarDTO $dataModel
     *
     * @return bool
     *
     * @throws \Exception
     */
    private function checkAvatarCropData(UpdateProfileAvatarDTO $dataModel) : bool
    {
        return $this->checkCropData($dataModel->getAvatar(), $dataModel->getCropJSONData());
    }

    /**
     * Get the avatar update error.
     *
     * @return array|null
     */
    public function getUserAvatarError() : ?array
    {
        return $this->customError;
    }
}
