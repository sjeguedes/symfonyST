<?php

declare(strict_types = 1);

namespace App\Form\Type\Admin;

use App\Domain\DTO\UpdateProfileDTO;
use App\Domain\ServiceLayer\UserManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Symfony\Component\PropertyInfo\PropertyListExtractorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class UpdateProfileType.
 *
 * Build a user profile update form type.
 */
class UpdateProfileType extends AbstractType
{
    /**
     * @var EventSubscriberInterface
     */
    private $formSubscriber;

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
     * @var UserManager
     */
    private $userService;

    /**
     * UpdateProfileType constructor.
     *
     * @param EventSubscriberInterface       $formSubscriber
     * @param PropertyAccessorInterface      $propertyAccessor
     * @param PropertyListExtractorInterface $propertyListExtractor
     * @param RouterInterface                $router
     * @param UserManager                    $userService
     */
    public function __construct(
        EventSubscriberInterface $formSubscriber,
        PropertyAccessorInterface $propertyAccessor,
        PropertyListExtractorInterface $propertyListExtractor,
        RouterInterface $router,
        UserManager $userService
    ) {
        $this->formSubscriber = $formSubscriber;
        $this->propertyAccessor = $propertyAccessor;
        $this->propertyListExtractor = $propertyListExtractor;
        $this->router = $router;
        $this->userService = $userService;
    }

    /**
     * Add String to boolean model transformer to a form data.
     *
     * @param FormBuilderInterface $formBuilder
     * @param string               $formName    a Form instance name
     *
     * @return void
     */
    private function addStringToBoolCustomDataTransformer(FormBuilderInterface $formBuilder, string $formName) : void
    {
        $formBuilder
            ->get($formName)
            ->addViewTransformer(
            new CallbackTransformer(
                //  View data (transform)
                function ($boolAsString) {
                    if (\is_null($boolAsString)) {
                        $boolAsString = false;
                    }
                    // Transform the bool to string
                    return (false === $boolAsString || 0 === $boolAsString) ? '0' : '1';
                },
                // Normalized data (reverse transform)
                function ($stringAsBool) {
                    if (!\in_array($stringAsBool, ['false', '0', 'true', '1'])) {
                        return false;
                    }
                    // Transform the string back to a bool
                    return ('0' === $stringAsBool || 'false' === $stringAsBool) ? false : true;
                }
            )
        );
    }

    /**
     * Configure a form builder for the type hierarchy.
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options) : void
    {
        $builder
            ->add('familyName', TextType::class, [
            ])
            ->add('firstName', TextType::class, [
            ])
            ->add('userName', TextType::class, [
            ])
            ->add('email', EmailType::class, [
            ])
            ->add('passwords', RepeatedType::class, [
                'type'            => PasswordType::class,
                'first_name'      => 'password',
                'second_name'     => 'confirmedPassword',
                'invalid_message' => 'Password and confirmation must match.',
                'options'         => ['always_empty' => false]
            ])
            ->add('avatar', FileType::class, [
              ])
            ->add('removeAvatar', HiddenType::class, [
            ])
            ->add('token', HiddenType::class, [
                'inherit_data' => true
            ]);
        // Add data transformer to "removeAvatar" data.
        $this->addStringToBoolCustomDataTransformer($builder, 'removeAvatar');
        // Add custom form subscriber to this form events with dependencies
        $builder->addEventSubscriber($this->formSubscriber);
    }

    /**
     * Configure options for this form type.
     *
     * @param OptionsResolver $resolver
     *
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver) : void
    {
        $resolver->setDefaults([
            'data_class'     => UpdateProfileDTO::class,
            'empty_data'     => function (FormInterface $form) {
                return new UpdateProfileDTO(
                    $form->get('familyName')->getData(),
                    $form->get('firstName')->getData(),
                    $form->get('userName')->getData(),
                    $form->get('email')->getData(),
                    $form->get('passwords')->getData(),
                    $form->get('avatar')->getData(),
                    $form->get('removeAvatar')->getData()
                );
            },
            'required'        => false,
            'csrf_field_name' => 'token',
            'csrf_token_id'   => 'update_profile_token',
        ]);
    }
}
