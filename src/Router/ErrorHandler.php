<?php

namespace JulienLinard\Router;

class ErrorHandler
{
  public static function handleNotFound(): Response
  {
    return Response::json(['error' => 'Not Found'], 404);
  }

  public static function handleServerError(\Throwable $e): Response
  {
    // Logger le message et la stack trace complète pour faciliter le débogage
    error_log(sprintf(
      "Server Error: %s\nFile: %s:%d\nStack trace:\n%s",
      $e->getMessage(),
      $e->getFile(),
      $e->getLine(),
      $e->getTraceAsString()
    ));
    return Response::json(['error' => 'Internal Server Error'], 500);
  }
}
