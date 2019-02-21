<?php

declare(strict_types=1);

namespace App\Responder;

use App\Responder\Templating\TemplateRendererInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class SingleTrickResponder.
 *
 * Manage a response with html template to show a particular trick, its details and functionality.
 */
final class SingleTrickResponder
{
    /**
     * @var TemplateRendererInterface
     */
    private $renderer;

    /**
     * SingleTrickResponder constructor.
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