<?php

declare(strict_types = 1);

namespace App\Utils\Database\DataFixtures;

use App\Domain\Entity\MediaOwner;
use App\Domain\Entity\Trick;
use App\Domain\Entity\User;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Class MediaOwnerFixtures.
 *
 * Generate media owners fake starting set of data in database.
 */
class MediaOwnerFixtures extends BaseFixture implements DependentFixtureInterface
{
    /**
     * Get class dependencies with other entities.
     *
     * @return array
     */
    public function getDependencies() : array
    {
        return [
            TrickFixtures::class,
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
        $array = $this->parseYamlFile('media_owner_fixtures.yaml');
        $data = $array['media_owners'];
        // Create tricks
        $this->createFixtures(MediaOwner::class, \count($data), function ($i) use ($data) {
            switch ( $data[$i]['type']) {
                case 'trick':
                    $proxy = $this->getReference(Trick::class . '_' . $data[$i]['references']['trick']);
                    break;
                case 'user':
                    $proxy = $this->getReference(User::class . '_' . $data[$i]['references']['user']);
                    break;
                default:
                    throw new \InvalidArgumentException('MediaOwner owner type is unknown!');
            }
            return new MediaOwner($proxy);
        });
        $manager->flush();
    }
}
