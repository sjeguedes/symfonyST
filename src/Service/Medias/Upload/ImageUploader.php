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
 * This class manages image upload provided by a form and handles it (crop, resize...) if necessary.
 *
 * Please note this package would be better for image handling (resize, crop...):
 * @see https://packagist.org/packages/intervention/image
 * @see http://image.intervention.io/use/basics
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
     * Check if a file was uploaded on server.
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
        return \is_file($this->getUploadDirectory($uploadDirectoryKey) . '/' . $fileName);
    }

    /**
     * Crop an image based on an uploaded file, resize it and rename it with expected media type format.
     *
     * @param string $uploadDirectory the destination folder
     * @param File   $uploadedImage
     * @param array  $parameters
     *
     * @return File|null an image file
     *
     * Use variables in curly braces instead of call_user_function() or call_user_function_array() functions:
     * @see https://stackoverflow.com/questions/9257505/using-braces-with-dynamic-variable-names-in-php
     *
     * @throws \Exception
     */
    private function createCroppedAndResizedImage(string $uploadDirectory, File $uploadedImage, array $parameters) : ?File
    {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('Image can not be handled: "gd" extension is not installed on server!');
        }
        // Decode crop data to get a stdClass instance
        $cropDataObject = json_decode($parameters['cropJSONData'])[0];
        // Get a resource based on uploaded image extension with particular "jpg" case
        $imageType = 'jpg' === $parameters['extension'] ? 'jpeg' : $parameters['extension'];
        $function = "imagecreatefrom{$imageType}";
        $uploadedImageResource = \call_user_func($function, $uploadedImage->getPathname());
        // Get cropped resource
        $croppedImageResource = imagecrop(
            $uploadedImageResource,
            ['x' => $cropDataObject->x, 'y' => $cropDataObject->y, 'width' => $cropDataObject->width, 'height' => $cropDataObject->height]
        );
        if (!$croppedImageResource) {
            throw new \UnexpectedValueException('Crop operation failed due to unexpected value(s) in parameters!');
        }
        $uploadedFileInfos = ['imageDirectory' => $uploadDirectory, 'imageFileName' => $uploadedImage->getFilename()];
        $finalImageFile = $this->resizeImageResourceAsExpected($croppedImageResource, $parameters, $uploadedFileInfos);
        if (\is_null($finalImageFile)) {
            return null;
        }
        // Free up memory by destroying resources
        imagedestroy($uploadedImageResource);
        imagedestroy($croppedImageResource);
        return $finalImageFile;
    }

    /**
     * Resize an image based on image source file, and rename it with expected media type format.
     *
     * @param string $imageDirectory
     * @param File   $imageSourceFile
     * @param array  $parameters
     *
     * @return File|null
     *
     * @throws \Exception
     */
    private function createResizedImage(string $imageDirectory, File $imageSourceFile, array $parameters) : ?File
    {
        if (!extension_loaded('gd')) {
            throw new \RuntimeException('Image can not be handled: "gd" extension is not installed on server!');
        }
        // Get a resource based on uploaded image extension with particular "jpg" case
        $imageType = 'jpg' === $parameters['extension'] ? 'jpeg' : $parameters['extension'];
        $function = "imagecreatefrom{$imageType}";
        $imageResource = \call_user_func($function, $imageSourceFile->getPathname());

        $fileInfos = ['imageDirectory' => $imageDirectory, 'imageFileName' => $imageSourceFile->getFilename()];
        $finalImageFile = $this->resizeImageResourceAsExpected($imageResource, $parameters, $fileInfos);
        if (\is_null($finalImageFile)) {
            return null;
        }
        // Free up memory by destroying resources
        imagedestroy($imageResource);
        return $finalImageFile;
    }

    /**
     * Generate the final image file after resource operation(s).
     *
     * @param resource $newImageToCreateResource a "gd" resource to handle
     * @param string   $imageType
     * @param string   $newImageNamePath
     *
     * Change gif quality with php:
     * @see https://stackoverflow.com/questions/3708947/compress-gif-images-quality-in-php
     *
     * @return bool
     */
    private function generateImage($newImageToCreateResource, string $imageType, string $newImageNamePath) : bool
    {
        // Define the compression quality
        // png: -1 default compiled in zlib library, 0 no compression, 1-9 where 9 is the maximum compression
        //jp(e)g: 80% (0-100)
        // gif: no change is used here.
        $qualityParameters = ['png' => 2, 'jpeg' => 80, 'jpg' => 80];
        $arguments = [$newImageToCreateResource, $newImageNamePath];
        if (isset($qualityParameters[$imageType])) array_push($arguments, $qualityParameters[$imageType]);
        // Call the adapted function which depends on image type (imagepng(), imagegif(), imagejpeg())
        $function = "image{$imageType}";
        $result = \call_user_func_array($function, $arguments);
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
        $newImageName = $fileName;
        $isImageNameHashChanged = $parameters['isImageNameHashChanged'];
        // WARNING: Change file name hash to avoid identical names
        // between uploaded file name and immediately created final image name when they have the same dimensions!
        if ($isImageNameHashChanged) {
            $newHash = hash('crc32', uniqid());
            preg_match('/^.*-([a-z0-9]*)-\d{2,}x\d{2,}(\.[a-z]{3,4})?$/', $fileName, $matches, PREG_UNMATCHED_AS_NULL);
            // Replace previous hash in group 1 by new hash
            $newImageName = preg_replace('/' . $matches[1] . '/', $newHash, $fileName);
        }
        // Change included format in name (Initial format is replaced with expected resize format!)
        $width = $parameters['resizeFormat']['width'];
        $height = $parameters['resizeFormat']['height'];
        preg_match('/^.*-(\d{2,}x\d{2,})(\.[a-z]{3,4})?$/', $newImageName, $matches, PREG_UNMATCHED_AS_NULL);
        // Replace previous dimensions ("with"x"height") in group 1 by new corresponding dimensions
        $newImageName = preg_replace('/' . $matches[1] . '/',  $width . 'x' . $height, $newImageName);
        return $newImageName;
    }

    /**
     * Resize an image resource as expected by preserving transparency if needed.
     *
     * Please take into account ratio is not checked and considered correct due to crop process!
     * Sadly, there is no type hint for a "gd" image resource!
     *
     * @param resource $imageResource a "gd" image resource to handle
     * @param array    $parameters
     *
     * @see https://www.php.net/manual/en/function.imagecolortransparent.php
     * 2 very old but useful posts:
     * @see https://stackoverflow.com/questions/6819822/how-to-allow-upload-transparent-gif-or-png-with-php
     * @see http://www.akemapa.com/2008/07/10/php-gd-resize-transparent-image-png-gif/comment-page-2/
     * Check png transparency:
     * @see https://stackoverflow.com/questions/5495275/how-to-check-if-an-image-has-transparency-using-gd
     *
     * @return resource|null a "gd" image resource
     */
    private function resizeImageResourceWithTransparency($imageResource, array $parameters) // No available return type hint for resource
    {
        $imageType = $parameters['extension'];
        // Get expected final format (dimensions)
        $width = $parameters['resizeFormat']['width'];
        $height = $parameters['resizeFormat']['height'];
        // Create a image resource
        $newImageToCreateResource = imagecreatetruecolor($width, $height);
        // Preserve transparency:
        // Concern transparent "gif" resource
        if ('gif' === $imageType && imagecolortransparent($imageResource) >= 0) {
            $transparentIndex = imagecolortransparent($imageResource);
            imagepalettecopy($imageResource, $newImageToCreateResource);
            imagefill($newImageToCreateResource, 0, 0, $transparentIndex);
            imagecolortransparent($newImageToCreateResource, $transparentIndex);
            imagetruecolortopalette($newImageToCreateResource, true, 256); // Maximum quality
        // Concern transparent "png" resource
        }
        if ('png' === $imageType) {
            imagealphablending($newImageToCreateResource, false);
            imagesavealpha($newImageToCreateResource,true);
            $transparent = imagecolorallocatealpha($newImageToCreateResource, 255, 255, 255, 127);
            imagefilledrectangle($newImageToCreateResource, 0, 0, $width, $height, $transparent);
        }
        // Concerns all cases including "jp(e)g" resource!:
        // Get scaled (resized) resource with the same ratio to create future image with expected media type dimension
        $isSuccess = imagecopyresampled($newImageToCreateResource, $imageResource, 0, 0, 0, 0, $width, $height, imagesx($imageResource), imagesy($imageResource));
        // Resource handling is a failure!
        if (!$isSuccess) {
            return null;
        }
        return $newImageToCreateResource;
    }

    /**
     * Resize an image as regards its media type dimensions definition.
     *
     * @param resource $imageResource a "gd" image resource to handle
     * @param array    $parameters
     * @param array    $fileInfos
     *
     * @return File|null an image file
     *
     * @throws \Exception
     */
    private function resizeImageResourceAsExpected($imageResource, array $parameters, array $fileInfos) : ?File
    {
        if (!\is_resource($imageResource)) {
            throw new \InvalidArgumentException('A valid image resource is expected to be able to resize uploaded image!');
        }
        // Resize image as expected by keeping transparency
        $newImageToCreateResource = $this->resizeImageResourceWithTransparency($imageResource, $parameters);
        if (!\is_null($newImageToCreateResource)) {
            // Replace dimensions format in new resized image name as expected in media type definition
            $newImageName = $this->renameImageAsExpectedInMediaType($parameters, $fileInfos['imageFileName']);
            $newImageNamePath = $fileInfos['imageDirectory'] . '/' . $newImageName;
            // Generate final image file by calling the appropriate function as regards image extension
            $imageType = $parameters['extension'];
            $isImageGenerated = $this->generateImage($newImageToCreateResource, $imageType, $newImageNamePath);
            // Free up memory by destroying final image resource
            imagedestroy($newImageToCreateResource);
            if (!$isImageGenerated) {
                return null;
            }
            return new File($newImageNamePath, true);
        }
        return null;
    }

    /**
     * Resize a new image with expected parameters based on a source image Image data parameters.
     *
     * @param string $key
     * @param array  $parameters
     *
     * @return \SplFileInfo|null a resized image SplFileInfo instance
     *
     * @throws \Exception
     */
    public function resizeNewImage(string $key, array $parameters) : ?\SplFileInfo
    {
        if (!isset($this->uploadDirectory[$key])) {
            throw new \InvalidArgumentException('Chosen upload directory is unknown!');
        }
        $sourceImageName = $parameters['identifierName'];
        $sourceImageExtension = $parameters['extension'];
        // Get a image File instance based on image source Image entity
        $imageDirectory = $this->uploadDirectory[$key];
        $imageName = $sourceImageName . '.' . $sourceImageExtension;
        $imagePath = $imageDirectory . '/' . $imageName;
        $sourceImageFile = new File($imagePath,true);
        // Get a new resized image \SplFileInfo|File instance
        $finalImage = $this->createResizedImage($imageDirectory, $sourceImageFile, $parameters);
        if (\is_null($finalImage)) {
            return null;
        }
        // Final image data will be stored in database
        return $finalImage;
    }

    /**
     * Upload a file and call crop/resize action if needed.
     *
     * Please note first uploaded image is not kept if crop/resize operations are used after.
     *
     * @param UploadedFile $file
     * @param string       $key        a key which indicates a chosen upload directory
     * @param array        $parameters an array of uploaded file parameters
     * @param bool         $isCropped
     *
     * @return \SplFileInfo|null a SplFileInfo instance based on uploaded file which can be handled to be cropped
     *
     * @throws \Exception
     */
    public function upload(UploadedFile $file, string $key, array $parameters, bool $isCropped = false) : ?\SplFileInfo
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
                // Remove physically uploaded image to keep only resized final image after crop
                unlink($uploadedFile->getPathname());
            // No crop
            } else {
                $finalImage = $uploadedFile;
            }
            // Final image data will be stored in database
            return $finalImage;
        } catch (FileException $e) {
            return null;
        }
    }
}
