<?php

declare(strict_types = 1);

namespace App\Service\Medias\Upload;

use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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
    use LoggerAwareTrait;

    /**
     * Define expected image formats.
     */
    const ALLOWED_IMAGE_FORMATS = ['jpeg', 'jpg', 'png', 'gif'];

    /**
     * Define a key to retrieve avatar images upload directory.
     */
    const AVATAR_IMAGE_DIRECTORY_KEY = 'avatarImages';

    /**
     * Define a key to retrieve avatar temporary images upload directory.
     */
    const AVATAR_TEMP_IMAGE_DIRECTORY_KEY = 'avatarTempImages';

    /**
     * Define a name to retrieve temporary directory.
     */
    const TEMPORARY_DIRECTORY_NAME = 'temporary';

    /**
     * Define a key to retrieve trick images upload directory.
     */
    const TRICK_IMAGE_DIRECTORY_KEY = 'trickImages';

    /**
     * Define a key to retrieve trick temporary images upload directory.
     */
    const TRICK_TEMP_IMAGE_DIRECTORY_KEY = 'trickTempImages';

    /*
     * @var ParameterBagInterface
     */
    private $parameterBag;

    /*
     * @var array
     */
    private $uploadDirectories;

    /**
     * ImageUploader constructor.
     *
     * @param ParameterBagInterface $parameterBag
     * @param LoggerInterface       $logger
     */
    public function __construct(ParameterBagInterface $parameterBag, LoggerInterface $logger)
    {
        $this->parameterBag = $parameterBag;
        $avatarImageDirectory = $this->parameterBag->get('app_avatar_image_upload_directory');
        $trickImageDirectory = $this->parameterBag->get('app_trick_image_upload_directory');
        $this->uploadDirectories = [
            self::AVATAR_IMAGE_DIRECTORY_KEY      => $avatarImageDirectory,
            self::AVATAR_TEMP_IMAGE_DIRECTORY_KEY => $avatarImageDirectory . '/' . self::TEMPORARY_DIRECTORY_NAME,
            self::TRICK_IMAGE_DIRECTORY_KEY       => $trickImageDirectory,
            self::TRICK_TEMP_IMAGE_DIRECTORY_KEY  => $trickImageDirectory . '/' . self::TEMPORARY_DIRECTORY_NAME
        ];
        // Use a PSR3 logger
        $this->setLogger($logger);
    }

    /**
     * Check if a file was uploaded on server.
     *
     * @param string $fileName           a base filename or regex pattern
     * @param string $uploadDirectoryKey
     * @param bool   $isTemporary        a file to find in temporary sub directory
     * @param bool   $isRegExMode        a regex pattern is used as filename
     *
     * @return array|\SplFileInfo[]|null
     *
     * @throws \Exception
     */
    public function checkFileUploadOnServer(
        string $fileName,
        string $uploadDirectoryKey = null,
        bool $isTemporary = false,
        bool $isRegExMode = false
    ) : ?array
    {
        $isDirectory = !\is_null($uploadDirectoryKey) ? true : false;
        $directories = $this->getUploadDirectories();
        // Upload directory is set!
        if ($isDirectory) {
            $temporarySubDirectory = $isTemporary ? '/' . ImageUploader::TEMPORARY_DIRECTORY_NAME : '';
            $uploadDirectory = $this->getUploadDirectory($uploadDirectoryKey);
            // Adjust more precisely upload directories
            $directories = [$uploadDirectory . $temporarySubDirectory];
            // Regex mode is also off, so return directly check result!
            if (!$isRegExMode) {
                // Check directly file path name
                $filePathName = $uploadDirectory . $temporarySubDirectory . '/' . $fileName;
                return \is_file($filePathName) ? [new \SplFileInfo($filePathName)] : null;
            }
        }
        // Prepare an array to store 0, 1 or more results if regex mode is on!
        $filesArray = [];
        // Escape filename for regex use
        $expression = preg_quote($fileName, '/');
        // Use filename as pattern
        $pattern = "/{$expression}/";
        // Loop on upload directories
        foreach ($directories as $directory) {
            // Avoid warning error (and use of "@" to silent it which is a bad practice!) by using try catch
            // if a directory does not exist (e.g. a temporary deleted directory, wrong directory due to misconfiguration...)!
            try {
                if (is_dir($directory)) {
                    $handle = opendir($directory);
                    while (false !== ($file = readdir($handle))) {
                        // Check file existence
                        if (preg_match($pattern, $file)) {
                            $filePathName = $directory . '/' . $file;
                            if ($isRegExMode) {
                                $filesArray[] = new \SplFileInfo($filePathName);
                                continue;
                            }
                            // Pattern is a complete filename without extension, so only 1 result is expected!
                            return [new \SplFileInfo($filePathName)];
                        }
                    }
                    closedir($handle);
                }
            } catch (\Throwable $exception) {
                // Store process error
                $this->logger->error(
                    sprintf(
                        "[trace app snowTricks] ImageUploader/checkFileUploadOnServer => exception: %s",
                        $exception->getMessage()
                    )
                );
                continue;
            }
        }
        // Pattern is a part of filename, so 1 or more results can match!
        return !empty($filesArray) ? $filesArray : null;
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
        // IMPORTANT! At this time, JSON data contains only one crop result,
        // but this "results" array could be useful for multiple uploads later!
        $cropData = json_decode($parameters['cropJSONData']);
        $cropDataObject = $cropData->results[0];
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
     * Convert an image to base64 encoding.
     *
     * @param string $imagePath
     *
     * @return string
     *
     * @throws \Exception
     */
    public function encodeImageWithBase64(string $imagePath) : string
    {
        if (!\file_exists($imagePath)) {
            throw new \RuntimeException("Image to encode with DataURI was not found!");
        }
        // Read image path, convert it to base64 encoding
        $imageData = base64_encode(file_get_contents($imagePath));
        // Format the image source with the expected format: data:{mime};base64,{data}
        $source = 'data: '. mime_content_type($imagePath) . ';base64,' . $imageData;
        return $source;
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
        if (!isset($this->uploadDirectories[$key])) {
            throw new \InvalidArgumentException('Upload directory key is unknown!');
        }
        return $this->uploadDirectories[$key];
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
        return $this->uploadDirectories;
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
            preg_match('/^.*-([a-z0-9]*)-\d+x\d+(\.[a-z]{3,4})?$/', $fileName, $matches, PREG_UNMATCHED_AS_NULL);
            // Replace previous hash in group 1 by new hash
            $newImageName = preg_replace('/' . $matches[1] . '/', $newHash, $fileName);
        }
        // Change included format in name (Initial format is replaced with expected resize format!)
        $width = $parameters['resizeFormat']['width'];
        $height = $parameters['resizeFormat']['height'];
        preg_match('/^.*-(\d+x\d+)(\.[a-z]{3,4})?$/', $newImageName, $matches, PREG_UNMATCHED_AS_NULL);
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
     * @param bool   $isTemporaryImageSource
     *
     * @return \SplFileInfo|null a resized image SplFileInfo instance
     *
     * @throws \Exception
     */
    public function resizeNewImage(string $key, array $parameters, bool $isTemporaryImageSource = false) : ?\SplFileInfo
    {
        if (!isset($this->uploadDirectories[$key])) {
            throw new \InvalidArgumentException('Chosen upload directory is unknown!');
        }
        $sourceImageName = $parameters['identifierName'];
        $sourceImageExtension = $parameters['extension'];
        // Get a image File instance based on image source Image entity
        $imageDirectory = $this->uploadDirectories[$key];
        // Get a possible used temporary upload sub directory
        $imageTempSubDirectory = $isTemporaryImageSource ? '/' . self::TEMPORARY_DIRECTORY_NAME : '';
        // Get image name and path
        $imageName = $sourceImageName . '.' . $sourceImageExtension;
        $imagePath = $imageDirectory . $imageTempSubDirectory . '/' . $imageName;
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
     * @param bool         $isTemporary
     *
     * @return \SplFileInfo|null a SplFileInfo instance based on uploaded file which can be handled to be cropped
     *
     * @throws \Exception
     */
    public function upload(
        UploadedFile $file,
        string $key,
        array $parameters,
        bool $isCropped = false,
        bool $isTemporary = false
    ) : ?\SplFileInfo
    {
        if (!isset($this->uploadDirectories[$key])) {
            throw new \InvalidArgumentException('Chosen upload directory is unknown!');
        }
        // Create a directory if it does not exist and add full permissions
        if (!is_dir($this->uploadDirectories[$key])) {
            mkdir($this->uploadDirectories[$key], 0755); // default permissions
        }
        // Get the label (original name turned into lower-cased slug) to concatenate with definitive file name
        $label = $parameters['identifierName'];
        // Get dimensions format which will be added to file name
        $format = $parameters['dimensionsFormat'];
        $definedFileName = $label . '-' . hash('crc32', uniqid()) . '-' . $format;
        $fileName = $definedFileName . '.' . $file->guessExtension();
        $uploadDirectory = $this->uploadDirectories[$key];
        // Get a possible used temporary upload sub directory
        $imageTempSubDirectory = $isTemporary ? '/' . self::TEMPORARY_DIRECTORY_NAME : '';
        $uploadDirectory = $uploadDirectory . $imageTempSubDirectory;
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
