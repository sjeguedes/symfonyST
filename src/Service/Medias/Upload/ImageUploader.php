<?php

declare(strict_types = 1);

namespace App\Service\Medias\Upload;

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
     * Define expected image formats.
     */
    const ALLOWED_IMAGE_FORMATS = ['jpeg', 'jpg', 'png', 'gif', 'svg'];

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
     * Upload a file.
     *
     * @param UploadedFile $file
     * @param string       $key    a key which indicates a chosen upload directory
     * @param string       $label  a particular label to concatenate with definitive filename
     * @param string       $format a particular dimensions format added to file name
     *
     * @return string
     *
     * @throws \Exception
     */
    public function upload(UploadedFile $file, string $key, string $label, string $format) : string
    {
        if (!isset($this->uploadDirectory[$key])) {
            throw new \InvalidArgumentException('Chosen upload directory is unknown!');
        }
        $databaseFileName = $label . '-' . hash('crc32', uniqid()) . '-' . $format;
        $fileName = $databaseFileName . '.' . $file->guessExtension();
        try {
            $file->move($this->uploadDirectory[$key], $fileName);
        } catch (FileException $e) {
            $this->flashBag->add(
                'danger',
                'Your image was not uploaded<br>due to technical issue!<br>Try again later or use another file.'
            );
        }
        return $databaseFileName;
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
}
