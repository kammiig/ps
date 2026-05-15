<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;
use PDO;

final class ContentRepository
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    public function db(): PDO
    {
        return $this->db;
    }

    public function settings(): array
    {
        $rows = $this->db->query('SELECT setting_key, setting_value FROM settings')->fetchAll();
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }

        $settings['company_name'] ??= env('APP_NAME', 'Planetic Solutions');
        $settings['app_url'] ??= env('APP_URL', '');
        $settings['whmcs_client_area_url'] ??= env('WHMCS_URL', env('WHMCS_CLIENT_AREA_URL', 'https://planeticsolution.com/clientarea/'));
        $settings['domain_hosting_pid'] ??= env('DOMAIN_HOSTING_PID', '');
        $settings['admin_email'] ??= env('ADMIN_EMAIL', '');
        $settings['mail_from'] ??= env('MAIL_FROM', '');

        return $settings;
    }

    public function saveSettings(array $settings): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO settings (setting_key, setting_value, updated_at)
             VALUES (:setting_key, :setting_value, NOW())
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()'
        );

        foreach ($settings as $key => $value) {
            $stmt->execute([
                'setting_key' => $key,
                'setting_value' => is_array($value) ? json_encode($value, JSON_UNESCAPED_SLASHES) : (string) $value,
            ]);
        }
    }

    public function seo(string $routeKey): array
    {
        $stmt = $this->db->prepare('SELECT * FROM seo_settings WHERE route_key = :route_key LIMIT 1');
        $stmt->execute(['route_key' => $routeKey]);
        return $stmt->fetch() ?: [];
    }

    public function allSeo(): array
    {
        return $this->db->query('SELECT * FROM seo_settings ORDER BY route_key ASC')->fetchAll();
    }

    public function saveSeo(array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE seo_settings
             SET meta_title = :meta_title, meta_description = :meta_description, keywords = :keywords,
                 og_title = :og_title, og_image = :og_image, canonical_url = :canonical_url, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute($data);
    }

    public function hostingPlans(?string $type = null, bool $activeOnly = true, ?int $limit = null): array
    {
        $sql = 'SELECT * FROM hosting_plans WHERE 1=1';
        $params = [];

        if ($type !== null) {
            $sql .= ' AND plan_type = :plan_type';
            $params['plan_type'] = $type;
        }

        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }

        $sql .= ' ORDER BY sort_order ASC, id ASC';
        if ($limit !== null) {
            $sql .= ' LIMIT ' . (int) $limit;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function plan(int $id): array
    {
        return $this->find('hosting_plans', $id);
    }

    public function savePlan(array $data): void
    {
        if (!empty($data['id'])) {
            $stmt = $this->db->prepare(
                'UPDATE hosting_plans
                 SET plan_type = :plan_type, title = :title, slug = :slug, description = :description,
                     monthly_price = :monthly_price, yearly_price = :yearly_price, storage = :storage,
                     bandwidth = :bandwidth, email_accounts = :email_accounts, features_json = :features_json,
                     whmcs_url = :whmcs_url, button_text = :button_text, badge = :badge,
                     is_highlighted = :is_highlighted, is_active = :is_active, sort_order = :sort_order,
                     updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute($data);
            return;
        }

        unset($data['id']);
        $stmt = $this->db->prepare(
            'INSERT INTO hosting_plans
             (plan_type, title, slug, description, monthly_price, yearly_price, storage, bandwidth, email_accounts,
              features_json, whmcs_url, button_text, badge, is_highlighted, is_active, sort_order, created_at, updated_at)
             VALUES
             (:plan_type, :title, :slug, :description, :monthly_price, :yearly_price, :storage, :bandwidth, :email_accounts,
              :features_json, :whmcs_url, :button_text, :badge, :is_highlighted, :is_active, :sort_order, NOW(), NOW())'
        );
        $stmt->execute($data);
    }

    public function tlds(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM domain_tlds';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        return $this->db->query($sql)->fetchAll();
    }

    public function saveTld(array $data): void
    {
        if (!empty($data['id'])) {
            $stmt = $this->db->prepare(
                'UPDATE domain_tlds
                 SET extension = :extension, price = :price, whmcs_url = :whmcs_url,
                     is_active = :is_active, sort_order = :sort_order, updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute($data);
            return;
        }

        unset($data['id']);
        $stmt = $this->db->prepare(
            'INSERT INTO domain_tlds (extension, price, whmcs_url, is_active, sort_order, created_at, updated_at)
             VALUES (:extension, :price, :whmcs_url, :is_active, :sort_order, NOW(), NOW())'
        );
        $stmt->execute($data);
    }

    public function package(): array
    {
        return $this->db->query('SELECT * FROM website_packages ORDER BY id ASC LIMIT 1')->fetch() ?: [];
    }

    public function savePackage(array $data): void
    {
        $exists = (bool) $this->package();
        if ($exists) {
            $stmt = $this->db->prepare(
                'UPDATE website_packages
                 SET title = :title, price = :price, description = :description, delivery_time = :delivery_time,
                     features_json = :features_json, cta_text = :cta_text, cta_url = :cta_url,
                     inquiry_mode = :inquiry_mode, is_active = :is_active, updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute($data);
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO website_packages
             (id, title, price, description, delivery_time, features_json, cta_text, cta_url, inquiry_mode, is_active, created_at, updated_at)
             VALUES
             (:id, :title, :price, :description, :delivery_time, :features_json, :cta_text, :cta_url, :inquiry_mode, :is_active, NOW(), NOW())'
        );
        $stmt->execute($data);
    }

    public function pageBySlug(string $slug): array
    {
        $stmt = $this->db->prepare('SELECT * FROM pages WHERE slug = :slug LIMIT 1');
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetch() ?: [];
    }

    public function pages(): array
    {
        return $this->db->query('SELECT * FROM pages ORDER BY title ASC')->fetchAll();
    }

    public function savePage(array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE pages
             SET title = :title, slug = :slug, body = :body, meta_title = :meta_title,
                 meta_description = :meta_description, keywords = :keywords, og_title = :og_title,
                 og_image = :og_image, canonical_url = :canonical_url, updated_at = NOW()
             WHERE id = :id'
        );
        $stmt->execute($data);
    }

    public function testimonials(bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM testimonials';
        if ($activeOnly) {
            $sql .= ' WHERE is_active = 1';
        }
        $sql .= ' ORDER BY sort_order ASC, id ASC';
        return $this->db->query($sql)->fetchAll();
    }

    public function saveTestimonial(array $data): void
    {
        if (!empty($data['id'])) {
            $stmt = $this->db->prepare(
                'UPDATE testimonials
                 SET name = :name, role = :role, company = :company, quote = :quote, image_url = :image_url,
                     rating = :rating, is_active = :is_active, sort_order = :sort_order, updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute($data);
            return;
        }

        unset($data['id']);
        $stmt = $this->db->prepare(
            'INSERT INTO testimonials (name, role, company, quote, image_url, rating, is_active, sort_order, created_at, updated_at)
             VALUES (:name, :role, :company, :quote, :image_url, :rating, :is_active, :sort_order, NOW(), NOW())'
        );
        $stmt->execute($data);
    }

    public function faqs(?string $page = null, bool $activeOnly = true): array
    {
        $sql = 'SELECT * FROM faqs WHERE 1=1';
        $params = [];
        if ($page !== null) {
            $sql .= ' AND page_key = :page_key';
            $params['page_key'] = $page;
        }
        if ($activeOnly) {
            $sql .= ' AND is_active = 1';
        }
        $sql .= ' ORDER BY page_key ASC, sort_order ASC, id ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function saveFaq(array $data): void
    {
        if (!empty($data['id'])) {
            $stmt = $this->db->prepare(
                'UPDATE faqs
                 SET page_key = :page_key, question = :question, answer = :answer,
                     is_active = :is_active, sort_order = :sort_order, updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute($data);
            return;
        }

        unset($data['id']);
        $stmt = $this->db->prepare(
            'INSERT INTO faqs (page_key, question, answer, is_active, sort_order, created_at, updated_at)
             VALUES (:page_key, :question, :answer, :is_active, :sort_order, NOW(), NOW())'
        );
        $stmt->execute($data);
    }

    public function posts(bool $publishedOnly = true): array
    {
        $sql = 'SELECT p.*, c.name AS category_name
                FROM blog_posts p
                LEFT JOIN blog_categories c ON c.id = p.category_id';
        if ($publishedOnly) {
            $sql .= " WHERE p.status = 'published' AND (p.published_at IS NULL OR p.published_at <= NOW())";
        }
        $sql .= ' ORDER BY COALESCE(p.published_at, p.created_at) DESC, p.id DESC';
        return $this->db->query($sql)->fetchAll();
    }

    public function postBySlug(string $slug): array
    {
        $stmt = $this->db->prepare(
            'SELECT p.*, c.name AS category_name
             FROM blog_posts p
             LEFT JOIN blog_categories c ON c.id = p.category_id
             WHERE p.slug = :slug AND p.status = "published" LIMIT 1'
        );
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetch() ?: [];
    }

    public function categories(): array
    {
        return $this->db->query('SELECT * FROM blog_categories ORDER BY name ASC')->fetchAll();
    }

    public function savePost(array $data): void
    {
        if (!empty($data['id'])) {
            $stmt = $this->db->prepare(
                'UPDATE blog_posts
                 SET category_id = :category_id, title = :title, slug = :slug, featured_image = :featured_image,
                     featured_alt = :featured_alt,
                     meta_title = :meta_title, meta_description = :meta_description, content = :content,
                     status = :status, published_at = :published_at, updated_at = NOW()
                 WHERE id = :id'
            );
            $stmt->execute($data);
            return;
        }

        unset($data['id']);
        $stmt = $this->db->prepare(
            'INSERT INTO blog_posts
             (category_id, title, slug, featured_image, featured_alt, meta_title, meta_description, content, status, published_at, created_at, updated_at)
             VALUES
             (:category_id, :title, :slug, :featured_image, :featured_alt, :meta_title, :meta_description, :content, :status, :published_at, NOW(), NOW())'
        );
        $stmt->execute($data);
    }

    public function createInquiry(array $data): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO inquiries
             (full_name, email, phone, service, message, ip_address, user_agent, status, created_at, updated_at)
             VALUES
             (:full_name, :email, :phone, :service, :message, :ip_address, :user_agent, "new", NOW(), NOW())'
        );
        $stmt->execute($data);
    }

    public function inquiries(): array
    {
        return $this->db->query('SELECT * FROM inquiries ORDER BY created_at DESC')->fetchAll();
    }

    public function updateInquiry(int $id, string $status): void
    {
        $stmt = $this->db->prepare('UPDATE inquiries SET status = :status, updated_at = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id, 'status' => $status]);
    }

    public function delete(string $table, int $id): void
    {
        $allowed = ['hosting_plans', 'domain_tlds', 'testimonials', 'faqs', 'blog_posts', 'inquiries'];
        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported delete table.');
        }

        $stmt = $this->db->prepare("DELETE FROM {$table} WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public function find(string $table, int $id): array
    {
        $allowed = ['hosting_plans', 'domain_tlds', 'pages', 'testimonials', 'faqs', 'blog_posts', 'seo_settings'];
        if (!in_array($table, $allowed, true)) {
            throw new \InvalidArgumentException('Unsupported find table.');
        }

        $stmt = $this->db->prepare("SELECT * FROM {$table} WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: [];
    }

    public function dashboardCounts(): array
    {
        return [
            'plans' => (int) $this->db->query('SELECT COUNT(*) FROM hosting_plans')->fetchColumn(),
            'posts' => (int) $this->db->query('SELECT COUNT(*) FROM blog_posts')->fetchColumn(),
            'inquiries' => (int) $this->db->query('SELECT COUNT(*) FROM inquiries')->fetchColumn(),
            'new_inquiries' => (int) $this->db->query("SELECT COUNT(*) FROM inquiries WHERE status = 'new'")->fetchColumn(),
        ];
    }

    public function sitemapUrls(): array
    {
        $urls = [
            ['loc' => '/', 'changefreq' => 'weekly', 'priority' => '1.0'],
            ['loc' => '/hosting', 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => '/wordpress-hosting', 'changefreq' => 'monthly', 'priority' => '0.8'],
            ['loc' => '/website-development', 'changefreq' => 'weekly', 'priority' => '0.9'],
            ['loc' => '/domains', 'changefreq' => 'weekly', 'priority' => '0.8'],
            ['loc' => '/about', 'changefreq' => 'monthly', 'priority' => '0.6'],
            ['loc' => '/contact', 'changefreq' => 'monthly', 'priority' => '0.7'],
            ['loc' => '/blog', 'changefreq' => 'weekly', 'priority' => '0.7'],
        ];

        $staticSlugs = ['about', 'contact'];
        foreach ($this->pages() as $page) {
            if (in_array($page['slug'], $staticSlugs, true)) {
                continue;
            }
            $urls[] = ['loc' => '/' . $page['slug'], 'changefreq' => 'monthly', 'priority' => '0.4'];
        }

        foreach ($this->posts(true) as $post) {
            $urls[] = ['loc' => '/blog/' . $post['slug'], 'changefreq' => 'monthly', 'priority' => '0.6'];
        }

        return $urls;
    }
}
