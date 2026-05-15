SET NAMES utf8mb4;

INSERT INTO settings (setting_key, setting_value, updated_at)
VALUES ('domain_hosting_pid', '2', NOW())
ON DUPLICATE KEY UPDATE setting_value = IF(setting_value = '', VALUES(setting_value), setting_value), updated_at = NOW();

UPDATE settings SET setting_value = 'Planetic Solutions provides reliable hosting, domain registration and complete business websites with WHMCS-powered billing, cPanel access and Cloudflare CDN support.', updated_at = NOW()
WHERE setting_key = 'home_hero_subtitle';

UPDATE settings SET setting_value = 'WordPress Hosting|Fast WordPress-ready hosting with SSL, cPanel and one-click installs.|panel|/wordpress-hosting\ncPanel Hosting|Reliable business hosting with email, databases and simple management.|cloud|/hosting\nReseller Hosting|Sell hosting under your own brand with WHMCS-ready order links.|globe|/hosting#reseller-hosting\nDomain Registration|Search and register domains through the connected WHMCS client area.|shield|/domains\nWebsite Development|Complete business websites delivered fast with hosting setup included.|code|/website-development\nCloudflare CDN Setup|Performance and security tuning with Cloudflare CDN configuration.|bolt|/contact', updated_at = NOW()
WHERE setting_key = 'home_service_cards';

UPDATE settings SET setting_value = 'Free SSL|Secure every eligible hosting plan.\ncPanel Hosting|Familiar website and email control.\nWHMCS Billing|Orders, renewals and invoices handled.\nCloudflare CDN|Performance and security setup support.\n48h Website Delivery|Fast delivery for the website package.\nUK-focused Support|Professional support messaging for businesses.', updated_at = NOW()
WHERE setting_key = 'home_trust_badges';

UPDATE faqs SET answer = '<p>The website shows a custom availability results page using the backend WHMCS API integration. When you choose a domain, checkout and billing continue securely inside WHMCS.</p>', updated_at = NOW()
WHERE category = 'domains' AND question = 'What happens after I search a domain?';
