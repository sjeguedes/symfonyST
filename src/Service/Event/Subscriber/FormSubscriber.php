<?php

declare(strict_types = 1);

namespace App\Service\Event\Subscriber;

use App\Domain\Entity\User;
use App\Domain\ServiceLayer\UserManager;
use App\Service\Event\CustomEventFactory;
use App\Service\Form\Type\Admin\UpdateProfileAvatarType;
use App\Service\Form\Type\Admin\UpdateProfileInfosType;
use ArrayAccess;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\DataMapperInterface;
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
        UpdateProfileAvatarType::class,
        UpdateProfileInfosType::class
    ];

    /**
     * @var FormInterface|null
     */
    private $currentForm;

    /**
     * @var DataMapperInterface
     */
    private $dataMapper;

    /**
     * @var bool
     */
    private $isUpdateFormAction;

    /**
     * @var bool
     */
    private $isUnChangedForm;

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
     *
     * @throws \Exception
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
        $isUpdateFormAction = \in_array(\get_class($formType), self::UPDATE_FORMS_LIST) ? true : false;
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
     *
     * @throws \Exception
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
     * - UpdateProfileInfosType (user profile update):
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
        // Is it an update form action with unchanged data?
        if ($this->isUpdateFormAction && $this->isUnChangedForm) {
            // Check form type to set the proper redirection
            $formType = $this->currentForm->getConfig()->getType()->getInnerType();
            $response = $this->setUnchangedFormActionResponse($formType);
        }
        // Use redirection or not
        return \is_null($response) ? $response : $event->setResponse($response);
    }

    /**
     * Define a callback before user submitted the form.
     *
     * Get possibly changed form data and cloned previous data model
     *
     * Cloning must be used carefully and be aware of principle that properties which contain object
     * point to the same reference of object used in original instance before copy!
     *
     * @param FormEvent $event
     *
     * @return void
     */
    public function onPreSubmit(FormEvent $event) : void
    {
        $form = $event->getForm();
        $formType = $form->getConfig()->getType()->getInnerType();
        // Check if an update form action matched
        if ($this->isUpdateFormAction($formType)) {
            // Get request data (form fields data)
            $formDataWithPossibleChange = $event->getData();
            // Get last initialized data model (if value is null when submitted first, instantiate manually a default empty data object)
            $previousDataModel = $event->getForm()->getData() ?? call_user_func($form->getConfig()->getOption('empty_data'), $form); // $form->getData()
            // Previous data model instance provided by $event->getForm()->getData() can not be set directly, because it is the same object (reference).
            // Its properties will change (it will become the new updated model) once the next events happen.
            $this->previousDataModel = clone $previousDataModel;
            $this->nextFormData = $formDataWithPossibleChange;
        }
    }

    /**
     * Define a callback when user submitted the form.
     *
     * Check if form data changed:
     * Compare valid previous data model instance with new valid data model instance with possible form change.
     *
     * Please data mapping is not mandatory: $form->getData() retrieves new data model, if in between, no modification was applied.
     * It is more a principle to learn about data mapping.
     *
     * @param FormEvent $event
     *
     * @return void
     *
     * @throws \Exception
     */
    public function onPostSubmit(FormEvent $event) : void
    {
        $form = $event->getForm();
        // Check previous data model is correctly set submitted and valid form context
        if ($this->isUpdateFormAction && $form->isValid()) {
            // Get the corresponding DTO based on possibly updated form data, to compare state before and after possible update
            $formDataWithPossibleChange = $this->nextFormData;
            $previousDataModel = $this->previousDataModel;
            // Get new form data model with possible change thanks to pre-submit event
            // This mapping is not really necessary because $form->getData() can be used instead without data model modification.
            $nextDataModel = $this->dataMapper->mapFormsToData($form, $formDataWithPossibleChange); // $form->getData()
            if ($this->isUnchangedForm($form, $previousDataModel, $nextDataModel)) {
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
            case UpdateProfileAvatarType::class:
            case UpdateProfileInfosType::class:
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
