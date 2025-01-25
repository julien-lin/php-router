<?php

namespace JulienLinard\Router;

class Response
{
  private int $statusCode;
  private array $headers = [];
  private $body;
  private string $content;

  public function __construct(int $statusCode = 200, $body = null, string $content = '')
  {
    $this->statusCode = $statusCode;
    $this->body = $body;
    $this->content = $content;
  }

  public function setHeader(string $name, string $value): void
  {
    $this->headers[$name] = $value;
  }

  public function send(): void
  {
    http_response_code($this->statusCode);
    foreach ($this->headers as $name => $value) {
      header("$name: $value");
    }
    if ($this->body !== null) {
      echo $this->body;
    }
  }

  public static function json($data, int $statusCode = 200): self
  {
    $response = new self($statusCode, json_encode($data));
    $response->setHeader('Content-Type', 'application/json');
    return $response;
  }

  public function getStatusCode(): int
  {
    return $this->statusCode;
  }

  public function getContent(): string
  {
    return $this->content;
  }
}
