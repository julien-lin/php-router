<?php

namespace JulienLinard\Router\Middlewares\Traits;

trait SessionAware
{
  /**
   * Démarre la session si elle n'est pas déjà démarrée
   * Évite les warnings si la session est déjà active
   */
  protected function ensureSessionStarted(): void
  {
    if (session_status() === PHP_SESSION_NONE) {
      session_start();
    }
  }
}

