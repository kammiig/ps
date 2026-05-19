# Main-Site WHMCS Checkout Flow

This project keeps the buying experience on `https://planeticsolution.com/` while WHMCS remains the backend for clients, orders, invoices, domains, hosting provisioning, renewals and service management.

## File Structure

```text
app/config/whmcs.php                  Server-side WHMCS/product/domain config
app/Services/WhmcsService.php         WHMCS API wrapper
app/Controllers/SiteController.php    Domain API, checkout page and order submit endpoint
app/Views/site/domain-search.php      Domain results page
app/Views/site/checkout.php           Main-site checkout form
assets/js/app.js                      Domain result rendering
assets/css/style.css                  Checkout/domain results styling
wordpress/planetic-whmcs-shortcodes.php Optional WordPress bridge shortcodes
storage/logs/whmcs-api.log            Safe WHMCS API error log
```

## URLs

```text
GET  /domain-search?domain=example.com
GET  /api/domain-search?domain=example.com
GET  /checkout
POST /checkout
```

## WHMCS API Actions Used

- `DomainWhois` checks domain availability.
- `GetTLDPricing` fetches live domain prices.
- `GetClientsDetails` finds an existing client by email.
- `AddClient` creates a new WHMCS client when no matching email exists.
- `AddOrder` creates the WHMCS order and invoice.
- `AcceptOrder` and `CreateSsoToken` wrappers exist in the service, but checkout does not automatically call them. Accept orders only after payment or an approved operational flow.

## Product ID Mapping

Set product IDs in `.env`:

```env
WHMCS_API_URL=https://planeticsolution.com/clientarea/includes/api.php
WHMCS_API_IDENTIFIER=your_identifier
WHMCS_API_SECRET=your_secret
WHMCS_API_ACCESS_KEY=
WHMCS_API_SSL_VERIFY=true
WHMCS_PAYMENT_METHOD=stripe
WHMCS_STARTER_HOSTING_PID=1
WHMCS_BUSINESS_HOSTING_PID=2
WHMCS_WORDPRESS_HOSTING_PID=3
WHMCS_RESELLER_HOSTING_PID=4
WHMCS_WEBSITE_PACKAGE_PID=5
WHMCS_WEBSITE_REGISTER_DOMAIN=true
WHMCS_WEBSITE_DOMAIN_PRICE_OVERRIDE=0.00
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

Checkout does not trust frontend prices. WHMCS creates the actual invoice and pricing, except when the website package uses the server-side `domain_price_override` setting for a free first-year domain.

## Setup Steps

1. Create WHMCS API credentials in WHMCS Admin > System Settings > API Credentials.
2. Add the credentials to `.env`.
3. If WHMCS API IP access control uses an access key, add it as `WHMCS_API_ACCESS_KEY`.
4. Confirm `WHMCS_PAYMENT_METHOD` matches a payment gateway system name enabled in WHMCS.
5. Map all hosting and website product IDs.
6. Configure domain registrar/TLD pricing in WHMCS.
7. Upload the site to cPanel and keep `.env`, `app/`, `database/` and `storage/` protected by `.htaccess`.
8. Test the flow with a low-value test product or sandbox payment gateway first.

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
- Click `Get domain + hosting` and confirm `/checkout?type=bundle&domain=example.com`.
- Submit a domain-only test order and confirm WHMCS creates a client, order and invoice.
- Submit hosting-only with an existing domain.
- Submit domain + hosting.
- Submit the £200 website package.
- Confirm unpaid orders are not provisioned until WHMCS payment/approval automation handles them.
- Check `storage/logs/whmcs-api.log` for safe error messages if an API call fails.
