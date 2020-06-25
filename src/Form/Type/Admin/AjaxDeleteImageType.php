<?php

declare(strict_types = 1);

namespace App\Form\Type\Admin;

use App\Domain\DTO\AjaxDeleteImageDTO;
use App\Domain\Entity\MediaOwner;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class AjaxDeleteImageType.
 *
 * Build an image deletion form type.
 */
class AjaxDeleteImageType extends AbstractType
{
    /**
     * Add String to uuid model transformer from a form data.
     *
     * @param FormBuilderInterface $formBuilder
     * @param string               $formName    a Form instance name
     *
     * @return void
     */
    private function addStringToUuidCustomDataTransformer(FormBuilderInterface $formBuilder, string $formName) : void
    {
        $formBuilder
            ->get($formName)
            ->addModelTransformer(
                new CallbackTransformer(
                    // Normalized data (transform)
                    function ($uuidAsString) {
                        if (\is_null($uuidAsString)) {
                            return null;
                        }
                        // Transform the uuid back to a string
                        /** @var UuidInterface $uuidAsString */
                        return $uuidAsString->toString();
                    },
                    // Model data (reverse transform)
                    function ($stringAsUuid) {
                        if (\is_null($stringAsUuid)) {
                            return null;
                        }
                        // Transform the string to uuid
                        return Uuid::isValid($stringAsUuid) ? Uuid::fromString($stringAsUuid) : null;
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
            ->add('uuid', HiddenType::class, [
            ])
            ->add('name', HiddenType::class, [
            ])
            ->add('mediaOwnerType', HiddenType::class, [
            ])
            ->add('token', HiddenType::class, [
                'inherit_data' => true
            ]);
        $router = $options['router'];
        $action = $router->generate('delete_image', ['mainRoleLabel' => $options['userMainRoleLabel']]);
        // Define a particular action
        $builder->setMethod('DELETE')->setAction($action);
        // Add data transformer to "uuid" data.
        $this->addStringToUuidCustomDataTransformer($builder, 'uuid');
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
            'data_class'     => AjaxDeleteImageDTO::class,
            'empty_data'     => function (FormInterface $form) {
                return new AjaxDeleteImageDTO(
                    $form->get('uuid')->getData(),
                    $form->get('name')->getData(),
                    $form->get('mediaOwnerType')->getData()
                );
            },
            'required'        => false,
            // Disable automatic CSRF validation: this validation/protection is checked/done in form handler manually!
            'csrf_protection' => false,
            'csrf_field_name' => 'token',
            'csrf_token_id'   => 'ajax_delete_image_token',
        ]);
        // Check "router" option
        $resolver->setRequired('router');
        $resolver->setAllowedValues('router', function ($value) {
            if (!$value instanceof RouterInterface) {
                return false;
            }
            return true;
        });
        // Check authenticated user "main role label" option
        $resolver->setRequired('userMainRoleLabel');
        $resolver->setAllowedTypes('userMainRoleLabel', 'string');
    }
}
