<?php

namespace JulienLinard\Router\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
  public string $path;
  public string $name;
  public array $methods;
  public array $middleware;
  /**
   * Contraintes de validation pour les paramètres de route
   * Format : ['paramName' => 'regex']
   * Exemple : ['id' => '\d+'] pour valider que id est numérique
   */
  public array $constraints;

  public function __construct(
    string $path, 
    string $name = '', 
    array $methods = ['GET'], 
    array $middleware = [],
    array $constraints = []
  )
  {
    $this->path = $path;
    $this->name = $name;
    $this->methods = $methods;
    $this->middleware = $middleware;
    $this->constraints = $constraints;
  }
}
