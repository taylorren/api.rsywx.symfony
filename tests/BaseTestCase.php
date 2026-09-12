<?php

namespace Tests;

use PHPUnit\Framework\TestCase as PhpUnitTestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Base test case that boots the full Slim application (routes + middleware),
 * mirroring public/index.php so integration tests exercise the real stack.
 */
abstract class BaseTestCase extends PhpUnitTestCase
{
    private $app;

    /**
     * The API key the tests authenticate with. Set as an environment variable
     * BEFORE the app bootstraps so the API-key middleware validates against it.
     */
    public static function setUpBeforeClass(): void
    {
        // Do not let Dotenv overwrite these (createImmutable already skips
        // existing vars, but be explicit about the test key).
        if (!getenv('API_KEY')) {
            putenv('API_KEY=test-api-key-12345');
            $_ENV['API_KEY'] = 'test-api-key-12345';
        }
        if (!getenv('API_VERSION')) {
            putenv('API_VERSION=v1');
            $_ENV['API_VERSION'] = 'v1';
        }
    }

    protected function getApp(): \Slim\App
    {
        if ($this->app === null) {
            $this->app = require __DIR__ . '/../bootstrap-app.php';
        }
        return $this->app;
    }

    protected function createRequest(
        string $method,
        string $path,
        array $headers = [],
        array $serverParams = []
    ): ServerRequestInterface {
        $request = (new \Slim\Psr7\Factory\ServerRequestFactory())
            ->createServerRequest($method, $path);
        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }
        return $request;
    }

    protected function runApp(ServerRequestInterface $request): ResponseInterface
    {
        return $this->getApp()->handle($request);
    }
}
