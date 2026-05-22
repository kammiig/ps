SET NAMES utf8mb4;

INSERT INTO settings (setting_key, setting_value, updated_at)
VALUES ('domain_hosting_pid', '2', NOW())
ON DUPLICATE KEY UPDATE setting_value = IF(setting_value = '', VALUES(setting_value), setting_value), updated_at = NOW();

INSERT INTO settings (setting_key, setting_value, updated_at)
VALUES ('whmcs_payment_method', 'stripe', NOW())
ON DUPLICATE KEY UPDATE setting_value = IF(setting_value = '', VALUES(setting_value), setting_value), updated_at = NOW();

UPDATE settings SET setting_value = 'Planetic Solutions provides reliable hosting, domain registration and complete business websites with WHMCS-powered billing, cPanel access and Cloudflare CDN support.', updated_at = NOW()
WHERE setting_key = 'home_hero_subtitle';

UPDATE settings SET setting_value = 'WordPress Hosting|Fast WordPress-ready hosting with SSL, cPanel and one-click installs.|panel|/wordpress-hosting\ncPanel Hosting|Reliable business hosting with email, databases and simple management.|cloud|/hosting\nReseller Hosting|Sell hosting under your own brand with WHMCS-backed billing.|globe|/hosting#reseller-hosting\nDomain Registration|Search and register domains through the main website checkout.|shield|/domains\nWebsite Development|Complete business websites delivered fast with hosting setup included.|code|/website-development\nCloudflare CDN Setup|Performance and security tuning with Cloudflare CDN configuration.|bolt|/contact', updated_at = NOW()
WHERE setting_key = 'home_service_cards';

UPDATE settings SET setting_value = 'Free SSL|Secure every eligible hosting plan.\ncPanel Hosting|Familiar website and email control.\nWHMCS Billing|Orders, renewals and invoices handled.\nCloudflare CDN|Performance and security setup support.\n48h Website Delivery|Fast delivery for the website package.\nUK-focused Support|Professional support messaging for businesses.', updated_at = NOW()
WHERE setting_key = 'home_trust_badges';

UPDATE settings SET setting_value = '/checkout', updated_at = NOW()
WHERE setting_key = 'default_order_url' AND setting_value LIKE '%clientarea%';

UPDATE settings SET setting_value = 'Get Website for £199', updated_at = NOW()
WHERE setting_key = 'home_website_cta_text';

UPDATE settings SET setting_value = 'Choose hosting, search a domain, or order a bespoke £199 website package through a WHMCS-connected flow.', updated_at = NOW()
WHERE setting_key = 'home_final_cta_text';

UPDATE website_packages
SET title = 'Bespoke Website Development',
    price = '199',
    description = 'Launch a professional business website with domain, hosting setup, Elementor, premium Envato elements, stock photos, content writing and Cloudflare integration included.',
    features_json = '["Professional business website","Free domain and hosting for 1 year","Free Elementor","Free Envato premium elements","Free stock photos","Free premium content writing","Free Cloudflare integration","Free SSL setup","Free domain and hosting setup support"]',
    cta_text = 'Get Complete Website Package',
    cta_url = '/checkout?type=website',
    updated_at = NOW()
WHERE id = 1;

UPDATE seo_settings
SET meta_title = 'Planetic Solutions | Web Hosting, Domains & £199 Websites',
    meta_description = 'Fast, secure and affordable web hosting, reseller hosting, WordPress hosting, domain registration and £199 business websites.',
    updated_at = NOW()
WHERE route_key = 'home';

UPDATE seo_settings
SET meta_title = 'Bespoke Website Development for just £199 | Planetic Solutions',
    meta_description = 'Order a bespoke business website for £199 with first-year domain and hosting support, Elementor setup, content writing, SSL, Cloudflare CDN and 48 hour delivery.',
    keywords = '£199 website, business website, website development',
    og_title = 'Bespoke Website Development for just £199',
    updated_at = NOW()
WHERE route_key = 'website-development';

UPDATE pages
SET body = '<p>Ask about hosting, reseller hosting, domain registration, Cloudflare setup or the £199 website package.</p>',
    updated_at = NOW()
WHERE slug = 'contact';

UPDATE pages
SET body = REPLACE(body, '£200 website package', '£199 website package'),
    updated_at = NOW()
WHERE slug = 'terms-and-conditions';

UPDATE faqs SET question = 'Is the £199 website offer editable?', updated_at = NOW()
WHERE page_key = 'home' AND question = 'Is the £200 website offer editable?';

UPDATE faqs SET answer = '<p>Yes. The website development package is available for £199 and can be ordered from the website.</p>', updated_at = NOW()
WHERE page_key = 'wordpress' AND question = 'Can you build the WordPress website for me?';

UPDATE faqs SET question = 'What is included in the £199 website package?',
    answer = '<p>The package includes a professional business website, first-year domain and hosting support, Elementor, Envato premium elements, stock photos, premium content writing, SSL and Cloudflare integration.</p>',
    updated_at = NOW()
WHERE page_key = 'website-development' AND question = 'What is included in the £200 website package?';

UPDATE faqs SET answer = '<p>The website shows a custom availability results page using the backend WHMCS API integration. When you choose a domain, checkout and billing continue securely inside WHMCS.</p>', updated_at = NOW()
WHERE page_key = 'domains' AND question = 'What happens after I search a domain?';
