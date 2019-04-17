<?php

declare(strict_types = 1);

namespace App\Utils\Database\DataFixtures;

use App\Domain\Entity\Video;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Class VideoFixtures.
 *
 * Generate trick video fake starting set of data in database.
 */
class VideoFixtures extends BaseFixture
{
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
        $this->createFixtures(Video::class, \count($data), function($i) use($data) {
            return new Video(
                $data[$i]['fields']['url'],
                $data[$i]['fields']['description'],
                new \DateTime(sprintf("+%d days", $i - 1))
            );
        });
        $manager->flush();
    }
}
