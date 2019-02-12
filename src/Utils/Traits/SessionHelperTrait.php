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
    public function setSession(SessionInterface $session)
    {
        $this->session = $session;
        $this->session->start();
    }

    /**
     * Store or replace data in session.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return void
     */
    public function storeInSession(string $name, $value) : void
    {
        if (!$this->session->has($name)) {
            $this->session->set($name, $value);
        } else {
            $this->session->remove($name);
            $this->session->set($name, $value);
        }
    }

    /**
     * Get data from session.
     *
     * @param string $name
     *
     * @return mixed|null
     */
    public function getFromSession(string $name) : mixed
    {
        if (!$this->session->has($name)) {
            return null;
        }
        return $this->session->get($name);
    }

    /**
     * Get all session attributes.
     *
     * @return array
     */
    public function getAllFromSession() : array
    {
        return $this->session->all();
    }

    /*
     * Remove data from session.
     *
     * @param string $name
     *
     * @return void
     */
    public function removeFromSession(string $name) : void
    {
        if ($this->session->has($name)) {
            $this->session->remove($name);
        }
    }
}
