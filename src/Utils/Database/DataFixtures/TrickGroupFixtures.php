<?php

declare(strict_types=1);

namespace App\Utils\Database\DataFixtures;

use App\Domain\Entity\TrickGroup;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Class TrickGroupFixtures.
 *
 * Generate trick group fake starting set of data in database.
 */
class TrickGroupFixtures extends BaseFixture
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
        $array = $this->parseYamlFile('trick_group_fixtures.yaml');
        $data = $array['trick_groups'];
        // Create trick groups
        $this->createFixtures(TrickGroup::class, \count($data), function($i) use($data) {
            return new TrickGroup(
                $data[$i]['fields']['name'],
                $data[$i]['fields']['description'],
                new \DateTime(sprintf("+%d days", $i - 1)),
                new \DateTime(sprintf("+%d days", $i - 1))
            );
        });
        $manager->flush();
    }
}
