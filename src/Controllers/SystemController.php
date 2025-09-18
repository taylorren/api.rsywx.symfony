<?php

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use OpenApi\Attributes as OA;

class SystemController
{
    #[OA\Get(
        path: "/health",
        summary: "Health check",
        description: "Check if the API is running and accessible",
        tags: ["System"],
        responses: [
            new OA\Response(
                response: 200,
                description: "API is healthy",
                content: new OA\JsonContent(
                    properties: [
                        "success" => new OA\Property(property: "success", type: "boolean", example: true),
                        "message" => new OA\Property(property: "message", type: "string", example: "API is running"),
                        "timestamp" => new OA\Property(property: "timestamp", type: "string", example: "2025-07-27 10:30:00")
                    ]
                )
            )
        ]
    )]
    public function health(Request $request, Response $response)
    {
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'API is running',
            'timestamp' => date('Y-m-d H:i:s')
        ]));
        return $response->withHeader('Content-Type', 'application/json');
    }
}