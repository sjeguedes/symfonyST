<?php

declare(strict_types = 1);

namespace App\Service\Event;

use Symfony\Component\EventDispatcher\Event;

/**
 * Interface CustomEventFactoryInterface.
 *
 * Define a contract for a factory method to create custom events instances
 * which implement CustomEventInterface.
 */
interface CustomEventFactoryInterface
{
    /**
     * Create a instance for all custom event objects.
     *
     * @param string $eventContext
     * @param array  $eventParameters
     *
     * @return CustomEventInterface|Event|null the event instance
     */
    public function createFromContext(string $eventContext, array $eventParameters) : ?CustomEventInterface;
}
