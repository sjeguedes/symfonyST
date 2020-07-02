<?php

declare(strict_types = 1);

namespace App\Utils\Database\DataFixtures;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;

/**
 * Class BaseFixture.
 *
 * Abstract class to be extended by fixtures classes.
 */
abstract class BaseFixture extends Fixture
{
    /**
     * @var ObjectManager;
     */
    private $manager;

    /**
     * @var string
     */
    private $yamlFilePath;

    /**
     * BaseFixture constructor.
     *
     * @param ParameterBagInterface  $parameterBag
     */
    public function __construct(ParameterBagInterface $parameterBag)
    {
        $this->yamlFilePath = $parameterBag->get('app_data_fixtures_yaml_dir');
    }

    /**
     * Load entity manager.
     *
     * @param ObjectManager $manager
     */
    public function load(ObjectManager $manager) : void
    {
        $this->manager = $manager;
        $this->loadData($manager);
    }

    /**
     * Parse a yaml file to load fixture data
     *
     * @param string $dataFile
     *
     * @return array
     */
    protected function parseYamlFile(string $dataFile) : array
    {
        return Yaml::parseFile( $this->yamlFilePath . $dataFile);
    }

    /**
     * Method to be called in class to load fixtures.
     *
     * @param ObjectManager $manager
     *
     * @return mixed
     */
    abstract protected function loadData(ObjectManager $manager);

    /**
     * Creates multiple entity instance with parameters.
     *
     * @param string $className
     * @param int $count
     * @param callable $factory
     *
     * @return void
     *
     * @see Doctrine\Common\DataFixtures\AbstractFixture for addReference() method
     */
    protected function createFixtures(string $className, int $count, callable $factory) : void
    {
        for ($i = 0; $i < $count; $i++) {
            $entity = $factory($i);
            $this->manager->persist($entity);
            // Used to reference fixtures in between (inherited from AbstractFixture)
            $this->addReference($className . '_' . ($i + 1), $entity);
        }
    }
}
