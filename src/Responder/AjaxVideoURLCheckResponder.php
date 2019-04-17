<?php

declare(strict_types = 1);

namespace App\Responder;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class AjaxVideoURLCheckResponder.
 *
 * Manage a simple JSON response with status from ajax request.
 */
final class AjaxVideoURLCheckResponder
{
    /**
     * Invokable Responder with Magic method.
     *
     * @param array $data
     *
     * @return JsonResponse
     */
    public function __invoke(array $data) : JsonResponse
    {
        // Encode data with JSON string with serializer
        $response = new JsonResponse($data);
        $response->headers->set('Content-Type', 'application/json; charset=utf-8');
        return $response;
    }
}