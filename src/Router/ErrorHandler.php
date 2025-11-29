<?php

declare(strict_types=1);

namespace JulienLinard\Router;

class ErrorHandler
{
  /**
   * Vérifie si le mode debug est activé
   * 
   * @return bool True si le mode debug est activé
   */
  private static function isDebugMode(): bool
  {
    return defined('APP_DEBUG') && constant('APP_DEBUG') === true;
  }

  public static function handleNotFound(): Response
  {
    // Vérifier si on est en mode debug pour afficher une page HTML
    if (self::isDebugMode()) {
      return self::renderErrorPage('Page non trouvée', 'La page demandée n\'existe pas.', 404);
    }
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
    
    // Si on est en mode debug, afficher une page HTML détaillée
    if (self::isDebugMode()) {
      return self::renderErrorPage(
        'Erreur serveur',
        $e->getMessage(),
        500,
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
      );
    }
    
    return Response::json(['error' => 'Internal Server Error'], 500);
  }
  
  /**
   * Rend une page d'erreur HTML détaillée
   */
  private static function renderErrorPage(
    string $title,
    string $message,
    int $code = 500,
    ?string $file = null,
    ?int $line = null,
    ?string $trace = null
  ): Response {
    $html = self::getErrorPageHtml($title, $message, $code, $file, $line, $trace);
    return new Response($code, $html, ['Content-Type' => 'text/html; charset=utf-8']);
  }
  
  /**
   * Génère le HTML de la page d'erreur
   */
  private static function getErrorPageHtml(
    string $title,
    string $message,
    int $code,
    ?string $file,
    ?int $line,
    ?string $trace
  ): string {
    $fileDisplay = $file ? htmlspecialchars($file) : 'N/A';
    $lineDisplay = $line ? htmlspecialchars((string)$line) : 'N/A';
    $traceDisplay = $trace ? '<div class="error-details"><h5>🔍 Stack Trace</h5><pre>' . htmlspecialchars($trace) . '</pre></div>' : '';
    $titleEscaped = htmlspecialchars($title);
    $messageEscaped = htmlspecialchars($message);
    
    return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur {$code} - {$titleEscaped}</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <style>
        .error-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
        }
        .error-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            max-width: 900px;
            width: 100%;
            overflow: hidden;
        }
        .error-header {
            background: #dc3545;
            color: white;
            padding: 20px;
            font-size: 24px;
            font-weight: bold;
        }
        .error-body {
            padding: 30px;
        }
        .error-message {
            background: #f8f9fa;
            border-left: 4px solid #dc3545;
            padding: 15px;
            margin-bottom: 20px;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .error-details {
            margin-top: 20px;
        }
        .error-details h5 {
            color: #495057;
            margin-bottom: 10px;
        }
        .error-details pre {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 15px;
            overflow-x: auto;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
        }
        .file-path {
            color: #6c757d;
            font-size: 13px;
        }
        .line-number {
            color: #dc3545;
            font-weight: bold;
        }
        .back-button {
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-card">
            <div class="error-header">
                ⚠️ Erreur {$code} - {$titleEscaped}
            </div>
            <div class="error-body">
                <div class="error-message">{$messageEscaped}</div>
                
                <div class="error-details">
                    <h5>📍 Fichier</h5>
                    <div class="file-path">
                        <strong>{$fileDisplay}</strong>
                        <span class="line-number"> (ligne {$lineDisplay})</span>
                    </div>
                </div>
                
                {$traceDisplay}
                
                <div class="back-button">
                    <a href="javascript:history.back()" class="btn btn-primary">← Retour</a>
                    <a href="/" class="btn btn-secondary">Accueil</a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
HTML;
  }
}
