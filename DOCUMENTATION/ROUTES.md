# Définition de Routes

## Utilisation des Attributs PHP 8

Le routeur utilise les attributs PHP 8 pour définir les routes directement sur les méthodes de contrôleur.

## Syntaxe de base

```php
use JulienLinard\Router\Attributes\Route;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

class UserController
{
    #[Route(path: '/users', methods: ['GET'], name: 'users.index')]
    public function index(): Response
    {
        return new Response(200, 'Liste des utilisateurs');
    }
}
```

## Paramètres de l'attribut Route

### path (requis)

Le chemin de la route. Peut être statique ou dynamique.

```php
// Route statique
#[Route(path: '/users')]

// Route dynamique avec un paramètre
#[Route(path: '/user/{id}')]

// Route dynamique avec plusieurs paramètres
#[Route(path: '/user/{userId}/post/{slug}')]
```

**Règles** :
- Pas de trailing slash (sauf pour la racine `/`)
- Pas de doubles slashes
- Les paramètres doivent respecter `[a-zA-Z_][a-zA-Z0-9_]*`

### name (optionnel)

Nom unique de la route, utilisé pour la génération d'URL.

```php
#[Route(path: '/user/{id}', name: 'user.show')]
```

**Important** : Chaque route doit avoir un nom unique. Une exception est levée en cas de duplication.

### methods (optionnel, défaut: ['GET'])

Méthodes HTTP acceptées pour cette route.

```php
// Une seule méthode
#[Route(path: '/users', methods: ['GET'])]

// Plusieurs méthodes
#[Route(path: '/users', methods: ['GET', 'POST'])]

// Toutes les méthodes courantes
#[Route(path: '/users', methods: ['GET', 'POST', 'PUT', 'DELETE'])]
```

### middleware (optionnel, défaut: [])

Liste des middlewares spécifiques à cette route.

```php
#[Route(
    path: '/admin/users',
    methods: ['GET'],
    middleware: [AuthMiddleware::class, AdminMiddleware::class]
)]
```

Les middlewares sont exécutés dans l'ordre défini, après les middlewares globaux.

### constraints (optionnel, défaut: [])

Contraintes de validation pour les paramètres de route (expressions régulières).

```php
#[Route(
    path: '/user/{id}',
    methods: ['GET'],
    constraints: ['id' => '\d+']  // id doit être numérique
)]
```

**Exemples** :
```php
// ID numérique uniquement
constraints: ['id' => '\d+']

// Slug (lettres, chiffres, tirets)
constraints: ['slug' => '[a-z0-9-]+']

// UUID
constraints: ['uuid' => '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}']
```

## Routes statiques

Les routes sans paramètres dynamiques sont stockées dans un tableau associatif pour un accès O(1).

```php
#[Route(path: '/about', methods: ['GET'], name: 'about')]
public function about(): Response
{
    return new Response(200, 'À propos');
}
```

**Avantages** :
- Matching instantané
- Pas de compilation regex nécessaire

## Routes dynamiques

Les routes avec paramètres sont compilées en patterns regex et triées par spécificité.

```php
#[Route(path: '/user/{id}', methods: ['GET'], name: 'user.show')]
public function show(Request $request): Response
{
    $id = $request->getRouteParam('id');
    return new Response(200, "Utilisateur {$id}");
}
```

### Accès aux paramètres

Les paramètres de route sont accessibles via `Request::getRouteParam()` :

```php
$id = $request->getRouteParam('id');
$allParams = $request->getRouteParams(); // ['id' => '123', ...]
```

### Ordre de matching

Les routes dynamiques sont testées dans l'ordre de spécificité :
1. Nombre de paramètres (décroissant)
2. Longueur du path (décroissante)

**Exemple** :
```php
// Route 1 : Plus spécifique (2 paramètres)
#[Route(path: '/user/{userId}/post/{slug}')]

// Route 2 : Moins spécifique (1 paramètre)
#[Route(path: '/user/{id}')]
```

La route 1 sera testée en premier car elle a plus de paramètres.

## Injection de Request

Le routeur injecte automatiquement l'objet `Request` si le paramètre est typé :

```php
#[Route(path: '/user/{id}', methods: ['GET'])]
public function show(Request $request): Response
{
    // $request est automatiquement injecté
    $id = $request->getRouteParam('id');
    return new Response(200, "User {$id}");
}
```

**Support des union types** :
```php
public function show(Request|string $request): Response
{
    // Le routeur détecte Request dans l'union type
}
```

## Injection des paramètres de route

Les paramètres de route peuvent être injectés directement dans les paramètres de la méthode :

```php
#[Route(path: '/user/{id}/post/{slug}', methods: ['GET'])]
public function show(int $id, string $slug): Response
{
    // $id et $slug sont automatiquement injectés
    return new Response(200, "User {$id}, Post {$slug}");
}
```

**Types supportés** :
- `int` : Conversion automatique
- `string` : Valeur telle quelle
- `float` : Conversion automatique
- Union types : `int|string`

## Groupes de routes

Les groupes permettent d'appliquer un préfixe et des middlewares à plusieurs routes.

### Syntaxe de base

```php
$router->group('/api', [], function($router) {
    $router->registerRoutes(UserController::class);
});
```

### Avec middlewares

```php
$router->group('/admin', [AuthMiddleware::class], function($router) {
    $router->registerRoutes(AdminController::class);
});
```

### Groupes imbriqués

```php
$router->group('/api', [], function($router) {
    $router->group('/v1', [], function($router) {
        $router->registerRoutes(ApiController::class);
    });
});
```

**Résultat** : Les routes auront le préfixe `/api/v1/...`

### Préfixe vide

```php
$router->group('', [GlobalMiddleware::class], function($router) {
    // Applique uniquement les middlewares, pas de préfixe
    $router->registerRoutes(Controller::class);
});
```

## Génération d'URL

### Méthode url()

```php
$url = $router->url('user.show', ['id' => '123']);
// Retourne : '/user/123'
```

### Avec query parameters

```php
$url = $router->url('user.show', ['id' => '123'], ['page' => '2', 'sort' => 'name']);
// Retourne : '/user/123?page=2&sort=name'
```

### Routes dynamiques

```php
#[Route(path: '/user/{userId}/post/{slug}', name: 'post.show')]

$url = $router->url('post.show', ['userId' => '456', 'slug' => 'mon-article']);
// Retourne : '/user/456/post/mon-article'
```

### Encodage automatique

Les valeurs sont automatiquement encodées pour l'URL :

```php
$url = $router->url('user.show', ['id' => 'test with spaces']);
// Retourne : '/user/test%20with%20spaces'
```

### Gestion des erreurs

```php
// Route inexistante
$url = $router->url('non.existent');
// Retourne : null

// Paramètre manquant
$router->url('user.show', []); 
// Lève : InvalidArgumentException
```

## Exemples complets

### CRUD complet

```php
class UserController
{
    #[Route(path: '/users', methods: ['GET'], name: 'users.index')]
    public function index(): Response
    {
        return new Response(200, 'Liste');
    }
    
    #[Route(path: '/users', methods: ['POST'], name: 'users.store')]
    public function store(Request $request): Response
    {
        return new Response(201, 'Créé');
    }
    
    #[Route(path: '/users/{id}', methods: ['GET'], name: 'users.show')]
    public function show(int $id): Response
    {
        return new Response(200, "User {$id}");
    }
    
    #[Route(path: '/users/{id}', methods: ['PUT'], name: 'users.update')]
    public function update(int $id, Request $request): Response
    {
        return new Response(200, "Mis à jour {$id}");
    }
    
    #[Route(path: '/users/{id}', methods: ['DELETE'], name: 'users.destroy')]
    public function destroy(int $id): Response
    {
        return new Response(204);
    }
}
```

### Routes avec contraintes

```php
#[Route(
    path: '/blog/{year}/{month}/{slug}',
    methods: ['GET'],
    name: 'blog.post',
    constraints: [
        'year' => '\d{4}',
        'month' => '(0[1-9]|1[0-2])',
        'slug' => '[a-z0-9-]+'
    ]
)]
public function show(int $year, int $month, string $slug): Response
{
    return new Response(200, "{$year}/{$month}/{$slug}");
}
```

