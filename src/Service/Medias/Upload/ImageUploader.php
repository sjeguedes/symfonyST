<?php

declare(strict_types = 1);

namespace App\Service\Medias\Upload;

use http\Exception\UnexpectedValueException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

/**
 * Class ImageUploader.
 *
 * This class manages image upload provided by a form.
 */
class ImageUploader
{
    /**
     * Define expected image formats.
     */
    const ALLOWED_IMAGE_FORMATS = ['jpeg', 'jpg', 'png', 'gif'];

    /**
     * Define a key to retrieve avatars upload directory.
     */
    const AVATAR_DIRECTORY_KEY = 'avatarImages';

    /**
     * @var FlashBagInterface
     */
    private $flashBag;

    /*
     * @var ParameterBagInterface
     */
    private $parameterBag;

    /*
     * @var array
     */
    private $uploadDirectory;

    /**
     * ImageUploader constructor.
     *
     * @param ParameterBagInterface $parameterBag
     * @param FlashBagInterface     $flashBag
     */
    public function __construct(ParameterBagInterface $parameterBag, FlashBagInterface $flashBag)
    {
        $this->parameterBag = $parameterBag;
        // TODO: add trick directory later
        $this->uploadDirectory = [
            self::AVATAR_DIRECTORY_KEY => $this->parameterBag->get('app_avatar_upload_directory')
        ];
        $this->flashBag = $flashBag;

    }

    /**
     * Crop an image, resize it and rename it with expected media type format.
     *
     * @param string $uploadDirectory the destination folder
     * @param File   $uploadedImage
     * @param array  $parameters
     *
     * @return File an image file
     *
     * @throws \Exception
     *
     * @see https://stackoverflow.com/questions/9257505/using-braces-with-dynamic-variable-names-in-php
     * to possibly use variables in curly braces instead of call_user_function() or call_user_function_array() functions
     */
    private function createCroppedAndResizedImages(string $uploadDirectory, File $uploadedImage, array $parameters) : File
    {
        // Decode crop data to get a stdClass instance
        $cropDataObject = json_decode($parameters['cropJSONData'])[0];
        // Get a resource based on uploaded image extension with particular "jpg" case
        $imageType = 'jpg' === $parameters['extension'] ? 'jpeg' : $parameters['extension'];
        $uploadedImageResource = \call_user_func('imagecreatefrom' . $imageType, $uploadedImage->getPathname());
        // Get cropped resource
        $croppedImageResource = imagecrop($uploadedImageResource, ['x' => $cropDataObject->x, 'y' => $cropDataObject->y, 'width' => $cropDataObject->width, 'height' => $cropDataObject->height]);
        if (false === $croppedImageResource) {
            throw new \UnexpectedValueException('Crop operation failed due to unexpected value(s) in parameters!');
        }
        $uploadedFileInfos = ['baseUploadDirectory' => $uploadDirectory, 'uploadedFileName' => $uploadedImage->getFilename()];
        $finalImageFile = $this->resizeCroppedResourceAsExpected($croppedImageResource, $parameters, $uploadedFileInfos);
        // Free up memory by destroying resources
        imagedestroy($uploadedImageResource);
        imagedestroy($croppedImageResource);
        return $finalImageFile;
    }

    /**
     * Generate the final image file after resource operation(s).
     *
     * @param        $newImageToCreateResource
     * @param string $imageType
     * @param string $newImageNamePath
     *
     * @return void
     */
    private function generateImage($newImageToCreateResource, string $imageType, string $newImageNamePath) : void
    {
        // Use the highest possible compression quality
        $qualityParameters = ['png' => 0, 'jpeg' => 100, 'jpg' => 100];
        $arguments = [$newImageToCreateResource, $newImageNamePath];
        if (isset($qualityParameters[$imageType])) array_push($arguments, $qualityParameters[$imageType]);
        // Call the adapted function which depends on image type (imagepng(), imagegif(), imagejpeg())
        \call_user_func_array('image' . $imageType, $arguments);
    }

    /**
     * Get upload directory path used to move a file.
     *
     * @param string $key
     *
     * @return string
     *
     * @throws \Exception
     */
    public function getUploadDirectory(string $key) : string
    {
        if (!isset($this->uploadDirectory[$key])) {
            throw new \InvalidArgumentException('Upload directory key is unknown!');
        }
        return $this->uploadDirectory[$key];
    }

    /**
     * Rename image as regard its media type dimensions definition.
     *
     * @param array $parameters
     * @param string $fileName
     *
     * @return string
     */
    private function renameImageAsExpectedInMediaType(array $parameters, string $fileName) : string
    {
        $width = $parameters['resizeFormat']['width'];
        $height = $parameters['resizeFormat']['height'];
        // Change included format in name
        preg_match('/^.*-(\d{2,}x\d{2,})\.[a-z]{3,4}$/', $fileName, $matches, PREG_UNMATCHED_AS_NULL);
        $newImageName = preg_replace('/' . $matches[1] . '/',  $width . 'x' . $height, $fileName);
        return $newImageName;
    }

    /**
     * Resize a user cropped image as regards its media type dimensions definition.
     *
     * @param $croppedImageResource
     * @param array $parameters
     * @param array $uploadedFileInfos
     *
     * @return File an image file
     *
     * @throws \Exception
     */
    private function resizeCroppedResourceAsExpected($croppedImageResource, array $parameters, array $uploadedFileInfos) : File
    {
        if (!\is_resource($croppedImageResource)) {
            throw new \InvalidArgumentException('A valid cropped resource is expected to be able to resize uploaded image!');
        }
        $imageType = $parameters['extension'];
        // Expected final format (dimensions)
        $width = $parameters['resizeFormat']['width'];
        $height = $parameters['resizeFormat']['height'];
        $newImageToCreateResource = imagecreatetruecolor($width, $height);
        // Preserve transparency
        if ('gif' === $imageType || 'png' === $imageType){
            imagecolortransparent($newImageToCreateResource, imagecolorallocatealpha($newImageToCreateResource, 0, 0, 0, 127));
            imagealphablending($newImageToCreateResource, false);
            imagesavealpha($newImageToCreateResource, true);
        }
        // Get scaled (resized) resource with ratio to create future image with expected media type dimensions
        $scaledImageResource = imagescale($croppedImageResource , $width, $height);
        imagecopyresampled($newImageToCreateResource, $scaledImageResource, 0, 0, 0, 0, $width, $height, $width, $height);
        // Replace dimensions format in new resized image name as expected in media type definition
        $newImageName = $this->renameImageAsExpectedInMediaType($parameters, $uploadedFileInfos['uploadedFileName']);
        $newImageNamePath = $uploadedFileInfos['baseUploadDirectory'] . '/' . $newImageName;
        // Generate final image file by calling the appropriate function as regards image extension
        $this->generateImage($newImageToCreateResource, $imageType, $newImageNamePath);
        // Free up memory by destroying scaled (resized) and final image resources
        imagedestroy($scaledImageResource);
        imagedestroy($newImageToCreateResource);
        return new File($newImageNamePath);
    }

    /**
     * Upload a file.
     *
     * @param UploadedFile $file
     * @param string       $key a key which indicates a chosen upload directory
     * @param array        $parameters an array of uploaded file parameters
     * @param bool         $isCropped
     *
     * @return string|null
     *
     * @throws \Exception
     */
    public function upload(UploadedFile $file, string $key, array $parameters, bool $isCropped = false) : ?string
    {
        if (!isset($this->uploadDirectory[$key])) {
            throw new \InvalidArgumentException('Chosen upload directory is unknown!');
        }
        // Get the label to concatenate with definitive file name
        $label = $parameters['identifierName'];
        // Get dimensions format which will be added to file name
        $format = $parameters['dimensionsFormat'];
        $definedFileName = $label . '-' . hash('crc32', uniqid()) . '-' . $format;
        $fileName = $definedFileName . '.' . $file->guessExtension();
        $uploadDirectory = $this->uploadDirectory[$key];
        try {
            $uploadedFile = $file->move($uploadDirectory, $fileName);
            $finalImage = $this->createCroppedAndResizedImages($uploadDirectory, $uploadedFile, $parameters);
            $finalImageNameWithoutExtension = str_replace('.' . $finalImage->getExtension(), '', $finalImage->getFilename());
            // Remove physically uploaded image to keep only resized final image
            @unlink($uploadedFile->getPathname());
            // Result will be stored in database
            return $finalImageNameWithoutExtension;
        } catch (FileException $e) {
            return null;
        }
    }
}
