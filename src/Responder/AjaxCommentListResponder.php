<?php

declare(strict_types = 1);

namespace App\Responder;

use App\Service\Templating\TemplateBlockRendererInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AjaxCommentListResponder.
 *
 * Manage a response with block html content based on ajax data, to add more comment(s) to an existing list.
 */
final class AjaxCommentListResponder extends AbstractAjaxListResponder
{
    /**
     * AjaxCommentListResponder constructor.
     *
     * @param TemplateBlockRendererInterface $renderer avoid coupling with template engine
     *
     * @return void
     */
    public function __construct(TemplateBlockRendererInterface $renderer)
    {
        parent::__construct($renderer);
    }

    /**
     * Invokable Responder with Magic method.
     *
     * @param array $data
     *
     * @return Response
     *
     * @throws \Throwable
     */
    public function __invoke(array $data) : Response
    {
        // Render a template block
        return $this->setHTMLBlockResponse($data, self::class);
    }
}
