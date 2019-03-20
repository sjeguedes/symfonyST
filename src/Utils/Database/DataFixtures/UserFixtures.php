<?php

declare(strict_types=1);

namespace App\Utils\Database\DataFixtures;

use App\Domain\Entity\User;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Class UserFixtures.
 *
 * Generate user fake starting set of data in database.
 */
class UserFixtures extends BaseFixture
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
        $array = $this->parseYamlFile('user_fixtures.yaml');
        $data = $array['users'];
        // Create users
        $this->createFixtures(User::class, \count($data), function($i) use($data) {
            return new User(
                $data[$i]['fields']['family_name'],
                $data[$i]['fields']['first_name'],
                $data[$i]['fields']['nick_name'],
                $data[$i]['fields']['email'],
                $data[$i]['fields']['password'],
                new \DateTime(sprintf("+%d days", $i - 1)),
                new \DateTime(sprintf("+%d days", $i - 1))
            );
        });
        $manager->flush();
    }
}
