<?php

declare(strict_types=1);

namespace App\Service\Event\Subscriber;

use App\Domain\DTO\AbstractReadableDTO;
use App\Domain\DTO\UpdateTrickDTO;
use App\Domain\Entity\User;
use App\Domain\ServiceLayer\TrickManager;
use App\Domain\ServiceLayer\UserManager;
use App\Service\Event\CustomEventFactory;
use App\Service\Form\Collection\DTOCollection;
use App\Service\Form\Type\Admin\UpdateProfileAvatarType;
use App\Service\Form\Type\Admin\UpdateProfileInfosType;
use App\Service\Form\Type\Admin\UpdateTrickType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Event\FilterResponseEvent;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
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
        UpdateProfileInfosType::class,
        UpdateTrickType::class
    ];

    /**
     * @var FormInterface|null
     */
    private $currentForm;

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

    /**
     * @var RequestStack
     */
    private $requestStack;

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var TrickManager
     */
    private $trickService;

    /**
     * @var UserManager
     */
    private $userService;

    /**
     * FormSubscriber constructor.
     *
     * @param PropertyAccessorInterface      $propertyAccessor
     * @param PropertyListExtractorInterface $propertyListExtractor
     * @param RequestStack                   $requestStack
     * @param RouterInterface                $router
     * @param TrickManager                   $trickService
     * @param UserManager                    $userService
     */
    public function __construct(
        PropertyAccessorInterface $propertyAccessor,
        PropertyListExtractorInterface $propertyListExtractor,
        RequestStack $requestStack,
        RouterInterface $router,
        TrickManager $trickService,
        UserManager $userService
    ) {
        $this->currentForm = null;
        $this->isUpdateFormAction = false;
        $this->isUnChangedForm = false;
        $this->previousDataModel = null;
        $this->propertyAccessor = $propertyAccessor;
        $this->propertyListExtractor = $propertyListExtractor;
        $this->requestStack = $requestStack;
        $this->router = $router;
        $this->trickService = $trickService;
        $this->userService = $userService;
    }

    /**
     * Clone particular children objects in a cloned previous DTO model dedicated to update process.
     *
     * @param object $previousDataModel
     *
     * @return void
     *
     * @throws \Exception
     */
    private function cloneChildrenObjectsForUpdateContext(object $previousDataModel): void
    {
        // Switch previous DTO which corresponds to an update process
        switch ($previousDataModel) {
            // Clone for trick update
            case $previousDataModel instanceof UpdateTrickDTO:
                $previousDataModel->setGroup(clone $previousDataModel->getGroup());
                $imagesDTOCollection = new DTOCollection();
                foreach ($previousDataModel->getImages() as $key => $imageToCropDTO) {
                    $imagesDTOCollection[$key] = clone $imageToCropDTO;
                }
                $previousDataModel->setImages($imagesDTOCollection);
                $videosDTOCollection = new DTOCollection();
                foreach ($previousDataModel->getVideos() as $key => $videoInfosDTO) {
                    $videosDTOCollection[$key] = clone $videoInfosDTO;
                }
                $previousDataModel->setVideos($videosDTOCollection);
                break;
        }
    }

    /**
     * Compare properties values from two instances of the same class.
     *
     * @param string|null $className                          the common class shared between the two instances to compare
     * @param AbstractReadableDTO|null    $firstModel         a first $className instance
     * @param AbstractReadableDTO|null    $secondModel        a second $className instance
     * @param bool                        $isComparedStrictly a strict comparison mode for objects
     *
     * @return bool
     *
     * @throws \Exception
     */
    private function compareObjectsPropertiesValues(
        ?string $className,
        ?AbstractReadableDTO $firstModel,
        ?AbstractReadableDTO $secondModel,
        bool $isComparedStrictly = true
    ): bool {
        $modelDataClassName = !\is_null($className) ? $className : $this->currentForm->getConfig()->getDataClass();
        $isSameInstance = $firstModel instanceof $modelDataClassName && $secondModel instanceof $modelDataClassName;
        if (!\is_null($firstModel) && !\is_null($secondModel) && !$isSameInstance) {
            throw new \InvalidArgumentException('Both objects must be instances of the same class!');
        }
        // Check "null" values length to process correctly
        $result = array_filter([$firstModel, $secondModel], function ($item) {
            return null === $item;
        });
        switch (\count($result)) {
            case 1:
                return false;
            case 2:
                return true;
            default:
                $isIdentical = true;
                $propertiesList = $this->propertyListExtractor->getProperties($modelDataClassName);
                $propertiesListLength = \count($propertiesList);
                for ($i = 0; $i < $propertiesListLength; $i++) {
                    $value = $propertiesList[$i];
                    $firstModelPropertyValue = $this->propertyAccessor->getValue($firstModel, $value);
                    $secondModelPropertyValue = $this->propertyAccessor->getValue($secondModel, $value);
                    // Avoid issue with objects identifiers when comparison is strict!
                    if (!$isComparedStrictly && \is_object($firstModelPropertyValue)) {
                        if ($firstModelPropertyValue instanceof DTOCollection) {
                            foreach ($firstModelPropertyValue as $key => $DTO) {
                                $className = \get_class($firstModelPropertyValue[$key]);
                                $isIdentical = $this->compareObjectsPropertiesValues(
                                    $className,
                                    $firstModelPropertyValue[$key],
                                    $secondModelPropertyValue[$key],
                                    $isComparedStrictly
                                );
                                if (false === $isIdentical) break;
                            }
                        }
                        if ($firstModelPropertyValue != $secondModelPropertyValue) {
                            $isIdentical = false;
                            break;
                        }
                     // Compare properties strictly
                    } else {
                        if ($firstModelPropertyValue !== $secondModelPropertyValue) {
                            $isIdentical = false;
                            break;
                        }
                    }
                }
                return $isIdentical;
        }
    }

    /**
     * Create and dispatch an event when a user submits an unchanged updated profile form.
     *
     * @param FormEvent $event
     *
     * @return void
     *
     * @throws \Exception
     */
    private function createAndDispatchUnchangedUserProfileEvent(FormEvent $event): void
    {
        /** @var User $authenticatedUser */
        $authenticatedUser = $this->userService->getAuthenticatedMember();
        $this->userService->createAndDispatchUserEvent(
            CustomEventFactory::USER_WITH_UNCHANGED_UPDATED_PROFILE,
            $authenticatedUser
        );
    }

    /**
     * Create and dispatch an event when a user submits an unchanged updated trick form.
     *
     * @param FormEvent $event
     *
     * @return void
     *
     * @throws \Exception
     */
    private function createAndDispatchUnchangedTrickContentEvent(FormEvent $event): void
    {
        $form = $event->getForm();
        $formOptions = $form->getConfig()->getOptions();
        $updatedTrick = isset($formOptions['trickToUpdate']) ? $formOptions['trickToUpdate'] : null;
        /** @var User $authenticatedUser */
        $authenticatedUser = $this->userService->getAuthenticatedMember();
        $this->trickService->createAndDispatchTrickEvent(
            CustomEventFactory::TRICK_WITH_UNCHANGED_UPDATED_CONTENT,
            $updatedTrick,
            $authenticatedUser
        );
     }

    /**
     * {@inheritdoc}
     */
    public static function getSubscribedEvents(): array
    {
        return [
            FormEvents::PRE_SET_DATA => 'onPreSetData',
            FormEvents::PRE_SUBMIT   => 'onPreSubmit',
            FormEvents::POST_SUBMIT  => 'onPostSubmit',
            KernelEvents::EXCEPTION  => 'onKernelException',
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
    private function isUpdateFormAction(FormTypeInterface $formType): bool
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
     * @param FormInterface         $form
     * @param AbstractReadableDTO   $modelDataBefore
     * @param AbstractReadableDTO   $modelDataAfter
     * @param bool                  $isComparedStrictly a strict comparison mode for objects
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function isUnchangedForm(
        FormInterface $form,
        AbstractReadableDTO $modelDataBefore,
        AbstractReadableDTO $modelDataAfter,
        bool  $isComparedStrictly = true
    ): bool {
        $modelDataClassName = $form->getConfig()->getDataClass();
        // Check if form is unchanged or not
        $isUnChangedForm = $this->compareObjectsPropertiesValues(
            $modelDataClassName,
            $modelDataBefore,
            $modelDataAfter,
            $isComparedStrictly
        );
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
    public function onPreSetData(FormEvent $event): void
    {
        // Store current form to share it in methods where it is not possible to get this data.
        $this->currentForm = $event->getForm();
    }

    /**
     * Define a callback when user submitted a form which generated an exception.
     *
     * @param GetResponseForExceptionEvent $event
     *
     * @return void
     *
     * @throws /Exception
     */
    public function onKernelException(GetResponseForExceptionEvent $event): void
    {
        // Catch exception thrown by updated forms and throw a new custom exception
        // To avoid response management with "onKernelResponse" callback!
        $actionClassName = $event->getRequest()->attributes->get('_controller');
        /*if (preg_match('/Update/', $actionClassName)) {
                $exception = $event->getException();
                throw new \Exception(
                    sprintf(
                        "A technical issue happened during update form handling! %s",
                        $exception->getMessage()
                    )
                );
        }*/
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
    public function onKernelResponse(FilterResponseEvent $event): ?RedirectResponse
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
            $response = $this->setUnchangedFormActionResponse($formType, $event);
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
     *
     * @throws \Exception
     */
    public function onPreSubmit(FormEvent $event): void
    {
        $form = $event->getForm();
        $formType = $form->getConfig()->getType()->getInnerType();
        // Check if an update form action matched
        if ($this->isUpdateFormAction($formType)) {
            // CAUTION! new form fields data (array) are set in "$event->getData();"
            // Get last initialized data model (if value is null when submitted first,
            // instantiate manually a default empty data object)
            $emptyDataOption = $form->getConfig()->getOption('empty_data');
            $previousDataModel = $event->getForm()->getData() ?? call_user_func($emptyDataOption, $form); // $form->getData()
            // Previous data model instance provided by $event->getForm()->getData() cannot be set directly,
            // because it is the same object (reference).
            // Its properties will change (it will become the new updated model) once the next events happen.
            $this->previousDataModel = clone $previousDataModel;
            $this->cloneChildrenObjectsForUpdateContext($this->previousDataModel);
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
    public function onPostSubmit(FormEvent $event): void
    {
        // Get the form with potential changes (CAUTION! During pre submit event, it was the previous submitted form!)
        $form = $event->getForm();
        $formType = $form->getConfig()->getType()->getInnerType();
        $formTypeClassName = \get_class($formType);
        // Check previous data model is correctly set submitted and valid form context
        if ($this->isUpdateFormAction && $form->isValid()) {
            // Get the previous corresponding DTO before potential form changes
            $previousDataModel = $this->previousDataModel;
            // Get new form data model with possible change thanks to pre-submit event
            $nextDataModel = $form->getData();
            // Check with no strict comparison (set to false) due to previous data model cloning
            if ($this->isUnchangedForm($form, $previousDataModel, $nextDataModel, false)) {
                // Check called form type to create and dispatch new custom events from here!
                switch ($formTypeClassName) {
                    case UpdateProfileAvatarType::class:
                    case UpdateProfileInfosType::class:
                        // A user subscriber listens to this event.
                        $this->createAndDispatchUnchangedUserProfileEvent($event);
                        break;
                    case UpdateTrickType::class:
                        // A trick subscriber listens to this event.
                        $this->createAndDispatchUnchangedTrickContentEvent($event);
                        break;
                }
            }
        }
    }

    /**
     * Redirect to current form page after submit, if form is valid and unchanged.
     *
     * @param FormTypeInterface   $formType
     * @param FilterResponseEvent $event
     *
     * @return RedirectResponse|null
     */
    private function setUnchangedFormActionResponse(FormTypeInterface $formType, FilterResponseEvent $event): ?RedirectResponse
    {
        // Redirect to the correct url
        $formTypeClassName = \get_class($formType);
        // Master request is used to get initial attributes parameters
        $request = $this->requestStack->getMasterRequest();
        // Check called form type
        switch ($formTypeClassName) {
            case UpdateProfileAvatarType::class:
            case UpdateProfileInfosType::class:
                $response = new RedirectResponse(
                    $event->getRequest()->getPathInfo()
                );
                break;
            case UpdateTrickType::class:
                $response = new RedirectResponse(
                    // Here, "$event->getRequest()->getPathInfo()" is also more pragmatic instead of router,
                    // if the same URL is kept, but use of router (demo) can be very useful
                    // in certain cases with master request!
                    $this->router->generate(
                        'update_trick',
                        [
                            'mainRoleLabel' => $request->attributes->get('mainRoleLabel'),
                            'slug'          => $request->attributes->get('slug'),
                            'encodedUuid'   => $request->attributes->get('encodedUuid')
                        ]
                    )
                );
                break;
            default:
                $response = null;
        }
        return $response;
    }
}
