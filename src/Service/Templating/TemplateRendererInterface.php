<?php

declare(strict_types=1);

namespace App\Service\Templating;

/**
 * Interface TemplateRendererInterface.
 *
 * Define a contract to render a template (e.g. from a template engine).
 */
interface TemplateRendererInterface
{
    /**
     * Render a template.
     *
     * @param string $template a template name
     * @param array  $data some data to pass to template
     *
     * @return string
     */
    public function renderTemplate(string $template, array $data): string;

    /**
     * Retrieve the template name based on fully qualified class name
     * where template rendering is called (e.g. Controller, Responder, or "Presenter" class name...)
     *
     * For instance, use an array to enable matching
     * between Responder (or other class) fully qualified class name (key) and template name (value).
     *
     * @param string $className a fully qualified class name
     * @param bool   $isEmail   an indicator to precise if it is an email template to render
     *
     * @return string
     */
    public function getTemplate(string $className, bool $isEmail = false): string;
}
