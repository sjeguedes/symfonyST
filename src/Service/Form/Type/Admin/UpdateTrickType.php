<?php

declare(strict_types = 1);

namespace App\Service\Form\Type\Admin;

use App\Domain\DTO\UpdateTrickDTO;
use App\Domain\Entity\TrickGroup;
use App\Domain\Entity\User;
use App\Domain\ServiceLayer\ImageManager;
use App\Domain\ServiceLayer\MediaTypeManager;
use App\Domain\ServiceLayer\TrickGroupManager;
use App\Domain\ServiceLayer\VideoManager;
use App\Service\Form\Handler\UpdateTrickHandler;
use App\Service\Form\TypeToEmbed\ImageToCropType;
use App\Service\Form\TypeToEmbed\VideoInfosType;
use App\Utils\Traits\UuidHelperTrait;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class UpdateTrickType.
 *
 * Build a trick update form type.
 */
class UpdateTrickType extends AbstractTrickType
{
    use UuidHelperTrait;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var TrickGroupManager
     */
    private $trickGroupService;

    /**
     * UpdateTrickType constructor.
     *
     * @param MediaTypeManager  $mediaTypeService,
     * @param ImageManager      $imageService
     * @param VideoManager      $videoService
     * @param RequestStack      $requestStack
     * @param TrickGroupManager $trickGroupService the trick group entity service layer
     */
    public function __construct(
        MediaTypeManager $mediaTypeService,
        ImageManager $imageService,
        VideoManager $videoService,
        RequestStack $requestStack,
        TrickGroupManager $trickGroupService
    )
    {
        parent::__construct($mediaTypeService, $imageService, $videoService);
        $this->request = $requestStack->getCurrentRequest();
        $this->trickGroupService = $trickGroupService;
    }

    /**
     * Configure a form builder for the type hierarchy.
     *
     * @param FormBuilderInterface $builder
     * @param array                $options
     *
     * @return void
     *
     * @see Entity choicelist without bringing the whole entity:
     * http://ghirardotti.fr/symfony2/2015/09/28/entity-choicelist-without-bringing-the-whole-entity/
     * @see For information: custom options passed from Parent form to child form:
     * https://github.com/symfony/symfony/issues/25675
     * https://stackoverflow.com/questions/25363926/symfony-form-with-form-collection-cannot-pass-options-array-into-sub-forms
     */
    public function buildForm(FormBuilderInterface $builder, array $options) : void
    {
        $trickGroupService = $this->trickGroupService;
        $builder
            ->add('group', EntityType::class, [
                'class'         => TrickGroup::class,
                // Order group names when feeding select
                'query_builder' => function () use ($trickGroupService) {
                    return $trickGroupService->getRepository()
                        ->createQueryBuilder('tr')
                        ->orderBy('tr.name', 'ASC');
                },
                // Show group names in select
                'choice_label'  => 'name',
                // Use encoded uuid value to query entities
                // Replace the need to use a setter for "group" corresponding UpdateTrickDTO property
                'choice_value'  => function (TrickGroup $group = null) {
                    return !\is_null($group) ? $this->encode($group->getUuid()) : '';
                },
                'placeholder'   => 'Choose an existing category'
            ])
            ->add('name', TextType::class, [
            ])
            ->add('description', TextareaType::class, [
            ])
            ->add('images', CollectionType::class, [
                'entry_type'     => ImageToCropType::class,
                'allow_add'      => true,
                'prototype_name' => '__imageIndex__',
                // Used here to access fields in templates and customize a particular prototype
                'prototype'      => true, // This is he default value but more explicit due to customization.
                // Custom root form options passed to entry type form
                'entry_options'  => [
                    'rootFormHandler' =>  $options['formHandler']
                ],
                // Maintain validation state at the collection form level, to be able to show errors near field
                'error_bubbling' => false,
                //'by_reference'   => false
            ])
            ->add('videos', CollectionType::class, [
                'entry_type'     => VideoInfosType::class,
                'allow_add'      => true,
                'prototype_name' => '__videoIndex__',
                // Used here to access fields in templates and customize a particular prototype
                'prototype'      => true, // This is he default value but more explicit due to customization.
                // Maintain validation state at the collection form level, to be able to show errors near field
                'error_bubbling' => false,
                //'by_reference'   => false
            ])
            ->add('token', HiddenType::class, [
                'inherit_data' => true
            ]);
        // Add dynamically a choice type to enable publication moderation for admin users
        if (\in_array(User::ADMIN_ROLE, $options['userRoles'])) {
            $builder->add('isPublished', ChoiceType::class, [
                'choices'    => [
                    // Replace the need to use a setter for "isPublished" corresponding UpdateTrickDTO property due to null default value
                    'Choose a moderation state'           => null, // default value
                    'Make this trick publicly accessible' => true,
                    'Keep this trick unpublished'         => false
                ],
            ]);
        }
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
            'data_class'     => UpdateTrickDTO::class,
            'empty_data'     => function (FormInterface $form) {
                return new UpdateTrickDTO(
                    $form->get('group')->getData(),
                    $form->get('name')->getData(),
                    $form->get('description')->getData(),
                    $form->get('images')->getData(),
                    $form->get('videos')->getData(),
                    // "false" by default for members which are not administrator (Select field is not available!)
                    $form->offsetExists('isPublished')
                        ? $form->get('isPublished')->getData() : false
                );
            },
            'required'        => false,
            // Disable automatic CSRF validation: this validation/protection is checked/done in form handler manually!
            'csrf_protection' => false,
            'csrf_field_name' => 'token',
            'csrf_token_id'   => 'update_trick_token'
        ]);
        // Check "formHandler" option
        $resolver->setRequired('formHandler');
        $resolver->setAllowedValues('formHandler', function ($value) {
            if (!$value instanceof UpdateTrickHandler) {
                return false;
            }
            return true;
        });
        // Check authenticated user "roles" option
        $resolver->setRequired('userRoles');
        $resolver->setAllowedTypes('userRoles', ['array']);
        $resolver->setAllowedValues('userRoles', function ($value) {
            foreach ($value as $role) {
                if (!\in_array($role, array_keys(User::ROLE_LABELS))) {
                    return false;
                }
            }
            return true;
        });
    }
}
