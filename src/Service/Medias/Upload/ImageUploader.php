<?php

declare(strict_types = 1);

namespace App\Service\Medias\Upload;

use App\Domain\ServiceLayer\MediaTypeManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
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
     * @var FlashBagInterface
     */
    private $flashBag;

    /**
     * @var MediaTypeManager
     */
    private $mediaTypeService;

    /*
     * @var ParameterBagInterface
     */
    private $parameterBag;

    /*
     * @var string
     */
    private $uploadDirectory;

    /**
     * ImageUploader constructor.
     *
     * @param ParameterBagInterface $parameterBag
     * @param MediaTypeManager      $mediaTypeService
     * @param FlashBagInterface     $flashBag
     */
    public function __construct(ParameterBagInterface $parameterBag, MediaTypeManager $mediaTypeService, FlashBagInterface $flashBag)
    {
        $this->parameterBag = $parameterBag;
        $this->uploadDirectory = $this->parameterBag->get('app_avatar_upload_directory');
        $this->mediaTypeService = $mediaTypeService;
        $this->flashBag = $flashBag;

    }

    /**
     * Get a MediaTypeManager service.
     *
     * @return MediaTypeManager
     */
    public function getMediaTypeService() : MediaTypeManager
    {
        return $this->mediaTypeService;
    }

    /**
     * Upload a file.
     *
     * @param UploadedFile $file
     * @param string       $name
     * @param string       $format
     *
     * @return string
     */
    public function upload(UploadedFile $file, string $name, string $format) : string
    {
        $fileName = $name . '-' . hash('crc32', uniqid()) . '-' . $format . '.' . $file->guessExtension();
        try {
            $file->move($this->uploadDirectory, $fileName);
        } catch (FileException $e) {
            $this->flashBag->add(
                'danger',
                'Your avatar was not uploaded<br>due to technical issue!<br>Try again later or use another file.'
            );
        }
        return $fileName;
    }

    /**
     * Get upload directory used to move a file.
     *
     * @return string
     */
    public function getUploadDirectory() : string
    {
        return $this->uploadDirectory;
    }
}
