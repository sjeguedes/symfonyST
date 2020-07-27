<?php

declare(strict_types = 1);

namespace App\Utils\Database\DataFixtures;

use App\Domain\Entity\Video;
use App\Domain\ServiceLayer\VideoManager;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Class VideoFixtures.
 *
 * Generate trick video fake starting set of data in database.
 */
class VideoFixtures extends BaseFixture
{
    /**
     * @var VideoManager
     */
    private $videoService;

    /**
     * VideoFixtures constructor.
     *
     * @param ParameterBagInterface $parameterBag
     * @param VideoManager          $videoService
     */
    public function __construct(ParameterBagInterface $parameterBag, VideoManager $videoService)
    {
        parent::__construct($parameterBag);
        $this->videoService = $videoService;
    }

    /**
     * Persist and flush entities in database.
     *
     * @param ObjectManager $manager
     *
     * @return void
     *
     * @throws \Exception
     */
    public function loadData(ObjectManager $manager) : void
    {
        $array = $this->parseYamlFile('video_fixtures.yaml');
        $data = $array['videos'];
        // Create trick videos
        $this->createFixtures(Video::class, \count($data), function ($i) use ($data) {
            // Define a video unique name based on URL
            $videoUniqueName = $this->videoService->generateUniqueVideoNameWithURL($data[$i]['fields']['url']);
            return new Video(
                $videoUniqueName,
                $data[$i]['fields']['url'],
                $data[$i]['fields']['description'],
                new \DateTime(sprintf("+%d days", -$i))
            );
        });
        $manager->flush();
    }
}
