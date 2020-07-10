<?php

declare(strict_types = 1);

namespace App\Service\Form\Handler;

/**
 * Interface InitModelDataInterface.
 *
 * Define a contract to set initial data in order to pre-filling a form.
 */
interface InitModelDataInterface
{
    /**
     * Set initial model data (DTO, entity...).
     *
     * @param array $data
     *
     * @return object
     */
    public function initModelData(array $data) : object;
}
