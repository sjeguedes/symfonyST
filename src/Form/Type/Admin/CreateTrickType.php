<?php

declare(strict_types = 1);

namespace App\Form\Type\Admin;

use App\Domain\DTO\CreateTrickDTO;
use App\Domain\Entity\TrickGroup;
use App\Domain\ServiceLayer\TrickGroupManager;
use App\Form\Handler\CreateTrickHandler;
use App\Form\TypeToEmbed\ImageToCropType;
use App\Form\TypeToEmbed\VideoInfosType;
use App\Utils\Traits\UuidHelperTrait;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class CreateTrickType.
 *
 * Build a trick creation form type.
 */
class CreateTrickType extends AbstractType
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
     * CreateTrickType constructor.
     *
     * @param RequestStack      $requestStack
     * @param TrickGroupManager $trickGroupService the trick group entity service layer
     */
    public function __construct(RequestStack $requestStack, TrickGroupManager $trickGroupService)
    {
        $this->request = $requestStack->getCurrentRequest();
        $this->trickGroupService = $trickGroupService;
    }

    /**
     * Add single entity to array model transformer to a form data.
     *
     * This transformer aims at using the same DTO if "multiple" form option changes.
     *
     * @param FormBuilderInterface $formBuilder
     * @param string               $formName    a Form instance name
     *
     * @return void
     */
    private function addSingleEntityToArrayCustomDataTransformer(FormBuilderInterface $formBuilder, string $formName) : void
    {
        /** @var FormBuilderInterface$form */
        $form = $formBuilder->get($formName);
        $form->addModelTransformer(
            new CallbackTransformer(
                // Normalized data (transform)
                function ($uuidStrings) {
                    // Do not transform the (encoded) uuid string(s): (encoded) uuid strings can be a single or several (encoded) uuid strings in array
                    return !\is_null($uuidStrings) ? $uuidStrings : null;
                },
                // Model data (reverse transform)
                function ($entities) use ($form) {
                    if (\is_null($entities)) {
                        return null;
                    }
                    // Transform a single entity into an array: $entities can be a single entity or an array (collection) of several entities
                    return !$form->getOption('multiple') ? [$entities] : $entities;
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
                'multiple'      => false,
                'class'         => TrickGroup::class,
                // Order group names when feeding select
                'query_builder' => function () use ($trickGroupService) {
                    return $trickGroupService->getRepository()
                        ->createQueryBuilder('tr')
                        ->orderBy('tr.name', 'ASC');
                },
                // Show group names in select
                'choice_label' => 'name',
                // Use encoded uuid value to query entities
                'choice_value' => function (TrickGroup $trickGroup = null) {
                    return !\is_null($trickGroup) ? $this->encode($trickGroup->getUuid()) : '';
                },
                'placeholder'  => 'Choose an existing category'
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
                'prototype'      => true,
                // Custom root form options passed to entry type form
                'entry_options'  => [
                    'rootFormHandler' =>  $options['formHandler']
                ],
                //'by_reference'   => false,
                //'error_bubbling' => false
            ])
            ->add('videos', CollectionType::class, [
                'entry_type'     => VideoInfosType::class,
                'allow_add'      => true,
                'prototype_name' => '__videoIndex__',
                // Used here to access fields in templates and customize a particular prototype
                'prototype'      => true,
                //'by_reference'   => false
                //'error_bubbling' => false
            ]);

        // Add data transformer to "group" data.
        $this->addSingleEntityToArrayCustomDataTransformer($builder, 'group');
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
            'data_class'     => CreateTrickDTO::class,
            'empty_data'     => function (FormInterface $form) {
                return new CreateTrickDTO(
                    $form->get('group')->getData(),
                    $form->get('name')->getData(),
                    $form->get('description')->getData(),
                    $form->get('images')->getData(),
                    $form->get('videos')->getData()
                );
            },
            'required'        => false,
            'csrf_field_name' => 'token',
            'csrf_token_id'   => 'create_trick_token'
        ]);
        // Check "formHandler" option
        $resolver->setRequired('formHandler');
        $resolver->setAllowedValues('formHandler', function ($value) {
            if (!$value instanceof CreateTrickHandler) {
                return false;
            }
            return true;
        });
    }
}
