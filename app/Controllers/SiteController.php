<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Csrf;
use App\Models\ContentRepository;
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
                ['Reseller Hosting', 'Sell hosting under your own brand with WHMCS-ready order links.', 'globe', '/hosting#reseller-hosting'],
                ['Domain Registration', 'Search and register domains through the main website checkout.', 'shield', '/domains'],
                ['Website Development', 'Complete business websites delivered fast with hosting setup included.', 'code', '/website-development'],
                ['Cloudflare CDN Setup', 'Performance and security tuning with Cloudflare CDN configuration.', 'bolt', '/contact'],
            ]),
            'trustBadges' => $this->rowsFromSetting('home_trust_badges', [
                ['Free SSL', 'Secure every eligible hosting plan.'],
                ['cPanel Hosting', 'Familiar website and email control.'],
                ['WHMCS Billing', 'Orders, renewals and invoices handled.'],
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
                ['Business Support', 'Clear support pathways through WHMCS, contact forms and direct service inquiry flows.', 'mail'],
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
                $this->serviceSchema('Domain Registration', 'Domain search and registration on the main website with WHMCS-backed billing.'),
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
                'description' => 'Order domains, hosting and website development through the Planetic Solutions website with WHMCS-backed billing.',
            ],
        ]));
    }

    private function publicWhmcsErrorMessage(string $message): string
    {
        $message = strtolower($message);
        if (str_contains($message, 'invalid ip') || str_contains($message, 'api access')) {
            return 'Live WHMCS domain availability is blocked by WHMCS API IP access settings. Please contact support or try again shortly.';
        }

        return 'Live WHMCS domain availability could not be checked. Please try again shortly.';
    }

    public function submitCheckout(): string
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            return $this->checkout(['Your security token expired. Please submit the form again.'], $_POST);
        }

        [$data, $errors] = $this->validateCheckout($_POST);
        if ($errors) {
            return $this->checkout($errors, $data);
        }

        if ($this->orderRegistersDomain($data['order_type'])) {
            $availability = $this->whmcs->checkDomain($data['domain']);
            if (!$availability['ok']) {
                return $this->checkout([
                    $this->publicWhmcsErrorMessage($availability['message'] ?? ''),
                ], $data);
            }

            if (!$availability['available']) {
                return $this->checkout(['That domain is no longer available. Please search another domain.'], $data);
            }
        }

        $client = $this->whmcs->createOrFindClient([
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

        if (!$client['ok']) {
            return $this->checkout([$client['message'] ?? 'Unable to create or find the WHMCS client.'], $data);
        }

        $orderPayload = $this->buildWhmcsOrderPayload($data);
        if (!$orderPayload['ok']) {
            return $this->checkout([$orderPayload['message']], $data);
        }

        $order = $this->whmcs->addOrder(array_merge([
            'clientid' => (int) $client['client_id'],
        ], $orderPayload['payload']));

        if (!$order['ok']) {
            return $this->checkout([$order['message'] ?? 'Unable to create the WHMCS order.'], $data);
        }

        if (!empty($order['invoice_id'])) {
            $this->redirect($this->whmcs->invoiceUrl((int) $order['invoice_id']));
        }

        return $this->checkout(['Order created, but WHMCS did not return an invoice ID. Please contact support.'], $data);
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
            $pid = (int) ($configured['pid'] ?? 0);
            if ($pid <= 0) {
                $pid = $this->pidFromUrl((string) ($plan['whmcs_url'] ?? ''));
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
        $data = [
            'order_type' => trim((string) ($input['order_type'] ?? '')),
            'domain' => $this->normaliseDomain((string) ($input['domain'] ?? '')),
            'hosting_plan' => trim((string) ($input['hosting_plan'] ?? '')),
            'billing_cycle' => $this->normaliseBillingCycle((string) ($input['billing_cycle'] ?? '')),
            'first_name' => trim((string) ($input['first_name'] ?? '')),
            'last_name' => trim((string) ($input['last_name'] ?? '')),
            'email' => trim((string) ($input['email'] ?? '')),
            'phone' => trim((string) ($input['phone'] ?? '')),
            'address' => trim((string) ($input['address'] ?? '')),
            'city' => trim((string) ($input['city'] ?? '')),
            'state' => trim((string) ($input['state'] ?? '')),
            'postcode' => trim((string) ($input['postcode'] ?? '')),
            'country' => strtoupper(trim((string) ($input['country'] ?? 'GB'))),
            'password' => (string) ($input['password'] ?? ''),
        ];

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

        if (strlen($data['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }

        return [$data, $errors];
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
            return ['ok' => true, 'payload' => $this->appendNameservers($payload)];
        }

        if (in_array($data['order_type'], ['hosting', 'bundle'], true)) {
            $plan = $this->checkoutPlanBySlug($data['hosting_plan']);
            $pid = (int) ($plan['checkout_pid'] ?? 0);
            if ($pid <= 0) {
                return ['ok' => false, 'message' => 'Hosting product ID is not configured.'];
            }

            $payload['pid'] = [$pid];
            $payload['billingcycle'] = [$data['billing_cycle'] ?: ($plan['checkout_billing_cycle'] ?? 'monthly')];

            if ($data['order_type'] === 'bundle') {
                $payload['domain'] = [$domain];
                $payload['domaintype'] = ['register'];
                $payload['regperiod'] = [$registrationPeriod];
            } elseif ($domain !== '') {
                $payload['domain'] = [$domain];
            }

            return ['ok' => true, 'payload' => $this->appendNameservers($payload)];
        }

        $website = $config['website_package'] ?? [];
        $pid = (int) ($website['pid'] ?? 0);
        if ($pid <= 0) {
            return ['ok' => false, 'message' => 'Website package product ID is not configured.'];
        }

        $payload['pid'] = [$pid];
        $cycle = (string) ($website['billing_cycle'] ?? '');
        if ($cycle !== '' && $cycle !== 'onetime') {
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

        return ['ok' => true, 'payload' => $this->appendNameservers($payload)];
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
