<?php

declare(strict_types=1);

namespace App\Service\Mailer\Email;

/**
 * Interface EmailConfigFactoryInterface.
 *
 * Define a contract to configure email instances
 * which implement EmailConfigInterface.
 */
interface EmailConfigInterface
{
    /**
     * Merge initialized options and parameters with a options resolver
     * and add new parameters if necessary.
     *
     * First, call initOptions() in this method and then resolve options.
     *
     * @param array $parameters
     *
     * @return void
     */
    public function buildConfiguration(array $parameters): void;

    /**
     * Initialize options with a options resolver.
     *
     * @return void
     */
    public function initOptions(): void;
}
