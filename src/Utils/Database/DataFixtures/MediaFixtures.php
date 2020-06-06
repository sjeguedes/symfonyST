<?php

declare(strict_types = 1);

namespace App\Utils\Database\DataFixtures;

use App\Domain\Entity\Media;
use App\Domain\Entity\MediaOwner;
use App\Domain\Entity\MediaSource;
use App\Domain\Entity\MediaType;
use App\Domain\Entity\User;
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
            MediaOwnerFixtures::class,
            MediaSourceFixtures::class,
            MediaTypeFixtures::class,
            UserFixtures::class
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
        $this->createFixtures(Media::class, \count($data), function ($i) use ($data) {
            /** @var $proxy object|MediaOwner */
            $proxy = $this->getReference(MediaOwner::class . '_' . $data[$i]['references']['media_owner']);
            /** @var $proxy2 object|MediaSource */
            $proxy2 = $this->getReference(MediaSource::class . '_' . ($i + 1)); //$data[$i]['references']['media_source']
            /** @var $proxy3 object|MediaType */
            $proxy3 = $this->getReference(MediaType::class . '_' . $data[$i]['references']['media_type']);
            /** @var $proxy4 object|User */
            $proxy4 = $this->getReference(User::class . '_' . $data[$i]['references']['user']);
            return new Media(
                $proxy,
                $proxy2,
                $proxy3,
                $proxy4,
                $data[$i]['fields']['is_main'],
                $data[$i]['fields']['is_published'],
                $data[$i]['fields']['show_list_rank'],
                new \DateTime(sprintf("+%d days", $i - 1))
            );
        });
        $manager->flush();
    }
}
