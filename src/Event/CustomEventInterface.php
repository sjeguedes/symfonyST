<?php

declare(strict_types = 1);

namespace App\Event;

/**
 * Interface CustomEventInterface.
 *
 * Define a contract to dispatch a event based on context.
 */
interface CustomEventInterface
{
    /**
     * @return string a event context label to distinct cases
     */
    public function getEventContext() : string;
}
