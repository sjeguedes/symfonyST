<?php

declare(strict_types = 1);

namespace App\Responder\Admin;

use App\Service\Templating\TemplateRendererInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class CreateTrickResponder.
 *
 * Manage a response with html template to show trick creation form, its details and functionality.
 */
final class CreateTrickResponder
{
    /**
     * @var TemplateRendererInterface
     */
    private $renderer;

    /**
     * CreateTrickResponder constructor.
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
     * @param array $data
     *
     * @return Response
     */
    public function __invoke(array $data) : Response
    {
        $template = $this->renderer->getTemplate(self::class);
        return new Response($this->renderer->renderTemplate($template, $data));
    }
}
