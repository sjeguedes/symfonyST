<?php

namespace App\DataFixtures;

use App\Entity\Trick;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;

/**
 * Class TrickFixtures
 * This class generates Trick entity fake set of data in database
 *
 * @package App\DataFixtures
 */
class TrickFixtures extends Fixture
{
    /**
     * @param ObjectManager $manager
     * @throws \Exception
     */
    public function load(ObjectManager $manager)
    {
        // create 45 Tricks
        for ($i = 1; $i <= 45; $i++) {
            $trick = new Trick();
            $trick->setName('Trick ' . $i);
            $trick->setDescription('This is description for Trick ' . $i);
            $trick->setCreationDate(new \DateTime("now"));
            $trick->setTrickGroupId(1);
            $manager->persist($trick);
        }
        $manager->flush();
    }
}
