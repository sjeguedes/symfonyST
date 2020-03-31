<?php

declare(strict_types = 1);

namespace App\Form\Validator\Constraint;

use App\Form\Validator\VideoInfosConstraintValidator;
use Symfony\Component\Validator\Constraint;

/**
 * Class VideoInfosConstraint.
 *
 * This class manages a custom validation constraint as concerns VideoInfosDTO instance.
 *
 * @see https://symfony.com/doc/current/validation/custom_constraint.html
 */
class VideoInfosConstraint extends Constraint
{
    /**
     * Apply this custom constraint to a class (VideoInfosDTO).
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
        return VideoInfosConstraintValidator::class;
    }
}
