<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

$settings = json_decode(file_get_contents(__DIR__.'/../runtime-settings.json'), true, 512, JSON_THROW_ON_ERROR);
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'GET' || ! hash_equals($settings['http_token'], $_SERVER['HTTP_X_READINESS_TOKEN'] ?? '')
    || ! in_array(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), ['/up', '/login', '/dashboard', '/courses', '/downloads'], true)) {
    http_response_code(404);
    exit;
}
$app = require __DIR__.'/validation-bootstrap.php';
if (($_SERVER['HTTP_X_READINESS_PERSONA'] ?? '') === 'learner') {
    Auth::onceUsingId($settings['probe_user_id']);
}
$kernel = $app->make(Kernel::class);
$request = Request::capture();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
