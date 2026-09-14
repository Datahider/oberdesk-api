<?php

use losthost\DB\DB;
use losthost\OberdeskAPIv1\Controller\GroupController;
use losthost\OberdeskAPIv1\Controller\GroupThreadsController;
use losthost\OberdeskAPIv1\Controller\MeController;
use losthost\OberdeskAPIv1\Controller\GroupsController;
use losthost\OberdeskAPIv1\Controller\ThreadController;
use losthost\OberdeskAPIv1\Controller\ThreadMessagesController;

require __DIR__ . '/vendor/autoload.php';
require __DIR__ . '/etc/config.php';

header('Content-Type: application/json; charset=utf-8');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$originParts = $origin ? parse_url($origin) : null;
$originHost = $originParts['host'] ?? '';
$originScheme = $originParts['scheme'] ?? '';

if (
    $origin
    && $originScheme === 'https'
    && (
        $originHost === 'oberdesk.ru'
        || str_ends_with($originHost, '.oberdesk.ru')
    )
) {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Access-Control-Allow-Credentials: true');
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');

    $requestHeaders = $_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS'] ?? '';
    if ($requestHeaders !== '') {
        header('Access-Control-Allow-Headers: ' . $requestHeaders);
    }

    http_response_code(204);
    exit;
}

DB::connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PREF, DB_CHARSET);

$routes = [
    ['GET', '#^/me$#', MeController::class, 'handle'],
    ['GET', '#^/groups$#', GroupsController::class, 'handle'],
    ['GET', '#^/group/([^/]+)$#', GroupController::class, 'handle'],
    ['GET', '#^/group/([^/]+)/threads$#', GroupThreadsController::class, 'handle'],
    ['GET', '#^/thread/([^/]+)$#', ThreadController::class, 'handle'],
    ['GET', '#^/thread/([^/]+)/messages$#', ThreadMessagesController::class, 'handle'],
];

$method = $_SERVER['REQUEST_METHOD'];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = preg_replace('#^/v1#', '', $uri);
$data = null;

foreach ($routes as [$routeMethod, $pattern, $class, $action]) {
    if ($routeMethod !== $method) {
        continue;
    }

    if (!preg_match($pattern, $path, $matches)) {
        continue;
    }

    array_shift($matches);
    $matches = array_map('rawurldecode', $matches);
    $data = (new $class())->$action(...$matches);
    break;
}

if ($data === null) {
    http_response_code(404);
    $data = ['ok' => false];
}

echo json_encode($data);
