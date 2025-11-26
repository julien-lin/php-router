<?php

namespace JulienLinard\Router;

class Request
{
  private string $path;
  private string $method;
  private array $queryParams = [];
  private array $headers = [];
  private array $cookies = [];
  private ?array $body = null;
  private string $rawBody = '';
  private array $routeParams = [];
  
  /**
   * Taille maximale du body en bytes (10MB par défaut, protection DoS)
   */
  private int $maxBodySize = 10 * 1024 * 1024; // 10MB

  /**
   * @param string|null $uri URI personnalisée (pour les tests), null pour utiliser $_SERVER
   * @param string|null $method Méthode HTTP personnalisée (pour les tests), null pour utiliser $_SERVER
   */
  public function __construct(?string $uri = null, ?string $method = null)
  {
    $requestUri = $uri ?? $_SERVER['REQUEST_URI'] ?? '/';
    $this->method = strtoupper($method ?? $_SERVER['REQUEST_METHOD'] ?? 'GET');
    
    // Séparer le path de la query string
    $parsedUrl = parse_url($requestUri);
    $this->path = $parsedUrl['path'] ?? '/';
    
    // Normaliser le path (supprimer les trailing slashes sauf pour la racine)
    $this->path = rtrim($this->path, '/') ?: '/';
    
    // Parser les query parameters
    if (isset($parsedUrl['query'])) {
      parse_str($parsedUrl['query'], $this->queryParams);
    }
    
    // Charger les headers HTTP
    $this->loadHeaders();
    
    // Charger les cookies
    $this->cookies = $_COOKIE ?? [];
    
    // Charger le body pour les méthodes POST/PUT/PATCH
    if (in_array($this->method, ['POST', 'PUT', 'PATCH'])) {
      $this->loadBody();
    }
  }

  /**
   * Charge les headers HTTP depuis $_SERVER
   */
  private function loadHeaders(): void
  {
    foreach ($_SERVER as $key => $value) {
      if (str_starts_with($key, 'HTTP_')) {
        // Convertir HTTP_X_FORWARDED_FOR en X-Forwarded-For
        $headerName = str_replace('_', '-', substr($key, 5));
        $this->headers[strtolower($headerName)] = $value;
      } elseif (in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
        // Headers spéciaux sans préfixe HTTP_
        $this->headers[strtolower(str_replace('_', '-', $key))] = $value;
      }
    }
  }

  /**
   * Charge le body de la requête
   */
  private function loadBody(): void
  {
    // Lire le body avec limite de taille pour protection DoS
    $contentLength = (int)($this->getHeader('content-length', '0') ?? 0);
    
    // Vérifier la taille avant de lire (protection DoS)
    if ($contentLength > $this->maxBodySize) {
      throw new \RuntimeException(
        sprintf('Body size (%d bytes) exceeds maximum allowed size (%d bytes)', $contentLength, $this->maxBodySize)
      );
    }
    
    $this->rawBody = file_get_contents('php://input');
    
    // Vérifier la taille réelle après lecture (double protection)
    if (strlen($this->rawBody) > $this->maxBodySize) {
      throw new \RuntimeException(
        sprintf('Body size (%d bytes) exceeds maximum allowed size (%d bytes)', strlen($this->rawBody), $this->maxBodySize)
      );
    }
    
    // Optimisation : ne pas parser si le body est vide
    if (empty($this->rawBody)) {
      $this->body = [];
      return;
    }
    
    $contentType = $this->getHeader('content-type', '');
    
    if (str_contains($contentType, 'application/json')) {
      $decoded = json_decode($this->rawBody, true);
      $this->body = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    } elseif (str_contains($contentType, 'application/x-www-form-urlencoded')) {
      parse_str($this->rawBody, $this->body);
    } else {
      // Pour les autres types, essayer de parser comme form-urlencoded
      parse_str($this->rawBody, $this->body);
    }
  }
  
  /**
   * Définit la taille maximale du body (protection DoS)
   * 
   * @param int $maxSize Taille maximale en bytes
   */
  public function setMaxBodySize(int $maxSize): void
  {
    $this->maxBodySize = $maxSize;
  }
  
  /**
   * Retourne la taille maximale du body
   * 
   * @return int Taille maximale en bytes
   */
  public function getMaxBodySize(): int
  {
    return $this->maxBodySize;
  }

  /**
   * Retourne le chemin de la requête (sans query string)
   */
  public function getPath(): string
  {
    return $this->path;
  }

  /**
   * Retourne la méthode HTTP (GET, POST, etc.)
   */
  public function getMethod(): string
  {
    return $this->method;
  }

  /**
   * Retourne tous les query parameters
   */
  public function getQueryParams(): array
  {
    return $this->queryParams;
  }

  /**
   * Retourne un query parameter spécifique
   * 
   * @param string $key Clé du paramètre
   * @param mixed $default Valeur par défaut si le paramètre n'existe pas
   * @return mixed
   */
  public function getQueryParam(string $key, $default = null)
  {
    return $this->queryParams[$key] ?? $default;
  }

  /**
   * Retourne tous les headers HTTP
   */
  public function getHeaders(): array
  {
    return $this->headers;
  }

  /**
   * Retourne un header HTTP spécifique
   * 
   * @param string $name Nom du header (case-insensitive)
   * @param string|null $default Valeur par défaut si le header n'existe pas
   * @return string|null
   */
  public function getHeader(string $name, ?string $default = null): ?string
  {
    return $this->headers[strtolower($name)] ?? $default;
  }

  /**
   * Retourne tous les cookies
   */
  public function getCookies(): array
  {
    return $this->cookies;
  }

  /**
   * Retourne un cookie spécifique
   * 
   * @param string $name Nom du cookie
   * @param mixed $default Valeur par défaut si le cookie n'existe pas
   * @return mixed
   */
  public function getCookie(string $name, $default = null)
  {
    return $this->cookies[$name] ?? $default;
  }

  /**
   * Retourne le body parsé (tableau pour JSON/form-data)
   */
  public function getBody(): ?array
  {
    return $this->body;
  }

  /**
   * Retourne un paramètre du body
   * 
   * @param string $key Clé du paramètre
   * @param mixed $default Valeur par défaut si le paramètre n'existe pas
   * @return mixed
   */
  public function getBodyParam(string $key, $default = null)
  {
    return $this->body[$key] ?? $default;
  }

  /**
   * Retourne le body brut (string)
   */
  public function getRawBody(): string
  {
    return $this->rawBody;
  }

  /**
   * Vérifie si la requête est en AJAX
   */
  public function isAjax(): bool
  {
    return strtolower($this->getHeader('x-requested-with', '')) === 'xmlhttprequest';
  }

  /**
   * Vérifie si la requête accepte JSON
   */
  public function wantsJson(): bool
  {
    $accept = $this->getHeader('accept', '');
    return str_contains($accept, 'application/json');
  }

  /**
   * Définit les paramètres de route (utilisé par le Router)
   * 
   * @param array $params Paramètres de route extraits de l'URL
   */
  public function setRouteParams(array $params): void
  {
    $this->routeParams = $params;
  }

  /**
   * Retourne tous les paramètres de route
   */
  public function getRouteParams(): array
  {
    return $this->routeParams;
  }

  /**
   * Retourne un paramètre de route spécifique
   * 
   * @param string $key Clé du paramètre
   * @param mixed $default Valeur par défaut si le paramètre n'existe pas
   * @return mixed
   */
  public function getRouteParam(string $key, $default = null)
  {
    return $this->routeParams[$key] ?? $default;
  }

  /**
   * Retourne les données POST
   * 
   * @param string|null $key Clé du paramètre (null pour retourner tout $_POST)
   * @param mixed $default Valeur par défaut si le paramètre n'existe pas
   * @return mixed|array
   */
  public function getPost(?string $key = null, $default = null)
  {
    if ($key === null) {
      return $_POST;
    }

    return $_POST[$key] ?? $default;
  }
}
