<?php
declare(strict_types = 1);

namespace App\Service\Event;

use App\Domain\Entity\User;
use Symfony\Component\EventDispatcher\Event;

/**
 * Class FormUnchangedEvent.
 *
 * The form.unchanged event is dispatched each time a form is submitted with unchanged data.
 *
 * Event is dispatched in FormSubscriber::onPreSubmit().
 */
class FormUnchangedEvent extends Event implements CustomEventInterface
{
    /**
     * Define a event name.
     */
    public const NAME = 'form.unchanged';

    /**
     * @var string
     */
    private $eventContext;

    /**
     * @var User
     */
    protected $user;

    /**
     * @var object|null
     */
    private $entityToUpdate;

    /**
     * UserEvent constructor.
     *
     * @param string     $eventContext
     * @param User       $user
     * @param object|null $entityToUpdate
     */
    public function __construct(string $eventContext, User $user, object $entityToUpdate = null)
    {
        $this->eventContext = $eventContext;
        $this->user = $user;
        $this->entityToUpdate = $entityToUpdate;
    }

    /**
     * {@inheritDoc}
     */
    public function getEventContext() : string
    {
        return $this->eventContext;
    }

    /**
     * @return User
     */
    public function getUser() : User
    {
        return $this->user;
    }

    /**
     * @return object|null
     */
    public function getEntityToUpdate() : ?object
    {
        return $this->entityToUpdate;
    }
}
