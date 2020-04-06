<?php

declare(strict_types = 1);

namespace App\Domain\ServiceLayer;

use App\Domain\DTO\UpdateProfileAvatarDTO;
use App\Domain\DTOToEmbed\ImageToCropDTO;
use App\Domain\Entity\Image;
use App\Domain\Entity\MediaType;
use App\Domain\Entity\User;
use App\Domain\Repository\ImageRepository;
use App\Service\Medias\Upload\ImageUploader;
use App\Utils\Traits\StringHelperTrait;
use App\Utils\Traits\UserHandlingHelperTrait;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\File;

/**
 * Class ImageManager.
 *
 * Manage images to handle, and retrieve as a "service layer".
 */
class ImageManager
{
    use LoggerAwareTrait;
    use StringHelperTrait;
    use UserHandlingHelperTrait;

    /**
     * Define a default alternative text for image which is directly uploaded (e.g. not attached to trick entity) on server.
     */
    const DEFAULT_IMAGE_DESCRIPTION_TEXT = 'No image description is available.';

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var ImageUploader
     */
    private $imageUploader;

    /**
     * @var MediaManager
     */
    private $mediaManager;

    /**
     * @var MediaTypeManager
     */
    private $mediaTypeManager;

    /**
     * @var ImageRepository
     */
    private $repository;

    /**
     * ImageManager constructor.
     *
     * @param EntityManagerInterface $entityManager,
     * @param ImageUploader          $imageUploader
     * @param ImageRepository        $repository
     * @param MediaManager           $mediaManager
     * @param MediaTypeManager       $mediaTypeManager
     * @param LoggerInterface        $logger
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        ImageUploader $imageUploader,
        ImageRepository $repository,
        MediaManager $mediaManager,
        MediaTypeManager $mediaTypeManager,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->imageUploader = $imageUploader;
        $this->mediaManager = $mediaManager;
        $this->mediaTypeManager = $mediaTypeManager;
        $this->repository = $repository;
        $this->setLogger($logger);
    }


    /**
     * Add and save (persist) image in database.
     *
     * @param Image $image
     *
     * @return Image
     */
    private function addAndSaveImage(Image $image) : Image
    {
        $this->getEntityManager()->persist($image);
        $this->getEntityManager()->flush();
        return $image;
    }

    /**
     * Create trick image file with its parameters with two different ways:
     * - by uploading it directly on server without complete form validation,
     * - by using an existing image (e.g. big format or a particular image) to create other formats based on it
     *
     * Please note image is published by default, without administrator control in actual app configuration!
     *
     * @param ImageToCropDTO $dataModel
     * @param string         $mediaTypeKey
     * @param User           $user
     * @param bool           $isDirectUpload
     * @param string|null    $identifierName a partial name to use for uploaded image (slug, custom string...) for direct upload on mode
     *                                       or an existing image name with extension which will be resized to create a new image for direct upload off mode
     *
     * @return Image|null
     *
     * @throws \Exception
     */
    public function createTrickImage(
        ImageToCropDTO $dataModel,
        string $mediaTypeKey,
        User $user,
        bool $isDirectUpload = false,
        string $identifierName = null
    ) : ?Image
    {
        // Direct upload mode can not be used if no image was uploaded!
        // Direct upload off mode can not be used if data model "savedImageName" property is null and identifier is set to null
        $isDirectUploadOnModeRequirementSet = $isDirectUpload && \is_null($dataModel->getImage());
        $isDirectUploadOffModeRequirementSet = !$isDirectUpload && \is_null($dataModel->getSavedImageName()) && \is_null($identifierName);
        if (!$isDirectUploadOnModeRequirementSet || !$isDirectUploadOffModeRequirementSet) {
            // Avoid to block process with an exception (e.g. direct upload used in ImageToCropType form event)
            return null;
        }
        // Get image source necessary parameters (will be uploaded or a source for another image to create: e.g. another format)
        $sourceImageParameters = $this->getTrickImageParameters($dataModel, $mediaTypeKey, $isDirectUpload, $identifierName);
        // Create new trick image without complete form validation with direct upload on mode
        // or create new trick image based on existing image source (e.g. use this method in form handler to create Trick instance) with direct upload off mode
        $trickImageFile = $this->generateImageFile($dataModel, $mediaTypeKey, $sourceImageParameters, $isDirectUpload);
        // Create new trick image without complete form validation
        $entitiesParameters = $this->prepareTrickImageAndMediaData($dataModel, $isDirectUpload);
        // Create trick image entity
        $trickImageFileNameWithoutExtension = str_replace('.' . $trickImageFile->getExtension(), '', $trickImageFile->getFilename());
        $trickImageFileFormat = $trickImageFile->getExtension();
        $trickImageFileSize = $trickImageFile->getSize();
        // Get new trick Image entity
        $trickImage = new Image(
            $trickImageFileNameWithoutExtension,
            $entitiesParameters['imageDescription'],
            $trickImageFileFormat,
            $trickImageFileSize
        );
        // Create mandatory Media entity which references corresponding Image entity
        $this->mediaManager->createTrickMedia(
            $trickImage,
            $mediaTypeKey,
            $user,
            $entitiesParameters['isMainOption'],
            $entitiesParameters['isPublished'],
            $entitiesParameters['showListRank']
        );
        // Save data (image, media and media type instances):
        // There is no need to persist media and media type associated instances thanks to cascade option in mapping!
        $newTrickImage = $this->addAndSaveImage($trickImage);
        // Return Image entity
        return $newTrickImage;
    }

    /**
     * Create avatar image file with its parameters by uploading it on server.
     *
     * * Please note image is published by default, without administrator control in actual app configuration!
     *
     * @param UpdateProfileAvatarDTO $dataModel
     * @param User                   $user
     *
     * @return Image|null
     *
     * @throws \Exception
     */
    public function createUserAvatar(UpdateProfileAvatarDTO $dataModel, User $user) : ?Image
    {
        // Get avatar necessary parameters
        $parameters = $this->getUserAvatarParameters($dataModel, $user);
        // Upload file on server and get created file name with possible crop option
        $isCropped = property_exists(\get_class($dataModel), 'cropJSONData') ? true : false;
        $avatarFile = $this->imageUploader->upload($dataModel->getAvatar(), ImageUploader::AVATAR_IMAGE_DIRECTORY_KEY, $parameters, $isCropped);
        if (\is_null($avatarFile)) {
            return null;
        }
        $avatarFileNameWithoutExtension = str_replace('.' . $avatarFile->getExtension(), '', $avatarFile->getFilename());
        $avatarFileFormat = $avatarFile->getExtension();
        $avatarFileSize = $avatarFile->getSize();
        // Create avatar Image entity
        $image = new Image($avatarFileNameWithoutExtension, $user->getNickName() . '\'s avatar', $avatarFileFormat, $avatarFileSize);
        // Create mandatory Media entity which references corresponding Image entity
        $this->mediaManager->createUserAvatarMedia($image, $user, true, true);
        // Save data (image, media and media type instances):
        // There is no need to loop and persist media and media type associated instances thanks to cascade option in mapping!
        return $this->addAndSaveImage($image);
    }

    /**
     * Generate new image \SplFileInfo|File instance based on source image.
     *
     * @param ImageToCropDTO $dataModel
     * @param string         $key
     * @param array          $sourceImageParameters
     * @param bool           $isDirectUpload
     *
     * @return \SplFileInfo|null
     *
     * @throws \Exception
     */
    private function generateImageFile(ImageToCropDTO $dataModel, string $key, array $sourceImageParameters, bool $isDirectUpload) : ?\SplFileInfo
    {
        if ($isDirectUpload) {
            // Upload file on server and get created file name with possible crop option
            $isCropped = property_exists(\get_class($dataModel), 'cropJSONData') ? true : false;
            // Particular case for trick: upload image file on server without complete form validation and get new File instance
            $trickImageFile = $this->imageUploader->upload($dataModel->getImage(), ImageUploader::TRICK_IMAGE_DIRECTORY_KEY, $sourceImageParameters, $isCropped);
        } else {
            // Get new File instance based on source image (defined by data model "savedImageName" property or directly by identifier name parameter)
            $trickImageFile = $this->imageUploader->resizeNewImage(ImageUploader::TRICK_IMAGE_DIRECTORY_KEY, $sourceImageParameters);
        }
        if (\is_null($trickImageFile)) {
            return null;
        }
        return $trickImageFile;
    }

    /**
     * Get trick image to create corresponding MediaType by its unique key.
     *
     * @param string $mediaTypeKey
     *
     * @return MediaType
     */
    private function getTrickImageMediaType(string $mediaTypeKey) : MediaType
    {
        if (is_null($type = $this->mediaTypeManager->getType($mediaTypeKey))) {
            $this->logger->error("[trace app snowTricks] ImageManager/getTrickImageMediaType => MediaType instance: null for key $mediaTypeKey");
            throw new \RuntimeException("Media type key $mediaTypeKey is unknown!");
        }
        // Get expected media type data to resize the corresponding generated image
        $mediaTypeToFind = $this->mediaTypeManager->getType($mediaTypeKey);
        $trickImageMediaType = $this->mediaTypeManager->findSingleByUniqueType($mediaTypeToFind);
        return $trickImageMediaType;
    }

    /**
     * Get trick base (source) image to handle parameters depending on "direct upload" activation.
     *
     * @param ImageToCropDTO $dataModel
     * @param string         $mediaTypeKey
     * @param bool           $isDirectUpload
     * @param string         $identifierName a name to use for uploaded image (slug, custom string...)
     *
     * @return array
     *
     * @throws \Exception
     */
    private function getTrickImageParameters(ImageToCropDTO $dataModel, string $mediaTypeKey, bool $isDirectUpload, string $identifierName = null) : array
    {
        // Get corresponding MediaType instance data to associate to future Image entity
        // to resize correctly the corresponding generated image
        $trickImageMediaType = $this->getTrickImageMediaType($mediaTypeKey);
        // CAUTION: this case is used to upload an image without complete form validation!
        if ($isDirectUpload) {
            // Get File|UploadedFile instance which is an uploaded file to save on server
            $trickSourceImage = $dataModel->getImage();
            $cropJSONData = $dataModel->getCropJSONData();
            // Sanitize identifier name (if null, a fallback with image original name is used!)
            $trickImageIdentifierName = $this->getTrickImageSanitizedIdentifierName($identifierName, $trickSourceImage);
            $isImageNameHashChanged = true;
        // CAUTION: this case is used to create other image files based on big image or particular image, with different formats and the same ratio!
        } else {
            if (\is_null($identifierName)) {
                // Get the highest image file format which already exists thanks to "savedImageName" property
                $imageEntity = $this->findSingleByName($dataModel->getSavedImageName());
                $fileName = $imageEntity->getName() . '.' . $imageEntity->getFormat();
                // Get File instance based on "savedImageName" property
                $trickSourceImage = new File($this->getImageUploader()->getUploadDirectory(ImageUploader::TRICK_IMAGE_DIRECTORY_KEY) . '/' . $fileName, true);
                $trickImageIdentifierName = $imageEntity->getName();
            } else {
                // Get File instance based on identifier name parameter (used here as image name with its extension)
                $trickSourceImage = new File($this->getImageUploader()->getUploadDirectory(ImageUploader::TRICK_IMAGE_DIRECTORY_KEY) . '/' . $identifierName, true);
                $trickImageIdentifierName = str_replace('.' . $trickSourceImage->getExtension(), '', $trickSourceImage->getFileName());
            }
            $cropJSONData = null;
            $isImageNameHashChanged = false;
        }
        // Get image file data
        $trickImageData = $this->prepareImageFileData($trickSourceImage);
        return [
            'cropJSONData'           => $cropJSONData,
            'resizeFormat'           => ['width' => $trickImageMediaType->getWidth(), 'height' => $trickImageMediaType->getHeight()],
            'identifierName'         => $trickImageIdentifierName,
            'isImageNameHashChanged' => $isImageNameHashChanged,
            'width'                  => $trickImageData['imageWidth'],
            'height'                 => $trickImageData['imageHeight'],
            'dimensionsFormat'       => $trickImageData['imageDimensionsFormat'],
            'extension'              => $trickImageData['imageExtension'],
            'size'                   => $trickImageData['imageSize']
        ];
    }

    /**
     * Get trick image sanitized identifier name.
     *
     * @param string|null $identifierName
     * @param File        $trickImage
     *
     * @return string
     */
    private function getTrickImageSanitizedIdentifierName(?string $identifierName, File $trickImage) : string
    {
        if (\is_null($identifierName)) {
            // Use image original name (without extension) as image name with slug format
            $allowedImageExtensions = implode("|", ImageUploader::ALLOWED_IMAGE_FORMATS);
            // No need to use preg_quote() to escape, extensions are considered as "regex safe"!
            $pattern = '/\.(' . $allowedImageExtensions . ')/i';
            $originalNameWithoutExtension = preg_replace($pattern, '', $trickImage->getClientOriginalName());
            // Clean original name to avoid issues with special characters
            $trickImageIdentifierName = $this->sanitizeString($originalNameWithoutExtension);
        } else {
            // Sanitize passed identifier name to be sure to clean it
            $trickImageIdentifierName = $this->sanitizeString($identifierName);
        }
        return $trickImageIdentifierName;
    }

    /**
     * Get user avatar parameters.
     *
     * @param UpdateProfileAvatarDTO $dataModel
     * @param User                   $user
     *
     * @return array
     *
     * @throws \Exception
     */
    private function getUserAvatarParameters(UpdateProfileAvatarDTO $dataModel, User $user) : array
    {
        $avatar = $dataModel->getAvatar();
        $cropJSONData = $dataModel->getCropJSONData();
        $avatarMediaType = $this->mediaTypeManager->findSingleByUniqueType('u_avatar');
        // Use iconv() conversion to use nickname as partial image name with slug format
        $cleanNickName = $this->makeSlugWithNickName($user->getNickName());
        $avatarIdentifierName = $cleanNickName . '-avatar';
        $avatarImageData = $this->prepareImageFileData($avatar);
        return [
            'cropJSONData'           => $cropJSONData,
            'resizeFormat'           => ['width' => $avatarMediaType->getWidth(), 'height' => $avatarMediaType->getHeight()],
            'identifierName'         => $avatarIdentifierName,
            'isImageNameHashChanged' => true,
            'width'                  => $avatarImageData['imageWidth'],
            'height'                 => $avatarImageData['imageHeight'],
            'dimensionsFormat'       => $avatarImageData['imageDimensionsFormat'],
            'extension'              => $avatarImageData['imageExtension'],
            'size'                   => $avatarImageData['imageSize']
        ];
    }

    /**
     * Get entity manager.
     *
     * @return EntityManagerInterface
     */
    public function getEntityManager() : EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * Find Image by its name.
     *
     * @param string $fullNameWithoutExtension
     *
     * @return Image|null
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findSingleByName(string $fullNameWithoutExtension) : ?Image
    {
        return $this->getRepository()->findOneByName($fullNameWithoutExtension);
    }

    /**
     * Get image uploader.
     *
     * @return ImageUploader
     */
    public function getImageUploader() : ImageUploader
    {
        return $this->imageUploader;
    }

    /**
     * Get unique user avatar image entity.
     *
     * @param User $user
     *
     * @return Image|null
     *
     * @throws \Exception
     */
    public function getUserAvatarImage(User $user) : ?Image
    {
        $image = null;
        $medias = $user->getMedias();
        foreach ($medias as $media) {
            if ($this->mediaTypeManager->getType('userAvatar') === $media->getMediaType()->getType()) {
                $image = $media->getImage();
                // Avatar image is unique.
                break;
            }
        }
        return $image;
    }

    /**
     * Get User entity repository.
     *
     * @return ImageRepository
     */
    public function getRepository() : ImageRepository
    {
        return $this->repository;
    }

    /**
     * Prepare essential image data.
     *
     * Please not UploadedFile class extends File class and File class extends \SplFileInfo class
     *
     * @param File $image
     *
     * @return array
     */
    private function prepareImageFileData(File $image) : array
    {
        $imageWidth = getimagesize($image->getPathName())[0];
        $imageHeight = getimagesize($image->getPathName())[1];
        return [
            'imageWidth'            => $imageWidth,
            'imageHeight'           => $imageHeight,
            'imageDimensionsFormat' => $imageWidth . 'x' . $imageHeight,
            'imageExtension'        => $image->guessExtension(),
            'imageSize'             => $image->getSize()
        ];
    }

    /**
     * Prepare essential image data to set corresponding Image and Media entities.
     *
     * @param ImageToCropDTO $dataModel
     * @param bool $isDirectUpload
     *
     * @return array
     */
    private function prepareTrickImageAndMediaData(ImageToCropDTO $dataModel, bool $isDirectUpload) : array
    {
        // Create new trick image without complete form validation
        if ($isDirectUpload) {
            return [
                // Image description is set with default text at this level without possible complete form validation!
                'imageDescription' => self::DEFAULT_IMAGE_DESCRIPTION_TEXT,
                // Image main option is set to default value at this level without possible complete form validation like description!
                'isMainOption'     => false,
                // Corresponding Media entity will be published by default without administration (this management should be added in application!).
                'isPublished'      => true,
                // No show list rank can be defined without complete form validation!
                '$showListRank'    => null
            ];
         // Create new trick image based on existing image source
        } else {
            return [
                // Get image description from data model
                'imageDescription' => $dataModel->getDescription(),
                // Get image main option from data model
                'isMainOption'     => $dataModel->getIsMain(),
                // Corresponding Media entity will be published by default without administration (this management should be added in application!).
                'isPublished'      => true,
                // Get show list rank from data model
                '$showListRank'    => $dataModel->getShowListRank()
            ];
        }
    }

    /**
     * Remove user avatar image.
     *
     * @param User $user
     *
     * @return void
     *
     * @throws \Exception
     */
    public function removeUserAvatar(User $user) : void
    {
        // Image is both removed physically and in database (no user avatar gallery is used on website).
        $medias = $user->getMedias();
        foreach ($medias as $media) {
            if ($this->mediaTypeManager->getType('userAvatar') === $media->getMediaType()->getType()) {
                $pathToImage = $this->imageUploader->getUploadDirectory(ImageUploader::AVATAR_IMAGE_DIRECTORY_KEY);
                $image = $media->getImage();
                $imageFileName = $image->getName() . '.' . $image->getFormat();
                @unlink($pathToImage . '/' . $imageFileName);
                // Remove image entity (and corresponding media entity thanks to delete cascade option on relation)
                $this->entityManager->remove($image);
                // Save (update) data
                $this->entityManager->flush();
                // Avatar image is unique.
                break;
            }
        }
    }
}
