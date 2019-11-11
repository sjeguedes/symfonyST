<?php

declare(strict_types = 1);

namespace App\Form\Handler;

use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Interface FormHandlerInterface.
 *
 * Define a contract to set a form handler.
 */
interface FormHandlerInterface
{
    /**
     * Bind request and form to get all of the submitted data.
     *
     * @param Request $request
     *
     * @return FormInterface
     *
     * @throws \RuntimeException
     */
    public function bindRequest(Request $request) : FormInterface;

    /**
     * Get password renewal request form with a form factory.
     *
     * @return FormInterface
     *
     * @throws \RuntimeException
     */
    public function getForm() : FormInterface;

    /**
     * Initialize a form by creating it with a form factory.
     *
     * @param array|null  $data
     * @param string|null $formType the class name as F.Q.C.N
     * @param array|null  $options
     *
     * @return FormHandlerInterface
     *
     * @throws \RuntimeException
     */
    public function initForm(array $data = null, string $formType = null, array $options = null) : FormHandlerInterface;

    /**
     * Check if form handles current request.
     *
     * @return bool
     */
    public function isRequestHandled() : bool;

    /**
     * Deal with form request to return validation state only if request is bind.
     * Add any additional custom validations once constraints are validated.
     * Add any actions to perform when form is validated.
     *
     * @param array|null $actionData
     *
     * @return bool
     */
    public function processFormRequest(array $actionData = null) : bool;
}
