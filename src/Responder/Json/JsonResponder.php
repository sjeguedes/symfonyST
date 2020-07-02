<?php

declare(strict_types = 1);

namespace App\Responder\Json;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Class JsonResponder.
 *
 * Manage a simple JSON response with status from ajax request.
 */
final class JsonResponder
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
        return new JsonResponse($data);
    }
}
