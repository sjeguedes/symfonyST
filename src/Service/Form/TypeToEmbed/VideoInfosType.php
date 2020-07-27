<?php

declare(strict_types = 1);

namespace App\Service\Form\TypeToEmbed;

use App\Domain\DTO\AbstractReadableDTO;
use App\Domain\DTOToEmbed\VideoInfosDTO;
use App\Domain\ServiceLayer\VideoManager;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Class VideoInfosType.
 *
 * Build a video form type to be embedded in CollectionType form type.
 */
class VideoInfosType extends AbstractTrickCollectionEntryType
{
    /**
     * @var DataMapperInterface
     */
    private $dataMapper;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * @var VideoManager
     */
    private $videoService;

    /**
     * VideoInfosType constructor.
     *
     * @param DataMapperInterface $dataMapper
     * @param ValidatorInterface  $validator
     * @param VideoManager        $videoService
     */
    public function __construct(DataMapperInterface $dataMapper, ValidatorInterface $validator, VideoManager $videoService)
    {
        $this->dataMapper = $dataMapper;
        $this->validator = $validator;
        $this->videoService = $videoService;
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
            ->add('url', TextareaType::class, [
            ])
            ->add('description', TextType::class, [
            ])
            ->add('savedVideoName', HiddenType::class, [
            ])
            // "isPublished" property is set to true by default because it is not managed in project at this level!
            ->add('showListRank', HiddenType::class, [
                // Maintain validation state at the child form level, to be able to show errors near field
                'error_bubbling' => false
            ]);
        // Add data transformer to "showListRank" data.
        $this->addStringToIntegerCustomDataTransformer($builder, 'showListRank');
        // Use submit form event for video infos type to generate a unique name (savedVideoName) in field
        // based on validated URL field without global validation!
        $this->generateVideoUniqueName($builder);
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
                    $form->get('savedVideoName')->getData(),
                    $form->get('showListRank')->getData()
                );
            },
            'required' => false
        ]);
    }

    /**
     * Generate a unique name for current sub form validated video, without complete root form validation.
     *
     * Please note "savedImageName" field value is handled by this method!
     *
     * @param FormBuilderInterface $builder
     *
     * @return void
     */
    private function generateVideoUniqueName(FormBuilderInterface $builder) : void
    {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) {
            // Get current "video infos" form
            $currentVideoInfosForm = $event->getForm();
            // Get current video form data
            $formData = $event->getData();
            // Avoid issue with DTOMapper: Turn show list rank string into a real int, or if it the value is not an int,
            // define the value to 0 to be checked in validator!
            $formData['showListRank'] = ctype_digit((string) $formData['showListRank']) ? (int) $formData['showListRank'] : 0;
            // Get current feed videoInfosDTO instance with custom data mapper
            /** @var VideoInfosDTO|AbstractReadableDTO $videoInfosDataModel */
            $videoInfosDataModel = $this->dataMapper->mapFormsToData($currentVideoInfosForm, $formData);
            $urlViolationsList = $this->validator->validateProperty($videoInfosDataModel, 'url');
            $urlViolationsLength = $urlViolationsList->count();
            $isVideoURLViolation = 0 !== $urlViolationsLength ? true : false;
            // Video URL is valid, so generate a video unique name or keep current name if it is valid!
            if (!$isVideoURLViolation) {
                $isVideoNameEmpty = isset($formData['savedVideoName']) && empty($formData['savedVideoName']);
                $nameViolationsList = $this->validator->validateProperty($videoInfosDataModel, 'savedVideoName');
                $nameViolationsLength = $nameViolationsList->count();
                $isVideoNameViolation = 0 !== $nameViolationsLength ? true : false;
                // Define a video unique name based on URL
                $videoUniqueName = $this->videoService->generateUniqueVideoNameWithURL($videoInfosDataModel->getUrl());
                // Generate unique name only it does not exist yet, or it exists and it is not valid!
                $formData['savedVideoName'] = !$isVideoNameEmpty && !$isVideoNameViolation
                    ? $formData['savedVideoName'] : $videoUniqueName;
            } else {
                // Empty video name
                $formData['savedVideoName'] = '';
            }
            // Update global current "video infos" form data
            $event->setData($formData);
        });
    }
}
