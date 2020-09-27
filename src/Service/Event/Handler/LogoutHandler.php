<?php

declare(strict_types=1);

namespace App\Service\Event\Handler;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Http\Logout\LogoutSuccessHandlerInterface;

/**
 * Class LogoutHandler.
 *
 * Handle user logout.
 */
class LogoutHandler implements LogoutSuccessHandlerInterface
{
    /**
     * @var FlashBagInterface
     */
    private $flashBag;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * LogoutHandler constructor.
     *
     * @param FlashBagInterface $flashBag
     * @param RouterInterface   $router
     *
     * @return void
     */
    public function __construct(FlashBagInterface $flashBag, RouterInterface $router)
    {
        $this->flashBag = $flashBag;
        $this->router = $router;
    }
    /**
     * {@inheritdoc}
     *
     * Define a callback to show a flash message when user logout.
     */
    public function onLogoutSuccess(Request $request): RedirectResponse
    {
        $this->flashBag->add(
            'success',
            'You logged out!' . "\n" . 'Hope to see you soon.'
        );
        return new RedirectResponse($this->router->generate('home'));
    }
}
