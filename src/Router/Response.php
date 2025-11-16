<?php

namespace JulienLinard\Router;

class Response
{
  private int $statusCode;
  private array $headers = [];
  private string $content;

  /**
   * @param int $statusCode Code de statut HTTP (200 par défaut)
   * @param string $content Contenu de la réponse (chaîne vide par défaut)
   */
  public function __construct(int $statusCode = 200, string $content = '')
  {
    $this->statusCode = $statusCode;
    $this->content = $content;
  }

  /**
   * Définit un header HTTP
   * 
   * @param string $name Nom du header
   * @param string $value Valeur du header (sera échappée pour éviter les injections CRLF)
   */
  public function setHeader(string $name, string $value): void
  {
    // Valider et nettoyer le nom du header
    $name = $this->sanitizeHeaderName($name);
    
    // Échapper la valeur pour éviter les injections CRLF
    $value = $this->sanitizeHeaderValue($value);
    
    $this->headers[$name] = $value;
  }

  /**
   * Nettoie le nom d'un header pour éviter les injections
   */
  private function sanitizeHeaderName(string $name): string
  {
    // Supprimer les caractères non autorisés dans les noms de headers
    return preg_replace('/[^a-zA-Z0-9\-]/', '', $name);
  }

  /**
   * Nettoie la valeur d'un header pour éviter les injections CRLF
   */
  private function sanitizeHeaderValue(string $value): string
  {
    // Supprimer les retours à la ligne et les caractères de contrôle
    $value = str_replace(["\r", "\n"], '', $value);
    // Supprimer les caractères de contrôle (0x00-0x1F sauf tab)
    $value = preg_replace('/[\x00-\x08\x0B-\x1F]/', '', $value);
    return $value;
  }

  /**
   * Envoie la réponse HTTP au client
   */
  public function send(): void
  {
    http_response_code($this->statusCode);
    foreach ($this->headers as $name => $value) {
      header("$name: $value");
    }
    if ($this->content !== '') {
      echo $this->content;
    }
  }

  /**
   * Crée une réponse JSON
   * 
   * @param mixed $data Données à encoder en JSON
   * @param int $statusCode Code de statut HTTP (200 par défaut)
   * @return self Instance de Response avec le contenu JSON
   */
  public static function json($data, int $statusCode = 200): self
  {
    $response = new self($statusCode, json_encode($data));
    $response->setHeader('Content-Type', 'application/json');
    return $response;
  }

  /**
   * Retourne le code de statut HTTP
   */
  public function getStatusCode(): int
  {
    return $this->statusCode;
  }

  /**
   * Retourne le contenu de la réponse
   */
  public function getContent(): string
  {
    return $this->content;
  }

  /**
   * Retourne tous les headers HTTP définis
   */
  public function getHeaders(): array
  {
    return $this->headers;
  }
}
