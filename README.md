# PHP Router

Un routeur PHP personnalisé pour gérer les routes de votre application.

## Installation

Utilisez Composer pour installer le package :

```bash
composer require julienlinard/php-router
```

## Implémentation du Routeur

### Définition des Routes

Les routes sont définies dans les contrôleurs en utilisant l'attribut `Route`. Par exemple, dans `HomeController.php` :

```php
// filepath: /Users/julien/Downloads/ERN24_PROJET_POO_VIERGE-main/App/Controller/HomeController.php
<?php

namespace App\Controller;

use Core\View\View;
use Core\Router\Response;
use Core\Controller\Controller;
use Core\Router\Attributes\Route;

class HomeController extends Controller
{
  #[Route(path: '/', name: "home", methods: ["GET"])]
  public function home(): Response
  {
    $data = [
      'title' => 'My Website',
      'content' => 'Welcome to my website!'
    ];
    $view = new View('home/index');
    return $view->render($data);
  }
}
```

### Configuration du Routeur

Le routeur est configuré pour analyser les annotations de route et les enregistrer. Voici un exemple de configuration :

```php
<?php

namespace Core\Router;

use ReflectionClass;

class Router
{
  private array $routes = [];

  public function registerRoutes(string $controller): void
  {
    $reflection = new ReflectionClass($controller);
    foreach ($reflection->getMethods() as $method) {
      $attributes = $method->getAttributes(Route::class);
      foreach ($attributes as $attribute) {
        $route = $attribute->newInstance();
        $this->routes[$route->path] = [$controller, $method->getName()];
      }
    }
  }

  public function dispatch(Request $request): Response
  {
    $path = $request->getPath();
    if (isset($this->routes[$path])) {
      [$controller, $method] = $this->routes[$path];
      return (new $controller)->$method($request);
    }
    return new Response(404, 'Not Found');
  }
}
```

### Utilisation du Routeur

Pour utiliser le routeur, vous devez créer une instance de `Router`, enregistrer les routes et dispatcher les requêtes :

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use Core\Router\Router;
use Core\Router\Request;

$router = new Router();
$router->registerRoutes(App\Controller\HomeController::class);

$request = new Request();
$response = $router->dispatch($request);

http_response_code($response->getStatusCode());
echo $response->getContent();
```

## Conclusion

Ce document fournit une vue d'ensemble de l'implémentation et de l'utilisation du routeur dans ce projet. Pour plus de détails, veuillez consulter les fichiers source et les commentaires dans le code.
