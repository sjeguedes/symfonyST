<?php

declare(strict_types = 1);

namespace App\Service\Form\Collection;

use App\Domain\DTO\AbstractReadableDTO;

/**
 * class DTOCollection.
 *
 * Data Transfer Object (DTO) Collection is used to handle a set of DTO instances.
 *
 * Inspired from:
 * @see https://www.sitepoint.com/collection-classes-in-php/
 * @see https://dev.to/drearytown/collection-objects-in-php-1cbk
 * @see https://medium.com/2dotstwice-connecting-the-dots/creating-strictly-typed-arrays-and-collections-in-php-37036718c921
 * @see https://www.php.net/manual/fr/class.arrayaccess.php
 * @see https://github.com/doctrine/collections/blob/master/lib/Doctrine/Common/Collections/ArrayCollection.php
 */
class DTOCollection implements \IteratorAggregate, \Countable, \ArrayAccess
{
    /*
     * @var array
     */
    private $items;

    /**
     * DTOCollection constructor.
     *
     * @param array|AbstractReadableDTO[]|null $collection
     *
     * @throws \Exception
     */
    public function __construct(?array $collection)
    {
        // Check validity of initialized collection!
        if (0 !== count($collection)) {
            array_filter($collection, function ($value, $key) {
                if (!is_int($key)) {
                    throw new \RuntimeException("Item key $key must always be an integer!");
                }
                if (!$value instanceof AbstractReadableDTO) {
                    throw new \RuntimeException("Item value $value must always be an instance of \"AbstractReadableDTO\"!");
                }
            }, ARRAY_FILTER_USE_BOTH);
        }
        $this->items = $collection;
    }

    /**
     * Add a new item in collection.
     *
     * @param AbstractReadableDTO $object
     * @param int|null            $key
     *
     * @return void
     *
     * @throws \Exception
     */
    public function add(AbstractReadableDTO $object, int $key = null) : void
    {
        if ($key == null) {
            $this->items[] = $object;
        } else {
            if (isset($this->items[$key])) {
                throw new \OutOfRangeException("Item key $key is already used!");
            } else {
                $this->items[$key] = $object;
            }
        }
    }

    /**
     * Check if a key exists in items array.
     *
     * @param int $key
     *
     * @return bool
     */
    public function contains(int $key) : bool
    {
        return isset($this->items[$key]) || array_key_exists($key, $this->items);
    }

    /**
     * {@inheritDoc}
     */
    public function count() : int
    {
        return count($this->items);
    }

    /**
     * Delete an existing item in collection.
     *
     * @param int                      $key
     * @param AbstractReadableDTO|null $object
     */
    public function delete($key, AbstractReadableDTO $object = null) : void
    {
        if (\is_null($key) && \is_null($object)) {
            throw new \InvalidArgumentException("At least, item key $key must be defined!");
        }
        if (!isset($this->items[$key])) {
            throw new \InvalidArgumentException("Item key $key is not valid!");
        }
        unset($this->items[$key]);
    }

    /**
     * Get a particular item in collection.
     *
     * @param $key
     *
     * @return AbstractReadableDTO|null
     */
    public function get($key) :?AbstractReadableDTO
    {
        return $this->items[$key] ?? null;
    }

    /**
     * Get an array of all objects.
     *
     * @return array
     */
    public function getAll() : array
    {
        return $this->items;
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator() : \Iterator
    {
        return new \ArrayIterator($this->items);
    }

    /**
     * Check if a particular instance exists in collection.
     *
     * @param AbstractReadableDTO $object
     *
     * @return bool
     */
    public function has(AbstractReadableDTO $object) : bool
    {
        $currentCollection = $this->getAll();
        for ($i = 0; $i < $this->count(); $i ++) {
            // Compare objects strictly
            if ($currentCollection[$i] === $object) {
                return true;
            }
        }
        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function offsetExists($offset) : bool
    {
        $offset = (int) $offset;
        return $this->contains($offset);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetGet($offset) : ?AbstractReadableDTO
    {
        $offset = (int) $offset;
        return $this->get($offset);
    }

    /**
     * {@inheritDoc}
     *
     * @throws \Exception
     */
    public function offsetSet($offset, $value) : void
    {
        $offset = (int) $offset;
        $this->add($value, $offset);
    }

    /**
     * {@inheritDoc}
     */
    public function offsetUnset($offset) : void
    {
        $offset = (int) $offset;
        $this->delete($offset);
    }
}
