<?php

declare(strict_types = 1);

namespace App\Service\Form\Type\Admin;

use App\Domain\Entity\MediaType;
use App\Domain\ServiceLayer\ImageManager;
use App\Domain\ServiceLayer\MediaTypeManager;
use App\Domain\ServiceLayer\VideoManager;
use App\Service\Medias\Upload\ImageUploader;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

/**
 * Class AbstractTrickType.
 *
 * Define common methods for trick management (creation/update) form types.
 */
abstract class AbstractTrickType extends AbstractType
{
    /**
     * @var MediaTypeManager
     */
    private $mediaTypeService;

    /**
     * @var ImageManager
     */
    private $imageService;

    /**
     * @var VideoManager
     */
    private $videoService;

    /**
     * AbstractTrickType constructor.
     *
     * @param MediaTypeManager $mediaTypeService
     * @param ImageManager     $imageService
     * @param VideoManager     $videoService
     */
    public function __construct(MediaTypeManager $mediaTypeService, ImageManager $imageService, VideoManager $videoService)
    {
        $this->mediaTypeService = $mediaTypeService;
        $this->imageService = $imageService;
        $this->videoService = $videoService;
    }

    /**
     * Use finished view to redefine show list rank for images/videos collections.
     *
     * Please note hidden inputs values can be redefined and collections order may be changed
     * in case of tampered rank(s) by malicious user, and to be sure to loop these based on a correct show list rank.
     *
     * @param FormView      $view
     * @param FormInterface $form
     * @param array         $options
     *
     * @return void
     *
     * @throws \Exception
     */
    public function finishView(FormView $view, FormInterface $form, array $options) : void
    {
        // Maintain a correct order for images or videos collection form view to be sure to loop correctly on view data
        // and adapt show list ranks data depending on their validity.
        $this->manageImagesCollectionData($view, $form);
        $this->manageVideosCollectionData($view, $form);
    }

    /**
     * Manage images view data adjustments.
     *
     * @param FormView      $view
     * @param FormInterface $form
     *
     * @return void
     *
     * @throws \Exception
     */
    private function manageImagesCollectionData(FormView $view, FormInterface $form) : void
    {
        // Reorder "image to crop" boxes data in images collection and redefine rank if necessary
        $this->updateCollectionOrderAndRanks('images', $view, $form);
        // Get images entities which correspond to existing (temporary or not) image files on server
        $this->retrieveMediasSourcesEntities('images', $view, $form);
    }

    /**
     * Manage videos view data adjustments.
     *
     * @param FormView      $view
     * @param FormInterface $form
     *
     * @return void
     *
     * @throws \Exception
     */
    private function manageVideosCollectionData(FormView $view, FormInterface $form) : void
    {
        // Reorder "video infos" boxes data in videos collection and redefine rank if necessary
        $this->updateCollectionOrderAndRanks('videos', $view, $form);
        // TODO: add logic for video trick update
        // TODO: make changes in Video, VideoRepository, VideoInfosType, VideoInfosDTO, VideoInfosDTO.yaml to add logic for "name" / "savedVideoName"
        // Get videos entities which obviously already exists!
        $this->retrieveMediasSourcesEntities('videos', $view, $form);
    }

    /**
     * Retrieve existing medias sources (images or videos) entities
     * to pass them to collections in order to update/delete items.
     *
     * @param string        $collectionName
     * @param FormView      $view
     * @param FormInterface $form
     *
     * @return void
     *
     * @throws \Exception
     */
    private function retrieveMediasSourcesEntities(string $collectionName, FormView $view, FormInterface $form) : void
    {
        $config = [
            // This array will store all valid image collection entity items "identifier" names.
            'images' => ['validSavedNames' => [], 'childFormName' => 'savedImageName', 'serviceLayer' => $this->imageService],
            // This empty array will store all valid video collection entity items "identifier" names.
            'videos' => ['validSavedNames' => [], 'childFormName' => 'savedVideoName',  'serviceLayer' => $this->videoService]
        ];
        // Get images or videos names
        $validMediasSourcesNames = $config[$collectionName]['validSavedNames'];
        $childFormName = $config[$collectionName]['childFormName'];
        // Get an array of valid saved image name to perform a database query later
        foreach ($form->get($collectionName)->all() as $form) {
            // This condition will be used to check a temporary saved image:
            // Be aware of saved name which cannot be null even if it is a submitted and valid data.
            $isFormSubmittedAndValid = $form->get($childFormName)->isSubmitted() && $form->get($childFormName)->isValid();
            // This condition will be used to check a image to update:
            // Be aware of saved name which cannot be null event if image to update already valid before submit
            $isFormNotSubmitted = !$form->get($childFormName)->isSubmitted();
            // If at least one condition matched, check also necessarily if saved name is not null event.
            if (($isFormNotSubmitted or $isFormSubmittedAndValid) && !\is_null($form->get($childFormName)->getData())) {
                $validMediasSourcesNames[] = $form->get($childFormName)->getData();
            }
        }
        // Query database to get all needed data values (e.g. uuid and saved name for both cases, format for images)
        // which corresponds to valid saved names (can be considered as "identifier")
        if (0 !== \count($validMediasSourcesNames)) {
            $serviceLayer = $config[$collectionName]['serviceLayer'];
            $results = $serviceLayer->getRepository()->findManyToShowInFomByNames($validMediasSourcesNames);
            $formViewsCollection = $view->children[$collectionName]->children;
            // Pass a new entity uuid variable entity uuid template for each corresponding form view
            if (0 !== \count($results)) {
                // Pass new custom form view data to template
                $this->transmitMediasSourcesDataToTemplate($results, $formViewsCollection, $childFormName);
            }
        }
    }

    /**
     * Set image dataURI with base64 encoding for both temporary image or existing image to update.
     *
     * @param MediaType $thumbnailTypeEntity
     * @param array     $results
     * @param FormView  $imageFormView
     * @param string    $savedImageName
     *
     * @return void
     *
     * @throws \Exception
     */
    private function setImageDataURIForTemplate(
        MediaType $thumbnailTypeEntity,
        array $results,
        FormView $imageFormView,
        string $savedImageName
    ) : void
    {
        // Check temporary saved image or existing image to update
        $temporaryIdentifierPattern = preg_quote(ImageManager::DEFAULT_IMAGE_IDENTIFIER_NAME, '/');
        $isTemporaryImage = preg_match('/'. $temporaryIdentifierPattern . '/', $savedImageName);
        // Image exists thanks and can be updated: encode it to use dataURI.
        if (!$isTemporaryImage) {
            // Get thumbnail image name
            $pattern = '/^.*-(\d{2,}x\d{2,})(\.[a-z]{3,4})?$/';
            $bigImageName = $savedImageName;
            preg_match($pattern, $bigImageName, $matches, PREG_UNMATCHED_AS_NULL);
            // Replace big image dimensions ("with"x"height") in group 1 by thumbnail corresponding dimensions
            $width = $thumbnailTypeEntity->getWidth();
            $height = $thumbnailTypeEntity->getHeight();
            $thumbnailNameWithoutExtension = preg_replace(
                '/' . $matches[1] . '/',  $width . 'x' . $height, $bigImageName
            );
            $thumbnailName = $thumbnailNameWithoutExtension . '.' . $results[$savedImageName]['format'];
            $imageUploader = $this->imageService->getImageUploader();
            $thumbnailUploadDirectory = $imageUploader->getUploadDirectory(ImageUploader::TRICK_IMAGE_DIRECTORY_KEY);
            $thumbnailPath = $thumbnailUploadDirectory . '/' . $thumbnailName;
            $thumbnailImageDataURI = $imageUploader->encodeImageWithBase64($thumbnailPath);
            $imageFormView->vars['thumbnailImageDataURI'] = $thumbnailImageDataURI;
        // Image is temporary: simply use dataURI from field feed with JavaScript.
        } else {
            $imagePreviewFieldValue = $imageFormView->children['imagePreviewDataURI']->vars['value'];
            $imageFormView->vars['thumbnailImageDataURI'] = $imagePreviewFieldValue;
        }
    }

    /**
     * Transmit newly created FormView data to form template by querying corresponding data in database.
     *
     * Please note data are filtered thanks to media source saved names to get data results.
     *
     * @param array            $results             an array of queried data
     * @param array|FormView[] $formViewsCollection a collection of FormView instances
     * @param string           $childFormName       a field name to filter FormView instances
     *                                              (e.g. media source saved name)
     *
     * @return void
     *
     * @see https://www.php.net/manual/en/function.base64-encode.php
     *
     * @throws \Exception
     */
    private function transmitMediasSourcesDataToTemplate(array $results, array $formViewsCollection, string $childFormName) : void
    {
        // Get image thumbnail type by querying once
        $type = MediaType::TYPE_CHOICES['trickThumbnail'];
        $thumbnailTypeEntity = $this->mediaTypeService->findSingleByUniqueType($type);
        // Iterate on each form view
        foreach ($formViewsCollection as $formView) {
            // Retrieve child form view which store entity valid saved name
            // For instance, form view name is "savedImageName" or "savedVideoName".
            $childFormViewValue = $formView->children[$childFormName]->vars['value'];
            // Pass collection item entity uuid to corresponding form view to use it in template
            if (isset($results[$childFormViewValue]) && !empty($results[$childFormViewValue])) {
                // Add entity uuid for both image and video
                // No need to pass media name ("savedImageName" or "savedVideoName")
                // since it is already available in form and its validity can be checked directly!
                switch ($results[$childFormViewValue]['sourceType']) {
                    case 'image':
                        $formView->vars['bigImageUuid'] = $results[$childFormViewValue]['uuid'];
                        // Get dataURI for both temporary image or image to update, to use it for image preview
                        $this->setImageDataURIForTemplate(
                            $thumbnailTypeEntity,
                            $results,
                            $formView,
                            $childFormViewValue
                        );
                        break;
                    case 'video':
                        $formView->vars['videoUuid'] = $results[$childFormViewValue]['uuid'];
                        break;
                }
            }
        }
    }

    /**
     * Reorder collection and redefine each rank if necessary.
     *
     * @param string        $collectionName
     * @param FormView      $view
     * @param FormInterface $form
     *
     * @return void
     *
     * @throws \Exception
     */
    private function updateCollectionOrderAndRanks(string $collectionName, FormView $view, FormInterface $form) : void
    {
        $isShowListRankValid = true;
        // Check if at least one show list rank was tampered by malicious user thanks to custom validator!
        foreach ($form->get($collectionName)->all() as $form) {
            $isFormSubmittedAndValid = $form->get('showListRank')->isSubmitted() && $form->get('showListRank')->isValid();
            if (!$isFormSubmittedAndValid) {
                $isShowListRankValid = false;
                break;
            }
        }
        $array = $view->children[$collectionName]->children;
        // Sort children to be sure to have collection boxes in ascending order by their show list rank when a loop is made on collection in template
        if ($isShowListRankValid) {
            uasort($array, function ($a, $b) {
                return strcmp($a->children['showListRank']->vars['value'], $b->children['showListRank']->vars['value']);
            });
            // Sort by form view name in ascending order when at least one show list rank is not valid in collection
        } else {
            uasort($array, function ($a, $b) {
                return strcmp($a->vars['name'], $b->vars['name']);
            });
            // Redefine show list ranks
            // Loop on all collection boxes form views to keep a coherent order permanently between 1 and collection boxes length!
            $i = 1;
            foreach ($array as $formView) {
                $formView->children['showListRank']->vars['value'] = $i;
                $i ++;
            }
        }
        // Update collection array order to guarantee coherent loop on view
        $view->children[$collectionName]->children = $array;
    }
}
