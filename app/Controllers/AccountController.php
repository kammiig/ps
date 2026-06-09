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
use App\Models\ProvisioningRepository;
use App\Models\TicketRepository;
use App\Services\CloudflareDnsService;
use App\Services\CustomerAccountSyncService;
use App\Services\Mailer;
use App\Services\WhmcsService;

final class AccountController extends Controller
{
    private ContentRepository $content;
    private CustomerRepository $customers;
    private PaymentRepository $payments;
    private ProvisioningRepository $provisioning;
    private TicketRepository $tickets;
    private CustomerAccountSyncService $accountSync;
    private CloudflareDnsService $dnsProvider;
    private CustomerAuth $auth;
    private WhmcsService $whmcs;
    private array $settings;

    public function __construct()
    {
        $this->content = new ContentRepository();
        $this->customers = new CustomerRepository();
        $this->payments = new PaymentRepository();
        $this->provisioning = new ProvisioningRepository();
        $this->tickets = new TicketRepository();
        $this->auth = new CustomerAuth();
        $this->settings = $this->content->settings();
        $this->whmcs = new WhmcsService($this->settings);
        $this->accountSync = new CustomerAccountSyncService($this->whmcs, $this->provisioning, $this->settings);
        $this->dnsProvider = new CloudflareDnsService($this->settings);
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
        $this->syncAccount($account);
        $invoices = $this->clientInvoices($account, false);
        $payments = $this->accountPaymentOrders($account, 8);
        $domains = $this->accountDomainsForView($account, false);
        $hosting = $this->accountHostingForView($account, false);
        $websiteProjects = $this->accountWebsiteProjects($account);
        $recentTickets = $this->safeRecentTickets((int) ($account['id'] ?? 0), 3);

        return $this->render('account/dashboard', $this->baseData('account-dashboard', [
            'account' => $account,
            'invoices' => $invoices,
            'domains' => $domains,
            'hosting' => $hosting,
            'websiteProjects' => $websiteProjects,
            'recentTickets' => $recentTickets,
            'recentPayments' => array_slice($payments, 0, 3),
            'recentActivity' => $this->recentAccountActivity($domains, $hosting, $websiteProjects, $payments, $recentTickets),
            'domainCount' => count($domains),
            'hostingCount' => count($hosting),
            'websiteProjectCount' => count($websiteProjects),
            'openTicketCount' => $this->safeOpenTicketCount((int) ($account['id'] ?? 0)),
            'unpaidCount' => count(array_filter($invoices, static fn (array $invoice): bool => in_array(strtolower((string) ($invoice['status'] ?? '')), ['unpaid', 'payment pending'], true))),
        ]));
    }

    public function domains(): string
    {
        $account = $this->fullUser($this->requireCustomer());
        $this->syncAccount($account);

        return $this->render('account/domains', $this->baseData('account-domains', [
            'account' => $account,
            'domains' => $this->accountDomainsForView($account),
        ]));
    }

    public function hosting(): string
    {
        $account = $this->fullUser($this->requireCustomer());
        $this->syncAccount($account);

        return $this->render('account/hosting', $this->baseData('account-hosting', [
            'account' => $account,
            'hosting' => $this->accountHostingForView($account),
        ]));
    }

    public function websiteDevelopment(): string
    {
        $account = $this->fullUser($this->requireCustomer());
        $this->syncAccount($account);

        return $this->render('account/website-development', $this->baseData('account-website-development', [
            'account' => $account,
            'projects' => $this->accountWebsiteProjects($account),
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
        $this->syncAccount($account);

        return $this->render('account/billing', $this->baseData('account-billing', [
            'account' => $account,
            'invoices' => $this->clientInvoices($account),
            'payments' => $this->accountPaymentOrders($account, 10),
        ]));
    }

    public function dns(): string
    {
        $account = $this->fullUser($this->requireCustomer());
        $this->syncAccount($account);

        return $this->render('account/dns', $this->baseData('account-dns', [
            'account' => $account,
            'domains' => $this->accountDomainsForView($account),
        ]));
    }

    public function openDns(): string
    {
        $this->requireCustomer();
        $this->redirect(url('/account/dns'));
    }

    public function tickets(): string
    {
        $account = $this->fullUser($this->requireCustomer());

        return $this->render('account/tickets', $this->baseData('account-tickets', [
            'account' => $account,
            'tickets' => $this->safeTicketsForCustomer((int) ($account['id'] ?? 0)),
            'openTicketCount' => $this->safeOpenTicketCount((int) ($account['id'] ?? 0)),
        ]));
    }

    public function newTicket(array $errors = [], array $old = []): string
    {
        $account = $this->fullUser($this->requireCustomer());
        $old = $old ?: $this->ticketOldFromInput($_GET);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                return $this->ticketNewView($account, ['Your security token expired. Please try again.'], $this->ticketOldFromInput($_POST));
            }

            [$data, $message, $validation] = $this->validateTicketInput($_POST);
            if ($validation) {
                $old = $this->ticketOldFromInput($_POST);
                $old['message'] = (string) ($_POST['message'] ?? '');
                return $this->ticketNewView($account, $validation, $old);
            }

            try {
                $ticket = $this->tickets->createForCustomer($account, $data, $message, $this->ticketAttachments('attachment'));
                $this->redirect(url('/account/tickets/' . (int) ($ticket['id'] ?? 0) . '?created=1'));
            } catch (\Throwable $exception) {
                $this->logAccountIssue('Support ticket could not be created.', [
                    'customer_id' => (int) ($account['id'] ?? 0),
                    'type' => get_class($exception),
                    'message' => $exception->getMessage(),
                ]);
                $old = $this->ticketOldFromInput($_POST);
                $old['message'] = (string) ($_POST['message'] ?? '');
                $friendly = $exception instanceof \RuntimeException ? $exception->getMessage() : 'Your ticket could not be submitted right now. Please try again shortly.';
                return $this->ticketNewView($account, [$friendly], $old);
            }
        }

        return $this->ticketNewView($account, $errors, $old);
    }

    public function ticketDetail(string $id, array $errors = []): string
    {
        $account = $this->fullUser($this->requireCustomer());
        try {
            $ticket = $this->tickets->findForCustomer((int) $id, (int) ($account['id'] ?? 0));
            if (!$ticket) {
                $this->redirect(url('/account/tickets'));
            }

            return $this->render('account/ticket-detail', $this->baseData('account-tickets', [
                'account' => $account,
                'ticket' => $ticket,
                'messages' => $this->tickets->messages((int) $ticket['id']),
                'attachmentsByMessage' => $this->tickets->attachmentsByMessage((int) $ticket['id']),
                'errors' => $errors,
                'created' => (string) ($_GET['created'] ?? '') === '1',
                'replySaved' => (string) ($_GET['reply'] ?? '') === '1',
            ]));
        } catch (\Throwable $exception) {
            $this->logAccountIssue('Support ticket details could not be loaded.', [
                'customer_id' => (int) ($account['id'] ?? 0),
                'ticket_id' => (int) $id,
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return $this->render('account/tickets', $this->baseData('account-tickets', [
                'account' => $account,
                'tickets' => [],
                'openTicketCount' => 0,
                'errors' => ['Support ticket details could not be loaded right now. Please try again shortly.'],
            ]));
        }
    }

    public function replyTicket(string $id): string
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            return $this->ticketDetail($id, ['Your security token expired. Please try again.']);
        }

        $account = $this->fullUser($this->requireCustomer());
        try {
            $ticket = $this->tickets->findForCustomer((int) $id, (int) ($account['id'] ?? 0));
            if (!$ticket) {
                $this->redirect(url('/account/tickets'));
            }
        } catch (\Throwable $exception) {
            $this->logAccountIssue('Support ticket reply lookup failed.', [
                'customer_id' => (int) ($account['id'] ?? 0),
                'ticket_id' => (int) $id,
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
            return $this->tickets();
        }

        $message = trim((string) ($_POST['message'] ?? ''));
        if (strlen($message) < 3) {
            return $this->ticketDetail($id, ['Enter a reply before sending.']);
        }

        try {
            $this->tickets->addCustomerReply($ticket, $account, $message, $this->ticketAttachments('attachment'));
            $this->redirect(url('/account/tickets/' . (int) $ticket['id'] . '?reply=1'));
        } catch (\Throwable $exception) {
            $this->logAccountIssue('Support ticket reply could not be saved.', [
                'customer_id' => (int) ($account['id'] ?? 0),
                'ticket_id' => (int) ($ticket['id'] ?? 0),
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
            $friendly = $exception instanceof \RuntimeException ? $exception->getMessage() : 'Your reply could not be sent right now. Please try again shortly.';
            return $this->ticketDetail($id, [$friendly]);
        }
    }

    public function domainDns(string $domainName, array $errors = [], bool $recordSaved = false, bool $recordDeleted = false, bool $nameserversSaved = false): string
    {
        $account = $this->fullUser($this->requireCustomer());
        $this->syncAccount($account);
        $owned = $this->ownedDomainByName($account, $domainName);
        if (!$owned) {
            $this->redirect(url('/account/domains'));
        }

        $domain = $this->accountDomain($owned);
        $domainName = strtolower((string) ($domain['domainname'] ?? $domain['domain'] ?? $domainName));
        $nameservers = $this->blankNameservers();
        $domainId = (int) ($domain['id'] ?? $domain['domainid'] ?? 0);
        if ($domainId > 0) {
            $loaded = $this->whmcs->nameserversForDomain($domainId);
            if (!empty($loaded['ok'])) {
                $nameservers = array_replace($nameservers, $loaded['nameservers'] ?? []);
            } else {
                $errors[] = $this->publicDnsMessage((string) ($loaded['message'] ?? ''));
            }
        }

        $records = $this->dnsProvider->listRecords($domainName);

        return $this->render('account/dns-manage', $this->baseData('account-dns', [
            'account' => $account,
            'domain' => $domain,
            'domainName' => $domainName,
            'dnsProviderStatus' => $this->dnsProvider->providerLabel($domainName),
            'dnsRecordsAvailable' => !empty($records['available']),
            'dnsRecords' => $records['records'] ?? [],
            'dnsRecordsMessage' => $records['ok'] ? '' : (string) ($records['message'] ?? 'DNS records could not be loaded right now.'),
            'nameservers' => $nameservers,
            'errors' => $errors,
            'recordSaved' => $recordSaved,
            'recordDeleted' => $recordDeleted,
            'nameserversSaved' => $nameserversSaved,
        ]));
    }

    public function saveDnsRecord(string $domainName): string
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            return $this->domainDns($domainName, ['Your security token expired. Please try again.']);
        }

        $account = $this->fullUser($this->requireCustomer());
        $owned = $this->ownedDomainByName($account, $domainName);
        if (!$owned) {
            $this->redirect(url('/account/domains'));
        }

        $domainName = strtolower((string) ($owned['domainname'] ?? $owned['domain'] ?? $domainName));
        $record = [
            'type' => $_POST['type'] ?? '',
            'name' => $_POST['name'] ?? '',
            'content' => $_POST['content'] ?? '',
            'target' => $_POST['target'] ?? '',
            'priority' => $_POST['priority'] ?? '',
            'weight' => $_POST['weight'] ?? '',
            'port' => $_POST['port'] ?? '',
            'service' => $_POST['service'] ?? '',
            'proto' => $_POST['proto'] ?? '',
            'flag' => $_POST['flag'] ?? '',
            'tag' => $_POST['tag'] ?? '',
            'ttl' => $_POST['ttl'] ?? '3600',
            'proxied' => (string) ($_POST['proxied'] ?? '0') === '1',
        ];
        $saved = $this->dnsProvider->saveRecord($domainName, $record, trim((string) ($_POST['record_id'] ?? '')));
        if (empty($saved['ok'])) {
            $this->logAccountIssue('DNS record save failed.', [
                'customer_id' => (int) ($account['id'] ?? 0),
                'domain' => $domainName,
                'type' => (string) ($record['type'] ?? ''),
                'message' => $saved['message'] ?? '',
            ]);
            return $this->domainDns($domainName, [(string) ($saved['message'] ?? 'DNS record could not be saved right now.')]);
        }

        return $this->domainDns($domainName, [], true);
    }

    public function deleteDnsRecord(string $domainName, string $recordId): string
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            return $this->domainDns($domainName, ['Your security token expired. Please try again.']);
        }

        $account = $this->fullUser($this->requireCustomer());
        $owned = $this->ownedDomainByName($account, $domainName);
        if (!$owned) {
            $this->redirect(url('/account/domains'));
        }

        $domainName = strtolower((string) ($owned['domainname'] ?? $owned['domain'] ?? $domainName));
        $deleted = $this->dnsProvider->deleteRecord($domainName, $recordId);
        if (empty($deleted['ok'])) {
            $this->logAccountIssue('DNS record delete failed.', [
                'customer_id' => (int) ($account['id'] ?? 0),
                'domain' => $domainName,
                'record_id' => substr($recordId, 0, 24),
                'message' => $deleted['message'] ?? '',
            ]);
            return $this->domainDns($domainName, [(string) ($deleted['message'] ?? 'DNS record could not be deleted right now.')]);
        }

        return $this->domainDns($domainName, [], false, true);
    }

    public function updateDomainNameservers(string $domainName): string
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            return $this->domainDns($domainName, ['Your security token expired. Please try again.']);
        }

        $account = $this->fullUser($this->requireCustomer());
        $owned = $this->ownedDomainByName($account, $domainName);
        if (!$owned) {
            $this->redirect(url('/account/domains'));
        }

        $domainId = (int) ($owned['id'] ?? $owned['domainid'] ?? 0);
        if ($domainId <= 0) {
            return $this->domainDns($domainName, ['Nameserver updates are not available for this domain yet.']);
        }

        [$nameservers, $errors] = $this->validateNameservers($_POST);
        if ($errors) {
            return $this->domainDns($domainName, $errors);
        }

        $updated = $this->whmcs->updateDomainNameservers($domainId, $nameservers);
        if (empty($updated['ok'])) {
            $this->logAccountIssue('Domain nameserver update failed.', [
                'customer_id' => (int) ($account['id'] ?? 0),
                'domain_id' => $domainId,
                'domain' => $domainName,
                'message' => $updated['message'] ?? '',
            ]);
            return $this->domainDns($domainName, [$this->publicDnsMessage((string) ($updated['message'] ?? ''))]);
        }

        $this->clearCachedWhmcsRead((int) ($account['whmcs_client_id'] ?? 0), 'domains');
        return $this->domainDns($domainName, [], false, false, true);
    }

    public function manageDns(string $domainId): string
    {
        $account = $this->fullUser($this->requireCustomer());
        $domain = $this->ownedDomain($account, (int) $domainId);
        if (!$domain) {
            $this->redirect(url('/account/dns'));
        }

        $domainName = (string) ($domain['domainname'] ?? $domain['domain'] ?? '');
        if ($domainName !== '') {
            $this->redirect(url('/account/domains/' . rawurlencode(strtolower($domainName)) . '/dns'));
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
        return $this->mergeLocalServices($account, $this->clientLiveServices($account, $allowLiveLoad));
    }

    private function clientLiveServices(array $account, bool $allowLiveLoad = true): array
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
        $invoices = [];
        if (!empty($account['whmcs_client_id'])) {
            $clientId = (int) $account['whmcs_client_id'];
            $result = $this->cachedWhmcsRead($clientId, 'invoices', fn (): array => $this->whmcs->invoicesForClient($clientId), $allowLiveLoad);
            $invoices = $result['ok'] ? $result['invoices'] : [];
        }

        return $this->mergeLocalInvoices($account, $invoices);
    }

    private function accountDomainsForView(array $account, bool $allowLiveLoad = true): array
    {
        $rows = [];
        foreach ($this->clientDomains($account, $allowLiveLoad) as $domain) {
            $row = $this->domainViewRow($domain);
            $rows[strtolower($row['domain_name'])] = $row;
        }

        foreach ($this->localProvisioningItems($account, 'domain') as $item) {
            $row = $this->localDomainViewRow($item);
            $key = strtolower($row['domain_name']);
            if (isset($rows[$key])) {
                $rows[$key]['setup_issue'] = $row['setup_issue'] ?: $rows[$key]['setup_issue'];
                $rows[$key]['registrar_status'] = $row['registrar_status'] ?: $rows[$key]['registrar_status'];
                if ($row['nameservers']) {
                    $rows[$key]['nameservers'] = $row['nameservers'];
                }
                continue;
            }
            $rows[$key] = $row;
        }

        return array_values($rows);
    }

    private function accountHostingForView(array $account, bool $allowLiveLoad = true): array
    {
        $localItems = $this->localProvisioningItems($account, 'hosting');
        $localByDomain = [];
        foreach ($localItems as $item) {
            $localByDomain[strtolower(trim((string) ($item['domain_name'] ?? '')))] = $item;
        }

        $rows = [];
        foreach ($this->clientLiveServices($account, $allowLiveLoad) as $service) {
            if ($this->accountSync->isWebsiteDevelopmentService($service)) {
                continue;
            }

            $domain = strtolower(trim((string) ($service['domain'] ?? '')));
            $row = $this->hostingViewRow($service, $localByDomain[$domain] ?? null);
            $key = strtolower($row['package_name'] . ':' . $row['connected_domain']);
            $rows[$key] = $row;
        }

        foreach ($localItems as $item) {
            $row = $this->localHostingViewRow($item);
            $key = strtolower($row['package_name'] . ':' . $row['connected_domain']);
            if (isset($rows[$key])) {
                $rows[$key]['setup_issue'] = $row['setup_issue'] ?: $rows[$key]['setup_issue'];
                $rows[$key]['whm_package'] = $row['whm_package'] ?: $rows[$key]['whm_package'];
                continue;
            }
            $rows[$key] = $row;
        }

        return array_values($rows);
    }

    private function accountWebsiteProjects(array $account): array
    {
        try {
            $projects = $this->provisioning->websiteProjectsForAccount(
                isset($account['id']) ? (int) $account['id'] : null,
                isset($account['whmcs_client_id']) ? (int) $account['whmcs_client_id'] : null
            );
            foreach ($projects as &$project) {
                unset($project['internal_notes']);
            }
            unset($project);

            return $projects;
        } catch (\Throwable $exception) {
            $this->logAccountIssue('Website projects could not be loaded.', [
                'customer_id' => (int) ($account['id'] ?? 0),
                'whmcs_client_id' => (int) ($account['whmcs_client_id'] ?? 0),
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function localProvisioningItems(array $account, string $itemType): array
    {
        try {
            return $this->provisioning->itemsForAccount(
                isset($account['id']) ? (int) $account['id'] : null,
                isset($account['whmcs_client_id']) ? (int) $account['whmcs_client_id'] : null,
                $itemType
            );
        } catch (\Throwable $exception) {
            $this->logAccountIssue('Local provisioning items could not be loaded.', [
                'customer_id' => (int) ($account['id'] ?? 0),
                'whmcs_client_id' => (int) ($account['whmcs_client_id'] ?? 0),
                'item_type' => $itemType,
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function domainViewRow(array $domain): array
    {
        $domainId = (int) ($domain['id'] ?? $domain['domainid'] ?? 0);
        $nameservers = [];
        if ($domainId > 0) {
            $loaded = $this->whmcs->nameserversForDomain($domainId);
            if (!empty($loaded['ok'])) {
                $nameservers = array_values(array_filter(array_map('strval', $loaded['nameservers'] ?? [])));
            }
        }

        $status = (string) ($domain['status'] ?? 'Processing');
        return [
            'domain_name' => (string) ($domain['domainname'] ?? $domain['domain'] ?? 'Domain'),
            'registration_status' => $this->friendlyDomainStatus($status),
            'registration_date' => $this->dateLabel($domain['registrationdate'] ?? $domain['regdate'] ?? ''),
            'expiry_date' => $this->dateLabel($domain['expirydate'] ?? ''),
            'renewal_date' => $this->dateLabel($domain['nextduedate'] ?? ''),
            'renewal_amount' => $this->amountLabel($domain['recurringamount'] ?? $domain['renewalamount'] ?? null),
            'nameservers' => $nameservers,
            'registrar_status' => strtolower($status) === 'active' ? 'Registered' : $this->friendlyDomainStatus($status),
            'setup_issue' => '',
            'dns_url' => $this->dnsUrlForDomain((string) ($domain['domainname'] ?? $domain['domain'] ?? '')),
        ];
    }

    private function localDomainViewRow(array $item): array
    {
        $nameservers = json_decode((string) ($item['nameservers_json'] ?? '[]'), true);
        if (!is_array($nameservers) || !$nameservers) {
            $nameservers = array_values(array_filter($this->whmcs->checkoutConfig()['nameservers'] ?? []));
        }

        return [
            'domain_name' => (string) ($item['domain_name'] ?? 'Domain'),
            'registration_status' => $this->friendlyLocalProvisioningStatus((string) ($item['payment_status'] ?? ''), (string) ($item['domain_registration_status'] ?? ''), 'Processing'),
            'registration_date' => $this->dateLabel($item['registration_date'] ?? ''),
            'expiry_date' => $this->dateLabel($item['expiry_date'] ?? ''),
            'renewal_date' => $this->dateLabel($item['renewal_date'] ?? ''),
            'renewal_amount' => $this->amountLabel($item['renewal_amount'] ?? null),
            'nameservers' => array_values(array_filter(array_map('strval', $nameservers))),
            'registrar_status' => $this->friendlyLocalProvisioningStatus((string) ($item['payment_status'] ?? ''), (string) ($item['domain_registration_status'] ?? ''), 'Processing'),
            'setup_issue' => (string) ($item['setup_issue_public'] ?? ''),
            'dns_url' => $this->dnsUrlForDomain((string) ($item['domain_name'] ?? '')),
        ];
    }

    private function hostingViewRow(array $service, ?array $localItem): array
    {
        $status = (string) ($service['friendly_status'] ?? $service['status'] ?? 'Processing');
        $packageName = (string) ($service['name'] ?? $service['productname'] ?? 'Hosting Package');
        return [
            'package_name' => $packageName,
            'connected_domain' => (string) ($service['domain'] ?? 'No domain connected yet'),
            'hosting_status' => $status,
            'billing_cycle' => (string) ($service['billingcycle'] ?? 'Not available'),
            'start_date' => $this->dateLabel($service['regdate'] ?? $service['registrationdate'] ?? ''),
            'next_due_date' => $this->dateLabel($service['nextduedate'] ?? ''),
            'renewal_amount' => $this->amountLabel($service['recurringamount'] ?? $service['amount'] ?? null),
            'server_status' => $status,
            'whm_package' => (string) ($localItem['whm_package'] ?? $this->defaultWhmPackageForText($packageName)),
            'cpanel_url' => '',
            'setup_issue' => (string) ($localItem['setup_issue_public'] ?? ''),
        ];
    }

    private function localHostingViewRow(array $item): array
    {
        $packageName = (string) ($item['display_name'] ?? 'Hosting Package');
        return [
            'package_name' => $packageName,
            'connected_domain' => (string) (($item['domain_name'] ?? '') ?: 'No domain connected yet'),
            'hosting_status' => $this->friendlyLocalProvisioningStatus((string) ($item['payment_status'] ?? ''), (string) ($item['hosting_setup_status'] ?? ''), 'Setup in Progress'),
            'billing_cycle' => $this->billingCycleLabel((string) ($item['billing_cycle'] ?? '')),
            'start_date' => $this->dateLabel($item['start_date'] ?? $item['created_at'] ?? ''),
            'next_due_date' => $this->dateLabel($item['next_due_date'] ?? ''),
            'renewal_amount' => $this->amountLabel($item['renewal_amount'] ?? null),
            'server_status' => $this->friendlyProvisioningStatus((string) ($item['provisioning_status'] ?? 'processing')),
            'whm_package' => (string) ($item['whm_package'] ?? $this->defaultWhmPackageForText($packageName)),
            'cpanel_url' => '',
            'setup_issue' => (string) ($item['setup_issue_public'] ?? ''),
        ];
    }

    private function recentAccountActivity(array $domains, array $hosting, array $projects, array $payments, array $tickets = []): array
    {
        $activity = [];
        foreach ($payments as $payment) {
            if (($payment['payment_status'] ?? '') === 'paid') {
                $activity[] = [
                    'label' => 'Payment confirmed',
                    'detail' => 'Invoice #' . (int) ($payment['whmcs_invoice_id'] ?? 0),
                    'date' => (string) ($payment['paid_at'] ?? $payment['updated_at'] ?? ''),
                ];
            }
        }

        foreach ($domains as $domain) {
            if (($domain['registration_status'] ?? '') === 'Registered') {
                $activity[] = [
                    'label' => 'Domain registered',
                    'detail' => (string) ($domain['domain_name'] ?? ''),
                    'date' => (string) ($domain['registration_date'] ?? ''),
                ];
            }
        }

        foreach ($hosting as $service) {
            if (in_array((string) ($service['hosting_status'] ?? ''), ['Active', 'Setup Completed'], true)) {
                $activity[] = [
                    'label' => 'Hosting setup completed',
                    'detail' => (string) ($service['package_name'] ?? 'Hosting'),
                    'date' => (string) ($service['start_date'] ?? ''),
                ];
            }
        }

        foreach ($projects as $project) {
            $activity[] = [
                'label' => 'Website package purchased',
                'detail' => (string) ($project['package_name'] ?? 'Website Development'),
                'date' => (string) ($project['purchase_date'] ?? $project['created_at'] ?? ''),
            ];
            if (!in_array((string) ($project['project_status'] ?? ''), ['Payment Pending', 'Payment Confirmed'], true)) {
                $activity[] = [
                    'label' => 'Project status updated',
                    'detail' => (string) ($project['project_status'] ?? ''),
                    'date' => (string) ($project['updated_at'] ?? ''),
                ];
            }
        }

        foreach ($tickets as $ticket) {
            $activity[] = [
                'label' => 'Support ticket updated',
                'detail' => '#' . (string) ($ticket['public_ref'] ?? $ticket['id'] ?? '') . ' - ' . (string) ($ticket['subject'] ?? 'Ticket'),
                'date' => (string) ($ticket['updated_at'] ?? $ticket['created_at'] ?? ''),
            ];
        }

        usort($activity, static function (array $left, array $right): int {
            $leftTime = strtotime((string) ($left['date'] ?? '')) ?: 0;
            $rightTime = strtotime((string) ($right['date'] ?? '')) ?: 0;
            return $rightTime <=> $leftTime;
        });
        return array_slice($activity, 0, 8);
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

    private function billingCycleLabel(string $cycle): string
    {
        $cycle = strtolower(trim($cycle));
        return match ($cycle) {
            'annually', 'annual', 'yearly', 'year' => 'Yearly',
            'monthly' => 'Monthly',
            'onetime', 'one-time', 'one time' => 'One-time',
            default => $cycle !== '' ? ucwords(str_replace(['-', '_'], ' ', $cycle)) : 'Not available',
        };
    }

    private function friendlyDomainStatus(string $status): string
    {
        $status = strtolower(trim($status));
        return match ($status) {
            'active' => 'Registered',
            'pending', 'pending registration' => 'Processing',
            'expired' => 'Expired',
            'cancelled', 'canceled', 'fraud' => 'Action Required',
            default => $status !== '' ? ucwords(str_replace(['-', '_'], ' ', $status)) : 'Processing',
        };
    }

    private function friendlyLocalProvisioningStatus(string $paymentStatus, string $status, string $fallback): string
    {
        if ($paymentStatus !== 'paid') {
            return 'Payment Pending';
        }

        $status = trim($status);
        return $status !== '' ? $status : $fallback;
    }

    private function friendlyProvisioningStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'pending_payment' => 'Payment Pending',
            'processing' => 'Setup in Progress',
            'active' => 'Active',
            'action_required' => 'Action Required',
            'cancelled', 'canceled' => 'Cancelled',
            default => 'Setup in Progress',
        };
    }

    private function dateLabel(mixed $date): string
    {
        $date = trim((string) $date);
        if ($date === '' || $date === '0000-00-00' || $date === '0000-00-00 00:00:00') {
            return 'Not available';
        }

        return substr($date, 0, 10);
    }

    private function amountLabel(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return 'Not available';
        }

        $value = $this->accountMoneyToFloat($amount);
        return $value > 0 ? money(number_format($value, 2, '.', '')) : 'Not available';
    }

    private function defaultWhmPackageForText(string $text): string
    {
        $text = strtolower($text);
        if (str_contains($text, 'starter')) {
            return 'planetic_starter';
        }
        if (str_contains($text, 'business')) {
            return 'planetic_business';
        }
        if (str_contains($text, 'agency') || str_contains($text, 'ecommerce') || str_contains($text, 'commerce') || str_contains($text, 'reseller')) {
            return 'planetic_agency';
        }
        if (str_contains($text, 'pro') || str_contains($text, 'wordpress')) {
            return 'planetic_pro';
        }

        return '';
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

    private function syncAccount(array $account): void
    {
        try {
            $this->accountSync->sync($account);
        } catch (\Throwable $exception) {
            $this->logAccountIssue('Customer account sync failed.', [
                'customer_id' => (int) ($account['id'] ?? 0),
                'whmcs_client_id' => (int) ($account['whmcs_client_id'] ?? 0),
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);
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
        $domain['dns_url'] = $this->dnsUrlForDomain((string) ($domain['domainname'] ?? $domain['domain'] ?? ''));
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

    private function ownedDomainByName(array $account, string $domainName): ?array
    {
        $needle = strtolower(trim(rawurldecode($domainName)));
        $needle = rtrim($needle, '.');
        if ($needle === '' || !preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i', $needle)) {
            return null;
        }

        foreach ($this->clientDomains($account) as $domain) {
            $candidate = strtolower(trim((string) ($domain['domainname'] ?? $domain['domain'] ?? '')));
            if (rtrim($candidate, '.') === $needle) {
                return $domain;
            }
        }

        foreach ($this->localProvisioningItems($account, 'domain') as $item) {
            $candidate = strtolower(trim((string) ($item['domain_name'] ?? '')));
            if (rtrim($candidate, '.') === $needle) {
                return [
                    'id' => (int) ($item['whmcs_domain_id'] ?? 0),
                    'domainid' => (int) ($item['whmcs_domain_id'] ?? 0),
                    'domainname' => $candidate,
                    'domain' => $candidate,
                    'status' => (string) ($item['domain_registration_status'] ?? 'Processing'),
                    'registrationdate' => (string) ($item['registration_date'] ?? ''),
                    'expirydate' => (string) ($item['expiry_date'] ?? ''),
                    'nextduedate' => (string) ($item['renewal_date'] ?? ''),
                    'recurringamount' => (string) ($item['renewal_amount'] ?? ''),
                ];
            }
        }

        return null;
    }

    private function dnsUrlForDomain(string $domainName): string
    {
        $domainName = strtolower(trim($domainName));
        if ($domainName === '') {
            return url('/account/dns');
        }

        return url('/account/domains/' . rawurlencode($domainName) . '/dns');
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
            return 'DNS management is being updated. Please contact support if you need an urgent nameserver change.';
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

    private function ticketNewView(array $account, array $errors = [], array $old = []): string
    {
        return $this->render('account/ticket-new', $this->baseData('account-tickets', [
            'account' => $account,
            'errors' => $errors,
            'old' => $old ?: $this->ticketOldFromInput([]),
            'departments' => TicketRepository::DEPARTMENTS,
            'priorities' => TicketRepository::PRIORITIES,
        ]));
    }

    private function safeTicketsForCustomer(int $customerId): array
    {
        try {
            return $this->tickets->ticketsForCustomer($customerId);
        } catch (\Throwable $exception) {
            $this->logAccountIssue('Support tickets could not be loaded.', [
                'customer_id' => $customerId,
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function safeRecentTickets(int $customerId, int $limit): array
    {
        try {
            return $this->tickets->recentForCustomer($customerId, $limit);
        } catch (\Throwable $exception) {
            $this->logAccountIssue('Recent support tickets could not be loaded.', [
                'customer_id' => $customerId,
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return [];
        }
    }

    private function safeOpenTicketCount(int $customerId): int
    {
        try {
            return $this->tickets->openCountForCustomer($customerId);
        } catch (\Throwable $exception) {
            $this->logAccountIssue('Open support ticket count could not be loaded.', [
                'customer_id' => $customerId,
                'type' => get_class($exception),
                'message' => $exception->getMessage(),
            ]);

            return 0;
        }
    }

    private function ticketOldFromInput(array $input): array
    {
        $department = trim((string) ($input['department'] ?? ''));
        $priority = trim((string) ($input['priority'] ?? ''));

        return [
            'subject' => trim((string) ($input['subject'] ?? '')),
            'department' => in_array($department, TicketRepository::DEPARTMENTS, true) ? $department : 'General Support',
            'priority' => in_array($priority, TicketRepository::PRIORITIES, true) ? $priority : 'Medium',
            'related_type' => trim((string) ($input['related_type'] ?? '')),
            'related_label' => trim((string) ($input['related_label'] ?? '')),
            'related_reference' => trim((string) ($input['related_reference'] ?? '')),
            'message' => trim((string) ($input['message'] ?? '')),
        ];
    }

    private function validateTicketInput(array $input): array
    {
        $old = $this->ticketOldFromInput($input);
        $message = trim((string) ($input['message'] ?? ''));
        $errors = [];

        if (strlen($old['subject']) < 4) {
            $errors[] = 'Enter a short subject for your ticket.';
        }
        if (strlen($message) < 10) {
            $errors[] = 'Tell us a little more about what you need help with.';
        }

        return [
            [
                'subject' => substr($old['subject'], 0, 190),
                'department' => $old['department'],
                'priority' => $old['priority'],
                'related_type' => substr($old['related_type'], 0, 40),
                'related_label' => substr($old['related_label'], 0, 190),
                'related_reference' => substr($old['related_reference'], 0, 190),
            ],
            substr($message, 0, 10000),
            $errors,
        ];
    }

    private function ticketAttachments(string $field): array
    {
        if (empty($_FILES[$field]) || ($_FILES[$field]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [];
        }

        $file = $_FILES[$field];
        if (is_array($file['name'] ?? null)) {
            return [];
        }

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Attachment upload failed. Please try again.');
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > 5 * 1024 * 1024) {
            throw new \RuntimeException('Attachments must be smaller than 5MB.');
        }

        $original = basename((string) ($file['name'] ?? 'attachment'));
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'txt', 'doc', 'docx'];
        if (!in_array($extension, $allowed, true)) {
            throw new \RuntimeException('Use JPG, PNG, WebP, GIF, PDF, TXT, DOC, or DOCX attachments.');
        }

        $folder = 'uploads/tickets/' . date('Y/m');
        $targetDir = BASE_PATH . '/' . $folder;
        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new \RuntimeException('Attachment upload is temporarily unavailable.');
        }

        $storedName = bin2hex(random_bytes(12)) . '.' . $extension;
        $target = $targetDir . '/' . $storedName;
        if (!move_uploaded_file((string) ($file['tmp_name'] ?? ''), $target)) {
            throw new \RuntimeException('Attachment could not be saved.');
        }

        return [[
            'original_name' => $original,
            'stored_path' => $folder . '/' . $storedName,
            'mime_type' => $this->mimeType($target),
            'file_size' => $size,
        ]];
    }

    private function mimeType(string $path): string
    {
        if (function_exists('mime_content_type')) {
            return (string) mime_content_type($path);
        }

        if (class_exists(\finfo::class)) {
            $info = new \finfo(FILEINFO_MIME_TYPE);
            return (string) $info->file($path);
        }

        return '';
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
