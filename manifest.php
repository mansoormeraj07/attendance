<?php
require __DIR__ . '/lib.php';
$B = brand();
header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: no-cache');
$icons = [
  ['src' => 'assets/icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
  ['src' => 'assets/icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
];
echo json_encode([
  'name' => $B['name'],
  'short_name' => mb_substr($B['name'], 0, 12),
  'description' => $B['tagline'],
  'start_url' => 'index.php',
  'scope' => './',
  'display' => 'standalone',
  'background_color' => '#070b16',
  'theme_color' => '#070b16',
  'icons' => $icons,
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
