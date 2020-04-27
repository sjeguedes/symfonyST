<?php

declare(strict_types = 1);

namespace App\Domain\ServiceLayer;

use App\Domain\DTOToEmbed\VideoInfosDTO;
use App\Domain\Entity\Media;
use App\Domain\Entity\Video;
use App\Domain\Repository\VideoRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Class VideoManager.
 *
 * Manage videos to handle, and retrieve as a "service layer".
 */
class VideoManager extends AbstractServiceLayer
{
    use LoggerAwareTrait;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var MediaManager
     */
    private $mediaManager;

    /**
     * @var VideoRepository
     */
    private $repository;

    /**
     * VideoManager constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param VideoRepository        $repository
     * @param MediaManager           $mediaManager
     * @param LoggerInterface        $logger
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        VideoRepository $repository,
        MediaManager $mediaManager,
        LoggerInterface $logger
    )
    {
        parent::__construct($entityManager, $logger);
        $this->entityManager = $entityManager;
        $this->repository = $repository;
        $this->mediaManager = $mediaManager;
        $this->setLogger($logger);
    }

    /**
     * Add (persist) and save Video and Media entities in database.
     *
     * Please note combinations:
     * - $isPersisted = false, $isFlushed = false means Video and Media entities must be instantiated only.
     * - $isPersisted = true, $isFlushed = true means Video and Media entities are added to unit of work and saved in database.
     * - $isPersisted = true, $isFlushed = false means Video and Media entities are added to unit of work only.
     * - $isPersisted = false, $isFlushed = true means Video and Media entities are saved in database only with possible change(s) in unit of work.
     *
     * There is no need to persist media and media type associated instances if to cascade option is set in mapping!
     *
     * @param Video      $newVideo
     * @param Media|null $newMedia
     * @param bool       $isPersisted
     * @param bool       $isFlushed
     *
     * @return Video|null
     */
    public function addAndSaveVideo(
        Video $newVideo,
        ?Media $newMedia,
        bool $isPersisted = false,
        bool $isFlushed = false
    ) : ?Video
    {
        // Bind associated Media entity if it is expected.
        if (!\is_null($newMedia)) {
            $newVideo->setMedia($newMedia);
        }
        // The logic would be also more functional and easier by persisting Media entity directly,
        // without the need to set e Media entity.
        $object = $this->addAndSaveEntity($newVideo, $isPersisted, $isFlushed);
        return \is_null($object) ? null : $newVideo;
    }

    /**
     * Create trick video Video entity.
     *
     * @param VideoInfosDTO $dataModel
     *
     * @return Video|null
     *
     * @throws \Exception
     */
    public function createTrickVideo(VideoInfosDTO $dataModel) : ?Video
    {
        // Get new trick Video entity
        $trickVideo = new Video($dataModel->getUrl(), $dataModel->getDescription());
        // Return Video entity
        return $trickVideo;
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
     * Get Video entity repository.
     *
     * @return VideoRepository
     */
    public function getRepository() : VideoRepository
    {
        return $this->repository;
    }
}
