# Planetic Solutions Website

Production-ready plain PHP/MySQL website for Planetic Solutions with a responsive hosting-company frontend, main-site checkout, on-site Stripe invoice payment, customer account pages, WHMCS-backed orders/domains/provisioning, SEO controls, blog, editable legal pages, contact inquiry storage and a secure admin CMS.

## Requirements

- PHP 8.0 or newer with PDO MySQL enabled
- MySQL 5.7+/MariaDB 10.3+
- Apache with `mod_rewrite` enabled
- cPanel file manager, FTP, or Git deployment

## Installation on cPanel

1. Upload the project files to your cPanel site folder, usually `public_html`.
2. Make sure `.htaccess` is uploaded. It protects `app/`, `database/`, `storage/` and `.env`.
3. Create a MySQL database and user in cPanel.
4. Import `database/schema.sql` into the database using phpMyAdmin. If you already imported an earlier version, back up your database and import `database/stripe_account_update.sql` for the Stripe/account tables; import `database/redesign_update.sql` only if you also want the latest content defaults.
5. Edit `.env` with your database credentials:

```env
DB_HOST=localhost
DB_DATABASE=your_database
DB_USERNAME=your_database_user
DB_PASSWORD=your_database_password
APP_URL=https://planeticsolution.com
```

6. Ensure `uploads/` is writable by PHP. On most cPanel servers `755` is enough.
7. Visit `https://yourdomain.com/admin/login`.

Default admin login after importing the SQL:

- Email: `admin@planeticsolution.com`
- Password: `ChangeMe123!`

Change this password immediately after first login by updating the `users.password_hash` value or adding your own user row with a PHP `password_hash()` value.

## WHMCS Setup

Your WHMCS client area URL is already seeded as:

`https://planeticsolution.com/clientarea/`

In Admin > Settings and `.env`, configure:

- WHMCS Client Area URL
- WHMCS API URL, usually `https://planeticsolution.com/clientarea/includes/api.php`
- WHMCS API Identifier
- WHMCS API Secret
- WHMCS payment gateway system name, for example `stripe` or `paypal`
- WHMCS gateway name used when recording Stripe payments against invoices
- Product IDs for hosting plans and the £199 website package

The `.env` file should include:

```env
WHMCS_URL=https://planeticsolution.com/clientarea
WHMCS_API_URL=https://planeticsolution.com/clientarea/includes/api.php
WHMCS_API_IDENTIFIER=your_identifier
WHMCS_API_SECRET=your_secret
WHMCS_API_ACCESS_KEY=
WHMCS_API_SSL_VERIFY=true
WHMCS_LOCAL_API_BRIDGE_URL=
WHMCS_LOCAL_API_BRIDGE_TOKEN=
WHMCS_PAYMENT_METHOD=stripe
WHMCS_PAYMENT_GATEWAY_NAME=
WHMCS_STARTER_HOSTING_PID=1
WHMCS_BUSINESS_HOSTING_PID=2
WHMCS_WORDPRESS_HOSTING_PID=3
WHMCS_RESELLER_HOSTING_PID=4
WHMCS_WEBSITE_PACKAGE_PID=5
WHMCS_DOMAIN_REGISTRAR=
WHMCS_STARTER_WHM_PACKAGE=planetic_starter
WHMCS_BUSINESS_WHM_PACKAGE=planetic_business
WHMCS_PRO_WHM_PACKAGE=planetic_pro
WHMCS_AGENCY_WHM_PACKAGE=planetic_agency
WHMCS_ECOMMERCE_WHM_PACKAGE=planetic_agency
WHMCS_WEBSITE_PRICE_OVERRIDE=199.00
STRIPE_PUBLISHABLE_KEY=
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
```

The homepage domain form now sends visitors to:

`/domain-search?domain=searched-domain`

The results page calls the backend-only endpoint:

`/api/domain-search?domain=searched-domain`

That endpoint validates the domain server-side, calls WHMCS `DomainWhois`, fetches live WHMCS prices with `GetTLDPricing`, and returns safe JSON for the frontend. WHMCS API credentials are never exposed in browser JavaScript.

If WHMCS has API IP access restrictions enabled with an access key, set `WHMCS_API_ACCESS_KEY` in `.env`. The integration uses cURL when available and logs safe diagnostics to `storage/logs/whmcs-api.log` if cPanel cannot reach WHMCS.

If the log shows `Invalid IP 185.61.154.29`, allow `185.61.154.29` in WHMCS API IP access settings or set the matching API access key in `.env`.

If WHMCS continues rejecting external API calls with `Invalid IP`, use the included local bridge instead:

1. Upload `whmcs-bridge/planetic-local-api.php` into `public_html/clientarea/planetic-local-api.php`.
2. Edit the uploaded file and replace `change_this_long_random_token` with a long random token.
3. Add the same token and bridge URL to `.env`:

```env
WHMCS_LOCAL_API_BRIDGE_URL=https://planeticsolution.com/clientarea/planetic-local-api.php
WHMCS_LOCAL_API_BRIDGE_TOKEN=the_same_long_random_token
```

The bridge uses WHMCS `localAPI()` inside the WHMCS installation and avoids the external API IP restriction.

If the log says `Bridge authentication failed`, the token in the main website `.env` does not match the token in `clientarea/planetic-local-api.php`. You can also define the bridge token in WHMCS `configuration.php`:

```php
$planetic_bridge_token = 'the_same_long_random_token';
```

Domain-only, hosting-only, domain + hosting and website package buttons now send visitors to:

`/checkout`

The checkout page creates or finds the WHMCS client, calls `AddOrder`, stores the returned invoice locally, creates pending local purchase/provisioning records, and keeps the customer on the Planetic Solutions website for Stripe Payment Element checkout. WHMCS remains the backend for invoices, domains, service records, provisioning, renewals and support.

## Stripe On-Site Payment

Stripe keys are configured only in `.env`; never hard-code real keys:

```env
STRIPE_PUBLISHABLE_KEY=pk_live_or_test_xxx
STRIPE_SECRET_KEY=sk_live_or_test_xxx
STRIPE_WEBHOOK_SECRET=whsec_xxx
WHMCS_PAYMENT_GATEWAY_NAME=your_whmcs_gateway_system_name
```

Webhook endpoint:

`https://planeticsolution.com/stripe/webhook`

Enable these Stripe webhook events:

- `payment_intent.succeeded`
- `payment_intent.payment_failed`

The webhook verifies Stripe signatures, validates amount/currency against the saved invoice mapping, then records successful payments in WHMCS with `AddInvoicePayment`. Frontend redirects do not mark invoices as paid.

After WHMCS confirms the invoice is paid, the site accepts the WHMCS order, requests registrar submission for domain purchases, requests hosting account creation for paid hosting services, and creates/updates website development project records. Duplicate Stripe webhook events are stored and ignored after processing.

Hosting and website package product IDs can be updated in:

- `.env`
- `app/config/whmcs.php`
- Admin > Hosting Plans, where existing WHMCS product URLs are still used as a PID fallback

The local WHM package labels are mapped as Starter `planetic_starter`, Business `planetic_business`, Pro `planetic_pro`, and Agency/Ecommerce `planetic_agency`. The actual cPanel package assignment still needs to be confirmed inside each WHMCS product/module setting.

Full setup notes are in `docs/main-site-checkout.md`.

## Domain Pricing

Live domain checkout pricing should remain controlled inside WHMCS. The `/domain-search` results page reads live prices for `.com`, `.net`, `.org`, `.co.uk`, `.xyz` and `.online` from WHMCS `GetTLDPricing`. The website also has editable TLD display cards for marketing sections; update those manually in Admin > Domains/TLDs when needed.

## Admin CMS Features

- Homepage hero, CTAs, service cards, trust badges and feature sections
- Hosting plans with prices, features, WHMCS product mapping and highlighted badges
- Customer accounts, domains, hosting, local invoice/payment mappings, provisioning records and Stripe webhook event tracking tables
- £199 website development package
- Website project progress management with private internal notes and customer-facing notes
- TLD display prices and domain URLs
- About and legal pages
- Blog posts with slug, category, metadata, featured image, alt text and status
- Testimonials and FAQs
- Contact form submissions
- SEO metadata, Open Graph and canonical URLs
- Site settings, social links, reCAPTCHA, tracking code, WHMCS and Cloudflare config
- JSON database content export from Admin > Export Backup

## Contact Form

Contact submissions are stored in Admin > Inquiries. The app also attempts to send an email to the configured admin email using PHP `mail()`. If your host requires SMTP, configure server-level mail routing or replace `app/Services/Mailer.php` with your SMTP provider logic.

Google reCAPTCHA can be enabled from Admin > Settings by adding the site key and secret key.

## SEO

The site includes:

- Clean URLs via `.htaccess`
- Editable meta title, description and keywords
- Open Graph and Twitter card tags
- Organization and ProfessionalService schema
- Product/Service schema for hosting plans
- FAQ schema
- Breadcrumb schema
- Dynamic `/sitemap.xml`
- Dynamic `/robots.txt`
- Lazy loading for content images

## GitHub to cPanel Deployment

Option 1: cPanel Git Version Control

1. Push this project to a private GitHub repository.
2. In cPanel, open Git Version Control and clone the repository into your site folder.
3. Copy `.env.example` to `.env` if needed and fill in production values.
4. Import `database/schema.sql`.
   - If you already imported an earlier version, import `database/stripe_account_update.sql`, `database/customer_order_checkout_metadata.sql` and `database/provisioning_and_website_projects.sql` after backing up your database.
   - If you also want the latest content defaults, import `database/redesign_update.sql`.
5. Pull future changes from cPanel Git Version Control.

Option 2: Manual deploy

1. Zip the project.
2. Upload through cPanel File Manager.
3. Extract into `public_html`.
4. Edit `.env`.
5. Import `database/schema.sql`, or import `database/stripe_account_update.sql`, `database/customer_order_checkout_metadata.sql` and `database/provisioning_and_website_projects.sql` for an existing installation.

## Security Notes

- `.htaccess` blocks direct access to application and database folders.
- Admin passwords use PHP `password_hash()` / `password_verify()`.
- Admin forms use CSRF tokens.
- Customer login, registration, profile and billing payment actions use CSRF tokens where appropriate.
- Database queries use PDO prepared statements.
- Stripe secret and webhook keys remain server-side; card details are handled by Stripe and are never stored by the application.
- Stripe webhook event IDs are stored to prevent duplicate processing.
- Domains and hosting are not provisioned before verified payment.
- Uploaded images are validated with `getimagesize()` and extension checks.
- Contact form entries are escaped on output.
- Rich page/blog/FAQ HTML is filtered to a safe allowlist before saving.

## File Structure

```text
app/
  Controllers/
  Core/
  Models/
  Services/
  Views/
assets/
  css/style.css
  js/app.js
database/schema.sql
database/stripe_account_update.sql
uploads/
.env
.htaccess
index.php
```

## Customisation Checklist

1. Change the default admin login.
2. Add your logo, favicon and Open Graph image in Admin > Settings.
3. Update WHMCS product IDs for every hosting plan.
4. Add Stripe keys and configure the Stripe webhook.
5. Set `WHMCS_PAYMENT_GATEWAY_NAME` to the exact WHMCS gateway system name you want recorded on invoice payments.
6. Replace placeholder testimonials with real client testimonials.
7. Review legal pages with a qualified professional before publishing.
8. Add Google Analytics/tracking code if required.
9. Enable reCAPTCHA once your Google keys are ready.
