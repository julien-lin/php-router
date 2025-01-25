<?php

namespace JulienLinard\View;

class View
{
  private string $template;

  public function __construct(string $template)
  {
    $this->template = $template;
  }

  public function render(array $data = []): string
  {
    extract($data);
    ob_start();
    include __DIR__ . "/../../templates/{$this->template}.php";
    return ob_get_clean();
  }
}
