<?php

declare(strict_types = 1);

namespace App\Form\TypeToEmbed;

use App\Domain\DTOToEmbed\VideoInfosDTO;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Class VideoInfosType.
 *
 * Build a video form type to be embedded in CollectionType form type.
 */
class VideoInfosType extends AbstractType
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
            ->add('url', UrlType::class, [
            ])
            ->add('description', TextType::class, [
            ])
            // Please "isPublished" property (set to true by default) because it is not managed in project at this level!
            ->add('showListRank', HiddenType::class, [
                // maintain validation state at the child form level, to be able to show errors near field
                'error_bubbling' => false
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
            'data_class'     => VideoInfosDTO::class,
            'empty_data'     => function (FormInterface $form) {
                return new VideoInfosDTO(
                    $form->get('url')->getData(),
                    $form->get('description')->getData(),
                    $form->get('showListRank')->getData()
                );
            },
            'required' => false
        ]);
    }
}
