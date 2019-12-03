<?php

declare(strict_types = 1);

namespace App\Domain\DTO;

use ArrayAccess;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;

/**
 * Class AbstractReadableDTO.
 *
 * Abstract Data Transfer Object used to read properties by combining a property accessor.
 *
 * @see https://www.doctrine-project.org/projects/doctrine-orm/en/2.6/cookbook/implementing-arrayaccess-for-domain-objects.html
 */
abstract class AbstractReadableDTO implements ArrayAccess
{
    /**
     * {@inheritDoc}
     */
    public function offsetExists($offset)
    {
        return isset($this->$offset);
    }

    /**
     * {@inheritDoc}
     *
     * @throws \Exception
     */
    public function offsetSet($offset, $value)
    {
        throw new \BadMethodCallException('Array access of class ' . \get_class($this) . ' is read-only!');
    }

    /**
     * {@inheritDoc}
     */
    public function offsetGet($offset)
    {
        return $this->{"get$offset"}();
    }

    /**
     * {@inheritDoc}
     *
     * @throws \Exception
     */
    public function offsetUnset($offset)
    {
        throw new \BadMethodCallException('Array access of class ' . \get_class($this) . ' is read-only!');
    }
}
