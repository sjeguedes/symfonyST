<?php

declare(strict_types=1);

namespace App\Service\Templating;

/**
 * Interface TemplateBlockRendererInterface.
 *
 * Define a contract to render a template block (e.g. from a template engine).
 */
interface TemplateBlockRendererInterface
{
    /**
     * Render a template block.
     *
     * @param string $template a template name
     * @param string $block    a template block name
     * @param array  $data     some data to pass to template
     *
     * @return string
     */
    public function renderTemplateBlock(string $template, string $block, array $data): string;

    /**
     * Retrieve the template block name based on fully qualified class name
     * where template block rendering is called (e.g. Controller, Responder, or "Presenter" class name...).
     *
     * For instance, use an array to enable matching
     * between Responder (or other class) fully qualified class name (key) and template block name (value).
     *
     * @param string $className a fully qualified class name
     *
     * @return array an associative array which contains template name and template block name
     */
    public function getTemplateBlock(string $className): array;
}
