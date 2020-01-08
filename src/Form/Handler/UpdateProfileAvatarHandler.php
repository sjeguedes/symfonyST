<?php

declare(strict_types = 1);

namespace App\Form\Handler;

use App\Domain\DTO\UpdateProfileAvatarDTO;
use App\Domain\ServiceLayer\UserManager;
use App\Form\Type\Admin\UpdateProfileAvatarType;
use App\Service\Medias\Upload\ImageUploader;
use App\Utils\Traits\CSRFTokenHelperTrait;
use App\Utils\Traits\UserHandlingHelperTrait;
use http\Exception\InvalidArgumentException;
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
final class UpdateProfileAvatarHandler extends AbstractFormHandler
{
    use CSRFTokenHelperTrait;
    use UserHandlingHelperTrait;

    /**
     * @var csrfTokenManagerInterface
     */
    private $csrfTokenManager;

    /**
     * @var ImageUploader
     */
    private $imageUploader;

    /**
     * @var null
     */
    private $userToCreate;

    /**
     * UpdateProfileAvatarHandler constructor.
     *
     * @param CsrfTokenManagerInterface   $csrfTokenManager
     * @param FlashBagInterface           $flashBag
     * @param FormFactoryInterface        $formFactory
     * @param ImageUploader               $imageUploader
     * @param RequestStack                $requestStack
     */
    public function __construct(
        csrfTokenManagerInterface $csrfTokenManager,
        FlashBagInterface $flashBag,
        FormFactoryInterface $formFactory,
        ImageUploader $imageUploader,
        RequestStack $requestStack
    ) {
        parent::__construct($flashBag, $formFactory, UpdateProfileAvatarType::class, $requestStack);
        $this->csrfTokenManager = $csrfTokenManager;
        $this->customError = null;
        $this->imageUploader = $imageUploader;
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
                $cropDataError = 'Image upload can not be performed<br>due to incoherent crop data';
                // Do not create a flash message in case of ajax form validation
                $message = 'Form validation failed!<br>A technical error happened.';
                if (!$this->request->isXmlHttpRequest()) {
                    $this->customError = $cropDataError;
                    $this->flashBag->add('danger', $message);
                } else {
                    $this->customError = ['formError' => ['notification' => sprintf('%s<br>%s', $message, $cropDataError)]];
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
        // Update (including removal action) a user in database with the validated DTO
        /** @var UserManager $userService */
        $isAvatarUpdated = $userService->updateUserProfileAvatar(
            $this->form->getData(),
            $identifiedUser,
            $imageService
        );
        if (!$isAvatarUpdated) {
            $updateError = 'Your image was not uploaded!<br>Try again later or use another file.';
            // Do not create a flash message in case of ajax form action
            $message = 'Avatar update failed!<br>A technical error happened.';
            if (!$this->request->isXmlHttpRequest()) {
                $this->customError = $updateError;
                $this->flashBag->add('danger', $message);
            } else {
                $this->customError = ['formError' => ['notification' => sprintf('%s<br>%s', $message, $updateError)]];
            }
        } else {
            // Adapt message depending on avatar update or removal
            $info = false === $this->form->getData()->getRemoveAvatar()
                ? 'Your avatar was updated successfully!<br>It appears when you post comments on website.'
                : 'Your avatar was removed successfully!';
            $this->flashBag->add('success', sprintf('That\'s Cool <strong>%s</strong>,<br>%s', $identifiedUser->getNickName(), $info));
        }
    }

    /**
     * Check if crop data are valid.
     *
     * @param UpdateProfileAvatarDTO $dataModel
     *
     * @return bool
     *
     * @throws \Exception
     *
     * @see https://medium.com/@ideneal/how-to-handle-json-requests-using-forms-on-symfony-4-and-getting-a-clean-code-67dd796f3d2f
     */
    private function checkAvatarCropData(UpdateProfileAvatarDTO $dataModel) : bool
    {
        // Get an array of crop data objects (This array is useful in case of multiple uploads.)
        $cropData = json_decode($dataModel->getCropJSONData());
        // Optional with Symfony 4.3 JSON validation constraint
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Crop data is an invalid json string!');
        }
        $avatar = $dataModel->getAvatar();
        $isFileMatched = $avatar->getClientOriginalName() === $cropData[0]->imageName;
        $areCropAreaDataWithIntegerType = \is_int($cropData[0]->x) && \is_int($cropData[0]->y) && \is_int($cropData[0]->width) && \is_int($cropData[0]->height);
        if (!$isFileMatched || !$areCropAreaDataWithIntegerType) {
            throw new \InvalidArgumentException('Retrieved image crop data are invalid due to possible technical error, or user input tampered data!');
        }
        // Get the corresponding instance of stdClass
        $cropDataX = $cropData[0]->x;
        $cropDataY = $cropData[0]->y;
        $cropDataWidth = $cropData[0]->width;
        $cropDataHeight = $cropData[0]->height;
        // Get uploaded image dimensions to evaluate crop data
        $imageSize = getimagesize($avatar->getPathname());
        $imageWidth = $imageSize[0];
        $imageHeight = $imageSize[1];
        // Check top left coords for future crop area and its width / height to be contained in uploaded image natural dimensions
        $coherentCropData = ($cropDataX + $cropDataWidth <= $imageWidth) && ($cropDataY + $cropDataHeight <= $imageHeight);
        return $coherentCropData;
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
