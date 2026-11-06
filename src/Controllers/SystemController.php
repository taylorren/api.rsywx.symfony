<?php

namespace App\Controllers;

use App\Cache\ApcuCache;
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
    public function health(Request $request, Response $response): Response
    {
        $response->getBody()->write(json_encode([
            'success' => true,
            'message' => 'API is running',
            'timestamp' => date('Y-m-d H:i:s')
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * Serves the interactive ReDoc page (no auth).
     */
    public function docs(Request $request, Response $response): Response
    {
        $html = @file_get_contents(dirname(__DIR__, 2) . '/public/api-docs.html') ?: '';
        $response->getBody()->write($html);

        return $response->withHeader('Content-Type', 'text/html');
    }

    /**
     * Generated OpenAPI spec as JSON. Generated on demand from the controller
     * annotations and cached for an hour, keyed by a fingerprint of the
     * controller file mtimes — so a committed artifact can never go stale.
     */
    public function openApiDocs(Request $request, Response $response): Response
    {
        $response->getBody()->write($this->buildSpec('json', $request));

        return $response->withHeader('Content-Type', 'application/json');
    }

    public function openApiDocsYaml(Request $request, Response $response): Response
    {
        $response->getBody()->write($this->buildSpec('yaml', $request));

        return $response->withHeader('Content-Type', 'application/yaml');
    }

    /**
     * Clears the application cache. Whole cache by default; a single logical
     * key with ?key=<key>. Requires the API key (see config/routes.php).
     */
    #[OA\Post(
        path: "/admin/cache/clear",
        summary: "Clear the application cache",
        description: "Clears all cached responses, or a single key when ?key= is given. Requires the API key.",
        tags: ["System"],
        security: [["ApiKeyAuth" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "Cache cleared",
                content: new OA\JsonContent(
                    properties: [
                        "success" => new OA\Property(property: "success", type: "boolean", example: true),
                        "data" => new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                "cleared" => new OA\Property(property: "cleared", type: "boolean", example: true),
                                "scope" => new OA\Property(property: "scope", type: "string", example: "all"),
                                "key" => new OA\Property(property: "key", type: "string", example: null, nullable: true)
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    public function clearCache(Request $request, Response $response): Response
    {
        $params = $request->getQueryParams();
        $key = isset($params['key']) ? (string) $params['key'] : null;

        $cache = new ApcuCache();
        $cleared = $key !== null ? $cache->delete($key) : $cache->clear();

        $response->getBody()->write(json_encode([
            'success' => true,
            'data' => [
                'cleared' => $cleared,
                'scope' => $key !== null ? 'key' : 'all',
                'key' => $key,
            ],
            'cached' => false,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }

    private function buildSpec(string $format, Request $request): string
    {
        $refresh = ($request->getQueryParams()['refresh'] ?? null) === 'true';
        $cache = new ApcuCache();
        $cacheKey = 'openapi_' . $format . '_' . $this->specFingerprint();

        if ($refresh === false) {
            $cached = $cache->get($cacheKey);
            if (is_string($cached)) {
                return $cached;
            }
        }

        $openapi = \OpenApi\Generator::scan([dirname(__DIR__) . '/Controllers']);
        $spec = $format === 'yaml' ? $openapi->toYaml() : $openapi->toJson();

        $cache->set($cacheKey, $spec, 3600);

        return $spec;
    }

    private function specFingerprint(): string
    {
        $fingerprint = '';
        foreach (glob(dirname(__DIR__) . '/Controllers/*.php') ?: [] as $file) {
            $fingerprint .= $file . filemtime($file);
        }

        return md5($fingerprint);
    }
}
