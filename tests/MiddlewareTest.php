<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Router\Router;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;
use JulienLinard\Router\Attributes\Route;
use JulienLinard\Router\Middlewares\AuthMiddleware;
use JulienLinard\Router\Middlewares\RoleMiddleware;
use JulienLinard\Router\Middlewares\LoggingMiddleware;
use JulienLinard\Router\Middlewares\CorsMiddleware;

class MiddlewareTest extends TestCase
{
  /**
   * Test du middleware d'authentification - utilisateur non authentifié
   */
  public function testAuthMiddlewareUnauthenticated()
  {
    // S'assurer qu'aucune session n'est active
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_destroy();
    }
    
    $middleware = new AuthMiddleware();
    $request = new Request('/test', 'GET');
    
    $response = $middleware->handle($request);
    
    // Le middleware doit retourner une réponse 401
    $this->assertInstanceOf(Response::class, $response);
    $this->assertEquals(401, $response->getStatusCode());
    
    $content = json_decode($response->getContent(), true);
    $this->assertArrayHasKey('error', $content);
    $this->assertEquals('Unauthorized', $content['error']);
  }

  /**
   * Test du middleware d'authentification - utilisateur authentifié
   */
  public function testAuthMiddlewareAuthenticated()
  {
    // Démarrer une session et simuler un utilisateur authentifié
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    
    $_SESSION['user'] = ['id' => 1, 'name' => 'Test User'];
    
    $middleware = new AuthMiddleware();
    $request = new Request('/test', 'GET');
    
    $response = $middleware->handle($request);
    
    // Le middleware doit retourner null (continuer l'exécution)
    $this->assertNull($response);
    
    // Nettoyer
    unset($_SESSION['user']);
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_destroy();
    }
  }

  /**
   * Test du middleware de rôle - utilisateur sans le bon rôle
   */
  public function testRoleMiddlewareWithoutRole()
  {
    // Démarrer une session
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    
    // Utilisateur sans rôle ou avec un rôle différent
    $_SESSION['user'] = ['id' => 1, 'role' => 'user'];
    
    $middleware = new RoleMiddleware('admin');
    $request = new Request('/admin', 'GET');
    
    $response = $middleware->handle($request);
    
    // Le middleware doit retourner une réponse 403
    $this->assertInstanceOf(Response::class, $response);
    $this->assertEquals(403, $response->getStatusCode());
    
    $content = json_decode($response->getContent(), true);
    $this->assertArrayHasKey('error', $content);
    $this->assertEquals('Access denied', $content['error']);
    
    // Nettoyer
    unset($_SESSION['user']);
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_destroy();
    }
  }

  /**
   * Test du middleware de rôle - utilisateur avec le bon rôle
   */
  public function testRoleMiddlewareWithRole()
  {
    // Démarrer une session
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
    
    $_SESSION['user'] = ['id' => 1, 'role' => 'admin'];
    
    $middleware = new RoleMiddleware('admin');
    $request = new Request('/admin', 'GET');
    
    $response = $middleware->handle($request);
    
    // Le middleware doit retourner null (continuer l'exécution)
    $this->assertNull($response);
    
    // Nettoyer
    unset($_SESSION['user']);
    if (session_status() === PHP_SESSION_ACTIVE) {
      session_destroy();
    }
  }

  /**
   * Test du middleware de logging
   */
  public function testLoggingMiddleware()
  {
    $middleware = new LoggingMiddleware();
    $request = new Request('/test', 'GET');
    
    // Capturer la sortie d'error_log (difficile à tester directement)
    // On vérifie juste que le middleware ne bloque pas l'exécution
    $response = $middleware->handle($request);
    
    $this->assertNull($response); // Le middleware continue l'exécution
  }

  /**
   * Test du middleware CORS - requête OPTIONS (preflight)
   */
  public function testCorsMiddlewarePreflight()
  {
    $cors = new CorsMiddleware(['https://example.com']);
    
    $_SERVER['HTTP_ORIGIN'] = 'https://example.com';
    $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
    $request = new Request('/api/test', 'OPTIONS');
    
    $response = $cors->handle($request);
    
    // Le middleware doit retourner une réponse 204 pour OPTIONS
    $this->assertInstanceOf(Response::class, $response);
    $this->assertEquals(204, $response->getStatusCode());
    
    unset($_SERVER['HTTP_ORIGIN']);
    unset($_SERVER['REQUEST_METHOD']);
  }

  /**
   * Test du middleware CORS - requête normale avec credentials
   */
  public function testCorsMiddlewareWithCredentials()
  {
    $cors = new CorsMiddleware(
      ['https://example.com'],
      ['GET', 'POST'],
      ['Content-Type', 'Authorization'],
      true // allowCredentials
    );
    
    $_SERVER['HTTP_ORIGIN'] = 'https://example.com';
    $request = new Request('/api/test', 'GET');
    
    $response = $cors->handle($request);
    
    // Le middleware doit continuer l'exécution
    $this->assertNull($response);
    
    unset($_SERVER['HTTP_ORIGIN']);
  }

  /**
   * Test de chaîne de middlewares
   */
  public function testMiddlewareChain()
  {
    $router = new Router();
    
    // Ajouter plusieurs middlewares globaux
    $router->addMiddleware(new LoggingMiddleware());
    
    // Créer un contrôleur de test avec attribut Route
    $controller = new class {
      #[Route(path: '/', methods: ['GET'], name: 'test')]
      public function index(): Response
      {
        return new Response(200, 'OK');
      }
    };
    
    // Enregistrer une route
    $router->registerRoutes(get_class($controller));
    
    $request = new Request('/', 'GET');
    $response = $router->handle($request);
    
    // La réponse doit être OK (les middlewares ne bloquent pas)
    $this->assertEquals(200, $response->getStatusCode());
  }
}
