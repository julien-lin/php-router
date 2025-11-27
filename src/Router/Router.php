<?php

namespace JulienLinard\Router;

use ReflectionClass;
use JulienLinard\Router\Attributes\Route as RouteAttribute;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;
use JulienLinard\Router\ErrorHandler;
use JulienLinard\Router\Middleware;

class Router
{
  /**
   * Structure des routes statiques : ['path' => ['methods' => ['GET' => ['controller' => ..., 'method' => ..., 'middlewares' => [...], 'name' => ...]]]]
   */
  private array $routes = [];
  
  /**
   * Structure des routes dynamiques : [['pattern' => regex, 'params' => ['id', 'slug'], 'path' => '/user/{id}', 'methods' => ['GET' => [...]]]]
   */
  private array $dynamicRoutes = [];
  
  /**
   * Middlewares globaux appliqués à toutes les routes
   */
  private array $middlewares = [];

  /**
   * Cache de ReflectionClass pour améliorer les performances
   */
  private array $reflectionCache = [];
  
  /**
   * Container d'injection de dépendances (optionnel)
   */
  private $container = null;

  /**
   * Index inversé pour la recherche rapide de routes par nom
   * Structure : ['routeName' => ['path' => '/path', 'method' => 'GET', 'isDynamic' => false, 'dynamicRouteIndex' => 0]]
   * Pour routes dynamiques : 'dynamicRouteIndex' est l'index dans dynamicRoutes pour accès O(1)
   */
  private array $routeNameIndex = [];
  
  /**
   * Index des routes dynamiques par path pour accès O(1)
   * Structure : ['/user/{id}' => index dans dynamicRoutes]
   */
  private array $dynamicRoutesByPath = [];
  
  /**
   * Cache de compilation des routes dynamiques par pattern
   * Structure : ['/user/{id}' => ['pattern' => regex, 'params' => ['id']]]
   */
  private array $compilationCache = [];

  /**
   * Définit le container d'injection de dépendances
   * 
   * @param object $container Container DI (doit avoir une méthode make())
   */
  public function setContainer($container): void
  {
    $this->container = $container;
  }

  /**
   * Ajoute un middleware global au routeur
   */
  public function addMiddleware(Middleware $middleware): void
  {
    $this->middlewares[] = $middleware;
  }

  /**
   * Enregistre les routes d'un contrôleur en analysant ses attributs Route
   */
  public function registerRoutes(string $controller): void
  {
    if (!class_exists($controller)) {
      throw new \InvalidArgumentException("Le contrôleur {$controller} n'existe pas.");
    }

    $reflection = $this->getReflection($controller);
    
    foreach ($reflection->getMethods() as $method) {
      $attributes = $method->getAttributes(RouteAttribute::class);
      
      foreach ($attributes as $attribute) {
        $routeAttribute = $attribute->newInstance();
        
        // Appliquer le préfixe du groupe si présent
        $path = $routeAttribute->path;
        if ($this->currentGroupPrefix !== null && !empty($this->currentGroupPrefix)) {
          $path = $this->currentGroupPrefix . ($path === '/' ? '' : $path);
        }
        
        // Valider le format du path
        $this->validatePath($path);
        
        // Vérifier l'unicité du nom de route
        if (!empty($routeAttribute->name)) {
          if (isset($this->routeNameIndex[$routeAttribute->name])) {
            throw new \RuntimeException(
              "Le nom de route '{$routeAttribute->name}' est déjà utilisé. " .
              "Chaque route doit avoir un nom unique."
            );
          }
        }
        
        // Fusionner les middlewares du groupe avec ceux de la route
        $middlewares = array_merge($this->currentGroupMiddlewares, $routeAttribute->middleware);
        
        // Vérifier si la route contient des paramètres dynamiques
        if ($this->hasDynamicParams($path)) {
          // Route dynamique
          $compiled = $this->compileDynamicRoute($path, $routeAttribute->constraints);
          
          // Enregistrer chaque méthode HTTP pour ce path
          foreach ($routeAttribute->methods as $httpMethod) {
            $httpMethod = strtoupper($httpMethod);
            
            // Vérifier les collisions
            foreach ($this->dynamicRoutes as $dynamicRoute) {
              if ($dynamicRoute['path'] === $path && isset($dynamicRoute['methods'][$httpMethod])) {
                throw new \RuntimeException(
                  "Collision de route : le path '{$path}' avec la méthode '{$httpMethod}' est déjà enregistré."
                );
              }
            }
            
            // Trouver ou créer l'entrée pour ce pattern
            $found = false;
            $dynamicRouteIndex = null;
            
            foreach ($this->dynamicRoutes as $index => $dynamicRoute) {
              if ($dynamicRoute['pattern'] === $compiled['pattern']) {
                $this->dynamicRoutes[$index]['methods'][$httpMethod] = [
                  'controller' => $controller,
                  'method' => $method->getName(),
                  'middlewares' => $middlewares,
                  'name' => $routeAttribute->name,
                ];
                
                $dynamicRouteIndex = $index;
                $found = true;
                break;
              }
            }
            
            if (!$found) {
              $newDynamicRoute = [
                'pattern' => $compiled['pattern'],
                'params' => $compiled['params'],
                'path' => $path,
                'methods' => [
                  $httpMethod => [
                    'controller' => $controller,
                    'method' => $method->getName(),
                    'middlewares' => $middlewares,
                    'name' => $routeAttribute->name,
                  ],
                ],
              ];
              
              $this->dynamicRoutes[] = $newDynamicRoute;
              $dynamicRouteIndex = count($this->dynamicRoutes) - 1;
              
              // Ajouter à l'index par path pour accès O(1)
              $this->dynamicRoutesByPath[$path] = $dynamicRouteIndex;
            }
            
            // Ajouter à l'index inversé pour recherche rapide par nom (O(1))
            if (!empty($routeAttribute->name)) {
              // Vérifier à nouveau l'unicité (au cas où plusieurs méthodes HTTP)
              if (isset($this->routeNameIndex[$routeAttribute->name])) {
                throw new \RuntimeException(
                  "Le nom de route '{$routeAttribute->name}' est déjà utilisé. " .
                  "Chaque route doit avoir un nom unique."
                );
              }
              
              $this->routeNameIndex[$routeAttribute->name] = [
                'path' => $path,
                'method' => $httpMethod,
                'isDynamic' => true,
                'params' => $compiled['params'],
                'dynamicRouteIndex' => $dynamicRouteIndex,
              ];
            }
          }
        } else {
          // Route statique
          // Initialiser la structure pour ce path si elle n'existe pas
          if (!isset($this->routes[$path])) {
            $this->routes[$path] = [];
          }
          
          // Enregistrer chaque méthode HTTP pour ce path
          foreach ($routeAttribute->methods as $httpMethod) {
            $httpMethod = strtoupper($httpMethod);
            
            // Vérifier les collisions (même path + même méthode)
            if (isset($this->routes[$path][$httpMethod])) {
              throw new \RuntimeException(
                "Collision de route : le path '{$path}' avec la méthode '{$httpMethod}' est déjà enregistré."
              );
            }
            
            $this->routes[$path][$httpMethod] = [
              'controller' => $controller,
              'method' => $method->getName(),
              'middlewares' => $middlewares,
              'name' => $routeAttribute->name,
            ];
            
            // Ajouter à l'index inversé pour recherche rapide par nom
            if (!empty($routeAttribute->name)) {
              // Vérifier à nouveau l'unicité (au cas où plusieurs méthodes HTTP)
              if (isset($this->routeNameIndex[$routeAttribute->name])) {
                throw new \RuntimeException(
                  "Le nom de route '{$routeAttribute->name}' est déjà utilisé. " .
                  "Chaque route doit avoir un nom unique."
                );
              }
              
              $this->routeNameIndex[$routeAttribute->name] = [
                'path' => $path,
                'method' => $httpMethod,
                'isDynamic' => false,
              ];
            }
          }
        }
      }
    }
  }

  /**
   * Récupère ou crée une instance de ReflectionClass avec cache
   * 
   * @param string $class Nom de la classe
   * @return ReflectionClass Instance de ReflectionClass
   */
  private function getReflection(string $class): ReflectionClass
  {
    if (!isset($this->reflectionCache[$class])) {
      $this->reflectionCache[$class] = new ReflectionClass($class);
    }
    return $this->reflectionCache[$class];
  }

  /**
   * Traite une requête et retourne la réponse appropriée
   */
  public function handle(Request $request): Response
  {
    try {
      // Exécuter les middlewares globaux
      foreach ($this->middlewares as $middleware) {
        $response = $this->executeMiddleware($middleware, $request);
        if ($response instanceof Response) {
          return $response;
        }
      }

      $path = $request->getPath();
      $method = strtoupper($request->getMethod());

      // Essayer d'abord les routes statiques (plus rapide)
      $route = null;
      $routeParams = [];
      
      if (isset($this->routes[$path])) {
        // Route statique trouvée
        if (!isset($this->routes[$path][$method])) {
          return new Response(405, 'Method Not Allowed');
        }
        $route = $this->routes[$path][$method];
      } else {
        // Chercher dans les routes dynamiques (triées par spécificité)
        // Les routes avec plus de paramètres sont plus spécifiques et doivent être testées en premier
        $sortedDynamicRoutes = $this->getSortedDynamicRoutes();
        
        foreach ($sortedDynamicRoutes as $dynamicRoute) {
          if (preg_match($dynamicRoute['pattern'], $path, $matches)) {
            // Route dynamique trouvée
            if (!isset($dynamicRoute['methods'][$method])) {
              return new Response(405, 'Method Not Allowed');
            }
            
            // Extraire les paramètres
            $routeParams = [];
            foreach ($dynamicRoute['params'] as $index => $paramName) {
              $routeParams[$paramName] = $matches[$index + 1] ?? null;
            }
            
            $route = $dynamicRoute['methods'][$method];
            break;
          }
        }
      }

      // Si aucune route n'a été trouvée
      if ($route === null) {
        return ErrorHandler::handleNotFound();
      }
      
      // Ajouter les paramètres de route à la requête
      if (!empty($routeParams)) {
        $request->setRouteParams($routeParams);
      }

      // Exécuter les middlewares spécifiques à la route
      foreach ($route['middlewares'] as $middlewareClass) {
        $response = $this->executeMiddleware($middlewareClass, $request);
        if ($response instanceof Response) {
          return $response;
        }
      }

      // Instancier le contrôleur et appeler la méthode
      $controllerClass = $route['controller'];
      $controllerMethod = $route['method'];
      
      // Valider que la classe existe et est instanciable
      if (!class_exists($controllerClass)) {
        throw new \RuntimeException("Le contrôleur {$controllerClass} n'existe pas.");
      }
      
      $reflection = $this->getReflection($controllerClass);
      if (!$reflection->isInstantiable()) {
        throw new \RuntimeException("Le contrôleur {$controllerClass} n'est pas instanciable.");
      }
      
      // Vérifier que la méthode existe
      if (!$reflection->hasMethod($controllerMethod)) {
        throw new \RuntimeException("La méthode {$controllerMethod} n'existe pas dans le contrôleur {$controllerClass}.");
      }
      
      $methodReflection = $reflection->getMethod($controllerMethod);
      if (!$methodReflection->isPublic()) {
        throw new \RuntimeException("La méthode {$controllerMethod} n'est pas publique dans le contrôleur {$controllerClass}.");
      }
      
      // Instancier le contrôleur via le Container si disponible, sinon directement
      if ($this->container !== null && method_exists($this->container, 'make')) {
        $controller = $this->container->make($controllerClass);
      } else {
        $controller = new $controllerClass();
      }
      
      // Préparer les arguments pour la méthode
      $args = [];
      $parameters = $methodReflection->getParameters();
      
      foreach ($parameters as $param) {
        $paramName = $param->getName();
        $paramType = $param->getType();
        
        // Si le paramètre est de type Request, passer l'objet Request
        if ($paramType instanceof \ReflectionNamedType && $paramType->getName() === Request::class) {
          $args[] = $request;
        }
        // Si c'est un paramètre de route, le récupérer depuis routeParams
        elseif (isset($routeParams[$paramName])) {
          $value = $routeParams[$paramName];
          
          // Convertir le type si nécessaire
          if ($paramType instanceof \ReflectionNamedType) {
            $typeName = $paramType->getName();
            
            if ($typeName === 'int') {
              $args[] = (int)$value;
            } elseif ($typeName === 'float') {
              $args[] = (float)$value;
            } elseif ($typeName === 'bool') {
              $args[] = filter_var($value, FILTER_VALIDATE_BOOLEAN);
            } elseif ($typeName === 'string') {
              $args[] = (string)$value;
            } else {
              // Type personnalisé, garder la valeur telle quelle
              $args[] = $value;
            }
          } else {
            // Pas de type ou union type, garder la valeur telle quelle
            $args[] = $value;
          }
        }
        // Si le paramètre a une valeur par défaut, utiliser cette valeur
        elseif ($param->isDefaultValueAvailable()) {
          $args[] = $param->getDefaultValue();
        }
        // Sinon, passer null (peut causer une erreur si le paramètre est requis)
        else {
          $args[] = null;
        }
      }
      
      // Appeler la méthode avec les arguments
      $result = $methodReflection->invokeArgs($controller, $args);
      
      // Si la méthode retourne une Response, la retourner
      if ($result instanceof Response) {
        return $result;
      }
      
      // Sinon, créer une réponse vide (le contrôleur a déjà envoyé la réponse via View ou redirect)
      return new Response(200);

    } catch (\Throwable $e) {
      return ErrorHandler::handleServerError($e);
    }
  }

  /**
   * Exécute un middleware et retourne une Response si le middleware arrête l'exécution
   * Retourne null si l'exécution doit continuer
   */
  private function executeMiddleware(string|Middleware $middleware, Request $request): ?Response
  {
    // Si c'est déjà une instance, l'utiliser directement
    if ($middleware instanceof Middleware) {
      $middlewareInstance = $middleware;
    } else {
      // Sinon, instancier la classe via le Container si disponible
      if (!class_exists($middleware)) {
        throw new \InvalidArgumentException("Le middleware {$middleware} n'existe pas.");
      }
      
      if (!is_subclass_of($middleware, Middleware::class)) {
        throw new \InvalidArgumentException("Le middleware {$middleware} doit implémenter l'interface Middleware.");
      }
      
      if ($this->container !== null && method_exists($this->container, 'make')) {
        $middlewareInstance = $this->container->make($middleware);
      } else {
        $middlewareInstance = new $middleware();
      }
    }

    // Exécuter le middleware et récupérer la réponse
    $response = $middlewareInstance->handle($request);
    
    // Si le middleware retourne une Response, l'arrêter l'exécution
    // Sinon, continuer avec le middleware suivant
    return $response;
  }

  /**
   * Valide le format d'un path
   * 
   * @param string $path Path à valider
   * @throws \InvalidArgumentException Si le path est malformé
   */
  private function validatePath(string $path): void
  {
    // Rejeter les doubles slashes (sauf pour la racine)
    if ($path !== '/' && str_contains($path, '//')) {
      throw new \InvalidArgumentException(
        "Le path '{$path}' contient des doubles slashes. " .
        "Utilisez un seul slash entre les segments."
      );
    }
    
    // Rejeter les trailing slashes sauf pour la racine
    if ($path !== '/' && str_ends_with($path, '/')) {
      throw new \InvalidArgumentException(
        "Le path '{$path}' se termine par un slash (sauf pour la racine). " .
        "Supprimez le trailing slash."
      );
    }
    
    // Valider le format des paramètres dynamiques
    if (preg_match('/\{[^}]+\}/', $path, $matches)) {
      foreach ($matches as $match) {
        // Vérifier que le nom du paramètre est valide (lettres, chiffres, underscore)
        $paramName = trim($match, '{}');
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $paramName)) {
          throw new \InvalidArgumentException(
            "Le paramètre '{$match}' dans le path '{$path}' a un nom invalide. " .
            "Utilisez uniquement des lettres, chiffres et underscores, commençant par une lettre ou underscore."
          );
        }
      }
    }
  }

  /**
   * Vérifie si un path contient des paramètres dynamiques
   */
  private function hasDynamicParams(string $path): bool
  {
    return str_contains($path, '{') && str_contains($path, '}');
  }

  /**
   * Compile une route dynamique en pattern regex et extrait les noms des paramètres
   * 
   * @param string $path Path avec paramètres (ex: /user/{id}/post/{slug})
   * @param array $constraints Contraintes de validation pour les paramètres (ex: ['id' => '\d+'])
   * @return array ['pattern' => regex, 'params' => ['id', 'slug']]
   */
  private function compileDynamicRoute(string $path, array $constraints = []): array
  {
    // Vérifier le cache de compilation
    $cacheKey = $path . '|' . serialize($constraints);
    if (isset($this->compilationCache[$cacheKey])) {
      return $this->compilationCache[$cacheKey];
    }
    
    $params = [];
    
    // Remplacer les paramètres {name} par des placeholders temporaires
    $placeholders = [];
    $pattern = preg_replace_callback(
      '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
      function ($matches) use (&$params, &$placeholders, $constraints) {
        $paramName = $matches[1];
        $params[] = $paramName;
        $placeholder = '__PARAM_' . count($params) . '__';
        $placeholders[] = [
          'placeholder' => $placeholder,
          'constraint' => $constraints[$paramName] ?? null
        ];
        return $placeholder;
      },
      $path
    );
    
    // Échapper tous les caractères spéciaux de regex
    $pattern = preg_quote($pattern, '#');
    
    // Remplacer les placeholders par les patterns de capture avec contraintes
    foreach ($placeholders as $item) {
      $placeholder = $item['placeholder'];
      $constraint = $item['constraint'];
      
      // Utiliser la contrainte si fournie, sinon pattern par défaut
      $capturePattern = $constraint !== null ? "({$constraint})" : '([^/]+)';
      $pattern = str_replace(preg_quote($placeholder, '#'), $capturePattern, $pattern);
    }
    
    // Ajouter les délimiteurs de début et fin
    $pattern = '#^' . $pattern . '$#';
    
    $result = [
      'pattern' => $pattern,
      'params' => $params,
    ];
    
    // Mettre en cache
    $this->compilationCache[$cacheKey] = $result;
    
    return $result;
  }

  /**
   * Retourne toutes les routes enregistrées (utile pour le debug)
   */
  public function getRoutes(): array
  {
    return [
      'static' => $this->routes,
      'dynamic' => $this->dynamicRoutes,
    ];
  }

  /**
   * Retourne une route par son nom (optimisé O(1) pour toutes les routes)
   * 
   * @param string $name Nom de la route
   * @return array|null Informations sur la route ou null si non trouvée
   */
  public function getRouteByName(string $name): ?array
  {
    if (empty($name) || !isset($this->routeNameIndex[$name])) {
      return null;
    }
    
    $index = $this->routeNameIndex[$name];
    
    if ($index['isDynamic']) {
      // Route dynamique : accès direct O(1) via index
      $dynamicRouteIndex = $index['dynamicRouteIndex'] ?? null;
      if ($dynamicRouteIndex !== null && isset($this->dynamicRoutes[$dynamicRouteIndex])) {
        $dynamicRoute = $this->dynamicRoutes[$dynamicRouteIndex];
        if (isset($dynamicRoute['methods'][$index['method']])) {
          return [
            'path' => $dynamicRoute['path'],
            'method' => $index['method'],
            'route' => $dynamicRoute['methods'][$index['method']],
            'params' => $dynamicRoute['params'],
          ];
        }
      }
    } else {
      // Route statique : accès direct O(1)
      if (isset($this->routes[$index['path']][$index['method']])) {
        return [
          'path' => $index['path'],
          'method' => $index['method'],
          'route' => $this->routes[$index['path']][$index['method']],
        ];
      }
    }
    
    return null;
  }

  /**
   * Génère une URL à partir du nom d'une route et de ses paramètres
   * 
   * @param string $name Nom de la route
   * @param array $params Paramètres à remplacer dans l'URL (ex: ['id' => 123])
   * @param array $queryParams Paramètres de query string à ajouter (ex: ['page' => 2])
   * @return string|null URL générée ou null si la route n'existe pas
   */
  public function url(string $name, array $params = [], array $queryParams = []): ?string
  {
    $route = $this->getRouteByName($name);
    
    if ($route === null) {
      return null;
    }
    
    $path = $route['path'];
    
    // Remplacer les paramètres dans le path
    if (isset($route['params'])) {
      // Route dynamique
      foreach ($route['params'] as $paramName) {
        if (!isset($params[$paramName])) {
          throw new \InvalidArgumentException("Le paramètre '{$paramName}' est requis pour la route '{$name}'.");
        }
        
        $value = $params[$paramName];
        // Encoder la valeur pour l'URL
        $path = str_replace('{' . $paramName . '}', rawurlencode((string)$value), $path);
      }
    }
    
    // Ajouter les query parameters si présents
    if (!empty($queryParams)) {
      $queryString = http_build_query($queryParams);
      $path .= '?' . $queryString;
    }
    
    return $path;
  }

  /**
   * Crée un groupe de routes avec un préfixe et des middlewares communs
   * 
   * @param string $prefix Préfixe pour toutes les routes du groupe
   * @param array $middlewares Middlewares à appliquer à toutes les routes du groupe
   * @param callable $callback Fonction contenant les appels à registerRoutes()
   * @return void
   */
  public function group(string $prefix, array $middlewares, callable $callback): void
  {
    // Normaliser le préfixe
    $prefix = rtrim($prefix, '/');
    if (empty($prefix)) {
      $prefix = '';
    } else {
      $prefix = '/' . ltrim($prefix, '/');
    }
    
    // Sauvegarder l'état actuel
    $previousPrefix = $this->currentGroupPrefix ?? '';
    $previousMiddlewares = $this->currentGroupMiddlewares ?? [];
    
    // Définir le nouveau préfixe et middlewares pour ce groupe
    $this->currentGroupPrefix = $previousPrefix . $prefix;
    $this->currentGroupMiddlewares = array_merge($previousMiddlewares, $middlewares);
    
    try {
      // Exécuter le callback
      $callback($this);
    } finally {
      // Restaurer l'état précédent
      $this->currentGroupPrefix = $previousPrefix;
      $this->currentGroupMiddlewares = $previousMiddlewares;
    }
  }

  /**
   * Préfixe actuel pour les groupes de routes
   */
  private ?string $currentGroupPrefix = null;

  /**
   * Middlewares actuels pour les groupes de routes
   */
  private array $currentGroupMiddlewares = [];

  /**
   * Retourne les routes dynamiques triées par spécificité (plus de paramètres = plus spécifique)
   * Cette optimisation améliore les performances en testant d'abord les routes les plus spécifiques
   * 
   * @return array Routes dynamiques triées
   */
  private function getSortedDynamicRoutes(): array
  {
    // Créer une copie pour ne pas modifier l'original
    $sorted = $this->dynamicRoutes;
    
    // Trier par nombre de paramètres (décroissant) puis par longueur du path (décroissante)
    usort($sorted, function($a, $b) {
      $aParamCount = count($a['params']);
      $bParamCount = count($b['params']);
      
      // D'abord par nombre de paramètres (plus = plus spécifique)
      if ($aParamCount !== $bParamCount) {
        return $bParamCount <=> $aParamCount;
      }
      
      // Ensuite par longueur du path (plus long = plus spécifique)
      return strlen($b['path']) <=> strlen($a['path']);
    });
    
    return $sorted;
  }

  /**
   * Exécute le router (méthode principale pour compatibilité avec l'ancien code)
   */
  public function run(): void
  {
    $request = new Request();
    $response = $this->handle($request);
    $response->send();
  }
}
