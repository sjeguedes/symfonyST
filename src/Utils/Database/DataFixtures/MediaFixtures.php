<?php

declare(strict_types=1);

namespace App\Utils\Database\DataFixtures;

use App\Domain\Entity\Image;
use App\Domain\Entity\Media;
use App\Domain\Entity\MediaType;
use App\Domain\Entity\Trick;
use App\Domain\Entity\Video;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Class MediaFixtures.
 *
 * Generate media fake starting set of data in database.
 */
class MediaFixtures extends BaseFixture implements DependentFixtureInterface
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
            VideoFixtures::class,
            MediaTypeFixtures::class,
            TrickFixtures::class
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
        $array = $this->parseYamlFile('media_fixtures.yaml');
        $data = $array['medias'];
        // Create medias with image or video
        $this->createFixtures(Media::class, \count($data), function($i) use($data) {
            $proxy2 = $this->getReference(MediaType::class . '_' . $data[$i]['references']['media_type']);
            $proxy3 = $this->getReference(Trick::class . '_' . $data[$i]['references']['trick']);
            switch ($data[$i]['references']) {
                case array_key_exists('image', $data[$i]['references']):
                    $proxy = $this->getReference(Image::class . '_' . $data[$i]['references']['image']);
                    return Media::createNewInstanceWithImage(
                        $proxy,
                        $proxy2,
                        $proxy3,
                        $data[$i]['fields']['isMain'],
                        $data[$i]['fields']['isPublished']
                    );
                    break;
                case array_key_exists('video', $data[$i]['references']):
                    $proxy = $this->getReference(Video::class . '_' . $data[$i]['references']['video']);
                    return Media::createNewInstanceWithVideo(
                        $proxy,
                        $proxy2,
                        $proxy3,
                        $data[$i]['fields']['isMain'],
                        $data[$i]['fields']['isPublished']
                    );
                    break;
            }
        });
        $manager->flush();
    }
}
