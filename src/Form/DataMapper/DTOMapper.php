<?php

declare(strict_types = 1);

namespace App\Form\DataMapper;

use App\Domain\DTO\AbstractReadableDTO;
use IteratorAggregate;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;

/**
 * class DTOMapper.
 *
 * Data Transfer Object (DTO) Mapper to map form data on demand.
 *
 * @see https://symfony.com/doc/current/form/data_mappers.html#using-the-mapper
 * @see https://github.com/symfony/symfony/blob/4.2/src/Symfony/Component/Form/DataMapperInterface.php
 */
class DTOMapper implements DataMapperInterface
{
    /**
     * @var PropertyListExtractorInterface an extractor to list properties
     */
    private $propertyListExtractor;

    /**
     * DTOMapper constructor.
     *
     * @param PropertyListExtractorInterface $propertyListExtractor
     */
    public function __construct(PropertyListExtractorInterface $propertyListExtractor)
    {
        $this->propertyListExtractor = $propertyListExtractor;
    }

    /**
     * {@inheritDoc}
     */
    public function mapDataToForms($data, $forms)
    {
        // A AbstractReadableDTO instance is expected.
        if (!is_subclass_of($data, AbstractReadableDTO::class)) {
            throw new \InvalidArgumentException('Expected data instance must extend "AbstractReadableDTO"!');
        }
        // Check form ArrayIterator instance
        if (!$forms instanceof FormInterface || !$forms instanceof IteratorAggregate) {
            throw new \InvalidArgumentException('Form object must be an instance of "FormInterface" or "IteratorAggregate"!');
        }
        /** @var FormInterface[]|IteratorAggregate $forms */
        $forms = iterator_to_array($forms);
        // initialize form field values
        $dtoProperties = $this->propertyListExtractor->getProperties($data);
        for ($i = 0; $i < \count($dtoProperties); $i ++) {
            $method = 'get'. ucfirst($dtoProperties[$i]);
            if (!method_exists(\get_class($data), $method)) {
                throw new \BadMethodCallException('Getter ' . $method . ' name called is unknown!');
            }
            // Use form instances dynamic setting with DTO dynamic corresponding getter name
            $forms[$dtoProperties[$i]]->setData($data->$method());
        }
        return $forms;
    }

    /**
     * {@inheritDoc}
     */
    public function mapFormsToData($forms, &$data)
    {
        // Check form ArrayIterator instance
        if (!$forms instanceof FormInterface || !$forms instanceof IteratorAggregate) {
            throw new \InvalidArgumentException('Form object must be an instance of "FormInterface" or "IteratorAggregate"!');
        }
        // A AbstractReadableDTO instance is expected.
        $dtoClassName = $forms->getConfig()->getDataClass();
        if (!is_subclass_of($dtoClassName, AbstractReadableDTO::class)) {
            throw new \InvalidArgumentException('Data object instance to create must extend "AbstractReadableDTO"!');
        }
        /** @var FormInterface[]|IteratorAggregate $forms */
        $forms = iterator_to_array($forms);
        $dtoProperties = $this->propertyListExtractor->getProperties($dtoClassName);
        $args = [];
        for ($i = 0; $i < \count($dtoProperties); $i ++) {
            $key = $dtoProperties[$i];
            if (isset($data[$key]) && \is_array($data[$key])) {
                if (!isset($forms[$key])) {
                    throw new \RuntimeException('Form data can not be mapped due to unmatched object property!');
                }
                $data[$key] = $forms[$key]->getData();
            }
            // Use form dynamic value
            $args[$i] = $data[$key];
        }
        // Return corresponding DTO instance
        return new $dtoClassName(...$args);
    }
}
