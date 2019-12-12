<?php

declare(strict_types = 1);

namespace App\Form\Type\Admin;

use App\Domain\DTO\RenewPasswordDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class RenewPasswordType.
 *
 * Build a password renewal form type.
 */
class RenewPasswordType extends AbstractType
{
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
            ->add('userName', TextType::class, [
                'disabled'        => false
            ])
            ->add('passwords', RepeatedType::class, [
                'type'            => PasswordType::class,
                'first_name'      => 'password',
                'second_name'     => 'confirmedPassword',
                'invalid_message' => 'Password and confirmation must match.',
                'options'         => ['always_empty' => false]
            ])
            ->add('token', HiddenType::class, [
                'inherit_data'    => true
            ]);
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
            'data_class'     => RenewPasswordDTO::class,
            'empty_data'     => function (FormInterface $form) {
                return new RenewPasswordDTO(
                    $form->get('userName')->getData(),
                    $form->get('passwords')->getData()
                );
            },
            'required'        => false,
            'csrf_field_name' => 'token',
            'csrf_token_id'   => 'renew_password_token',
        ]);
    }
}
