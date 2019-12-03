<?php

declare(strict_types = 1);

namespace App\Event\Subscriber;

use App\Domain\DTO\AbstractReadableDTO;
use App\Domain\Entity\User;
use App\Domain\ServiceLayer\UserManager;
use App\Event\CustomEventFactory;
use App\Form\DataMapper\DTOMapper;
use App\Form\Type\Admin\UpdateProfileType;
use ArrayAccess;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
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
 */
class FormSubscriber implements EventSubscriberInterface
{
    const UPDATE_FORMS_LIST = [
        UpdateProfileType::class
    ];
    /**
     * @var DataMapperInterface|DTOMapper
     */
    private $dataMapper;

    /**
     * @var PropertyAccessorInterface
     */
    private $propertyAccessor;

    /**
     * @var PropertyListExtractorInterface used to list properties
     */
    private $propertyListExtractor;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var bool
     */
    private $unChangedForm;

    /**
     * @var UserManager
     */
    private $userService;

    /**
     * FormSubscriber constructor.
     *
     * @param PropertyAccessorInterface      $propertyAccessor
     * @param PropertyListExtractorInterface $propertyListExtractor
     * @param DataMapperInterface|DTOMapper  $dataMapper            an expected DTOMapper instance
     * @param RouterInterface                $router
     * @param UserManager                    $userService
     */
    public function __construct(
        PropertyAccessorInterface $propertyAccessor,
        PropertyListExtractorInterface $propertyListExtractor,
        DTOMapper $dataMapper,
        RouterInterface $router,
        UserManager $userService
    ) {
        $this->propertyAccessor = $propertyAccessor;
        $this->propertyListExtractor = $propertyListExtractor;
        $this->dataMapper = $dataMapper;
        $this->router = $router;
        $this->unChangedForm = true;
        $this->userService = $userService;
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
            FormEvents::PRE_SUBMIT  => 'onPreSubmit',
            FormEvents::POST_SUBMIT => 'onPostSubmit'
        ];
    }

    /**
     * Define a callback to check if form data changed when user starts submitting.
     *
     * @param FormEvent $event
     *
     * @return void
     */
    public function onPreSubmit(FormEvent $event) : void
    {
        $form = $event->getForm();
        $formType = $form->getConfig()->getType()->getInnerType();
        $formDataBeforeChange = $event->getData();
        $modelDataWhenSubmit = $form->getData();
        // Is an update form Matched and is data model a implementation of ArrayAccess?
        if ($this->isUpdateFormAction($formType) && $modelDataWhenSubmit instanceof ArrayAccess) {
            // Add the DTO data mapper to form on demand and only here!
            $formDataMapper = $this->addDataMapperToForm($form, $this->dataMapper);
            /// Get the corresponding DTO based on form data, to compare state before and after possible update
            $modelDataBeforeChange = $formDataMapper->mapFormsToData($form, $formDataBeforeChange);
               // Create and dispatch an event to inform about the fact user did not change his profile when submitting data.
            if ($this->isUnchangedForm($form, $modelDataBeforeChange, $modelDataWhenSubmit)) {
                // A user subscriber listens to this event.
                $this->createAndDispatchUnchangedUserProfileEvent();
            }
        }
    }

    /**
     * Define a callback to check if form data changed after user submitted data.
     *
     * @param FormEvent $event
     *
     * @return RedirectResponse|null
     */
    public function onPostSubmit(FormEvent $event) : ?RedirectResponse
    {
        $form = $event->getForm();
        $formType = $form->getConfig()->getType()->getInnerType();
        // Is an update form Matched?
        if ($this->isUpdateFormAction($formType)) {
            if ($form->isValid() && $this->unChangedForm) {
                // Redirect immediately to homepage after submit with unchanged valid data, to avoid the use of a success flash message
                // So use an info flash message instead, created in UserSubscriber.
                return new RedirectResponse($this->router->generate('home'));
            }
        }
        return null;
    }

    /**
     * Check if form action is an entity update.
     *
     * @param FormTypeInterface $formType
     *
     * @return bool
     */
    private function isUpdateFormAction(FormTypeInterface $formType) : bool
    {
        $isUpdateForm = \in_array(\get_class($formType), self::UPDATE_FORMS_LIST ) ? true : false;
        return $isUpdateForm;
    }

    /**
     * Check if form data changed when submitted.
     *
     * @param FormInterface $form
     * @param ArrayAccess   $modelDataBefore
     * @param ArrayAccess   $modelDataAfter
     *
     * @return bool
     */
    private function isUnchangedForm(FormInterface $form, ArrayAccess $modelDataBefore, ArrayAccess $modelDataAfter) : bool
    {
        $unChangedForm = true;
        $modelDataClassName = \get_class($form->getData());
        $dtoProperties = $this->propertyListExtractor->getProperties($modelDataClassName);
        foreach ($dtoProperties as $value) {
            $previousProperty = $this->propertyAccessor->getValue($modelDataBefore, $value);
            $updatedProperty = $this->propertyAccessor->getValue($modelDataAfter, $value);
            // At least one data changed in form before submitting.
            if ($previousProperty !== $updatedProperty) {
                $unChangedForm = false;
                break;
            }
        }
        $this->unChangedForm = $unChangedForm;
        return $unChangedForm;
    }

    /**
     * Add a data mapper to a particular form.
     *
     * @param FormInterface       $form
     * @param DataMapperInterface $dataMapper
     *
     * @return DataMapperInterface
     */
    private function addDataMapperToForm(FormInterface $form, DataMapperInterface $dataMapper) : DataMapperInterface
    {
        $formConfig = $form->getConfig();
        $formBuilder = $formConfig->getFormFactory()->createBuilder();
        $dataMapper = $formBuilder->setDataMapper($dataMapper)->getDataMapper();
        return $dataMapper;
    }

}
