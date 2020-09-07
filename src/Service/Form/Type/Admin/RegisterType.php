<?php

declare(strict_types=1);

namespace App\Service\Form\Type\Admin;

use App\Domain\DTO\RegisterUserDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class RegisterType.
 *
 * Build a user registration form type.
 */
class RegisterType extends AbstractType
{
    /**
     * Configure a form builder for the type hierarchy.
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     *
     * @return void
     */
    public function buildForm(FormBuilderInterface $builder, array $options): void
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
            ->add('agreement', CheckboxType::class, [
                'empty_data'   => false,
                'false_values' => [false]
            ])
            ->add('token', HiddenType::class, [
                'inherit_data' => true
            ]);
    }

    /**
     * Configure options for this form type.
     *
     * @param OptionsResolver $resolver
     *
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'     => RegisterUserDTO::class,
            'empty_data'     => function (FormInterface $form) {
                return new RegisterUserDTO(
                    $form->get('familyName')->getData(),
                    $form->get('firstName')->getData(),
                    $form->get('userName')->getData(),
                    $form->get('email')->getData(),
                    $form->get('passwords')->getData(),
                    $form->get('agreement')->getData()
                );
            },
            'required'        => false,
            // Disable automatic CSRF validation: this validation/protection is checked/done in form handler manually!
            'csrf_protection' => false,
            'csrf_field_name' => 'token',
            'csrf_token_id'   => 'register_token',
        ]);
    }
}
