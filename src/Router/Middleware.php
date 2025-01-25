<?php

namespace JulienLinard\Router;

interface Middleware
{
  public function handle(Request $request): void;
}
