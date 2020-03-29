<?php

declare(strict_types = 1);

namespace App\Form\DataMapper;

use App\Domain\DTO\AbstractReadableDTO;
use ArrayAccess;
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
     *
     * @return array
     *
     * @throws \Exception
     */
    public function mapDataToForms($data, $forms) : array
    {
        // A AbstractReadableDTO instance is expected.
        if (!is_subclass_of($data, AbstractReadableDTO::class)) {
            throw new \InvalidArgumentException('Expected data instance must extend "AbstractReadableDTO"!');
        }
        /** @var FormInterface[]|IteratorAggregate $forms */
        $forms = iterator_to_array($forms);
        // initialize form field values
        $dtoProperties = $this->propertyListExtractor->getProperties($data);
        for ($i = 0; $i < \count($dtoProperties); $i ++) {
            $offset = ucfirst($dtoProperties[$i]);
            /** @var AbstractReadableDTO|ArrayAccess $data */
            $dtoPropertyValue = $data->offsetGet($offset);
            // Use form instances dynamic setting with DTO dynamic returned value corresponding to getter name
            $forms[$dtoProperties[$i]]->setData($dtoPropertyValue);
        }
        return $forms;
    }

    /**
     * {@inheritDoc}
     *
     * @return AbstractReadableDTO
     *
     * @throws \Exception
     */
    public function mapFormsToData($forms, &$data) : AbstractReadableDTO
    {
        // A AbstractReadableDTO instance is expected.
        /** @var FormInterface $forms */
        $dtoClassName = $forms->getConfig()->getDataClass();
        if (!is_subclass_of($dtoClassName, AbstractReadableDTO::class)) {
            throw new \InvalidArgumentException('Data object instance to create must extend "AbstractReadableDTO"!');
        }
        /** @var FormInterface[]|IteratorAggregate $forms */
        $forms = iterator_to_array($forms);
        $dtoProperties = $this->propertyListExtractor->getProperties($dtoClassName);
        // Re-order correctly form data to map as expected for DTO properties
        $data = array_map(function ($item) use ($data) {
            return $data[$item];
        }, $dtoProperties);
        $data = array_combine(array_values($dtoProperties), array_values($data));
        // Loop on properties
        $dtoPropertyValues = [];
        for ($i = 0; $i < \count($dtoProperties); $i ++) {
            $key = $dtoProperties[$i];
            // Object property name and form data key to map Comparison do not match
            if (!\array_key_exists($key, $data)) {
                throw new \RuntimeException('Form data can not be mapped due to unmatched object property!');
            }
            // Use form dynamic value to feed array from data with no needed transformation.
            $value = $forms[$key]->isSubmitted() ? $forms[$key]->getData() : $data[$key];
            $value = \is_string($value) && 0 === strlen($value) ? null : $value;
            $dtoPropertyValues[$i] = $value;
        }
        // Return corresponding DTO instance with splat operator
        return new $dtoClassName(...$dtoPropertyValues);
    }
}
