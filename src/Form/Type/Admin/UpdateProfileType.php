<?php

declare(strict_types = 1);

namespace App\Form\Type\Admin;

use App\Domain\DTO\UpdateProfileDTO;
use App\Domain\ServiceLayer\UserManager;
use App\Event\Subscriber\FormSubscriber;
use App\Form\DataMapper\DTOMapper;
use Symfony\Component\Form\AbstractType;
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
     * @param PropertyAccessorInterface      $propertyAccessor
     * @param PropertyListExtractorInterface $propertyListExtractor
     * @param RouterInterface                $router
     * @param UserManager                    $userService
     */
    public function __construct(
        PropertyAccessorInterface $propertyAccessor,
        PropertyListExtractorInterface $propertyListExtractor,
        RouterInterface $router,
        UserManager $userService
    ) {
        $this->propertyAccessor = $propertyAccessor;
        $this->propertyListExtractor = $propertyListExtractor;
        $this->router = $router;
        $this->userService = $userService;
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
            ->add('familyName',TextType::class, [
            ])
            ->add('firstName',TextType::class, [
            ])
            ->add('userName',TextType::class, [
            ])
            ->add('email',EmailType::class, [
            ])
            ->add('passwords',RepeatedType::class, [
                'type'            => PasswordType::class,
                'first_name'      => 'password',
                'second_name'     => 'confirmedPassword',
                'invalid_message' => 'Password and confirmation must match.'
            ])
            ->add('avatar',FileType::class, [
              ])
            ->add('token',HiddenType::class, [
                'inherit_data' => true
            ]);
        // Add custom form subscriber to this form events with user service layer dependency
        $builder->addEventSubscriber(
            new FormSubscriber(
                $this->propertyAccessor,
                $this->propertyListExtractor,
                new DTOMapper($this->propertyListExtractor),
                $this->router,
                $this->userService
        ));
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
                    $form->get('avatar')->getData()
                );
            },
            'required'        => false,
            'csrf_field_name' => 'token',
            'csrf_token_id'   => 'update_profile_token',
        ]);
    }
}
