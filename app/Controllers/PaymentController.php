<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\CustomerAuth;
use App\Core\RateLimiter;
use App\Models\ContentRepository;
use App\Models\PaymentRepository;
use App\Models\ProvisioningRepository;
use App\Services\StripeService;
use App\Services\WhmcsService;

final class PaymentController extends Controller
{
    private ContentRepository $content;
    private array $settings;
    private WhmcsService $whmcs;
    private StripeService $stripe;
    private PaymentRepository $payments;
    private ProvisioningRepository $provisioning;
    private CustomerAuth $auth;

    public function __construct()
    {
        $this->content = new ContentRepository();
        $this->settings = $this->content->settings();
        $this->whmcs = new WhmcsService($this->settings);
        $this->stripe = new StripeService();
        $this->payments = new PaymentRepository();
        $this->provisioning = new ProvisioningRepository();
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

        $order = $this->syncOrderFromStripe($order);
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

        $order = $this->syncOrderFromStripe($order);

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

    public function status(): string
    {
        header('Content-Type: application/json; charset=UTF-8');

        $token = (string) ($_GET['order'] ?? '');
        $order = $token !== '' ? $this->payments->findByToken($token) : null;
        if (!$order || !$this->canView($order)) {
            http_response_code(404);
            return json_encode(['ok' => false, 'message' => 'Payment was not found.'], JSON_UNESCAPED_SLASHES);
        }

        $order = $this->syncOrderFromStripe($order);
        $status = strtolower((string) ($order['payment_status'] ?? 'pending'));
        $labels = [
            'pending' => 'Pending Payment',
            'processing' => 'Processing',
            'paid' => 'Paid',
            'failed' => 'Payment Failed',
        ];

        return json_encode([
            'ok' => true,
            'status' => $status,
            'confirmed' => $status === 'paid',
            'label' => $labels[$status] ?? ucwords($status),
            'invoice_reference' => '#' . (int) $order['whmcs_invoice_id'],
            'amount' => $this->money((float) $order['invoice_amount'], (string) $order['currency']),
        ], JSON_UNESCAPED_SLASHES);
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
            $this->logPaymentIssue('Stripe webhook rejected.', [
                'message' => $verified['message'] ?? 'Webhook signature verification failed.',
            ]);
            http_response_code(400);
            return 'Invalid webhook signature.';
        }

        $event = $verified['event'];
        $eventId = (string) ($event['id'] ?? '');
        $type = (string) ($event['type'] ?? '');
        $intent = $event['data']['object'] ?? [];
        $paymentIntentId = (string) ($intent['id'] ?? '');

        if ($eventId === '' || $paymentIntentId === '') {
            $this->logPaymentIssue('Stripe webhook payload was missing required IDs.', [
                'event_type' => $type,
                'event_id_present' => $eventId !== '',
                'payment_intent_present' => $paymentIntentId !== '',
            ]);
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
        $result = $this->processSucceededPaymentIntent($intent, $order, 'stripe_webhook:' . $eventId);
        if (!$result['ok']) {
            $this->payments->markWebhookEvent($eventId, 'failed', $order ? (int) $order['id'] : null);
            http_response_code((int) ($result['http_status'] ?? 400));
            return (string) ($result['response'] ?? 'Payment could not be processed.');
        }

        $this->payments->markWebhookEvent($eventId, 'processed', (int) $result['order_id']);
        return json_encode(['received' => true]);
    }

    private function syncOrderFromStripe(array $order): array
    {
        if (($order['payment_status'] ?? '') === 'paid') {
            if ($this->invoiceIsPaidInWhmcs($order)) {
                $this->acceptWhmcsOrderAfterPayment($order, [
                    'id' => (string) ($order['stripe_payment_reference'] ?? $order['stripe_payment_intent_id'] ?? ''),
                ], 'local_paid_reconciliation');
                $this->clearAccountWhmcsCache((int) ($order['whmcs_client_id'] ?? 0));
                return $order;
            }

            $this->payments->markPendingForReconciliation(
                (int) $order['id'],
                'Local payment was marked paid but WHMCS invoice is still unpaid.'
            );
            $order = $this->payments->find((int) $order['id']) ?? $order;
        }

        if (($order['payment_status'] ?? '') === 'paid') {
            return $order;
        }

        $paymentIntentId = (string) ($order['stripe_payment_intent_id'] ?? '');
        if ($paymentIntentId === '') {
            return $order;
        }

        $intent = $this->stripe->paymentIntent($paymentIntentId);
        if (!($intent['ok'] ?? false) || !isset($intent['data']) || !is_array($intent['data'])) {
            return $this->payments->find((int) $order['id']) ?? $order;
        }

        $intentData = $intent['data'];
        $intentStatus = strtolower((string) ($intentData['status'] ?? ''));
        if ($intentStatus === 'succeeded') {
            $this->processSucceededPaymentIntent($intentData, $order, 'payment_status_reconciliation');
            return $this->payments->find((int) $order['id']) ?? $order;
        }

        if ($intentStatus === 'requires_payment_method') {
            $message = (string) ($intentData['last_payment_error']['message'] ?? 'Payment was not completed.');
            $this->payments->markFailedByPaymentIntent($paymentIntentId, $message);
            return $this->payments->find((int) $order['id']) ?? $order;
        }

        return $order;
    }

    private function processSucceededPaymentIntent(array $intent, ?array $order, string $source): array
    {
        if (!$order) {
            $this->logPaymentIssue('Stripe payment order was not found.', [
                'source' => $source,
                'payment_intent_id' => (string) ($intent['id'] ?? ''),
                'local_order_id' => (int) ($intent['metadata']['local_order_id'] ?? 0),
            ]);
            return ['ok' => false, 'http_status' => 400, 'response' => 'Payment order was not found.'];
        }

        if ($order['payment_status'] === 'paid') {
            if ($this->invoiceIsPaidInWhmcs($order)) {
                $this->acceptWhmcsOrderAfterPayment($order, $intent, $source);
                $this->clearAccountWhmcsCache((int) ($order['whmcs_client_id'] ?? 0));
                return ['ok' => true, 'order_id' => (int) $order['id']];
            }

            $this->payments->markPendingForReconciliation(
                (int) $order['id'],
                'Local payment was marked paid but WHMCS invoice is still unpaid.'
            );
            $order = $this->payments->find((int) $order['id']) ?? $order;
        }

        $intentStatus = strtolower((string) ($intent['status'] ?? ''));
        if ($intentStatus !== 'succeeded') {
            return ['ok' => false, 'http_status' => 202, 'response' => 'Payment is not complete yet.'];
        }

        $metadataOrderId = (int) ($intent['metadata']['local_order_id'] ?? 0);
        if ($metadataOrderId > 0 && $metadataOrderId !== (int) $order['id']) {
            $this->logPaymentIssue('Stripe payment metadata did not match the local order.', [
                'source' => $source,
                'order_id' => (int) $order['id'],
                'metadata_order_id' => $metadataOrderId,
                'payment_intent_id' => (string) ($intent['id'] ?? ''),
            ]);
            return ['ok' => false, 'http_status' => 400, 'response' => 'Payment metadata mismatch.'];
        }

        $paidAmount = $this->stripe->minorUnitsToAmount((int) ($intent['amount_received'] ?? 0), (string) ($intent['currency'] ?? $order['currency']));
        $paidCurrency = strtoupper((string) ($intent['currency'] ?? ''));
        $expectedCurrency = strtoupper((string) $order['currency']);
        if (abs($paidAmount - (float) $order['invoice_amount']) > 0.01 || $paidCurrency !== $expectedCurrency) {
            $this->logPaymentIssue('Stripe payment amount mismatch.', [
                'source' => $source,
                'order_id' => (int) $order['id'],
                'payment_intent_id' => (string) ($intent['id'] ?? ''),
                'paid_amount' => $paidAmount,
                'expected_amount' => (float) $order['invoice_amount'],
                'paid_currency' => $paidCurrency,
                'expected_currency' => $expectedCurrency,
            ]);
            $this->payments->markPendingWithError((int) $order['id'], 'Stripe paid amount did not match the saved invoice amount.');
            return ['ok' => false, 'http_status' => 400, 'response' => 'Amount mismatch.'];
        }

        $gateway = $this->whmcs->invoicePaymentGateway();
        if ($gateway === '') {
            $this->logPaymentIssue('WHMCS payment gateway name is not configured for Stripe payment recording.', [
                'source' => $source,
                'order_id' => (int) $order['id'],
                'payment_intent_id' => (string) ($intent['id'] ?? ''),
                'invoice_id' => (int) $order['whmcs_invoice_id'],
            ]);
            $this->payments->markPendingWithError((int) $order['id'], 'WHMCS payment gateway name is not configured.');
            return ['ok' => false, 'http_status' => 500, 'response' => 'Gateway not configured.'];
        }

        if (!$this->payments->markProcessing((int) $order['id'])) {
            $latestOrder = $this->payments->find((int) $order['id']) ?? $order;
            if (($latestOrder['payment_status'] ?? '') === 'paid') {
                if (!$this->invoiceIsPaidInWhmcs($latestOrder)) {
                    $this->payments->markPendingForReconciliation(
                        (int) $latestOrder['id'],
                        'Local payment was marked paid but WHMCS invoice is still unpaid.'
                    );
                    return ['ok' => false, 'http_status' => 409, 'response' => 'Payment is still being reconciled.'];
                }

                $this->clearAccountWhmcsCache((int) ($latestOrder['whmcs_client_id'] ?? 0));
                return ['ok' => true, 'order_id' => (int) $order['id']];
            }

            return ['ok' => false, 'http_status' => 409, 'response' => 'Payment is already being processed.'];
        }

        $recorded = $this->whmcs->addInvoicePayment(
            (int) $order['whmcs_invoice_id'],
            (string) $intent['id'],
            $paidAmount,
            $gateway
        );

        if (!$recorded['ok']) {
            if ($this->invoiceIsPaidInWhmcs($order)) {
                $this->payments->markPaid((int) $order['id'], (string) $intent['id']);
                $paidOrder = $this->payments->find((int) $order['id']) ?? $order;
                $this->acceptWhmcsOrderAfterPayment($paidOrder, $intent, $source);
                $this->clearAccountWhmcsCache((int) ($order['whmcs_client_id'] ?? 0));
                return ['ok' => true, 'order_id' => (int) $order['id']];
            }

            $this->logPaymentIssue('Stripe payment could not be recorded against WHMCS invoice.', [
                'source' => $source,
                'order_id' => (int) $order['id'],
                'payment_intent_id' => (string) ($intent['id'] ?? ''),
                'invoice_id' => (int) $order['whmcs_invoice_id'],
                'message' => $recorded['message'] ?? 'Unable to record invoice payment.',
            ]);
            $this->payments->markPendingForReconciliation((int) $order['id'], 'Payment reached Stripe but could not be recorded on the invoice yet.');
            return ['ok' => false, 'http_status' => 500, 'response' => 'Invoice payment could not be recorded.'];
        }

        usleep(350000);
        if (!$this->invoiceIsPaidInWhmcs($order)) {
            $this->logPaymentIssue('WHMCS accepted the payment API call, but the invoice is not marked paid yet.', [
                'source' => $source,
                'order_id' => (int) $order['id'],
                'payment_intent_id' => (string) ($intent['id'] ?? ''),
                'invoice_id' => (int) $order['whmcs_invoice_id'],
            ]);
            $this->payments->markPendingForReconciliation((int) $order['id'], 'WHMCS invoice payment is still being reconciled.');
            return ['ok' => false, 'http_status' => 202, 'response' => 'Invoice payment is still being confirmed.'];
        }

        $this->payments->markPaid((int) $order['id'], (string) $intent['id']);
        $paidOrder = $this->payments->find((int) $order['id']) ?? $order;
        $this->acceptWhmcsOrderAfterPayment($paidOrder, $intent, $source);
        $this->clearAccountWhmcsCache((int) ($order['whmcs_client_id'] ?? 0));

        return ['ok' => true, 'order_id' => (int) $order['id']];
    }

    private function acceptWhmcsOrderAfterPayment(array $order, array $intent, string $source): void
    {
        $stripeReference = (string) ($intent['id'] ?? $order['stripe_payment_reference'] ?? $order['stripe_payment_intent_id'] ?? '');
        $whmPackage = $this->whmPackageForOrder($order);
        $this->prepareLocalProvisioningRecords($order, $whmPackage, $stripeReference, $source);

        $whmcsOrderId = (int) ($order['whmcs_order_id'] ?? 0);
        if ($whmcsOrderId <= 0) {
            $this->markProvisioningIssue($order, 'domain', 'Your domain setup is being reviewed by our team.', 'The paid order has no WHMCS order ID.');
            $this->markProvisioningIssue($order, 'hosting', 'Your hosting setup is being reviewed by our team.', 'The paid order has no WHMCS order ID.');
            return;
        }

        $accepted = $this->whmcs->acceptOrder($whmcsOrderId);
        if (!$accepted['ok']) {
            $this->logPaymentIssue('WHMCS order could not be accepted automatically after payment.', [
                'source' => $source,
                'order_id' => (int) $order['id'],
                'payment_intent_id' => (string) ($intent['id'] ?? ''),
                'whmcs_order_id' => $whmcsOrderId,
                'invoice_id' => (int) $order['whmcs_invoice_id'],
                'message' => $accepted['message'] ?? 'Unable to accept WHMCS order.',
            ]);
            if ($this->orderIncludesDomain($order)) {
                $this->markProvisioningIssue($order, 'domain', 'Your domain registration is paid and our team is completing setup.', (string) ($accepted['message'] ?? 'Unable to accept WHMCS order.'));
            }
            if ($this->orderIncludesHosting($order)) {
                $this->markProvisioningIssue($order, 'hosting', 'Your hosting package is paid and our team is completing setup.', (string) ($accepted['message'] ?? 'Unable to accept WHMCS order.'));
            }
            return;
        }

        if ($this->orderIncludesDomain($order)) {
            $this->markProvisioningProcessing($order, 'domain', 'Processing');
            $this->syncWhmcsDomainAfterPayment($order, $intent, $source);
        }

        $this->provisionWhmcsOrderServicesAfterPayment($order, $intent, $source);
    }

    private function provisionWhmcsOrderServicesAfterPayment(array $order, array $intent, string $source): void
    {
        if (!$this->orderIncludesHosting($order)) {
            return;
        }

        $whmcsOrderId = (int) ($order['whmcs_order_id'] ?? 0);
        $whmcsClientId = (int) ($order['whmcs_client_id'] ?? 0);
        if ($whmcsOrderId <= 0 || $whmcsClientId <= 0) {
            return;
        }

        $services = $this->whmcs->servicesForOrder($whmcsOrderId, $whmcsClientId);
        if (!$services['ok']) {
            $this->logPaymentIssue('WHMCS services could not be loaded for provisioning after payment.', [
                'source' => $source,
                'order_id' => (int) $order['id'],
                'payment_intent_id' => (string) ($intent['id'] ?? ''),
                'whmcs_order_id' => $whmcsOrderId,
                'invoice_id' => (int) $order['whmcs_invoice_id'],
                'message' => $services['message'] ?? 'Unable to load WHMCS services.',
            ]);
            if ($this->orderIncludesHosting($order)) {
                $this->markProvisioningProcessing($order, 'hosting', 'Setup in Progress');
            }
            return;
        }

        $matchedHosting = false;
        $whmPackage = $this->whmPackageForOrder($order);
        foreach (($services['products'] ?? []) as $service) {
            $matchedHosting = true;
            $this->markHostingFromWhmcs($order, $service, $whmPackage);

            if (!$this->serviceNeedsProvisioning($service)) {
                continue;
            }

            $serviceId = $this->serviceIdFromProduct($service);
            if ($serviceId <= 0) {
                continue;
            }

            if (!$this->hostingNeedsAutomaticRetry((int) $order['id'])) {
                continue;
            }

            $this->noteProvisioningAttempt((int) $order['id'], 'hosting');
            $created = $this->whmcs->moduleCreate($serviceId);
            if (!$created['ok']) {
                $this->logPaymentIssue('WHMCS hosting service could not be provisioned automatically after payment.', [
                    'source' => $source,
                    'order_id' => (int) $order['id'],
                    'payment_intent_id' => (string) ($intent['id'] ?? ''),
                    'whmcs_order_id' => $whmcsOrderId,
                    'invoice_id' => (int) $order['whmcs_invoice_id'],
                    'service_id' => $serviceId,
                    'service_status' => (string) ($service['status'] ?? ''),
                    'domain' => (string) ($service['domain'] ?? ''),
                    'message' => $created['message'] ?? 'Unable to create hosting account.',
                ]);
                $this->markProvisioningIssue($order, 'hosting', 'Your hosting setup is in progress and our team will complete it shortly.', (string) ($created['message'] ?? 'Unable to create hosting account.'));
                continue;
            }

            $this->markHostingProvisioned($order, $serviceId, $whmPackage);
            $this->logPaymentIssue('WHMCS hosting service provisioning was requested after payment.', [
                'source' => $source,
                'order_id' => (int) $order['id'],
                'payment_intent_id' => (string) ($intent['id'] ?? ''),
                'whmcs_order_id' => $whmcsOrderId,
                'invoice_id' => (int) $order['whmcs_invoice_id'],
                'service_id' => $serviceId,
                'domain' => (string) ($service['domain'] ?? ''),
            ]);
        }

        if (!$matchedHosting && $this->orderIncludesHosting($order)) {
            $this->markProvisioningIssue($order, 'hosting', 'Your hosting setup is being reviewed by our team.', 'No WHMCS hosting service was returned for the paid order.');
            $this->logPaymentIssue('No WHMCS hosting services matched the paid order.', [
                'source' => $source,
                'order_id' => (int) $order['id'],
                'payment_intent_id' => (string) ($intent['id'] ?? ''),
                'whmcs_order_id' => $whmcsOrderId,
                'invoice_id' => (int) $order['whmcs_invoice_id'],
            ]);
        }
    }

    private function serviceIdFromProduct(array $service): int
    {
        foreach (['id', 'serviceid', 'service_id', 'hostingid', 'relid'] as $key) {
            $id = (int) ($service[$key] ?? 0);
            if ($id > 0) {
                return $id;
            }
        }

        return 0;
    }

    private function serviceNeedsProvisioning(array $service): bool
    {
        $status = strtolower((string) ($service['status'] ?? ''));
        if (in_array($status, ['cancelled', 'canceled', 'terminated', 'fraud'], true)) {
            return false;
        }

        if ($status === 'active') {
            return false;
        }

        return trim((string) ($service['username'] ?? '')) === '';
    }

    private function prepareLocalProvisioningRecords(array $order, string $whmPackage, string $stripeReference, string $source): void
    {
        try {
            $this->provisioning->ensureOrderRecords($order, $whmPackage);
            $this->provisioning->markPaymentConfirmed($order, $stripeReference);
        } catch (\Throwable $exception) {
            $this->logPaymentIssue('Local provisioning records could not be updated after verified payment.', [
                'source' => $source,
                'order_id' => (int) ($order['id'] ?? 0),
                'whmcs_order_id' => (int) ($order['whmcs_order_id'] ?? 0),
                'invoice_id' => (int) ($order['whmcs_invoice_id'] ?? 0),
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function syncWhmcsDomainAfterPayment(array $order, array $intent, string $source): void
    {
        $domainName = strtolower(trim((string) ($order['selected_domain'] ?? '')));
        $clientId = (int) ($order['whmcs_client_id'] ?? 0);
        if ($domainName === '' || $clientId <= 0) {
            return;
        }

        $this->noteProvisioningAttempt((int) $order['id'], 'domain');
        $domains = $this->whmcs->domainsForClient($clientId);
        if (!$domains['ok']) {
            $this->markProvisioningProcessing($order, 'domain', 'Processing');
            $this->logPaymentIssue('WHMCS domains could not be loaded after paid domain order.', [
                'source' => $source,
                'order_id' => (int) $order['id'],
                'payment_intent_id' => (string) ($intent['id'] ?? ''),
                'whmcs_order_id' => (int) ($order['whmcs_order_id'] ?? 0),
                'invoice_id' => (int) ($order['whmcs_invoice_id'] ?? 0),
                'domain' => $domainName,
                'message' => $domains['message'] ?? 'Unable to load WHMCS domains.',
            ]);
            return;
        }

        foreach (($domains['domains'] ?? []) as $domain) {
            $candidate = strtolower(trim((string) ($domain['domainname'] ?? $domain['domain'] ?? '')));
            if ($candidate !== $domainName) {
                continue;
            }

            $nameservers = [];
            $domainId = (int) ($domain['id'] ?? $domain['domainid'] ?? 0);
            if ($domainId > 0) {
                $loadedNameservers = $this->whmcs->nameserversForDomain($domainId);
                if (!empty($loadedNameservers['ok'])) {
                    $nameservers = array_values(array_filter(array_map('strval', $loadedNameservers['nameservers'] ?? [])));
                }
            }

            try {
                $this->provisioning->markDomainFromWhmcs($order, $domain, $nameservers);
            } catch (\Throwable $exception) {
                $this->logPaymentIssue('Local domain provisioning status could not be saved.', [
                    'source' => $source,
                    'order_id' => (int) $order['id'],
                    'domain' => $domainName,
                    'type' => get_class($exception),
                    'message' => $exception->getMessage(),
                ]);
            }
            return;
        }

        $this->markProvisioningProcessing($order, 'domain', 'Processing');
        $this->logPaymentIssue('Paid domain order was accepted, but the domain is not visible in WHMCS yet.', [
            'source' => $source,
            'order_id' => (int) $order['id'],
            'payment_intent_id' => (string) ($intent['id'] ?? ''),
            'whmcs_order_id' => (int) ($order['whmcs_order_id'] ?? 0),
            'invoice_id' => (int) ($order['whmcs_invoice_id'] ?? 0),
            'domain' => $domainName,
        ]);
    }

    private function markHostingFromWhmcs(array $order, array $service, string $whmPackage): void
    {
        try {
            $this->provisioning->markHostingFromWhmcs($order, $service, $whmPackage);
        } catch (\Throwable $exception) {
            $this->logPaymentIssue('Local hosting provisioning status could not be saved.', [
                'order_id' => (int) ($order['id'] ?? 0),
                'service_id' => $this->serviceIdFromProduct($service),
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function markHostingProvisioned(array $order, int $serviceId, string $whmPackage): void
    {
        try {
            $this->provisioning->markHostingProvisioned($order, $serviceId, $whmPackage);
        } catch (\Throwable $exception) {
            $this->logPaymentIssue('Local hosting provisioned status could not be saved.', [
                'order_id' => (int) ($order['id'] ?? 0),
                'service_id' => $serviceId,
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function markProvisioningIssue(array $order, string $itemType, string $publicMessage, string $internalMessage): void
    {
        if (!$this->orderHasItemType($order, $itemType)) {
            return;
        }

        try {
            $this->provisioning->markItemIssue($order, $itemType, $publicMessage, $internalMessage);
        } catch (\Throwable $exception) {
            $this->logPaymentIssue('Local provisioning issue could not be saved.', [
                'order_id' => (int) ($order['id'] ?? 0),
                'item_type' => $itemType,
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function markProvisioningProcessing(array $order, string $itemType, string $statusLabel): void
    {
        if (!$this->orderHasItemType($order, $itemType)) {
            return;
        }

        try {
            $this->provisioning->markItemProcessing($order, $itemType, $statusLabel);
        } catch (\Throwable $exception) {
            $this->logPaymentIssue('Local provisioning processing status could not be saved.', [
                'order_id' => (int) ($order['id'] ?? 0),
                'item_type' => $itemType,
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function noteProvisioningAttempt(int $orderId, string $itemType): void
    {
        try {
            $this->provisioning->noteProvisioningAttempt($orderId, $itemType);
        } catch (\Throwable $exception) {
            $this->logPaymentIssue('Local provisioning attempt could not be recorded.', [
                'order_id' => $orderId,
                'item_type' => $itemType,
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
        }
    }

    private function hostingNeedsAutomaticRetry(int $orderId): bool
    {
        try {
            return $this->provisioning->itemNeedsAutomaticRetry($orderId, 'hosting');
        } catch (\Throwable $exception) {
            $this->logPaymentIssue('Local hosting retry state could not be checked.', [
                'order_id' => $orderId,
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
            return true;
        }
    }

    private function orderHasItemType(array $order, string $itemType): bool
    {
        return $itemType === 'domain' ? $this->orderIncludesDomain($order) : $this->orderIncludesHosting($order);
    }

    private function orderIncludesDomain(array $order): bool
    {
        $type = strtolower(trim((string) ($order['order_type'] ?? '')));
        $domain = trim((string) ($order['selected_domain'] ?? ''));
        if ($domain === '') {
            return false;
        }

        if (in_array($type, ['domain', 'bundle'], true)) {
            return true;
        }

        if ($type === 'website') {
            return !empty($this->whmcs->checkoutConfig()['website_package']['register_domain']);
        }

        return false;
    }

    private function orderIncludesHosting(array $order): bool
    {
        $type = strtolower(trim((string) ($order['order_type'] ?? '')));
        return in_array($type, ['hosting', 'bundle'], true);
    }

    private function whmPackageForOrder(array $order): string
    {
        $existing = trim((string) ($order['whm_package'] ?? ''));
        if ($existing !== '') {
            return $existing;
        }

        $slug = trim((string) ($order['hosting_plan_slug'] ?? ''));
        $config = $this->whmcs->checkoutConfig()['hosting_products'] ?? [];
        if ($slug !== '' && isset($config[$slug]['whm_package'])) {
            return (string) $config[$slug]['whm_package'];
        }

        $haystack = strtolower($slug . ' ' . (string) ($order['package_label'] ?? ''));
        if (str_contains($haystack, 'starter')) {
            return 'planetic_starter';
        }
        if (str_contains($haystack, 'business')) {
            return 'planetic_business';
        }
        if (str_contains($haystack, 'agency') || str_contains($haystack, 'ecommerce') || str_contains($haystack, 'commerce') || str_contains($haystack, 'reseller')) {
            return 'planetic_agency';
        }
        if (str_contains($haystack, 'pro') || str_contains($haystack, 'wordpress')) {
            return 'planetic_pro';
        }

        return '';
    }

    private function clearAccountWhmcsCache(int $clientId): void
    {
        if ($clientId <= 0) {
            return;
        }

        $dir = STORAGE_PATH . '/cache/account-whmcs';
        foreach (['invoices', 'products', 'domains'] as $key) {
            $file = $dir . '/' . sha1($clientId . ':' . $key) . '.json';
            if (is_file($file)) {
                @unlink($file);
            }
        }
    }

    private function invoiceIsPaidInWhmcs(array $order): bool
    {
        $invoice = $this->whmcs->invoiceForClient(
            (int) $order['whmcs_invoice_id'],
            (int) $order['whmcs_client_id'],
            (int) ($order['whmcs_order_id'] ?? 0)
        );

        if (!($invoice['ok'] ?? false) || !isset($invoice['invoice']) || !is_array($invoice['invoice'])) {
            return false;
        }

        return strtolower((string) ($invoice['invoice']['status'] ?? '')) === 'paid';
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

    private function logPaymentIssue(string $message, array $context = []): void
    {
        $dir = STORAGE_PATH . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        unset($context['secret_key'], $context['webhook_secret'], $context['client_secret'], $context['password']);
        @file_put_contents(
            $dir . '/payment.log',
            '[' . date('c') . '] ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );
    }
}
