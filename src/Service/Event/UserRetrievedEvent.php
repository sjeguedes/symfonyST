<?php
declare(strict_types=1);

namespace App\Service\Event;

use App\Domain\Entity\User;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class UserEvent.
 *
 * The user.retrieved event is dispatched each time a User instance is retrieved
 * and its data are available.
 *
 * Event is dispatched in UserManager::isPasswordRenewalRequestTokenAllowed().
 */
class UserRetrievedEvent extends Event implements CustomEventInterface
{
    /**
     * Define a event name.
     */
    public const NAME = 'user.retrieved';

    /**
     * @var string
     */
    private $eventContext;

    /**
     * @var User
     */
    protected $user;

    /**
     * UserEvent constructor.
     *
     * @param string $eventContext
     * @param User   $user
     */
    public function __construct(string $eventContext, User $user)
    {
        $this->eventContext = $eventContext;
        $this->user = $user;
    }

    /**
     * {@inheritDoc}
     */
    public function getEventContext(): string
    {
        return $this->eventContext;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }
}
