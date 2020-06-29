<?php

declare(strict_types = 1);

namespace App\Utils\Database\DataFixtures;

use App\Domain\Entity\MediaType;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Class MediaTypeFixtures.
 *
 * Generate media type fake starting set of data in database.
 */
class MediaTypeFixtures extends BaseFixture
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
        $array = $this->parseYamlFile('media_type_fixtures.yaml');
        $data = $array['media_types'];
        // Create media types
        $this->createFixtures(MediaType::class, \count($data), function ($i) use ($data) {
            return new MediaType(
                $data[$i]['fields']['type'],
                $data[$i]['fields']['source_type'],
                $data[$i]['fields']['name'],
                $data[$i]['fields']['description'],
                $data[$i]['fields']['width'],
                $data[$i]['fields']['height'],
                new \DateTime(sprintf("+%d days", -$i))
            );
        });
        $manager->flush();
    }
}
