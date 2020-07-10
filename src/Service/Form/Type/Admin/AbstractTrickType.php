<?php

declare(strict_types = 1);

namespace App\Service\Form\Type\Admin;

use App\Domain\ServiceLayer\ImageManager;
use App\Domain\ServiceLayer\VideoManager;
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
     * @param ImageManager $imageService
     * @param VideoManager $videoService
     */
    public function __construct(ImageManager $imageService, VideoManager $videoService)
    {
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
     */
    private function manageImagesCollectionData(FormView $view, FormInterface $form) : void
    {
        // Reorder "image to crop" boxes data in images collection and redefine rank if necessary
        $this->updateCollectionOrderAndRanks('images', $view, $form);

        $this->retrieveEntitiesUuid('images', $view, $form);
    }

    /**
     * Manage videos view data adjustments.
     *
     * @param FormView      $view
     * @param FormInterface $form
     *
     * @return void
     */
    private function manageVideosCollectionData(FormView $view, FormInterface $form) : void
    {
        // Reorder "video infos" boxes data in videos collection and redefine rank if necessary
        $this->updateCollectionOrderAndRanks('videos', $view, $form);
        // TODO: add logic for video trick update
        // TODO: make changes in Video, VideoInfosType, VideoInfosDTO, VideoInfosDTO.yaml to add logic for "name" / "savedVideoName"
        //$this->retrieveEntitiesUuid('videos', $view, $form);
    }

    /**
     * Retrieve existing entities uuid identifiers to pass them to collections in order to update/delete items.
     *
     * @param string        $collectionName
     * @param FormView      $view
     * @param FormInterface $form
     *
     * @return void
     */
    private function retrieveEntitiesUuid(string $collectionName, FormView $view, FormInterface $form) : void
    {
        $config = [
            'images' => [
                // This array will store all valid collection entity items "identifier" names.
                'validSavedNames' => [],
                'childFormName'   => 'savedImageName',
                'serviceLayer'    => $this->imageService
            ],
            'videos' => [
                // This array will store all valid collection entity items "identifier" names.
                'validSavedNames' => [],
                'childFormName'   => 'savedVideoName',
                'serviceLayer'    => $this->videoService
            ]
        ];
        $validImagesNames = $config[$collectionName]['validSavedNames'];
        $childFormName = $config[$collectionName]['childFormName'];
        // Get an array of valid saved image name to perform a database query later
        foreach ($form->get($collectionName)->all() as $form) {
            // Be aware of saved name which can be null even if it is a valid data.
            if ($form->get($childFormName)->isValid() && !\is_null($form->get($childFormName)->getData())) {
                $validImagesNames[] = $form->get($childFormName)->getData();
            }
        }
        // Query database to get all uuid values which corresponds to valid saved names (can be considered as "identifier")
        if (0 !== \count($validImagesNames)) {
            $serviceLayer = $config[$collectionName]['serviceLayer'];
            $results = $serviceLayer->getRepository()->findManyUuidByNames($validImagesNames);
            $collectionFormViews = $view->children[$collectionName]->children;
            // Pass a new entity uuid variable entity uuid template for each corresponding form view
            if (0 !== \count($results)) {
                // Iterate on each
                foreach ($collectionFormViews as $formView) {
                    // Retrieve child form view which store entity valid saved name
                    // For instance, form view name is "savedImageName" or "savedVideoName".
                    $childFormViewValue = $formView->children[$childFormName]->vars['value'];
                    // Pass collection item entity uuid to corresponding form view to use it in template
                    if (isset($results[$childFormViewValue])) {
                        $formView->vars['entityUuid'] = (string) $results[$childFormViewValue];
                    }
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
     */
    private function updateCollectionOrderAndRanks(string $collectionName, FormView $view, FormInterface $form) : void
    {
        $isShowListRankValid = true;
        // Check if at least one show list rank was tampered by malicious user thanks to custom validator!
        foreach ($form->get($collectionName)->all() as $form) {
            if (!$form->get('showListRank')->isValid()) {
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
