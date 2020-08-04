<?php

declare(strict_types = 1);

namespace App\Service\Event\Listener;

use App\Action\Admin\UpdateTrickAction;
use App\Service\Event\FormUnchangedEvent;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

/**
 * Class UnchangedTrickListener.
 *
 * Listen to event linked to trick to update, when no change is made.
 *
 * Please note this listener must be configured as a service in services.yaml file configuration!
 * Then event dispatcher is able to register it with associated event.
 */
class UnchangedTrickListener
{
    /**
     * @var FlashBagInterface
     */
    private $flashBag;

    /**
     * @var Request
     */
    private $request;

    /**
     * UnchangedTrickListener constructor.
     *
     * @param FlashBagInterface $flashBag
     * @param RequestStack      $requestStack
     */
    public function __construct(FlashBagInterface $flashBag, RequestStack $requestStack)
    {
        $this->flashBag = $flashBag;
        $this->request = $requestStack->getCurrentRequest();

    }

    /**
     * Define a callback on form unchanged event.
     *
     * Show an info flash message to user who submitted a trick update form without change.
     *
     * @param FormUnchangedEvent $event
     *
     * @return void
     */
    public function onFormUnchanged(FormUnchangedEvent $event) : void
    {
        // Check if action corresponds to trick update page
        if ($isUpdateTrickAction = UpdateTrickAction::class === $this->request->attributes->get('_controller')) {
            $userNickName = $event->getUser()->getNickName();
            $trickToUpdate = $event->getEntityToUpdate();
            $trickName = $trickToUpdate->getName();
            $text = !\is_null($trickToUpdate)
                ? nl2br('as regards content for this trick called' . "\n" . '"' . $trickName . '"')
                : nl2br("\n" . 'about this trick content');
            $this->flashBag->add(
                'info',
                sprintf(
                    nl2br('Hey "%s",' . "\n" . 'please note you changed nothing %s!'),
                    htmlentities($userNickName, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                    $text
                )
            );
            // Avoid other event listeners or subscribers also listen this event!
            $event->stopPropagation();
        }
    }
}
