<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Controller;
use App\Core\Csrf;
use App\Core\Upload;
use App\Models\ContentRepository;

final class AdminController extends Controller
{
    private Auth $auth;
    private ContentRepository $content;

    public function __construct()
    {
        $this->auth = new Auth();
        $this->content = new ContentRepository();
    }

    public function login(): string
    {
        if ($this->auth->check()) {
            $this->redirect(url('/admin'));
        }

        $error = null;
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!Csrf::verify($_POST['_csrf'] ?? null)) {
                $error = 'Security token expired. Please try again.';
            } elseif ($this->auth->attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''))) {
                $this->redirect(url('/admin'));
            } else {
                $error = 'The email or password is incorrect.';
            }
        }

        return $this->render('admin/login', [
            'error' => $error,
            'csrfToken' => Csrf::token(),
            'settings' => $this->content->settings(),
        ], 'admin-auth');
    }

    public function logout(): string
    {
        $this->auth->logout();
        $this->redirect(url('/admin/login'));
    }

    public function dashboard(): string
    {
        $this->requireAuth();

        return $this->adminRender('admin/dashboard', [
            'counts' => $this->content->dashboardCounts(),
            'inquiries' => array_slice($this->content->inquiries(), 0, 5),
        ]);
    }

    public function settings(): string
    {
        $this->requireAuth();
        $settings = $this->content->settings();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            try {
                $data = $this->only([
                    'company_name', 'tagline', 'app_url', 'logo_url', 'favicon_url', 'og_image',
                    'admin_email', 'mail_from', 'phone', 'whatsapp_number', 'address',
                    'facebook_url', 'instagram_url', 'linkedin_url', 'x_url',
                    'whmcs_client_area_url', 'whmcs_api_url', 'whmcs_api_identifier', 'whmcs_api_secret', 'domain_hosting_pid',
                    'google_analytics', 'recaptcha_site_key', 'recaptcha_secret_key',
                    'cloudflare_zone_id', 'cloudflare_api_token', 'default_order_url',
                ]);

                $data['recaptcha_enabled'] = $this->postedBool('recaptcha_enabled');
                $data['logo_url'] = Upload::image('logo_upload', $data['logo_url'] ?: ($settings['logo_url'] ?? null));
                $data['favicon_url'] = Upload::image('favicon_upload', $data['favicon_url'] ?: ($settings['favicon_url'] ?? null));
                $data['og_image'] = Upload::image('og_image_upload', $data['og_image'] ?: ($settings['og_image'] ?? null));
                $this->content->saveSettings($data);
                $this->flash('Website settings updated.');
                $this->redirect(url('/admin/settings'));
            } catch (\Throwable $exception) {
                $this->flash($exception->getMessage(), 'error');
            }
        }

        return $this->adminRender('admin/form', [
            'title' => 'Website Settings',
            'subtitle' => 'Branding, contact details, WHMCS, tracking, reCAPTCHA and Cloudflare configuration.',
            'action' => url('/admin/settings'),
            'values' => $settings,
            'fields' => [
                ['name' => 'company_name', 'label' => 'Company Name', 'type' => 'text'],
                ['name' => 'tagline', 'label' => 'Tagline', 'type' => 'text'],
                ['name' => 'app_url', 'label' => 'Website URL', 'type' => 'url'],
                ['name' => 'logo_url', 'label' => 'Logo URL / saved path', 'type' => 'text'],
                ['name' => 'logo_upload', 'label' => 'Upload Logo', 'type' => 'file', 'current' => $settings['logo_url'] ?? ''],
                ['name' => 'favicon_url', 'label' => 'Favicon URL / saved path', 'type' => 'text'],
                ['name' => 'favicon_upload', 'label' => 'Upload Favicon', 'type' => 'file', 'current' => $settings['favicon_url'] ?? ''],
                ['name' => 'og_image', 'label' => 'Default Open Graph Image', 'type' => 'text'],
                ['name' => 'og_image_upload', 'label' => 'Upload Open Graph Image', 'type' => 'file', 'current' => $settings['og_image'] ?? ''],
                ['name' => 'admin_email', 'label' => 'Admin Email', 'type' => 'email'],
                ['name' => 'mail_from', 'label' => 'Mail From Email', 'type' => 'email'],
                ['name' => 'phone', 'label' => 'Phone', 'type' => 'text'],
                ['name' => 'whatsapp_number', 'label' => 'WhatsApp Number', 'type' => 'text'],
                ['name' => 'address', 'label' => 'Address', 'type' => 'textarea'],
                ['name' => 'facebook_url', 'label' => 'Facebook URL', 'type' => 'url'],
                ['name' => 'instagram_url', 'label' => 'Instagram URL', 'type' => 'url'],
                ['name' => 'linkedin_url', 'label' => 'LinkedIn URL', 'type' => 'url'],
                ['name' => 'x_url', 'label' => 'X/Twitter URL', 'type' => 'url'],
                ['name' => 'whmcs_client_area_url', 'label' => 'WHMCS Client Area URL', 'type' => 'url'],
                ['name' => 'whmcs_api_url', 'label' => 'WHMCS API URL', 'type' => 'url'],
                ['name' => 'whmcs_api_identifier', 'label' => 'WHMCS API Identifier', 'type' => 'text'],
                ['name' => 'whmcs_api_secret', 'label' => 'WHMCS API Secret', 'type' => 'password'],
                ['name' => 'domain_hosting_pid', 'label' => 'Domain + Hosting Product ID', 'type' => 'text'],
                ['name' => 'default_order_url', 'label' => 'Default Get Started / WHMCS Order URL', 'type' => 'url'],
                ['name' => 'google_analytics', 'label' => 'Google Analytics / Tracking Code', 'type' => 'textarea'],
                ['name' => 'recaptcha_enabled', 'label' => 'Enable Google reCAPTCHA', 'type' => 'checkbox'],
                ['name' => 'recaptcha_site_key', 'label' => 'reCAPTCHA Site Key', 'type' => 'text'],
                ['name' => 'recaptcha_secret_key', 'label' => 'reCAPTCHA Secret Key', 'type' => 'password'],
                ['name' => 'cloudflare_zone_id', 'label' => 'Cloudflare Zone ID', 'type' => 'text'],
                ['name' => 'cloudflare_api_token', 'label' => 'Cloudflare API Token', 'type' => 'password'],
            ],
        ]);
    }

    public function homepage(): string
    {
        $this->requireAuth();
        $settings = $this->content->settings();

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $this->content->saveSettings($this->only([
                'home_hero_title', 'home_hero_subtitle',
                'home_primary_cta_text', 'home_primary_cta_url',
                'home_secondary_cta_text', 'home_secondary_cta_url',
                'home_website_cta_text', 'home_website_cta_url',
                'home_service_cards', 'home_trust_badges', 'home_feature_sections',
                'home_final_cta_title', 'home_final_cta_text',
            ]));
            $this->flash('Homepage content updated.');
            $this->redirect(url('/admin/homepage'));
        }

        return $this->adminRender('admin/form', [
            'title' => 'Homepage Content',
            'subtitle' => 'Edit hero copy, CTAs, service cards, trust badges and feature sections. Service cards can use Title | Description | icon | URL; other rows can use Title | Description | icon.',
            'action' => url('/admin/homepage'),
            'values' => $settings,
            'fields' => [
                ['name' => 'home_hero_title', 'label' => 'Hero Title', 'type' => 'text'],
                ['name' => 'home_hero_subtitle', 'label' => 'Hero Subtitle', 'type' => 'textarea'],
                ['name' => 'home_primary_cta_text', 'label' => 'Primary CTA Text', 'type' => 'text'],
                ['name' => 'home_primary_cta_url', 'label' => 'Primary CTA URL', 'type' => 'text'],
                ['name' => 'home_secondary_cta_text', 'label' => 'Secondary CTA Text', 'type' => 'text'],
                ['name' => 'home_secondary_cta_url', 'label' => 'Secondary CTA URL', 'type' => 'text'],
                ['name' => 'home_website_cta_text', 'label' => 'Website Package CTA Text', 'type' => 'text'],
                ['name' => 'home_website_cta_url', 'label' => 'Website Package CTA URL', 'type' => 'text'],
                ['name' => 'home_service_cards', 'label' => 'Service Cards', 'type' => 'textarea', 'rows' => 8],
                ['name' => 'home_trust_badges', 'label' => 'Trust Badges', 'type' => 'textarea', 'rows' => 7],
                ['name' => 'home_feature_sections', 'label' => 'Feature Sections', 'type' => 'textarea', 'rows' => 8],
                ['name' => 'home_final_cta_title', 'label' => 'Final CTA Title', 'type' => 'text'],
                ['name' => 'home_final_cta_text', 'label' => 'Final CTA Text', 'type' => 'textarea'],
            ],
        ]);
    }

    public function package(): string
    {
        $this->requireAuth();
        $package = $this->content->package() ?: ['id' => 1];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->verifyCsrf();
            $this->content->savePackage([
                'id' => (int) ($_POST['id'] ?? 1),
                'title' => trim((string) ($_POST['title'] ?? '')),
                'price' => trim((string) ($_POST['price'] ?? '')),
                'description' => trim((string) ($_POST['description'] ?? '')),
                'delivery_time' => trim((string) ($_POST['delivery_time'] ?? '')),
                'features_json' => json_encode(lines_to_array($_POST['features'] ?? ''), JSON_UNESCAPED_SLASHES),
                'cta_text' => trim((string) ($_POST['cta_text'] ?? '')),
                'cta_url' => trim((string) ($_POST['cta_url'] ?? '')),
                'inquiry_mode' => $this->postedBool('inquiry_mode'),
                'is_active' => $this->postedBool('is_active'),
            ]);
            $this->flash('Website package updated.');
            $this->redirect(url('/admin/package'));
        }

        $package['features'] = array_to_lines($package['features_json'] ?? '[]');

        return $this->adminRender('admin/form', [
            'title' => 'Website Development Package',
            'subtitle' => 'Manage the £200 website offer, included features, delivery time and order route.',
            'action' => url('/admin/package'),
            'values' => $package,
            'fields' => [
                ['name' => 'id', 'type' => 'hidden'],
                ['name' => 'title', 'label' => 'Package Title', 'type' => 'text'],
                ['name' => 'price', 'label' => 'Price', 'type' => 'text'],
                ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                ['name' => 'delivery_time', 'label' => 'Delivery Time', 'type' => 'text'],
                ['name' => 'features', 'label' => 'Included Features', 'type' => 'textarea', 'rows' => 10],
                ['name' => 'cta_text', 'label' => 'CTA Text', 'type' => 'text'],
                ['name' => 'cta_url', 'label' => 'WHMCS Order URL or Inquiry URL', 'type' => 'url'],
                ['name' => 'inquiry_mode', 'label' => 'Use inquiry/contact flow instead of direct checkout', 'type' => 'checkbox'],
                ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox'],
            ],
        ]);
    }

    public function plans(): string
    {
        $this->requireAuth();
        return $this->adminRender('admin/list', [
            'title' => 'Hosting Plans',
            'createUrl' => url('/admin/plans/create'),
            'columns' => ['title' => 'Plan', 'plan_type' => 'Type', 'monthly_price' => 'Monthly', 'yearly_price' => 'Yearly', 'is_highlighted' => 'Featured', 'is_active' => 'Active'],
            'rows' => $this->content->hostingPlans(null, false),
            'editBase' => '/admin/plans',
            'deleteBase' => '/admin/plans',
        ]);
    }

    public function planForm(?string $id = null): string
    {
        $this->requireAuth();
        $plan = $id ? $this->content->plan((int) $id) : [
            'id' => '', 'plan_type' => 'shared', 'button_text' => 'Order Now', 'is_active' => 1, 'is_highlighted' => 0, 'sort_order' => 10,
        ];
        $plan['features'] = array_to_lines($plan['features_json'] ?? '[]');

        return $this->adminRender('admin/form', [
            'title' => $id ? 'Edit Hosting Plan' : 'Add Hosting Plan',
            'action' => url('/admin/plans/save'),
            'values' => $plan,
            'fields' => [
                ['name' => 'id', 'type' => 'hidden'],
                ['name' => 'plan_type', 'label' => 'Plan Type', 'type' => 'select', 'options' => ['shared' => 'cPanel Hosting', 'wordpress' => 'WordPress Hosting', 'reseller' => 'Reseller Hosting', 'starter' => 'Starter Hosting', 'business' => 'Business Hosting']],
                ['name' => 'title', 'label' => 'Plan Title', 'type' => 'text'],
                ['name' => 'slug', 'label' => 'Slug', 'type' => 'text'],
                ['name' => 'description', 'label' => 'Description', 'type' => 'textarea'],
                ['name' => 'monthly_price', 'label' => 'Monthly Price', 'type' => 'text'],
                ['name' => 'yearly_price', 'label' => 'Yearly Price', 'type' => 'text'],
                ['name' => 'storage', 'label' => 'Storage', 'type' => 'text'],
                ['name' => 'bandwidth', 'label' => 'Bandwidth', 'type' => 'text'],
                ['name' => 'email_accounts', 'label' => 'Email Accounts', 'type' => 'text'],
                ['name' => 'features', 'label' => 'Features', 'type' => 'textarea', 'rows' => 10],
                ['name' => 'whmcs_url', 'label' => 'WHMCS Checkout URL', 'type' => 'url'],
                ['name' => 'button_text', 'label' => 'Button Text', 'type' => 'text'],
                ['name' => 'badge', 'label' => 'Badge Text', 'type' => 'text'],
                ['name' => 'sort_order', 'label' => 'Sort Order', 'type' => 'number'],
                ['name' => 'is_highlighted', 'label' => 'Highlighted / Recommended', 'type' => 'checkbox'],
                ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox'],
            ],
        ]);
    }

    public function savePlan(): string
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $title = trim((string) ($_POST['title'] ?? ''));
        $slug = trim((string) ($_POST['slug'] ?? '')) ?: slugify($title);

        $this->content->savePlan([
            'id' => $_POST['id'] !== '' ? (int) $_POST['id'] : null,
            'plan_type' => trim((string) ($_POST['plan_type'] ?? 'shared')),
            'title' => $title,
            'slug' => $slug,
            'description' => trim((string) ($_POST['description'] ?? '')),
            'monthly_price' => trim((string) ($_POST['monthly_price'] ?? '')),
            'yearly_price' => trim((string) ($_POST['yearly_price'] ?? '')),
            'storage' => trim((string) ($_POST['storage'] ?? '')),
            'bandwidth' => trim((string) ($_POST['bandwidth'] ?? '')),
            'email_accounts' => trim((string) ($_POST['email_accounts'] ?? '')),
            'features_json' => json_encode(lines_to_array($_POST['features'] ?? ''), JSON_UNESCAPED_SLASHES),
            'whmcs_url' => trim((string) ($_POST['whmcs_url'] ?? '')),
            'button_text' => trim((string) ($_POST['button_text'] ?? 'Order Now')),
            'badge' => trim((string) ($_POST['badge'] ?? '')),
            'is_highlighted' => $this->postedBool('is_highlighted'),
            'is_active' => $this->postedBool('is_active'),
            'sort_order' => (int) ($_POST['sort_order'] ?? 10),
        ]);
        $this->flash('Hosting plan saved.');
        $this->redirect(url('/admin/plans'));
    }

    public function deletePlan(string $id): string
    {
        $this->deleteEntity('hosting_plans', (int) $id, '/admin/plans', 'Hosting plan deleted.');
    }

    public function tlds(): string
    {
        $this->requireAuth();
        return $this->adminRender('admin/list', [
            'title' => 'Domain TLD Display',
            'createUrl' => url('/admin/tlds/create'),
            'columns' => ['extension' => 'TLD', 'price' => 'Display Price', 'whmcs_url' => 'WHMCS URL', 'is_active' => 'Active'],
            'rows' => $this->content->tlds(false),
            'editBase' => '/admin/tlds',
            'deleteBase' => '/admin/tlds',
        ]);
    }

    public function tldForm(?string $id = null): string
    {
        $this->requireAuth();
        $tld = $id ? $this->content->find('domain_tlds', (int) $id) : ['id' => '', 'is_active' => 1, 'sort_order' => 10];
        return $this->adminRender('admin/form', [
            'title' => $id ? 'Edit TLD' : 'Add TLD',
            'action' => url('/admin/tlds/save'),
            'values' => $tld,
            'fields' => [
                ['name' => 'id', 'type' => 'hidden'],
                ['name' => 'extension', 'label' => 'TLD Extension', 'type' => 'text'],
                ['name' => 'price', 'label' => 'Display Price', 'type' => 'text'],
                ['name' => 'whmcs_url', 'label' => 'WHMCS Domain URL', 'type' => 'url'],
                ['name' => 'sort_order', 'label' => 'Sort Order', 'type' => 'number'],
                ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox'],
            ],
        ]);
    }

    public function saveTld(): string
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->content->saveTld([
            'id' => $_POST['id'] !== '' ? (int) $_POST['id'] : null,
            'extension' => trim((string) ($_POST['extension'] ?? '')),
            'price' => trim((string) ($_POST['price'] ?? '')),
            'whmcs_url' => trim((string) ($_POST['whmcs_url'] ?? '')),
            'is_active' => $this->postedBool('is_active'),
            'sort_order' => (int) ($_POST['sort_order'] ?? 10),
        ]);
        $this->flash('TLD saved.');
        $this->redirect(url('/admin/tlds'));
    }

    public function deleteTld(string $id): string
    {
        $this->deleteEntity('domain_tlds', (int) $id, '/admin/tlds', 'TLD deleted.');
    }

    public function pages(): string
    {
        $this->requireAuth();
        return $this->adminRender('admin/list', [
            'title' => 'Editable Pages',
            'columns' => ['title' => 'Title', 'slug' => 'Slug', 'meta_title' => 'Meta Title'],
            'rows' => $this->content->pages(),
            'editBase' => '/admin/pages',
            'deleteBase' => null,
        ]);
    }

    public function pageForm(string $id): string
    {
        $this->requireAuth();
        $page = $this->content->find('pages', (int) $id);
        return $this->adminRender('admin/form', [
            'title' => 'Edit Page',
            'action' => url('/admin/pages/save'),
            'values' => $page,
            'fields' => [
                ['name' => 'id', 'type' => 'hidden'],
                ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
                ['name' => 'slug', 'label' => 'Slug', 'type' => 'text'],
                ['name' => 'body', 'label' => 'Body HTML', 'type' => 'textarea', 'rows' => 14],
                ['name' => 'meta_title', 'label' => 'Meta Title', 'type' => 'text'],
                ['name' => 'meta_description', 'label' => 'Meta Description', 'type' => 'textarea'],
                ['name' => 'keywords', 'label' => 'Keywords', 'type' => 'text'],
                ['name' => 'og_title', 'label' => 'Open Graph Title', 'type' => 'text'],
                ['name' => 'og_image', 'label' => 'Open Graph Image URL', 'type' => 'text'],
                ['name' => 'canonical_url', 'label' => 'Canonical URL', 'type' => 'url'],
            ],
        ]);
    }

    public function savePage(): string
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->content->savePage([
            'id' => (int) ($_POST['id'] ?? 0),
            'title' => trim((string) ($_POST['title'] ?? '')),
            'slug' => slugify((string) ($_POST['slug'] ?? $_POST['title'] ?? 'page')),
            'body' => safe_html((string) ($_POST['body'] ?? '')),
            'meta_title' => trim((string) ($_POST['meta_title'] ?? '')),
            'meta_description' => trim((string) ($_POST['meta_description'] ?? '')),
            'keywords' => trim((string) ($_POST['keywords'] ?? '')),
            'og_title' => trim((string) ($_POST['og_title'] ?? '')),
            'og_image' => trim((string) ($_POST['og_image'] ?? '')),
            'canonical_url' => trim((string) ($_POST['canonical_url'] ?? '')),
        ]);
        $this->flash('Page updated.');
        $this->redirect(url('/admin/pages'));
    }

    public function posts(): string
    {
        $this->requireAuth();
        return $this->adminRender('admin/list', [
            'title' => 'Blog Posts',
            'createUrl' => url('/admin/blog/create'),
            'columns' => ['title' => 'Title', 'category_name' => 'Category', 'status' => 'Status', 'published_at' => 'Publish Date'],
            'rows' => $this->content->posts(false),
            'editBase' => '/admin/blog',
            'deleteBase' => '/admin/blog',
        ]);
    }

    public function postForm(?string $id = null): string
    {
        $this->requireAuth();
        $post = $id ? $this->content->find('blog_posts', (int) $id) : ['id' => '', 'status' => 'draft', 'published_at' => date('Y-m-d H:i:s')];
        $categories = [];
        foreach ($this->content->categories() as $category) {
            $categories[$category['id']] = $category['name'];
        }

        return $this->adminRender('admin/form', [
            'title' => $id ? 'Edit Blog Post' : 'Add Blog Post',
            'action' => url('/admin/blog/save'),
            'values' => $post,
            'fields' => [
                ['name' => 'id', 'type' => 'hidden'],
                ['name' => 'category_id', 'label' => 'Category', 'type' => 'select', 'options' => $categories],
                ['name' => 'title', 'label' => 'Title', 'type' => 'text'],
                ['name' => 'slug', 'label' => 'Slug', 'type' => 'text'],
                ['name' => 'featured_image', 'label' => 'Featured Image URL / saved path', 'type' => 'text'],
                ['name' => 'featured_upload', 'label' => 'Upload Featured Image', 'type' => 'file', 'current' => $post['featured_image'] ?? ''],
                ['name' => 'featured_alt', 'label' => 'Featured Image Alt Text', 'type' => 'text'],
                ['name' => 'meta_title', 'label' => 'Meta Title', 'type' => 'text'],
                ['name' => 'meta_description', 'label' => 'Meta Description', 'type' => 'textarea'],
                ['name' => 'content', 'label' => 'Content HTML', 'type' => 'textarea', 'rows' => 16],
                ['name' => 'status', 'label' => 'Status', 'type' => 'select', 'options' => ['draft' => 'Draft', 'published' => 'Published']],
                ['name' => 'published_at', 'label' => 'Publish Date (YYYY-MM-DD HH:MM:SS)', 'type' => 'text'],
            ],
        ]);
    }

    public function savePost(): string
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $id = $_POST['id'] !== '' ? (int) $_POST['id'] : null;
        $existing = $id ? $this->content->find('blog_posts', $id) : [];
        try {
            $featured = Upload::image('featured_upload', $_POST['featured_image'] ?: ($existing['featured_image'] ?? null));
        } catch (\Throwable $exception) {
            $this->flash($exception->getMessage(), 'error');
            $this->back(url('/admin/blog'));
        }

        $title = trim((string) ($_POST['title'] ?? ''));
        $this->content->savePost([
            'id' => $id,
            'category_id' => (int) ($_POST['category_id'] ?? 1),
            'title' => $title,
            'slug' => trim((string) ($_POST['slug'] ?? '')) ?: slugify($title),
            'featured_image' => $featured,
            'featured_alt' => trim((string) ($_POST['featured_alt'] ?? '')),
            'meta_title' => trim((string) ($_POST['meta_title'] ?? '')),
            'meta_description' => trim((string) ($_POST['meta_description'] ?? '')),
            'content' => safe_html((string) ($_POST['content'] ?? '')),
            'status' => in_array($_POST['status'] ?? 'draft', ['draft', 'published'], true) ? $_POST['status'] : 'draft',
            'published_at' => trim((string) ($_POST['published_at'] ?? '')) ?: null,
        ]);
        $this->flash('Blog post saved.');
        $this->redirect(url('/admin/blog'));
    }

    public function deletePost(string $id): string
    {
        $this->deleteEntity('blog_posts', (int) $id, '/admin/blog', 'Blog post deleted.');
    }

    public function testimonials(): string
    {
        $this->requireAuth();
        return $this->adminRender('admin/list', [
            'title' => 'Testimonials',
            'createUrl' => url('/admin/testimonials/create'),
            'columns' => ['name' => 'Name', 'company' => 'Company', 'rating' => 'Rating', 'is_active' => 'Active'],
            'rows' => $this->content->testimonials(false),
            'editBase' => '/admin/testimonials',
            'deleteBase' => '/admin/testimonials',
        ]);
    }

    public function testimonialForm(?string $id = null): string
    {
        $this->requireAuth();
        $item = $id ? $this->content->find('testimonials', (int) $id) : ['id' => '', 'rating' => 5, 'is_active' => 1, 'sort_order' => 10];
        return $this->adminRender('admin/form', [
            'title' => $id ? 'Edit Testimonial' : 'Add Testimonial',
            'action' => url('/admin/testimonials/save'),
            'values' => $item,
            'fields' => [
                ['name' => 'id', 'type' => 'hidden'],
                ['name' => 'name', 'label' => 'Name', 'type' => 'text'],
                ['name' => 'role', 'label' => 'Role', 'type' => 'text'],
                ['name' => 'company', 'label' => 'Company', 'type' => 'text'],
                ['name' => 'quote', 'label' => 'Quote', 'type' => 'textarea'],
                ['name' => 'image_url', 'label' => 'Image URL / saved path', 'type' => 'text'],
                ['name' => 'image_upload', 'label' => 'Upload Image', 'type' => 'file', 'current' => $item['image_url'] ?? ''],
                ['name' => 'rating', 'label' => 'Rating', 'type' => 'number'],
                ['name' => 'sort_order', 'label' => 'Sort Order', 'type' => 'number'],
                ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox'],
            ],
        ]);
    }

    public function saveTestimonial(): string
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $id = $_POST['id'] !== '' ? (int) $_POST['id'] : null;
        $existing = $id ? $this->content->find('testimonials', $id) : [];
        try {
            $image = Upload::image('image_upload', $_POST['image_url'] ?: ($existing['image_url'] ?? null));
        } catch (\Throwable $exception) {
            $this->flash($exception->getMessage(), 'error');
            $this->back(url('/admin/testimonials'));
        }

        $this->content->saveTestimonial([
            'id' => $id,
            'name' => trim((string) ($_POST['name'] ?? '')),
            'role' => trim((string) ($_POST['role'] ?? '')),
            'company' => trim((string) ($_POST['company'] ?? '')),
            'quote' => trim((string) ($_POST['quote'] ?? '')),
            'image_url' => $image,
            'rating' => max(1, min(5, (int) ($_POST['rating'] ?? 5))),
            'is_active' => $this->postedBool('is_active'),
            'sort_order' => (int) ($_POST['sort_order'] ?? 10),
        ]);
        $this->flash('Testimonial saved.');
        $this->redirect(url('/admin/testimonials'));
    }

    public function deleteTestimonial(string $id): string
    {
        $this->deleteEntity('testimonials', (int) $id, '/admin/testimonials', 'Testimonial deleted.');
    }

    public function faqs(): string
    {
        $this->requireAuth();
        return $this->adminRender('admin/list', [
            'title' => 'FAQs',
            'createUrl' => url('/admin/faqs/create'),
            'columns' => ['page_key' => 'Page', 'question' => 'Question', 'is_active' => 'Active'],
            'rows' => $this->content->faqs(null, false),
            'editBase' => '/admin/faqs',
            'deleteBase' => '/admin/faqs',
        ]);
    }

    public function faqForm(?string $id = null): string
    {
        $this->requireAuth();
        $faq = $id ? $this->content->find('faqs', (int) $id) : ['id' => '', 'page_key' => 'home', 'is_active' => 1, 'sort_order' => 10];
        return $this->adminRender('admin/form', [
            'title' => $id ? 'Edit FAQ' : 'Add FAQ',
            'action' => url('/admin/faqs/save'),
            'values' => $faq,
            'fields' => [
                ['name' => 'id', 'type' => 'hidden'],
                ['name' => 'page_key', 'label' => 'Page Key', 'type' => 'select', 'options' => ['home' => 'Home', 'hosting' => 'Hosting', 'wordpress' => 'WordPress', 'website-development' => 'Website Development', 'domains' => 'Domains']],
                ['name' => 'question', 'label' => 'Question', 'type' => 'text'],
                ['name' => 'answer', 'label' => 'Answer', 'type' => 'textarea', 'rows' => 6],
                ['name' => 'sort_order', 'label' => 'Sort Order', 'type' => 'number'],
                ['name' => 'is_active', 'label' => 'Active', 'type' => 'checkbox'],
            ],
        ]);
    }

    public function saveFaq(): string
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->content->saveFaq([
            'id' => $_POST['id'] !== '' ? (int) $_POST['id'] : null,
            'page_key' => trim((string) ($_POST['page_key'] ?? 'home')),
            'question' => trim((string) ($_POST['question'] ?? '')),
            'answer' => safe_html((string) ($_POST['answer'] ?? '')),
            'is_active' => $this->postedBool('is_active'),
            'sort_order' => (int) ($_POST['sort_order'] ?? 10),
        ]);
        $this->flash('FAQ saved.');
        $this->redirect(url('/admin/faqs'));
    }

    public function deleteFaq(string $id): string
    {
        $this->deleteEntity('faqs', (int) $id, '/admin/faqs', 'FAQ deleted.');
    }

    public function inquiries(): string
    {
        $this->requireAuth();
        return $this->adminRender('admin/inquiries', [
            'title' => 'Contact Inquiries',
            'inquiries' => $this->content->inquiries(),
        ]);
    }

    public function updateInquiry(string $id): string
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $status = in_array($_POST['status'] ?? 'new', ['new', 'read', 'replied', 'closed'], true) ? $_POST['status'] : 'new';
        $this->content->updateInquiry((int) $id, $status);
        $this->flash('Inquiry status updated.');
        $this->redirect(url('/admin/inquiries'));
    }

    public function deleteInquiry(string $id): string
    {
        $this->deleteEntity('inquiries', (int) $id, '/admin/inquiries', 'Inquiry deleted.');
    }

    public function seo(): string
    {
        $this->requireAuth();
        return $this->adminRender('admin/list', [
            'title' => 'SEO Settings',
            'columns' => ['route_key' => 'Route', 'meta_title' => 'Meta Title', 'meta_description' => 'Meta Description'],
            'rows' => $this->content->allSeo(),
            'editBase' => '/admin/seo',
            'deleteBase' => null,
        ]);
    }

    public function seoForm(string $id): string
    {
        $this->requireAuth();
        $seo = $this->content->find('seo_settings', (int) $id);
        return $this->adminRender('admin/form', [
            'title' => 'Edit SEO Settings',
            'action' => url('/admin/seo/save'),
            'values' => $seo,
            'fields' => [
                ['name' => 'id', 'type' => 'hidden'],
                ['name' => 'route_key', 'label' => 'Route Key', 'type' => 'text', 'readonly' => true],
                ['name' => 'meta_title', 'label' => 'Meta Title', 'type' => 'text'],
                ['name' => 'meta_description', 'label' => 'Meta Description', 'type' => 'textarea'],
                ['name' => 'keywords', 'label' => 'Keywords', 'type' => 'text'],
                ['name' => 'og_title', 'label' => 'Open Graph Title', 'type' => 'text'],
                ['name' => 'og_image', 'label' => 'Open Graph Image URL', 'type' => 'text'],
                ['name' => 'canonical_url', 'label' => 'Canonical URL', 'type' => 'url'],
            ],
        ]);
    }

    public function saveSeo(): string
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->content->saveSeo([
            'id' => (int) ($_POST['id'] ?? 0),
            'meta_title' => trim((string) ($_POST['meta_title'] ?? '')),
            'meta_description' => trim((string) ($_POST['meta_description'] ?? '')),
            'keywords' => trim((string) ($_POST['keywords'] ?? '')),
            'og_title' => trim((string) ($_POST['og_title'] ?? '')),
            'og_image' => trim((string) ($_POST['og_image'] ?? '')),
            'canonical_url' => trim((string) ($_POST['canonical_url'] ?? '')),
        ]);
        $this->flash('SEO settings updated.');
        $this->redirect(url('/admin/seo'));
    }

    public function export(): string
    {
        $this->requireAuth();
        $tables = ['settings', 'seo_settings', 'hosting_plans', 'domain_tlds', 'website_packages', 'pages', 'testimonials', 'faqs', 'blog_categories', 'blog_posts', 'inquiries'];
        $backup = [
            'generated_at' => date('c'),
            'site' => $this->content->settings()['company_name'] ?? 'Planetic Solutions',
            'tables' => [],
        ];

        foreach ($tables as $table) {
            $backup['tables'][$table] = $this->content->db()->query("SELECT * FROM {$table}")->fetchAll();
        }

        header('Content-Type: application/json; charset=UTF-8');
        header('Content-Disposition: attachment; filename="planetic-solutions-backup-' . date('Y-m-d') . '.json"');
        return json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}';
    }

    private function adminRender(string $view, array $data = []): string
    {
        return $this->render($view, [
            ...$data,
            'settings' => $this->content->settings(),
            'adminUser' => $this->auth->user(),
            'flash' => $this->pullFlash(),
            'csrfToken' => Csrf::token(),
        ], 'admin');
    }

    private function requireAuth(): void
    {
        if (!$this->auth->check()) {
            $this->redirect(url('/admin/login'));
        }
    }

    private function verifyCsrf(): void
    {
        if (!Csrf::verify($_POST['_csrf'] ?? null)) {
            $this->flash('Security token expired. Please try again.', 'error');
            $this->back(url('/admin'));
        }
    }

    private function only(array $keys): array
    {
        $data = [];
        foreach ($keys as $key) {
            $data[$key] = trim((string) ($_POST[$key] ?? ''));
        }
        return $data;
    }

    private function postedBool(string $key): int
    {
        return (int) ((string) ($_POST[$key] ?? '0') === '1');
    }

    private function flash(string $message, string $type = 'success'): void
    {
        $_SESSION['_flash'] = ['message' => $message, 'type' => $type];
    }

    private function pullFlash(): ?array
    {
        $flash = $_SESSION['_flash'] ?? null;
        unset($_SESSION['_flash']);
        return $flash;
    }

    private function deleteEntity(string $table, int $id, string $redirect, string $message): void
    {
        $this->requireAuth();
        $this->verifyCsrf();
        $this->content->delete($table, $id);
        $this->flash($message);
        $this->redirect(url($redirect));
    }
}
