<?php

namespace JulienLinard\Router;

use ReflectionClass;
use JulienLinard\Router\Attributes\Route as RouteAttribute;

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
        
        // Fusionner les middlewares du groupe avec ceux de la route
        $middlewares = array_merge($this->currentGroupMiddlewares, $routeAttribute->middleware);
        
        // Vérifier si la route contient des paramètres dynamiques
        if ($this->hasDynamicParams($path)) {
          // Route dynamique
          $compiled = $this->compileDynamicRoute($path);
          
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
        if ($response !== null) {
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
        // Chercher dans les routes dynamiques
        foreach ($this->dynamicRoutes as $dynamicRoute) {
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
        if ($response !== null) {
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
      
      $controller = new $controllerClass();
      return $controller->$controllerMethod($request);

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
      // Sinon, instancier la classe
      if (!class_exists($middleware)) {
        throw new \InvalidArgumentException("Le middleware {$middleware} n'existe pas.");
      }
      
      if (!is_subclass_of($middleware, Middleware::class)) {
        throw new \InvalidArgumentException("Le middleware {$middleware} doit implémenter l'interface Middleware.");
      }
      
      $middlewareInstance = new $middleware();
    }

    // Exécuter le middleware
    $middlewareInstance->handle($request);
    
    // Si le middleware a envoyé une réponse (via exit ou autre), on ne peut pas continuer
    // Pour l'instant, on retourne null pour continuer l'exécution
    // Note: Les middlewares qui utilisent exit() empêcheront l'exécution de continuer
    return null;
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
   * @return array ['pattern' => regex, 'params' => ['id', 'slug']]
   */
  private function compileDynamicRoute(string $path): array
  {
    $params = [];
    
    // Remplacer les paramètres {name} par des placeholders temporaires
    $placeholders = [];
    $pattern = preg_replace_callback(
      '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
      function ($matches) use (&$params, &$placeholders) {
        $params[] = $matches[1];
        $placeholder = '__PARAM_' . count($params) . '__';
        $placeholders[] = $placeholder;
        return $placeholder;
      },
      $path
    );
    
    // Échapper tous les caractères spéciaux de regex
    $pattern = preg_quote($pattern, '#');
    
    // Remplacer les placeholders par les patterns de capture
    foreach ($placeholders as $placeholder) {
      $pattern = str_replace(preg_quote($placeholder, '#'), '([^/]+)', $pattern);
    }
    
    // Ajouter les délimiteurs de début et fin
    $pattern = '#^' . $pattern . '$#';
    
    return [
      'pattern' => $pattern,
      'params' => $params,
    ];
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
