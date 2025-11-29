<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use JulienLinard\Router\Response;
use JulienLinard\Router\Request;
use JulienLinard\Router\Middlewares\CorsMiddleware;

class SecurityTest extends TestCase
{
  /**
   * Test de protection contre l'injection CRLF dans les headers
   */
  public function testCrlfInjectionProtection()
  {
    $response = new Response();
    
    // Tentative d'injection CRLF dans le nom du header
    $response->setHeader("X-Test\r\nInjected-Header", "value");
    $headers = $response->getHeaders();
    
    // Le header doit être nettoyé (CRLF supprimé)
    $this->assertArrayHasKey('x-testinjected-header', $headers);
    $this->assertStringNotContainsString("\r", $headers['x-testinjected-header']);
    $this->assertStringNotContainsString("\n", $headers['x-testinjected-header']);
    
    // Tentative d'injection CRLF dans la valeur du header
    $response2 = new Response();
    $response2->setHeader("X-Custom", "value\r\nInjected: header");
    $headers2 = $response2->getHeaders();
    
    // La valeur doit être nettoyée
    $this->assertArrayHasKey('x-custom', $headers2);
    $this->assertStringNotContainsString("\r", $headers2['x-custom']);
    $this->assertStringNotContainsString("\n", $headers2['x-custom']);
    $this->assertEquals('valueInjected: header', $headers2['x-custom']);
  }

  /**
   * Test de protection contre les caractères de contrôle dans les headers
   */
  public function testControlCharacterProtection()
  {
    $response = new Response();
    
    // Caractères de contrôle (0x00-0x1F)
    $response->setHeader("X-Test", "value\x00\x01\x02Injection");
    $headers = $response->getHeaders();
    
    // Les caractères de contrôle doivent être supprimés
    $this->assertArrayHasKey('x-test', $headers);
    $this->assertStringNotContainsString("\x00", $headers['x-test']);
    $this->assertStringNotContainsString("\x01", $headers['x-test']);
    $this->assertEquals('valueInjection', $headers['x-test']);
  }

  /**
   * Test de validation CORS avec origine valide
   */
  public function testCorsValidOrigin()
  {
    $cors = new CorsMiddleware(['https://example.com', 'https://app.example.com']);
    
    // Test origine autorisée
    $_SERVER['HTTP_ORIGIN'] = 'https://example.com';
    $request = new Request();
    $response = $cors->handle($request);
    
    $this->assertNull($response); // Le middleware continue l'exécution
    
    unset($_SERVER['HTTP_ORIGIN']);
  }

  /**
   * Test de validation CORS avec origine non autorisée
   */
  public function testCorsInvalidOrigin()
  {
    $cors = new CorsMiddleware(['https://example.com']);
    
    // Test origine non autorisée
    $_SERVER['HTTP_ORIGIN'] = 'https://evil.com';
    $request = new Request();
    $response = $cors->handle($request);
    
    // Le middleware devrait continuer mais ne pas ajouter les headers CORS
    $this->assertNull($response);
    
    unset($_SERVER['HTTP_ORIGIN']);
  }

  /**
   * Test de validation CORS avec wildcard
   */
  public function testCorsWildcardOrigin()
  {
    $cors = new CorsMiddleware(['*']);
    
    $_SERVER['HTTP_ORIGIN'] = 'https://any-origin.com';
    $request = new Request();
    $response = $cors->handle($request);
    
    $this->assertNull($response);
    
    unset($_SERVER['HTTP_ORIGIN']);
  }

  /**
   * Test de validation CORS avec schéma invalide
   */
  public function testCorsInvalidScheme()
  {
    $cors = new CorsMiddleware(['https://example.com']);
    
    // Test avec schéma non autorisé (ftp)
    $_SERVER['HTTP_ORIGIN'] = 'ftp://example.com';
    $request = new Request();
    $response = $cors->handle($request);
    
    // Le middleware ne devrait pas accepter un schéma non HTTP/HTTPS
    $this->assertNull($response);
    
    unset($_SERVER['HTTP_ORIGIN']);
  }

  /**
   * Test de protection DoS - limite de taille du body
   */
  public function testBodySizeLimit()
  {
    $request = new Request();
    
    // Vérifier la limite par défaut (10MB)
    $this->assertEquals(10 * 1024 * 1024, $request->getMaxBodySize());
    
    // Modifier la limite
    $request->setMaxBodySize(5 * 1024 * 1024);
    $this->assertEquals(5 * 1024 * 1024, $request->getMaxBodySize());
  }

  /**
   * Test de validation des noms de headers
   */
  public function testHeaderNameSanitization()
  {
    $response = new Response();
    
    // Test avec caractères spéciaux dans le nom
    $response->setHeader("X-Test@#$%", "value");
    $headers = $response->getHeaders();
    
    // Les caractères spéciaux doivent être supprimés
    $this->assertArrayHasKey('x-test', $headers);
    $this->assertEquals('value', $headers['x-test']);
  }

  /**
   * Test de normalisation des noms de headers en minuscules
   */
  public function testHeaderNameNormalization()
  {
    $response = new Response();
    
    // Test avec nom en majuscules
    $response->setHeader("X-CUSTOM-HEADER", "value");
    $headers = $response->getHeaders();
    
    // Le nom doit être normalisé en minuscules
    $this->assertArrayHasKey('x-custom-header', $headers);
    $this->assertArrayNotHasKey('X-CUSTOM-HEADER', $headers);
  }
}
