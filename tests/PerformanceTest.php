<?php

declare(strict_types=1);

namespace Tests;

use JulienLinard\Router\Attributes\Route;
use PHPUnit\Framework\TestCase;
use JulienLinard\Router\Router;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

/**
 * Tests de performance pour le routeur
 */
class PerformanceTest extends TestCase
{
    private Router $router;
    private const ROUTE_COUNT = 1000;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    /**
     * Test de performance avec beaucoup de routes statiques
     */
    public function testPerformanceWithManyStaticRoutes(): void
    {
        $routeCount = 100; // Utiliser une variable au lieu de la constante dans eval
        
        // Enregistrer beaucoup de routes statiques
        for ($i = 1; $i <= $routeCount; $i++) {
            // Utiliser une classe dynamique pour chaque route
            $className = "DynamicController{$i}";
            $path = "/route{$i}";
            $name = "route.{$i}";
            $content = "Route {$i}";
            
            eval("
                class {$className} {
                    #[\\JulienLinard\\Router\\Attributes\\Route(path: '{$path}', methods: ['GET'], name: '{$name}')]
                    public function index(): \\JulienLinard\\Router\\Response {
                        return new \\JulienLinard\\Router\\Response(200, '{$content}');
                    }
                }
            ");
            
            $this->router->registerRoutes($className);
        }

        $start = microtime(true);

        // Tester plusieurs routes
        for ($i = 1; $i <= 50; $i++) {
            $routeNum = rand(1, $routeCount);
            $request = new Request("/route{$routeNum}", 'GET');
            $response = $this->router->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
        }

        $duration = microtime(true) - $start;

        // 50 requêtes devraient être rapides (< 1 seconde)
        $this->assertLessThan(1.0, $duration, 
            "50 requêtes sur " . $routeCount . " routes devraient prendre moins de 1 seconde");
    }

    /**
     * Test de performance avec beaucoup de routes dynamiques
     */
    public function testPerformanceWithManyDynamicRoutes(): void
    {
        // Enregistrer beaucoup de routes dynamiques
        for ($i = 1; $i <= 100; $i++) {
            $className = "DynamicRouteController{$i}";
            eval("
                class {$className} {
                    #[\\JulienLinard\\Router\\Attributes\\Route(path: '/user/{id}/action{$i}', methods: ['GET'], name: 'dynamic.{$i}')]
                    public function index(\\JulienLinard\\Router\\Request \$request): \\JulienLinard\\Router\\Response {
                        return new \\JulienLinard\\Router\\Response(200, 'Dynamic {$i}');
                    }
                }
            ");
            
            $this->router->registerRoutes($className);
        }

        $start = microtime(true);

        // Tester plusieurs routes dynamiques
        for ($i = 1; $i <= 50; $i++) {
            $routeNum = rand(1, 100);
            $userId = rand(1, 1000);
            $request = new Request("/user/{$userId}/action{$routeNum}", 'GET');
            $response = $this->router->handle($request);
            $this->assertEquals(200, $response->getStatusCode());
        }

        $duration = microtime(true) - $start;

        // 50 requêtes devraient être rapides (< 0.5 seconde)
        $this->assertLessThan(0.5, $duration, 
            "50 requêtes sur 100 routes dynamiques devraient prendre moins de 0.5 seconde");
    }

    /**
     * Test de performance pour la génération d'URL avec beaucoup de routes
     */
    public function testPerformanceUrlGeneration(): void
    {
        // Enregistrer beaucoup de routes
        for ($i = 1; $i <= 200; $i++) {
            $className = "UrlGenController{$i}";
            $path = "/url{$i}";
            $name = "url.{$i}";
            $content = "URL {$i}";
            
            eval("
                class {$className} {
                    #[\\JulienLinard\\Router\\Attributes\\Route(path: '{$path}', methods: ['GET'], name: '{$name}')]
                    public function index(): \\JulienLinard\\Router\\Response {
                        return new \\JulienLinard\\Router\\Response(200, '{$content}');
                    }
                }
            ");
            
            $this->router->registerRoutes($className);
        }

        $start = microtime(true);

        // Générer beaucoup d'URLs
        for ($i = 1; $i <= 500; $i++) {
            $routeNum = rand(1, 200);
            $url = $this->router->url("url.{$routeNum}");
            $this->assertNotNull($url);
        }

        $duration = microtime(true) - $start;

        // 500 générations d'URL devraient être rapides (< 0.1 seconde)
        $this->assertLessThan(0.1, $duration, 
            "500 générations d'URL devraient prendre moins de 0.1 seconde");
    }

    /**
     * Test de performance pour le tri des routes dynamiques
     */
    public function testPerformanceDynamicRoutesSorting(): void
    {
        // Enregistrer des routes avec différents niveaux de spécificité
        for ($i = 1; $i <= 50; $i++) {
            $className = "SortController{$i}";
            $paramCount = ($i % 3) + 1; // 1, 2 ou 3 paramètres
            
            // Rendre le path unique en ajoutant l'ID de la route
            $path = "/test{$i}";
            for ($j = 1; $j <= $paramCount; $j++) {
                $path .= "/{param{$j}}";
            }
            
            $pathStr = $path;
            $name = "sort.{$i}";
            $content = "Sort {$i}";
            
            eval("
                class {$className} {
                    #[\\JulienLinard\\Router\\Attributes\\Route(path: '{$pathStr}', methods: ['GET'], name: '{$name}')]
                    public function index(\\JulienLinard\\Router\\Request \$request): \\JulienLinard\\Router\\Response {
                        return new \\JulienLinard\\Router\\Response(200, '{$content}');
                    }
                }
            ");
            
            $this->router->registerRoutes($className);
        }

        $start = microtime(true);

        // Tester plusieurs routes (le tri devrait être optimisé)
        for ($i = 1; $i <= 50; $i++) {
            $routeNum = rand(1, 50);
            // Tester avec un path qui correspond à une route spécifique
            $request = new Request("/test{$routeNum}/1/2/3", 'GET');
            $response = $this->router->handle($request);
            // Peut être 200 ou 404 selon la route
            $this->assertContains($response->getStatusCode(), [200, 404]);
        }

        $duration = microtime(true) - $start;

        // 100 requêtes devraient être rapides (< 0.5 seconde)
        $this->assertLessThan(0.5, $duration, 
            "100 requêtes avec tri de routes devraient prendre moins de 0.5 seconde");
    }
}

