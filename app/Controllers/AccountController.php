<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\CustomerAuth;
use App\Core\RateLimiter;
use App\Models\ContentRepository;
use App\Models\CustomerRepository;
use App\Models\PaymentRepository;
use App\Services\Mailer;
use App\Services\WhmcsService;

final class AccountController extends Controller
{
    private ContentRepository $content;
    private CustomerRepository $customers;
    private PaymentRepository $payments;
    private CustomerAuth $auth;
    private WhmcsService $whmcs;
    private array $settings;

    public function __construct()
    {
        $this->content = new ContentRepository();
        $this->customers = new CustomerRepository();
        $this->payments = new PaymentRepository();
        $this->auth = new CustomerAuth();
        $this->settings = $this->content->settings();
        $this->whmcs = new WhmcsService($this->settings);
    }

    public function login(array $errors = [], array $old = []): string
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' && $this->auth->check()) {
            $next = $this->safeNext((string) ($_GET['next'] ?? $old['next'] ?? ''));
            $this->redirect($next ?: url('/account/dashboard'));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                return $this->loginForm(['Your security token expired. Please try again.'], $_POST);
            }

            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $identifier = ($_SERVER['REMOTE_ADDR'] ?? 'local') . ':' . $email;
            if (RateLimiter::tooManyAttempts('customer_login', $identifier, 6, 900)) {
                return $this->loginForm(['Too many login attempts. Please wait a few minutes and try again.'], $_POST);
            }

            $existingCustomer = filter_var($email, FILTER_VALIDATE_EMAIL) ? $this->customers->findByEmail($email) : null;
            if ($existingCustomer) {
                $hashSafety = CustomerAuth::passwordHashSafety((string) ($existingCustomer['password_hash'] ?? ''));
                if (!$hashSafety['safe']) {
                    $this->logAccountIssue('Customer login blocked because password hash needs resetting.', [
                        'email' => $email,
                        'reason' => $hashSafety['reason'],
                    ]);
                    RateLimiter::hit('customer_login', $identifier, 900);

                    return $this->loginForm(['Please reset your password before logging in.'], $_POST);
                }
            }

            try {
                if ($this->auth->attempt($email, (string) ($_POST['password'] ?? ''))) {
                    RateLimiter::clear('customer_login', $identifier);
                    $next = $this->safeNext((string) ($_POST['next'] ?? $_GET['next'] ?? ''));
                    $this->redirect($next ?: url('/account/dashboard'));
                }
            } catch (\Throwable $exception) {
                $this->logAccountIssue('Customer login failed.', [
                    'email' => $email,
                    'type' => get_class($exception),
                    'message' => $exception->getMessage(),
                ]);

                return $this->loginForm(['Login is temporarily unavailable. Please try again shortly.'], $_POST);
            }

            RateLimiter::hit('customer_login', $identifier, 900);
            $next = $this->safeNext((string) ($_POST['next'] ?? $_GET['next'] ?? ''));
            if ((string) ($_POST['context'] ?? '') === 'checkout' && str_starts_with($next, '/checkout')) {
                $this->redirect($this->withQueryFlag($next, 'login_error', '1'));
            }

            return $this->loginForm(['Email or password is incorrect.'], $_POST);
        }

        return $this->loginForm($errors, $old);
    }

    public function register(array $errors = [], array $old = []): string
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                return $this->registerForm(['Your security token expired. Please try again.'], $_POST);
            }

            [$data, $validation] = $this->validateRegistration($_POST);
            if ($validation) {
                return $this->registerForm($validation, $data);
            }

            $identifier = ($_SERVER['REMOTE_ADDR'] ?? 'local') . ':' . $data['email'];
            if (RateLimiter::tooManyAttempts('customer_register', $identifier, 5, 900)) {
                return $this->registerForm(['Too many registration attempts. Please wait a few minutes and try again.'], $data);
            }
            RateLimiter::hit('customer_register', $identifier, 900);

            if ($this->customers->findByEmail($data['email'])) {
                return $this->registerForm(['An account already exists for this email. Please log in instead.'], $data);
            }

            $user = $this->customers->create($data);
            $this->auth->login($user);
            RateLimiter::clear('customer_register', $identifier);
            $this->redirect(url('/account/dashboard'));
        }

        return $this->registerForm($errors, $old);
    }

    public function logout(): string
    {
        $this->auth->logout();
        $this->redirect(url('/account/login'));
    }

    public function passwordReset(array $errors = [], bool $sent = false): string
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                return $this->passwordResetRequestForm(['Your security token expired. Please try again.']);
            }

            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $identifier = ($_SERVER['REMOTE_ADDR'] ?? 'local') . ':' . $email;
            if (RateLimiter::tooManyAttempts('password_reset', $identifier, 5, 900)) {
                return $this->passwordResetRequestForm(['Too many reset requests. Please wait a few minutes and try again.']);
            }
            RateLimiter::hit('password_reset', $identifier, 900);

            $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? $this->customers->findByEmail($email) : null;
            if ($user) {
                $token = bin2hex(random_bytes(32));
                $this->customers->createPasswordReset((int) $user['id'], $token);
                Mailer::customerPasswordReset($this->settings, $user, rtrim((string) ($this->settings['app_url'] ?? env('APP_URL', '')), '/') . url('/account/password-reset/' . $token));
            }

            return $this->passwordResetRequestForm([], true);
        }

        return $this->passwordResetRequestForm($errors, $sent);
    }

    public function passwordResetForm(string $token, array $errors = []): string
    {
        $reset = $this->customers->resetByToken($token);
        if (!$reset) {
            return $this->passwordResetTokenView($token, ['This password reset link is invalid or expired.'], false);
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                return $this->passwordResetTokenView($token, ['Your security token expired. Please try again.'], true);
            }

            $password = (string) ($_POST['password'] ?? '');
            if (strlen($password) < 8) {
                return $this->passwordResetTokenView($token, ['Password must be at least 8 characters.'], true);
            }
            if ($password !== (string) ($_POST['password_confirmation'] ?? '')) {
                return $this->passwordResetTokenView($token, ['Password confirmation does not match.'], true);
            }

            $this->customers->updatePasswordFromReset((int) $reset['id'], (int) $reset['customer_user_id'], $password);
            $this->redirect(url('/account/login'));
        }

        return $this->passwordResetTokenView($token, $errors, true);
    }

    private function loginForm(array $errors = [], array $old = []): string
    {
        return $this->render('account/login', $this->baseData('account-login', [
            'errors' => $errors,
            'old' => $old,
            'next' => $this->safeNext((string) ($_GET['next'] ?? $old['next'] ?? '')),
        ]));
    }

    private function registerForm(array $errors = [], array $old = []): string
    {
        return $this->render('account/register', $this->baseData('account-register', [
            'errors' => $errors,
            'old' => $old,
            'countries' => $this->countries(),
        ]));
    }

    private function passwordResetRequestForm(array $errors = [], bool $sent = false): string
    {
        return $this->render('account/password-reset', $this->baseData('account-password-reset', [
            'errors' => $errors,
            'sent' => $sent,
        ]));
    }

    private function passwordResetTokenView(string $token, array $errors = [], bool $valid = true): string
    {
        return $this->render('account/password-reset-form', $this->baseData('account-password-reset', [
            'errors' => $errors,
            'token' => $token,
            'valid' => $valid,
        ]));
    }

    private function profileView(array $account, array $errors = [], bool $saved = false): string
    {
        return $this->render('account/profile', $this->baseData('account-profile', [
            'account' => $account,
            'countries' => $this->countries(),
            'errors' => $errors,
            'saved' => $saved,
        ]));
    }

    public function dashboard(): string
    {
        $user = $this->requireCustomer();
        $account = $this->fullUser($user);
        $services = $this->clientServices($account, false);
        $invoices = $this->clientInvoices($account, false);
        $recentPayments = $this->accountPaymentOrders($account, 3);

        return $this->render('account/dashboard', $this->baseData('account-dashboard', [
            'account' => $account,
            'services' => $services,
            'invoices' => $invoices,
            'recentPayments' => $recentPayments,
            'activeCount' => count(array_filter($services, static fn (array $service): bool => strtolower((string) ($service['status'] ?? '')) === 'active')),
            'unpaidCount' => count(array_filter($invoices, static fn (array $invoice): bool => in_array(strtolower((string) ($invoice['status'] ?? '')), ['unpaid', 'payment pending'], true))),
            'nextDueDate' => $this->nextDueDate($services),
        ]));
    }

    public function services(): string
    {
        $account = $this->fullUser($this->requireCustomer());

        return $this->render('account/services', $this->baseData('account-services', [
            'account' => $account,
            'services' => $this->clientServices($account),
            'domains' => array_map([$this, 'accountDomain'], $this->clientDomains($account)),
        ]));
    }

    public function billing(): string
    {
        $account = $this->fullUser($this->requireCustomer());

        return $this->render('account/billing', $this->baseData('account-billing', [
            'account' => $account,
            'invoices' => $this->clientInvoices($account),
            'payments' => $this->accountPaymentOrders($account, 10),
        ]));
    }

    public function dns(): string
    {
        $account = $this->fullUser($this->requireCustomer());

        return $this->render('account/dns', $this->baseData('account-dns', [
            'account' => $account,
            'domains' => array_map([$this, 'accountDomain'], $this->clientDomains($account)),
        ]));
    }

    public function openDns(): string
    {
        $this->requireCustomer();
        $this->redirect(url('/account/dns'));
    }

    public function manageDns(string $domainId): string
    {
        $account = $this->fullUser($this->requireCustomer());
        $domain = $this->ownedDomain($account, (int) $domainId);
        if (!$domain) {
            $this->redirect(url('/account/dns'));
        }

        $errors = [];
        $saved = false;
        $nameservers = $this->blankNameservers();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                $errors[] = 'Your security token expired. Please try again.';
            } else {
                [$nameservers, $errors] = $this->validateNameservers($_POST);
                if (!$errors) {
                    $updated = $this->whmcs->updateDomainNameservers((int) $domainId, $nameservers);
                    if (!empty($updated['ok'])) {
                        $saved = true;
                        $this->clearCachedWhmcsRead((int) ($account['whmcs_client_id'] ?? 0), 'domains');
                    } else {
                        $errors[] = $this->publicDnsMessage((string) ($updated['message'] ?? ''));
                    }
                }
            }
        } else {
            $loaded = $this->whmcs->nameserversForDomain((int) $domainId);
            if (!empty($loaded['ok'])) {
                $nameservers = array_replace($nameservers, $loaded['nameservers'] ?? []);
            } else {
                $errors[] = $this->publicDnsMessage((string) ($loaded['message'] ?? ''));
            }
        }

        return $this->render('account/dns-manage', $this->baseData('account-dns', [
            'account' => $account,
            'domain' => $this->accountDomain($domain),
            'nameservers' => $nameservers,
            'errors' => $errors,
            'saved' => $saved,
        ]));
    }

    public function profile(array $errors = [], bool $saved = false): string
    {
        $account = $this->fullUser($this->requireCustomer());

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                return $this->profileView($account, ['Your security token expired. Please try again.'], false);
            }

            [$data, $validation] = $this->validateProfile($_POST, (int) $account['id']);
            if ($validation) {
                return $this->profileView(array_merge($account, $data), $validation, false);
            }

            $updated = $this->customers->updateProfile((int) $account['id'], $data);
            if ($updated && !empty($updated['whmcs_client_id'])) {
                $this->whmcs->updateClient((int) $updated['whmcs_client_id'], [
                    'firstname' => $updated['first_name'],
                    'lastname' => $updated['last_name'],
                    'companyname' => $updated['company_name'] ?? '',
                    'email' => $updated['email'],
                    'phonenumber' => $updated['phone'] ?? '',
                    'address1' => $updated['address'] ?? '',
                    'city' => $updated['city'] ?? '',
                    'state' => $updated['state'] ?? '',
                    'postcode' => $updated['postcode'] ?? '',
                    'country' => $updated['country'] ?? 'GB',
                ]);
            }

            return $this->profileView($updated ?: $account, [], true);
        }

        return $this->profileView($account, $errors, $saved);
    }

    private function clientServices(array $account, bool $allowLiveLoad = true): array
    {
        $services = [];
        if (!empty($account['whmcs_client_id'])) {
            $clientId = (int) $account['whmcs_client_id'];
            $result = $this->cachedWhmcsRead($clientId, 'products', fn (): array => $this->whmcs->productsForClient($clientId), $allowLiveLoad);
            $services = $result['ok'] ? array_map([$this, 'friendlyService'], $result['products']) : [];
        }

        return $this->mergeLocalServices($account, $services);
    }

    private function clientDomains(array $account, bool $allowLiveLoad = true): array
    {
        if (empty($account['whmcs_client_id'])) {
            return [];
        }

        $clientId = (int) $account['whmcs_client_id'];
        $result = $this->cachedWhmcsRead($clientId, 'domains', fn (): array => $this->whmcs->domainsForClient($clientId), $allowLiveLoad);
        return $result['ok'] ? $result['domains'] : [];
    }

    private function clientInvoices(array $account, bool $allowLiveLoad = true): array
    {
        $invoices = [];
        if (!empty($account['whmcs_client_id'])) {
            $clientId = (int) $account['whmcs_client_id'];
            $result = $this->cachedWhmcsRead($clientId, 'invoices', fn (): array => $this->whmcs->invoicesForClient($clientId), $allowLiveLoad);
            $invoices = $result['ok'] ? $result['invoices'] : [];
        }

        return $this->mergeLocalInvoices($account, $invoices);
    }

    private function mergeLocalInvoices(array $account, array $invoices): array
    {
        $merged = [];
        foreach ($invoices as $invoice) {
            $invoiceId = (int) ($invoice['id'] ?? $invoice['invoiceid'] ?? 0);
            if ($invoiceId > 0) {
                $merged[$invoiceId] = $invoice;
            }
        }

        foreach ($this->accountPaymentOrders($account, 200) as $order) {
            $invoiceId = (int) ($order['whmcs_invoice_id'] ?? 0);
            if ($invoiceId <= 0) {
                continue;
            }

            $localInvoice = $this->localInvoiceRow($order);
            if (!isset($merged[$invoiceId])) {
                $merged[$invoiceId] = $localInvoice;
                continue;
            }

            $amount = $this->accountMoneyToFloat($order['invoice_amount'] ?? '0.00');
            $existing = $merged[$invoiceId];
            $existingAmount = max(
                $this->accountMoneyToFloat($existing['total'] ?? '0.00'),
                $this->accountMoneyToFloat($existing['balance'] ?? '0.00')
            );

            if ($existingAmount <= 0.0 && $amount > 0.0) {
                $existing['total'] = number_format($amount, 2, '.', '');
            }

            if ((string) ($order['payment_status'] ?? '') === 'paid') {
                $existing['status'] = 'Paid';
                $existing['balance'] = '0.00';
                $existing['_local_paid'] = true;
            }

            $existing['_local_sort_at'] = $localInvoice['_local_sort_at'];
            $existing['_local_order_id'] = $localInvoice['_local_order_id'];
            $existing['_local_payment_status'] = $localInvoice['_local_payment_status'];
            $merged[$invoiceId] = $existing;
        }

        $rows = array_values($merged);
        usort($rows, function (array $left, array $right): int {
            $leftId = (int) ($left['id'] ?? $left['invoiceid'] ?? 0);
            $rightId = (int) ($right['id'] ?? $right['invoiceid'] ?? 0);
            if ($leftId !== $rightId) {
                return $rightId <=> $leftId;
            }

            return strcmp((string) ($right['_local_sort_at'] ?? $right['date'] ?? ''), (string) ($left['_local_sort_at'] ?? $left['date'] ?? ''));
        });

        return $rows;
    }

    private function localInvoiceRow(array $order): array
    {
        $invoiceId = (int) ($order['whmcs_invoice_id'] ?? 0);
        $status = match ((string) ($order['payment_status'] ?? 'pending')) {
            'paid' => 'Paid',
            'failed' => 'Payment Failed',
            'processing' => 'Pending Payment',
            default => 'Unpaid',
        };

        $amount = number_format($this->accountMoneyToFloat($order['invoice_amount'] ?? '0.00'), 2, '.', '');
        $date = substr((string) ($order['created_at'] ?? $order['paid_at'] ?? date('Y-m-d')), 0, 10);

        return [
            'id' => $invoiceId,
            'invoiceid' => $invoiceId,
            'date' => $date,
            'duedate' => $date,
            'total' => $amount,
            'balance' => $status === 'Paid' ? '0.00' : $amount,
            'status' => $status,
            '_local_order_id' => (int) ($order['id'] ?? 0),
            '_local_payment_status' => (string) ($order['payment_status'] ?? 'pending'),
            '_local_sort_at' => (string) ($order['paid_at'] ?? $order['updated_at'] ?? $order['created_at'] ?? ''),
        ];
    }

    private function mergeLocalServices(array $account, array $services): array
    {
        $existingKeys = [];
        foreach ($services as $service) {
            $existingKeys[] = strtolower(trim((string) ($service['name'] ?? $service['productname'] ?? ''))) . ':' . strtolower(trim((string) ($service['domain'] ?? '')));
        }

        $websitePrice = $this->configuredAccountWebsitePrice();
        $localServices = [];
        foreach ($this->accountPaymentOrders($account, 200) as $order) {
            $paymentStatus = (string) ($order['payment_status'] ?? 'pending');
            if (!in_array($paymentStatus, ['paid', 'processing', 'pending'], true)) {
                continue;
            }

            $type = $this->localOrderType($order, $websitePrice);
            if ($type === '' || $type === 'domain') {
                continue;
            }

            $domain = trim((string) ($order['selected_domain'] ?? ''));
            if ($domain === '') {
                $invoiceId = (int) ($order['whmcs_invoice_id'] ?? 0);
                $domain = $type === 'website'
                    ? ($invoiceId > 0 ? 'Website package invoice #' . $invoiceId : 'Website package order')
                    : 'Hosting service';
            }

            $name = $this->localOrderServiceName($order, $type);
            $key = strtolower($name) . ':' . strtolower($domain);
            if (in_array($key, $existingKeys, true)) {
                continue;
            }
            $existingKeys[] = $key;

            $amount = $this->accountMoneyToFloat($order['invoice_amount'] ?? '0.00');
            $cycle = $this->localOrderBillingCycle($order);
            $createdAt = substr((string) ($order['created_at'] ?? ''), 0, 10) ?: 'Not available';
            $status = match ($paymentStatus) {
                'paid' => $type === 'website' ? 'paid' : 'active',
                'processing' => 'processing',
                default => 'pending',
            };
            $friendly = match ($paymentStatus) {
                'paid' => $type === 'website' ? 'Paid' : 'Active',
                'processing' => 'Processing',
                default => 'Pending Payment',
            };

            $localServices[] = [
                'id' => 'local-order-' . (int) ($order['id'] ?? 0),
                'name' => $name,
                'productname' => $name,
                'domain' => $domain,
                'status' => $status,
                'friendly_status' => $friendly,
                'billingcycle' => $cycle,
                'regdate' => $createdAt,
                'registrationdate' => $createdAt,
                'nextduedate' => $this->localOrderNextDueDate($order, $cycle),
                'recurringamount' => $type === 'website' ? 0.0 : $amount,
                'amount' => $amount,
                '_local_order_id' => (int) ($order['id'] ?? 0),
            ];
        }

        return array_merge($localServices, $services);
    }

    private function localOrderType(array $order, float $websitePrice): string
    {
        $type = strtolower(trim((string) ($order['order_type'] ?? '')));
        if ($type !== '') {
            return $type;
        }

        $amount = $this->accountMoneyToFloat($order['invoice_amount'] ?? '0.00');
        if ($websitePrice > 0.0 && abs($amount - $websitePrice) <= 0.01) {
            return 'website';
        }

        if (trim((string) ($order['hosting_plan_slug'] ?? '')) !== '') {
            return 'hosting';
        }

        return '';
    }

    private function localOrderServiceName(array $order, string $type): string
    {
        $label = trim((string) ($order['package_label'] ?? ''));
        if ($label !== '' && !in_array($label, ['Domain Registration', 'Domain + Hosting'], true)) {
            return $label;
        }

        if ($type === 'website') {
            return 'Bespoke Website Development';
        }

        if ($type === 'bundle') {
            return 'Domain + ' . $this->hostingPlanTitleFromSlug((string) ($order['hosting_plan_slug'] ?? ''));
        }

        return $this->hostingPlanTitleFromSlug((string) ($order['hosting_plan_slug'] ?? ''));
    }

    private function hostingPlanTitleFromSlug(string $slug): string
    {
        $slug = trim($slug);
        if ($slug !== '') {
            foreach ($this->content->hostingPlans(null, true) as $plan) {
                if ((string) ($plan['slug'] ?? '') === $slug) {
                    return (string) ($plan['title'] ?? $slug);
                }
            }

            return ucwords(str_replace('-', ' ', $slug));
        }

        return 'Hosting Package';
    }

    private function localOrderBillingCycle(array $order): string
    {
        $type = strtolower(trim((string) ($order['order_type'] ?? '')));
        $cycle = strtolower(trim((string) ($order['billing_cycle'] ?? '')));
        if ($type === 'website') {
            return 'One-time';
        }

        if (in_array($cycle, ['annually', 'yearly', 'annual'], true)) {
            return 'Yearly';
        }

        if ($cycle === 'monthly') {
            return 'Monthly';
        }

        return $cycle !== '' ? ucwords(str_replace('-', ' ', $cycle)) : 'Monthly';
    }

    private function localOrderNextDueDate(array $order, string $cycle): string
    {
        if ($cycle === 'One-time') {
            return 'Not applicable';
        }

        $base = (string) ($order['paid_at'] ?? $order['created_at'] ?? '');
        if ($base === '') {
            return 'Not available';
        }

        try {
            $date = new \DateTimeImmutable(substr($base, 0, 10));
            return $date->add($cycle === 'Yearly' ? new \DateInterval('P1Y') : new \DateInterval('P1M'))->format('Y-m-d');
        } catch (\Throwable $exception) {
            return 'Not available';
        }
    }

    private function accountPaymentOrders(array $account, int $limit): array
    {
        try {
            $customerId = isset($account['id']) ? (int) $account['id'] : null;
            $whmcsClientId = isset($account['whmcs_client_id']) ? (int) $account['whmcs_client_id'] : null;

            return $this->payments->forAccount($customerId, $whmcsClientId, $limit);
        } catch (\Throwable $exception) {
            $this->logAccountIssue('Customer payment history could not be loaded.', [
                'customer_id' => (int) ($account['id'] ?? 0),
                'whmcs_client_id' => (int) ($account['whmcs_client_id'] ?? 0),
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function configuredAccountWebsitePrice(): float
    {
        $package = $this->content->package();
        return $this->accountMoneyToFloat($package['price'] ?? '199.00');
    }

    private function accountMoneyToFloat(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return round((float) $value, 2);
        }

        if (!is_string($value)) {
            return 0.0;
        }

        $clean = preg_replace('/[^0-9.,-]+/', '', $value) ?: '';
        if ($clean === '' || $clean === '-' || $clean === '.' || $clean === ',') {
            return 0.0;
        }

        if (str_contains($clean, ',') && !str_contains($clean, '.')) {
            $clean = str_replace(',', '.', $clean);
        } else {
            $clean = str_replace(',', '', $clean);
        }

        return is_numeric($clean) ? round((float) $clean, 2) : 0.0;
    }

    private function accountDomain(array $domain): array
    {
        $domainId = (int) ($domain['id'] ?? $domain['domainid'] ?? 0);
        $domain['management_url'] = $this->whmcs->domainManagementUrl($domainId);
        $domain['dns_url'] = $domainId > 0 ? url('/account/dns/' . $domainId) : url('/account/dns');
        return $domain;
    }

    private function ownedDomain(array $account, int $domainId): ?array
    {
        if ($domainId <= 0) {
            return null;
        }

        foreach ($this->clientDomains($account) as $domain) {
            $candidateId = (int) ($domain['id'] ?? $domain['domainid'] ?? 0);
            if ($candidateId === $domainId) {
                return $domain;
            }
        }

        return null;
    }

    private function blankNameservers(): array
    {
        return [1 => '', 2 => '', 3 => '', 4 => '', 5 => ''];
    }

    private function validateNameservers(array $input): array
    {
        $nameservers = $this->blankNameservers();
        $errors = [];

        for ($index = 1; $index <= 5; $index++) {
            $value = strtolower(trim((string) ($input['ns' . $index] ?? '')));
            $value = rtrim($value, '.');
            $nameservers[$index] = $value;

            if ($value === '') {
                continue;
            }

            if (!$this->isValidNameserver($value)) {
                $errors[] = 'Nameserver ' . $index . ' must be a valid hostname, for example ns1.example.com.';
            }
        }

        if ($nameservers[1] === '' || $nameservers[2] === '') {
            $errors[] = 'Nameserver 1 and nameserver 2 are required.';
        }

        return [$nameservers, array_values(array_unique($errors))];
    }

    private function isValidNameserver(string $hostname): bool
    {
        if (strlen($hostname) > 253 || !str_contains($hostname, '.')) {
            return false;
        }

        return (bool) preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $hostname);
    }

    private function publicDnsMessage(string $message): string
    {
        $lower = strtolower($message);
        if (str_contains($lower, 'bridge action is not allowed')) {
            return 'DNS management needs the latest WHMCS bridge file uploaded to the client area folder.';
        }

        if (str_contains($lower, 'not found')) {
            return 'This domain could not be found in your account.';
        }

        return 'DNS settings could not be updated right now. Please try again shortly or contact support.';
    }

    private function cachedWhmcsRead(int $clientId, string $key, callable $loader, bool $allowLiveLoad = true): array
    {
        $cacheDir = STORAGE_PATH . '/cache/account-whmcs';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }

        $file = $cacheDir . '/' . sha1($clientId . ':' . $key) . '.json';
        $cached = is_file($file) ? json_decode((string) @file_get_contents($file), true) : null;
        if (is_array($cached) && (int) ($cached['expires_at'] ?? 0) > time() && isset($cached['payload']) && is_array($cached['payload'])) {
            return $cached['payload'];
        }

        if (!$allowLiveLoad) {
            return ['ok' => false, $key => []];
        }

        try {
            $payload = $loader();
        } catch (\Throwable $exception) {
            $this->logAccountIssue('WHMCS account read failed.', [
                'client_id' => $clientId,
                'key' => $key,
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
            $payload = ['ok' => false, $key => []];
        }

        $ttl = !empty($payload['ok']) ? 120 : 45;
        @file_put_contents($file, json_encode([
            'expires_at' => time() + $ttl,
            'payload' => $payload,
        ], JSON_UNESCAPED_SLASHES));

        return $payload;
    }

    private function clearCachedWhmcsRead(int $clientId, string $key): void
    {
        if ($clientId <= 0) {
            return;
        }

        $file = STORAGE_PATH . '/cache/account-whmcs/' . sha1($clientId . ':' . $key) . '.json';
        if (is_file($file)) {
            @unlink($file);
        }
    }

    private function safeRecentPayments(int $customerId, int $limit): array
    {
        try {
            return $this->payments->recentForCustomer($customerId, $limit);
        } catch (\Throwable $exception) {
            $this->logAccountIssue('Customer payment history could not be loaded.', [
                'customer_id' => $customerId,
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function logAccountIssue(string $message, array $context = []): void
    {
        $dir = STORAGE_PATH . '/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }

        unset($context['password'], $context['password_hash']);
        @file_put_contents(
            $dir . '/account.log',
            '[' . date('c') . '] ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );
    }

    private function friendlyService(array $service): array
    {
        $status = strtolower((string) ($service['status'] ?? ''));
        $friendly = match ($status) {
            'active' => 'Active',
            'pending' => 'Pending Payment',
            'suspended' => 'Processing',
            'terminated', 'cancelled' => 'Cancelled',
            default => $service['status'] ?? 'Processing',
        };

        $service['friendly_status'] = $friendly;
        return $service;
    }

    private function validateRegistration(array $input): array
    {
        [$data, $errors] = $this->validateProfile($input, null);
        $password = (string) ($input['password'] ?? '');
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if ($password !== (string) ($input['password_confirmation'] ?? '')) {
            $errors[] = 'Password confirmation does not match.';
        }
        $data['password'] = $password;

        return [$data, $errors];
    }

    private function validateProfile(array $input, ?int $currentUserId): array
    {
        $data = [
            'first_name' => trim((string) ($input['first_name'] ?? '')),
            'last_name' => trim((string) ($input['last_name'] ?? '')),
            'company_name' => trim((string) ($input['company_name'] ?? '')),
            'email' => strtolower(trim((string) ($input['email'] ?? ''))),
            'phone' => trim((string) ($input['phone'] ?? '')),
            'address' => trim((string) ($input['address'] ?? '')),
            'city' => trim((string) ($input['city'] ?? '')),
            'state' => trim((string) ($input['state'] ?? '')),
            'postcode' => trim((string) ($input['postcode'] ?? '')),
            'country' => strtoupper(trim((string) ($input['country'] ?? 'GB'))),
        ];

        $errors = [];
        foreach (['first_name' => 'First name', 'last_name' => 'Last name', 'phone' => 'Phone', 'address' => 'Address', 'city' => 'City', 'state' => 'County/state', 'postcode' => 'Postcode'] as $key => $label) {
            if ($data[$key] === '') {
                $errors[] = $label . ' is required.';
            }
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        if (!preg_match('/^[A-Z]{2}$/', $data['country'])) {
            $errors[] = 'Choose a valid country.';
        }

        $existing = $this->customers->findByEmail($data['email']);
        if ($existing && ($currentUserId === null || (int) $existing['id'] !== $currentUserId)) {
            $errors[] = 'This email address is already in use.';
        }

        return [$data, $errors];
    }

    private function fullUser(array $sessionUser): array
    {
        $user = $this->customers->find((int) $sessionUser['id']);
        if (!$user) {
            $this->auth->logout();
            $this->redirect(url('/account/login'));
        }

        return $user;
    }

    private function requireCustomer(): array
    {
        $user = $this->auth->user();
        if (!$user) {
            $this->redirect(url('/account/login'));
        }

        return $user;
    }

    private function safeNext(string $next): string
    {
        $next = trim($next);
        return str_starts_with($next, '/') && !str_starts_with($next, '//') ? $next : '';
    }

    private function withQueryFlag(string $path, string $key, string $value): string
    {
        $separator = str_contains($path, '?') ? '&' : '?';
        return url($path . $separator . rawurlencode($key) . '=' . rawurlencode($value));
    }

    private function nextDueDate(array $services): string
    {
        $dates = [];
        foreach ($services as $service) {
            $date = (string) ($service['nextduedate'] ?? '');
            if ($date !== '' && $date !== '0000-00-00') {
                $dates[] = $date;
            }
        }

        sort($dates);
        return $dates[0] ?? '';
    }

    private function countries(): array
    {
        return ['GB' => 'United Kingdom', 'US' => 'United States', 'PK' => 'Pakistan', 'IE' => 'Ireland', 'CA' => 'Canada', 'AU' => 'Australia'];
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
                'title' => $seo['meta_title'] ?? 'My Account | Planetic Solutions',
                'description' => $seo['meta_description'] ?? 'Manage your Planetic Solutions account, services, invoices and profile.',
                'keywords' => $seo['keywords'] ?? '',
                'og_title' => $seo['og_title'] ?? '',
                'og_image' => '',
                'canonical_url' => '',
            ],
            'schemas' => [],
            'csrfToken' => Csrf::token(),
        ];
    }
}
