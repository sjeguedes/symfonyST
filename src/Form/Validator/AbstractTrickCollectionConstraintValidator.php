<?php

declare(strict_types = 1);

namespace App\Form\Validator;

use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

abstract class AbstractTrickCollectionConstraintValidator extends ConstraintValidator
{
    /**
     * Validate each item rank in Collections.
     *
     * @param string $collectionName
     * @param ExecutionContextInterface $context
     * @param null $payload
     *
     * @return void
     *
     * @see For information: root namespace special compiled functions: https://github.com/FriendsOfPHP/PHP-CS-Fixer/issues/3048
     */
    protected function validateItemCollectionRank(string $collectionName, ExecutionContextInterface $context, $payload = null) : void
    {
        // Get current validated object (ImageToCropDTO or VideoInfosDTO)
        $object = $context->getObject();
        /** @var Form|FormInterface $collectionForm */
        $collectionForm = $context->getRoot()->get($collectionName);
        $countedCollectionFormItems = $collectionForm->count();
        $isRankTampered = false;
        // Data was tampered by malicious user! Current sortable order is not an int, or rank equals 0, or is not a positive integer, or greater than items boxes length.
        if (!ctype_digit(trim((string) $object->getShowListRank())) || 0 >= $object->getShowListRank() || $countedCollectionFormItems < $object->getShowListRank()) {
            $isRankTampered = true;
        } else {
            // Loop on all existing collection items boxes
            if (1 != $countedCollectionFormItems) {
                $result = [];
                foreach ($collectionForm as $key => $form) {
                    $rank = $form->getData()->getShowListRank();
                    // Data was tampered by malicious user!
                    // Rank equals 0, or is not a positive integer, or greater than collection items boxes length, or result array does not contain unique values!
                    if (!ctype_digit(trim((string) $rank)) || 0 >= $rank || $countedCollectionFormItems < $rank || \in_array($rank, $result)) {
                        // Add constraint violation  only for current item box when it is is involved in violation!
                        if ($object->getShowListRank() === $rank) {
                            $isRankTampered = true;
                        }
                        break;
                    }
                    // Push rank in this array to check uniques values later
                    array_push($result, $rank);
                }
            }
        }
        if (true === $isRankTampered) {
            $context->buildViolation('You are not allowed to tamper show list rank!' . "\n" . ucfirst($collectionName) . ' list was reordered by default.')
                ->atPath('showListRank')
                ->addViolation();
        }
    }
}
