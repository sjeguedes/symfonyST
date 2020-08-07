<?php

declare(strict_types = 1);

namespace App\Service\Form\TypeToEmbed;

use App\Domain\DTO\AbstractReadableDTO;
use App\Domain\DTOToEmbed\ImageToCropDTO;
use App\Domain\Entity\Image;
use App\Domain\Entity\Media;
use App\Domain\Entity\MediaOwner;
use App\Domain\Entity\MediaSource;
use App\Domain\Entity\Trick;
use App\Domain\ServiceLayer\ImageManager;
use App\Domain\ServiceLayer\MediaManager;
use App\Domain\ServiceLayer\TrickManager;
use App\Service\Form\Handler\FormHandlerInterface;
use App\Service\Form\Type\Admin\CreateTrickType;
use App\Service\Form\Type\Admin\UpdateTrickType;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
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
     * @var MediaManager
     */
    private $mediaService;

    /**
     * @var TrickManager
     */
    private $trickService;

    /**
     * @var ValidatorInterface
     */
    private $validator;

    /**
     * ImageToCropType constructor.
     *
     * @param DataMapperInterface $dataMapper
     * @param ImageManager        $imageService
     * @param MediaManager        $mediaService
     * @param TrickManager        $trickService
     * @param ValidatorInterface  $validator
     */
    public function __construct(
        DataMapperInterface $dataMapper,
        ImageManager $imageService,
        MediaManager $mediaService,
        TrickManager $trickService,
        ValidatorInterface $validator
    ) {
        $this->dataMapper = $dataMapper;
        $this->imageService = $imageService;
        $this->mediaService = $mediaService;
        $this->trickService = $trickService;
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
            // "isPublished" property is set to true by default because it is not managed in project at this level!
            ->add('showListRank', HiddenType::class, [
                // Maintain validation state at the child form level, to be able to show errors near field
                'error_bubbling' => false
            ]);
        // Add data transformer to "showListRank" data.
        $this->addStringToIntegerCustomDataTransformer($builder, 'showListRank');
        // Retrieve root form handler instance passed in parent form entry_options parameter
        $rootFormHandler = $options['rootFormHandler'];
        // Retrieve trick instance passed in parent form entry_options parameter if it exists!
        $trickToUpdate = $options['trickToUpdate'] ?? null;
        // Use submit form event for image to crop type to save each uploaded image on server without global validation!
        $this->uploadValidatedImage($builder, $rootFormHandler, $trickToUpdate);
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
        $resolver->setAllowedValues('rootFormHandler', function ($value) {
            if (!$value instanceof FormHandlerInterface) {
                return false;
            }
            return true;
        });
        // Check "trickToUpdate" optional option passed in parent form type entry_options parameter
        // This is used for UpdateTrickType only at this time!
        $resolver->setDefined('trickToUpdate');
        $resolver->setAllowedValues('trickToUpdate', function ($value) {
            if (!$value instanceof Trick) {
                return false;
            }
            return true;
        });
    }

    /**
     * Use finished view to reset (purge) invalid hidden crop/uploaded image data for the current managed image
     * which have no reason to be handled by user unless he is a malicious one!
     *
     * @param FormView      $view
     * @param FormInterface $form
     * @param array         $options
     *
     * @return void
     */
    public function finishView(FormView $view, FormInterface $form, array $options) : void
    {
        // Apply this reset only if form index name is set, to avoid issue
        if (ctype_digit($form->getName())) { // Form name is a string!
            foreach ($form->all() as $childForm) {
                // "image", "description", "isMain" values are user inputs and must remain unchanged!
                // "showListRank" can be changed in root form (e.g. Trick creation or update form) finishView() method.
                $array = ['cropJSONData', 'imagePreviewDataURI', 'savedImageName'];
                if ($childForm->isSubmitted() && !$childForm->isValid() && \in_array($childForm->getName(), $array)) {
                    // Purge value
                    $view->children[$childForm->getName()]->vars['value'] = '';
                }
            }
        }
    }

    /**
     * Generate image with direct upload when it is expected.
     *
     * @param FormtypeInterface $rootFormType
     * @param ImageToCropDTO    $imageToCropDataModel
     *
     * @return Image|null
     *
     * @throws \Exception
     */
    private function generateImageWithDirectUpload(FormtypeInterface $rootFormType, ImageToCropDTO $imageToCropDataModel) : ?Image
    {
        // Apply this only for trick creation or update forms at this time!
        switch ($rootFormType) {
            // Create a Trick image with the highest expected format for both cases
            case $rootFormType instanceof CreateTrickType:
            case $rootFormType instanceof UpdateTrickType:
                // At this level, Trick slug can't be used due to not validated Trick name,
                // so we used a image basic identifier name to replace it later on Trick creation or update actions.
                $imageIdentifierName = ImageManager::TRICK_IMAGE_TYPE_KEY . ImageManager::DEFAULT_IMAGE_IDENTIFIER_NAME;
                // Generate a physical image and returns a image file instance
                $imageFile = $this->imageService->generateTrickImageFile(
                    $imageToCropDataModel,
                    'trickBig',
                    true,
                    $imageIdentifierName
                );
                $newTemporaryImage = $this->imageService->createTrickImage($imageToCropDataModel, $imageFile, true);
                break;
            // Stop process for other root form types
            default:
                $newTemporaryImage = null;
        }
        return $newTemporaryImage;
    }

    /**
     * Generate a trick image media after direct upload.
     *
     * @param Image             $createdImageOnServer
     * @param ImageToCropDTO    $imageToCropDataModel
     * @param FormTypeInterface $rootFormType
     * @param Trick|null        $entity
     *
     * @return Media|null
     *
     * @throws \Exception
     */
    private function generateTrickImageMediaAfterDirectUpload(
        Image $createdImageOnServer,
        ImageToCropDTO $imageToCropDataModel,
        FormTypeInterface $rootFormType,
        ?Trick $entity
    ) : ?Media
    {
        // Create mandatory Media entity which references corresponding entities:
        // MediaOwner is the attachment (none is defined here, since no trick is set yet), MediaSource is an image.
        /** @var MediaOwner|null $newMediaOwner */
        $newMediaOwner = null;
        /** @var MediaSource|null $newMediaSource */
        $newMediaSource = $this->mediaService->getMediaSourceManager()->createMediaSource($createdImageOnServer);
        if (\is_null($newMediaSource)) {
            return null;
        }
        // CAUTION! Particular case: attach image corresponding Media entity to a particular Trick entity to update
        // to be able to remove temporary associated image with trick update process (collection type "allow_delete" option)
        switch ($rootFormType) {
            // Get corresponding MediaOwner entity
            case $rootFormType instanceof UpdateTrickType:
                $mediaOwner = !\is_null($entity) ? $entity->getMediaOwner() : null;
                break;
            // Define no media owner by default
            default:
                $mediaOwner = null;
        }
        // Create temporary Media instance
        $createdMedia = $this->mediaService->createTrickMedia(
            $mediaOwner,
            $newMediaSource,
            $imageToCropDataModel,
            'trickBig'
        );
        return $createdMedia;
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
        // No image file was uploaded, then stop process!
        if (\is_null($formData['image'])) {
            return null;
        }
        // Avoid issue with DTOMapper: turn show list rank string into a real int, or if it the value is not an int,
        // define the value to 0 to be checked in validator!
        $formData['showListRank'] = ctype_digit((string) $formData['showListRank']) ? (int) $formData['showListRank'] : 0;
        // Avoid issue with DTOMapper: get into account checkbox case which has no value when unchecked!
        $formData['isMain'] = isset($formData['isMain']) ? (bool) $formData['isMain'] : false;
        // Get new current feed imageToCropDTO instance with custom data mapper by mapping form changes
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
     * Update an image in the same time than direct upload by associating it with entity owner.
     *
     * @param FormTypeInterface           $rootFormType
     * @param object                      $entity
     * @param ImageToCropDTO              $imageToCropDataModel
     * @param Image                       $createdImageOnServer
     * @param object|FormHandlerInterface $rootFormHandler
     *
     * @return void
     *
     * @throws \Exception
     */
    private function updateImageDirectly(
        FormTypeInterface $rootFormType,
        object $entity,
        ImageToCropDTO $imageToCropDataModel,
        Image $createdImageOnServer,
        FormHandlerInterface $rootFormHandler
    ) : void
    {
        // Apply this only for update process cases
        switch ($rootFormType) {
            // Generate directly the two other image formats associated to an existing updated trick
            case $rootFormType instanceof UpdateTrickType:
                /** @var Trick $updatedTrick */
                $updatedTrick = $entity;
                // At this level, DTO "SavedImageName" property is not already feed and description must be adjusted,
                // so add it thanks to image name!
                $imageToCropDataModel->setSavedImageName($createdImageOnServer->getName());
                $description = $imageToCropDataModel->getDescription() ?? ImageManager::DEFAULT_IMAGE_DESCRIPTION_TEXT;
                $imageToCropDataModel->setDescription($description);
                // Create the mandatory (3) image formats directly with corresponding entities.
                $rootFormHandler->addTrickImageFromCollection(
                    $updatedTrick,
                    $imageToCropDataModel,
                    ['imageService' => $this->imageService, 'mediaService' => $this->mediaService]
                );
                // Flush persisted data thanks to updated trick
                $this->trickService->addAndSaveTrick($updatedTrick, false, true);
                break;
        }
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
     *
     * @throws \Exception
     */
    private function updateMandatoryPersistentImageData(Image $createdImageOnServer, array $formData) : array
    {
        // WARNING! Delete corresponding "UploadedFile" instance to avoid a "The file could not be uploaded." violation
        // due to temporary file which does not exist on server after direct upload anymore!
        $formData['image'] = null;
        // Add uploaded image filename (as a kind of "identifier")
        // into "savedImageName" form data, to keep a reference later
        $formData['savedImageName'] = $createdImageOnServer->getName();
        // Add also uploaded image filename into "cropJSONData" form data
        // provided by JavaScript, to keep a reference later
        $cropData = json_decode($formData['cropJSONData'], true); // get JSON as associative array
        // IMPORTANT! At this time, JSON data contains only one crop result,
        // but this "results" array could be useful for multiple uploads later!
        $cropData = $cropData['results'];
        $cropData[0]['identifier'] = $createdImageOnServer->getName();
        $cropData = json_encode(['results' => [$cropData[0]]]);
        $formData['cropJSONData'] = $cropData;
        return $formData;
    }

    /**
     * Upload directly current sub form validated image on server, without complete root form validation.
     *
     * @param FormBuilderInterface $builder
     * @param object               $rootFormHandler
     * @param object|null          $entity
     *
     * @return void
     */
    private function uploadValidatedImage(FormBuilderInterface $builder , object $rootFormHandler, ?object $entity) : void
    {
        $builder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event) use ($rootFormHandler, $entity) {
            // Get current "image to crop" form
            $currentImageToCropForm = $event->getForm();
            // Get current image form data
            $formData = $event->getData();
            // Get the data model based on form data
            $imageToCropDataModel = $this->prepareAndCheckDataModelForDirectUpload(
                $currentImageToCropForm,
                $formData,
                $rootFormHandler
            );
            // Image or/and cropJSON Data are not valid, so direct upload must no be enabled!
            if (\is_null($imageToCropDataModel)) {
                return null;
            }
            // if no expected violation is found, save directly the uploaded file
            // as a standalone media without complete root form validation
            $rootFormType = $currentImageToCropForm->getRoot()->getConfig()->getType()->getInnerType();
            // Perform directUpload
            /** @var Image|null $createdImageOnServer */
            $createdImageOnServer =$this->generateImageWithDirectUpload($rootFormType, $imageToCropDataModel);
            // Upload is a success: update mandatory form data associated to image
            // to make a persistence until complete form validation
            if (\is_null($createdImageOnServer)) {
                return null;
            }
            // Apply this only for trick creation or update forms at this time!
            switch ($rootFormType) {
                // Create an trick image media based on direct upload
                case $rootFormType instanceof CreateTrickType:
                case $rootFormType instanceof UpdateTrickType:
                    // "$entity" object is null in cas of trick creation!
                    $entity = \is_object($entity) && $entity instanceof Trick ? $entity : null;
                    /** @var Media|null $createdMedia */
                    $createdMedia = $this->generateTrickImageMediaAfterDirectUpload(
                        $createdImageOnServer,
                        $imageToCropDataModel,
                        $rootFormType,
                        $entity
                    );
                    break;
                default:
                    $createdMedia = null;
            }
            // Persist and flush big image Image (or possibly the 3 image formats in case of update) and Media entities
            if (\is_null($createdMedia)) {
                return null;
            }
            // Save entities for corresponding image
            $savedImage = $this->imageService->addAndSaveImage(
                $createdImageOnServer,
                $createdMedia,
                true,
                true
            );
            // Update an image in the same time than direct upload by associating it to an existing entity owner
            if (!\is_null($entity)) {
                $this->updateImageDirectly(
                    $rootFormType,
                    $entity,
                    $imageToCropDataModel,
                    $createdImageOnServer,
                    $rootFormHandler
                );
             }
            // Update form with new data when pre-submitting
            $formData = $this->updateMandatoryPersistentImageData($savedImage, $formData);
            // Update global current "image to crop" form data
            $event->setData($formData);
        });
    }
}
