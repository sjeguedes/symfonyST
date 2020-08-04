<?php

declare(strict_types = 1);

namespace App\Service\Form\Type\Admin;

use App\Domain\DTO\UpdateProfileAvatarDTO;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class UpdateProfileAvatarType.
 *
 * Build a user profile avatar update form type.
 */
class UpdateProfileAvatarType extends AbstractType
{
    /**
     * Define the use of AJAX request to process this form.
     */
    const IS_AVATAR_UPLOAD_AJAX_MODE = true;

    /**
     * @var EventSubscriberInterface
     */
    private $formSubscriber;

    /**
     * UpdateProfileInfosType constructor.
     *
     * @param EventSubscriberInterface $formSubscriber
     */
    public function __construct(EventSubscriberInterface $formSubscriber)
    {
        $this->formSubscriber = $formSubscriber;
    }

    /**
     * Add String to boolean view transformer to a form data.
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
                // View data (transform)
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
            ->add('avatar', FileType::class, [
              ])
            ->add('removeAvatar', HiddenType::class, [
            ])
            ->add('cropJSONData', HiddenType::class, [
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
            'data_class'     => UpdateProfileAvatarDTO::class,
            'empty_data'     => function (FormInterface $form) {
                // Enable manual call to closure (with the right default values) to avoid empty data to be set to null instead of object
                return new UpdateProfileAvatarDTO(
                    $form->get('avatar')->getData(),
                    $form->get('removeAvatar')->getData() ?? false,
                    $form->get('cropJSONData')->getData()
                );
            },
            'required'        => false,
            // Disable automatic CSRF validation: this validation/protection is checked/done in form handler manually!
            'csrf_protection' => false,
            'csrf_field_name' => 'token',
            'csrf_token_id'   => 'update_profile_avatar_token',
        ]);
    }
}
