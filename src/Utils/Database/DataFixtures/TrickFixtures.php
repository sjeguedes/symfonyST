<?php

declare(strict_types=1);

namespace App\Utils\Database\DataFixtures;

use App\Domain\Entity\Trick;
use App\Domain\Entity\TrickGroup;
use App\Domain\Entity\User;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Class TrickFixtures.
 *
 * Generate trick fake starting set of data in database.
 */
class TrickFixtures extends BaseFixture implements DependentFixtureInterface
{
    /**
     * Get class dependencies with other entities.
     *
     * @return array
     */
    public function getDependencies() : array
    {
        return [
            TrickGroupFixtures::class,
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
        $array = $this->parseYamlFile('trick_fixtures.yaml');
        $data = $array['tricks'];
        // Create tricks
        $this->createFixtures(Trick::class, \count($data), function($i) use($data) {
            $proxy = $this->getReference(TrickGroup::class . '_' . $data[$i]['references']['trick_group']);
            $proxy2 = $this->getReference(User::class . '_' . $data[$i]['references']['user']);
            return new Trick(
                $data[$i]['fields']['name'],
                $data[$i]['fields']['description'],
                $proxy,
                $proxy2,
                $data[$i]['fields']['slug'],
                new \DateTime(sprintf("+%d days", $i - 1))
            );
        });
        $manager->flush();
    }
}
