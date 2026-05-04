SET NAMES utf8mb4;

UPDATE settings SET setting_value = 'Planetic Solutions provides reliable hosting, domain registration and complete business websites with WHMCS-powered billing, cPanel access and Cloudflare CDN support.', updated_at = NOW()
WHERE setting_key = 'home_hero_subtitle';

UPDATE settings SET setting_value = 'WordPress Hosting|Fast WordPress-ready hosting with SSL, cPanel and one-click installs.|panel|/wordpress-hosting\ncPanel Hosting|Reliable business hosting with email, databases and simple management.|cloud|/hosting\nReseller Hosting|Sell hosting under your own brand with WHMCS-ready order links.|globe|/hosting#reseller-hosting\nDomain Registration|Search and register domains through the connected WHMCS client area.|shield|/domains\nWebsite Development|Complete business websites delivered fast with hosting setup included.|code|/website-development\nCloudflare CDN Setup|Performance and security tuning with Cloudflare CDN configuration.|bolt|/contact', updated_at = NOW()
WHERE setting_key = 'home_service_cards';

UPDATE settings SET setting_value = 'Free SSL|Secure every eligible hosting plan.\ncPanel Hosting|Familiar website and email control.\nWHMCS Billing|Orders, renewals and invoices handled.\nCloudflare CDN|Performance and security setup support.\n48h Website Delivery|Fast delivery for the website package.\nUK-focused Support|Professional support messaging for businesses.', updated_at = NOW()
WHERE setting_key = 'home_trust_badges';
