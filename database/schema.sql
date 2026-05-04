SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS inquiries;
DROP TABLE IF EXISTS blog_posts;
DROP TABLE IF EXISTS blog_categories;
DROP TABLE IF EXISTS faqs;
DROP TABLE IF EXISTS testimonials;
DROP TABLE IF EXISTS pages;
DROP TABLE IF EXISTS website_packages;
DROP TABLE IF EXISTS domain_tlds;
DROP TABLE IF EXISTS hosting_plans;
DROP TABLE IF EXISTS seo_settings;
DROP TABLE IF EXISTS settings;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(40) NOT NULL DEFAULT 'admin',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(120) NOT NULL UNIQUE,
    setting_value LONGTEXT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE seo_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    route_key VARCHAR(120) NOT NULL UNIQUE,
    meta_title VARCHAR(255) NULL,
    meta_description TEXT NULL,
    keywords TEXT NULL,
    og_title VARCHAR(255) NULL,
    og_image VARCHAR(255) NULL,
    canonical_url VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE hosting_plans (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    plan_type VARCHAR(60) NOT NULL DEFAULT 'shared',
    title VARCHAR(160) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    description TEXT NULL,
    monthly_price VARCHAR(60) NULL,
    yearly_price VARCHAR(60) NULL,
    storage VARCHAR(120) NULL,
    bandwidth VARCHAR(120) NULL,
    email_accounts VARCHAR(120) NULL,
    features_json TEXT NULL,
    whmcs_url VARCHAR(255) NULL,
    button_text VARCHAR(80) NOT NULL DEFAULT 'Order Now',
    badge VARCHAR(80) NULL,
    is_highlighted TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 10,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX hosting_plans_type_index (plan_type),
    INDEX hosting_plans_active_index (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE domain_tlds (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    extension VARCHAR(40) NOT NULL,
    price VARCHAR(80) NULL,
    whmcs_url VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 10,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE website_packages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(180) NOT NULL,
    price VARCHAR(60) NOT NULL,
    description TEXT NULL,
    delivery_time VARCHAR(80) NULL,
    features_json TEXT NULL,
    cta_text VARCHAR(100) NULL,
    cta_url VARCHAR(255) NULL,
    inquiry_mode TINYINT(1) NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE pages (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(180) NOT NULL UNIQUE,
    title VARCHAR(200) NOT NULL,
    body LONGTEXT NULL,
    meta_title VARCHAR(255) NULL,
    meta_description TEXT NULL,
    keywords TEXT NULL,
    og_title VARCHAR(255) NULL,
    og_image VARCHAR(255) NULL,
    canonical_url VARCHAR(255) NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE testimonials (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(140) NOT NULL,
    role VARCHAR(140) NULL,
    company VARCHAR(140) NULL,
    quote TEXT NOT NULL,
    image_url VARCHAR(255) NULL,
    rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 10,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE faqs (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    page_key VARCHAR(120) NOT NULL,
    question VARCHAR(255) NOT NULL,
    answer TEXT NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 10,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX faqs_page_index (page_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blog_categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(140) NOT NULL,
    slug VARCHAR(160) NOT NULL UNIQUE,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE blog_posts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id INT UNSIGNED NULL,
    title VARCHAR(220) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    featured_image VARCHAR(255) NULL,
    featured_alt VARCHAR(255) NULL,
    meta_title VARCHAR(255) NULL,
    meta_description TEXT NULL,
    content LONGTEXT NULL,
    status ENUM('draft', 'published') NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL,
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX blog_posts_status_index (status),
    CONSTRAINT blog_posts_category_fk FOREIGN KEY (category_id) REFERENCES blog_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE inquiries (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(160) NOT NULL,
    email VARCHAR(190) NOT NULL,
    phone VARCHAR(80) NULL,
    service VARCHAR(160) NOT NULL,
    message TEXT NOT NULL,
    ip_address VARCHAR(80) NULL,
    user_agent VARCHAR(255) NULL,
    status ENUM('new', 'read', 'replied', 'closed') NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL,
    updated_at DATETIME NOT NULL,
    INDEX inquiries_status_index (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO users (name, email, password_hash, role, is_active, created_at, updated_at) VALUES
('Planetic Admin', 'admin@planeticsolution.com', '$2y$10$p8P3ntqMKbNt5elkLVOZXuFeuMxzIi9vw/EIQX1anW1QHJJ.TIbyW', 'admin', 1, NOW(), NOW());

INSERT INTO settings (setting_key, setting_value, updated_at) VALUES
('company_name', 'Planetic Solutions', NOW()),
('tagline', 'Hosting, domains, websites and digital support for growing businesses.', NOW()),
('app_url', 'https://planeticsolution.com', NOW()),
('logo_url', '', NOW()),
('favicon_url', '', NOW()),
('og_image', '', NOW()),
('admin_email', 'hello@planeticsolution.com', NOW()),
('mail_from', 'hello@planeticsolution.com', NOW()),
('phone', '', NOW()),
('whatsapp_number', '', NOW()),
('address', '', NOW()),
('facebook_url', '', NOW()),
('instagram_url', '', NOW()),
('linkedin_url', '', NOW()),
('x_url', '', NOW()),
('whmcs_client_area_url', 'https://planeticsolution.com/clientarea/', NOW()),
('whmcs_api_enabled', '0', NOW()),
('whmcs_api_url', 'https://planeticsolution.com/clientarea/includes/api.php', NOW()),
('whmcs_api_identifier', '', NOW()),
('whmcs_api_secret', '', NOW()),
('default_order_url', 'https://planeticsolution.com/clientarea/cart.php', NOW()),
('google_analytics', '', NOW()),
('recaptcha_enabled', '0', NOW()),
('recaptcha_site_key', '', NOW()),
('recaptcha_secret_key', '', NOW()),
('cloudflare_zone_id', '', NOW()),
('cloudflare_api_token', '', NOW()),
('home_hero_title', 'Fast, Secure & Affordable Web Hosting for Your Business', NOW()),
('home_hero_subtitle', 'Planetic Solutions provides reseller hosting through WHMCS, WordPress/cPanel hosting, domain registration and complete business websites for only £200.', NOW()),
('home_primary_cta_text', 'Search Domain', NOW()),
('home_primary_cta_url', '#domain-search', NOW()),
('home_secondary_cta_text', 'View Hosting Plans', NOW()),
('home_secondary_cta_url', '/hosting', NOW()),
('home_website_cta_text', 'Get Website for £200', NOW()),
('home_website_cta_url', '/website-development', NOW()),
('home_service_cards', 'WordPress Hosting|Fast WordPress-ready hosting with SSL, cPanel and one-click installs.|panel\ncPanel Hosting|Reliable business hosting with email, databases and simple management.|cloud\nReseller Hosting|Sell hosting under your own brand with WHMCS-ready order links.|globe\nDomain Registration|Search and register domains through the connected WHMCS client area.|shield\nWebsite Development|Complete business websites delivered fast with hosting setup included.|code\nCloudflare CDN Setup|Performance and security tuning with Cloudflare CDN configuration.|bolt', NOW()),
('home_trust_badges', 'Fast hosting|Optimised hosting setup for business websites.\nFree SSL|SSL certificates included on hosting plans.\nFree Cloudflare CDN|CDN setup support for faster delivery.\ncPanel included|Familiar control panel for sites, email and files.\nWHMCS billing|Orders, renewals and invoices handled in WHMCS.\nSupport focused|Professional business support messaging.', NOW()),
('home_feature_sections', 'Speed & Performance|LiteSpeed/cache-ready wording, efficient hosting resources and Cloudflare support help your website feel quick from the first visit.|bolt\nSecurity & Backups|Free SSL, hardened hosting practices and backup-friendly cPanel workflows keep everyday business sites protected.|shield\nWordPress Ready|Install WordPress quickly, connect Elementor-friendly tooling and manage updates through a simple control panel.|panel\nFree Website Migration|Move from another provider with practical migration support and minimum disruption to your business.|cloud\nSEO Ready Hosting|Clean performance foundations, HTTPS, schema-ready pages and editable metadata built into the website.|globe\nBusiness Support|Clear support pathways through WHMCS, contact forms and direct service inquiry flows.|mail', NOW()),
('home_final_cta_title', 'Ready to launch with Planetic Solutions?', NOW()),
('home_final_cta_text', 'Choose hosting, search a domain, or order a complete £200 website package through a WHMCS-connected flow.', NOW());

INSERT INTO seo_settings (route_key, meta_title, meta_description, keywords, og_title, og_image, canonical_url, created_at, updated_at) VALUES
('home', 'Planetic Solutions | Web Hosting, Domains & £200 Websites', 'Fast, secure and affordable web hosting, reseller hosting, WordPress hosting, domain registration and £200 business websites.', 'web hosting, reseller hosting, WordPress hosting, cPanel hosting, domains, website development', 'Planetic Solutions Hosting and Website Development', '', '', NOW(), NOW()),
('hosting', 'Hosting Plans | Planetic Solutions', 'Compare editable cPanel, WordPress and reseller hosting plans with WHMCS checkout links.', 'hosting plans, cPanel hosting, reseller hosting, WordPress hosting', 'Hosting Plans from Planetic Solutions', '', '', NOW(), NOW()),
('wordpress-hosting', 'WordPress Hosting | Planetic Solutions', 'Fast WordPress hosting with free SSL, cPanel, installer support, Cloudflare CDN and Elementor-friendly setup.', 'WordPress hosting, Elementor hosting, cPanel WordPress', 'WordPress Hosting from Planetic Solutions', '', '', NOW(), NOW()),
('website-development', 'Complete Business Website in Just £200 | Planetic Solutions', 'Order a complete business website for £200 with hosting setup, domain registration support, SEO setup, Cloudflare CDN and 48 hour delivery.', '£200 website, business website, website development', 'Complete Business Website in Just £200', '', '', NOW(), NOW()),
('domains', 'Domain Registration | Planetic Solutions', 'Search and register domains through WHMCS with editable TLD display prices and live checkout rates.', 'domain registration, domain search, WHMCS domains', 'Domain Search and Registration', '', '', NOW(), NOW()),
('about', 'About Planetic Solutions | Hosting, Domains & Websites', 'Learn about Planetic Solutions, a hosting, domains, website development and digital support provider.', 'about Planetic Solutions', 'About Planetic Solutions', '', '', NOW(), NOW()),
('contact', 'Contact Planetic Solutions | Hosting and Website Support', 'Contact Planetic Solutions for hosting, domain registration, reseller hosting, Cloudflare setup or website development.', 'contact hosting provider, website development inquiry', 'Contact Planetic Solutions', '', '', NOW(), NOW()),
('blog', 'Hosting Blog | Planetic Solutions', 'Read hosting, domains, WordPress, Cloudflare and website development guides from Planetic Solutions.', 'hosting blog, WordPress tips, domain guides', 'Planetic Solutions Blog', '', '', NOW(), NOW()),
('404', 'Page Not Found | Planetic Solutions', 'The requested page could not be found.', '', 'Page Not Found', '', '', NOW(), NOW());

INSERT INTO hosting_plans (plan_type, title, slug, description, monthly_price, yearly_price, storage, bandwidth, email_accounts, features_json, whmcs_url, button_text, badge, is_highlighted, is_active, sort_order, created_at, updated_at) VALUES
('starter', 'Starter Hosting', 'starter-hosting', 'A simple hosting foundation for new business websites and small portfolios.', '4.99', '49.99', '5GB SSD', '50GB', '5 accounts', '["WHMCS billing checkout","Business email setup support"]', 'https://planeticsolution.com/clientarea/cart.php?a=add&pid=1', 'Order Starter', '', 0, 1, 10, NOW(), NOW()),
('business', 'Business Hosting', 'business-hosting', 'More room for business websites, emails and steady traffic growth.', '8.99', '89.99', '15GB SSD', '150GB', '25 accounts', '["Priority business setup support","Extra room for growing sites"]', 'https://planeticsolution.com/clientarea/cart.php?a=add&pid=2', 'Order Business', 'Recommended', 1, 1, 20, NOW(), NOW()),
('wordpress', 'WordPress Hosting', 'wordpress-hosting-plan', 'WordPress-ready hosting with installer support and cache-friendly performance wording.', '9.99', '99.99', '20GB SSD', '200GB', '25 accounts', '["WordPress auto installer","LiteSpeed/cache-ready wording","Elementor-friendly hosting"]', 'https://planeticsolution.com/clientarea/cart.php?a=add&pid=3', 'Order WordPress', '', 0, 1, 30, NOW(), NOW()),
('reseller', 'Reseller Hosting', 'reseller-hosting-plan', 'Reseller hosting for agencies and freelancers who want to sell hosting through WHMCS.', '19.99', '199.99', '50GB SSD', '500GB', 'Configurable accounts', '["cPanel/WHM ready","WHMCS product link support","Client billing through WHMCS"]', 'https://planeticsolution.com/clientarea/cart.php?a=add&pid=4', 'Order Reseller', 'Best for agencies', 0, 1, 40, NOW(), NOW());

INSERT INTO domain_tlds (extension, price, whmcs_url, is_active, sort_order, created_at, updated_at) VALUES
('.com', 'From £12.99/yr', 'https://planeticsolution.com/clientarea/cart.php?a=add&domain=register', 1, 10, NOW(), NOW()),
('.co.uk', 'From £9.99/yr', 'https://planeticsolution.com/clientarea/cart.php?a=add&domain=register', 1, 20, NOW(), NOW()),
('.net', 'From £13.99/yr', 'https://planeticsolution.com/clientarea/cart.php?a=add&domain=register', 1, 30, NOW(), NOW()),
('.org', 'From £12.99/yr', 'https://planeticsolution.com/clientarea/cart.php?a=add&domain=register', 1, 40, NOW(), NOW());

INSERT INTO website_packages (id, title, price, description, delivery_time, features_json, cta_text, cta_url, inquiry_mode, is_active, created_at, updated_at) VALUES
(1, 'Complete Website in Just £200', '200', 'Professional business website package with hosting setup, domain registration support, licensed premium tools where available, stock images, basic content writing, SEO setup and Cloudflare CDN.', '48 hours', '["Professional business website","Free domain subject to package/availability","Free hosting where included in package","Elementor Pro/page builder setup where legally licensed by us","Envato templates/assets where legally licensed by us","Free stock photos","Free basic content writing","Basic SEO setup","Contact forms","Mobile responsive design","Free Cloudflare CDN","Delivery in 48 hours"]', 'Order Website Package', 'https://planeticsolution.com/clientarea/cart.php?a=add&pid=5', 0, 1, NOW(), NOW());

INSERT INTO pages (slug, title, body, meta_title, meta_description, keywords, og_title, og_image, canonical_url, created_at, updated_at) VALUES
('about', 'About Planetic Solutions', '<p>Planetic Solutions provides practical hosting, domain registration, website development and digital support for businesses that want a reliable online foundation without unnecessary complexity.</p><p>Our services are built around clear order flows, WHMCS billing, cPanel hosting, WordPress-ready setup, Cloudflare CDN support and affordable business website packages.</p><p>We focus on dependable setup, professional communication and flexible services that can grow with your business.</p>', 'About Planetic Solutions', 'Professional company overview for Planetic Solutions, covering hosting, domains, websites and digital support.', 'Planetic Solutions, hosting company, website development', 'About Planetic Solutions', '', '', NOW(), NOW()),
('contact', 'Tell Us What You Need', '<p>Ask about hosting, reseller hosting, domain registration, Cloudflare setup or the £200 website package.</p>', 'Contact Planetic Solutions', 'Contact Planetic Solutions for hosting, domains, website development and support inquiries.', 'contact Planetic Solutions', 'Contact Planetic Solutions', '', '', NOW(), NOW()),
('privacy-policy', 'Privacy Policy', '<p>This Privacy Policy explains how Planetic Solutions collects, uses and protects personal information submitted through this website, WHMCS client area, billing systems and contact forms.</p><h2>Information we collect</h2><p>We may collect contact details, billing details, service inquiry information, domain registration details and technical information required to provide hosting and website services.</p><h2>How we use information</h2><p>Information is used to provide services, respond to inquiries, manage orders, maintain security, meet legal obligations and improve support.</p><h2>Contact</h2><p>Contact Planetic Solutions if you need your personal data updated, exported or removed where legally possible.</p>', 'Privacy Policy | Planetic Solutions', 'Privacy Policy for Planetic Solutions hosting, domains and website development services.', 'privacy policy', 'Privacy Policy', '', '', NOW(), NOW()),
('terms-and-conditions', 'Terms and Conditions', '<p>These Terms and Conditions outline the general rules for using Planetic Solutions services, including hosting, domain registration, website development and related support.</p><h2>Services</h2><p>Hosting and domain orders are processed through WHMCS. Domain registration, renewals and billing are subject to the terms shown at checkout and any applicable registry rules.</p><h2>Website development</h2><p>The £200 website package includes the items described on the website package page. Premium tools, Elementor Pro and Envato assets are included only where legally licensed by Planetic Solutions.</p><h2>Acceptable use</h2><p>Customers must not use services for illegal, abusive, harmful or resource-abusive activity.</p>', 'Terms and Conditions | Planetic Solutions', 'Terms and Conditions for Planetic Solutions hosting, domains and website development services.', 'terms, hosting terms', 'Terms and Conditions', '', '', NOW(), NOW()),
('refund-policy', 'Refund Policy', '<p>This Refund Policy explains how Planetic Solutions handles refund requests for hosting, domains, website development and related services.</p><h2>Hosting</h2><p>Hosting refund eligibility depends on the package, billing term and the terms shown during WHMCS checkout.</p><h2>Domains</h2><p>Domain registrations, renewals and transfers are usually non-refundable once submitted to the registry.</p><h2>Website development</h2><p>Website development payments may be non-refundable once work has started, unless otherwise agreed in writing.</p>', 'Refund Policy | Planetic Solutions', 'Refund Policy for hosting, domains and website development services from Planetic Solutions.', 'refund policy, hosting refunds', 'Refund Policy', '', '', NOW(), NOW()),
('acceptable-use-policy', 'Acceptable Use Policy', '<p>This Acceptable Use Policy sets expectations for safe, lawful and fair use of Planetic Solutions hosting and related services.</p><h2>Prohibited activity</h2><p>Customers must not host malware, phishing, spam systems, illegal content, abusive scripts, copyright-infringing material or content that harms other users or networks.</p><h2>Resource use</h2><p>Services must be used within the limits of the selected package and any WHMCS product terms.</p><h2>Enforcement</h2><p>Planetic Solutions may suspend or restrict services to protect platform security, compliance and other customers.</p>', 'Acceptable Use Policy | Planetic Solutions', 'Acceptable Use Policy for Planetic Solutions hosting services.', 'acceptable use policy, hosting rules', 'Acceptable Use Policy', '', '', NOW(), NOW());

INSERT INTO testimonials (name, role, company, quote, image_url, rating, is_active, sort_order, created_at, updated_at) VALUES
('Placeholder Client One', 'Business Owner', 'Replace With Real Company', 'Planetic Solutions made the hosting and website setup process clear and straightforward. Replace this placeholder with a real testimonial.', '', 5, 1, 10, NOW(), NOW()),
('Placeholder Client Two', 'Founder', 'Replace With Real Company', 'The WHMCS order flow and support process helped us get online quickly. Replace this placeholder with a real testimonial.', '', 5, 1, 20, NOW(), NOW()),
('Placeholder Client Three', 'Consultant', 'Replace With Real Company', 'Our website package was easy to understand and ready fast. Replace this placeholder with a real testimonial.', '', 5, 1, 30, NOW(), NOW());

INSERT INTO faqs (page_key, question, answer, is_active, sort_order, created_at, updated_at) VALUES
('home', 'How do hosting orders work?', '<p>Hosting buttons send customers to the WHMCS checkout URL configured for each plan in the admin dashboard.</p>', 1, 10, NOW(), NOW()),
('home', 'Can I edit the prices and plan features?', '<p>Yes. The admin dashboard lets you edit plan title, price, storage, bandwidth, email accounts, features, badge, button text and WHMCS order URL.</p>', 1, 20, NOW(), NOW()),
('home', 'Is the £200 website offer editable?', '<p>Yes. You can edit the price, included features, delivery time and CTA/order link from the dashboard.</p>', 1, 30, NOW(), NOW()),
('hosting', 'Do plans include cPanel?', '<p>Yes. The seeded plans include cPanel wording, and you can edit plan features from the admin dashboard.</p>', 1, 10, NOW(), NOW()),
('hosting', 'Do you support reseller hosting?', '<p>Yes. The reseller plan can link directly to the correct WHMCS product checkout URL.</p>', 1, 20, NOW(), NOW()),
('wordpress', 'Is WordPress auto installer support included?', '<p>The WordPress hosting page includes auto installer wording and cPanel support messaging. Confirm exact installer availability in your WHMCS product terms.</p>', 1, 10, NOW(), NOW()),
('wordpress', 'Can you build the WordPress website for me?', '<p>Yes. The website development package is available for £200 and can be ordered or requested from the website.</p>', 1, 20, NOW(), NOW()),
('website-development', 'What is included in the £200 website package?', '<p>The package includes professional website setup, hosting setup, domain registration support, stock images, basic content writing, SEO setup, contact forms, responsive design and Cloudflare CDN.</p>', 1, 10, NOW(), NOW()),
('website-development', 'Are Elementor Pro and Envato assets included?', '<p>They are included only where legally licensed by Planetic Solutions. This wording is intentionally clear so the offer remains compliant.</p>', 1, 20, NOW(), NOW()),
('website-development', 'How fast is delivery?', '<p>The offer states delivery in 48 hours, subject to receiving the required content and access details.</p>', 1, 30, NOW(), NOW()),
('domains', 'Are domain prices live from WHMCS?', '<p>Checkout and billing rates are confirmed in WHMCS. The display cards on this website can be edited manually if live API pricing is not enabled.</p>', 1, 10, NOW(), NOW()),
('domains', 'What happens after I search a domain?', '<p>The website redirects to the WHMCS domain checker/cart with the searched domain pre-filled, or shows an availability result if WHMCS API checking is enabled.</p>', 1, 20, NOW(), NOW());

INSERT INTO blog_categories (name, slug, created_at, updated_at) VALUES
('Hosting Guides', 'hosting-guides', NOW(), NOW()),
('Website Tips', 'website-tips', NOW(), NOW());

INSERT INTO blog_posts (category_id, title, slug, featured_image, featured_alt, meta_title, meta_description, content, status, published_at, created_at, updated_at) VALUES
(1, 'How to Choose the Right Hosting Plan for a Small Business', 'choose-right-hosting-plan-small-business', '', 'Hosting plan comparison for a small business website', 'How to Choose the Right Hosting Plan for a Small Business', 'A simple guide to choosing hosting based on storage, email, WordPress support, SSL, cPanel and future growth.', '<p>Choosing hosting starts with your website goals. A small brochure website may only need starter resources, while a busy WordPress site or client portfolio needs more storage, email capacity and room to grow.</p><h2>Start with your platform</h2><p>If you are using WordPress, choose hosting with WordPress installer support, free SSL and cache-ready performance wording.</p><h2>Check billing and support</h2><p>WHMCS-connected order flows make billing, invoices, renewals and support easier to manage from one client area.</p><h2>Plan for growth</h2><p>Choose a plan that can be upgraded as traffic, email accounts and content grow.</p>', 'published', NOW(), NOW(), NOW());
