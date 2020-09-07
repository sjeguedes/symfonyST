<?php

declare(strict_types=1);

namespace App\Utils\Traits;

use Symfony\Component\HttpFoundation\Session\SessionInterface;

/*
 * Trait SessionHelperTrait.
 *
 * Enable simple data management with session.
 */
trait SessionHelperTrait
{

    /**
     * @var SessionInterface
     */
    private $session;

    /**
     * Enable use of session.
     *
     * @param SessionInterface $session
     *
     * @return void
     */
    public function setSession(SessionInterface $session): void
    {
        $this->session = $session;
        if (!$this->session->isStarted()) {
            $this->session->start();
        }
    }

    /**
     * Get initialized session.
     *
     * @return SessionInterface
     *
     * @throws \Exception
     */
    public function getSession(): SessionInterface
    {
        if (\is_null($this->session)) {
            throw new \RuntimeException('SessionInterface implementation must be set before!');
        }
        return $this->session;
    }

    /**
     * Store or replace data in session.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return void
     */
    public function storeInSession(string $name, $value): void
    {
        if (!$this->session->has($name)) {
            $this->session->set($name, $value);
        } else {
            $this->session->remove($name);
            $this->session->set($name, $value);
        }
    }
}
