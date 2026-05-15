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
                ['Domain Registration', 'Search and register domains through the connected WHMCS client area.', 'shield', '/domains'],
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
                $this->serviceSchema('Complete Business Website in Just £200', 'Business website package with hosting setup, domain registration support, SEO setup, Cloudflare CDN and 48 hour delivery.'),
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
                $this->serviceSchema('Domain Registration', 'Domain search and registration powered by WHMCS checkout and live billing flows.'),
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
        if (!$pricing['ok']) {
            return $this->json([
                'ok' => false,
                'message' => $pricing['message'] ?? 'Unable to fetch live WHMCS domain pricing.',
            ], 502);
        }

        $tlds = ['.com', '.net', '.org', '.co.uk', '.xyz', '.online'];
        $orderedTlds = array_values(array_unique(array_merge([$parsed['tld']], $tlds)));
        $currency = $this->currencyPrefix($pricing['currency'] ?? []);
        $hostingPid = $this->hostingPid();
        $results = [];

        foreach ($orderedTlds as $tld) {
            if (!in_array($tld, $tlds, true)) {
                continue;
            }

            $candidate = $parsed['sld'] . $tld;
            $availability = $this->whmcs->checkDomain($candidate);
            if (!$availability['ok']) {
                return $this->json([
                    'ok' => false,
                    'message' => $availability['message'] ?? 'Unable to check domain availability through WHMCS.',
                ], 502);
            }

            $results[] = [
                'domain' => $candidate,
                'sld' => $parsed['sld'],
                'tld' => $tld,
                'available' => (bool) $availability['available'],
                'price' => $this->whmcs->priceForTld($pricing['pricing'], $tld),
                'currency' => $currency,
                'type' => $candidate === $domain ? 'match' : 'alternative',
                'domain_url' => $this->whmcs->domainSearchUrl($candidate),
                'hosting_url' => $this->domainHostingUrl($parsed['sld'], $tld, $hostingPid),
            ];
        }

        $match = $results[0] ?? null;

        return $this->json([
            'ok' => true,
            'searched' => $domain,
            'sld' => $parsed['sld'],
            'tld' => $parsed['tld'],
            'available' => (bool) ($match['available'] ?? false),
            'currency' => $currency,
            'hosting_pid' => $hostingPid,
            'results' => $results,
        ]);
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
        $supported = ['.co.uk', '.online', '.com', '.net', '.org', '.xyz'];
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

    private function domainHostingUrl(string $sld, string $tld, string $pid): string
    {
        return $this->whmcs->cartUrl('cart.php?' . http_build_query([
            'a' => 'add',
            'pid' => $pid,
            'domainoption' => 'register',
            'sld' => $sld,
            'tld' => $tld,
        ]));
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
                        'url' => $plan['whmcs_url'],
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
