# Architecture du Routeur

## Vue d'ensemble

Le routeur `php-router` est un système de routage HTTP moderne pour PHP 8+ qui utilise les attributs PHP 8 pour définir les routes. Il supporte les routes statiques et dynamiques, les middlewares, les groupes de routes, et la génération d'URL.

## Composants principaux

### 1. Router (Classe principale)

La classe `Router` est le composant central qui gère :
- L'enregistrement des routes
- La correspondance des requêtes avec les routes
- L'exécution des middlewares
- L'invocation des contrôleurs
- La génération d'URL

**Structure interne** :
```php
class Router
{
    // Routes statiques : accès O(1) par path
    private array $routes = [];
    
    // Routes dynamiques : triées par spécificité
    private array $dynamicRoutes = [];
    
    // Middlewares globaux
    private array $middlewares = [];
    
    // Cache de ReflectionClass
    private array $reflectionCache = [];
    
    // Container DI (optionnel)
    private ?object $container = null;
    
    // Index inversé pour recherche rapide par nom
    private array $routeNameIndex = [];
    
    // Cache de compilation des routes dynamiques
    private array $compilationCache = [];
}
```

### 2. Request

La classe `Request` encapsule une requête HTTP :
- Path et méthode HTTP
- Query parameters
- Headers
- Cookies
- Body (pour POST/PUT/PATCH)
- Paramètres de route (après matching)

**Fonctionnalités** :
- Normalisation automatique du path (suppression trailing slashes)
- Protection DoS (limite de taille du body : 10MB par défaut)
- Sanitization des headers (protection CRLF injection)
- Validation du Content-Type

### 3. Response

La classe `Response` représente une réponse HTTP :
- Code de statut
- Contenu
- Headers
- Cookies

### 4. Route Attribute

L'attribut `#[Route]` permet de définir des routes directement sur les méthodes de contrôleur :

```php
#[Route(path: '/user/{id}', methods: ['GET'], name: 'user.show')]
public function show(Request $request): Response
{
    // ...
}
```

**Paramètres** :
- `path` : Le chemin de la route (peut contenir des paramètres `{name}`)
- `name` : Nom unique de la route (pour génération d'URL)
- `methods` : Méthodes HTTP acceptées (par défaut : `['GET']`)
- `middleware` : Liste des middlewares spécifiques à la route
- `constraints` : Contraintes de validation pour les paramètres (regex)

### 5. Middleware Interface

Interface simple pour les middlewares :

```php
interface Middleware
{
    public function handle(Request $request): ?Response;
}
```

Un middleware peut :
- Retourner `null` pour continuer l'exécution
- Retourner une `Response` pour arrêter l'exécution (ex: redirection, erreur)

## Flux d'exécution

### 1. Enregistrement des routes

```php
$router = new Router();
$router->registerRoutes(UserController::class);
```

**Processus** :
1. Analyse des attributs `#[Route]` sur les méthodes du contrôleur
2. Compilation des routes dynamiques en patterns regex
3. Séparation des routes statiques et dynamiques
4. Indexation par nom de route pour accès O(1)
5. Mise en cache de la compilation

### 2. Traitement d'une requête

```php
$request = new Request();
$response = $router->handle($request);
```

**Processus détaillé** :

1. **Middlewares globaux** : Exécution dans l'ordre d'ajout
   - Si un middleware retourne une `Response`, arrêt immédiat

2. **Matching de route** :
   - **Routes statiques** : Recherche directe O(1) dans `$routes[$path]`
   - **Routes dynamiques** : Parcours des routes triées par spécificité
     - Tri par nombre de paramètres (décroissant)
     - Puis par longueur du path (décroissante)
   - Extraction des paramètres de route

3. **Vérification méthode HTTP** :
   - Si la méthode n'est pas supportée → `405 Method Not Allowed`

4. **Route non trouvée** :
   - Si aucune route ne correspond → `404 Not Found`

5. **Middlewares de route** : Exécution dans l'ordre défini
   - Si un middleware retourne une `Response`, arrêt immédiat

6. **Invocation du contrôleur** :
   - Résolution des dépendances via Container DI (si disponible)
   - Injection automatique de `Request` dans les paramètres
   - Injection des paramètres de route
   - Appel de la méthode du contrôleur

7. **Retour de la réponse** :
   - Si le contrôleur retourne une `Response`, elle est retournée
   - Sinon, une `Response(200)` vide est créée

## Optimisations

### 1. Routes statiques vs dynamiques

Les routes statiques sont stockées dans un tableau associatif pour un accès O(1) :
```php
$routes['/users'] = ['GET' => [...], 'POST' => [...]]
```

Les routes dynamiques sont triées par spécificité pour minimiser les tests :
- Routes avec plus de paramètres testées en premier
- Routes plus longues testées en premier

### 2. Cache de compilation

Les routes dynamiques sont compilées en patterns regex et mis en cache :
```php
'/user/{id}' → '#^/user/([^/]+)$#'
```

Le cache évite de recompiler les routes à chaque requête.

### 3. Cache de Reflection

Les instances `ReflectionClass` sont mises en cache pour éviter de recréer les objets de réflexion à chaque invocation de contrôleur.

### 4. Index inversé pour génération d'URL

Un index `routeNameIndex` permet de retrouver une route par son nom en O(1) :
```php
$routeNameIndex['user.show'] = ['path' => '/user/{id}', ...]
```

## Groupes de routes

Les groupes permettent d'appliquer un préfixe et des middlewares à plusieurs routes :

```php
$router->group('/api', [AuthMiddleware::class], function($router) {
    $router->registerRoutes(UserController::class);
});
```

**Fonctionnement** :
- Le préfixe est ajouté à toutes les routes du groupe
- Les middlewares sont fusionnés avec ceux des routes individuelles
- Les groupes peuvent être imbriqués

## Injection de dépendances

Le routeur supporte un Container DI optionnel :

```php
$router->setContainer($container);
```

**Utilisation** :
- Instanciation des contrôleurs via `$container->make()`
- Instanciation des middlewares via `$container->make()`
- Si pas de container, instanciation directe avec `new`

## Gestion des erreurs

Le routeur utilise `ErrorHandler` pour gérer les erreurs :
- `404 Not Found` : Route non trouvée
- `405 Method Not Allowed` : Méthode HTTP non supportée
- `500 Internal Server Error` : Exception non gérée

## Sécurité

### Protection CRLF injection
- Sanitization automatique des headers
- Normalisation des noms de headers

### Protection DoS
- Limite de taille du body (10MB par défaut)
- Validation du Content-Type

### Validation des routes
- Rejet des doubles slashes
- Rejet des trailing slashes (sauf racine)
- Validation des noms de paramètres dynamiques

