<?php

declare(strict_types = 1);

namespace App\Form\Handler;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;

/**
 * Class AbstractFormHandler.
 *
 * Define Form Handler essential responsibilities.
 */
class AbstractFormHandler implements FormHandlerInterface
{
    /**
     * @var FlashBagInterface
     */
    protected $flashBag;

    /**
     * @var FormInterface
     */
    protected $form;

    /**
     * @var FormFactoryInterface
     */
    protected $formFactory;

    /**
     * @var string a F.Q.C.N
     */
    protected $formType;

    /**
     * @var Request
     */
    protected $request;

    /**
     * @var bool
     */
    private $requestHandled;


    /**
     * AbstractFormHandler constructor.
     *
     * @param FlashBagInterface    $flashBag
     * @param FormFactoryInterface $formFactory
     * @param string               $formType
     * @param RequestStack         $requestStack
     */
    public function __construct(
        FlashBagInterface $flashBag,
        FormFactoryInterface $formFactory,
        string $formType,
        RequestStack $requestStack
    ) {
        $this->flashBag = $flashBag;
        $this->form = null;
        $this->formFactory = $formFactory;
        $this->formType = $formType;
        $this->request = $requestStack->getCurrentRequest();
        $this->requestHandled = false;
    }

    /**
     * {@inheritDoc}
     */
    public function bindRequest(Request $request) : FormInterface
    {
        if (\is_null($this->form)) {
            throw new \RuntimeException('The form must be initialized first with "initForm" method!');
        }
         $this->requestHandled = true;
        return $this->form->handleRequest($request);
    }

    /**
     * {@inheritDoc}
     */
    public function initForm(array $data = null, string $formType = null, array $options = null) : FormHandlerInterface
    {
        // Set model data with $data parameter
        if (!\is_null($data)) {
            if (!method_exists($this,'initModelData')) {
                throw new \RuntimeException('Final form handler class must implement "InitModelDataInterface" to deal with custom data parameter!');
            }
            // Get particular model data object to pre-populate the form
            $data = $this->initModelData($data);
        }
        $formType = \is_null($formType) ? $this->formType : $formType;
        $options = $options ?? [];
        $this->form = $this->formFactory->create($formType, $data, $options);
        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function isRequestHandled() : bool
    {
        return $this->requestHandled;
    }

    /**
     * {@inheritDoc}
     */
    public function getForm() : FormInterface
    {
        if (\is_null($this->form)) {
            throw new \RuntimeException('The form must be initialized first with "initForm" method!');
        }
        return $this->form;
    }

    /**
     * {@inheritDoc}
     */
    public function processFormRequest(array $actionData = null) : bool
    {
        if (!$this->isRequestHandled()) {
            throw new \RuntimeException('The form handler must bind the request first!');
        }
        // Check constraints validation
        if (!$this->form->isValid()) {
            // Validation failed.
            $this->flashBag->add('danger', 'Form validation failed!<br>Try to login again by checking the fields.');
            return false;
        }
        // Add custom validation
        if (method_exists($this,'addCustomValidation')) {
            // Custom validation did not pass!
            if (false === $this->addCustomValidation($actionData)) {
                return false;
            }
        }
        // Add action to perform once form is validated
        if (method_exists($this,'addCustomAction')) {
            $this->addCustomAction($actionData);
        }
        return true;
    }
}
