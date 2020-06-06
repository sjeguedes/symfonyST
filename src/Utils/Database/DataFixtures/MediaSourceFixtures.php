<?php

declare(strict_types = 1);

namespace App\Utils\Database\DataFixtures;

use App\Domain\Entity\Image;
use App\Domain\Entity\MediaSource;
use App\Domain\Entity\Video;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Class MediaSourceFixtures.
 *
 * Generate trick media fake starting set of data in database.
 */
class MediaSourceFixtures extends BaseFixture implements DependentFixtureInterface
{
    /**
     * Get class dependencies with other entities.
     *
     * @return array
     */
    public function getDependencies() : array
    {
        return [
            ImageFixtures::class,
            VideoFixtures::class
        ];
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
        $array = $this->parseYamlFile('media_source_fixtures.yaml');
        $data = $array['media_sources'];
        // Create tricks
        $this->createFixtures(MediaSource::class, \count($data), function ($i) use ($data) {
            switch ( $data[$i]['type']) {
                case 'image':
                    $proxy = $this->getReference(Image::class . '_' . $data[$i]['references']['image']);
                    break;
                case 'video':
                    $proxy = $this->getReference(Video::class . '_' . $data[$i]['references']['video']);
                    break;
                default:
                    throw new \InvalidArgumentException('MediaSource source type is unknown!');
            }
            return new MediaSource($proxy);
        });
        $manager->flush();
    }
}
