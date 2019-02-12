<?php
declare(strict_types=1);

namespace App\Utils\Database\DataFixtures;

use App\Domain\Entity\Trick;
use App\Domain\Entity\TrickGroup;
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
        return [TrickGroupFixtures::class];
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
            $trick = new Trick(
                $data[$i]['fields']['name'],
                $data[$i]['fields']['description'],
                $proxy,
                $data[$i]['fields']['slug'],
                new \DateTime(sprintf("+%d days", $i - 1)),
                new \DateTime(sprintf("+%d days", $i - 1))
            );
            return $trick;
        });
        $manager->flush();
    }
}
