<?php

declare(strict_types = 1);

namespace App\Event\Subscriber;

use App\Domain\DTO\AbstractReadableDTO;
use App\Domain\Entity\User;
use App\Domain\ServiceLayer\UserManager;
use App\Event\CustomEventFactory;
use App\Form\Type\Admin\UpdateProfileType;
use ArrayAccess;
use http\Exception\RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\FormConfigInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class FormSubscriber.
 *
 * Subscribe to events linked to form.
 *
 * Please note this subscriber is auto configured as a service due to services.yaml file configuration!
 * So event dispatcher does not need to register it with associated event.
 *
 * @see https://github.com/symfony/symfony/issues/20770
 * to understand form and model data change depending on form events:
 * $eventData = $event->getData();
 * $formData = $event->getForm()->getData();
 */
class FormSubscriber implements EventSubscriberInterface
{
    /**
     * Define update (edit) forms list.
     */
    const UPDATE_FORMS_LIST = [
        UpdateProfileType::class
    ];

    /**
     * @var FormInterface|null
     */
    private $currentForm;

    /**
     * @var DataMapperInterface
     */
    private $dataMapper;

    /*
     * @var AbstractReadableDTO
     */
    private $previousDataModel;

    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    /**
     * @var PropertyListExtractorInterface
     */
    private $propertyListExtractor;

    /*
     * @var AbstractReadableDTO
     */
    private $nextFormData;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var bool
     */
    private $isUpdateFormAction;

    /**
     * @var bool
     */
    private $isUnChangedForm;

    /**
     * @var UserManager
     */
    private $userService;

    /**
     * FormSubscriber constructor.
     *
     * @param PropertyAccessorInterface      $propertyAccessor
     * @param PropertyListExtractorInterface $propertyListExtractor
     * @param DataMapperInterface            $dataMapper            an expected custom DTOMapper instance
     * @param RouterInterface                $router
     * @param UserManager                    $userService
     */
    public function __construct(
        PropertyAccessorInterface $propertyAccessor,
        PropertyListExtractorInterface $propertyListExtractor,
        DataMapperInterface $dataMapper,
        RouterInterface $router,
        UserManager $userService
    ) {
        $this->currentForm = null;
        $this->isUpdateFormAction = false;
        $this->isUnChangedForm = false;
        $this->previousDataModel = null;
        $this->propertyAccessor = $propertyAccessor;
        $this->propertyListExtractor = $propertyListExtractor;
        $this->nextFormData = null;
        $this->dataMapper = $dataMapper;
        $this->router = $router;
        $this->userService = $userService;
    }

    /**
     * Compare properties values from two instances of the same class.
     *
     * @param string|null $className   the common class shared between the two instances to compare
     * @param object      $firstModel  a first $className instance
     * @param object      $secondModel a second $className instance
     *
     * @return bool
     */
    private function compareObjectsPropertiesValues(?string $className, object $firstModel, object $secondModel) : bool
    {
        $modelDataClassName = !\is_null($className) ? $className : $this->currentForm->getConfig()->getDataClass();
        if (!$firstModel instanceof $modelDataClassName || !$firstModel instanceof $modelDataClassName) {
            throw new \InvalidArgumentException('The two objects must be instances of the same class!');
        }
        $isIdentical = true;
        $propertiesList = $this->propertyListExtractor->getProperties($modelDataClassName);
        for ($i = 0; $i < \count($propertiesList); $i ++) {
            $value = $propertiesList[$i];
            $firstModelPropertyValue = $this->propertyAccessor->getValue($firstModel, $value);
            $secondModelPropertyValue = $this->propertyAccessor->getValue($secondModel, $value);
            // At least one value is not the same.
            if ($firstModelPropertyValue !== $secondModelPropertyValue) {
                $isIdentical = false;
                break;
            }
        }
        return $isIdentical;
    }

    /**
     * Create and dispatch an event when a user submits an unchanged updated profile.
     *
     * @return void
     */
    private function createAndDispatchUnchangedUserProfileEvent() : void
    {
        /** @var User $authenticatedUser */
        $authenticatedUser = $this->userService->getAuthenticatedMember();
        $this->userService->createAndDispatchUserEvent(CustomEventFactory::USER_WITH_UNCHANGED_UPDATED_PROFILE, $authenticatedUser);
    }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents() : array
    {
        return [
            FormEvents::PRE_SET_DATA => 'onPreSetData',
            FormEvents::PRE_SUBMIT   => 'onPreSubmit',
            FormEvents::POST_SUBMIT  => 'onPostSubmit',
            KernelEvents::RESPONSE   => 'onKernelResponse',
        ];
    }

    /**
     * Check if form action is an update (edit).
     *
     * @param FormTypeInterface $formType
     *
     * @return bool
     */
    private function isUpdateFormAction(FormTypeInterface $formType) : bool
    {
        $isUpdateFormAction = \in_array(\get_class($formType), self::UPDATE_FORMS_LIST ) ? true : false;
        // Feed form subscriber update form action property
        $this->isUpdateFormAction = $isUpdateFormAction;
        return $isUpdateFormAction;
    }

    /**
     * Check if form data changed when submitted.
     *
     * Please note ArrayAccess implementation (which exists thanks to AbstractReadableDTO) combined to array_diff() function
     * can be used to compare possible change instead of use of property list extractor and accessor.
     *
     * @param FormInterface $form
     * @param ArrayAccess   $modelDataBefore
     * @param ArrayAccess   $modelDataAfter
     *
     * @return bool
     */
    private function isUnchangedForm(FormInterface $form, ArrayAccess $modelDataBefore, ArrayAccess $modelDataAfter) : bool
    {
        $modelDataClassName = $form->getConfig()->getDataClass();
        // Check if form is unchanged or not
        $isUnChangedForm = $this->compareObjectsPropertiesValues($modelDataClassName, $modelDataBefore, $modelDataAfter);
        // Feed form subscriber property, and possibly set a redirect response thanks to kernel response event
        $this->isUnChangedForm = $isUnChangedForm;
        return $isUnChangedForm;
    }

    /**
     * Define a callback on form pre-populated data event to simply get the current form.
     *
     * Get the current form which is used.
     *
     * @param FormEvent $event
     *
     * @return void
     */
    public function onPreSetData(FormEvent $event) : void
    {
        // Store current form to share it in methods where it is not possible to get this data.
        $this->currentForm = $event->getForm();
    }

    /**
     * Define a callback on response event.
     *
     * Redirect to current form page after submit, if form is valid and unchanged.
     *
     * This is used for:
     * - UpdateProfileType (user profile update):
     * -- Redirect immediately to the same form page after submit with unchanged valid data,
     * -- to avoid the use of a success flash message with redirection to homepage.
     * -- So use an info flash message instead, created in UserSubscriber.
     *
     * @param FilterResponseEvent $event
     *
     * @return RedirectResponse|null
     */
    public function onKernelResponse(FilterResponseEvent $event) : ?RedirectResponse
    {
        $response = null;
        // Check if it is not a form action
        if (\is_null($this->currentForm)) {
            return $response;
        }
        // Is an update form Matched with unchanged data?
        if ($this->isUpdateFormAction && $this->isUnChangedForm) {
            // Check form type to set the proper redirection
            $formType = $this->currentForm->getConfig()->getType()->getInnerType();
            $response = $this->setUnchangedFormActionResponse($formType);
        }
        // Use redirection or not
        return \is_null($response) ? $response : $event->setResponse($response);
    }

    /**
     * Define a callback to check if form data changed when user submitted the form.
     *
     * Compare valid previous model data instance with new valid model data instance with possible form change.
     *
     * @param FormEvent $event
     *
     * @return void
     */
    public function onPreSubmit(FormEvent $event) : void
    {
        $form = $event->getForm();
        $formType = $form->getConfig()->getType()->getInnerType();
        $dataClassName = $form->getConfig()->getDataClass();
        // Is an update form Matched and is data model a implementation of ArrayAccess thanks to AbstractReadableDTO?
        if (true === $this->isUpdateFormAction($formType) && is_subclass_of($dataClassName, AbstractReadableDTO::class)) {
            // Get request data (form fields data)
            $formDataWithPossibleChange = $event->getData();
            // Get last initialized data model
            $previousDataModel = $event->getForm()->getData(); // $form->getData()
            // Instance provided by $event->getForm()->getData() can not be used directly to set previous data model,
            // because it is the same object and its properties change once the next events happen.
            $this->previousDataModel = clone $previousDataModel;
            $this->nextFormData = $formDataWithPossibleChange;
        }
    }

    public function onPostSubmit(FormEvent $event) : void
    {
        $form = $event->getForm();
        $formConfig = $form->getConfig();
        // Check previous data model is correctly set submitted and valid form context
        if ($form->isValid() && $this->isUpdateFormAction) {
            // Get the corresponding DTO based on possibly updated form data, to compare state before and after possible update
            $formDataWithPossibleChange = $this->nextFormData;
            $previousDataModel = $this->previousDataModel;
            // Get new form data with possible change thanks to pre-submit event
            $nextDataModel = $this->dataMapper->mapFormsToData($form, $formDataWithPossibleChange);
            $isUnchangedForm = $this->isUnchangedForm($form, $previousDataModel, $nextDataModel);
            if ($isUnchangedForm) {
                // A user subscriber listens to this event.
                $this->createAndDispatchUnchangedUserProfileEvent();
            }
        }
    }

    /**
     * Redirect to current form page after submit, if form is valid and unchanged.
     *
     * @param FormTypeInterface $formType
     *
     * @return RedirectResponse|null
     */
    private function setUnchangedFormActionResponse(FormTypeInterface $formType) : ?RedirectResponse
    {
        // Redirect to the correct url
        $formTypeClassName = \get_class($formType);
        switch ($formTypeClassName) {
            case UpdateProfileType::class:
                /** @var User $authenticatedUser */
                $authenticatedUser = $this->userService->getAuthenticatedMember();
                $mainRoleLabel = lcfirst($authenticatedUser->getMainRoleLabel());
                $response = new RedirectResponse($this->router->generate('update_profile', ['mainRoleLabel' => $mainRoleLabel]));
                break;
            default:
                $response = null;
        }
        return $response;
    }
}
