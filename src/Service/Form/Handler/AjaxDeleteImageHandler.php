<?php

declare(strict_types = 1);

namespace App\Service\Form\Handler;

use App\Domain\Entity\Image;
use App\Domain\ServiceLayer\ImageManager;
use App\Domain\ServiceLayer\MediaManager;
use App\Service\Form\Type\Admin\AjaxDeleteImageType;
use App\Utils\Traits\CSRFTokenHelperTrait;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Class AjaxDeleteImageHandler.
 *
 * Handle the form request when a user tries to remove a particular (temporary or attached) image.
 * Call any additional validations and actions.
 *
 * Please note this is used for trick creation or update!
 */
final class AjaxDeleteImageHandler extends AbstractFormHandler
{
    use CSRFTokenHelperTrait;
    use LoggerAwareTrait;

    /**
     * @var csrfTokenManagerInterface
     */
    private $csrfTokenManager;

    /**
     * @var array
     */
    private $customSuccess;

    /**
     * @var Image|null
     */
    private $imageToRemove;

    /**
     * UpdateProfileAvatarHandler constructor.
     *
     * @param CsrfTokenManagerInterface $csrfTokenManager
     * @param FlashBagInterface         $flashBag
     * @param FormFactoryInterface      $formFactory
     * @param LoggerInterface           $logger
     * @param RequestStack              $requestStack
     */
    public function __construct(
        csrfTokenManagerInterface $csrfTokenManager,
        FlashBagInterface $flashBag,
        FormFactoryInterface $formFactory,
        LoggerInterface $logger,
        RequestStack $requestStack
    ) {
        parent::__construct($flashBag, $formFactory, AjaxDeleteImageType::class, $requestStack);
        $this->csrfTokenManager = $csrfTokenManager;
        $this->customError = null;
        $this->customSuccess = null;
        $this->imageToRemove = null;
        $this->setLogger($logger);
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
        $csrfToken = $this->request->request->get('ajax_delete_image')['token'];
        // CSRF token is not valid.
        if (false === $this->isCSRFTokenValid('ajax_delete_image_token', $csrfToken)) {
            throw new \Exception('Security error: CSRF form token is invalid!');
        }
        // Check UserManager, User, and ImageManager instances in passed data
        $this->checkNecessaryData($actionData);
        // DTO is in valid state: check image existence and coherence
        /** @var ImageManager $imageService */
        $imageService = $actionData['imageService'];
        $uuid = $this->form->getData()->getUuid(); // or $this->form->get('uuid')->getData()
        $uuidBytes = $uuid->getBytes();
        $imageToRemove = $imageService->findSingleByUuid($uuid);
        // Check if form data were not altered by user:
        // So, is there an existing result and also Image entity name matches image name from form)
        $areDataCoherent = !\is_null($imageToRemove) && $imageToRemove->getName() === $this->form->getData()->getName();
        if (!$areDataCoherent) {
            $this->customError = [
                'formError' => [
                    'notification' => sprintf(
                        nl2br(
                            'You are not allowed to tamper data!' . "\n" .
                            'Please use form as expected.'
                        )
                    )
                ]
            ];
            return false;
        }
        // Store Image entity to perform removal after
        $this->imageToRemove = $imageToRemove;
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
        // Check Managers and Image instances in passed data
        $this->checkNecessaryData($actionData);
        /** @var ImageManager $imageService */
        $imageService = $actionData['imageService'];
        /** @var MediaManager $mediaService */
        $mediaService = $actionData['mediaService'];
        // Proceed to image removal
        $imageDirectoryKey = $this->form->getData()->getMediaOwnerType() . 'Images';
        $isImageRemoved = false;
        // Remove image physically
        $isImageFileRemoved = $imageService->removeOneImageFile($this->imageToRemove, $imageDirectoryKey);
        if ($isImageFileRemoved ) {
            // Remove entity and corresponding entities thanks to cascade option
            $isImageEntityRemoved = $imageService->removeImage($this->imageToRemove);
            if ($isImageEntityRemoved ) {
                $isImageRemoved = true;
            }
        } else {
            $this->logger->critical(
                sprintf(
                    "[trace app snowTricks] AjaxDeleteImageHandler/addCustomAction => error: " .
                    "image file or entity removal with identifier name \"%s\" created on \"%s\" was not performed correctly!",
                    $this->imageToRemove->getName(),
                    $this->imageToRemove->getCreationDate()->format('d/m/Y H:i:s')
                )
            );
        }
        // Prepare notification messages to show
        if (!$isImageRemoved) {
            $deletionError = nl2br(
                'Your image was not removed correctly!' . "\n" .
                'Try again later or contact us if necessary.'
            );
            // Prepare image deletion error message
            $message = nl2br('Image deletion failed!' . "\n" . 'A technical error happened.');
            $this->customError = [
                'formError' => ['notification' => sprintf(nl2br('%s' . "\n" . '%s'), $message, $deletionError)]
            ];
        } else {
            // Prepare image deletion success message
            $message = nl2br(
                'Your image was removed successfully!' . "\n" .
                'Please note corresponding file' . "\n" .
                'did not exist on server anymore.'
            );
            $this->customSuccess = [
                'formSuccess' => ['notification' => sprintf(nl2br('%s'), $message)]
            ];

        }
    }

    /**
     * Get the image deletion error.
     *
     * @return array|null
     */
    public function getImageDeletionError() : ?array
    {
        return $this->customError;
    }

    /**
     * Get the image deletion success message.
     *
     * @return array|null
     */
    public function getImageDeletionSuccess() : ?array
    {
        return $this->customSuccess;
    }
}
