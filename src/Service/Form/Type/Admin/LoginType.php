<?php

declare(strict_types=1);

namespace App\Service\Form\Type\Admin;

use App\Domain\DTO\LoginUserDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class LoginType.
 *
 * Build a login form type.
 */
class LoginType extends AbstractType
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
            ->add('userName', TextType::class, [
            ])
            ->add('password', PasswordType::class, [
                'always_empty' => false
            ])
            ->add('rememberMe', CheckboxType::class, [
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
            'data_class'     => LoginUserDTO::class,
            'empty_data'     => function (FormInterface $form) {
                return new LoginUserDTO(
                    $form->get('userName')->getData(),
                    $form->get('password')->getData(),
                    $form->get('rememberMe')->getData()
                );
            },
            'required'        => false,
            // Disable automatic CSRF validation: this validation/protection is checked/done in form handler manually!
            'csrf_protection' => false,
            'csrf_field_name' => 'token',
            'csrf_token_id'   => 'login_token',
        ]);
    }
}
