<?php

declare(strict_types=1);

namespace Tests;

use JulienLinard\Router\Attributes\Route;
use PHPUnit\Framework\TestCase;
use JulienLinard\Router\Router;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;
use ReflectionClass;

/**
 * Tests pour le cache du tri des routes dynamiques (Phase 2.1)
 */
class SortedRoutesCacheTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    /**
     * Test que le cache est créé lors du premier appel
     */
    public function testCacheIsCreatedOnFirstCall(): void
    {
        // Enregistrer quelques routes dynamiques
        $this->router->registerRoutes(TestDynamicController::class);

        // Accéder au cache via réflexion
        $reflection = new ReflectionClass($this->router);
        $cacheProperty = $reflection->getProperty('sortedDynamicRoutesCache');
        $cacheProperty->setAccessible(true);

        // Le cache devrait être null avant le premier appel
        $cacheBefore = $cacheProperty->getValue($this->router);
        $this->assertNull($cacheBefore, 'Le cache devrait être null avant le premier appel');

        // Appeler getSortedDynamicRoutes() via handle()
        $request = new Request('/user/123', 'GET');
        $this->router->handle($request);

        // Le cache devrait être rempli après le premier appel
        $cacheAfter = $cacheProperty->getValue($this->router);
        $this->assertNotNull($cacheAfter, 'Le cache devrait être rempli après le premier appel');
        $this->assertIsArray($cacheAfter, 'Le cache devrait être un tableau');
    }

    /**
     * Test que le cache est réutilisé lors des appels suivants
     */
    public function testCacheIsReusedOnSubsequentCalls(): void
    {
        // Enregistrer quelques routes dynamiques
        $this->router->registerRoutes(TestDynamicController::class);

        // Premier appel
        $request1 = new Request('/user/123', 'GET');
        $response1 = $this->router->handle($request1);
        $this->assertEquals(200, $response1->getStatusCode());

        // Accéder au cache
        $reflection = new ReflectionClass($this->router);
        $cacheProperty = $reflection->getProperty('sortedDynamicRoutesCache');
        $cacheProperty->setAccessible(true);
        $cacheAfterFirst = $cacheProperty->getValue($this->router);

        // Deuxième appel
        $request2 = new Request('/user/456', 'GET');
        $response2 = $this->router->handle($request2);
        $this->assertEquals(200, $response2->getStatusCode());

        // Le cache devrait être le même (même référence)
        $cacheAfterSecond = $cacheProperty->getValue($this->router);
        $this->assertSame($cacheAfterFirst, $cacheAfterSecond, 'Le cache devrait être réutilisé');
    }

    /**
     * Test que le cache est invalidé lors de l'ajout d'une nouvelle route
     */
    public function testCacheIsInvalidatedWhenAddingNewRoute(): void
    {
        // Enregistrer quelques routes dynamiques
        $this->router->registerRoutes(TestDynamicController::class);

        // Faire un appel pour créer le cache
        $request1 = new Request('/user/123', 'GET');
        $this->router->handle($request1);

        // Accéder au cache
        $reflection = new ReflectionClass($this->router);
        $cacheProperty = $reflection->getProperty('sortedDynamicRoutesCache');
        $cacheProperty->setAccessible(true);
        $cacheBefore = $cacheProperty->getValue($this->router);
        $this->assertNotNull($cacheBefore, 'Le cache devrait être rempli');
        $this->assertIsArray($cacheBefore, 'Le cache devrait être un tableau');

        // Ajouter une nouvelle route dynamique
        $this->router->registerRoutes(TestDynamicController2::class);

        // Le cache devrait être invalidé (null)
        $cacheAfter = $cacheProperty->getValue($this->router);
        $this->assertNull($cacheAfter, 'Le cache devrait être invalidé après l\'ajout d\'une nouvelle route');
    }

    /**
     * Test que le cache est régénéré après invalidation
     */
    public function testCacheIsRegeneratedAfterInvalidation(): void
    {
        // Enregistrer quelques routes dynamiques
        $this->router->registerRoutes(TestDynamicController::class);

        // Faire un appel pour créer le cache
        $request1 = new Request('/user/123', 'GET');
        $this->router->handle($request1);

        // Accéder au cache avant invalidation
        $reflection = new ReflectionClass($this->router);
        $cacheProperty = $reflection->getProperty('sortedDynamicRoutesCache');
        $cacheProperty->setAccessible(true);
        $cacheBefore = $cacheProperty->getValue($this->router);
        $this->assertNotNull($cacheBefore, 'Le cache devrait être rempli avant invalidation');

        // Ajouter une nouvelle route (invalide le cache)
        $this->router->registerRoutes(TestDynamicController2::class);

        // Vérifier que le cache est invalidé
        $cacheAfterInvalidation = $cacheProperty->getValue($this->router);
        $this->assertNull($cacheAfterInvalidation, 'Le cache devrait être invalidé');

        // Faire un nouvel appel (devrait régénérer le cache)
        $request2 = new Request('/user/456', 'GET');
        $response2 = $this->router->handle($request2);
        $this->assertEquals(200, $response2->getStatusCode());

        // Le cache devrait être régénéré
        $cacheAfter = $cacheProperty->getValue($this->router);
        $this->assertNotNull($cacheAfter, 'Le cache devrait être régénéré après un nouvel appel');
        $this->assertIsArray($cacheAfter, 'Le cache devrait être un tableau');
        $this->assertGreaterThan(count($cacheBefore), count($cacheAfter), 
            'Le nouveau cache devrait contenir plus de routes');
    }

    /**
     * Test que le tri est correct même avec le cache
     */
    public function testSortingIsCorrectWithCache(): void
    {
        // Enregistrer des routes avec différents niveaux de spécificité
        $this->router->registerRoutes(TestDynamicController::class);
        $this->router->registerRoutes(TestDynamicController2::class);

        // Faire un appel pour créer le cache
        $request = new Request('/user/123/post/test', 'GET');
        $response = $this->router->handle($request);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('User Post', $response->getContent());

        // Vérifier que la route la plus spécifique est testée en premier
        // (route avec 2 paramètres devrait matcher avant route avec 1 paramètre)
        $request2 = new Request('/user/123', 'GET');
        $response2 = $this->router->handle($request2);
        $this->assertEquals(200, $response2->getStatusCode());
        $this->assertEquals('User', $response2->getContent());
    }

    /**
     * Test de performance : le cache améliore les performances
     */
    public function testCacheImprovesPerformance(): void
    {
        // Enregistrer beaucoup de routes dynamiques
        for ($i = 1; $i <= 50; $i++) {
            $className = "PerformanceTestController{$i}";
            $paramCount = ($i % 3) + 1;
            $path = "/test{$i}";
            for ($j = 1; $j <= $paramCount; $j++) {
                $path .= "/{param{$j}}";
            }
            
            eval("
                class {$className} {
                    #[\\JulienLinard\\Router\\Attributes\\Route(path: '{$path}', methods: ['GET'], name: 'test.{$i}')]
                    public function index(\\JulienLinard\\Router\\Request \$request): \\JulienLinard\\Router\\Response {
                        return new \\JulienLinard\\Router\\Response(200, 'Test {$i}');
                    }
                }
            ");
            
            $this->router->registerRoutes($className);
        }

        // Premier appel (crée le cache)
        $start1 = microtime(true);
        $request1 = new Request('/test1/1/2/3', 'GET');
        $this->router->handle($request1);
        $duration1 = microtime(true) - $start1;

        // Deuxième appel (utilise le cache)
        $start2 = microtime(true);
        $request2 = new Request('/test2/1/2', 'GET');
        $this->router->handle($request2);
        $duration2 = microtime(true) - $start2;

        // Le deuxième appel devrait être plus rapide (ou au moins similaire)
        // Note: La différence peut être minime, mais le cache évite le tri
        $this->assertLessThanOrEqual($duration1 * 1.5, $duration2, 
            'Le deuxième appel devrait être au moins aussi rapide grâce au cache');
    }
}

/**
 * Contrôleurs de test
 */
class TestDynamicController
{
    #[Route(path: '/user/{id}', methods: ['GET'], name: 'user.show')]
    public function show(Request $request): Response
    {
        return new Response(200, 'User');
    }
}

class TestDynamicController2
{
    #[Route(path: '/user/{userId}/post/{slug}', methods: ['GET'], name: 'user.post.show')]
    public function show(Request $request): Response
    {
        return new Response(200, 'User Post');
    }
}

