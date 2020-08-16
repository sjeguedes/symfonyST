<?php

declare(strict_types = 1);

namespace App\Domain\ServiceLayer;

use App\Domain\DTO\UpdateProfileAvatarDTO;
use App\Domain\DTOToEmbed\ImageToCropDTO;
use App\Domain\Entity\Image;
use App\Domain\Entity\Media;
use App\Domain\Entity\MediaType;
use App\Domain\Entity\Trick;
use App\Domain\Entity\User;
use App\Domain\Repository\ImageRepository;
use App\Service\Medias\Upload\ImageUploader;
use App\Utils\Traits\StringHelperTrait;
use App\Utils\Traits\UserHandlingHelperTrait;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class ImageManager.
 *
 * Manage images to handle, and retrieve as a "service layer".
 */
class ImageManager extends AbstractServiceLayer
{
    use LoggerAwareTrait;
    use StringHelperTrait;
    use UserHandlingHelperTrait;

    /**
     * Define a avatar image type key.
     */
    const AVATAR_IMAGE_TYPE_KEY = 'avatar';

    /**
     * Define a trick image type key.
     */
    const TRICK_IMAGE_TYPE_KEY = 'trick';

    /**
     * Define a default alternative text for image which is directly uploaded (e.g. not attached to trick entity) on server.
     */
    const DEFAULT_IMAGE_DESCRIPTION_TEXT = 'No image description is available.';

    /**
     * Define a default identifier name for image which is directly uploaded (e.g. not attached to trick entity) on server.
     */
    const DEFAULT_IMAGE_IDENTIFIER_NAME = '-unnamed-image'; // A image category will be added before!

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

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
        parent::__construct($entityManager, $logger);
        $this->entityManager = $entityManager;
        $this->imageUploader = $imageUploader;
        $this->mediaManager = $mediaManager;
        $this->mediaTypeManager = $mediaTypeManager;
        $this->repository = $repository;
        $this->setLogger($logger);
    }


    /**
     * Add (persist) and save Image and Media entities in database.
     *
     * Please note combinations:
     * - $isPersisted = false, $isFlushed = false means Image and Media entities must be instantiated only.
     * - $isPersisted = true, $isFlushed = true means Image and Media entities are added to unit of work and saved in database.
     * - $isPersisted = true, $isFlushed = false means Image and Media entities are added to unit of work only.
     * - $isPersisted = false, $isFlushed = true means Image and Media entities are saved in database only with possible change(s) in unit of work.
     *
     * There is no need to persist media and media type associated instances if to cascade option is set in mapping!
     *
     * @param Image      $newImage
     * @param Media|null $newMedia
     * @param bool       $isPersisted
     * @param bool       $isFlushed
     *
     * @return Image|null
     */
    public function addAndSaveImage(
        Image $newImage,
        ?Media $newMedia,
        bool $isPersisted = false,
        bool $isFlushed = false
    ) : ?Image
    {
        // Bind associated Media entity if it is expected to ensure correct persistence!
        // This is needed without individual persistence by using cascade option.
        if (!\is_null($newMedia)) {
            $mediaSource = $newMedia->getMediaSource();
            $newImage->assignMediaSource($mediaSource);
            $mediaSource->assignMedia($newMedia);
        }
        // The logic would be also more functional and easier by persisting Media entity directly,
        // without the need to assign e Media entity.
        $object = $this->addAndSaveNewEntity($newImage, $isPersisted, $isFlushed);
        return \is_null($object) ? null : $newImage;
    }

    /**
     * Create trick image Image and Media entities, and file with its parameters with two different ways:
     * - by uploading it directly on server without complete form validation,
     * - by using an existing image (e.g. big format or a particular image) to create other formats based on it
     *
     * Please note image is published by default, without administrator control in application at this time!
     *
     * @param ImageToCropDTO $dataModel
     * @param \SplFileInfo   $newTrickImageFile
     * @param bool           $isDirectUpload
     * @param bool           $isPersisted
     * @param bool           $isFlushed
     *
     * @return Image|null
     *
     * @see addAndSaveImage() method to save data (image, media and media type instances)
     *
     * @throws \Exception
     */
    public function createTrickImage(
        ImageToCropDTO $dataModel,
        ?\SplFileInfo $newTrickImageFile,
        bool $isDirectUpload = false,
        bool $isPersisted = false,
        bool $isFlushed = false
    ) : ?Image
    {
        // Image file was not generated! So, this avoids to block running process.
        if (\is_null($newTrickImageFile)) {
           return null;
        }
        // Create trick image entity
        $trickImageFileNameWithoutExtension = str_replace('.' . $newTrickImageFile->getExtension(), '', $newTrickImageFile->getFilename());
        $trickImageDescription = $isDirectUpload ? self::DEFAULT_IMAGE_DESCRIPTION_TEXT : $dataModel->getDescription();
        $trickImageFileFormat = $newTrickImageFile->getExtension();
        $trickImageFileSize = $newTrickImageFile->getSize();
        // Get new trick Image entity
        $newTrickImage = new Image(
            $trickImageFileNameWithoutExtension,
            $trickImageDescription,
            $trickImageFileFormat,
            $trickImageFileSize
        );
        // Return Image entity
        // Maybe persist and possibly save data in database
        return $this->addAndSaveImage($newTrickImage, null, $isPersisted, $isFlushed); // null or the entity
    }

    /**
     * Create avatar image file with its parameters by uploading it on server.
     *
     * Please note image is published by default, without administrator control in application at this time!
     *
     * @param \SplFileInfo|null  $newAvatarImageFile
     * @param User|UserInterface $user
     *
     * @return Image|null
     *
     * @throws \Exception
     */
    public function createUserAvatar(?\SplFileInfo $newAvatarImageFile, UserInterface $user) : ?Image
    {
        // Image file was not generated! So, this avoids to block running process.
        if (\is_null($newAvatarImageFile)) {
            return null;
        }
        $avatarFileNameWithoutExtension = str_replace('.' . $newAvatarImageFile->getExtension(), '', $newAvatarImageFile->getFilename());
        $avatarFileFormat = $newAvatarImageFile->getExtension();
        $avatarFileSize = $newAvatarImageFile->getSize();
        // Create avatar Image entity
        $avatarImage = new Image(
            $avatarFileNameWithoutExtension,
            $user->getNickName() . '\'s avatar',
            $avatarFileFormat,
            $avatarFileSize
        );
        return $avatarImage;
    }

    /**
     * Generate a physical image file which will correspond to a User avatar image.
     *
     * Please note this method returns a \SplFileInfo instance in case of success.
     *
     * @param UpdateProfileAvatarDTO $dataModel
     * @param User|UserInterface     $user
     *
     * @return \SplFileInfo|null
     *
     * @throws \Exception
     */
    public function generateUserAvatarFile(
        UpdateProfileAvatarDTO $dataModel,
        UserInterface $user
    ) : ?\SplFileInfo
    {
        // Get avatar necessary parameters
        $parameters = $this->getUserAvatarParameters($dataModel, $user);
        // Upload file on server and get created file name with possible crop option
        $isCropped = property_exists(\get_class($dataModel), 'cropJSONData') ? true : false;
        $avatarFile = $this->imageUploader->upload($dataModel->getAvatar(), ImageUploader::AVATAR_IMAGE_DIRECTORY_KEY, $parameters, $isCropped);
        if (\is_null($avatarFile)) {
            return null;
        }
        return $avatarFile;
     }

    /**
     * Delete physically all images already associated to a Trick entity
     * or a particular list if it is specified in parameters thanks to corresponding Media entities.
     *
     * Please note, Image and Media entities corresponding to deleted image still exist after!
     * Thanks to possibly defined cascade operations, Trick entity removal will also remove
     * corresponding Image and Media entities for each image.
     *
     * @param Trick              $trick
     * @param Collection|Media[]|null $mediasList
     *
     * @return void
     *
     * @throws \Exception
     * @see TrickManager::removeTrick()
     */
    public function deleteAllTrickImagesFiles(Trick $trick, Collection $mediasList = null) : void
    {
        // Take into account all trick medias if specified media list is empty!
        $mediasList = $mediasList ?? $trick->getMediaOwner()->getMedias();
        // Filter with Media entity which must correspond to an Image entity
        foreach ($mediasList as $media) {
            /** @var Image|null $imageEntity */
            $imageEntity = $media->getMediaSource()->getImage();
            // Media must reference a Image entity!
            if (\is_null($imageEntity)) {
                continue;
            }
            // Delete image physically
            $this->deleteOneImageFile($imageEntity, ImageUploader::TRICK_IMAGE_DIRECTORY_KEY);
        }
    }

    /**
     * Delete physically a particular image.
     *
     * @param Image|null  $imageEntity
     * @param string|null $uploadDirectoryKey
     * @param string|null $imageFullName      a full image name (not a path) with extension
     *
     * @return bool
     *
     * @throws \Exception
     *
     * @see TrickManager::removeTrick()
     */
    public function deleteOneImageFile(?Image $imageEntity, string $uploadDirectoryKey, string $imageFullName = null) : bool
    {
        if (\is_null($imageEntity) && \is_null($imageFullName)) {
            throw new \RuntimeException('A instance of Image, or a full image name must be defined!');
        }
        $imageNameWithExtension = !\is_null($imageEntity)
                                  ? $imageEntity->getName() . '.' . $imageEntity->getFormat() : $imageFullName;
        // Remove image physically (check also temporary image)
        $uploadDirectory = $this->imageUploader->getUploadDirectory($uploadDirectoryKey);
        if (preg_match('/' . ImageManager::DEFAULT_IMAGE_IDENTIFIER_NAME . '/', $imageNameWithExtension)) {
            $uploadDirectory = $uploadDirectory . '/' .ImageUploader::TEMPORARY_DIRECTORY_NAME;
        }
        $isDeleted = false;
        if (file_exists($uploadDirectory . '/' . $imageNameWithExtension)) {
            $isDeleted = unlink($uploadDirectory . '/' . $imageNameWithExtension);
        }
        return $isDeleted;
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
    private function generateImageFile(
        ImageToCropDTO $dataModel,
        string $key,
        array $sourceImageParameters,
        bool $isDirectUpload
    ) : ?\SplFileInfo
    {
        if ($isDirectUpload) {
            // Upload file on server and get created file name with possible crop option
            $isCropped = property_exists(\get_class($dataModel), 'cropJSONData') ? true : false;
            // Particular case for trick: upload image file on server without complete form validation and get new File instance
            $imageFile = $this->imageUploader->upload($dataModel->getImage(), $key, $sourceImageParameters, $isCropped, true);
        } else {
            // Get new File instance based on source image (defined by data model "savedImageName" property or directly by identifier name parameter)
            $isTemporaryImageSource = $isDirectUpload;
            $imageFile = $this->imageUploader->resizeNewImage($key, $sourceImageParameters, $isTemporaryImageSource);
        }
        if (\is_null($imageFile)) {
            return null;
        }
        return $imageFile;
    }

    /**
     * Generate a physical image file which will correspond to a Trick entity.
     *
     * Please note this method returns a \SplFileInfo instance in case of success.
     *
     * @param ImageToCropDTO $dataModel
     * @param string         $mediaTypeKey
     * @param bool           $isDirectUpload
     * @param string|null    $identifierName a partial name to use for uploaded image (slug, custom string...) for direct upload on mode
     *                                       or an existing image name with extension which will be resized to create a new image for direct upload off mode
     *
     * @return \SplFileInfo|null
     *
     * @throws \Exception
     */
    public function generateTrickImageFile(
        ImageToCropDTO $dataModel,
        string $mediaTypeKey,
        bool $isDirectUpload = false,
        string $identifierName = null
    ) : ?\SplFileInfo
    {
        // Direct upload mode can not be used if no image was uploaded!
        // Direct upload off mode can not be used if data model "savedImageName" property is null and identifier is set to null!
        $isDirectUploadOnModeRequirementSet = $isDirectUpload && !\is_null($dataModel->getImage());
        $isDirectUploadOffModeRequirementSet = !$isDirectUpload && (!\is_null($dataModel->getSavedImageName()) || !\is_null($identifierName));
        if (!$isDirectUploadOnModeRequirementSet && !$isDirectUploadOffModeRequirementSet) {
            // Avoid to block process with an exception (e.g. direct upload used in ImageToCropType form event)
            return null;
        }
        // Get image source necessary parameters (will be uploaded or a source for another image to create: e.g. another format)
        $sourceImageParameters = $this->getTrickImageParameters($dataModel, $mediaTypeKey, $isDirectUpload, $identifierName);
        // Create new trick image without complete form validation with direct upload on mode
        // or create new trick image based on existing image source (e.g. use this method in form handler to create Trick instance) with direct upload off mode
        $imageDirectoryKey = ImageUploader::TRICK_IMAGE_DIRECTORY_KEY;
        $trickImageFile = $this->generateImageFile($dataModel, $imageDirectoryKey, $sourceImageParameters, $isDirectUpload);
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
        // CAUTION: this case is used to create other image files based on big image or particular image,
        // with different formats and the same ratio!
        } else {
            $uploadDirectory = $this->imageUploader->getUploadDirectory(ImageUploader::TRICK_IMAGE_DIRECTORY_KEY);
            if (\is_null($identifierName)) {
                // Get the highest image file format which already exists thanks to "savedImageName" property
                $imageEntity = $this->findSingleByName($dataModel->getSavedImageName());
                $fileName = $imageEntity->getName() . '.' . $imageEntity->getFormat();
                // Get File instance based on "savedImageName" property
                $trickSourceImage = new File($uploadDirectory . '/' . $fileName, true);
                $trickImageIdentifierName = $imageEntity->getName();
            } else {
                // Get File instance based on identifier name parameter (used here as image name with its extension)
                $trickSourceImage = new File($uploadDirectory . '/' . $identifierName, true);
                $trickImageIdentifierName = str_replace(
                    '.' . $trickSourceImage->getExtension(),
                    '', $trickSourceImage->getFileName()
                );
            }
            $cropJSONData = null;
        }
        // Get image file data
        $trickImageData = $this->prepareImageFileData($trickSourceImage);
        return [
            'cropJSONData'           => $cropJSONData,
            'resizeFormat'           => ['width' => $trickImageMediaType->getWidth(), 'height' => $trickImageMediaType->getHeight()],
            'identifierName'         => $trickImageIdentifierName,
            'isImageNameHashChanged' => $isDirectUpload,
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
     * @param User|UserInterface user
     *
     * @return array
     *
     * @throws \Exception
     */
    private function getUserAvatarParameters(UpdateProfileAvatarDTO $dataModel, UserInterface $user) : array
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
     * Find an Image entity by its name without extension.
     *
     * @param string $fileNameWithoutExtension
     *
     * @return object|Image|null
     */
    public function findSingleByName(string $fileNameWithoutExtension) : ?object
    {
        return $this->getRepository()->findOneBy(['name' => $fileNameWithoutExtension]);
    }

    /**
     * Find an Image entity by its uuid string representation.
     *
     * Please note uuid must be converted to binary string to make query!
     *
     * @param UuidInterface $uuid
     *
     * @return Image|null
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findSingleByUuid(UuidInterface $uuid) : ?Image
    {
        return $this->getRepository()->findOneByUuid($uuid);
    }

    /**
     * Get a particular image directory key constant value depending on image type key.
     *
     * Please not this method is a bit tricky but useful for refactoring!
     *
     * @param string $imageTypeKey
     *
     * @return string
     *
     * @throws \Exception
     */
    public function getImageDirectoryConstantValue(string $imageTypeKey) : string
    {
        $constantName = strtoupper($imageTypeKey) . '_IMAGE_DIRECTORY_KEY';
        $className = ImageUploader::class;
        $constant = constant("{$className}::{$constantName}");
        if (\is_null($constant)) {
            throw new \InvalidArgumentException("Image type key $imageTypeKey is unknown!");
        }
        return $constant;
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
     * Get all existing Image entities instances with the same name (identifier) based on Image entity instance.
     *
     * @param Image $imageEntityToFind
     *
     * @return array|Image[]|null
     */
    public function getImageWithIdenticalName(Image $imageEntityToFind) : ?array
    {
        $foundEntities = [];
        // Get image to find name
        $imageEntityToFindName = $imageEntityToFind->getName();
        $imageEntityToFindNameWithoutFormat = preg_replace('/(\d+x\d+)$/', '', $imageEntityToFindName);
        // Get all the images entities with the same name
        foreach ($this->getRepository()->findAll() as $imageEntity) {
            /** @var Image $imageEntity */
            // Take into account the 3 image versions with the same identifier but depending on different formats
            $imageEntityName = $imageEntity->getName();
            // preg_match() can be used instead to avoid a second preg_replace()!
            $imageEntityNameWithoutFormat = preg_replace('/(\d+x\d+)$/', '', $imageEntityName);
            // Image must be removed and its physical file must be also deleted!
            if ($imageEntityToFindNameWithoutFormat === $imageEntityNameWithoutFormat) {
                $foundEntities[] = $imageEntity;
            }
        }
        return !empty($foundEntities) ? $foundEntities : null;
    }

    /**
     * Get unique user avatar image entity.
     *
     * @param User|UserInterface $user
     *
     * @return Image|null
     *
     * @throws \Exception
     */
    public function getUserAvatarImage(UserInterface $user) : ?Image
    {
        $image = null;
        // Corresponding media owner can be null, if authenticated user just created his account!
        if (\is_null($user->getMediaOwner())) {
            return null;
        }
        $medias = $user->getMediaOwner()->getMedias();
        foreach ($medias as $media) {
            if ($this->mediaTypeManager->getType('userAvatar') === $media->getMediaType()->getType()) {
                $image = $media->getMediaSource()->getImage();
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
     * Prepare a new image name depending on image type key.
     *
     * @param string $currentImageName
     * @param string $newSlug           a partial name used to replace image name slug part
     * @param string $imageTypeKey      a key which identifies image type
     *
     * @return string
     *
     * @throws \Exception
     */
    public function prepareImageName(string $currentImageName, string $newSlug, string $imageTypeKey) : ?string
    {
        // Get dynamically a image directory key constant value depending on image type
        $constant = $this->getImageDirectoryConstantValue($imageTypeKey);
        switch ($imageTypeKey) {
            case self::AVATAR_IMAGE_TYPE_KEY:
                preg_match('/^(.*)-[a-z0-9]*(\.[a-z]{3,4})?$/', $currentImageName, $matches, PREG_UNMATCHED_AS_NULL);
                // Replace group 1 in front of string by slug
                $newImageName = preg_replace('/' . $matches[1] . '/', $newSlug, $currentImageName);
                break;
            case self::TRICK_IMAGE_TYPE_KEY:
                preg_match('/^(.*)-[a-z0-9]*-\d+x\d+(\.[a-z]{3,4})?$/', $currentImageName, $matches, PREG_UNMATCHED_AS_NULL);
                // Replace group 1 in front of string by slug
                $newImageName = preg_replace('/' . $matches[1] . '/', $newSlug, $currentImageName);
                break;
            default:
                // No change
                $newImageName = $currentImageName;
        }
        return $newImageName;
    }

    /**
     * Purge orphaned images files which are not associated to Image entities.
     *
     * @param string $uploadDirectoryKey
     * @param array  $imagesEntities
     *
     * @return void
     *
     * @throws \Exception
     */
    public function purgeOrphanedImagesFiles(string $uploadDirectoryKey, array $imagesEntities) : void
    {
        // Restrict search in an upload directory or sub directory to avoid unexpected file deletion
        $imagesDirectory = $this->getImageUploader()->getUploadDirectory($uploadDirectoryKey);
        // Loop on existing files path in this particular directory
        foreach (glob($imagesDirectory . "/*") as $imagePath) {
            if (is_dir($imagePath)) continue;
            $pattern = preg_quote($imagesDirectory . '/', '/');
            $listedImageName = preg_replace('/' . $pattern . '/', '', $imagePath);
            $isImageOrphaned = true;
            foreach ($imagesEntities as $imageEntity) {
                $existingImageName = $imageEntity->getName() . '.' . $imageEntity->getFormat();
                if ($existingImageName === $listedImageName) {
                    $isImageOrphaned = false;
                    break;
                }
            }
            // Delete file only if it is orphaned!
            !$isImageOrphaned ?: unlink($imagePath);
        }
    }

    /**
     * Remove each empty temporary sub directory, each time one is found.
     *
     * Please note this searches in a particular upload directory to avoid issue.
     *
     * @param string $uploadDirectoryKey
     *
     * @return void
     *
     * @throws \Exception
     */
    public function removeEmptyTemporaryDirectory(string $uploadDirectoryKey) : void
    {
        // Restrict search in an upload directory or sub directory to avoid unexpected file deletion
        $imagesDirectory = $this->getImageUploader()->getUploadDirectory($uploadDirectoryKey);
        // Used to match a temporary directory
        $pattern = preg_quote(ImageUploader::TEMPORARY_DIRECTORY_NAME, '/');
        // Remove each empty temporary sub directory.
        foreach (glob($imagesDirectory . "/*") as $imagePath) {
            if (!is_dir($imagePath)) continue;
            $directoryPath = $imagePath;
            $isTemporaryPath = preg_match("/{$pattern}$/", $directoryPath);
            // Remove each found temporary directory if it (still) exists and is empty.
            if ($isTemporaryPath && is_dir($directoryPath) && !$files = glob($directoryPath . "/*")) {
                rmdir($directoryPath);
            }
        }
    }

    /**
     * Remove user avatar image.
     *
     * @param User|UserInterface $user
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function removeUserAvatar(UserInterface $user) : bool
    {
        // Image is both removed physically and in database (no user avatar gallery is used on website).
        $isImageRemoved = false;
        // Avoid issue: no need to remove if no media owner is set (no attached media)!
        if (\is_null($mediaOwner = $user->getMediaOwner())) {
            return true;
        }
        $medias = $mediaOwner->getMedias();
        foreach ($medias as $media) {
            if ($this->mediaTypeManager->getType('userAvatar') === $media->getMediaType()->getType()) {
                $uploadDirectory = $this->imageUploader->getUploadDirectory(ImageUploader::AVATAR_IMAGE_DIRECTORY_KEY);
                $image = $media->getMediaSource()->getImage();
                $imageFileName = $image->getName() . '.' . $image->getFormat();
                // Try to delete physical image
                try {
                    unlink($uploadDirectory . '/' . $imageFileName);
                    $isImageRemoved = true;
                } catch (\Exception $exception) {
                    $isImageRemoved = false;
                }
                // Remove image entity (and corresponding media entity thanks to delete cascade option on relation)
                // and save change (update) data.
                if ($isImageRemoved) {
                    $isImageRemoved = $this->removeAndSaveNoMoreEntity($image, true);
                }
                // Avatar assigned image is unique.
                break;
            }
        }
        return $isImageRemoved;
    }

    /**
     * Rename a file which exists physically in a particular directory.
     *
     * @param string $currentImageName a base image name with extension
     * @param string $newImageName     a base image name with extension
     * @param string $imageTypeKey     a key which identifies image type
     * @param bool   $isTemporary      the file can be uploaded in a temporary directory
     *
     * @return bool
     *
     * @see https://www.php.net/manual/fr/function.constant.php
     * @see https://electrictoolbox.com/php-constant-value-dynamically/
     *
     * @throws \Exception
     */
    public function renameImage(
        string $currentImageName,
        string $newImageName,
        string $imageTypeKey,
        bool $isTemporary = false
    ) : bool
    {
        // Get dynamically a image directory key constant value depending on image type
        $constant = $this->getImageDirectoryConstantValue($imageTypeKey);
        // Get image directory thanks to image type key passed as argument
        $uploadDirectory = $this->imageUploader->getUploadDirectory($constant);
        // Update base path with temporary directory if needed
        $baseDirectory = $isTemporary ? $uploadDirectory . '/' . ImageUploader::TEMPORARY_DIRECTORY_NAME : $uploadDirectory;
        // Rename image
        $isImageRenamed = rename(
            $baseDirectory . '/' . $currentImageName,
            $uploadDirectory . '/' . $newImageName
        );
        return $isImageRenamed;
    }

    /**
     * Update big image corresponding Image and Media entities when a trick is created.
     *
     * Please not this valid image was created without complete form validation.
     *
     * @param Image          $bigImageEntity
     * @param ImageToCropDTO $imageToCropDTO
     * @param string         $newImageName
     * @param bool           $isFlushed
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function updateTrickBigImage(
        Image $bigImageEntity,
        ImageToCropDTO $imageToCropDTO,
        string $newImageName,
        bool $isFlushed = true
    ) : bool
    {
        // Get corresponding image Media entity
        $bigImagMediaEntity = $bigImageEntity->getMediaSource()->getMedia();
        if (is_null($bigImagMediaEntity)) {
            return false;
        }
        // Update name (with new name), description and update date in corresponding Image entity
        $bigImageEntity->modifyName($newImageName);
        $bigImageEntity->modifyDescription($imageToCropDTO->getDescription());
        $bigImageEntity->modifyUpdateDate(new \DateTime('now'));
        // Update update date and data with corresponding ImageToCropDTO data ("isMain" and "showListRank" properties) in corresponding Media entity
        $bigImagMediaEntity->modifyIsMain($imageToCropDTO->getIsMain());
        $bigImagMediaEntity->modifyShowListRank($imageToCropDTO->getShowListRank());
        $bigImagMediaEntity->modifyUpdateDate(new \DateTime('now'));
        // To be sure to update data in database
        if ($isFlushed) {
            $updatedBigImagMediaEntity = $this->addAndSaveNewEntity($bigImagMediaEntity, false, true);
            if (\is_null($updatedBigImagMediaEntity)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Remove an Image entity and all associated entities depending on cascade operations.
     *
     * @param Image $image
     * @param bool  $isFlushed
     *
     * @return bool
     */
    public function removeImage(Image $image, bool $isFlushed = true) : bool
    {
        // Proceed to removal in database
        return $this->removeAndSaveNoMoreEntity($image, $isFlushed);
    }
}
