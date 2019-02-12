<?php
declare(strict_types=1);

namespace App\Responder;

use App\Responder\Templating\TemplateRendererInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class PaginatedTrickListResponder.
 *
 * Manage a response with html template to show a complete trick list with pagination.
 */
final class PaginatedTrickListResponder
{
    /**
     * @var TemplateRendererInterface
     */
    private $renderer;

    /**
     * PaginatedTrickListResponder constructor.
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