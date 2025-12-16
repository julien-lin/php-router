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
 * Tests complets pour les groupes de routes
 */
class RouteGroupTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    /**
     * Test que le préfixe est correctement appliqué aux routes du groupe
     */
    public function testGroupPrefixApplied(): void
    {
        $this->router->group('/api', [], function($router) {
            $router->registerRoutes(TestGroupController::class);
        });

        $request = new Request('/api/test', 'GET');
        $response = $this->router->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Test route', $response->getContent());
    }

    /**
     * Test que les middlewares du groupe sont appliqués
     */
    public function testGroupMiddlewaresApplied(): void
    {
        $state = new \stdClass();
        $state->called = false;
        
        $testMiddleware = new class($state) implements Middleware {
            private $state;
            public function __construct($state) {
                $this->state = $state;
            }
            public function handle(\JulienLinard\Router\Request $request): ?\JulienLinard\Router\Response {
                $this->state->called = true;
                return null;
            }
        };

        $this->router->group('/admin', [$testMiddleware], function($router) {
            $router->registerRoutes(TestGroupController::class);
        });

        $request = new Request('/admin/test', 'GET');
        $this->router->handle($request);

        $this->assertTrue($state->called, 'Le middleware du groupe devrait être appelé');
    }

    /**
     * Test que les middlewares du groupe peuvent bloquer l'exécution
     */
    public function testGroupMiddlewareCanBlockExecution(): void
    {
        $blockingMiddleware = new class implements Middleware {
            public function handle(\JulienLinard\Router\Request $request): ?\JulienLinard\Router\Response {
                return new \JulienLinard\Router\Response(403, 'Forbidden');
            }
        };

        $this->router->group('/admin', [$blockingMiddleware], function($router) {
            $router->registerRoutes(TestGroupController::class);
        });

        $request = new Request('/admin/test', 'GET');
        $response = $this->router->handle($request);

        $this->assertEquals(403, $response->getStatusCode());
        $this->assertEquals('Forbidden', $response->getContent());
    }

    /**
     * Test des groupes imbriqués avec préfixes
     */
    public function testNestedGroupsWithPrefixes(): void
    {
        $this->router->group('/api', [], function($router) {
            $router->group('/v1', [], function($router) {
                $router->registerRoutes(TestGroupController::class);
            });
        });

        $request = new Request('/api/v1/test', 'GET');
        $response = $this->router->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test des groupes imbriqués avec middlewares
     */
    public function testNestedGroupsWithMiddlewares(): void
    {
        $state = new \stdClass();
        $state->outerCalled = false;
        $state->innerCalled = false;

        $outerMiddleware = new class($state) implements Middleware {
            private $state;
            public function __construct($state) { $this->state = $state; }
            public function handle(\JulienLinard\Router\Request $request): ?\JulienLinard\Router\Response {
                $this->state->outerCalled = true;
                return null;
            }
        };

        $innerMiddleware = new class($state) implements Middleware {
            private $state;
            public function __construct($state) { $this->state = $state; }
            public function handle(\JulienLinard\Router\Request $request): ?\JulienLinard\Router\Response {
                $this->state->innerCalled = true;
                return null;
            }
        };

        $this->router->group('/api', [$outerMiddleware], function($router) use (&$innerCalled, $innerMiddleware) {
            $router->group('/v1', [$innerMiddleware], function($router) {
                $router->registerRoutes(TestGroupController::class);
            });
        });

        $request = new Request('/api/v1/test', 'GET');
        $this->router->handle($request);

        $this->assertTrue($state->outerCalled, 'Le middleware externe devrait être appelé');
        $this->assertTrue($state->innerCalled, 'Le middleware interne devrait être appelé');
    }

    /**
     * Test que le préfixe vide fonctionne
     */
    public function testEmptyPrefix(): void
    {
        $this->router->group('', [], function($router) {
            $router->registerRoutes(TestGroupController::class);
        });

        $request = new Request('/test', 'GET');
        $response = $this->router->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test que le préfixe avec slash fonctionne
     */
    public function testPrefixWithSlash(): void
    {
        $this->router->group('/api/', [], function($router) {
            $router->registerRoutes(TestGroupController::class);
        });

        $request = new Request('/api/test', 'GET');
        $response = $this->router->handle($request);

        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Test que la génération d'URL fonctionne avec les groupes
     */
    public function testUrlGenerationWithGroups(): void
    {
        $this->router->group('/api', [], function($router) {
            $router->registerRoutes(TestGroupController::class);
        });

        $url = $this->router->url('test.group');
        $this->assertEquals('/api/test', $url);
    }

    /**
     * Test que les routes en dehors du groupe ne sont pas affectées
     */
    public function testRoutesOutsideGroupUnaffected(): void
    {
        $this->router->registerRoutes(TestGroupController::class);
        
        // Utiliser un contrôleur différent pour éviter le conflit de nom de route
        $this->router->group('/api', [], function($router) {
            $router->registerRoutes(TestGroupController2::class);
        });

        // Route en dehors du groupe
        $request1 = new Request('/test', 'GET');
        $response1 = $this->router->handle($request1);
        $this->assertEquals(200, $response1->getStatusCode());

        // Route dans le groupe
        $request2 = new Request('/api/test', 'GET');
        $response2 = $this->router->handle($request2);
        $this->assertEquals(200, $response2->getStatusCode());
    }
}

/**
 * Contrôleur de test pour les groupes
 */
class TestGroupController
{
    #[Route(path: '/test', methods: ['GET'], name: 'test.group')]
    public function test(): Response
    {
        return new Response(200, 'Test route');
    }
}

class TestGroupController2
{
    #[Route(path: '/test', methods: ['GET'], name: 'test.group2')]
    public function test(): Response
    {
        return new Response(200, 'Test route 2');
    }
}

