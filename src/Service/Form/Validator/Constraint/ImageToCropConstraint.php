<?php

declare(strict_types = 1);

namespace App\Service\Form\Validator\Constraint;

use App\Service\Form\Validator\ImageToCropConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * Class ImageToCropConstraint.
 *
 * This class manages a custom validation constraint as concerns imageToCropDTO instance.
 *
 * @see https://symfony.com/doc/current/validation/custom_constraint.html
 */
class ImageToCropConstraint extends Constraint
{
    /**
     * Apply this custom constraint to a class (ImageToCropDTO).
     *
     * @return string
     */
    public function getTargets() : string
    {
        return self::CLASS_CONSTRAINT;
    }

    /**
     * Link the corresponding custom constraint validator.
     *
     * @return string
     */
    public function validatedBy() : string
    {
        return ImageToCropConstraintValidator::class;
    }
}
