<?php

declare(strict_types=1);

namespace App\Service\Form\TypeToEmbed;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Form\FormBuilderInterface;

/**
 * Class AbstractTrickCollectionEntryType.
 *
 * Define common methods for trick management (creation/update) collection form entry types.
 */
abstract class AbstractTrickCollectionEntryType extends AbstractType
{
    /**
     * Add string to integer model transformer to a form data.
     *
     * This transformer aims at using an integer instead of string in a DTO property.
     *
     * Please not value 0 is returned if the string does not contain a "valid" int.
     *
     * @param FormBuilderInterface $formBuilder
     * @param string               $formName    a Form instance name
     *
     * @return void
     */
    protected function addStringToIntegerCustomDataTransformer(FormBuilderInterface $formBuilder, string $formName): void
    {
        /** @var FormBuilderInterface $form */
        $form = $formBuilder->get($formName);
        $form->addModelTransformer(
            new CallbackTransformer(
                // Normalized data (transform)
                function ($value) {
                    // Do not transform the string or null value
                    return !\is_null($value) ? $value : null;
                },
                // Model data (reverse transform)
                function ($value) {
                    // Transform the string into a real int
                    if (ctype_digit((string) $value)) {
                        return (int) $value;
                    }
                    // The string does not contain an int, so define the value to 0 to be checked and filtered in validator!
                    return 0;
                }
            )
        );
    }
}
