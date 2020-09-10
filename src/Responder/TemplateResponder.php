<?php

declare(strict_types=1);

namespace App\Responder;

use App\Service\Templating\TemplateRendererInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class TemplateResponder.
 *
 * Manage a response with html template and its compiled data.
 */
final class TemplateResponder
{
    /**
     * @var TemplateRendererInterface
     */
    private $renderer;

    /**
     * TemplateResponder constructor.
     *
     * @param TemplateRendererInterface $renderer avoid coupling with template engine
     *
     * @return void
     */
    public function __construct(TemplateRendererInterface $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * Invokable Responder with Magic method.
     *
     * @param array  $data
     * @param string $actionClassName
     * @return Response
     */
    public function __invoke(array $data, string $actionClassName): Response
    {
        $template = $this->renderer->getTemplate($actionClassName);
        return new Response($this->renderer->renderTemplate($template, $data));
    }
}
