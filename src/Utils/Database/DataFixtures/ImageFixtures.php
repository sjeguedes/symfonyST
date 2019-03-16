<?php

declare(strict_types=1);

namespace App\Utils\Database\DataFixtures;

use App\Domain\Entity\Image;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Class ImageFixtures.
 *
 * Generate trick image fake starting set of data in database.
 */
class ImageFixtures extends BaseFixture
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
        $array = $this->parseYamlFile('image_fixtures.yaml');
        $data = $array['images'];
        // Create trick images
        $this->createFixtures(Image::class, \count($data), function($i) use($data) {
            return new Image(
                $data[$i]['fields']['name'],
                $data[$i]['fields']['description'],
                $data[$i]['fields']['format'],
                $data[$i]['fields']['size'],
                new \DateTime(sprintf("+%d days", $i - 1)),
                new \DateTime(sprintf("+%d days", $i - 1))
            );
        });
        $manager->flush();
    }
}
