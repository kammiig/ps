<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\CustomerAuth;
use App\Core\RateLimiter;
use App\Models\ContentRepository;
use App\Models\PaymentRepository;
use App\Services\StripeService;
use App\Services\WhmcsService;

final class PaymentController extends Controller
{
    private ContentRepository $content;
    private array $settings;
    private WhmcsService $whmcs;
    private StripeService $stripe;
    private PaymentRepository $payments;
    private CustomerAuth $auth;

    public function __construct()
    {
        $this->content = new ContentRepository();
        $this->settings = $this->content->settings();
        $this->whmcs = new WhmcsService($this->settings);
        $this->stripe = new StripeService();
        $this->payments = new PaymentRepository();
        $this->auth = new CustomerAuth();
    }

    public function show(string $token): string
    {
        $order = $this->payments->findByToken($token);
        if (!$order || !$this->canView($order)) {
            return $this->notFound();
        }

        if (!$this->auth->check()) {
            $this->redirect(url('/account/login?next=' . rawurlencode('/checkout/payment/' . $token)));
        }

        if ($order['payment_status'] === 'paid') {
            $this->redirect(url('/checkout/success?order=' . rawurlencode($token)));
        }

        $refreshed = $this->refreshInvoiceAmount($order);
        if (!$refreshed['ok']) {
            return $this->render('site/payment-failed', $this->baseData('checkout', [
                'order' => $order,
                'message' => $refreshed['message'],
            ]));
        }
        $order = $refreshed['order'];

        $identifier = ($_SERVER['REMOTE_ADDR'] ?? 'local') . ':' . $order['id'];
        if (RateLimiter::tooManyAttempts('payment_intent', $identifier, 20, 300)) {
            return $this->render('site/payment-failed', $this->baseData('checkout', [
                'order' => $order,
                'message' => 'Too many payment requests were made. Please wait a few minutes and try again.',
            ]));
        }
        RateLimiter::hit('payment_intent', $identifier, 300);

        $intent = $this->stripe->paymentIntentForOrder($order);
        if (!$intent['ok']) {
            return $this->render('site/payment-failed', $this->baseData('checkout', [
                'order' => $order,
                'message' => $this->paymentSetupMessage((string) ($intent['message'] ?? '')),
            ]));
        }

        if (($order['stripe_payment_intent_id'] ?? '') !== $intent['payment_intent_id']) {
            $order = $this->payments->setPaymentIntent((int) $order['id'], $intent['payment_intent_id']) ?? $order;
        }

        return $this->render('site/payment', $this->baseData('checkout', [
            'order' => $order,
            'clientSecret' => $intent['client_secret'],
            'publishableKey' => $this->stripe->publishableKey(),
            'amountLabel' => $this->money((float) $order['invoice_amount'], (string) $order['currency']),
        ]));
    }

    public function success(): string
    {
        $token = (string) ($_GET['order'] ?? '');
        $order = $token !== '' ? $this->payments->findByToken($token) : null;
        if (!$order || !$this->canView($order)) {
            return $this->notFound();
        }

        return $this->render('site/payment-success', $this->baseData('checkout', [
            'order' => $order,
            'amountLabel' => $this->money((float) $order['invoice_amount'], (string) $order['currency']),
        ]));
    }

    public function failed(): string
    {
        $token = (string) ($_GET['order'] ?? '');
        $order = $token !== '' ? $this->payments->findByToken($token) : null;
        if (!$order || !$this->canView($order)) {
            return $this->notFound();
        }

        return $this->render('site/payment-failed', $this->baseData('checkout', [
            'order' => $order,
            'message' => 'Your payment was not completed. You can safely retry using the same invoice.',
        ]));
    }

    public function payInvoice(string $invoiceId): string
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $this->redirect(url('/account/billing'));
        }

        $user = $this->requireCustomer();
        $whmcsClientId = (int) ($user['whmcs_client_id'] ?? 0);
        if ($whmcsClientId <= 0) {
            $this->redirect(url('/account/billing'));
        }

        $identifier = ($_SERVER['REMOTE_ADDR'] ?? 'local') . ':' . $user['id'];
        if (RateLimiter::tooManyAttempts('payment_intent', $identifier, 10, 300)) {
            $this->redirect(url('/account/billing'));
        }
        RateLimiter::hit('payment_intent', $identifier, 300);

        $existingOrder = $this->payments->findByInvoiceId((int) $invoiceId);
        $invoice = $this->whmcs->invoiceForClient(
            (int) $invoiceId,
            $whmcsClientId,
            (int) ($existingOrder['whmcs_order_id'] ?? 0)
        );
        if (!$invoice['ok'] || !$this->invoiceBelongsToClient($invoice['invoice'], $whmcsClientId)) {
            $this->redirect(url('/account/billing'));
        }

        $amount = $this->invoiceAmountDue($invoice['invoice']);
        if ($amount <= 0) {
            $this->redirect(url('/account/billing'));
        }

        $order = $this->payments->createOrUpdateOrder([
            'customer_user_id' => (int) $user['id'],
            'whmcs_client_id' => $whmcsClientId,
            'whmcs_order_id' => (int) ($invoice['invoice']['orderid'] ?? 0),
            'whmcs_invoice_id' => (int) $invoiceId,
            'invoice_amount' => $amount,
            'currency' => $this->invoiceCurrency($invoice['invoice']),
        ]);

        $this->redirect(url('/checkout/payment/' . $order['public_token']));
    }

    public function webhook(): string
    {
        $payload = file_get_contents('php://input') ?: '';
        $signature = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        $verified = $this->stripe->verifyWebhook($payload, $signature);
        if (!$verified['ok']) {
            http_response_code(400);
            return 'Invalid webhook signature.';
        }

        $event = $verified['event'];
        $eventId = (string) ($event['id'] ?? '');
        $type = (string) ($event['type'] ?? '');
        $intent = $event['data']['object'] ?? [];
        $paymentIntentId = (string) ($intent['id'] ?? '');

        if ($eventId === '' || $paymentIntentId === '') {
            http_response_code(400);
            return 'Invalid webhook event.';
        }

        $order = $this->paymentOrderForIntent($intent);
        $inserted = $this->payments->insertWebhookEvent($eventId, $type, $paymentIntentId, $order ? (int) $order['id'] : null);
        if (!$inserted) {
            $existingEvent = $this->payments->webhookEvent($eventId);
            if (($existingEvent['processing_status'] ?? '') !== 'failed') {
                return json_encode(['received' => true, 'duplicate' => true]);
            }
            if ($type === 'payment_intent.succeeded') {
                return $this->handleSucceededWebhook($eventId, $intent, $order);
            }
            return json_encode(['received' => true, 'duplicate' => true]);
        }

        if ($type === 'payment_intent.succeeded') {
            return $this->handleSucceededWebhook($eventId, $intent, $order);
        }

        if ($type === 'payment_intent.payment_failed') {
            $message = (string) ($intent['last_payment_error']['message'] ?? 'Payment failed.');
            $this->payments->markFailedByPaymentIntent($paymentIntentId, $message);
            $this->payments->markWebhookEvent($eventId, 'processed', $order ? (int) $order['id'] : null);
            return json_encode(['received' => true]);
        }

        $this->payments->markWebhookEvent($eventId, 'processed', $order ? (int) $order['id'] : null);
        return json_encode(['received' => true]);
    }

    private function handleSucceededWebhook(string $eventId, array $intent, ?array $order): string
    {
        if (!$order) {
            $this->payments->markWebhookEvent($eventId, 'failed');
            http_response_code(400);
            return 'Payment order was not found.';
        }

        if ($order['payment_status'] === 'paid') {
            $this->payments->markWebhookEvent($eventId, 'processed', (int) $order['id']);
            return json_encode(['received' => true]);
        }

        $paidAmount = $this->stripe->minorUnitsToAmount((int) ($intent['amount_received'] ?? 0), (string) ($intent['currency'] ?? $order['currency']));
        $paidCurrency = strtoupper((string) ($intent['currency'] ?? ''));
        $expectedCurrency = strtoupper((string) $order['currency']);
        if (abs($paidAmount - (float) $order['invoice_amount']) > 0.01 || $paidCurrency !== $expectedCurrency) {
            $this->payments->markPendingWithError((int) $order['id'], 'Stripe paid amount did not match the saved invoice amount.');
            $this->payments->markWebhookEvent($eventId, 'failed', (int) $order['id']);
            http_response_code(400);
            return 'Amount mismatch.';
        }

        $gateway = $this->whmcs->invoicePaymentGateway();
        if ($gateway === '') {
            $this->payments->markPendingWithError((int) $order['id'], 'WHMCS payment gateway name is not configured.');
            $this->payments->markWebhookEvent($eventId, 'failed', (int) $order['id']);
            http_response_code(500);
            return 'Gateway not configured.';
        }

        if (!$this->payments->markProcessing((int) $order['id'])) {
            $latestOrder = $this->payments->find((int) $order['id']) ?? $order;
            if (($latestOrder['payment_status'] ?? '') === 'paid') {
                $this->payments->markWebhookEvent($eventId, 'processed', (int) $order['id']);
                return json_encode(['received' => true]);
            }

            $this->payments->markWebhookEvent($eventId, 'failed', (int) $order['id']);
            http_response_code(409);
            return 'Payment is already being processed.';
        }

        $recorded = $this->whmcs->addInvoicePayment(
            (int) $order['whmcs_invoice_id'],
            (string) $intent['id'],
            $paidAmount,
            $gateway
        );

        if (!$recorded['ok']) {
            $this->payments->markPendingWithError((int) $order['id'], 'Payment reached Stripe but could not be recorded on the invoice yet.');
            $this->payments->markWebhookEvent($eventId, 'failed', (int) $order['id']);
            http_response_code(500);
            return 'Invoice payment could not be recorded.';
        }

        $this->payments->markPaid((int) $order['id'], (string) $intent['id']);
        $this->payments->markWebhookEvent($eventId, 'processed', (int) $order['id']);
        return json_encode(['received' => true]);
    }

    private function paymentOrderForIntent(array $intent): ?array
    {
        $paymentIntentId = (string) ($intent['id'] ?? '');
        if ($paymentIntentId !== '') {
            $order = $this->payments->findByPaymentIntent($paymentIntentId);
            if ($order) {
                return $order;
            }
        }

        $localOrderId = (int) ($intent['metadata']['local_order_id'] ?? 0);
        return $localOrderId > 0 ? $this->payments->find($localOrderId) : null;
    }

    private function paymentSetupMessage(string $message): string
    {
        if (str_contains(strtolower($message), 'not configured')) {
            return 'Secure card payment is not configured yet. Please contact support.';
        }

        return 'Secure card payment is not available right now. Please contact support.';
    }

    private function refreshInvoiceAmount(array $order): array
    {
        $invoice = $this->whmcs->invoiceForClient(
            (int) $order['whmcs_invoice_id'],
            (int) $order['whmcs_client_id'],
            (int) ($order['whmcs_order_id'] ?? 0)
        );
        if (!$invoice['ok']) {
            return ['ok' => false, 'message' => 'The invoice could not be loaded for secure payment. Please try again shortly.'];
        }
        if (!$this->invoiceBelongsToClient($invoice['invoice'], (int) $order['whmcs_client_id'])) {
            return ['ok' => false, 'message' => 'This invoice could not be matched to your account.'];
        }

        $status = strtolower((string) ($invoice['invoice']['status'] ?? ''));
        if ($status === 'paid') {
            return ['ok' => false, 'message' => 'This invoice is already paid.'];
        }

        $amount = $this->invoiceAmountDue($invoice['invoice']);
        if ($amount <= 0) {
            return ['ok' => false, 'message' => 'This invoice does not have a payable balance.'];
        }

        $updated = $this->payments->createOrUpdateOrder([
            'customer_user_id' => $order['customer_user_id'],
            'whmcs_client_id' => (int) $order['whmcs_client_id'],
            'whmcs_order_id' => (int) ($order['whmcs_order_id'] ?? 0),
            'whmcs_invoice_id' => (int) $order['whmcs_invoice_id'],
            'invoice_amount' => $amount,
            'currency' => $this->invoiceCurrency($invoice['invoice']),
        ]);

        return ['ok' => true, 'order' => $updated];
    }

    private function canView(array $order): bool
    {
        $user = $this->auth->user();
        if (!$user) {
            return true;
        }

        return (int) ($order['customer_user_id'] ?? 0) === (int) $user['id'];
    }

    private function requireCustomer(): array
    {
        $user = $this->auth->user();
        if (!$user) {
            $this->redirect(url('/account/login'));
        }

        return $user;
    }

    private function invoiceBelongsToClient(array $invoice, int $clientId): bool
    {
        $invoiceClientId = (int) ($invoice['userid'] ?? $invoice['clientid'] ?? $invoice['user_id'] ?? 0);
        if ($invoiceClientId > 0) {
            return $invoiceClientId === $clientId;
        }

        $clientInvoices = $this->whmcs->invoicesForClient($clientId);
        if (!$clientInvoices['ok']) {
            return false;
        }

        foreach ($clientInvoices['invoices'] as $clientInvoice) {
            if ((int) ($clientInvoice['id'] ?? $clientInvoice['invoiceid'] ?? 0) === (int) ($invoice['invoiceid'] ?? $invoice['id'] ?? 0)) {
                return true;
            }
        }

        return false;
    }

    private function invoiceAmountDue(array $invoice): float
    {
        foreach (['balance', 'amountpaidremaining', 'total', 'subtotal'] as $key) {
            if (isset($invoice[$key]) && is_numeric($invoice[$key]) && (float) $invoice[$key] > 0) {
                return round((float) $invoice[$key], 2);
            }
        }

        return 0.0;
    }

    private function invoiceCurrency(array $invoice): string
    {
        $currency = $invoice['currencycode'] ?? $invoice['currency'] ?? 'GBP';
        if (is_array($currency)) {
            $currency = $currency['code'] ?? $currency['suffix'] ?? 'GBP';
        }

        $currency = strtoupper(preg_replace('/[^A-Z]/i', '', (string) $currency) ?: 'GBP');
        return strlen($currency) === 3 ? $currency : 'GBP';
    }

    private function money(float $amount, string $currency): string
    {
        $symbol = strtoupper($currency) === 'GBP' ? '£' : strtoupper($currency) . ' ';
        return $symbol . number_format($amount, 2);
    }

    private function baseData(string $routeKey, array $data = []): array
    {
        $seo = $this->content->seo($routeKey);
        return [
            ...$data,
            'settings' => $this->settings,
            'whmcs' => $this->whmcs,
            'customerUser' => $this->auth->user(),
            'meta' => [
                'title' => $seo['meta_title'] ?? 'Secure Payment | Planetic Solutions',
                'description' => $seo['meta_description'] ?? 'Secure on-site payment for Planetic Solutions invoices.',
                'keywords' => $seo['keywords'] ?? '',
                'og_title' => $seo['og_title'] ?? '',
                'og_image' => '',
                'canonical_url' => '',
            ],
            'schemas' => [],
            'csrfToken' => Csrf::token(),
        ];
    }

    private function notFound(): string
    {
        http_response_code(404);
        return (new SiteController())->notFound();
    }
}
