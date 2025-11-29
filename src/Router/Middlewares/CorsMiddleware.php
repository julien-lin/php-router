<?php

declare(strict_types=1);

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
  public function handle(Request $request): ?Response
  {
    $origin = $request->getHeader('origin', '');
    
    // Vérifier si l'origine est autorisée avec validation stricte
    if ($this->isOriginAllowed($origin)) {
      $allowedOrigin = $this->getAllowedOrigin($origin);
      
      // Utiliser la méthode utilitaire sécurisée pour envoyer les headers
      Response::sendHeader('Access-Control-Allow-Origin', $allowedOrigin, false);
      Response::sendHeader('Access-Control-Allow-Methods', implode(', ', $this->allowedMethods), false);
      Response::sendHeader('Access-Control-Allow-Headers', implode(', ', $this->allowedHeaders), false);
      
      if ($this->allowCredentials) {
        Response::sendHeader('Access-Control-Allow-Credentials', 'true', false);
      }
    }

    // Gérer les requêtes preflight OPTIONS
    if ($request->getMethod() === 'OPTIONS') {
      return Response::json([], 204);
    }
    
    return null; // Continuer l'exécution
  }

  /**
   * Vérifie si l'origine est autorisée avec validation stricte
   * 
   * @param string $origin Origine à valider
   * @return bool True si l'origine est autorisée
   */
  private function isOriginAllowed(string $origin): bool
  {
    if (empty($origin)) {
      return false;
    }
    
    // Validation basique de l'URL (protection contre les injections)
    if (!filter_var($origin, FILTER_VALIDATE_URL)) {
      return false;
    }
    
    // Vérifier le schéma (doit être http ou https)
    $parsed = parse_url($origin);
    if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'])) {
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
