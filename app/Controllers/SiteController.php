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
                ['WordPress Hosting', 'Fast WordPress-ready hosting with SSL, cPanel and one-click installs.', 'panel'],
                ['cPanel Hosting', 'Reliable business hosting with email, databases and simple management.', 'cloud'],
                ['Reseller Hosting', 'Sell hosting under your own brand with WHMCS-ready order links.', 'globe'],
                ['Domain Registration', 'Search and register domains through the connected WHMCS client area.', 'shield'],
                ['Website Development', 'Complete business websites delivered fast with hosting setup included.', 'code'],
                ['Cloudflare CDN Setup', 'Performance and security tuning with Cloudflare CDN configuration.', 'bolt'],
            ]),
            'trustBadges' => $this->rowsFromSetting('home_trust_badges', [
                ['Fast hosting', 'Optimised stack and cache-ready hosting.'],
                ['Free SSL', 'SSL certificates included on hosting plans.'],
                ['Free Cloudflare CDN', 'CDN setup support for faster delivery.'],
                ['cPanel included', 'Familiar control panel for sites, email and files.'],
                ['WHMCS billing', 'Orders, renewals and invoices handled in WHMCS.'],
                ['Support focused', 'Business support messaging built into every service.'],
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

        $apiEnabled = (string) ($this->settings['whmcs_api_enabled'] ?? '0') === '1';
        if (!$apiEnabled || !str_contains($domain, '.')) {
            $this->redirect($this->whmcs->domainSearchUrl($domain));
        }

        $result = $this->whmcs->checkDomain($domain);
        if (!$result['ok']) {
            $this->redirect($this->whmcs->domainSearchUrl($domain));
        }

        return $this->render('site/domain-result', $this->baseData('domains', [
            'domain' => $domain,
            'result' => $result,
            'registerUrl' => $this->whmcs->domainSearchUrl($domain),
            'transferUrl' => $this->whmcs->domainTransferUrl($domain),
        ]));
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
                $rows[] = [$parts[0], $parts[1] ?? '', $parts[2] ?? 'check'];
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
