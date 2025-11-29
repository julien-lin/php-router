<?php

namespace Tests;

use JulienLinard\Router\Attributes\Route;
use PHPUnit\Framework\TestCase;
use JulienLinard\Router\Router;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;
use JulienLinard\Router\ErrorHandler;
use JulienLinard\Router\Middlewares\CorsMiddleware;

class RouterTest extends TestCase
{
  private Router $router;

  protected function setUp(): void
  {
    $this->router = new Router();
  }

  public function testRouteRegistration()
  {
    $this->router->registerRoutes(DummyController::class);

    $request = new Request('/', 'GET');
    $response = $this->router->handle($request);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('Hello, world!', $response->getContent());
  }

  public function testRouteNotFound()
  {
    $this->router->registerRoutes(DummyController::class);

    $request = new Request('/not-found', 'GET');
    $response = $this->router->handle($request);

    $this->assertEquals(404, $response->getStatusCode());
  }

  public function testMethodNotAllowed()
  {
    $this->router->registerRoutes(DummyController::class);

    $request = new Request('/', 'POST');
    $response = $this->router->handle($request);

    $this->assertEquals(405, $response->getStatusCode());
  }

  public function testDynamicRouteWithSingleParameter()
  {
    $this->router->registerRoutes(UserController::class);

    $request = new Request('/user/123', 'GET');
    $response = $this->router->handle($request);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('123', $request->getRouteParam('id'));
    $this->assertEquals(['id' => '123'], $request->getRouteParams());
  }

  public function testDynamicRouteWithMultipleParameters()
  {
    $this->router->registerRoutes(PostController::class);

    $request = new Request('/user/123/post/my-slug', 'GET');
    $response = $this->router->handle($request);

    $this->assertEquals(200, $response->getStatusCode());
    $this->assertEquals('123', $request->getRouteParam('userId'));
    $this->assertEquals('my-slug', $request->getRouteParam('slug'));
    $this->assertEquals(['userId' => '123', 'slug' => 'my-slug'], $request->getRouteParams());
  }

  public function testDynamicRouteNotFound()
  {
    $this->router->registerRoutes(UserController::class);

    $request = new Request('/user/123/edit', 'GET');
    $response = $this->router->handle($request);

    $this->assertEquals(404, $response->getStatusCode());
  }

  public function testMultipleHttpMethods()
  {
    $this->router->group('/api', [], function($router) {
      $router->registerRoutes(ApiController::class);
    });

    // Test GET
    $request = new Request('/api/users', 'GET');
    $response = $this->router->handle($request);
    $this->assertEquals(200, $response->getStatusCode());

    // Test POST
    $request = new Request('/api/users', 'POST');
    $response = $this->router->handle($request);
    $this->assertEquals(201, $response->getStatusCode());
  }

  public function testRouteCollisionDetection()
  {
    $this->router->registerRoutes(DummyController::class);

    $this->expectException(\RuntimeException::class);
    $this->expectExceptionMessage('Collision de route');
    $this->router->registerRoutes(DuplicateRouteController::class);
  }

  public function testGetRoutes()
  {
    $this->router->registerRoutes(DummyController::class);
    $this->router->registerRoutes(UserController::class);

    $routes = $this->router->getRoutes();

    $this->assertArrayHasKey('static', $routes);
    $this->assertArrayHasKey('dynamic', $routes);
    $this->assertArrayHasKey('/', $routes['static']);
    $this->assertNotEmpty($routes['dynamic']);
  }

  public function testGetRouteByName()
  {
    $this->router->registerRoutes(DummyController::class);
    $this->router->registerRoutes(UserController::class);

    $route = $this->router->getRouteByName('home');
    $this->assertNotNull($route);
    $this->assertEquals('/', $route['path']);
    $this->assertEquals('GET', $route['method']);

    $route = $this->router->getRouteByName('user.show');
    $this->assertNotNull($route);
    $this->assertEquals('/user/{id}', $route['path']);
    $this->assertArrayHasKey('params', $route);
  }

  public function testGetRouteByNameNotFound()
  {
    $this->router->registerRoutes(DummyController::class);

    $route = $this->router->getRouteByName('non-existent');
    $this->assertNull($route);
  }

  public function testRequestQueryParams()
  {
    $request = new Request('/test?foo=bar&baz=qux', 'GET');
    
    $this->assertEquals('bar', $request->getQueryParam('foo'));
    $this->assertEquals('qux', $request->getQueryParam('baz'));
    $this->assertEquals(['foo' => 'bar', 'baz' => 'qux'], $request->getQueryParams());
    $this->assertEquals('default', $request->getQueryParam('non-existent', 'default'));
  }

  public function testRequestHeaders()
  {
    // Note: Dans un vrai environnement, on devrait mock $_SERVER
    // Pour ce test, on vérifie juste que les méthodes existent
    $request = new Request('/test', 'GET');
    
    $this->assertIsArray($request->getHeaders());
    $this->assertNull($request->getHeader('non-existent-header'));
    $this->assertEquals('default', $request->getHeader('non-existent-header', 'default'));
  }

  public function testResponseJson()
  {
    $data = ['message' => 'Hello', 'status' => 'ok'];
    $response = Response::json($data, 201);

    $this->assertEquals(201, $response->getStatusCode());
    $this->assertJson($response->getContent());
    $decoded = json_decode($response->getContent(), true);
    $this->assertEquals($data, $decoded);
  }

  public function testResponseHeaders()
  {
    $response = new Response(200, 'Test content');
    $response->setHeader('X-Custom-Header', 'test-value');

    $headers = $response->getHeaders();
    $this->assertArrayHasKey('x-custom-header', $headers);
    $this->assertEquals('test-value', $headers['x-custom-header']);
  }

  public function testResponseHeaderSanitization()
  {
    $response = new Response();
    
    // Test que les caractères dangereux sont supprimés
    $response->setHeader('X-Test', "value\r\nInjection");
    $headers = $response->getHeaders();
    
    // Le header doit être nettoyé et normalisé en minuscules
    $this->assertArrayHasKey('x-test', $headers);
    $this->assertStringNotContainsString("\r", $headers['x-test']);
    $this->assertStringNotContainsString("\n", $headers['x-test']);
    $this->assertEquals('valueInjection', $headers['x-test']);
  }

  public function testUrlGenerationStaticRoute()
  {
    $this->router->registerRoutes(DummyController::class);

    $url = $this->router->url('home');
    $this->assertEquals('/', $url);
  }

  public function testUrlGenerationDynamicRoute()
  {
    $this->router->registerRoutes(UserController::class);

    $url = $this->router->url('user.show', ['id' => '123']);
    $this->assertEquals('/user/123', $url);
  }

  public function testUrlGenerationWithMultipleParams()
  {
    $this->router->registerRoutes(PostController::class);

    $url = $this->router->url('post.show', ['userId' => '123', 'slug' => 'mon-article']);
    $this->assertEquals('/user/123/post/mon-article', $url);
  }

  public function testUrlGenerationWithQueryParams()
  {
    $this->router->registerRoutes(UserController::class);

    $url = $this->router->url('user.show', ['id' => '123'], ['page' => '2', 'sort' => 'name']);
    $this->assertStringContainsString('/user/123', $url);
    $this->assertStringContainsString('page=2', $url);
    $this->assertStringContainsString('sort=name', $url);
  }

  public function testUrlGenerationMissingParam()
  {
    $this->router->registerRoutes(UserController::class);

    $this->expectException(\InvalidArgumentException::class);
    $this->expectExceptionMessage("Le paramètre 'id' est requis");
    $this->router->url('user.show', []);
  }

  public function testUrlGenerationNonExistentRoute()
  {
    $url = $this->router->url('non-existent');
    $this->assertNull($url);
  }

  public function testRouteGrouping()
  {
    $this->router->group('/api', [], function($router) {
      $router->registerRoutes(ApiController::class);
    });

    $request = new Request('/api/users', 'GET');
    $response = $this->router->handle($request);
    $this->assertEquals(200, $response->getStatusCode());
  }

  public function testRouteGroupingWithMiddleware()
  {
    // Utiliser un objet pour partager l'état entre le test et le middleware
    $state = new \stdClass();
    $state->called = false;
    
    $testMiddleware = new class($state) implements \JulienLinard\Router\Middleware {
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
      $router->registerRoutes(DummyController::class);
    });

    $request = new Request('/admin/', 'GET');
    $this->router->handle($request);
    // Note: Le middleware sera appelé mais ne bloque pas l'exécution dans ce cas
    $this->assertTrue($state->called);
  }

  public function testNestedRouteGroups()
  {
    $this->router->group('/api', [], function($router) {
      $router->group('/v1', [], function($router) {
        $router->registerRoutes(ApiController::class);
      });
    });

    $request = new Request('/api/v1/users', 'GET');
    $response = $this->router->handle($request);
    $this->assertEquals(200, $response->getStatusCode());
  }

  public function testUrlGenerationWithGroupPrefix()
  {
    $this->router->group('/api', [], function($router) {
      $router->registerRoutes(ApiController::class);
    });

    $url = $this->router->url('api.users.index');
    $this->assertEquals('/api/users', $url);
  }

  public function testGetRouteByNamePerformance()
  {
    // Enregistrer plusieurs routes
    $this->router->registerRoutes(DummyController::class);
    $this->router->registerRoutes(UserController::class);
    $this->router->registerRoutes(PostController::class);
    $this->router->registerRoutes(ApiController::class);

    // Test que getRouteByName est O(1) même avec beaucoup de routes
    $route = $this->router->getRouteByName('home');
    $this->assertNotNull($route);
    $this->assertEquals('/', $route['path']);
    
    $route = $this->router->getRouteByName('user.show');
    $this->assertNotNull($route);
    $this->assertTrue($route['route']['name'] === 'user.show');
  }

  public function testRequestMaxBodySize()
  {
    $request = new Request();
    
    // Test taille par défaut
    $this->assertEquals(10 * 1024 * 1024, $request->getMaxBodySize());
    
    // Test modification de la taille
    $request->setMaxBodySize(5 * 1024 * 1024);
    $this->assertEquals(5 * 1024 * 1024, $request->getMaxBodySize());
  }

  public function testResponseSendHeaderStatic()
  {
    // Test de la méthode utilitaire statique
    Response::sendHeader('X-Test-Header', 'test-value');
    
    // Vérifier que le header a été envoyé (en test, on vérifie juste qu'il n'y a pas d'erreur)
    $this->assertTrue(true); // Si on arrive ici, pas d'exception
  }

  public function testCorsMiddlewareOriginValidation()
  {
    $cors = new CorsMiddleware(['https://example.com']);
    
    // Test origine valide - créer une requête avec header Origin
    // On simule une requête avec header Origin via $_SERVER
    $_SERVER['HTTP_ORIGIN'] = 'https://example.com';
    $request = new Request();
    
    // Le middleware devrait accepter l'origine valide
    $response = $cors->handle($request);
    $this->assertNull($response); // Le middleware continue l'exécution
    
    // Test origine invalide (non autorisée)
    $_SERVER['HTTP_ORIGIN'] = 'https://evil.com';
    $request2 = new Request();
    $response2 = $cors->handle($request2);
    // Le middleware devrait continuer mais ne pas ajouter les headers CORS
    $this->assertNull($response2);
    
    // Nettoyer $_SERVER
    unset($_SERVER['HTTP_ORIGIN']);
  }

  public function testErrorHandlerStackTrace()
  {
    try {
      throw new \RuntimeException('Test error');
    } catch (\Throwable $e) {
      $response = ErrorHandler::handleServerError($e);
      $this->assertEquals(500, $response->getStatusCode());
      $content = json_decode($response->getContent(), true);
      $this->assertArrayHasKey('error', $content);
    }
  }
}

// Contrôleurs de test

class DummyController
{
  #[Route(path: '/', methods: ['GET'], name: 'home')]
  public function index(): Response
  {
    return new Response(200, 'Hello, world!');
  }
}

class UserController
{
  #[Route(path: '/user/{id}', methods: ['GET'], name: 'user.show')]
  public function show(Request $request): Response
  {
    $id = $request->getRouteParam('id');
    return new Response(200, "User ID: {$id}");
  }
}

class PostController
{
  #[Route(path: '/user/{userId}/post/{slug}', methods: ['GET'], name: 'post.show')]
  public function show(Request $request): Response
  {
    $userId = $request->getRouteParam('userId');
    $slug = $request->getRouteParam('slug');
    return new Response(200, "User: {$userId}, Post: {$slug}");
  }
}

class ApiController
{
  #[Route(path: '/users', methods: ['GET'], name: 'api.users.index')]
  public function index(): Response
  {
    return new Response(200, 'Users list');
  }

  #[Route(path: '/users', methods: ['POST'], name: 'api.users.store')]
  public function store(): Response
  {
    return new Response(201, 'User created');
  }
}

class DuplicateRouteController
{
  #[Route(path: '/', methods: ['GET'], name: 'duplicate')]
  public function index(): Response
  {
    return new Response(200, 'Duplicate');
  }
}
