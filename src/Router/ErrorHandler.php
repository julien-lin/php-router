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
    error_log($e->getMessage());
    return Response::json(['error' => 'Internal Server Error'], 500);
  }
}
