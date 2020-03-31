<?php

declare(strict_types = 1);

namespace App\Form\Type\Admin;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;
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
     * Add single entity to array model transformer to a form data.
     *
     * This transformer aims at using the same DTO if "multiple" form option changes.
     *
     * @param FormBuilderInterface $formBuilder
     * @param string               $formName    a Form instance name
     *
     * @return void
     */
    protected function addSingleEntityToArrayCustomDataTransformer(FormBuilderInterface $formBuilder, string $formName) : void
    {
        /** @var FormBuilderInterface $form */
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
     * Use finished view to redefine show list rank for images/videos collections.
     *
     * Please note hidden inputs values can be redefined and collections order may be changed
     * in case of tampered rank(s) by malicious user, and to be sure to loop these based on a correct show list rank
     *
     * @param FormView      $view
     * @param FormInterface $form
     * @param array         $options
     *
     * @return void
     */
    public function finishView(FormView $view, FormInterface $form, array $options) : void
    {
        // Reorder images and videos collections and redefine ranks if necessary
        $this->reOrderImagesCollectionDataAndShowListRank($view, $form);
        $this->reOrderVideosCollectionDataAndShowListRank($view, $form);
    }

    /**
     * Maintain a correct order for images collection form view to be sure to loop correctly on view data
     * and adapt show list ranks data depending on their validity.
     *
     * @param FormView      $view
     * @param FormInterface $form
     *
     * @return void
     */
    private function reOrderImagesCollectionDataAndShowListRank(FormView $view, FormInterface $form) : void
    {
        // Reorder "image to crop" boxes data in images collection and redefine rank if necessary
        $this->updateCollectionOrderAndRanks('images', $view, $form);
    }

    /**
     * Maintain a correct order for videos collection form view to be sure to loop correctly on view data
     * and adapt show list ranks data depending on their validity.
     *
     * @param FormView      $view
     * @param FormInterface $form
     *
     * @return void
     */
    private function reOrderVideosCollectionDataAndShowListRank(FormView $view, FormInterface $form) : void
    {
        // Reorder "video infos" boxes data in videos collection and redefine rank if necessary
        $this->updateCollectionOrderAndRanks('videos', $view, $form);
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
