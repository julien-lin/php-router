<?php

declare(strict_types=1);

namespace Tests;

use JulienLinard\Router\Attributes\Route;
use PHPUnit\Framework\TestCase;
use JulienLinard\Router\Router;
use JulienLinard\Router\Response;
use JulienLinard\Router\Request;

/**
 * Tests complets pour la génération d'URL
 */
class UrlGenerationTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    /**
     * Test la génération d'URL pour une route statique
     */
    public function testStaticRouteUrl(): void
    {
        $this->router->registerRoutes(StaticRouteController::class);

        $url = $this->router->url('static.route');
        $this->assertEquals('/static', $url);
    }

    /**
     * Test la génération d'URL pour une route dynamique avec un paramètre
     */
    public function testDynamicRouteUrlWithOneParam(): void
    {
        $this->router->registerRoutes(DynamicRouteController::class);

        $url = $this->router->url('dynamic.route', ['id' => '123']);
        $this->assertEquals('/dynamic/123', $url);
    }

    /**
     * Test la génération d'URL pour une route dynamique avec plusieurs paramètres
     */
    public function testDynamicRouteUrlWithMultipleParams(): void
    {
        $this->router->registerRoutes(MultiParamRouteController::class);

        $url = $this->router->url('multi.param', ['userId' => '456', 'postId' => '789']);
        $this->assertEquals('/user/456/post/789', $url);
    }

    /**
     * Test la génération d'URL avec query parameters
     */
    public function testUrlWithQueryParams(): void
    {
        $this->router->registerRoutes(DynamicRouteController::class);

        $url = $this->router->url('dynamic.route', ['id' => '123'], ['page' => '1', 'sort' => 'name']);
        
        $this->assertStringContainsString('/dynamic/123', $url);
        $this->assertStringContainsString('page=1', $url);
        $this->assertStringContainsString('sort=name', $url);
    }

    /**
     * Test la génération d'URL avec valeurs encodées
     */
    public function testUrlWithEncodedValues(): void
    {
        $this->router->registerRoutes(DynamicRouteController::class);

        $url = $this->router->url('dynamic.route', ['id' => 'test with spaces']);
        $this->assertEquals('/dynamic/test%20with%20spaces', $url);
    }

    /**
     * Test la génération d'URL avec caractères spéciaux
     */
    public function testUrlWithSpecialCharacters(): void
    {
        $this->router->registerRoutes(DynamicRouteController::class);

        $url = $this->router->url('dynamic.route', ['id' => 'test@example.com']);
        $this->assertEquals('/dynamic/test%40example.com', $url);
    }

    /**
     * Test que la génération d'URL échoue si un paramètre requis est manquant
     */
    public function testUrlGenerationFailsWithMissingParam(): void
    {
        $this->router->registerRoutes(DynamicRouteController::class);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Le paramètre 'id' est requis");
        $this->router->url('dynamic.route', []);
    }

    /**
     * Test que la génération d'URL retourne null pour une route inexistante
     */
    public function testUrlGenerationReturnsNullForNonExistentRoute(): void
    {
        $url = $this->router->url('non.existent');
        $this->assertNull($url);
    }

    /**
     * Test la génération d'URL avec des types numériques
     */
    public function testUrlWithNumericParams(): void
    {
        $this->router->registerRoutes(DynamicRouteController::class);

        $url = $this->router->url('dynamic.route', ['id' => 123]);
        $this->assertEquals('/dynamic/123', $url);
    }

    /**
     * Test la génération d'URL avec des groupes
     */
    public function testUrlWithGroupPrefix(): void
    {
        $this->router->group('/api', [], function($router) {
            $router->registerRoutes(DynamicRouteController::class);
        });

        $url = $this->router->url('dynamic.route', ['id' => '123']);
        $this->assertEquals('/api/dynamic/123', $url);
    }

    /**
     * Test la génération d'URL avec groupes imbriqués
     */
    public function testUrlWithNestedGroups(): void
    {
        $this->router->group('/api', [], function($router) {
            $router->group('/v1', [], function($router) {
                $router->registerRoutes(DynamicRouteController::class);
            });
        });

        $url = $this->router->url('dynamic.route', ['id' => '123']);
        $this->assertEquals('/api/v1/dynamic/123', $url);
    }

    /**
     * Test la génération d'URL avec query parameters vides
     */
    public function testUrlWithEmptyQueryParams(): void
    {
        $this->router->registerRoutes(StaticRouteController::class);

        $url = $this->router->url('static.route', [], []);
        $this->assertEquals('/static', $url);
    }

    /**
     * Test la génération d'URL avec query parameters contenant des valeurs spéciales
     */
    public function testUrlWithSpecialQueryParams(): void
    {
        $this->router->registerRoutes(StaticRouteController::class);

        $url = $this->router->url('static.route', [], ['search' => 'test query', 'filter' => 'active']);
        $this->assertStringContainsString('search=test+query', $url);
        $this->assertStringContainsString('filter=active', $url);
    }
}

/**
 * Contrôleurs de test
 */
class StaticRouteController
{
    #[Route(path: '/static', methods: ['GET'], name: 'static.route')]
    public function index(): Response
    {
        return new Response(200, 'Static');
    }
}

class DynamicRouteController
{
    #[Route(path: '/dynamic/{id}', methods: ['GET'], name: 'dynamic.route')]
    public function show(Request $request): Response
    {
        return new Response(200, 'Dynamic: ' . $request->getRouteParam('id'));
    }
}

class MultiParamRouteController
{
    #[Route(path: '/user/{userId}/post/{postId}', methods: ['GET'], name: 'multi.param')]
    public function show(Request $request): Response
    {
        return new Response(200, 'Multi param');
    }
}

