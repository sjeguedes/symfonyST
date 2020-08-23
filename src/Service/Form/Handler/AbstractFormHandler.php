<?php

declare(strict_types = 1);

namespace App\Service\Form\Handler;

use App\Domain\Entity\Trick;
use App\Domain\Entity\User;
use App\Domain\ServiceLayer\CommentManager;
use App\Domain\ServiceLayer\ImageManager;
use App\Domain\ServiceLayer\MediaManager;
use App\Domain\ServiceLayer\TrickManager;
use App\Domain\ServiceLayer\UserManager;
use App\Domain\ServiceLayer\VideoManager;
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
     * Define data keys and types to check which are used in form request process.
     */
    const DATA_CONFIG_TO_CHECK = [
        'commentService' => CommentManager::class,
        'imageService'   => ImageManager::class,
        'mediaService'   => MediaManager::class,
        'request'        => Request::class,
        'trickService'   => TrickManager::class,
        'trickToUpdate'  => Trick::class,
        'userService'    => UserManager::class,
        'userToUpdate'   => User::class,
        'videoService'   => VideoManager::class
    ];

    /**
     * @var FlashBagInterface
     */
    protected $flashBag;

    /**
     * @var FormInterface
     */
    protected $form;

    /**
     * @var string|array
     */
    protected $customError;

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
     * Check validity of all data and their types passed in form request process.
     *
     * Please note this method can be improved with use of OptionsResolver.
     *
     * @param array $data
     *
     * @return void
     */
    protected function checkNecessaryData(array $data) : void
    {
        $dataConfig = self::DATA_CONFIG_TO_CHECK;
        array_filter($data, function ($value, $key) use ($dataConfig) {
            // Check data type name
            if (\is_object($value) && !isset($dataConfig[$key])) {
                throw new \InvalidArgumentException('Data type name used in form request process is unknown!');
            }
            // Check data type
            if (\is_object($value) && !$value instanceof $dataConfig[$key]) {
                throw new \InvalidArgumentException('Data type used in form request process is not valid (does not match with its type)!');
            }
        }, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * {@inheritDoc}
     */
    public function initForm(array $data = null, string $formType = null, array $options = null) : FormHandlerInterface
    {
        // Set model data with $data parameter
        if (!\is_null($data)) {
            if (!method_exists($this, 'initModelData')) {
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
        if ($this->form->isSubmitted() && !$this->form->isValid()) {
            // Validation failed.
            $message = 'Validation failed!' . "\n" . 'Try to submit again by checking the form fields.';
            // Do not create a flash message in case of ajax form validation
            !$this->request->isXmlHttpRequest()
                ? $this->flashBag->add('danger', $message)
                : $this->customError = ['formError' => ['notification' => $message]];
            return false;
        }
        // Add custom validation
        if (method_exists($this, 'addCustomValidation')) {
            // Custom validation did not pass!
            if (false === $this->addCustomValidation($actionData)) {
                return false;
            }
        }
        // Add action to perform once form is validated
        if (method_exists($this, 'addCustomAction')) {
            $this->addCustomAction($actionData);
        }
        return true;
    }
}
