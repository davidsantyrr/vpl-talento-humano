<?php
// Custom router for PHP built-in server to avoid static folder collisions
// Routes everything through Laravel unless it's a real file in /public

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . '/public' . $path;

// If the request is for an existing real file, let the server handle it
if ($path !== '/' && file_exists($file) && is_file($file)) {
    return false;
}

// Otherwise, hand off to Laravel's front controller
require __DIR__ . '/public/index.php';
