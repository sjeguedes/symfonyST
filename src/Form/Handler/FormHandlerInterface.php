<?php

declare(strict_types = 1);

namespace App\Form\Handler;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\Request;

interface FormHandlerInterface
{
    /**
     * Initialize a form by creating it with a form factory.
     *
     * @param string     $formType the class name as F.Q.C.N
     * @param mixed|null $data
     * @param array      $options
     *
     * @return FormInterface
     */
    public function initForm(string $formType, $data = null, array $options = []) : FormInterface;

    /**
     * Bind request and form to get all of the submitted data.
     *
     * @param Request $request
     *
     * @return FormInterface
     */
    public function bindRequest(Request $request) : FormInterface;

    /**
     * Get password renewal request form with a form factory.
     *
     * @return FormInterface
     */
    public function getForm() : FormInterface;

    /**
     * Deal with form request to return validation state.
     *
     * @param Request $request
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function processFormRequestOnSubmit(Request $request) : bool;

    /**
     * Execute action purpose if form is validated.
     *
     * @param array $actionData an array of particular data to perform action
     * @param Request $request
     *
     * @return bool
     */
    public function executeFormRequestActionOnSuccess(array $actionData = null, Request $request = null) : bool;
}
