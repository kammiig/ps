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
                return $this->login(['Your security token expired. Please try again.'], $_POST);
            }

            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $identifier = ($_SERVER['REMOTE_ADDR'] ?? 'local') . ':' . $email;
            if (RateLimiter::tooManyAttempts('customer_login', $identifier, 6, 900)) {
                return $this->login(['Too many login attempts. Please wait a few minutes and try again.'], $_POST);
            }

            if ($this->auth->attempt($email, (string) ($_POST['password'] ?? ''))) {
                RateLimiter::clear('customer_login', $identifier);
                $next = $this->safeNext((string) ($_POST['next'] ?? $_GET['next'] ?? ''));
                $this->redirect($next ?: url('/account/dashboard'));
            }

            RateLimiter::hit('customer_login', $identifier, 900);
            $next = $this->safeNext((string) ($_POST['next'] ?? $_GET['next'] ?? ''));
            if ((string) ($_POST['context'] ?? '') === 'checkout' && str_starts_with($next, '/checkout')) {
                $this->redirect($this->withQueryFlag($next, 'login_error', '1'));
            }

            return $this->login(['Email or password is incorrect.'], $_POST);
        }

        return $this->render('account/login', $this->baseData('account-login', [
            'errors' => $errors,
            'old' => $old,
            'next' => $this->safeNext((string) ($_GET['next'] ?? $old['next'] ?? '')),
        ]));
    }

    public function register(array $errors = [], array $old = []): string
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                return $this->register(['Your security token expired. Please try again.'], $_POST);
            }

            [$data, $validation] = $this->validateRegistration($_POST);
            if ($validation) {
                return $this->register($validation, $data);
            }

            $identifier = ($_SERVER['REMOTE_ADDR'] ?? 'local') . ':' . $data['email'];
            if (RateLimiter::tooManyAttempts('customer_register', $identifier, 5, 900)) {
                return $this->register(['Too many registration attempts. Please wait a few minutes and try again.'], $data);
            }
            RateLimiter::hit('customer_register', $identifier, 900);

            if ($this->customers->findByEmail($data['email'])) {
                return $this->register(['An account already exists for this email. Please log in instead.'], $data);
            }

            $user = $this->customers->create($data);
            $this->auth->login($user);
            RateLimiter::clear('customer_register', $identifier);
            $this->redirect(url('/account/dashboard'));
        }

        return $this->render('account/register', $this->baseData('account-register', [
            'errors' => $errors,
            'old' => $old,
            'countries' => $this->countries(),
        ]));
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
                return $this->passwordReset(['Your security token expired. Please try again.']);
            }

            $email = strtolower(trim((string) ($_POST['email'] ?? '')));
            $identifier = ($_SERVER['REMOTE_ADDR'] ?? 'local') . ':' . $email;
            if (RateLimiter::tooManyAttempts('password_reset', $identifier, 5, 900)) {
                return $this->passwordReset(['Too many reset requests. Please wait a few minutes and try again.']);
            }
            RateLimiter::hit('password_reset', $identifier, 900);

            $user = filter_var($email, FILTER_VALIDATE_EMAIL) ? $this->customers->findByEmail($email) : null;
            if ($user) {
                $token = bin2hex(random_bytes(32));
                $this->customers->createPasswordReset((int) $user['id'], $token);
                Mailer::customerPasswordReset($this->settings, $user, rtrim((string) ($this->settings['app_url'] ?? env('APP_URL', '')), '/') . url('/account/password-reset/' . $token));
            }

            return $this->passwordReset([], true);
        }

        return $this->render('account/password-reset', $this->baseData('account-password-reset', [
            'errors' => $errors,
            'sent' => $sent,
        ]));
    }

    public function passwordResetForm(string $token, array $errors = []): string
    {
        $reset = $this->customers->resetByToken($token);
        if (!$reset) {
            return $this->render('account/password-reset-form', $this->baseData('account-password-reset', [
                'errors' => ['This password reset link is invalid or expired.'],
                'token' => $token,
                'valid' => false,
            ]));
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                return $this->passwordResetForm($token, ['Your security token expired. Please try again.']);
            }

            $password = (string) ($_POST['password'] ?? '');
            if (strlen($password) < 8) {
                return $this->passwordResetForm($token, ['Password must be at least 8 characters.']);
            }
            if ($password !== (string) ($_POST['password_confirmation'] ?? '')) {
                return $this->passwordResetForm($token, ['Password confirmation does not match.']);
            }

            $this->customers->updatePasswordFromReset((int) $reset['id'], (int) $reset['customer_user_id'], $password);
            $this->redirect(url('/account/login'));
        }

        return $this->render('account/password-reset-form', $this->baseData('account-password-reset', [
            'errors' => $errors,
            'token' => $token,
            'valid' => true,
        ]));
    }

    public function dashboard(): string
    {
        $user = $this->requireCustomer();
        $account = $this->fullUser($user);
        $services = $this->clientServices($account, false);
        $invoices = $this->clientInvoices($account, false);
        $recentPayments = $this->payments->recentForCustomer((int) $account['id'], 3);

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
            'payments' => $this->payments->recentForCustomer((int) $account['id'], 10),
        ]));
    }

    public function dns(): string
    {
        $account = $this->fullUser($this->requireCustomer());

        return $this->render('account/dns', $this->baseData('account-dns', [
            'account' => $account,
            'domains' => array_map([$this, 'accountDomain'], $this->clientDomains($account)),
            'dnsOpenUrl' => url('/account/dns/open'),
        ]));
    }

    public function openDns(): string
    {
        $account = $this->fullUser($this->requireCustomer());
        $clientId = (int) ($account['whmcs_client_id'] ?? 0);
        if ($clientId <= 0) {
            $this->redirect(url('/account/dns'));
        }

        $token = $this->whmcs->createSsoToken($clientId, 'clientarea:domains');
        if (!empty($token['ok']) && (string) ($token['redirect_url'] ?? '') !== '') {
            $this->redirect((string) $token['redirect_url']);
        }

        $this->redirect($this->whmcs->domainManagementUrl());
    }

    public function profile(array $errors = [], bool $saved = false): string
    {
        $account = $this->fullUser($this->requireCustomer());

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                return $this->profile(['Your security token expired. Please try again.']);
            }

            [$data, $validation] = $this->validateProfile($_POST, (int) $account['id']);
            if ($validation) {
                return $this->render('account/profile', $this->baseData('account-profile', [
                    'account' => array_merge($account, $data),
                    'countries' => $this->countries(),
                    'errors' => $validation,
                    'saved' => false,
                ]));
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

            return $this->profile([], true);
        }

        return $this->render('account/profile', $this->baseData('account-profile', [
            'account' => $account,
            'countries' => $this->countries(),
            'errors' => $errors,
            'saved' => $saved,
        ]));
    }

    private function clientServices(array $account, bool $allowLiveLoad = true): array
    {
        if (empty($account['whmcs_client_id'])) {
            return [];
        }

        $clientId = (int) $account['whmcs_client_id'];
        $result = $this->cachedWhmcsRead($clientId, 'products', fn (): array => $this->whmcs->productsForClient($clientId), $allowLiveLoad);
        return $result['ok'] ? array_map([$this, 'friendlyService'], $result['products']) : [];
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
        if (empty($account['whmcs_client_id'])) {
            return [];
        }

        $clientId = (int) $account['whmcs_client_id'];
        $result = $this->cachedWhmcsRead($clientId, 'invoices', fn (): array => $this->whmcs->invoicesForClient($clientId), $allowLiveLoad);
        return $result['ok'] ? $result['invoices'] : [];
    }

    private function accountDomain(array $domain): array
    {
        $domainId = (int) ($domain['id'] ?? $domain['domainid'] ?? 0);
        $domain['management_url'] = $this->whmcs->domainManagementUrl($domainId);
        return $domain;
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

        $payload = $loader();
        $ttl = !empty($payload['ok']) ? 120 : 45;
        @file_put_contents($file, json_encode([
            'expires_at' => time() + $ttl,
            'payload' => $payload,
        ], JSON_UNESCAPED_SLASHES));

        return $payload;
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
