<?php

declare(strict_types=1);

namespace Tests;

use JulienLinard\Router\Attributes\Route;
use PHPUnit\Framework\TestCase;
use JulienLinard\Router\Router;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;
use JulienLinard\Router\Middleware;

/**
 * Tests complets pour les middlewares
 */
class MiddlewareTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    /**
     * Test qu'un middleware global est appelé
     */
    public function testGlobalMiddlewareCalled(): void
    {
        $state = new \stdClass();
        $state->called = false;
        
        $middleware = new class($state) implements Middleware {
            private $state;
            public function __construct($state) { $this->state = $state; }
            public function handle(\JulienLinard\Router\Request $request): ?\JulienLinard\Router\Response {
                $this->state->called = true;
                return null;
            }
        };

        $this->router->addMiddleware($middleware);
        $this->router->registerRoutes(TestController::class);

        $request = new Request('/test', 'GET');
        $this->router->handle($request);

        $this->assertTrue($state->called, 'Le middleware global devrait être appelé');
    }

    /**
     * Test qu'un middleware global peut bloquer l'exécution
     */
    public function testGlobalMiddlewareCanBlock(): void
    {
        $middleware = new class implements Middleware {
            public function handle(\JulienLinard\Router\Request $request): ?\JulienLinard\Router\Response {
                return new \JulienLinard\Router\Response(401, 'Unauthorized');
            }
        };

        $this->router->addMiddleware($middleware);
        $this->router->registerRoutes(TestController::class);

        $request = new Request('/test', 'GET');
        $response = $this->router->handle($request);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('Unauthorized', $response->getContent());
    }

    /**
     * Test que plusieurs middlewares globaux sont appelés dans l'ordre
     */
    public function testMultipleGlobalMiddlewaresCalledInOrder(): void
    {
        $state = new \stdClass();
        $state->order = [];

        $middleware1 = new class($state) implements Middleware {
            private $state;
            public function __construct($state) { $this->state = $state; }
            public function handle(\JulienLinard\Router\Request $request): ?\JulienLinard\Router\Response {
                $this->state->order[] = 1;
                return null;
            }
        };

        $middleware2 = new class($state) implements Middleware {
            private $state;
            public function __construct($state) { $this->state = $state; }
            public function handle(\JulienLinard\Router\Request $request): ?\JulienLinard\Router\Response {
                $this->state->order[] = 2;
                return null;
            }
        };

        $this->router->addMiddleware($middleware1);
        $this->router->addMiddleware($middleware2);
        $this->router->registerRoutes(TestController::class);

        $request = new Request('/test', 'GET');
        $this->router->handle($request);

        $this->assertEquals([1, 2], $state->order, 'Les middlewares devraient être appelés dans l\'ordre');
    }

    /**
     * Test qu'un middleware de route est appelé
     */
    public function testRouteMiddlewareCalled(): void
    {
        TestMiddleware::reset();
        
        $this->router->registerRoutes(RouteWithMiddlewareController::class);

        $request = new Request('/test-middleware', 'GET');
        $this->router->handle($request);

        $this->assertTrue(TestMiddleware::wasCalled(), 'Le middleware de route devrait être appelé');
    }

    /**
     * Test qu'un middleware de route peut bloquer l'exécution
     */
    public function testRouteMiddlewareCanBlock(): void
    {
        // Utiliser un middleware qui bloque
        $this->router->registerRoutes(RouteWithBlockingMiddlewareController::class);

        $request = new Request('/test-blocking', 'GET');
        $response = $this->router->handle($request);

        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * Test que les middlewares globaux sont appelés avant les middlewares de route
     */
    public function testGlobalMiddlewaresBeforeRouteMiddlewares(): void
    {
        $state = new \stdClass();
        $state->order = [];

        $globalMiddleware = new class($state) implements Middleware {
            private $state;
            public function __construct($state) { $this->state = $state; }
            public function handle(\JulienLinard\Router\Request $request): ?\JulienLinard\Router\Response {
                $this->state->order[] = 'global';
                return null;
            }
        };

        $this->router->addMiddleware($globalMiddleware);
        $this->router->registerRoutes(RouteWithMiddlewareController::class);

        TestMiddleware::reset();
        $request = new Request('/test-middleware', 'GET');
        $this->router->handle($request);

        $this->assertContains('global', $state->order, 'Le middleware global devrait être appelé');
        $this->assertTrue(TestMiddleware::wasCalled(), 'Le middleware de route devrait être appelé');
        // Le middleware global devrait être appelé en premier
        $this->assertEquals('global', $state->order[0] ?? null, 'Le middleware global devrait être appelé en premier');
    }

    /**
     * Test qu'un middleware peut accéder à la requête
     */
    public function testMiddlewareCanAccessRequest(): void
    {
        $state = new \stdClass();
        $state->requestPath = null;
        
        $middleware = new class($state) implements Middleware {
            private $state;
            public function __construct($state) { $this->state = $state; }
            public function handle(\JulienLinard\Router\Request $request): ?\JulienLinard\Router\Response {
                $this->state->requestPath = $request->getPath();
                return null;
            }
        };

        $this->router->addMiddleware($middleware);
        $this->router->registerRoutes(TestController::class);

        $request = new Request('/test', 'GET');
        $this->router->handle($request);

        $this->assertEquals('/test', $state->requestPath);
    }
}

/**
 * Contrôleurs de test
 */
class TestController
{
    #[Route(path: '/test', methods: ['GET'], name: 'test')]
    public function index(): Response
    {
        return new Response(200, 'Test');
    }
}

class RouteWithMiddlewareController
{
    #[Route(path: '/test-middleware', methods: ['GET'], name: 'test.middleware', middleware: [\Tests\TestMiddleware::class])]
    public function index(): Response
    {
        return new Response(200, 'Test with middleware');
    }
}

class RouteWithBlockingMiddlewareController
{
    #[Route(path: '/test-blocking', methods: ['GET'], name: 'test.blocking', middleware: [\Tests\BlockingMiddleware::class])]
    public function index(): Response
    {
        return new Response(200, 'Should not be reached');
    }
}

class BlockingMiddleware implements Middleware
{
    public function handle(Request $request): ?Response
    {
        return new Response(403, 'Forbidden');
    }
}

/**
 * Middleware de test
 */
class TestMiddleware implements Middleware
{
    private static bool $called = false;

    public function handle(Request $request): ?Response
    {
        self::$called = true;
        return null;
    }

    public static function wasCalled(): bool
    {
        return self::$called;
    }

    public static function reset(): void
    {
        self::$called = false;
    }
}
