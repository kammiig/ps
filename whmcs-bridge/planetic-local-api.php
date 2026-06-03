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

define('PLANETIC_BRIDGE_VERSION', '2026-06-03-package-priceoverride-v4');

$bridgeToken = 'change_this_long_random_token';
$adminUsername = '';

header('Content-Type: application/json; charset=UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['status'])) {
    echo json_encode([
        'result' => 'success',
        'bridge' => 'planetic-local-api',
        'version' => PLANETIC_BRIDGE_VERSION,
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['result' => 'error', 'message' => 'Method not allowed.', 'version' => PLANETIC_BRIDGE_VERSION]);
    exit;
}

$allowedActions = [
    'DomainWhois',
    'GetTLDPricing',
    'GetProducts',
    'GetClientsDetails',
    'AddClient',
    'AddOrder',
    'AddInvoicePayment',
    'AcceptOrder',
    'CreateSsoToken',
    'GetInvoice',
    'GetInvoices',
    'GetOrders',
    'PlaneticGetInvoice',
    'PlaneticGetOrderInvoice',
    'GetClientsProducts',
    'GetClientsDomains',
    'UpdateClient',
    'DomainGetNameservers',
    'DomainUpdateNameservers',
];

$action = (string) ($_POST['action'] ?? '');
if (!in_array($action, $allowedActions, true)) {
    http_response_code(403);
    echo json_encode([
        'result' => 'error',
        'message' => 'Bridge action is not allowed. Upload the latest Planetic WHMCS bridge file.',
        'version' => PLANETIC_BRIDGE_VERSION,
    ]);
    exit;
}

$initPath = __DIR__ . '/init.php';
if (!is_file($initPath)) {
    http_response_code(500);
    echo json_encode(['result' => 'error', 'message' => 'WHMCS init.php was not found.']);
    exit;
}

require $initPath;

if (isset($planetic_bridge_token) && is_string($planetic_bridge_token) && $planetic_bridge_token !== '') {
    $bridgeToken = $planetic_bridge_token;
}

if (isset($planetic_bridge_admin) && is_string($planetic_bridge_admin) && $planetic_bridge_admin !== '') {
    $adminUsername = $planetic_bridge_admin;
}
if (isset($planetic_bridge_admin_username) && is_string($planetic_bridge_admin_username) && $planetic_bridge_admin_username !== '') {
    $adminUsername = $planetic_bridge_admin_username;
}

$providedToken = (string) ($_POST['bridge_token'] ?? '');
if ($bridgeToken === 'change_this_long_random_token' || !hash_equals($bridgeToken, $providedToken)) {
    http_response_code(403);
    echo json_encode(['result' => 'error', 'message' => 'Bridge authentication failed.']);
    exit;
}

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

function planeticBridgeCurrency(int $clientId): array
{
    $currencyId = (int) \WHMCS\Database\Capsule::table('tblclients')
        ->where('id', $clientId)
        ->value('currency');

    $currencyRow = $currencyId > 0
        ? \WHMCS\Database\Capsule::table('tblcurrencies')->where('id', $currencyId)->first()
        : null;

    return $currencyRow ? (array) $currencyRow : [];
}

function planeticBridgeInvoicePayload(array $invoice, int $invoiceId, int $clientId, int $orderId = 0): array
{
    $status = (string) ($invoice['status'] ?? 'Unpaid');
    $total = round((float) ($invoice['total'] ?? 0), 2);
    $credit = round((float) ($invoice['credit'] ?? 0), 2);
    $balance = strtolower($status) === 'paid' ? 0.0 : max(0.0, round($total - $credit, 2));
    $currency = planeticBridgeCurrency($clientId);

    return [
        'invoiceid' => $invoiceId,
        'id' => $invoiceId,
        'userid' => $clientId,
        'clientid' => $clientId,
        'orderid' => $orderId,
        'invoicenum' => $invoice['invoicenum'] ?? '',
        'date' => $invoice['date'] ?? '',
        'duedate' => $invoice['duedate'] ?? '',
        'subtotal' => number_format((float) ($invoice['subtotal'] ?? $total), 2, '.', ''),
        'credit' => number_format($credit, 2, '.', ''),
        'total' => number_format($total, 2, '.', ''),
        'balance' => number_format($balance, 2, '.', ''),
        'status' => $status,
        'paymentmethod' => $invoice['paymentmethod'] ?? '',
        'currencycode' => (string) ($currency['code'] ?? 'GBP'),
        'currency' => [
            'code' => (string) ($currency['code'] ?? 'GBP'),
            'prefix' => (string) ($currency['prefix'] ?? ''),
            'suffix' => (string) ($currency['suffix'] ?? ''),
        ],
    ];
}

function planeticBridgeIdsFromRows($rows): array
{
    $ids = [];
    foreach ($rows as $row) {
        $row = (array) $row;
        $id = (int) ($row['id'] ?? 0);
        if ($id > 0) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

function planeticBridgeInvoiceIdsFromItems(int $clientId, array $relIds, array $types): array
{
    if ($relIds === []) {
        return [];
    }

    $rows = \WHMCS\Database\Capsule::table('tblinvoiceitems')
        ->where('userid', $clientId)
        ->whereIn('relid', $relIds)
        ->whereIn('type', $types)
        ->orderBy('invoiceid', 'desc')
        ->get();

    $invoiceIds = [];
    foreach ($rows as $row) {
        $row = (array) $row;
        $invoiceId = (int) ($row['invoiceid'] ?? 0);
        if ($invoiceId > 0) {
            $invoiceIds[] = $invoiceId;
        }
    }

    return array_values(array_unique($invoiceIds));
}

function planeticBridgeInvoiceRow(int $invoiceId, int $clientId)
{
    if ($invoiceId <= 0 || $clientId <= 0) {
        return null;
    }

    return \WHMCS\Database\Capsule::table('tblinvoices')
        ->where('id', $invoiceId)
        ->where('userid', $clientId)
        ->first();
}

function planeticBridgeInvoicePayableAmount(array $invoice): float
{
    if (strtolower((string) ($invoice['status'] ?? '')) === 'paid') {
        return 0.0;
    }

    $total = round((float) ($invoice['total'] ?? 0), 2);
    $credit = round((float) ($invoice['credit'] ?? 0), 2);
    return max(0.0, round($total - $credit, 2));
}

function planeticBridgeInvoiceMatchesExpected(array $invoice, float $expectedAmount): bool
{
    if ($expectedAmount <= 0) {
        return true;
    }

    return abs(planeticBridgeInvoicePayableAmount($invoice) - round($expectedAmount, 2)) <= 0.01;
}

function planeticBridgeFirstMatchingInvoiceId(int $clientId, array $invoiceIds, float $expectedAmount): int
{
    $firstExisting = 0;
    foreach (array_values(array_unique($invoiceIds)) as $candidateId) {
        $candidate = planeticBridgeInvoiceRow((int) $candidateId, $clientId);
        if (!$candidate) {
            continue;
        }

        if ($firstExisting <= 0) {
            $firstExisting = (int) $candidateId;
        }

        if (planeticBridgeInvoiceMatchesExpected((array) $candidate, $expectedAmount)) {
            return (int) $candidateId;
        }
    }

    return $expectedAmount > 0 ? 0 : $firstExisting;
}

function planeticBridgeResolveOrderInvoiceId(array $order, int $orderId, int $clientId, float $expectedAmount = 0.0): int
{
    $candidateIds = [];

    $invoiceId = (int) ($order['invoiceid'] ?? 0);
    if ($invoiceId > 0) {
        $candidateIds[] = $invoiceId;
    }

    $hostingIds = planeticBridgeIdsFromRows(
        \WHMCS\Database\Capsule::table('tblhosting')
            ->where('orderid', $orderId)
            ->where('userid', $clientId)
            ->get()
    );

    $domainIds = planeticBridgeIdsFromRows(
        \WHMCS\Database\Capsule::table('tbldomains')
            ->where('orderid', $orderId)
            ->where('userid', $clientId)
            ->get()
    );

    $candidates = array_merge(
        planeticBridgeInvoiceIdsFromItems($clientId, $hostingIds, ['Hosting', 'Setup', 'Addon']),
        planeticBridgeInvoiceIdsFromItems($clientId, $domainIds, ['DomainRegister', 'DomainTransfer', 'DomainRenew', 'Domain'])
    );
    $candidateIds = array_merge($candidateIds, $candidates);

    $matchedCandidate = planeticBridgeFirstMatchingInvoiceId($clientId, $candidateIds, $expectedAmount);
    if ($matchedCandidate > 0) {
        return $matchedCandidate;
    }

    $orderDate = (string) ($order['date'] ?? $order['datecreated'] ?? '');
    if ($orderDate === '') {
        return 0;
    }

    $orderAmount = round((float) ($order['amount'] ?? 0), 2);
    $recentQuery = \WHMCS\Database\Capsule::table('tblinvoices')
        ->where('userid', $clientId)
        ->where('status', 'Unpaid')
        ->where('total', '>', 0)
        ->where('date', '>=', substr($orderDate, 0, 10))
        ->orderBy('id', 'desc');

    if ($expectedAmount > 0) {
        $recentQuery->where('total', number_format($expectedAmount, 2, '.', ''));
    } elseif ($orderAmount > 0) {
        $recentQuery->where('total', number_format($orderAmount, 2, '.', ''));
    }

    $recent = $recentQuery->first();
    if (!$recent) {
        return 0;
    }

    $recent = (array) $recent;
    return planeticBridgeInvoiceMatchesExpected($recent, $expectedAmount) ? (int) $recent['id'] : 0;
}

if ($action === 'PlaneticGetInvoice') {
    $invoiceId = (int) ($params['invoiceid'] ?? 0);
    $clientId = (int) ($params['clientid'] ?? $params['userid'] ?? 0);
    $orderId = (int) ($params['orderid'] ?? 0);

    if ($invoiceId <= 0 || $clientId <= 0) {
        http_response_code(422);
        echo json_encode(['result' => 'error', 'message' => 'Invoice ID and client ID are required.']);
        exit;
    }

    try {
        if (!class_exists('\WHMCS\Database\Capsule')) {
            throw new RuntimeException('WHMCS database layer is unavailable.');
        }

        $invoiceRow = \WHMCS\Database\Capsule::table('tblinvoices')
            ->where('id', $invoiceId)
            ->where('userid', $clientId)
            ->first();

        if (!$invoiceRow) {
            echo json_encode(['result' => 'error', 'message' => 'Invoice not found.']);
            exit;
        }

        echo json_encode([
            'result' => 'success',
            'invoice' => planeticBridgeInvoicePayload((array) $invoiceRow, $invoiceId, $clientId, $orderId),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable) {
        http_response_code(500);
        echo json_encode(['result' => 'error', 'message' => 'Bridge invoice lookup failed.']);
        exit;
    }
}

if ($action === 'PlaneticGetOrderInvoice') {
    $orderId = (int) ($params['orderid'] ?? 0);
    $clientId = (int) ($params['clientid'] ?? $params['userid'] ?? 0);
    $expectedAmount = round((float) ($params['expected_amount'] ?? 0), 2);

    if ($orderId <= 0 || $clientId <= 0) {
        http_response_code(422);
        echo json_encode(['result' => 'error', 'message' => 'Order ID and client ID are required.']);
        exit;
    }

    try {
        if (!class_exists('\WHMCS\Database\Capsule')) {
            throw new RuntimeException('WHMCS database layer is unavailable.');
        }

        $orderRow = \WHMCS\Database\Capsule::table('tblorders')
            ->where('id', $orderId)
            ->where('userid', $clientId)
            ->first();

        if (!$orderRow) {
            echo json_encode(['result' => 'error', 'message' => 'Order not found.']);
            exit;
        }

        $order = (array) $orderRow;
        $invoiceId = planeticBridgeResolveOrderInvoiceId($order, $orderId, $clientId, $expectedAmount);
        if ($invoiceId <= 0) {
            echo json_encode(['result' => 'error', 'message' => 'Order has no invoice attached.']);
            exit;
        }

        $invoiceRow = \WHMCS\Database\Capsule::table('tblinvoices')
            ->where('id', $invoiceId)
            ->where('userid', $clientId)
            ->first();

        if (!$invoiceRow) {
            echo json_encode(['result' => 'error', 'message' => 'Invoice not found.']);
            exit;
        }

        echo json_encode([
            'result' => 'success',
            'invoice' => planeticBridgeInvoicePayload((array) $invoiceRow, $invoiceId, $clientId, $orderId),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    } catch (Throwable) {
        http_response_code(500);
        echo json_encode(['result' => 'error', 'message' => 'Bridge order invoice lookup failed.']);
        exit;
    }
}

try {
    if ($adminUsername === '' && class_exists('\WHMCS\Database\Capsule')) {
        try {
            $adminUsername = (string) \WHMCS\Database\Capsule::table('tbladmins')
                ->where('disabled', 0)
                ->orderBy('id')
                ->value('username');
        } catch (Throwable) {
            $adminUsername = '';
        }
    }

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
