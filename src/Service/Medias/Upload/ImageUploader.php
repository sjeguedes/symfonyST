<?php

declare(strict_types = 1);

namespace App\Service\Medias\Upload;

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
     * Define a key to retrieve avatar images upload directory.
     */
    const AVATAR_IMAGE_DIRECTORY_KEY = 'avatarImages';

    /**
     * Define a key to retrieve trick images upload directory.
     */
    const TRICK_IMAGE_DIRECTORY_KEY = 'trickImages';

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
        $this->uploadDirectory = [
            self::AVATAR_IMAGE_DIRECTORY_KEY => $this->parameterBag->get('app_avatar_image_upload_directory'),
            self::TRICK_IMAGE_DIRECTORY_KEY  => $this->parameterBag->get('app_trick_image_upload_directory')
        ];
        $this->flashBag = $flashBag;
    }

    /**
     * Check if a file was uploaded on server;
     *
     * @param string $fileName
     * @param string $uploadDirectoryKey
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function checkFileUploadOnServer(string $fileName, string $uploadDirectoryKey = null) : bool
    {
        // Loop on upload directories
        if (\is_null($uploadDirectoryKey)) {
            $isFileFound = false;
            // Escape filename for regex use
            $fileName = preg_quote($fileName, '/');
            foreach ($this->getUploadDirectories() as $directory) {
                if ($handle = opendir($directory)) {
                    while (false !== ($file = readdir($handle))) {
                        // Check file existence
                        if (preg_match("/{$fileName}/", $file)) {
                            $isFileFound = true;
                            break;
                        }
                    }
                    closedir($handle);
                }
            }
            return $isFileFound;
        }
        // Check directly file path name
        return is_file($this->getUploadDirectory($uploadDirectoryKey) . '/' . $fileName);
    }

    /**
     * Crop an image, resize it and rename it with expected media type format.
     *
     * @param string $uploadDirectory the destination folder
     * @param File   $uploadedImage
     * @param array  $parameters
     *
     * @return File|null an image file
     *
     * @throws \Exception
     *
     * @see https://stackoverflow.com/questions/9257505/using-braces-with-dynamic-variable-names-in-php
     * to possibly use variables in curly braces instead of call_user_function() or call_user_function_array() functions
     */
    private function createCroppedAndResizedImage(string $uploadDirectory, File $uploadedImage, array $parameters) : ?File
    {
        // Decode crop data to get a stdClass instance
        $cropDataObject = json_decode($parameters['cropJSONData'])[0];
        // Get a resource based on uploaded image extension with particular "jpg" case
        $imageType = 'jpg' === $parameters['extension'] ? 'jpeg' : $parameters['extension'];
        $uploadedImageResource = \call_user_func('imagecreatefrom' . $imageType, $uploadedImage->getPathname());
        // Get cropped resource
        $croppedImageResource = imagecrop(
            $uploadedImageResource,
            ['x' => $cropDataObject->x, 'y' => $cropDataObject->y, 'width' => $cropDataObject->width, 'height' => $cropDataObject->height]
        );
        if (!$croppedImageResource) {
            throw new \UnexpectedValueException('Crop operation failed due to unexpected value(s) in parameters!');
        }
        $uploadedFileInfos = ['baseUploadDirectory' => $uploadDirectory, 'uploadedFileName' => $uploadedImage->getFilename()];
        $finalImageFile = $this->resizeCroppedResourceAsExpected($croppedImageResource, $parameters, $uploadedFileInfos);
        if (\is_null($finalImageFile)) {
            return null;
        }
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
     * @return bool
     */
    private function generateImage($newImageToCreateResource, string $imageType, string $newImageNamePath) : bool
    {
        // Define the compression quality
        // png: -1 default compiled in zlib library, 0 no compression, 1-9 where 9 is the maximum compression
        //jp(e)g: 80% (0-100)
        $qualityParameters = ['png' => 2, 'jpeg' => 80, 'jpg' => 80];
        $arguments = [$newImageToCreateResource, $newImageNamePath];
        if (isset($qualityParameters[$imageType])) array_push($arguments, $qualityParameters[$imageType]);
        // Call the adapted function which depends on image type (imagepng(), imagegif(), imagejpeg())
        $result = \call_user_func_array('image' . $imageType, $arguments);
        if (!$result) {
            return false;
        }
        return true;
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
     * Get all upload directories path used to move a file.
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getUploadDirectories() : array
    {
        return [
            self::AVATAR_IMAGE_DIRECTORY_KEY => $this->getUploadDirectory(self::AVATAR_IMAGE_DIRECTORY_KEY),
            self::TRICK_IMAGE_DIRECTORY_KEY  => $this->getUploadDirectory(self::TRICK_IMAGE_DIRECTORY_KEY)
        ];
    }

    /**
     * Rename image as regard its media type dimensions definition.
     *
     * @param array  $parameters
     * @param string $fileName
     *
     * @return string
     */
    private function renameImageAsExpectedInMediaType(array $parameters, string $fileName) : string
    {
        // WARNING: Change identifier with hash to avoid identical name between uploaded file name and final image name
        // when they have the same dimensions!
        $newHash = hash('crc32', uniqid());
        preg_match('/^.*-([a-z0-9]*)-\d{2,}x\d{2,}\.[a-z]{3,4}$/', $fileName, $matches, PREG_UNMATCHED_AS_NULL);
        $newImageName = preg_replace('/' . $matches[1] . '/',  $newHash, $fileName);
        // Change included format in name (Initial format is replaced with expected resize format!)
        $width = $parameters['resizeFormat']['width'];
        $height = $parameters['resizeFormat']['height'];
        preg_match('/^.*-(\d{2,}x\d{2,})\.[a-z]{3,4}$/', $newImageName, $matches, PREG_UNMATCHED_AS_NULL);
        $newImageName = preg_replace('/' . $matches[1] . '/',  $width . 'x' . $height, $newImageName);
        return $newImageName;
    }

    /**
     * Resize a user cropped image as regards its media type dimensions definition.
     *
     * @param       $croppedImageResource
     * @param array $parameters
     * @param array $uploadedFileInfos
     *
     * @return File|null an image file
     *
     * @throws \Exception
     */
    private function resizeCroppedResourceAsExpected($croppedImageResource, array $parameters, array $uploadedFileInfos) : ?File
    {
        if (!\is_resource($croppedImageResource)) {
            throw new \InvalidArgumentException('A valid cropped resource is expected to be able to resize uploaded image!');
        }
        $imageType = $parameters['extension'];
        // Get expected final format (dimensions)
        $width = $parameters['resizeFormat']['width'];
        $height = $parameters['resizeFormat']['height'];
        // Create a image resource
        $newImageToCreateResource = imagecreatetruecolor($width, $height);
        // Preserve transparency
        if ('gif' === $imageType || 'png' === $imageType) {
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
        $isImageGenerated = $this->generateImage($newImageToCreateResource, $imageType, $newImageNamePath);
        if (!$isImageGenerated) {
            return null;
        }
        // Free up memory by destroying scaled (resized) and final image resources
        imagedestroy($scaledImageResource);
        imagedestroy($newImageToCreateResource);
        return new File($newImageNamePath, true);
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
        // Create a directory if it does not exist and add full permissions
        if (!is_dir($this->uploadDirectory[$key])) {
            mkdir($this->uploadDirectory[$key], 0777);
        }
        // Get the label (original name turned into lower-cased slug) to concatenate with definitive file name
        $label = $parameters['identifierName'];
        // Get dimensions format which will be added to file name
        $format = $parameters['dimensionsFormat'];
        $definedFileName = $label . '-' . hash('crc32', uniqid()) . '-' . $format;
        $fileName = $definedFileName . '.' . $file->guessExtension();
        $uploadDirectory = $this->uploadDirectory[$key];
        try {
            // Upload file on server
            $uploadedFile = $file->move($uploadDirectory, $fileName);
            // Crop activation case
            if ($isCropped) {
                $finalImage = $this->createCroppedAndResizedImage($uploadDirectory, $uploadedFile, $parameters);
                if (\is_null($finalImage)) {
                    return null;
                }
                $finalImageNameWithoutExtension = str_replace('.' . $finalImage->getExtension(), '', $finalImage->getFilename());
                // Remove physically uploaded image to keep only resized final image after crop
                @unlink($uploadedFile->getPathname());
            // No crop
            } else {
                $finalImageNameWithoutExtension = str_replace('.' . $uploadedFile->getExtension(), '', $uploadedFile->getFilename());
            }
            // Result will be stored in database
            return $finalImageNameWithoutExtension;
        } catch (FileException $e) {
            return null;
        }
    }
}
