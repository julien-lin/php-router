<?php

use JulienLinard\Router\Attributes\Route;
use PHPUnit\Framework\TestCase;
use JulienLinard\Router\Router;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

class RouterTest extends TestCase
{
  public function testRouteRegistration()
  {
    $router = new Router();
    $router->registerRoutes(DummyController::class);

    $request = new Request('/');
    $response = $router->handle($request);

    $this->assertEquals('Hello, world!', $response->getContent());
  }
}

class DummyController
{
  #[Route(path: '/', methods: ['GET'])]
  public function index(): Response
  {
    return new Response(200, 'Hello, world!');
  }
}
