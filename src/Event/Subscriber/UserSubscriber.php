<?php

declare(strict_types = 1);

namespace App\Event\Subscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

/**
 * Class UserSubscriber.
 *
 * Subscribe to events linked to user.
 */
class UserSubscriber implements EventSubscriberInterface
{
    /**
     * @var FlashBagInterface
     */
    private $flashBag;

    /**
     * UserSubscriber constructor.
     *
     * @param FlashBagInterface $flashBag
     *
     * @return void
     */
    public function __construct(
        FlashBagInterface $flashBag
    ) {
        $this->flashBag = $flashBag;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents() : array
    {
        return [
            SecurityEvents::INTERACTIVE_LOGIN => 'onSecurityInteractiveLogin'
        ];
    }

    /**
     * Define a callback to show a flash message with login form handler.
     *
     * @param InteractiveLoginEvent $event
     *
     * @return void
     */
    public function onSecurityInteractiveLogin(InteractiveLoginEvent $event) : void
    {
        $user = $event->getAuthenticationToken()->getUser();
        $userNickName = $user->getNickName();
        $message = 'Welcome on board ' . $userNickName . '!<br>You are successfully logged in.';
        $this->flashBag->add('success', $message);
    }
}