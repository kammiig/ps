# Main-Site WHMCS Checkout Flow

This project keeps the buying experience on `https://planeticsolution.com/` while WHMCS remains the backend for clients, orders, invoices, domains, hosting provisioning, renewals and service management.

## File Structure

```text
app/config/whmcs.php                  Server-side WHMCS/product/domain config
app/Services/WhmcsService.php         WHMCS API wrapper
app/Services/StripeService.php        Server-side Stripe PaymentIntent and webhook helper
app/Controllers/SiteController.php    Domain API, checkout page and order submit endpoint
app/Controllers/PaymentController.php On-site invoice payment and Stripe webhook endpoint
app/Controllers/AccountController.php Customer login, dashboard, domains, hosting, website projects, billing and profile
app/Models/ProvisioningRepository.php Local provisioning and website project records
app/Views/site/domain-search.php      Domain results page
app/Views/site/checkout.php           Main-site checkout form
app/Views/site/payment.php            Stripe Payment Element page
app/Views/account/                    Customer account pages
assets/js/app.js                      Domain result rendering
assets/css/style.css                  Checkout/domain results styling
database/stripe_account_update.sql    Existing-site migration for customer/payment tables
wordpress/planetic-whmcs-shortcodes.php Optional WordPress bridge shortcodes
storage/logs/whmcs-api.log            Safe WHMCS API error log
```

## URLs

```text
GET  /domain-search?domain=example.com
GET  /api/domain-search?domain=example.com
GET  /checkout
POST /checkout
GET  /checkout/payment/{token}
GET  /checkout/success
GET  /checkout/payment-failed
POST /stripe/webhook
GET  /account/dashboard
GET  /account/domains
GET  /account/hosting
GET  /account/website-development
GET  /account/services
GET  /account/billing
GET  /account/profile
GET  /admin/website-projects
```

## WHMCS API Actions Used

- `DomainWhois` checks domain availability.
- `GetTLDPricing` fetches live domain prices.
- `GetClientsDetails` finds an existing client by email.
- `AddClient` creates a new WHMCS client when no matching email exists.
- `AddOrder` creates the WHMCS order and invoice.
- `GetInvoice` loads the exact invoice amount before creating a Stripe PaymentIntent.
- `GetInvoices` loads customer invoices for `/account/billing`.
- `GetClientsProducts` and `GetClientsDomains` load services/domains for the customer account.
- `UpdateClient` syncs customer profile edits.
- `AddInvoicePayment` records verified Stripe payments against the existing WHMCS invoice.
- `AcceptOrder` runs only after verified payment, with automatic setup enabled and registrar submission requested.
- `ModuleCreate` is used only after verified payment for paid hosting services that still need setup.
- `CreateSsoToken` exists for safe WHMCS SSO flows, but cPanel buttons are only shown when a safe URL is available.

## Product ID Mapping

Set product IDs in `.env`:

```env
WHMCS_API_URL=https://planeticsolution.com/clientarea/includes/api.php
WHMCS_API_IDENTIFIER=your_identifier
WHMCS_API_SECRET=your_secret
WHMCS_API_ACCESS_KEY=
WHMCS_API_SSL_VERIFY=true
WHMCS_PAYMENT_METHOD=stripe
WHMCS_PAYMENT_GATEWAY_NAME=
WHMCS_DOMAIN_REGISTRAR=
WHMCS_STARTER_HOSTING_PID=1
WHMCS_STARTER_WHM_PACKAGE=planetic_starter
WHMCS_BUSINESS_HOSTING_PID=2
WHMCS_BUSINESS_WHM_PACKAGE=planetic_business
WHMCS_PRO_HOSTING_PID=3
WHMCS_PRO_WHM_PACKAGE=planetic_pro
WHMCS_AGENCY_HOSTING_PID=4
WHMCS_AGENCY_WHM_PACKAGE=planetic_agency
WHMCS_ECOMMERCE_HOSTING_PID=4
WHMCS_ECOMMERCE_WHM_PACKAGE=planetic_agency
WHMCS_WEBSITE_PACKAGE_PID=5
WHMCS_WEBSITE_PRICE_OVERRIDE=199.00
WHMCS_WEBSITE_REGISTER_DOMAIN=true
WHMCS_WEBSITE_DOMAIN_PRICE_OVERRIDE=0.00
STRIPE_PUBLISHABLE_KEY=
STRIPE_SECRET_KEY=
STRIPE_WEBHOOK_SECRET=
```

Or edit `app/config/whmcs.php` if you prefer file-based mapping. Product IDs must match the IDs in WHMCS Admin > Products/Services.

## Domain Pricing Mapping

Domain display fallback pricing and registration periods are in `app/config/whmcs.php`:

```php
'domain_pricing' => [
    '.com' => ['price' => '12.99', 'regperiod' => 1],
    '.co.uk' => ['price' => '9.99', 'regperiod' => 1],
]
```

Checkout does not trust frontend prices. WHMCS creates the actual invoice and pricing, except when the website package uses the server-side `price_override` setting for the £199 package price and `domain_price_override` for a free first-year domain.

## On-Site Stripe Payment

After `AddOrder` returns a WHMCS invoice ID, the website stores a local `customer_orders` mapping with the local customer, WHMCS client, WHMCS order, WHMCS invoice, amount due and currency. The customer is sent to `/checkout/payment/{token}` where Stripe Payment Element is rendered.

PaymentIntent creation is server-side only. The browser receives only the publishable key and PaymentIntent client secret.

Stripe webhook endpoint:

```text
https://planeticsolution.com/stripe/webhook
```

Enable these events in Stripe:

```text
payment_intent.succeeded
payment_intent.payment_failed
```

On `payment_intent.succeeded`, the webhook verifies the Stripe signature, checks the local order, validates amount/currency, then calls WHMCS `AddInvoicePayment` using `WHMCS_PAYMENT_GATEWAY_NAME`. Set this value to the exact WHMCS payment gateway system name you want to appear on invoice payments. Do not guess it in code.

After the WHMCS invoice is confirmed paid, the site marks the local payment record paid, accepts the WHMCS order, requests registrar submission for domains, requests hosting module creation for paid hosting services, creates or updates website project records, and clears customer account WHMCS cache.

On `payment_intent.payment_failed`, the local order is marked failed and the existing WHMCS invoice remains unpaid. Retry uses the same local invoice mapping.

## Setup Steps

1. Create WHMCS API credentials in WHMCS Admin > System Settings > API Credentials.
2. Add the credentials to `.env`.
3. If WHMCS API IP access control uses an access key, add it as `WHMCS_API_ACCESS_KEY`.
4. Confirm `WHMCS_PAYMENT_METHOD` matches the order payment method used for creating WHMCS invoices.
5. Set `WHMCS_PAYMENT_GATEWAY_NAME` to the exact WHMCS gateway system name used by `AddInvoicePayment`.
6. Add Stripe publishable, secret and webhook keys to `.env`.
7. Map all hosting and website product IDs.
8. Configure domain registrar/TLD pricing in WHMCS.
9. Upload the site to cPanel and keep `.env`, `app/`, `database/` and `storage/` protected by `.htaccess`.
10. Import `database/stripe_account_update.sql` if this is an existing installation.
11. Import `database/customer_order_checkout_metadata.sql` and `database/provisioning_and_website_projects.sql` for the local checkout/provisioning records.
12. In WHMCS, confirm each hosting product is assigned to the correct cPanel package: Starter `planetic_starter`, Business `planetic_business`, Pro `planetic_pro`, Agency/Ecommerce `planetic_agency`.
13. Confirm WHMCS domain registrar modules and TLD auto-registration settings are configured.
14. Test the flow with Stripe test mode and low-value WHMCS products first.

If domain search or checkout says WHMCS is not responding, check `storage/logs/whmcs-api.log`. The website calls WHMCS server-side using cURL first, then a PHP stream fallback. Most failures are caused by missing API credentials, WHMCS API IP restrictions, an incorrect `WHMCS_API_URL`, or cPanel outbound HTTPS/SSL issues.

If the log says `Invalid IP 185.61.154.29`, WHMCS is blocking the main website server. Add `185.61.154.29` to the allowed API IP list in WHMCS or configure a WHMCS API access key and set it as `WHMCS_API_ACCESS_KEY` in `.env`.

If the access key is configured but WHMCS still returns `Invalid IP`, use the local bridge:

```text
whmcs-bridge/planetic-local-api.php
```

Upload it to:

```text
public_html/clientarea/planetic-local-api.php
```

Edit the uploaded file and replace `change_this_long_random_token` with a long random token, then add this to the main website `.env`:

```env
WHMCS_LOCAL_API_BRIDGE_URL=https://planeticsolution.com/clientarea/planetic-local-api.php
WHMCS_LOCAL_API_BRIDGE_TOKEN=the_same_long_random_token
```

The main website will prefer the local bridge when both bridge variables are configured. This keeps API calls server-side and uses WHMCS `localAPI()` from inside the WHMCS installation.

If `storage/logs/whmcs-api.log` says `Bridge authentication failed`, the token in the main website `.env` does not match the token in the uploaded bridge file. The bridge can also read the token from WHMCS `configuration.php`:

```php
$planetic_bridge_token = 'the_same_long_random_token';
```

## WordPress Shortcodes

The optional file `wordpress/planetic-whmcs-shortcodes.php` provides:

```text
[planetic_domain_search]
[planetic_checkout_button type="bundle" plan="business-hosting" label="Get Started"]
[planetic_checkout_embed]
```

These shortcodes send visitors to the main website checkout. They do not store WHMCS credentials in WordPress.

## Testing Checklist

- Search `example.com` from the homepage and confirm it lands on `/domain-search`.
- Confirm `/api/domain-search?domain=example.com` returns JSON and no credentials.
- Click `Get domain` and confirm `/checkout?type=domain&domain=example.com`.
- Click `Get Complete Website Package` and confirm `/checkout?type=website&domain=example.com` when the domain is available.
- Submit a domain-only test order and confirm WHMCS creates a client, order and invoice, then the site shows `/checkout/payment/{token}`.
- Submit hosting-only with an existing domain.
- Submit domain + hosting.
- Submit the £199 website package.
- Pay with Stripe test card `4242 4242 4242 4242` and confirm the Stripe webhook records payment in WHMCS.
- Confirm the paid WHMCS order is accepted only after verified payment.
- Confirm paid domain orders appear in `/account/domains`.
- Confirm paid hosting orders appear in `/account/hosting` with the expected WHM package label.
- Confirm paid website package orders appear in `/account/website-development`.
- Update a website project in `/admin/website-projects` and confirm only the customer-facing note appears in the customer account.
- Test failed payment with Stripe test card `4000 0000 0000 9995` and confirm retry uses the same invoice.
- Log in at `/account/login`, then check `/account/dashboard`, `/account/domains`, `/account/hosting`, `/account/website-development`, `/account/billing` and `/account/profile`.
- Use Pay Now on an unpaid invoice from `/account/billing` and confirm it opens on-site Stripe payment.
- Confirm unpaid orders are not provisioned.
- Refresh `/checkout/success`, retry the webhook event, and confirm duplicate domain/hosting/project records are not created.
- Check `storage/logs/whmcs-api.log` for safe error messages if an API call fails.
