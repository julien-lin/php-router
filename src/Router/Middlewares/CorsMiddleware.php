<?php

namespace JulienLinard\Router\Middlewares;

use JulienLinard\Router\Middleware;
use JulienLinard\Router\Request;
use JulienLinard\Router\Response;

class CorsMiddleware implements Middleware
{
  private array $allowedOrigins;
  private array $allowedMethods;
  private array $allowedHeaders;
  private bool $allowCredentials;

  /**
   * @param array|string $allowedOrigins Origines autorisées (['*'] par défaut pour toutes)
   * @param array $allowedMethods Méthodes HTTP autorisées
   * @param array $allowedHeaders Headers autorisés
   * @param bool $allowCredentials Autoriser les credentials (cookies, etc.)
   */
  public function __construct(
    array|string $allowedOrigins = ['*'],
    array $allowedMethods = ['GET', 'POST', 'PUT', 'DELETE', 'PATCH', 'OPTIONS'],
    array $allowedHeaders = ['Content-Type', 'Authorization'],
    bool $allowCredentials = false
  ) {
    $this->allowedOrigins = is_array($allowedOrigins) ? $allowedOrigins : [$allowedOrigins];
    $this->allowedMethods = array_map('strtoupper', $allowedMethods);
    $this->allowedHeaders = $allowedHeaders;
    $this->allowCredentials = $allowCredentials;
  }

  /**
   * Gère les en-têtes CORS pour la requête
   */
  public function handle(Request $request): void
  {
    $origin = $request->getHeader('origin', '');
    
    // Vérifier si l'origine est autorisée
    if ($this->isOriginAllowed($origin)) {
      // Utiliser Response pour définir les headers de manière sécurisée
      $response = new Response();
      $response->setHeader('Access-Control-Allow-Origin', $this->getAllowedOrigin($origin));
      $response->setHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods));
      $response->setHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders));
      
      if ($this->allowCredentials) {
        $response->setHeader('Access-Control-Allow-Credentials', 'true');
      }
      
      // Envoyer les headers immédiatement (CORS doit être envoyé avant le contenu)
      foreach ($response->getHeaders() as $name => $value) {
        header("$name: $value", false);
      }
    }

    // Gérer les requêtes preflight OPTIONS
    if ($request->getMethod() === 'OPTIONS') {
      Response::json([], 204)->send();
      exit;
    }
  }

  /**
   * Vérifie si l'origine est autorisée
   */
  private function isOriginAllowed(string $origin): bool
  {
    if (empty($origin)) {
      return false;
    }
    
    return in_array('*', $this->allowedOrigins) || in_array($origin, $this->allowedOrigins);
  }

  /**
   * Retourne l'origine autorisée à utiliser dans le header
   */
  private function getAllowedOrigin(string $origin): string
  {
    if (in_array('*', $this->allowedOrigins)) {
      return '*';
    }
    
    return in_array($origin, $this->allowedOrigins) ? $origin : $this->allowedOrigins[0];
  }

}
