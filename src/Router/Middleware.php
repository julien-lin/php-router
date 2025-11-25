<?php

namespace JulienLinard\Router;

interface Middleware
{
  /**
   * Traite la requête
   * 
   * @param Request $request Requête HTTP
   * @return Response|null Réponse si le middleware arrête l'exécution, null pour continuer
   */
  public function handle(Request $request): ?Response;
}
