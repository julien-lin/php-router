# PHP Router

[🇫🇷 Read in French](README.fr.md) | [🇬🇧 Read in English](README.md)

## 💝 Support the project

If this bundle is useful to you, consider [becoming a sponsor](https://github.com/sponsors/julien-lin) to support the development and maintenance of this open source project.

---

A modern and complete PHP router for managing your application routes with support for dynamic routes, middlewares, and all essential features.

## 📋 Table of Contents

- [Installation](#installation)
- [Quick Start](#quick-start)
- [Route Definition](#route-definition)
- [Dynamic Routes](#dynamic-routes)
- [Route Groups](#route-groups)
- [URL Generation](#url-generation)
- [Request](#request)
- [Response](#response)
- [Middlewares](#middlewares)
- [Error Handling](#error-handling)
- [API Reference](#api-reference)
- [Complete Examples](#complete-examples)

## 🚀 Installation

Use Composer to install the package:

```bash
composer require julienlinard/php-router
```

**Requirements**: PHP 8.0 or higher

## ⚡ Quick Start

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use JulienLinard\Router\Router;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;
use JulienLinard\Router\Attributes\Route;

// Create a router instance
$router = new Router();

// Define a controller with routes
class HomeController
{
    #[Route(path: '/', methods: ['GET'], name: 'home')]
    public function index(): Response
    {
        return new Response(200, 'Welcome!');
    }
}

// Register routes
$router->registerRoutes(HomeController::class);

// Handle the request
$request = new Request();
$response = $router->handle($request);

// Send the response
$response->send();
```

## 🛣️ Route Definition

Routes are defined in your controllers using the `Route` attribute (PHP 8).

### Simple Route

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
        return new Response(200, 'Homepage');
    }
}
```

### Routes with Multiple HTTP Methods

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
        // Process data...
        return Response::json(['message' => 'User created'], 201);
    }
}
```

### Route Registration

```php
$router = new Router();
$router->registerRoutes(HomeController::class);
$router->registerRoutes(ApiController::class);
```

### Route Groups

Route groups allow you to organize your routes with a common prefix and shared middlewares.

```php
use JulienLinard\Router\Middlewares\AuthMiddleware;

// Group with prefix only
$router->group('/api', [], function($router) {
    $router->registerRoutes(ApiController::class);
    // All routes will have the /api prefix
});

// Group with prefix and middlewares
$router->group('/admin', [AuthMiddleware::class], function($router) {
    $router->registerRoutes(AdminController::class);
    // All routes will have the /admin prefix AND the AuthMiddleware
});

// Nested groups
$router->group('/api', [], function($router) {
    $router->group('/v1', [], function($router) {
        $router->registerRoutes(ApiV1Controller::class);
        // Routes with /api/v1 prefix
    });
    
    $router->group('/v2', [], function($router) {
        $router->registerRoutes(ApiV2Controller::class);
        // Routes with /api/v2 prefix
    });
});
```

**Complete Example**:
```php
class ApiController
{
    // Path defined in controller: '/users'
    #[Route(path: '/users', methods: ['GET'], name: 'api.users.index')]
    public function index(): Response
    {
        return Response::json(['users' => []]);
    }
}

// Registration with group
$router->group('/api', [], function($router) {
    $router->registerRoutes(ApiController::class);
});

// The route will be accessible at: /api/users
```

## 🔄 Dynamic Routes

The router supports dynamic routes with parameters automatically extracted from the URL.

### Route with One Parameter

```php
class UserController
{
    #[Route(path: '/user/{id}', methods: ['GET'], name: 'user.show')]
    public function show(Request $request): Response
    {
        $userId = $request->getRouteParam('id');
        
        return Response::json([
            'user_id' => $userId,
            'message' => "Displaying user {$userId}"
        ]);
    }
}
```

**Example URL**: `/user/123` → `$userId = '123'`

### Route with Multiple Parameters

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

**Example URL**: `/user/123/post/my-article` → `$userId = '123'`, `$slug = 'my-article'`

### Accessing Parameters

```php
// Get a specific parameter
$id = $request->getRouteParam('id');
$id = $request->getRouteParam('id', 'default'); // with default value

// Get all parameters
$params = $request->getRouteParams(); // ['id' => '123', 'slug' => 'my-article']
```

## 📥 Request

The `Request` class provides complete access to HTTP request data.

### Path and Method

```php
$request = new Request();

$path = $request->getPath();        // '/user/123'
$method = $request->getMethod();    // 'GET', 'POST', etc.
```

### Query Parameters

```php
// URL: /search?q=php&page=2
$query = $request->getQueryParam('q');           // 'php'
$page = $request->getQueryParam('page', 1);      // '2' or 1 as default
$allParams = $request->getQueryParams();         // ['q' => 'php', 'page' => '2']
```

### HTTP Headers

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
// For JSON
$data = $request->getBody();                    // ['name' => 'John', 'email' => '...']
$name = $request->getBodyParam('name');         // 'John'
$rawBody = $request->getRawBody();              // Raw string

// For form-urlencoded
$data = $request->getBody();                    // ['field1' => 'value1', ...]
```

### Utility Methods

```php
if ($request->isAjax()) {
    // AJAX request
}

if ($request->wantsJson()) {
    // Client accepts JSON
}
```

### Customization for Tests

```php
// Create a custom request for tests
$request = new Request('/user/123', 'GET');
```

## 📤 Response

The `Response` class allows you to create and send HTTP responses.

### Simple Response

```php
$response = new Response(200, 'Response content');
$response->send();
```

### JSON Response

```php
$data = ['message' => 'Success', 'data' => []];
$response = Response::json($data, 200);
$response->send();
```

### Custom Headers

```php
$response = new Response(200, 'Content');
$response->setHeader('X-Custom-Header', 'value');
$response->setHeader('Content-Type', 'application/xml');
$response->send();
```

### Available Methods

```php
$statusCode = $response->getStatusCode();    // 200
$content = $response->getContent();          // 'Content'
$headers = $response->getHeaders();         // ['content-type' => 'application/json']
```

## 🔌 Dependency Injection

The Router now supports dependency injection via a Container. This allows automatic injection of dependencies into controllers and middlewares.

### Configuration with Container

```php
use JulienLinard\Router\Router;
use JulienLinard\Core\Container\Container;

$router = new Router();
$container = new Container();

// Pass the Container to the Router
$router->setContainer($container);

// The Router will automatically use the Container to instantiate controllers
```

### Controllers with Dependencies

```php
use JulienLinard\Router\Attributes\Route;
use JulienLinard\Router\Response;
use JulienLinard\Doctrine\EntityManager;
use JulienLinard\Auth\AuthManager;

class UserController
{
    private EntityManager $em;
    private AuthManager $auth;

    // Dependencies are automatically injected via the Container
    public function __construct(EntityManager $em, AuthManager $auth)
    {
        $this->em = $em;
        $this->auth = $auth;
    }

    #[Route(path: '/users', methods: ['GET'], name: 'users.index')]
    public function index(): Response
    {
        $users = $this->em->getRepository(User::class)->findAll();
        return Response::json($users);
    }
}
```

**Note**: If no Container is set, the Router instantiates controllers directly with `new`.

## 🛡️ Middlewares

Middlewares allow you to execute code before request processing.

**Important** : The `Middleware` interface has been improved. The `handle()` method now returns `?Response` instead of `void`. If a middleware returns a `Response`, execution stops and that response is returned. If it returns `null`, execution continues with the next middleware.

### Global Middlewares

```php
use JulienLinard\Router\Middlewares\CorsMiddleware;
use JulienLinard\Router\Middlewares\LoggingMiddleware;

$router = new Router();

// Add a global middleware
$router->addMiddleware(new CorsMiddleware());
$router->addMiddleware(new LoggingMiddleware());
```

### Route-Specific Middlewares

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
        return new Response(200, 'Admin dashboard');
    }
}
```

### Available Middlewares

#### CorsMiddleware

```php
use JulienLinard\Router\Middlewares\CorsMiddleware;

// Default configuration (all origins)
$cors = new CorsMiddleware();

// Custom configuration
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
        // User is authenticated
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
        // User is authenticated AND has admin role
        return Response::json(['users' => []]);
    }
}

// In your bootstrap
$router->addMiddleware(new RoleMiddleware('admin'));
```

#### LoggingMiddleware

```php
use JulienLinard\Router\Middlewares\LoggingMiddleware;

$router->addMiddleware(new LoggingMiddleware());
// Log all requests to error_log
```

### Create a Custom Middleware

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
        // Your logic here
        // For example, check a condition
        
        if (/* condition not met */) {
            Response::json(['error' => 'Access denied'], 403)->send();
            exit;
        }
        
        // Otherwise, continue execution
    }
}
```

## ⚠️ Error Handling

The router automatically handles common errors:

- **404 Not Found**: Route not found
- **405 Method Not Allowed**: HTTP method not supported for this route
- **500 Internal Server Error**: Server error (exceptions)

### Customize Error Handling

```php
use JulienLinard\Router\ErrorHandler;

// Errors are automatically handled by the router
// You can use ErrorHandler directly if needed

$response = ErrorHandler::handleNotFound();      // 404
$response = ErrorHandler::handleServerError($e); // 500
```

## 📚 API Reference

### Router

#### `registerRoutes(string $controller): void`

Registers all routes of a controller.

```php
$router->registerRoutes(HomeController::class);
```

#### `addMiddleware(Middleware $middleware): void`

Adds a global middleware.

```php
$router->addMiddleware(new CorsMiddleware());
```

#### `handle(Request $request): Response`

Processes a request and returns the response.

```php
$response = $router->handle($request);
```

#### `getRoutes(): array`

Returns all registered routes (debug).

```php
$routes = $router->getRoutes();
// ['static' => [...], 'dynamic' => [...]]
```

#### `getRouteByName(string $name): ?array`

Returns a route by its name.

```php
$route = $router->getRouteByName('home');
// ['path' => '/', 'method' => 'GET', 'route' => [...]]
```

#### `url(string $name, array $params = [], array $queryParams = []): ?string`

Generates a URL from a route name and its parameters.

```php
// Static route
$url = $router->url('home');
// Returns: '/'

// Dynamic route with one parameter
$url = $router->url('user.show', ['id' => '123']);
// Returns: '/user/123'

// Dynamic route with multiple parameters
$url = $router->url('post.show', ['userId' => '123', 'slug' => 'my-article']);
// Returns: '/user/123/post/my-article'

// With query parameters
$url = $router->url('user.show', ['id' => '123'], ['page' => '2', 'sort' => 'name']);
// Returns: '/user/123?page=2&sort=name'

// Returns null if route doesn't exist
$url = $router->url('non-existent');
// Returns: null

// Throws exception if required parameter is missing
try {
    $url = $router->url('user.show', []); // Missing 'id' parameter
} catch (\InvalidArgumentException $e) {
    // "The parameter 'id' is required for route 'user.show'."
}
```

#### `group(string $prefix, array $middlewares, callable $callback): void`

Creates a route group with a prefix and common middlewares.

```php
$router->group('/api', [AuthMiddleware::class], function($router) {
    $router->registerRoutes(ApiController::class);
});
```

### Request

#### Main Methods

- `getPath(): string` - Request path
- `getMethod(): string` - HTTP method
- `getQueryParams(): array` - All query parameters
- `getQueryParam(string $key, $default = null)` - A query parameter
- `getHeaders(): array` - All headers
- `getHeader(string $name, ?string $default = null): ?string` - A header
- `getCookies(): array` - All cookies
- `getCookie(string $name, $default = null)` - A cookie
- `getBody(): ?array` - Parsed body
- `getBodyParam(string $key, $default = null)` - A body parameter
- `getRawBody(): string` - Raw body
- `getRouteParams(): array` - All route parameters
- `getRouteParam(string $key, $default = null)` - A route parameter
- `isAjax(): bool` - Checks if it's an AJAX request
- `wantsJson(): bool` - Checks if client accepts JSON

### Response

#### Constructor

```php
new Response(int $statusCode = 200, string $content = '')
```

#### Static Methods

- `Response::json($data, int $statusCode = 200): self` - Creates a JSON response

#### Instance Methods

- `setHeader(string $name, string $value): void` - Sets a header
- `send(): void` - Sends the HTTP response
- `getStatusCode(): int` - Status code
- `getContent(): string` - Content
- `getHeaders(): array` - All headers

## 🔗 Integration with Other Packages

### Integration with core-php

`core-php` automatically includes `php-router`. The router is accessible via `Application::getRouter()`.

```php
<?php

use JulienLinard\Core\Application;
use JulienLinard\Router\Attributes\Route;
use JulienLinard\Router\Response;

$app = Application::create(__DIR__);
$router = $app->getRouter();

class HomeController
{
    #[Route(path: '/', methods: ['GET'], name: 'home')]
    public function index(): Response
    {
        return new Response(200, '<h1>Home</h1>');
    }
}

$router->registerRoutes(HomeController::class);
$app->start();
```

### Integration with auth-php

Use authentication middlewares with `php-router`.

```php
<?php

use JulienLinard\Router\Router;
use JulienLinard\Router\Attributes\Route;
use JulienLinard\Router\Response;
use JulienLinard\Auth\AuthManager;
use JulienLinard\Auth\Middleware\AuthMiddleware;
use JulienLinard\Auth\Middleware\RoleMiddleware;

$router = new Router();
$auth = new AuthManager($authConfig);

class DashboardController
{
    #[Route(
        path: '/dashboard',
        methods: ['GET'],
        name: 'dashboard',
        middleware: [new AuthMiddleware($auth)]
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
        middleware: [
            new AuthMiddleware($auth),
            new RoleMiddleware('admin', $auth)
        ]
    )]
    public function index(): Response
    {
        return new Response(200, '<h1>Admin</h1>');
    }
}

$router->registerRoutes(DashboardController::class);
$router->registerRoutes(AdminController::class);
```

### Standalone Usage

`php-router` can be used independently of all other packages.

```php
<?php

require_once __DIR__ . '/vendor/autoload.php';

use JulienLinard\Router\Router;
use JulienLinard\Router\Attributes\Route;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

$router = new Router();

class HomeController
{
    #[Route(path: '/', methods: ['GET'], name: 'home')]
    public function index(): Response
    {
        return new Response(200, 'Hello World');
    }
}

$router->registerRoutes(HomeController::class);

$request = new Request();
$response = $router->handle($request);
$response->send();
```

## 🔗 URL Generation

The router allows you to generate URLs from route names, which facilitates maintenance and avoids hardcoded URLs.

### Simple URL Generation

```php
// In your views or controllers
$homeUrl = $router->url('home');
// Returns: '/'

$userUrl = $router->url('user.show', ['id' => '123']);
// Returns: '/user/123'
```

### URL Generation with Query Parameters

```php
$url = $router->url('user.show', ['id' => '123'], ['page' => '2', 'sort' => 'name']);
// Returns: '/user/123?page=2&sort=name'
```

### Usage in Responses

```php
class UserController
{
    #[Route(path: '/user/{id}', methods: ['GET'], name: 'user.show')]
    public function show(Request $request, Router $router): Response
    {
        $id = $request->getRouteParam('id');
        
        // Generate next user URL
        $nextUserId = (int)$id + 1;
        $nextUrl = $router->url('user.show', ['id' => $nextUserId]);
        
        return Response::json([
            'user_id' => $id,
            'next_user_url' => $nextUrl
        ]);
    }
}
```

**Note**: To use `$router` in your controllers, you can inject it via a dependency container or pass it as a parameter.

## 💡 Complete Examples

### Example 1: Complete REST API with Groups

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
    // Paths are defined without the /api prefix (added by group)
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
        // Create user...
        return Response::json(['message' => 'User created'], 201);
    }

    #[Route(path: '/users/{id}', methods: ['PUT'], name: 'users.update')]
    public function update(Request $request): Response
    {
        $id = $request->getRouteParam('id');
        $data = $request->getBody();
        // Update user...
        return Response::json(['message' => 'User updated']);
    }

    #[Route(path: '/users/{id}', methods: ['DELETE'], name: 'users.delete')]
    public function delete(Request $request): Response
    {
        $id = $request->getRouteParam('id');
        // Delete user...
        return Response::json(['message' => 'User deleted'], 204);
    }
}

// Configuration with groups
$router = new Router();
$router->addMiddleware(new CorsMiddleware());

// API group with /api prefix
$router->group('/api', [], function($router) {
    $router->registerRoutes(UserController::class);
});

// Processing
$request = new Request();
$response = $router->handle($request);
$response->send();

// URL generation
$usersUrl = $router->url('users.index');           // '/api/users'
$userUrl = $router->url('users.show', ['id' => 5]); // '/api/users/5'
```

### Example 2: Web Application with Authentication and Groups

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
        return new Response(200, '<h1>Welcome</h1>');
    }
}

class AuthController
{
    #[Route(path: '/login', methods: ['GET', 'POST'], name: 'login')]
    public function login(Request $request): Response
    {
        if ($request->getMethod() === 'POST') {
            // Process login
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

// Configuration with groups
$router = new Router();

// Public routes
$router->registerRoutes(HomeController::class);
$router->registerRoutes(AuthController::class);

// Dashboard group with authentication
$router->group('/dashboard', [AuthMiddleware::class], function($router) {
    $router->registerRoutes(DashboardController::class);
});

// Admin group with authentication and role
$router->group('/admin', [AuthMiddleware::class, new RoleMiddleware('admin')], function($router) {
    $router->registerRoutes(AdminController::class);
});

// Processing
session_start();
$request = new Request();
$response = $router->handle($request);
$response->send();
```

## 🧪 Tests

The package includes a complete test suite. To run the tests:

```bash
composer test
# or
vendor/bin/phpunit tests/
```

## 📝 License

MIT License - See the LICENSE file for more details.

## 🤝 Contributing

Contributions are welcome! Feel free to open an issue or a pull request.

## 📧 Support

For any questions or issues, please open an issue on GitHub.

## 💝 Support the project

If this bundle is useful to you, consider [becoming a sponsor](https://github.com/sponsors/julien-lin) to support the development and maintenance of this open source project.

---

**Developed with ❤️ by Julien Linard**
