# Middlewares

## Vue d'ensemble

Les middlewares permettent d'intercepter les requêtes avant qu'elles n'atteignent le contrôleur. Ils peuvent :
- Modifier la requête
- Arrêter l'exécution et retourner une réponse (ex: redirection, erreur)
- Exécuter du code avant/après le contrôleur

## Interface Middleware

Tous les middlewares doivent implémenter l'interface `Middleware` :

```php
use JulienLinard\Router\Middleware;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

class MyMiddleware implements Middleware
{
    public function handle(Request $request): ?Response
    {
        // Code du middleware
        
        // Retourner null pour continuer
        return null;
        
        // Ou retourner une Response pour arrêter
        return new Response(403, 'Forbidden');
    }
}
```

**Comportement** :
- `null` : L'exécution continue vers le middleware suivant ou le contrôleur
- `Response` : L'exécution s'arrête, la réponse est retournée immédiatement

## Types de middlewares

### 1. Middlewares globaux

Appliqués à toutes les routes, dans l'ordre d'ajout.

```php
$router = new Router();
$router->addMiddleware(new LoggingMiddleware());
$router->addMiddleware(new CorsMiddleware());
```

**Ordre d'exécution** :
1. Middlewares globaux (dans l'ordre d'ajout)
2. Middlewares de groupe (dans l'ordre du groupe)
3. Middlewares de route (dans l'ordre défini)
4. Contrôleur

### 2. Middlewares de groupe

Appliqués à toutes les routes d'un groupe.

```php
$router->group('/admin', [AuthMiddleware::class], function($router) {
    $router->registerRoutes(AdminController::class);
});
```

### 3. Middlewares de route

Appliqués uniquement à une route spécifique.

```php
#[Route(
    path: '/admin/users',
    methods: ['GET'],
    middleware: [AuthMiddleware::class, AdminMiddleware::class]
)]
public function index(): Response
{
    // ...
}
```

## Middlewares intégrés

### AuthMiddleware

Vérifie que l'utilisateur est authentifié.

```php
use JulienLinard\Router\Middlewares\AuthMiddleware;

$router->addMiddleware(new AuthMiddleware($auth, '/login'));
```

**Paramètres** :
- `$auth` : Instance de `AuthManager` (ou compatible)
- `$redirectTo` : URL de redirection si non authentifié

### RoleMiddleware

Vérifie que l'utilisateur a un rôle spécifique.

```php
use JulienLinard\Router\Middlewares\RoleMiddleware;

#[Route(
    path: '/admin',
    methods: ['GET'],
    middleware: [RoleMiddleware::class]
)]
```

**Configuration** :
- Le middleware doit être configuré avec les rôles requis

### PermissionMiddleware

Vérifie que l'utilisateur a une permission spécifique.

```php
use JulienLinard\Router\Middlewares\PermissionMiddleware;
```

### CorsMiddleware

Gère les en-têtes CORS (Cross-Origin Resource Sharing).

```php
use JulienLinard\Router\Middlewares\CorsMiddleware;

$router->addMiddleware(new CorsMiddleware([
    'allowedOrigins' => ['https://example.com'],
    'allowedMethods' => ['GET', 'POST'],
    'allowedHeaders' => ['Content-Type', 'Authorization'],
    'allowCredentials' => true,
]));
```

**Configuration** :
- `allowedOrigins` : Liste des origines autorisées (ou `['*']` pour toutes)
- `allowedMethods` : Méthodes HTTP autorisées
- `allowedHeaders` : Headers autorisés
- `allowCredentials` : Autoriser les credentials (cookies, etc.)

**Gestion automatique** :
- Répond automatiquement aux requêtes OPTIONS (preflight)
- Ajoute les headers CORS appropriés

### LoggingMiddleware

Enregistre les requêtes dans un log.

```php
use JulienLinard\Router\Middlewares\LoggingMiddleware;

$router->addMiddleware(new LoggingMiddleware($logger));
```

## Création d'un middleware personnalisé

### Exemple : Rate Limiting

```php
use JulienLinard\Router\Middleware;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

class RateLimitMiddleware implements Middleware
{
    private array $requests = [];
    private int $maxRequests;
    private int $windowSeconds;
    
    public function __construct(int $maxRequests = 100, int $windowSeconds = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->windowSeconds = $windowSeconds;
    }
    
    public function handle(Request $request): ?Response
    {
        $ip = $request->getHeader('X-Forwarded-For') 
            ?? $_SERVER['REMOTE_ADDR'] 
            ?? 'unknown';
        
        $now = time();
        $windowStart = $now - $this->windowSeconds;
        
        // Nettoyer les anciennes requêtes
        if (isset($this->requests[$ip])) {
            $this->requests[$ip] = array_filter(
                $this->requests[$ip],
                fn($timestamp) => $timestamp > $windowStart
            );
        }
        
        // Compter les requêtes dans la fenêtre
        $count = count($this->requests[$ip] ?? []);
        
        if ($count >= $this->maxRequests) {
            return new Response(429, 'Too Many Requests');
        }
        
        // Enregistrer cette requête
        $this->requests[$ip][] = $now;
        
        return null; // Continuer
    }
}
```

### Exemple : Maintenance Mode

```php
class MaintenanceMiddleware implements Middleware
{
    private bool $enabled;
    
    public function __construct(bool $enabled = false)
    {
        $this->enabled = $enabled;
    }
    
    public function handle(Request $request): ?Response
    {
        if ($this->enabled) {
            return new Response(503, 'Service en maintenance');
        }
        
        return null;
    }
}
```

### Exemple : CSRF Protection

```php
class CsrfMiddleware implements Middleware
{
    private Session $session;
    
    public function __construct(Session $session)
    {
        $this->session = $session;
    }
    
    public function handle(Request $request): ?Response
    {
        // Ignorer GET, HEAD, OPTIONS
        if (in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'])) {
            return null;
        }
        
        $token = $request->getHeader('X-CSRF-Token')
            ?? $request->getBody()['csrf_token'] 
            ?? null;
        
        $sessionToken = $this->session->get('csrf_token');
        
        if (!$token || !hash_equals($sessionToken, $token)) {
            return new Response(403, 'Invalid CSRF token');
        }
        
        return null;
    }
}
```

## Injection de dépendances

Les middlewares peuvent être instanciés via le Container DI si disponible :

```php
// Si le middleware a des dépendances
class MyMiddleware implements Middleware
{
    public function __construct(LoggerInterface $logger, Config $config)
    {
        // ...
    }
}

// Le routeur utilisera le container pour l'instancier
$router->addMiddleware(MyMiddleware::class);
```

**Ordre de résolution** :
1. Si c'est une instance, l'utiliser directement
2. Si le container est disponible, utiliser `$container->make()`
3. Sinon, instancier avec `new`

## Bonnes pratiques

### 1. Middlewares légers

Les middlewares sont exécutés à chaque requête. Évitez les opérations coûteuses.

```php
// ❌ Mauvais : Requête DB à chaque requête
public function handle(Request $request): ?Response
{
    $user = DB::query('SELECT * FROM users WHERE id = ?', [1]);
    // ...
}

// ✅ Bon : Utiliser un cache
public function handle(Request $request): ?Response
{
    $user = $this->cache->get('user:1');
    // ...
}
```

### 2. Retourner tôt

Si le middleware doit arrêter l'exécution, retournez immédiatement.

```php
public function handle(Request $request): ?Response
{
    if (!$this->isAuthorized($request)) {
        return new Response(403, 'Forbidden');
    }
    
    // Code suivant seulement si autorisé
    return null;
}
```

### 3. Ne pas modifier la requête directement

Préférez créer une nouvelle requête ou utiliser des méthodes dédiées.

```php
// ❌ Mauvais
$request->path = '/modified';

// ✅ Bon : Utiliser des méthodes dédiées si disponibles
// Ou créer un wrapper
```

### 4. Gérer les erreurs

Les exceptions dans les middlewares doivent être gérées.

```php
public function handle(Request $request): ?Response
{
    try {
        // Code du middleware
    } catch (\Exception $e) {
        // Logger l'erreur
        $this->logger->error($e->getMessage());
        
        // Retourner une erreur ou continuer selon le cas
        return new Response(500, 'Internal Server Error');
    }
}
```

## Ordre d'exécution complet

```
1. Middlewares globaux (ordre d'ajout)
   └─ Si Response retournée → Arrêt
   
2. Matching de route
   └─ Si non trouvée → 404
   └─ Si méthode non autorisée → 405
   
3. Middlewares de groupe (ordre du groupe)
   └─ Si Response retournée → Arrêt
   
4. Middlewares de route (ordre défini)
   └─ Si Response retournée → Arrêt
   
5. Contrôleur
   └─ Retourne Response
```

## Tests

Les middlewares peuvent être testés indépendamment :

```php
class MyMiddlewareTest extends TestCase
{
    public function testMiddlewareAllowsRequest(): void
    {
        $middleware = new MyMiddleware();
        $request = new Request('/test', 'GET');
        
        $response = $middleware->handle($request);
        
        $this->assertNull($response);
    }
    
    public function testMiddlewareBlocksRequest(): void
    {
        $middleware = new MyMiddleware();
        $request = new Request('/test', 'GET');
        
        $response = $middleware->handle($request);
        
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals(403, $response->getStatusCode());
    }
}
```

