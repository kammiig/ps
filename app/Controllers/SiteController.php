<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Core\CustomerAuth;
use App\Models\ContentRepository;
use App\Models\CustomerRepository;
use App\Models\PaymentRepository;
use App\Services\Mailer;
use App\Services\RecaptchaService;
use App\Services\WhmcsService;

final class SiteController extends Controller
{
    private ContentRepository $content;
    private array $settings;
    private WhmcsService $whmcs;

    public function __construct()
    {
        $this->content = new ContentRepository();
        $this->settings = $this->content->settings();
        $this->whmcs = new WhmcsService($this->settings);
    }

    public function home(): string
    {
        $plans = $this->content->hostingPlans(null, true, 4);
        $faqs = $this->content->faqs('home');
        $data = $this->baseData('home', [
            'plans' => $plans,
            'tlds' => $this->content->tlds(),
            'testimonials' => $this->content->testimonials(),
            'faqs' => $faqs,
            'package' => $this->content->package(),
            'services' => $this->rowsFromSetting('home_service_cards', [
                ['WordPress Hosting', 'Fast WordPress-ready hosting with SSL, cPanel and one-click installs.', 'panel', '/wordpress-hosting'],
                ['cPanel Hosting', 'Reliable business hosting with email, databases and simple management.', 'cloud', '/hosting'],
                ['Reseller Hosting', 'Sell hosting under your own brand with clean order links.', 'globe', '/hosting#reseller-hosting'],
                ['Domain Registration', 'Search and register domains through the main website checkout.', 'shield', '/domains'],
                ['Website Development', 'Complete business websites delivered fast with hosting setup included.', 'code', '/website-development'],
                ['Cloudflare CDN Setup', 'Performance and security tuning with Cloudflare CDN configuration.', 'bolt', '/contact'],
            ]),
            'trustBadges' => $this->rowsFromSetting('home_trust_badges', [
                ['Free SSL', 'Secure every eligible hosting plan.'],
                ['cPanel Hosting', 'Familiar website and email control.'],
                ['Easy Billing', 'Orders, renewals and invoices handled.'],
                ['Cloudflare CDN', 'Performance and security setup support.'],
                ['48h Website Delivery', 'Fast delivery for the website package.'],
                ['UK-focused Support', 'Professional support messaging for businesses.'],
            ]),
            'featureSections' => $this->rowsFromSetting('home_feature_sections', [
                ['Speed & Performance', 'LiteSpeed/cache-ready wording, efficient hosting resources and Cloudflare support help your website feel quick from the first visit.', 'bolt'],
                ['Security & Backups', 'Free SSL, hardened hosting practices and backup-friendly cPanel workflows keep everyday business sites protected.', 'shield'],
                ['WordPress Ready', 'Install WordPress quickly, connect Elementor-friendly tooling and manage updates through a simple control panel.', 'panel'],
                ['Free Website Migration', 'Move from another provider with practical migration support and minimum disruption to your business.', 'cloud'],
                ['SEO Ready Hosting', 'Clean performance foundations, HTTPS, schema-ready pages and editable metadata built into the website.', 'globe'],
                ['Business Support', 'Clear support pathways through account billing, contact forms and direct service inquiry flows.', 'mail'],
            ]),
            'schemas' => [
                $this->faqSchema($faqs),
                $this->serviceSchema('Planetic Solutions Web Hosting', 'Fast, secure and affordable hosting, domains and website development.'),
            ],
        ]);

        return $this->render('site/home', $data);
    }

    public function hosting(): string
    {
        $plans = $this->content->hostingPlans();
        $faqs = $this->content->faqs('hosting');

        return $this->render('site/hosting', $this->baseData('hosting', [
            'plans' => $plans,
            'faqs' => $faqs,
            'schemas' => [
                $this->faqSchema($faqs),
                $this->plansSchema($plans),
            ],
            'breadcrumbs' => [
                ['name' => 'Home', 'url' => url('/')],
                ['name' => 'Hosting Plans', 'url' => url('/hosting')],
            ],
        ]));
    }

    public function wordpressHosting(): string
    {
        $plans = $this->content->hostingPlans('wordpress');
        $faqs = $this->content->faqs('wordpress');

        return $this->render('site/wordpress', $this->baseData('wordpress-hosting', [
            'plans' => $plans,
            'faqs' => $faqs,
            'schemas' => [
                $this->faqSchema($faqs),
                $this->serviceSchema('WordPress Hosting', 'Fast WordPress hosting with SSL, cPanel, installer support, Cloudflare CDN and website development add-ons.'),
            ],
            'breadcrumbs' => [
                ['name' => 'Home', 'url' => url('/')],
                ['name' => 'WordPress Hosting', 'url' => url('/wordpress-hosting')],
            ],
        ]));
    }

    public function websiteDevelopment(): string
    {
        $package = $this->content->package();
        $faqs = $this->content->faqs('website-development');

        return $this->render('site/website-development', $this->baseData('website-development', [
            'package' => $package,
            'faqs' => $faqs,
            'schemas' => [
                $this->faqSchema($faqs),
                $this->serviceSchema('Bespoke Website Development for just £199', 'Business website package with first-year domain and hosting support, Elementor setup, content writing, SSL, Cloudflare CDN and 48 hour delivery.'),
            ],
            'breadcrumbs' => [
                ['name' => 'Home', 'url' => url('/')],
                ['name' => 'Website Development', 'url' => url('/website-development')],
            ],
        ]));
    }

    public function domains(): string
    {
        $faqs = $this->content->faqs('domains');

        return $this->render('site/domains', $this->baseData('domains', [
            'tlds' => $this->content->tlds(),
            'faqs' => $faqs,
            'schemas' => [
                $this->faqSchema($faqs),
                $this->serviceSchema('Domain Registration', 'Domain search and registration on the main website with secure account billing.'),
            ],
            'breadcrumbs' => [
                ['name' => 'Home', 'url' => url('/')],
                ['name' => 'Domains', 'url' => url('/domains')],
            ],
        ]));
    }

    public function domainSearchPage(): string
    {
        $domain = $this->normaliseDomain((string) ($_GET['domain'] ?? ''));

        return $this->render('site/domain-search', $this->baseData('domains', [
            'domain' => $domain,
            'breadcrumbs' => [
                ['name' => 'Home', 'url' => url('/')],
                ['name' => 'Domain Search', 'url' => url('/domain-search')],
            ],
        ]));
    }

    public function domainSearch(): string
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            http_response_code(419);
            return 'Security token expired. Please go back and try again.';
        }

        $domain = $this->normaliseDomain((string) ($_POST['domain'] ?? ''));
        if ($domain === '') {
            $this->redirect(url('/domains'));
        }

        $this->redirect(url('/domain-search?domain=' . rawurlencode($domain)));
    }

    public function apiDomainSearch(): string
    {
        $domain = $this->normaliseDomain((string) ($_GET['domain'] ?? ''));
        $parsed = $this->splitDomain($domain);

        if (!$parsed) {
            return $this->json([
                'ok' => false,
                'message' => 'Enter a valid domain using .com, .net, .org, .co.uk, .xyz, or .online.',
            ], 422);
        }

        $pricing = $this->whmcs->tldPricing(1);
        $pricingOk = (bool) ($pricing['ok'] ?? false);

        $tlds = $this->supportedTlds();
        $orderedTlds = array_values(array_unique(array_merge([$parsed['tld']], $tlds)));
        $currency = $pricingOk ? $this->currencyPrefix($pricing['currency'] ?? []) : '£';
        $hostingPid = $this->hostingPid();
        $package = $this->content->package();
        $websiteConfig = $this->whmcs->checkoutConfig()['website_package'] ?? [];
        $websitePriceRaw = (string) ($websiteConfig['price_override'] ?? $package['price'] ?? '199.00');
        $websitePrice = preg_replace('/[^0-9.]/', '', $websitePriceRaw) ?: '199.00';
        $websitePackage = [
            'title' => (string) (($package['title'] ?? '') ?: 'Bespoke Website Development'),
            'description' => (string) (($package['description'] ?? '') ?: 'Launch a professional business website with domain, hosting setup, Elementor, premium Envato elements, stock photos, content writing and Cloudflare integration included.'),
            'price' => number_format((float) $websitePrice, 2, '.', ''),
            'cta_text' => (string) (($package['cta_text'] ?? '') ?: 'Get Complete Website Package'),
            'features' => json_decode($package['features_json'] ?? '[]', true) ?: [],
        ];
        $results = [];
        $availabilityErrors = [];

        foreach ($orderedTlds as $tld) {
            if (!in_array($tld, $tlds, true)) {
                continue;
            }

            $candidate = $parsed['sld'] . $tld;
            $availability = $this->whmcs->checkDomain($candidate);
            if (!$availability['ok']) {
                $availabilityErrors[] = $candidate . ': ' . ($availability['message'] ?? 'WHMCS availability check failed.');
                if ($candidate === $domain) {
                    return $this->json([
                        'ok' => false,
                        'message' => $this->publicWhmcsErrorMessage($availability['message'] ?? ''),
                    ]);
                }
            }

            $domainCheckoutUrl = url('/checkout?' . http_build_query([
                'type' => 'domain',
                'domain' => $candidate,
            ]));
            $bundleCheckoutUrl = url('/checkout?' . http_build_query([
                'type' => 'bundle',
                'domain' => $candidate,
            ]));
            $websiteCheckoutParams = ['type' => 'website'];
            if (!empty($availability['ok']) && !empty($availability['available'])) {
                $websiteCheckoutParams['domain'] = $candidate;
            }
            $websiteCheckoutUrl = url('/checkout?' . http_build_query($websiteCheckoutParams));

            $results[] = [
                'domain' => $candidate,
                'sld' => $parsed['sld'],
                'tld' => $tld,
                'available' => $availability['ok'] ? (bool) $availability['available'] : null,
                'price' => $pricingOk ? $this->whmcs->priceForTld($pricing['pricing'], $tld) : $this->configuredDomainPrice($tld),
                'currency' => $currency,
                'type' => $candidate === $domain ? 'match' : 'alternative',
                'domain_url' => $domainCheckoutUrl,
                'hosting_url' => $bundleCheckoutUrl,
                'checkout_url' => $domainCheckoutUrl,
                'bundle_checkout_url' => $bundleCheckoutUrl,
                'website_checkout_url' => $websiteCheckoutUrl,
                'website_package' => $websitePackage,
            ];
        }

        $match = $results[0] ?? null;

        return $this->json([
            'ok' => true,
            'searched' => $domain,
            'sld' => $parsed['sld'],
            'tld' => $parsed['tld'],
            'available' => (bool) ($match['available'] ?? false),
            'availability_checked' => true,
            'message' => $availabilityErrors ? 'Some alternative TLDs could not be checked and have been disabled.' : null,
            'currency' => $currency,
            'hosting_pid' => $hostingPid,
            'results' => $results,
        ]);
    }

    public function checkout(array $errors = [], array $old = []): string
    {
        if (!$errors && (string) ($_GET['login_error'] ?? '') === '1') {
            $errors[] = 'Email or password is incorrect. Please try again or continue as a new customer.';
        }

        $query = [
            'order_type' => $old['order_type'] ?? $_GET['type'] ?? 'bundle',
            'domain' => $old['domain'] ?? $_GET['domain'] ?? '',
            'hosting_plan' => $old['hosting_plan'] ?? $_GET['plan'] ?? '',
            'billing_cycle' => $old['billing_cycle'] ?? $_GET['billing_cycle'] ?? '',
        ];

        $plans = $this->checkoutPlans();
        if ($query['hosting_plan'] === '' && $plans) {
            $highlighted = array_values(array_filter($plans, static fn (array $plan): bool => !empty($plan['is_highlighted'])));
            $query['hosting_plan'] = ($highlighted[0] ?? $plans[0])['slug'];
        }

        $old = array_merge($query, $old);
        $old = $this->applyLoggedInCustomerDefaults($old);
        unset($old['password']);

        return $this->render('site/checkout', $this->baseData('checkout', [
            'errors' => $errors,
            'old' => $old,
            'plans' => $plans,
            'package' => $this->content->package(),
            'checkoutConfig' => $this->whmcs->checkoutConfig(),
            'breadcrumbs' => [
                ['name' => 'Home', 'url' => url('/')],
                ['name' => 'Checkout', 'url' => url('/checkout')],
            ],
            'metaOverride' => [
                'title' => 'Checkout | Planetic Solutions',
                'description' => 'Order domains, hosting and website development through the Planetic Solutions website with secure account billing.',
            ],
        ]));
    }

    private function publicWhmcsErrorMessage(string $message): string
    {
        $message = strtolower($message);
        if (str_contains($message, 'invalid ip') || str_contains($message, 'api access')) {
            return 'Live domain availability is temporarily blocked by the billing system. Please contact support or try again shortly.';
        }

        return 'Live domain availability could not be checked. Please try again shortly.';
    }

    public function submitCheckout(): string
    {
        try {
            return $this->handleCheckoutSubmission();
        } catch (\Throwable $exception) {
            $this->logCheckoutFailure($exception, $_POST);

            return $this->checkout([
                $this->publicCheckoutFailureMessage($exception),
            ], $_POST);
        }
    }

    private function handleCheckoutSubmission(): string
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            return $this->checkout(['Your security token expired. Please submit the form again.'], $_POST);
        }

        [$data, $errors] = $this->validateCheckout($_POST);
        if ($errors) {
            return $this->checkout($errors, $data);
        }

        $customerPrecheck = $this->precheckCustomerAccount($data);
        if (!$customerPrecheck['ok']) {
            return $this->checkout([$customerPrecheck['message']], $data);
        }

        if ($this->orderRegistersDomain($data['order_type'])) {
            $availability = $this->whmcs->checkDomain($data['domain']);
            if (!$availability['ok']) {
                $this->writeCheckoutLog('Domain availability precheck could not be completed; continuing to WHMCS order validation.', [
                    'order_type' => $data['order_type'],
                    'domain' => $data['domain'],
                    'message' => $availability['message'] ?? '',
                ]);
            } elseif (!$availability['available']) {
                return $this->checkout(['That domain is no longer available. Please search another domain.'], $data);
            }
        }

        $client = $this->prepareWhmcsClientForCheckout($data);

        if (!$client['ok']) {
            return $this->checkout([$this->publicCustomerAccountError($client['message'] ?? '')], $data);
        }

        $orderPayload = $this->buildWhmcsOrderPayload($data);
        if (!$orderPayload['ok']) {
            return $this->checkout([$orderPayload['message']], $data);
        }

        $order = $this->whmcs->addOrder(array_merge([
            'clientid' => (int) $client['client_id'],
        ], $orderPayload['payload']));

        if (!$order['ok']) {
            $retry = $this->retryAddOrderWithMatchedProduct($data, (int) $client['client_id'], $orderPayload, (string) ($order['message'] ?? ''));
            if ($retry['ok']) {
                $order = $retry['order'];
                $orderPayload = $retry['order_payload'];
            }
        }

        if (!$order['ok']) {
            $this->writeCheckoutLog('WHMCS AddOrder failed.', [
                'order_type' => $data['order_type'],
                'domain' => $data['domain'],
                'hosting_plan' => $data['hosting_plan'],
                'billing_cycle' => $data['billing_cycle'],
                'pid' => $orderPayload['debug']['pid'] ?? 0,
                'payload_keys' => array_keys((array) ($orderPayload['payload'] ?? [])),
                'message' => $order['message'] ?? '',
                'response_keys' => array_keys((array) ($order['raw'] ?? [])),
            ]);
            return $this->checkout([$this->publicOrderError($order['message'] ?? '', $data)], $data);
        }

        $invoiceId = (int) ($order['invoice_id'] ?? 0);
        $orderId = (int) ($order['order_id'] ?? 0);
        $expectedAmount = $this->expectedCheckoutAmount($data);
        $invoiceMatchAmount = $this->checkoutInvoiceMatchAmount($data, $expectedAmount);
        if ($invoiceId <= 0) {
            $this->writeCheckoutLog('WHMCS AddOrder returned without an invoice reference.', [
                'order_id' => $orderId,
                'client_id' => (int) $client['client_id'],
                'order_type' => $data['order_type'] ?? '',
                'service_ids' => $order['service_ids'] ?? '',
                'domain_ids' => $order['domain_ids'] ?? '',
                'response_keys' => array_keys((array) ($order['raw'] ?? [])),
            ]);
        }

        if ($invoiceId > 0) {
            $invoice = $this->whmcs->invoiceForClient($invoiceId, (int) $client['client_id'], $orderId);
        } else {
            $invoice = $this->whmcs->invoiceForOrder($orderId, (int) $client['client_id'], $invoiceMatchAmount);
            $invoiceId = (int) ($invoice['invoice']['invoiceid'] ?? $invoice['invoice']['id'] ?? 0);
        }

        if (!$invoice['ok']) {
            $fallbackInvoice = $this->fallbackInvoiceForCheckout($invoiceId, $orderId, (int) $client['client_id'], $data, $invoice['message'] ?? '');
            if (!$fallbackInvoice['ok']) {
                return $this->checkout(['Your order was created, but the secure payment amount could not be loaded. Please contact support.'], $data);
            }
            $invoice = $fallbackInvoice;
        }

        $invoiceData = $invoice['invoice'];
        $invoiceId = (int) ($invoiceData['invoiceid'] ?? $invoiceData['id'] ?? $invoiceId);
        if ($invoiceId <= 0) {
            return $this->checkout(['Your order was created, but the secure payment amount could not be loaded. Please contact support.'], $data);
        }

        $invoiceClientId = $this->invoiceClientId($invoiceData);
        if ($invoiceClientId > 0 && $invoiceClientId !== (int) $client['client_id']) {
            return $this->checkout(['The invoice could not be matched to your account. Please contact support.'], $data);
        }

        $invoiceData = $this->checkoutInvoiceMatchingExpectedAmount($invoiceData, $invoiceId, $orderId, (int) $client['client_id'], $invoiceMatchAmount, $data);
        if (!$invoiceData['ok']) {
            return $this->checkout([$invoiceData['message']], $data);
        }
        $invoiceData = $invoiceData['invoice'];
        $invoiceId = (int) ($invoiceData['invoiceid'] ?? $invoiceData['id'] ?? $invoiceId);

        $amount = $this->invoiceAmountDue($invoiceData);
        if ($amount <= 0) {
            if ($expectedAmount <= 0) {
                return $this->checkout(['This invoice does not have a payable balance. Please contact support if this looks wrong.'], $data);
            }

            $amount = $expectedAmount;
            $this->writeCheckoutLog('Using configured checkout amount because WHMCS invoice balance was empty.', [
                'invoice_id' => $invoiceId,
                'order_id' => $orderId,
                'order_type' => $data['order_type'],
                'amount' => number_format($amount, 2, '.', ''),
            ]);
        }

        $customer = $this->customerForCheckout($data, (int) $client['client_id']);
        if (!$customer['ok']) {
            return $this->checkout([$customer['message']], $data);
        }

        $payment = (new PaymentRepository())->createOrUpdateOrder([
            'customer_user_id' => (int) $customer['user']['id'],
            'whmcs_client_id' => (int) $client['client_id'],
            'whmcs_order_id' => $orderId,
            'whmcs_invoice_id' => $invoiceId,
            'invoice_amount' => $amount,
            'currency' => $this->invoiceCurrency($invoiceData),
        ]);

        $this->redirect(url('/checkout/payment/' . $payment['public_token']));
    }

    public function about(): string
    {
        $page = $this->content->pageBySlug('about');

        return $this->render('site/about', $this->baseData('about', [
            'page' => $page,
            'breadcrumbs' => [
                ['name' => 'Home', 'url' => url('/')],
                ['name' => 'About', 'url' => url('/about')],
            ],
        ], $page));
    }

    public function contact(array $errors = [], array $old = [], bool $sent = false): string
    {
        $page = $this->content->pageBySlug('contact');

        return $this->render('site/contact', $this->baseData('contact', [
            'page' => $page,
            'errors' => $errors,
            'old' => $old,
            'sent' => $sent,
            'breadcrumbs' => [
                ['name' => 'Home', 'url' => url('/')],
                ['name' => 'Contact', 'url' => url('/contact')],
            ],
        ], $page));
    }

    public function submitContact(): string
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            return $this->contact(['Your security token expired. Please submit the form again.'], $_POST);
        }

        $data = [
            'full_name' => trim((string) ($_POST['full_name'] ?? '')),
            'email' => trim((string) ($_POST['email'] ?? '')),
            'phone' => trim((string) ($_POST['phone'] ?? '')),
            'service' => trim((string) ($_POST['service'] ?? '')),
            'message' => trim((string) ($_POST['message'] ?? '')),
        ];

        $errors = [];
        if ($data['full_name'] === '') {
            $errors[] = 'Full name is required.';
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }
        if ($data['service'] === '') {
            $errors[] = 'Please choose a service.';
        }
        if (strlen($data['message']) < 10) {
            $errors[] = 'Please add a short message.';
        }
        if (!RecaptchaService::verify($this->settings, $_POST['g-recaptcha-response'] ?? null)) {
            $errors[] = 'reCAPTCHA verification failed.';
        }

        if ($errors) {
            return $this->contact($errors, $data);
        }

        $this->content->createInquiry([
            ...$data,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
        ]);

        Mailer::inquiry($this->settings, $data);
        return $this->contact([], [], true);
    }

    public function blog(): string
    {
        return $this->render('site/blog', $this->baseData('blog', [
            'posts' => $this->content->posts(true),
            'breadcrumbs' => [
                ['name' => 'Home', 'url' => url('/')],
                ['name' => 'Blog', 'url' => url('/blog')],
            ],
        ]));
    }

    public function blogPost(string $slug): string
    {
        $post = $this->content->postBySlug($slug);
        if (!$post) {
            return $this->notFound();
        }

        return $this->render('site/post', $this->baseData('blog-post', [
            'post' => $post,
            'metaOverride' => [
                'title' => $post['meta_title'] ?: $post['title'],
                'description' => $post['meta_description'] ?: excerpt($post['content']),
                'og_image' => upload_url($post['featured_image'] ?? ''),
            ],
            'breadcrumbs' => [
                ['name' => 'Home', 'url' => url('/')],
                ['name' => 'Blog', 'url' => url('/blog')],
                ['name' => $post['title'], 'url' => url('/blog/' . $post['slug'])],
            ],
        ]));
    }

    public function page(string $slug): string
    {
        $reserved = ['admin', 'assets', 'uploads', 'app', 'database', 'storage'];
        if (in_array($slug, $reserved, true)) {
            return $this->notFound();
        }

        $page = $this->content->pageBySlug($slug);
        if (!$page) {
            return $this->notFound();
        }

        return $this->render('site/page', $this->baseData($slug, [
            'page' => $page,
            'breadcrumbs' => [
                ['name' => 'Home', 'url' => url('/')],
                ['name' => $page['title'], 'url' => url('/' . $page['slug'])],
            ],
        ], $page));
    }

    public function sitemap(): string
    {
        header('Content-Type: application/xml; charset=UTF-8');
        $base = rtrim((string) ($this->settings['app_url'] ?? env('APP_URL', '')), '/');
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        foreach ($this->content->sitemapUrls() as $entry) {
            $xml .= "  <url>\n";
            $xml .= '    <loc>' . e($base . $entry['loc']) . "</loc>\n";
            $xml .= '    <changefreq>' . e($entry['changefreq']) . "</changefreq>\n";
            $xml .= '    <priority>' . e($entry['priority']) . "</priority>\n";
            $xml .= "  </url>\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }

    public function robots(): string
    {
        header('Content-Type: text/plain; charset=UTF-8');
        $base = rtrim((string) ($this->settings['app_url'] ?? env('APP_URL', '')), '/');
        return "User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /app\nDisallow: /database\n\nSitemap: {$base}/sitemap.xml\n";
    }

    public function notFound(): string
    {
        http_response_code(404);
        return $this->render('site/404', $this->baseData('404', [
            'metaOverride' => [
                'title' => 'Page not found | ' . ($this->settings['company_name'] ?? 'Planetic Solutions'),
                'description' => 'The page you are looking for could not be found.',
            ],
        ]));
    }

    private function baseData(string $routeKey, array $data = [], array $pageOverride = []): array
    {
        $seo = $this->content->seo($routeKey);
        $metaOverride = $data['metaOverride'] ?? [];
        unset($data['metaOverride']);

        $title = $metaOverride['title']
            ?? $pageOverride['meta_title']
            ?? $seo['meta_title']
            ?? (($this->settings['company_name'] ?? 'Planetic Solutions') . ' | Web Hosting, Domains & Websites');

        $description = $metaOverride['description']
            ?? $pageOverride['meta_description']
            ?? $seo['meta_description']
            ?? 'Fast, secure and affordable hosting, domains and website development from Planetic Solutions.';

        $canonical = ($pageOverride['canonical_url'] ?? '') ?: ($seo['canonical_url'] ?? '');
        $ogImage = $metaOverride['og_image'] ?? '';
        if (!$ogImage) {
            $ogImage = upload_url($pageOverride['og_image'] ?? '');
        }
        if (!$ogImage) {
            $ogImage = upload_url($seo['og_image'] ?? '');
        }
        if (!$ogImage) {
            $ogImage = upload_url($this->settings['og_image'] ?? '');
        }

        $schemas = array_values(array_filter($data['schemas'] ?? []));
        unset($data['schemas']);

        if (!empty($data['breadcrumbs'])) {
            $schemas[] = $this->breadcrumbSchema($data['breadcrumbs']);
        }

        return [
            ...$data,
            'settings' => $this->settings,
            'whmcs' => $this->whmcs,
            'meta' => [
                'title' => $title,
                'description' => $description,
                'keywords' => $pageOverride['keywords'] ?? $seo['keywords'] ?? '',
                'og_title' => $pageOverride['og_title'] ?? $seo['og_title'] ?? $title,
                'og_image' => $ogImage,
                'canonical_url' => $canonical,
            ],
            'schemas' => $schemas,
            'csrfToken' => Csrf::token(),
            'customerUser' => (new CustomerAuth())->user(),
        ];
    }

    private function customerForCheckout(array $data, int $whmcsClientId): array
    {
        $auth = new CustomerAuth();
        $customers = new CustomerRepository();
        $current = $auth->user();

        if ($current) {
            $user = $customers->find((int) $current['id']);
            if (!$user) {
                return ['ok' => false, 'message' => 'Please log in again to continue.'];
            }
            if (!empty($user['whmcs_client_id']) && (int) $user['whmcs_client_id'] !== $whmcsClientId) {
                return ['ok' => false, 'message' => 'This order could not be matched to your account. Please contact support.'];
            }
            $linked = $customers->setWhmcsClient((int) $user['id'], $whmcsClientId);
            if (!$linked) {
                return ['ok' => false, 'message' => 'This order could not be linked to your account. Please contact support.'];
            }
            $auth->login($linked);
            return ['ok' => true, 'user' => $linked];
        }

        $existing = $customers->findByEmail($data['email']);
        if ($existing) {
            if (!CustomerAuth::passwordMatches((string) $data['password'], (string) ($existing['password_hash'] ?? ''))) {
                return ['ok' => false, 'message' => 'An account already exists for this email. Please log in before continuing.'];
            }
            if (!empty($existing['whmcs_client_id']) && (int) $existing['whmcs_client_id'] !== $whmcsClientId) {
                return ['ok' => false, 'message' => 'This order could not be matched to your account. Please contact support.'];
            }
            $linked = $customers->setWhmcsClient((int) $existing['id'], $whmcsClientId) ?? $existing;
            $auth->login($linked);
            return ['ok' => true, 'user' => $linked];
        }

        $user = $customers->create([
            'whmcs_client_id' => $whmcsClientId,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'phone' => $data['phone'],
            'address' => $data['address'],
            'city' => $data['city'],
            'state' => $data['state'],
            'postcode' => $data['postcode'],
            'country' => $data['country'],
            'password' => $data['password'],
        ]);
        $auth->login($user);

        return ['ok' => true, 'user' => $user];
    }

    private function precheckCustomerAccount(array $data): array
    {
        $auth = new CustomerAuth();
        $current = $auth->user();
        if ($current) {
            return ['ok' => true];
        }

        $existing = (new CustomerRepository())->findByEmail($data['email']);
        if ($existing && !CustomerAuth::passwordMatches((string) $data['password'], (string) ($existing['password_hash'] ?? ''))) {
            return ['ok' => false, 'message' => 'An account already exists for this email. Please log in before continuing.'];
        }

        return ['ok' => true];
    }

    private function prepareWhmcsClientForCheckout(array $data): array
    {
        $current = (new CustomerAuth())->user();
        if ($current) {
            $user = (new CustomerRepository())->find((int) $current['id']);
            if ($user && !empty($user['whmcs_client_id'])) {
                return [
                    'ok' => true,
                    'client_id' => (int) $user['whmcs_client_id'],
                    'created' => false,
                    'source' => 'local_account',
                ];
            }
        }

        return $this->whmcs->createOrFindClient([
            'firstname' => $data['first_name'],
            'lastname' => $data['last_name'],
            'email' => $data['email'],
            'address1' => $data['address'],
            'city' => $data['city'],
            'state' => $data['state'],
            'postcode' => $data['postcode'],
            'country' => $data['country'],
            'phonenumber' => $data['phone'],
            'password2' => $data['password'],
            'clientip' => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);
    }

    private function invoiceAmountDue(array $invoice): float
    {
        foreach (['balance', 'amountpaidremaining', 'total', 'subtotal'] as $key) {
            if (!isset($invoice[$key])) {
                continue;
            }

            $amount = $this->moneyToFloat($invoice[$key]);
            if ($amount > 0) {
                return round($amount, 2);
            }
        }

        return 0.0;
    }

    private function moneyToFloat(mixed $value): float
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

    private function invoiceCurrency(array $invoice): string
    {
        $currency = $invoice['currencycode'] ?? $invoice['currency'] ?? 'GBP';
        if (is_array($currency)) {
            $currency = $currency['code'] ?? $currency['suffix'] ?? 'GBP';
        }

        $currency = strtoupper(preg_replace('/[^A-Z]/i', '', (string) $currency) ?: 'GBP');
        return strlen($currency) === 3 ? $currency : 'GBP';
    }

    private function invoiceClientId(array $invoice): int
    {
        return (int) ($invoice['userid'] ?? $invoice['clientid'] ?? $invoice['user_id'] ?? 0);
    }

    private function checkoutInvoiceMatchAmount(array $data, float $expectedAmount): float
    {
        $type = (string) ($data['order_type'] ?? '');
        if ($type === 'domain') {
            return 0.0;
        }

        if ($type !== 'bundle') {
            return $expectedAmount;
        }

        $plan = $this->checkoutPlanBySlug((string) ($data['hosting_plan'] ?? ''));
        if (!$plan) {
            return $expectedAmount;
        }

        $domainAmount = $this->liveDomainPriceAmount((string) ($data['domain'] ?? ''));
        if ($domainAmount <= 0) {
            $domainAmount = $this->domainPriceAmount((string) ($data['domain'] ?? ''));
        }

        $hostingAmount = $this->planPriceAmount($plan, (string) ($data['billing_cycle'] ?? 'monthly'));
        return round($domainAmount + $hostingAmount, 2);
    }

    private function checkoutInvoiceMatchingExpectedAmount(array $invoice, int $invoiceId, int $orderId, int $clientId, float $expectedAmount, array $data): array
    {
        if ($expectedAmount <= 0) {
            return ['ok' => true, 'invoice' => $invoice];
        }

        $status = strtolower((string) ($invoice['status'] ?? ''));
        $amount = $this->invoiceAmountDue($invoice);
        if ($status !== 'paid' && ($amount <= 0 || $this->moneyAmountsMatch($amount, $expectedAmount))) {
            return ['ok' => true, 'invoice' => $invoice];
        }

        $this->writeCheckoutLog('Checkout invoice did not match selected package amount.', [
            'invoice_id' => $invoiceId,
            'order_id' => $orderId,
            'client_id' => $clientId,
            'order_type' => $data['order_type'] ?? '',
            'hosting_plan' => $data['hosting_plan'] ?? '',
            'invoice_status' => $invoice['status'] ?? '',
            'invoice_amount' => number_format($amount, 2, '.', ''),
            'expected_amount' => number_format($expectedAmount, 2, '.', ''),
        ]);

        if ($orderId > 0) {
            $matched = $this->whmcs->invoiceForOrder($orderId, $clientId, $expectedAmount);
            if ($matched['ok']) {
                $matchedInvoice = (array) ($matched['invoice'] ?? []);
                $matchedStatus = strtolower((string) ($matchedInvoice['status'] ?? ''));
                $matchedAmount = $this->invoiceAmountDue($matchedInvoice);
                if ($matchedStatus !== 'paid' && $this->moneyAmountsMatch($matchedAmount, $expectedAmount)) {
                    $this->writeCheckoutLog('Using WHMCS order invoice matched by selected package amount.', [
                        'original_invoice_id' => $invoiceId,
                        'matched_invoice_id' => (int) ($matchedInvoice['invoiceid'] ?? $matchedInvoice['id'] ?? 0),
                        'order_id' => $orderId,
                        'amount' => number_format($matchedAmount, 2, '.', ''),
                    ]);

                    return ['ok' => true, 'invoice' => $matchedInvoice];
                }

                $this->writeCheckoutLog('WHMCS order invoice lookup returned a non-matching invoice.', [
                    'invoice_id' => (int) ($matchedInvoice['invoiceid'] ?? $matchedInvoice['id'] ?? 0),
                    'order_id' => $orderId,
                    'invoice_status' => $matchedInvoice['status'] ?? '',
                    'invoice_amount' => number_format($matchedAmount, 2, '.', ''),
                    'expected_amount' => number_format($expectedAmount, 2, '.', ''),
                ]);
            } else {
                $this->writeCheckoutLog('Unable to resolve a matching WHMCS order invoice.', [
                    'invoice_id' => $invoiceId,
                    'order_id' => $orderId,
                    'client_id' => $clientId,
                    'expected_amount' => number_format($expectedAmount, 2, '.', ''),
                    'message' => $matched['message'] ?? '',
                ]);
            }
        }

        return [
            'ok' => false,
            'message' => 'Your order was created, but the billing invoice amount did not match your selected package. Please contact support before paying.',
        ];
    }

    private function moneyAmountsMatch(float $first, float $second): bool
    {
        return abs(round($first, 2) - round($second, 2)) <= 0.01;
    }

    private function fallbackInvoiceForCheckout(int $invoiceId, int $orderId, int $clientId, array $data, string $message = ''): array
    {
        $expectedAmount = $this->expectedCheckoutAmount($data);
        if ($invoiceId <= 0 || $clientId <= 0 || $expectedAmount <= 0) {
            $this->writeCheckoutLog('Unable to build checkout invoice fallback.', [
                'invoice_id' => $invoiceId,
                'order_id' => $orderId,
                'client_id' => $clientId,
                'order_type' => $data['order_type'] ?? '',
                'expected_amount' => number_format($expectedAmount, 2, '.', ''),
                'message' => $message,
            ]);

            return ['ok' => false, 'message' => $message ?: 'Unable to load invoice amount.'];
        }

        $this->writeCheckoutLog('Using configured checkout amount because WHMCS invoice lookup failed.', [
            'invoice_id' => $invoiceId,
            'order_id' => $orderId,
            'client_id' => $clientId,
            'order_type' => $data['order_type'] ?? '',
            'amount' => number_format($expectedAmount, 2, '.', ''),
            'message' => $message,
        ]);

        return [
            'ok' => true,
            'fallback' => 'configured_checkout_amount',
            'invoice' => [
                'invoiceid' => $invoiceId,
                'id' => $invoiceId,
                'userid' => $clientId,
                'clientid' => $clientId,
                'orderid' => $orderId,
                'balance' => number_format($expectedAmount, 2, '.', ''),
                'total' => number_format($expectedAmount, 2, '.', ''),
                'status' => 'Unpaid',
                'currency' => ['code' => 'GBP'],
            ],
        ];
    }

    private function rowsFromSetting(string $key, array $fallback): array
    {
        $raw = trim((string) ($this->settings[$key] ?? ''));
        if ($raw === '') {
            return $fallback;
        }

        $rows = [];
        foreach (preg_split('/\R/', $raw) ?: [] as $line) {
            $parts = array_map('trim', explode('|', $line));
            if (!empty($parts[0])) {
                $rows[] = [$parts[0], $parts[1] ?? '', $parts[2] ?? 'check', $parts[3] ?? ''];
            }
        }

        return $rows ?: $fallback;
    }

    private function checkoutPlans(): array
    {
        $config = $this->whmcs->checkoutConfig();
        $configuredProducts = $config['hosting_products'] ?? [];

        return array_map(function (array $plan) use ($configuredProducts, $config): array {
            $slug = (string) $plan['slug'];
            $configured = $configuredProducts[$slug] ?? [];
            $pid = $this->pidFromUrl((string) ($plan['whmcs_url'] ?? ''));
            if ($pid <= 0) {
                $pid = (int) ($configured['pid'] ?? 0);
            }

            $plan['checkout_pid'] = $pid;
            $plan['checkout_billing_cycle'] = (string) ($configured['billing_cycle'] ?? $config['default_billing_cycle'] ?? 'monthly');
            return $plan;
        }, $this->content->hostingPlans(null, true));
    }

    private function checkoutPlanBySlug(string $slug): ?array
    {
        foreach ($this->checkoutPlans() as $plan) {
            if ($plan['slug'] === $slug) {
                return $plan;
            }
        }

        return null;
    }

    private function validateCheckout(array $input): array
    {
        $currentCustomer = (new CustomerAuth())->user();
        $phone = $this->normalisePhone((string) ($input['phone'] ?? ''), (string) ($input['country'] ?? 'GB'));
        $postcode = $this->normalisePostcode((string) ($input['postcode'] ?? ''));
        $data = [
            'order_type' => trim((string) ($input['order_type'] ?? '')),
            'domain' => $this->normaliseDomain((string) ($input['domain'] ?? '')),
            'hosting_plan' => trim((string) ($input['hosting_plan'] ?? '')),
            'billing_cycle' => $this->normaliseBillingCycle((string) ($input['billing_cycle'] ?? '')),
            'first_name' => trim((string) ($input['first_name'] ?? '')),
            'last_name' => trim((string) ($input['last_name'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'phone' => $phone,
            'address' => trim((string) ($input['address'] ?? '')),
            'city' => trim((string) ($input['city'] ?? '')),
            'state' => trim((string) ($input['state'] ?? '')),
            'postcode' => $postcode,
            'country' => strtoupper(trim((string) ($input['country'] ?? 'GB'))),
            'password' => (string) ($input['password'] ?? ''),
        ];

        if ($currentCustomer) {
            $data['email'] = (string) ($currentCustomer['email'] ?? $data['email']);
            $data['password'] = '';
        }

        $errors = [];
        if (!in_array($data['order_type'], ['domain', 'hosting', 'bundle', 'website'], true)) {
            $errors[] = 'Choose a valid order type.';
        }

        if ($this->orderRegistersDomain($data['order_type']) && $data['domain'] === '') {
            $errors[] = 'Enter a valid domain name.';
        }

        if ($data['domain'] !== '' && !preg_match('/^[a-z0-9][a-z0-9.-]+\.[a-z.]{2,}$/', $data['domain'])) {
            $errors[] = 'Enter a valid domain name.';
        }

        if ($this->orderRegistersDomain($data['order_type']) && !$this->splitDomain($data['domain'])) {
            $errors[] = 'Choose a supported domain extension.';
        }

        if (in_array($data['order_type'], ['hosting', 'bundle'], true)) {
            $plan = $this->checkoutPlanBySlug($data['hosting_plan']);
            if (!$plan || (int) ($plan['checkout_pid'] ?? 0) <= 0) {
                $errors[] = 'Choose a valid hosting package.';
            }
        }

        foreach (['first_name' => 'First name', 'last_name' => 'Last name', 'phone' => 'Phone', 'address' => 'Address', 'city' => 'City', 'state' => 'State', 'postcode' => 'Postcode'] as $key => $label) {
            if ($data[$key] === '') {
                $errors[] = $label . ' is required.';
            }
        }

        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'A valid email address is required.';
        }

        if (!preg_match('/^[A-Z]{2}$/', $data['country'])) {
            $errors[] = 'Country must be a two-letter ISO code, for example GB.';
        }

        $phoneDigits = preg_replace('/\D+/', '', $data['phone']);
        if (strlen((string) $phoneDigits) < 7 || strlen((string) $phoneDigits) > 15) {
            $errors[] = 'Enter a valid phone number.';
        }

        if (!$currentCustomer && strlen($data['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        return [$data, $errors];
    }

    private function applyLoggedInCustomerDefaults(array $old): array
    {
        $current = (new CustomerAuth())->user();
        if (!$current) {
            return $old;
        }

        $full = (new CustomerRepository())->find((int) $current['id']);
        if (!$full) {
            return $old;
        }

        $defaults = [
            'first_name' => $full['first_name'] ?? '',
            'last_name' => $full['last_name'] ?? '',
            'email' => $full['email'] ?? '',
            'phone' => $full['phone'] ?? '',
            'address' => $full['address'] ?? '',
            'city' => $full['city'] ?? '',
            'state' => $full['state'] ?? '',
            'postcode' => $full['postcode'] ?? '',
            'country' => $full['country'] ?? 'GB',
        ];

        foreach ($defaults as $key => $value) {
            if ($value !== '') {
                $old[$key] = $value;
            }
        }

        $old['email'] = (string) ($defaults['email'] ?: ($current['email'] ?? ''));
        return $old;
    }

    private function normalisePhone(string $phone, string $country): string
    {
        $country = strtoupper(trim($country ?: 'GB'));
        $callingCodes = [
            'GB' => '44',
            'US' => '1',
            'CA' => '1',
            'PK' => '92',
            'IE' => '353',
            'AU' => '61',
        ];

        $clean = preg_replace('/[^\d+]+/', '', trim($phone)) ?: '';
        if (str_starts_with($clean, '00')) {
            $clean = '+' . substr($clean, 2);
        }

        $digits = preg_replace('/\D+/', '', $clean) ?: '';
        $callingCode = $callingCodes[$country] ?? '';
        if ($callingCode !== '' && !str_starts_with($clean, '+')) {
            if (str_starts_with($digits, '0')) {
                $digits = $callingCode . ltrim($digits, '0');
            } elseif (!str_starts_with($digits, $callingCode)) {
                $digits = $callingCode . $digits;
            }
            $clean = '+' . $digits;
        } elseif ($clean !== '' && !str_starts_with($clean, '+')) {
            $clean = $digits;
        }

        return substr($clean, 0, 20);
    }

    private function normalisePostcode(string $postcode): string
    {
        $postcode = strtoupper(trim($postcode));
        $postcode = preg_replace('/[^A-Z0-9 ]+/', '', $postcode) ?: '';
        $postcode = preg_replace('/\s+/', ' ', $postcode) ?: '';
        return substr(trim($postcode), 0, 20);
    }

    private function publicCustomerAccountError(string $message): string
    {
        $lower = strtolower($message);
        if (str_contains($lower, 'phone') || str_contains($lower, 'telephone')) {
            return 'Please enter a valid phone number with country code, then try again.';
        }
        if (str_contains($lower, 'postcode') || str_contains($lower, 'postal')) {
            return 'Please enter a valid postcode using only letters, numbers and spaces.';
        }
        if (str_contains($lower, 'email') && (str_contains($lower, 'exists') || str_contains($lower, 'available'))) {
            return 'An account already exists with this email address. Please log in or use a different email.';
        }

        return 'Unable to prepare your customer account. Please check your details and try again.';
    }

    private function expectedCheckoutAmount(array $data): float
    {
        $type = (string) ($data['order_type'] ?? '');
        $amount = 0.0;

        if ($type === 'website') {
            $amount += $this->configuredWebsitePrice();
            if ($this->websiteChargesDomain() && !empty($data['domain'])) {
                $amount += $this->domainPriceAmount((string) $data['domain']);
            }

            return round($amount, 2);
        }

        if (in_array($type, ['domain', 'bundle'], true) && !empty($data['domain'])) {
            $amount += $this->domainPriceAmount((string) $data['domain']);
        }

        if (in_array($type, ['hosting', 'bundle'], true)) {
            $plan = $this->checkoutPlanBySlug((string) ($data['hosting_plan'] ?? ''));
            if ($plan) {
                $amount += $this->planPriceAmount($plan, (string) ($data['billing_cycle'] ?? 'monthly'));
            }
        }

        return round($amount, 2);
    }

    private function configuredWebsitePrice(): float
    {
        $config = $this->whmcs->checkoutConfig()['website_package'] ?? [];
        $package = $this->content->package();
        return $this->moneyToFloat($config['price_override'] ?? $package['price'] ?? '199.00');
    }

    private function websiteChargesDomain(): bool
    {
        $config = $this->whmcs->checkoutConfig()['website_package'] ?? [];
        if (array_key_exists('domain_price_override', $config)) {
            return $this->moneyToFloat($config['domain_price_override']) > 0;
        }

        return false;
    }

    private function domainPriceAmount(string $domain): float
    {
        $parsed = $this->splitDomain($domain);
        if (!$parsed) {
            return 0.0;
        }

        return $this->moneyToFloat($this->configuredDomainPrice($parsed['tld']) ?? '0.00');
    }

    private function liveDomainPriceAmount(string $domain): float
    {
        $parsed = $this->splitDomain($domain);
        if (!$parsed) {
            return 0.0;
        }

        $config = $this->whmcs->checkoutConfig();
        $pricing = $this->whmcs->tldPricing((int) ($config['currency_id'] ?? 1));
        if (!$pricing['ok']) {
            return 0.0;
        }

        return $this->moneyToFloat($this->whmcs->priceForTld((array) ($pricing['pricing'] ?? []), $parsed['tld']) ?? '0.00');
    }

    private function planPriceAmount(array $plan, string $billingCycle): float
    {
        $billingCycle = $this->normaliseBillingCycle($billingCycle) ?: 'monthly';
        $monthly = $this->moneyToFloat($plan['monthly_price'] ?? '0.00');
        $yearly = $this->moneyToFloat($plan['yearly_price'] ?? '0.00');

        if ($billingCycle === 'annually') {
            return $yearly > 0 ? $yearly : $monthly * 12;
        }

        return $monthly;
    }

    private function publicCheckoutFailureMessage(\Throwable $exception): string
    {
        $message = strtolower($exception->getMessage());
        if (str_contains($message, 'sqlstate') || str_contains($message, 'base table') || str_contains($message, 'customer_orders') || str_contains($message, 'customer_users') || str_contains($message, 'stripe')) {
            return 'Secure payment setup is not fully installed yet. Please contact support.';
        }

        return 'We could not prepare your secure payment right now. Please check the selected package or contact support.';
    }

    private function retryAddOrderWithMatchedProduct(array $data, int $clientId, array $orderPayload, string $originalMessage): array
    {
        if (!in_array((string) ($data['order_type'] ?? ''), ['hosting', 'bundle'], true)) {
            return ['ok' => false];
        }

        if (!$this->looksLikeProductMappingError($originalMessage)) {
            return ['ok' => false];
        }

        $plan = $this->checkoutPlanBySlug((string) ($data['hosting_plan'] ?? ''));
        if (!$plan) {
            return ['ok' => false];
        }

        $matchedPid = $this->matchedWhmcsProductPid((string) ($plan['title'] ?? ''));
        $currentPid = (int) ($orderPayload['debug']['pid'] ?? 0);
        if ($matchedPid <= 0 || $matchedPid === $currentPid) {
            return ['ok' => false];
        }

        $retryPayload = (array) ($orderPayload['payload'] ?? []);
        $retryPayload['pid'] = [$matchedPid];
        $retryOrderPayload = $orderPayload;
        $retryOrderPayload['payload'] = $retryPayload;
        $retryOrderPayload['debug']['pid'] = $matchedPid;
        $retryOrderPayload['debug']['pid_source'] = 'whmcs_product_name_match';

        $this->writeCheckoutLog('Retrying WHMCS AddOrder with product matched by WHMCS product name.', [
            'order_type' => $data['order_type'],
            'hosting_plan' => $data['hosting_plan'],
            'plan_title' => $plan['title'] ?? '',
            'original_pid' => $currentPid,
            'matched_pid' => $matchedPid,
            'original_message' => $originalMessage,
        ]);

        $retryOrder = $this->whmcs->addOrder(array_merge([
            'clientid' => $clientId,
        ], $retryPayload));

        if (!$retryOrder['ok']) {
            $this->writeCheckoutLog('WHMCS AddOrder retry with matched product failed.', [
                'order_type' => $data['order_type'],
                'hosting_plan' => $data['hosting_plan'],
                'matched_pid' => $matchedPid,
                'message' => $retryOrder['message'] ?? '',
                'response_keys' => array_keys((array) ($retryOrder['raw'] ?? [])),
            ]);

            return ['ok' => false];
        }

        return [
            'ok' => true,
            'order' => $retryOrder,
            'order_payload' => $retryOrderPayload,
        ];
    }

    private function looksLikeProductMappingError(string $message): bool
    {
        $message = strtolower($message);
        return str_contains($message, 'product')
            || str_contains($message, 'pid')
            || str_contains($message, 'package')
            || str_contains($message, 'not found');
    }

    private function matchedWhmcsProductPid(string $title): int
    {
        $needle = $this->normaliseProductTitle($title);
        if ($needle === '') {
            return 0;
        }

        $products = $this->whmcs->products();
        if (!$products['ok']) {
            $this->writeCheckoutLog('Unable to fetch WHMCS products for checkout PID fallback.', [
                'plan_title' => $title,
                'message' => $products['message'] ?? '',
            ]);

            return 0;
        }

        foreach ($this->normaliseApiRows($products['products'] ?? []) as $product) {
            $candidate = $this->normaliseProductTitle((string) ($product['name'] ?? $product['productname'] ?? $product['title'] ?? ''));
            if ($candidate !== $needle) {
                continue;
            }

            return (int) ($product['pid'] ?? $product['id'] ?? 0);
        }

        return 0;
    }

    private function normaliseProductTitle(string $title): string
    {
        $title = strtolower(trim($title));
        $title = preg_replace('/[^a-z0-9]+/', ' ', $title) ?: '';
        return trim(preg_replace('/\s+/', ' ', $title) ?: '');
    }

    private function normaliseApiRows(mixed $rows): array
    {
        if (!is_array($rows) || $rows === []) {
            return [];
        }

        if (array_keys($rows) === range(0, count($rows) - 1)) {
            return $rows;
        }

        return [$rows];
    }

    private function publicOrderError(string $message, array $data): string
    {
        $lower = strtolower($message);
        if (str_contains($lower, 'payment method') || str_contains($lower, 'paymentmethod') || str_contains($lower, 'gateway')) {
            return 'The billing payment method is not configured correctly yet. Please contact support.';
        }

        if (str_contains($lower, 'product') || str_contains($lower, 'pid') || str_contains($lower, 'package') || str_contains($lower, 'not found')) {
            return 'This hosting package is not connected to an active billing product yet. Please choose another package or contact support.';
        }

        if (str_contains($lower, 'tld') || str_contains($lower, 'registration period') || str_contains($lower, 'domain registration')) {
            return 'This domain extension is not configured for registration yet. Please choose another domain or contact support.';
        }

        if (str_contains($lower, 'domain')) {
            return 'Domain registration could not be created right now. Please check the domain extension or contact support.';
        }

        if (($data['order_type'] ?? '') === 'domain') {
            return 'Domain registration is not fully configured yet. Please contact support.';
        }

        if (in_array((string) ($data['order_type'] ?? ''), ['hosting', 'bundle'], true)) {
            return 'The selected hosting package could not be ordered right now. Please choose another package or contact support.';
        }

        return 'Unable to create your order right now. Please contact support.';
    }

    private function logCheckoutFailure(\Throwable $exception, array $input): void
    {
        $context = [
            'type' => get_class($exception),
            'message' => $exception->getMessage(),
            'file' => basename($exception->getFile()),
            'line' => $exception->getLine(),
            'request' => $this->safeCheckoutContext($input),
        ];

        $this->writeCheckoutLog('Checkout submission failed.', $context);
    }

    private function writeCheckoutLog(string $message, array $context = []): void
    {
        $logDir = STORAGE_PATH . '/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        @file_put_contents(
            $logDir . '/checkout.log',
            '[' . date('c') . '] ' . $message . ' ' . json_encode($context, JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );
    }

    private function safeCheckoutContext(array $input): array
    {
        $allowed = ['order_type', 'domain', 'hosting_plan', 'billing_cycle', 'email', 'country'];
        $context = [];
        foreach ($allowed as $key) {
            if (isset($input[$key])) {
                $context[$key] = substr((string) $input[$key], 0, 120);
            }
        }

        return $context;
    }

    private function buildWhmcsOrderPayload(array $data): array
    {
        $config = $this->whmcs->checkoutConfig();
        $payload = [];
        $domain = $data['domain'];
        $registrationPeriod = $domain !== '' ? $this->domainRegistrationPeriod($domain) : 1;

        if ($data['order_type'] === 'domain') {
            $payload['domain'] = [$domain];
            $payload['domaintype'] = ['register'];
            $payload['regperiod'] = [$registrationPeriod];
            return [
                'ok' => true,
                'payload' => $this->appendNameservers($payload),
                'debug' => [
                    'kind' => 'domain',
                    'pid' => 0,
                    'registration_period' => $registrationPeriod,
                ],
            ];
        }

        if (in_array($data['order_type'], ['hosting', 'bundle'], true)) {
            $plan = $this->checkoutPlanBySlug($data['hosting_plan']);
            $pid = (int) ($plan['checkout_pid'] ?? 0);
            if ($pid <= 0) {
                return ['ok' => false, 'message' => 'This hosting package is not ready for checkout yet.'];
            }

            $payload['pid'] = [$pid];
            $payload['billingcycle'] = [$data['billing_cycle'] ?: ($plan['checkout_billing_cycle'] ?? 'monthly')];
            $payload['priceoverride'] = [number_format($this->planPriceAmount($plan, (string) ($payload['billingcycle'][0] ?? 'monthly')), 2, '.', '')];

            if ($data['order_type'] === 'bundle') {
                $payload['domain'] = [$domain];
                $payload['domaintype'] = ['register'];
                $payload['regperiod'] = [$registrationPeriod];
            } elseif ($domain !== '') {
                $payload['domain'] = [$domain];
            }

            return [
                'ok' => true,
                'payload' => $this->appendNameservers($payload),
                'debug' => [
                    'kind' => $data['order_type'],
                    'plan' => (string) ($plan['slug'] ?? ''),
                    'pid' => $pid,
                    'billing_cycle' => (string) ($payload['billingcycle'][0] ?? ''),
                    'registration_period' => $registrationPeriod,
                ],
            ];
        }

        $website = $config['website_package'] ?? [];
        $pid = (int) ($website['pid'] ?? 0);
        if ($pid <= 0) {
            return ['ok' => false, 'message' => 'The website package is not ready for checkout yet.'];
        }

        $payload['pid'] = [$pid];
        $cycle = (string) ($website['billing_cycle'] ?? '');
        if ($cycle !== '') {
            $payload['billingcycle'] = [$cycle];
        }

        if (!empty($website['register_domain'])) {
            $payload['domain'] = [$domain];
            $payload['domaintype'] = ['register'];
            $payload['regperiod'] = [$registrationPeriod];
            if (array_key_exists('domain_price_override', $website)) {
                $payload['domainpriceoverride'] = [(string) $website['domain_price_override']];
            }
        } elseif ($domain !== '') {
            $payload['domain'] = [$domain];
        }

        if (array_key_exists('price_override', $website) && (string) $website['price_override'] !== '') {
            $payload['priceoverride'] = [(string) $website['price_override']];
        }

        return [
            'ok' => true,
            'payload' => $this->appendNameservers($payload),
            'debug' => [
                'kind' => 'website',
                'pid' => $pid,
                'billing_cycle' => (string) ($payload['billingcycle'][0] ?? 'onetime'),
                'registration_period' => $registrationPeriod,
            ],
        ];
    }

    private function orderRegistersDomain(string $type): bool
    {
        if (in_array($type, ['domain', 'bundle'], true)) {
            return true;
        }

        if ($type !== 'website') {
            return false;
        }

        return !empty($this->whmcs->checkoutConfig()['website_package']['register_domain']);
    }

    private function appendNameservers(array $payload): array
    {
        $nameservers = $this->whmcs->checkoutConfig()['nameservers'] ?? [];
        foreach (array_slice($nameservers, 0, 5) as $index => $nameserver) {
            $payload['nameserver' . ($index + 1)] = $nameserver;
        }

        return $payload;
    }

    private function pidFromUrl(string $url): int
    {
        $query = parse_url($url, PHP_URL_QUERY);
        if (!$query) {
            return 0;
        }

        parse_str($query, $params);
        return (int) ($params['pid'] ?? 0);
    }

    private function normaliseBillingCycle(string $cycle): string
    {
        $cycle = strtolower(trim($cycle));
        return match ($cycle) {
            'annual', 'annually', 'year', 'yearly' => 'annually',
            'monthly' => 'monthly',
            default => '',
        };
    }

    private function supportedTlds(): array
    {
        $pricing = $this->whmcs->checkoutConfig()['domain_pricing'] ?? [];
        $tlds = array_keys($pricing);
        return $tlds ?: ['.co.uk', '.online', '.com', '.net', '.org', '.xyz'];
    }

    private function domainRegistrationPeriod(string $domain): int
    {
        $parsed = $this->splitDomain($domain);
        $pricing = $this->whmcs->checkoutConfig()['domain_pricing'] ?? [];
        return max(1, (int) ($pricing[$parsed['tld'] ?? '']['regperiod'] ?? 1));
    }

    private function configuredDomainPrice(string $tld): ?string
    {
        $pricing = $this->whmcs->checkoutConfig()['domain_pricing'] ?? [];
        $price = $pricing[$tld]['price'] ?? null;
        return $price === null || $price === '' ? null : number_format((float) $price, 2, '.', '');
    }

    private function normaliseDomain(string $domain): string
    {
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^https?://#', '', $domain) ?: $domain;
        $domain = preg_replace('#/.*$#', '', $domain) ?: $domain;
        $domain = preg_replace('/[^a-z0-9.-]/', '', $domain) ?: '';
        return trim($domain, '.-');
    }

    private function splitDomain(string $domain): ?array
    {
        $supported = $this->supportedTlds();
        usort($supported, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        foreach ($supported as $tld) {
            if (!str_ends_with($domain, $tld)) {
                continue;
            }

            $sld = substr($domain, 0, -strlen($tld));
            if (str_contains($sld, '.') || !preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $sld)) {
                return null;
            }

            return ['sld' => $sld, 'tld' => $tld];
        }

        return null;
    }

    private function hostingPid(): string
    {
        $plans = $this->content->hostingPlans(null, true);
        usort($plans, static fn (array $a, array $b): int => (int) $b['is_highlighted'] <=> (int) $a['is_highlighted']);

        foreach ($plans as $plan) {
            $query = parse_url($plan['whmcs_url'] ?? '', PHP_URL_QUERY);
            if (!$query) {
                continue;
            }

            parse_str($query, $params);
            if (!empty($params['pid'])) {
                return (string) $params['pid'];
            }
        }

        $configuredPid = trim((string) ($this->settings['domain_hosting_pid'] ?? ''));
        return $configuredPid !== '' ? $configuredPid : env('DOMAIN_HOSTING_PID', 'HOSTING_PID_HERE');
    }

    private function currencyPrefix(array $currency): string
    {
        $prefix = (string) ($currency['prefix'] ?? '');
        return $prefix !== '' ? html_entity_decode($prefix, ENT_QUOTES, 'UTF-8') : '£';
    }

    private function json(array $payload, int $status = 200): string
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=UTF-8');
        return json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    private function serviceSchema(string $name, string $description): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            'name' => $name,
            'description' => $description,
            'provider' => [
                '@type' => 'Organization',
                'name' => $this->settings['company_name'] ?? 'Planetic Solutions',
                'url' => $this->settings['app_url'] ?? env('APP_URL', ''),
            ],
        ];
    }

    private function faqSchema(array $faqs): array
    {
        if (!$faqs) {
            return [];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(static fn (array $faq): array => [
                '@type' => 'Question',
                'name' => $faq['question'],
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => strip_tags($faq['answer']),
                ],
            ], $faqs),
        ];
    }

    private function plansSchema(array $plans): array
    {
        if (!$plans) {
            return [];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'ItemList',
            'itemListElement' => array_map(static fn (array $plan, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'item' => [
                    '@type' => 'Service',
                    'name' => $plan['title'],
                    'description' => $plan['description'],
                    'offers' => [
                        '@type' => 'Offer',
                        'priceCurrency' => 'GBP',
                        'price' => preg_replace('/[^0-9.]/', '', (string) $plan['monthly_price']),
                        'url' => url('/checkout?' . http_build_query(['type' => 'hosting', 'plan' => $plan['slug']])),
                    ],
                ],
            ], $plans, array_keys($plans)),
        ];
    }

    private function breadcrumbSchema(array $breadcrumbs): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => array_map(static fn (array $item, int $index): array => [
                '@type' => 'ListItem',
                'position' => $index + 1,
                'name' => $item['name'],
                'item' => $item['url'],
            ], $breadcrumbs, array_keys($breadcrumbs)),
        ];
    }
}
