<?php

declare(strict_types = 1);

namespace App\Domain\ServiceLayer;

use App\Domain\Entity\Image;
use App\Domain\Entity\MediaType;
use App\Domain\Entity\User;
use App\Domain\Repository\ImageRepository;
use App\Service\Medias\Upload\ImageUploader;
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
    use UserHandlingHelperTrait;

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
     *
     * @return void
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        ImageUploader $imageUploader,
        ImageRepository $repository,
        MediaManager $mediaManager,
        MediaTypeManager $mediaTypeManager,
        LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->imageUploader = $imageUploader;
        $this->mediaManager = $mediaManager;
        $this->mediaTypeManager = $mediaTypeManager;
        $this->repository = $repository;
        $this->setLogger($logger);
    }

    /**
     * Create avatar image file with its parameters by uploading it on server.
     *
     * @param User         $user
     * @param UploadedFile $avatar
     *
     * @return Image
     *
     * @throws \Exception
     *
     * // TODO: Review Image, MediaType, Media entity
     */
    public function createUserAvatar(User $user, UploadedFile $avatar) : Image
    {
        // Get avatar necessary parameters
        $parameters = $this->getAvatarParameters($user, $avatar);
        // Upload file on server and get created file name
        $avatarName = $this->imageUploader->upload($avatar, ImageUploader::AVATAR_DIRECTORY_KEY, $parameters['identifierName'], $parameters['dimensionsFormat']);
        // Create avatar media image entity
        $image = new Image($avatarName, $user->getNickName() . '\'s avatar', $parameters['extension'], $parameters['size']);
        // Create mandatory media which references image
        $this->mediaManager->createUserAvatarMedia($image, $user, true, true);
        // Save data (image, media and media type instances:
        // There is no need to loop and persist media and media type associated instances thanks to cascade option in mapping!
        $this->getEntityManager()->persist($image);
        $this->getEntityManager()->flush();
        return $image;
    }

    /**
     * Get user avatar parameters.
     *
     * @param User $user
     * @param UploadedFile $avatar
     *
     * @return array
     *
     * @throws \Exception
     */
    public function getAvatarParameters(User $user, UploadedFile $avatar) : array
    {
        // Use iconv() conversion to use nickname as slug
        $cleanNickName = $this->cleanAvatarNickNameForSlug($user->getNickName());
        $avatarIdentifierName = $cleanNickName . '-avatar';
        $avatarWidth = getimagesize($avatar->getPathName())[0];
        $avatarHeight = getimagesize($avatar->getPathName())[1];
        $avatarDimensionsFormat = $avatarWidth . 'x' . $avatarHeight;
        $avatarExtension = $avatar->guessExtension();
        $avatarSize = $avatar->getSize();
        return [
            'identifierName'   => $avatarIdentifierName,
            'width'            => $avatarWidth,
            'height'           => $avatarHeight,
            'dimensionsFormat' => $avatarDimensionsFormat,
            'extension'        => $avatarExtension,
            'size'             => $avatarSize
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
            if (MediaType::TYPE_CHOICES['userAvatar'] === $media->getMediaType()->getType()) {
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
            if (MediaType::TYPE_CHOICES['userAvatar'] === $media->getMediaType()->getType()) {
                $pathToImage = $this->imageUploader->getUploadDirectory(ImageUploader::AVATAR_DIRECTORY_KEY);
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
