# PHP Router

Un routeur PHP moderne et complet pour gérer les routes de votre application avec support des routes dynamiques, middlewares, et toutes les fonctionnalités essentielles.

## 📋 Table des matières

- [Installation](#installation)
- [Démarrage rapide](#démarrage-rapide)
- [Définition des routes](#définition-des-routes)
- [Routes dynamiques](#routes-dynamiques)
- [Groupes de routes](#groupes-de-routes)
- [Génération d'URL](#génération-durl)
- [Request](#request)
- [Response](#response)
- [Middlewares](#middlewares)
- [Gestion des erreurs](#gestion-des-erreurs)
- [API Reference](#api-reference)
- [Exemples complets](#exemples-complets)

## 🚀 Installation

Utilisez Composer pour installer le package :

```bash
composer require julienlinard/php-router
```

**Requirements** : PHP 8.0 ou supérieur

## ⚡ Démarrage rapide

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use JulienLinard\Router\Router;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;
use JulienLinard\Router\Attributes\Route;

// Créer une instance du routeur
$router = new Router();

// Définir un contrôleur avec des routes
class HomeController
{
    #[Route(path: '/', methods: ['GET'], name: 'home')]
    public function index(): Response
    {
        return new Response(200, 'Bienvenue !');
    }
}

// Enregistrer les routes
$router->registerRoutes(HomeController::class);

// Traiter la requête
$request = new Request();
$response = $router->handle($request);

// Envoyer la réponse
$response->send();
```

## 🛣️ Définition des routes

Les routes sont définies dans vos contrôleurs en utilisant l'attribut `Route` (PHP 8).

### Route simple

```php
<?php

namespace App\Controller;

use JulienLinard\Router\Attributes\Route;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

class HomeController
{
    #[Route(path: '/', methods: ['GET'], name: 'home')]
    public function index(): Response
    {
        return new Response(200, 'Page d\'accueil');
    }
}
```

### Routes avec plusieurs méthodes HTTP

```php
class ApiController
{
    #[Route(path: '/api/users', methods: ['GET'], name: 'api.users.index')]
    public function index(): Response
    {
        return Response::json(['users' => []]);
    }

    #[Route(path: '/api/users', methods: ['POST'], name: 'api.users.store')]
    public function store(Request $request): Response
    {
        $data = $request->getBody();
        // Traiter les données...
        return Response::json(['message' => 'Utilisateur créé'], 201);
    }
}
```

### Enregistrement des routes

```php
$router = new Router();
$router->registerRoutes(HomeController::class);
$router->registerRoutes(ApiController::class);
```

### Groupes de routes

Les groupes de routes permettent d'organiser vos routes avec un préfixe commun et des middlewares partagés.

```php
use JulienLinard\Router\Middlewares\AuthMiddleware;

// Groupe avec préfixe uniquement
$router->group('/api', [], function($router) {
    $router->registerRoutes(ApiController::class);
    // Toutes les routes auront le préfixe /api
});

// Groupe avec préfixe et middlewares
$router->group('/admin', [AuthMiddleware::class], function($router) {
    $router->registerRoutes(AdminController::class);
    // Toutes les routes auront le préfixe /admin ET le middleware AuthMiddleware
});

// Groupes imbriqués
$router->group('/api', [], function($router) {
    $router->group('/v1', [], function($router) {
        $router->registerRoutes(ApiV1Controller::class);
        // Routes avec préfixe /api/v1
    });
    
    $router->group('/v2', [], function($router) {
        $router->registerRoutes(ApiV2Controller::class);
        // Routes avec préfixe /api/v2
    });
});
```

**Exemple complet** :
```php
class ApiController
{
    // Path défini dans le contrôleur : '/users'
    #[Route(path: '/users', methods: ['GET'], name: 'api.users.index')]
    public function index(): Response
    {
        return Response::json(['users' => []]);
    }
}

// Enregistrement avec groupe
$router->group('/api', [], function($router) {
    $router->registerRoutes(ApiController::class);
});

// La route sera accessible à : /api/users
```

## 🔄 Routes dynamiques

Le router supporte les routes dynamiques avec paramètres extraits automatiquement de l'URL.

### Route avec un paramètre

```php
class UserController
{
    #[Route(path: '/user/{id}', methods: ['GET'], name: 'user.show')]
    public function show(Request $request): Response
    {
        $userId = $request->getRouteParam('id');
        
        return Response::json([
            'user_id' => $userId,
            'message' => "Affichage de l'utilisateur {$userId}"
        ]);
    }
}
```

**Exemple d'URL** : `/user/123` → `$userId = '123'`

### Route avec plusieurs paramètres

```php
class PostController
{
    #[Route(path: '/user/{userId}/post/{slug}', methods: ['GET'], name: 'post.show')]
    public function show(Request $request): Response
    {
        $userId = $request->getRouteParam('userId');
        $slug = $request->getRouteParam('slug');
        
        return Response::json([
            'user_id' => $userId,
            'slug' => $slug
        ]);
    }
}
```

**Exemple d'URL** : `/user/123/post/mon-article` → `$userId = '123'`, `$slug = 'mon-article'`

### Accès aux paramètres

```php
// Obtenir un paramètre spécifique
$id = $request->getRouteParam('id');
$id = $request->getRouteParam('id', 'default'); // avec valeur par défaut

// Obtenir tous les paramètres
$params = $request->getRouteParams(); // ['id' => '123', 'slug' => 'mon-article']
```

## 📥 Request

La classe `Request` fournit un accès complet aux données de la requête HTTP.

### Path et méthode

```php
$request = new Request();

$path = $request->getPath();        // '/user/123'
$method = $request->getMethod();     // 'GET', 'POST', etc.
```

### Query parameters

```php
// URL: /search?q=php&page=2
$query = $request->getQueryParam('q');           // 'php'
$page = $request->getQueryParam('page', 1);      // '2' ou 1 par défaut
$allParams = $request->getQueryParams();         // ['q' => 'php', 'page' => '2']
```

### Headers HTTP

```php
$contentType = $request->getHeader('content-type');
$allHeaders = $request->getHeaders();
$customHeader = $request->getHeader('x-custom-header', 'default');
```

### Cookies

```php
$token = $request->getCookie('auth_token');
$allCookies = $request->getCookies();
```

### Body (POST/PUT/PATCH)

```php
// Pour JSON
$data = $request->getBody();                    // ['name' => 'John', 'email' => '...']
$name = $request->getBodyParam('name');         // 'John'
$rawBody = $request->getRawBody();              // String brute

// Pour form-urlencoded
$data = $request->getBody();                    // ['field1' => 'value1', ...]
```

### Méthodes utilitaires

```php
if ($request->isAjax()) {
    // Requête AJAX
}

if ($request->wantsJson()) {
    // Le client accepte JSON
}
```

### Personnalisation pour les tests

```php
// Créer une requête personnalisée pour les tests
$request = new Request('/user/123', 'GET');
```

## 📤 Response

La classe `Response` permet de créer et envoyer des réponses HTTP.

### Réponse simple

```php
$response = new Response(200, 'Contenu de la réponse');
$response->send();
```

### Réponse JSON

```php
$data = ['message' => 'Succès', 'data' => []];
$response = Response::json($data, 200);
$response->send();
```

### Headers personnalisés

```php
$response = new Response(200, 'Contenu');
$response->setHeader('X-Custom-Header', 'valeur');
$response->setHeader('Content-Type', 'application/xml');
$response->send();
```

### Méthodes disponibles

```php
$statusCode = $response->getStatusCode();    // 200
$content = $response->getContent();          // 'Contenu'
$headers = $response->getHeaders();         // ['content-type' => 'application/json']
```

## 🛡️ Middlewares

Les middlewares permettent d'exécuter du code avant le traitement de la requête.

### Middlewares globaux

```php
use JulienLinard\Router\Middlewares\CorsMiddleware;
use JulienLinard\Router\Middlewares\LoggingMiddleware;

$router = new Router();

// Ajouter un middleware global
$router->addMiddleware(new CorsMiddleware());
$router->addMiddleware(new LoggingMiddleware());
```

### Middlewares spécifiques à une route

```php
use JulienLinard\Router\Middlewares\AuthMiddleware;
use JulienLinard\Router\Middlewares\RoleMiddleware;

class AdminController
{
    #[Route(
        path: '/admin/dashboard',
        methods: ['GET'],
        name: 'admin.dashboard',
        middleware: [AuthMiddleware::class, RoleMiddleware::class]
    )]
    public function dashboard(): Response
    {
        return new Response(200, 'Dashboard admin');
    }
}
```

### Middlewares disponibles

#### CorsMiddleware

```php
use JulienLinard\Router\Middlewares\CorsMiddleware;

// Configuration par défaut (toutes origines)
$cors = new CorsMiddleware();

// Configuration personnalisée
$cors = new CorsMiddleware(
    allowedOrigins: ['https://example.com', 'https://app.example.com'],
    allowedMethods: ['GET', 'POST', 'PUT', 'DELETE'],
    allowedHeaders: ['Content-Type', 'Authorization'],
    allowCredentials: true
);

$router->addMiddleware($cors);
```

#### AuthMiddleware

```php
use JulienLinard\Router\Middlewares\AuthMiddleware;

class ProtectedController
{
    #[Route(
        path: '/profile',
        methods: ['GET'],
        middleware: [AuthMiddleware::class]
    )]
    public function profile(): Response
    {
        // L'utilisateur est authentifié
        return Response::json(['user' => $_SESSION['user']]);
    }
}
```

#### RoleMiddleware

```php
use JulienLinard\Router\Middlewares\RoleMiddleware;

class AdminController
{
    #[Route(
        path: '/admin/users',
        methods: ['GET'],
        middleware: [AuthMiddleware::class, RoleMiddleware::class]
    )]
    public function users(): Response
    {
        // L'utilisateur est authentifié ET a le rôle admin
        return Response::json(['users' => []]);
    }
}

// Dans votre bootstrap
$router->addMiddleware(new RoleMiddleware('admin'));
```

#### LoggingMiddleware

```php
use JulienLinard\Router\Middlewares\LoggingMiddleware;

$router->addMiddleware(new LoggingMiddleware());
// Log toutes les requêtes dans error_log
```

### Créer un middleware personnalisé

```php
<?php

namespace App\Middlewares;

use JulienLinard\Router\Middleware;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

class CustomMiddleware implements Middleware
{
    public function handle(Request $request): void
    {
        // Votre logique ici
        // Par exemple, vérifier une condition
        
        if (/* condition non remplie */) {
            Response::json(['error' => 'Accès refusé'], 403)->send();
            exit;
        }
        
        // Sinon, continuer l'exécution
    }
}
```

## ⚠️ Gestion des erreurs

Le router gère automatiquement les erreurs courantes :

- **404 Not Found** : Route non trouvée
- **405 Method Not Allowed** : Méthode HTTP non supportée pour cette route
- **500 Internal Server Error** : Erreur serveur (exceptions)

### Personnaliser la gestion d'erreurs

```php
use JulienLinard\Router\ErrorHandler;

// Les erreurs sont gérées automatiquement par le router
// Vous pouvez utiliser ErrorHandler directement si besoin

$response = ErrorHandler::handleNotFound();      // 404
$response = ErrorHandler::handleServerError($e); // 500
```

## 📚 API Reference

### Router

#### `registerRoutes(string $controller): void`

Enregistre toutes les routes d'un contrôleur.

```php
$router->registerRoutes(HomeController::class);
```

#### `addMiddleware(Middleware $middleware): void`

Ajoute un middleware global.

```php
$router->addMiddleware(new CorsMiddleware());
```

#### `handle(Request $request): Response`

Traite une requête et retourne la réponse.

```php
$response = $router->handle($request);
```

#### `getRoutes(): array`

Retourne toutes les routes enregistrées (debug).

```php
$routes = $router->getRoutes();
// ['static' => [...], 'dynamic' => [...]]
```

#### `getRouteByName(string $name): ?array`

Retourne une route par son nom.

```php
$route = $router->getRouteByName('home');
// ['path' => '/', 'method' => 'GET', 'route' => [...]]
```

#### `url(string $name, array $params = [], array $queryParams = []): ?string`

Génère une URL à partir du nom d'une route et de ses paramètres.

```php
// Route statique
$url = $router->url('home');
// Retourne : '/'

// Route dynamique avec un paramètre
$url = $router->url('user.show', ['id' => '123']);
// Retourne : '/user/123'

// Route dynamique avec plusieurs paramètres
$url = $router->url('post.show', ['userId' => '123', 'slug' => 'mon-article']);
// Retourne : '/user/123/post/mon-article'

// Avec query parameters
$url = $router->url('user.show', ['id' => '123'], ['page' => '2', 'sort' => 'name']);
// Retourne : '/user/123?page=2&sort=name'

// Retourne null si la route n'existe pas
$url = $router->url('non-existent');
// Retourne : null

// Lance une exception si un paramètre requis est manquant
try {
    $url = $router->url('user.show', []); // Paramètre 'id' manquant
} catch (\InvalidArgumentException $e) {
    // "Le paramètre 'id' est requis pour la route 'user.show'."
}
```

#### `group(string $prefix, array $middlewares, callable $callback): void`

Crée un groupe de routes avec un préfixe et des middlewares communs.

```php
$router->group('/api', [AuthMiddleware::class], function($router) {
    $router->registerRoutes(ApiController::class);
});
```

### Request

#### Méthodes principales

- `getPath(): string` - Chemin de la requête
- `getMethod(): string` - Méthode HTTP
- `getQueryParams(): array` - Tous les query parameters
- `getQueryParam(string $key, $default = null)` - Un query parameter
- `getHeaders(): array` - Tous les headers
- `getHeader(string $name, ?string $default = null): ?string` - Un header
- `getCookies(): array` - Tous les cookies
- `getCookie(string $name, $default = null)` - Un cookie
- `getBody(): ?array` - Body parsé
- `getBodyParam(string $key, $default = null)` - Un paramètre du body
- `getRawBody(): string` - Body brut
- `getRouteParams(): array` - Tous les paramètres de route
- `getRouteParam(string $key, $default = null)` - Un paramètre de route
- `isAjax(): bool` - Vérifie si c'est une requête AJAX
- `wantsJson(): bool` - Vérifie si le client accepte JSON

### Response

#### Constructeur

```php
new Response(int $statusCode = 200, string $content = '')
```

#### Méthodes statiques

- `Response::json($data, int $statusCode = 200): self` - Crée une réponse JSON

#### Méthodes d'instance

- `setHeader(string $name, string $value): void` - Définit un header
- `send(): void` - Envoie la réponse HTTP
- `getStatusCode(): int` - Code de statut
- `getContent(): string` - Contenu
- `getHeaders(): array` - Tous les headers

## 🔗 Génération d'URL

Le router permet de générer des URLs à partir des noms de routes, ce qui facilite la maintenance et évite les URLs codées en dur.

### Génération d'URL simple

```php
// Dans vos vues ou contrôleurs
$homeUrl = $router->url('home');
// Retourne : '/'

$userUrl = $router->url('user.show', ['id' => '123']);
// Retourne : '/user/123'
```

### Génération d'URL avec query parameters

```php
$url = $router->url('user.show', ['id' => '123'], ['page' => '2', 'sort' => 'name']);
// Retourne : '/user/123?page=2&sort=name'
```

### Utilisation dans les réponses

```php
class UserController
{
    #[Route(path: '/user/{id}', methods: ['GET'], name: 'user.show')]
    public function show(Request $request, Router $router): Response
    {
        $id = $request->getRouteParam('id');
        
        // Générer l'URL de l'utilisateur suivant
        $nextUserId = (int)$id + 1;
        $nextUrl = $router->url('user.show', ['id' => $nextUserId]);
        
        return Response::json([
            'user_id' => $id,
            'next_user_url' => $nextUrl
        ]);
    }
}
```

**Note** : Pour utiliser `$router` dans vos contrôleurs, vous pouvez l'injecter via un conteneur de dépendances ou le passer en paramètre.

## 💡 Exemples complets

### Exemple 1 : API REST complète avec groupes

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use JulienLinard\Router\Router;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;
use JulienLinard\Router\Attributes\Route;
use JulienLinard\Router\Middlewares\CorsMiddleware;

class UserController
{
    // Les paths sont définis sans le préfixe /api (ajouté par le groupe)
    #[Route(path: '/users', methods: ['GET'], name: 'users.index')]
    public function index(): Response
    {
        return Response::json(['users' => []]);
    }

    #[Route(path: '/users/{id}', methods: ['GET'], name: 'users.show')]
    public function show(Request $request): Response
    {
        $id = $request->getRouteParam('id');
        return Response::json(['user' => ['id' => $id]]);
    }

    #[Route(path: '/users', methods: ['POST'], name: 'users.store')]
    public function store(Request $request): Response
    {
        $data = $request->getBody();
        // Créer l'utilisateur...
        return Response::json(['message' => 'Utilisateur créé'], 201);
    }

    #[Route(path: '/users/{id}', methods: ['PUT'], name: 'users.update')]
    public function update(Request $request): Response
    {
        $id = $request->getRouteParam('id');
        $data = $request->getBody();
        // Mettre à jour l'utilisateur...
        return Response::json(['message' => 'Utilisateur mis à jour']);
    }

    #[Route(path: '/users/{id}', methods: ['DELETE'], name: 'users.delete')]
    public function delete(Request $request): Response
    {
        $id = $request->getRouteParam('id');
        // Supprimer l'utilisateur...
        return Response::json(['message' => 'Utilisateur supprimé'], 204);
    }
}

// Configuration avec groupes
$router = new Router();
$router->addMiddleware(new CorsMiddleware());

// Groupe API avec préfixe /api
$router->group('/api', [], function($router) {
    $router->registerRoutes(UserController::class);
});

// Traitement
$request = new Request();
$response = $router->handle($request);
$response->send();

// Génération d'URLs
$usersUrl = $router->url('users.index');           // '/api/users'
$userUrl = $router->url('users.show', ['id' => 5]); // '/api/users/5'
```

### Exemple 2 : Application web avec authentification et groupes

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use JulienLinard\Router\Router;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;
use JulienLinard\Router\Attributes\Route;
use JulienLinard\Router\Middlewares\AuthMiddleware;
use JulienLinard\Router\Middlewares\RoleMiddleware;

class HomeController
{
    #[Route(path: '/', methods: ['GET'], name: 'home')]
    public function index(): Response
    {
        return new Response(200, '<h1>Bienvenue</h1>');
    }
}

class AuthController
{
    #[Route(path: '/login', methods: ['GET', 'POST'], name: 'login')]
    public function login(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            // Traiter la connexion
            $_SESSION['user'] = ['id' => 1, 'role' => 'user'];
            return new Response(302, '', ['Location' => '/dashboard']);
        }
        return new Response(200, '<form>...</form>');
    }
}

class DashboardController
{
    #[Route(
        path: '/dashboard',
        methods: ['GET'],
        name: 'dashboard',
        middleware: [AuthMiddleware::class]
    )]
    public function index(): Response
    {
        return new Response(200, '<h1>Dashboard</h1>');
    }
}

class AdminController
{
    #[Route(
        path: '/admin',
        methods: ['GET'],
        name: 'admin',
        middleware: [AuthMiddleware::class, RoleMiddleware::class]
    )]
    public function index(): Response
    {
        return new Response(200, '<h1>Admin</h1>');
    }
}

// Configuration avec groupes
$router = new Router();

// Routes publiques
$router->registerRoutes(HomeController::class);
$router->registerRoutes(AuthController::class);

// Groupe dashboard avec authentification
$router->group('/dashboard', [AuthMiddleware::class], function($router) {
    $router->registerRoutes(DashboardController::class);
});

// Groupe admin avec authentification et rôle
$router->group('/admin', [AuthMiddleware::class, new RoleMiddleware('admin')], function($router) {
    $router->registerRoutes(AdminController::class);
});

// Traitement
session_start();
$request = new Request();
$response = $router->handle($request);
$response->send();
```

## 🧪 Tests

Le package inclut une suite de tests complète. Pour exécuter les tests :

```bash
composer test
# ou
vendor/bin/phpunit tests/
```

## 📝 License

MIT License - Voir le fichier LICENSE pour plus de détails.

## 🤝 Contribution

Les contributions sont les bienvenues ! N'hésitez pas à ouvrir une issue ou une pull request.

## 📧 Support

Pour toute question ou problème, veuillez ouvrir une issue sur GitHub.

---

**Développé avec ❤️ par Julien Linard**
