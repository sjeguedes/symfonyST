<?php

declare(strict_types = 1);

namespace App\Event\Subscriber;

use App\Action\Admin\RenewPasswordAction;
use App\Action\Admin\UpdateProfileAction;
use App\Domain\Entity\User;
use App\Domain\ServiceLayer\UserManager;
use App\Event\CustomEventFactory;
use App\Event\FormUnchangedEvent;
use App\Event\UserRetrievedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\GetResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;
use Symfony\Component\Security\Http\SecurityEvents;

/**
 * Class UserSubscriber.
 *
 * Subscribe to events linked to user.
 *
 * Please note this subscriber is auto configured as a service due to services.yaml file configuration!
 * So event dispatcher does not need to register it with associated event.
 */
class UserSubscriber implements EventSubscriberInterface
{
    /**
     * Define a custom session key to check first access to password renewal page.
     */
    private const PASSWORD_RENEWAL_FIRST_ACCESS = 'PasswordRenewalFirstAccess';

    /**
     * @var string|null
     */
    private $calledAction;

    /**
     * @var FlashBagInterface
     */
    private $flashBag;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * @var UserManager
     */
    private $userService;

    /**
     * UserSubscriber constructor.
     *
     * @param UserManager       $userService
     * @param RequestStack      $requestStack
     * @param SessionInterface  $session
     */
    public function __construct(
        RequestStack $requestStack,
        SessionInterface $session,
        UserManager $userService
    ) {
        $this->flashBag = $session->getFlashBag();
        $this->request = $requestStack->getCurrentRequest();
        $this->session = $session;
        $this->userService = $userService;
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents() : array
    {
        return [
            FormUnchangedEvent::NAME          => 'onFormUnchanged',
            KernelEvents::REQUEST             => 'onKernelRequest',
            SecurityEvents::INTERACTIVE_LOGIN => 'onSecurityInteractiveLogin',
            UserRetrievedEvent::NAME          => 'onUserRetrieved'
        ];
    }

    /**
     * Define a callback on form unchanged event.
     *
     * Show an info flash message to user who submitted a form without change.
     *
     * @param FormUnchangedEvent $event
     *
     * @return void
     */
    public function onFormUnchanged(FormUnchangedEvent $event) : void
    {
        // Check if action corresponds to user update profile page
        $isUpdateProfileAction = UpdateProfileAction::class === $this->request->attributes->get('_controller');
        if ($isUpdateProfileAction) {
            $userNickName = $event->getUser()->getNickName();
            $this->flashBag->add('info', sprintf('Hey <strong>%s</strong>!<br>Please note you changed nothing about your profile.', $userNickName));
        }
    }

    /**
     * Define a callback on request event.
     *
     * Remove all outdated users session custom vars which are defined
     * to check password renewal first access page.
     *
     * @param GetResponseEvent $event
     *
     * @return void
     *
     * @throws \Exception
     */
    public function onKernelRequest(GetResponseEvent $event) : void
    {
        // Clean unneeded password renewal page first access users session vars
        if ($event->getRequest()->getSession()->has(self::PASSWORD_RENEWAL_FIRST_ACCESS)) {
            $this->cleanPasswordRenewalFirstAccess();
        }
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
        $this->flashBag->add('success', sprintf('Welcome on board <strong>%s</strong>!<br>You logged in successfully.', $userNickName));
    }

    /**
     * Define a callback on user retrieved event.
     *
     * Create or add password renewal page first access session var for a particular user.
     *
     * @param UserRetrievedEvent $event
     *
     * @return void
     */
    public function onUserRetrieved(UserRetrievedEvent $event) : void
    {
        // Create a user password renewal page first access session var
        $isPasswordRenewalAction = RenewPasswordAction::class === $this->request->attributes->get('_controller');
        $isPasswordRenewalContext = CustomEventFactory::USER_ALLOWED_TO_RENEW_PASSWORD === $event->getEventContext();
        if ($isPasswordRenewalAction && $isPasswordRenewalContext) {
            $this->addPasswordRenewalFirstAccessMessage($event->getUser());
        }
    }

    /**
     * Set a flash message when user first access the password renewal page.
     *
     * @param User $identifiedUser
     *
     * @return void
     *
     * @see Please note session namespaced attributes bag can be used instead of attributes bag:
     * https://symfony.com/doc/current/components/http_foundation/sessions.html#namespaced-attributes
     * https://symfony.com/doc/current/session.html
     */
    private function addPasswordRenewalFirstAccessMessage(User $identifiedUser) : void
    {
        // Set first access message
        $customSessionKey = self::PASSWORD_RENEWAL_FIRST_ACCESS;
        // Set a custom session var for all users with an empty array, if it does not exist.
        if (!$this->session->has($customSessionKey)) {
            $this->session->set($customSessionKey, []);
        }
        // Handle session attributes bag
        $customSessionVar = $this->session->get($customSessionKey);
        $key = $identifiedUser->getRenewalToken();
        $value = $identifiedUser->getRenewalRequestDate();
        // Custom identified user var in custom session var is not set, then add it!
        if (!array_key_exists($key, $customSessionVar)) {
            // Update custom session var after user entry was created.
            $customSessionVar[$key] = $value;
            $this->session->set($customSessionKey, $customSessionVar);
            // Add flash message
            $this->flashBag->add(
                'info',
                sprintf('Well done <strong>%s</strong>!<br>Please fill in the form below<br>to renew your password!', $identifiedUser->getUsername())
            );
        }
    }

    /**
     * Clean all first access custom session variables
     * dedicated to the password renewal page.
     *
     * Principle is based on user outdated password renewal request date.
     * So with this context, user variable has no reason to exist anymore.
     *
     * @return void
     *
     * @throws \Exception
     *
     * @see Please note session namespaced attributes bag can be used instead of attributes bag:
     * https://symfony.com/doc/current/components/http_foundation/sessions.html#namespaced-attributes
     * https://symfony.com/doc/current/session.html
     */
    private function cleanPasswordRenewalFirstAccess() : void
    {
        $customSessionKey = self::PASSWORD_RENEWAL_FIRST_ACCESS;
        // Handle session attributes bag
        $customSessionVar = $this->session->get($customSessionKey);
        // Remove all outdated users custom vars
        $filteredArray = array_filter($customSessionVar, function ($value) {
            // Password renewal updated date is outdated or null, so user can not access password renewal page anymore.
            $renewalRequestDate = $value;
            // Then there is no need to keep user session custom var.
            return false === $this->userService->isPasswordRenewalRequestOutdated($renewalRequestDate);
        });
        // if filtered custom session var is an empty array then remove it, otherwise update custom session var after user entry was filtered and removed.
        0 == \count($filteredArray) ? $this->session->remove($customSessionKey) : $this->session->set($customSessionKey, $filteredArray);
    }
}
