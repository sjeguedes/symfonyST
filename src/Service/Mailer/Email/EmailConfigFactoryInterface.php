<?php

declare(strict_types = 1);

namespace App\Service\Mailer\Email;

/**
 * Interface EmailConfigFactoryInterface.
 *
 * Define a contract for a factory method to create email configurations instances
 * which implement EmailConfigInterface.
 */
interface EmailConfigFactoryInterface
{
    /**
     * Create an email configuration instance depending on controller or action name.
     *
     * @param string $actionClassName a F.Q.C.N which determines a controller or action
     * @param string $actionContext   a context label to describe the email configuration purpose
     * @param array  $emailParameters the parameters used to configure an email
     *
     * @return EmailConfigInterface
     */
    public function createFromActionContext(string $actionClassName, string $actionContext, array $emailParameters) : EmailConfigInterface;
}
