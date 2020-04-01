<?php

declare(strict_types = 1);

namespace App\Domain\ServiceLayer;

use App\Domain\DTO\UpdateProfileAvatarDTO;
use App\Domain\DTOToEmbed\ImageToCropDTO;
use App\Domain\Entity\Image;
use App\Domain\Entity\User;
use App\Domain\Repository\ImageRepository;
use App\Service\Medias\Upload\ImageUploader;
use App\Utils\Traits\StringHelperTrait;
use App\Utils\Traits\UserHandlingHelperTrait;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

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
     * Create trick image file with its parameters by uploading it on server.
     *
     * Please note image is published by default, without administrator control in actual app configuration!
     *
     * @param ImageToCropDTO $dataModel
     * @param string         $mediaTypeKey
     * @param User           $user
     * @param string         $identifierName a name to use for uploaded image (slug, custom string...)
     * @param bool           $isDirectUpload
     *
     * @return Image|null
     *
     * @throws \Exception
     */
    public function createTrickImage(
        ImageToCropDTO $dataModel,
        string $mediaTypeKey,
        User $user,
        string $identifierName = null,
        bool $isDirectUpload = false
    ) : ?Image
    {
        // No image was uploaded!
        if (\is_null($dataModel->getImage())) {
            return null;
        }
        // Get image necessary parameters
        $parameters = $this->getTrickImageParameters($dataModel, $mediaTypeKey, $identifierName);
        // Upload file on server and get created file name with possible crop option
        $isCropped = property_exists(\get_class($dataModel), 'cropJSONData') ? true : false;
        // Check uploaded "image" data or retrieve "savedImageName" value to create image entity directly without upload (use this method in form handler when trick is created)
        if ($isDirectUpload) {
            // Particular case for trick: upload image without complete form validation
            $trickImageName = $this->imageUploader->upload($dataModel->getImage(), ImageUploader::TRICK_IMAGE_DIRECTORY_KEY, $parameters, $isCropped);
            // Image description may not have been set due to direct image upload (to insure persistence) without complete form validation for other fields (e.g. description)!
            $imageDescription = !$isDirectUpload ? $dataModel->getDescription() : self::DEFAULT_IMAGE_DESCRIPTION_TEXT;
            // Create image media entity with image entity
            $image = new Image($trickImageName, $imageDescription, $parameters['extension'], $parameters['size']);
            // Image main option may not have been set due to possible direct image upload like description!
            $isMainOption = !$isDirectUpload ? $dataModel->getIsMain() : false;
            // Create mandatory media which references image
            $this->mediaManager->createTrickMedia($image, $mediaTypeKey, $user, $isMainOption, true);
            // Save data (image, media and media type instances):
            // There is no need to loop and persist media and media type associated instances thanks to cascade option in mapping!
            $newTrickImage = $this->addAndSaveImage($image);
        } else {
            // Image was already and directly uploaded (case above), so simply use its name stored in "savedImageName" field.
            $trickImageName = $dataModel->getSavedImageName();
            // Associated Image and Media entities are already set, so get and return an image entity by its name!
            // Saved image name can not be null at this point!
            $newTrickImage = $this->findSingleByName($trickImageName);
        }
        if (\is_null($trickImageName)) {
            return null;
        }
        // Return image entity
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
        $avatarName = $this->imageUploader->upload($dataModel->getAvatar(), ImageUploader::AVATAR_IMAGE_DIRECTORY_KEY, $parameters, $isCropped);
        if (\is_null($avatarName)) {
            return null;
        }
        // Create avatar media entity with image entity
        $image = new Image($avatarName, $user->getNickName() . '\'s avatar', $parameters['extension'], $parameters['size']);
        // Create mandatory media which references image
        $this->mediaManager->createUserAvatarMedia($image, $user, true, true);
        // Save data (image, media and media type instances):
        // There is no need to loop and persist media and media type associated instances thanks to cascade option in mapping!
        return $this->addAndSaveImage($image);
    }

    /**
     * Get trick image parameters.
     *
     * @param ImageToCropDTO $dataModel
     * @param string         $mediaTypeKey
     * @param string         $identifierName a name to use for uploaded image (slug, custom string...)
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getTrickImageParameters(ImageToCropDTO $dataModel, string $mediaTypeKey, string $identifierName = null) : array
    {
        if (is_null($type = $this->mediaTypeManager->getType($mediaTypeKey))) {
            throw new \RuntimeException("Media type key $mediaTypeKey is unknown!");
        }
        $trickImage = $dataModel->getImage();
        $cropJSONData = $dataModel->getCropJSONData();
        $mediaTypeToFind = $this->mediaTypeManager->getType($mediaTypeKey);
        $trickBigImageMediaType = $this->mediaTypeManager->findSingleByUniqueType($mediaTypeToFind);
        if (\is_null($identifierName)) {
            // Use image original name (without extension) as image name with slug format
            $allowedImageExtensions = implode("|", ImageUploader::ALLOWED_IMAGE_FORMATS);
            // No need to use preg_quote() to escape, extensions are considered as "regex safe"!
            $pattern = '/\.(' . $allowedImageExtensions . ')/i';
            $originalNameWithoutExtension = preg_replace($pattern, '', $trickImage->getClientOriginalName());
            // Clean original name to avoid issues with special characters
            $trickImageIdentifierName = $this->sanitizeString($originalNameWithoutExtension);
        } else {
            // Sanitize passed identifier name
            $trickImageIdentifierName = $this->sanitizeString($identifierName);
        }
        $trickImageData = $this->prepareImageData($trickImage);
        return [
            'cropJSONData'     => $cropJSONData,
            'resizeFormat'     => ['width' => $trickBigImageMediaType->getWidth(), 'height' => $trickBigImageMediaType->getHeight()],
            'identifierName'   => $trickImageIdentifierName,
            'width'            => $trickImageData['imageWidth'],
            'height'           => $trickImageData['imageHeight'],
            'dimensionsFormat' => $trickImageData['imageDimensionsFormat'],
            'extension'        => $trickImageData['imageExtension'],
            'size'             => $trickImageData['imageSize']
        ];
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
    public function getUserAvatarParameters(UpdateProfileAvatarDTO $dataModel, User $user) : array
    {
        $avatar = $dataModel->getAvatar();
        $cropJSONData = $dataModel->getCropJSONData();
        $avatarMediaType = $this->mediaTypeManager->findSingleByUniqueType('u_avatar');
        // Use iconv() conversion to use nickname as partial image name with slug format
        $cleanNickName = $this->cleanAvatarNickNameForSlug($user->getNickName());
        $avatarIdentifierName = $cleanNickName . '-avatar';
        $avatarImageData = $this->prepareImageData($avatar);
        return [
            'cropJSONData'     => $cropJSONData,
            'resizeFormat'     => ['width' => $avatarMediaType->getWidth(), 'height' => $avatarMediaType->getHeight()],
            'identifierName'   => $avatarIdentifierName,
            'width'            => $avatarImageData['imageWidth'],
            'height'           => $avatarImageData['imageHeight'],
            'dimensionsFormat' => $avatarImageData['imageDimensionsFormat'],
            'extension'        => $avatarImageData['imageExtension'],
            'size'             => $avatarImageData['imageSize']
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
     * @param UploadedFile $image
     *
     * @return array
     */
    private function prepareImageData(UploadedFile $image) : array
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
