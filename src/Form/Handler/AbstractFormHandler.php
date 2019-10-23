<?php

declare(strict_types = 1);

namespace App\Form\Handler;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Class AbstractFormHandler.
 *
 * Define Form Handler responsibilities.
 */
abstract class AbstractFormHandler implements FormHandlerInterface
{
    /**
     * @var FormFactoryInterface
     */
    protected $formFactory;

    /**
     * @var FormInterface
     */
    protected $form;

    public function initForm(string $formType, $data = null, array $options = []) : FormInterface
    {
        return $this->form = $this->formFactory->create($formType, $data, $options);
    }

    /**
     * {@inheritDoc}
     */
    public function bindRequest(Request $request) : FormInterface
    {
        return $this->form->handleRequest($request);
    }

    /**
     * {@inheritDoc}
     */
    public function getForm() : FormInterface
    {
        return $this->form;
    }

    /**
     * {@inheritDoc}
     */
    abstract public function processFormRequestOnSubmit(Request $request) : bool;

    /**
     * {@inheritDoc}
     */
    abstract public function executeFormRequestActionOnSuccess(array $actionData = null, Request $request = null) : bool;
}
