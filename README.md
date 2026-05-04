# Planetic Solutions Website

Production-ready plain PHP/MySQL website for Planetic Solutions with a responsive hosting-company frontend, WHMCS order links, domain search redirect/API support, SEO controls, blog, editable legal pages, contact inquiry storage and a secure admin CMS.

## Requirements

- PHP 8.0 or newer with PDO MySQL enabled
- MySQL 5.7+/MariaDB 10.3+
- Apache with `mod_rewrite` enabled
- cPanel file manager, FTP, or Git deployment

## Installation on cPanel

1. Upload the project files to your cPanel site folder, usually `public_html`.
2. Make sure `.htaccess` is uploaded. It protects `app/`, `database/`, `storage/` and `.env`.
3. Create a MySQL database and user in cPanel.
4. Import `database/schema.sql` into the database using phpMyAdmin. If you already imported an earlier version and only need the redesign content defaults, back up your database and import `database/redesign_update.sql`.
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

In Admin > Settings, configure:

- WHMCS Client Area URL
- WHMCS API URL, usually `https://planeticsolution.com/clientarea/includes/api.php`
- WHMCS API Identifier
- WHMCS API Secret
- Enable WHMCS API Domain Checks

If API credentials are not enabled, domain searches redirect to:

`/clientarea/cart.php?a=add&domain=register&query=searched-domain`

Hosting and website package buttons use editable WHMCS URLs. Update them in:

- Admin > Hosting Plans
- Admin > Website Package
- Admin > Domains/TLDs
- Admin > Settings > Default Get Started / WHMCS Order URL

Typical WHMCS product URL:

```text
https://planeticsolution.com/clientarea/cart.php?a=add&pid=PRODUCT_ID
```

## Domain Pricing

Live domain checkout pricing should remain controlled inside WHMCS. The website has editable TLD display cards for `.com`, `.co.uk`, `.net` and `.org`. If you do not use WHMCS API pricing, update display prices manually in Admin > Domains/TLDs.

## Admin CMS Features

- Homepage hero, CTAs, service cards, trust badges and feature sections
- Hosting plans with prices, features, WHMCS checkout URLs and highlighted badges
- £200 website development package
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
   - If you already imported an earlier version and only want the redesign content defaults, import `database/redesign_update.sql` after backing up your database.
5. Pull future changes from cPanel Git Version Control.

Option 2: Manual deploy

1. Zip the project.
2. Upload through cPanel File Manager.
3. Extract into `public_html`.
4. Edit `.env`.
5. Import `database/schema.sql`.

## Security Notes

- `.htaccess` blocks direct access to application and database folders.
- Admin passwords use PHP `password_hash()` / `password_verify()`.
- Admin forms use CSRF tokens.
- Database queries use PDO prepared statements.
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
uploads/
.env
.htaccess
index.php
```

## Customisation Checklist

1. Change the default admin login.
2. Add your logo, favicon and Open Graph image in Admin > Settings.
3. Update WHMCS product IDs for every hosting plan.
4. Update the website package WHMCS product URL or switch it to inquiry flow.
5. Replace placeholder testimonials with real client testimonials.
6. Review legal pages with a qualified professional before publishing.
7. Add Google Analytics/tracking code if required.
8. Enable reCAPTCHA once your Google keys are ready.
