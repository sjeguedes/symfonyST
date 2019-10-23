<?php

declare(strict_types = 1);

namespace App\Form\Handler;

use App\Form\Type\Admin\LoginType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class LoginHandler.
 *
 * Handle the form request when a user tries to login.
 * Call any additional actions.
 */
final class LoginHandler extends AbstractFormHandler
{
    /**
     * @var FormFactoryInterface
     */
    protected $formFactory;

    /**
     * @var FormInterface
     */
    protected $form;

    /**
     * LoginHandler constructor.
     *
     * @param FormFactoryInterface $formFactory
     */
    public function __construct(FormFactoryInterface $formFactory)
    {
        $this->formFactory = $formFactory;
        $this->form = $this->initForm(LoginType::class);
    }

    /**
     * {@inheritDoc}
     *
     * @see: CSRF token is checked in App\Service\Security\LoginFormAuthenticationManager.
     */
    public function processFormRequestOnSubmit(Request $request) : bool
    {
        $validProcess = $this->getForm()->isValid() ? true : false;
        return $validProcess;
    }

    /**
     * {@inheritDoc}
     */
    public function executeFormRequestActionOnSuccess(array $actionData = null, Request $request = null) : bool
    {
        // Do stuff if necessary.
    }
}
