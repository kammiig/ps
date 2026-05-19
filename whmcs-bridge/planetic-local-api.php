<?php

declare(strict_types=1);

/*
 * Upload this file to:
 * /home/planeticsolution/public_html/clientarea/planetic-local-api.php
 *
 * Then set the same token in the main website .env:
 * WHMCS_LOCAL_API_BRIDGE_URL=https://planeticsolution.com/clientarea/planetic-local-api.php
 * WHMCS_LOCAL_API_BRIDGE_TOKEN=change_this_long_random_token
 */

$bridgeToken = 'change_this_long_random_token';
$adminUsername = '';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['result' => 'error', 'message' => 'Method not allowed.']);
    exit;
}

$providedToken = (string) ($_POST['bridge_token'] ?? '');
if ($bridgeToken === 'change_this_long_random_token' || !hash_equals($bridgeToken, $providedToken)) {
    http_response_code(403);
    echo json_encode(['result' => 'error', 'message' => 'Bridge authentication failed.']);
    exit;
}

$allowedActions = [
    'DomainWhois',
    'GetTLDPricing',
    'GetProducts',
    'GetClientsDetails',
    'AddClient',
    'AddOrder',
    'AcceptOrder',
    'CreateSsoToken',
];

$action = (string) ($_POST['action'] ?? '');
if (!in_array($action, $allowedActions, true)) {
    http_response_code(403);
    echo json_encode(['result' => 'error', 'message' => 'Bridge action is not allowed.']);
    exit;
}

$initPath = __DIR__ . '/init.php';
if (!is_file($initPath)) {
    http_response_code(500);
    echo json_encode(['result' => 'error', 'message' => 'WHMCS init.php was not found.']);
    exit;
}

require $initPath;

$params = $_POST;
unset(
    $params['action'],
    $params['bridge_token'],
    $params['identifier'],
    $params['secret'],
    $params['accesskey'],
    $params['access_key'],
    $params['responsetype']
);

try {
    $response = $adminUsername !== ''
        ? localAPI($action, $params, $adminUsername)
        : localAPI($action, $params);
} catch (Throwable $exception) {
    http_response_code(500);
    echo json_encode([
        'result' => 'error',
        'message' => 'WHMCS local API failed.',
    ]);
    exit;
}

echo json_encode($response, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
