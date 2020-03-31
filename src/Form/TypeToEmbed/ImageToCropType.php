<?php

declare(strict_types = 1);

namespace App\Form\TypeToEmbed;

use App\Domain\DTO\AbstractReadableDTO;
use App\Domain\DTOToEmbed\ImageToCropDTO;
use App\Domain\Entity\Image;
use App\Domain\Entity\User;
use App\Domain\ServiceLayer\ImageManager;
use App\Form\Type\Admin\CreateTrickType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Class ImageToCropType.
 *
 * Build a image to crop form type to be embedded in CollectionType form type.
 */
class ImageToCropType extends AbstractTrickCollectionEntryType
{
    /**
     * @var DataMapperInterface
     */
    private $dataMapper;

    /**
     * @var ImageManager
     */
    private $imageService;

    /**
     * @var Security
     */
    private $security;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * ImageToCropType constructor.
     *
     * @param DataMapperInterface $dataMapper
     * @param ImageManager        $imageService
     * @param Security            $security
     * @param ValidatorInterface  $validator
     */
    public function __construct(
        DataMapperInterface $dataMapper,
        ImageManager $imageService,
        Security $security,
        ValidatorInterface $validator
    ) {
        $this->dataMapper = $dataMapper;
        $this->imageService = $imageService;
        $this->security = $security;
        $this->validator = $validator;
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
            ->add('image', FileType::class, [
            ])
            ->add('description', TextType::class, [
            ])
            ->add('cropJSONData', HiddenType::class, [
                // Maintain validation state at the child form level, to be able to show errors near field
                'error_bubbling' => false
            ])
            ->add('imagePreviewDataURI', HiddenType::class, [
                // Maintain validation state at the child form level, to be able to show errors near field
                'error_bubbling' => false
            ])
            ->add('savedImageName', HiddenType::class, [
                // Maintain validation state at the child form level, to be able to show errors near field
                'error_bubbling' => false
            ])
            ->add('isMain', CheckboxType::class, [
                'empty_data'     => false,
                'false_values'   => [false]
            ])
            // Please "isPublished" property (set to true by default) because it is not managed in project at this level!
            ->add('showListRank', HiddenType::class, [
                // Maintain validation state at the child form level, to be able to show errors near field
                'error_bubbling' => false
            ]);

        // Add data transformer to "showListRank" data.
        $this->addStringToIntegerCustomDataTransformer($builder, 'showListRank');

        // Retrieve root form handler passed in parent form entry_options parameter
        $rootFormHandler = $options['rootFormHandler'];

        // Use submit form event for image to crop type to save each uploaded image on server without global validation!
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($builder, $rootFormHandler) {
            // User must be authenticated to upload a standalone file in application "gallery"!
            /** @var User|UserInterface $authenticatedUser */
            $authenticatedUser = $this->security->getUser();
            if (\is_null($authenticatedUser)) {
                return;
            }
            // Get current "image to crop" form
            $currentImageToCropForm = $event->getForm();

            // Get current image form data
            $formData = $event->getData();

            // Get the data model based on form data
            $imageToCropDataModel = $this->prepareAndCheckDataModelForDirectUpload($currentImageToCropForm, $formData, $rootFormHandler);
            // Image or/and cropJSON Data are not valid, so direct upload must no be enabled!
            if (\is_null($imageToCropDataModel)) {
                return;
            }
            // if no expected violation is found, save directly the uploaded file as a standalone media without complete root form validation
            $rootFormType = $currentImageToCropForm->getRoot()->getConfig()->getType()->getInnerType();
            switch ($rootFormType) {
                // Create a Trick image with the highest expected format
                case $rootFormType instanceof CreateTrickType:
                    $createdImageOnServer = $this->imageService->createTrickImage($imageToCropDataModel, 'trickBig', $authenticatedUser, true);
                    break;
                // Stop process for other root form types
                default:
                    return;
            }
            // Upload is a success: update mandatory form data associated to image to make a persistence until complete form validation
            if (!\is_null($createdImageOnServer)) {
                $formData = $this->updateMandatoryPersistentImageData($createdImageOnServer, $formData);
                // Update global current "image to crop" form data
                $event->setData($formData);
            }
        });
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
            'data_class'  => ImageToCropDTO::class,
            'empty_data'  => function (FormInterface $form) {
                return new ImageToCropDTO(
                    $form->get('image')->getData(),
                    $form->get('description')->getData(),
                    $form->get('cropJSONData')->getData(),
                    $form->get('imagePreviewDataURI')->getData(),
                    $form->get('savedImageName')->getData(),
                    $form->get('showListRank')->getData(),
                    $form->get('isMain')->getData()
                );
            },
            'required'    => false
        ]);
        // Check "rootFormHandler" option passed in parent form type entry_options parameter
        $resolver->setRequired('rootFormHandler');
    }

    /**
     * Prepare and check data model for a direct upload without complete form validation.
     *
     * Please note image and crop JSON data are controlled only to upload a selected image.
     *
     * @param FormInterface $imageToCropForm
     * @param array         $formData
     * @param object        $rootFormHandler
     *
     * @return ImageToCropDTO|null
     */
    private function prepareAndCheckDataModelForDirectUpload(FormInterface $imageToCropForm, array $formData, object $rootFormHandler) : ?ImageToCropDTO
    {
        // Turn show list rank string into a real int, or if it the value is not an int define the value to 0 to be checked in validator!
        $formData['showListRank'] = ctype_digit((string) $formData['showListRank']) ? (int) $formData['showListRank'] : 0;
        // Get into account checkbox case which has no value when unchecked!
        $formData['isMain'] = isset($formData['isMain']) ? (bool) $formData['isMain'] : false;
        // Get current feed imageToCropDTO instance with custom data mapper
        /** @var ImageToCropDTO|AbstractReadableDTO $imageToCropDataModel */
        $imageToCropDataModel = $this->dataMapper->mapFormsToData($imageToCropForm, $formData);
        // Check if there are violations as concerns "image" field value with manual validation on current imageToCropDTO instance.
        // Here, "directUpload" group validates only Image constraint (mime type, dimensions, size...) in corresponding yaml file!
        $violationsList = $this->validator->validateProperty($imageToCropDataModel, 'image', ['directUpload']);
        $violationsLength = $violationsList->count();
        $isImageViolation = 0 !== $violationsLength ? true : false;
        // Uploaded image is not valid, so stop process!
        if ($isImageViolation) {
            return null;
        }
        // Crop JSON data must be valid and coherent!
        $imageData = $imageToCropDataModel->getImage();
        $cropJSONData = $imageToCropDataModel->getCropJSONData();
        if (!\is_null($imageData) && !$isImageViolation) {
            // Crop JSON data must be valid!
            if (!$hasCoherentCropData = $rootFormHandler->checkCropData($imageData, $cropJSONData)) {
                return null;
            }
        }
        return $imageToCropDataModel;
    }

    /**
     * Update all mandatory data associated to uploaded image to persist a reference until complete form validation.
     *
     * Please note UploadedFile instance must be reset to avoid an incoherent upload violation error in form!
     *
     * @param Image $createdImageOnServer
     * @param array $formData
     *
     * @return array the updated form data
     */
    private function updateMandatoryPersistentImageData(Image $createdImageOnServer, array $formData) : array
    {
        // WARNING! Delete corresponding "UploadedFile" instance to avoid a "The file could not be uploaded." violation
        // due to temporary file which does not exist on server after direct upload anymore!
        $formData['image'] = null;
        // Add uploaded image filename (as a kind of "identifier") into "savedImageName" form data, to keep a reference later
        $formData['savedImageName'] = $createdImageOnServer->getName();
        // Add also uploaded image filename into "cropJSONData" form data provided by JavaScript, to keep a reference later
        $cropData = json_decode($formData['cropJSONData'], true);
        $cropData[0]['identifier'] = $createdImageOnServer->getName();
        $cropData = json_encode([$cropData[0]]);
        $formData['cropJSONData'] = $cropData;
        return $formData;
    }
}
