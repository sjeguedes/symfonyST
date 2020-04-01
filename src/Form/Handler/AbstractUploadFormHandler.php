<?php

declare(strict_types = 1);

namespace App\Form\Handler;

use App\Domain\DTO\AbstractReadableDTO;
use App\Domain\Entity\User;
use App\Domain\ServiceLayer\ImageManager;
use App\Domain\ServiceLayer\UserManager;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

/**
 * Class AbstractUploadFormHandler.
 *
 * Define Form Handler essential responsibilities with upload control.
 */
class AbstractUploadFormHandler extends AbstractFormHandler
{
    /**
     * AbstractUploadFormHandler constructor.
     *
     * @param FlashBagInterface    $flashBag
     * @param FormFactoryInterface $formFactory
     * @param string               $formType
     * @param RequestStack         $requestStack
     */
    public function __construct(
        FlashBagInterface $flashBag,
        FormFactoryInterface $formFactory,
        string $formType,
        RequestStack $requestStack
    ) {
        parent::__construct($flashBag, $formFactory, $formType, $requestStack);
    }

    /**
     * Check if form crop data are valid for a particular image.
     *
     * @param UploadedFile $image
     * @param string       $cropJSONData
     *
     * @return bool
     *
     * @see https://medium.com/@ideneal/how-to-handle-json-requests-using-forms-on-symfony-4-and-getting-a-clean-code-67dd796f3d2f
     *
     * @throws \Exception
     */
    public function checkCropData(UploadedFile $image, ?string $cropJSONData) : bool
    {
        if (\is_null($cropJSONData)) {
            return false;
        }
        // Get an array of crop data objects (This array is useful in case of multiple uploads.)
        $cropData = json_decode($cropJSONData);
        // Optional with Symfony 4.3 JSON validation constraint
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Crop data is an invalid json string!');
        }
        // Use str_replace() function to remove added "\" (by Javascript) to make a correct comparison
        $isFileMatched = urldecode($image->getClientOriginalName()) === str_replace('\\', '', $cropData[0]->imageName);
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
        // Please note uploaded file validity (size, mime type...) was already checked here thanks to field constraints!
        $imageSize = getimagesize($image->getPathname());
        $imageWidth = $imageSize[0];
        $imageHeight = $imageSize[1];
        // Check top left coords for future crop area and its width / height to be contained in uploaded image natural dimensions
        $coherentCropData = ($cropDataX + $cropDataWidth <= $imageWidth) && ($cropDataY + $cropDataHeight <= $imageHeight);
        return $coherentCropData;
    }
}
