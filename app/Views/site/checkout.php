<?php
$orderType = $old['order_type'] ?? 'bundle';
$selectedPlan = $old['hosting_plan'] ?? ($plans[0]['slug'] ?? '');
$domain = $old['domain'] ?? '';
$billingCycle = strtolower((string) ($old['billing_cycle'] ?? $checkoutConfig['default_billing_cycle'] ?? 'monthly'));
$billingCycle = in_array($billingCycle, ['annually', 'annual', 'yearly', 'year'], true) ? 'annually' : 'monthly';
$usesHosting = in_array($orderType, ['hosting', 'bundle'], true);
$isHostingOnly = $orderType === 'hosting';
$isDomainOnly = $orderType === 'domain';
$domainTitle = $isHostingOnly ? 'Existing domain' : 'Domain';
$domainLabel = $isHostingOnly ? 'Existing domain name (optional)' : 'Domain name';
$domainHelp = $isHostingOnly
    ? 'Optional. Add the domain you want this hosting account linked to, or leave blank and provide it later.'
    : ($isDomainOnly
        ? 'This domain will be checked again before your secure payment is created.'
        : ($orderType === 'website'
            ? 'Enter the domain you want for the website package. Domain registration is included for the first year where available.'
            : 'This domain will be checked again before your secure payment is created.'));
$countries = ['GB' => 'United Kingdom', 'US' => 'United States', 'PK' => 'Pakistan', 'IE' => 'Ireland', 'CA' => 'Canada', 'AU' => 'Australia'];
$websiteConfig = $checkoutConfig['website_package'] ?? [];
$websitePriceRaw = (string) ($websiteConfig['price_override'] ?? $package['price'] ?? '199.00');
$websitePriceNumber = preg_replace('/[^0-9.]/', '', $websitePriceRaw) ?: '199.00';
$websitePackageSummary = [
    'title' => (string) (($package['title'] ?? '') ?: 'Bespoke Website Development'),
    'price' => number_format((float) $websitePriceNumber, 2, '.', ''),
    'description' => (string) (($package['description'] ?? '') ?: 'Launch a professional business website with domain, hosting setup, Elementor, premium Envato elements, stock photos, content writing and Cloudflare integration included.'),
];
$domainPricingJson = e(json_encode($checkoutConfig['domain_pricing'] ?? [], JSON_UNESCAPED_SLASHES));
$websitePackageJson = e(json_encode($websitePackageSummary, JSON_UNESCAPED_SLASHES));
?>
<section class="checkout-page">
    <div class="container">
        <div class="checkout-head">
            <span class="section-kicker">Secure website checkout</span>
            <h1>Order Domains, Hosting and Website Packages</h1>
            <p>Choose your domain, hosting or complete website package, review your live order summary and continue to secure payment.</p>
        </div>

        <?php if ($errors): ?>
            <div class="notice error checkout-notice" role="alert">
                <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form class="checkout-layout" action="<?= e(url('/checkout')) ?>" method="post" data-checkout-form data-domain-pricing="<?= $domainPricingJson ?>" data-website-package="<?= $websitePackageJson ?>">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

            <div class="checkout-main">
                <section class="checkout-panel" data-checkout-section="choice">
                    <div class="section-head compact">
                        <div>
                            <span class="section-kicker" data-checkout-step>Step 1</span>
                            <h2>Choose what you need</h2>
                        </div>
                    </div>
                    <div class="checkout-choice-grid">
                        <?php foreach ([
                            'domain' => ['Domain only', 'Register a new domain name.'],
                            'hosting' => ['Hosting only', 'Use an existing domain with hosting.'],
                            'bundle' => ['Domain + hosting', 'Register a domain with a hosting package.'],
                            'website' => ['Bespoke Website Development', '£199 one-time package with first-year domain and hosting included.'],
                        ] as $value => [$title, $text]): ?>
                            <label class="checkout-choice">
                                <input type="radio" name="order_type" value="<?= e($value) ?>" <?= $orderType === $value ? 'checked' : '' ?>>
                                <span>
                                    <strong><?= e($title) ?></strong>
                                    <small><?= e($text) ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="checkout-panel" data-checkout-section="domain">
                    <div class="section-head compact">
                        <div>
                            <span class="section-kicker" data-checkout-step>Step 2</span>
                            <h2 data-domain-title><?= e($domainTitle) ?></h2>
                        </div>
                    </div>
                    <label>
                        <span data-domain-label><?= e($domainLabel) ?></span>
                        <input name="domain" type="text" inputmode="url" autocomplete="off" placeholder="<?= $isHostingOnly ? 'your-existing-domain.com' : 'example.com' ?>" value="<?= e($domain) ?>" aria-describedby="checkout-domain-help" <?= $isHostingOnly ? '' : 'required' ?>>
                    </label>
                    <p id="checkout-domain-help" class="checkout-help" data-domain-help><?= e($domainHelp) ?></p>
                    <div class="domain-benefits-card" data-domain-benefits <?= $isHostingOnly ? 'hidden' : '' ?>>
                        <h3>Great to know</h3>
                        <ul>
                            <li><?= icon('check') ?> Free WHOIS privacy protection where available</li>
                            <li><?= icon('check') ?> 1 year domain registration</li>
                            <li><?= icon('check') ?> 24/7 customer support</li>
                            <li><?= icon('check') ?> ICANN fee included in price where applicable</li>
                            <li><?= icon('check') ?> Secure SSL encrypted payment</li>
                        </ul>
                        <p>You’re getting your own custom domain, a great way to brand your website or business.</p>
                        <button class="btn btn-primary" type="button" data-checkout-continue>Continue</button>
                    </div>
                </section>

                <section class="checkout-panel" data-checkout-section="hosting" <?= $usesHosting ? '' : 'hidden' ?>>
                    <div class="section-head compact">
                        <div>
                            <span class="section-kicker" data-checkout-step>Step 3</span>
                            <h2>Hosting package</h2>
                        </div>
                    </div>
                    <div class="billing-cycle-toggle" data-billing-toggle>
                        <label>
                            <input type="radio" name="billing_cycle" value="monthly" <?= $billingCycle === 'monthly' ? 'checked' : '' ?>>
                            <span>Monthly</span>
                        </label>
                        <label>
                            <input type="radio" name="billing_cycle" value="annually" <?= $billingCycle === 'annually' ? 'checked' : '' ?>>
                            <span>Yearly</span>
                        </label>
                    </div>
                    <div class="checkout-plan-list">
                        <?php foreach ($plans as $plan): ?>
                            <?php $yearlyPrice = trim((string) ($plan['yearly_price'] ?? '')); ?>
                            <label class="checkout-plan" data-plan-title="<?= e($plan['title']) ?>" data-plan-monthly="<?= e(preg_replace('/[^0-9.]/', '', (string) $plan['monthly_price'])) ?>" data-plan-yearly="<?= e(preg_replace('/[^0-9.]/', '', (string) ($plan['yearly_price'] ?? ''))) ?>">
                                <input type="radio" name="hosting_plan" value="<?= e($plan['slug']) ?>" <?= $selectedPlan === $plan['slug'] ? 'checked' : '' ?>>
                                <span>
                                    <strong><?= e($plan['title']) ?></strong>
                                    <small>
                                        <span class="plan-price-monthly"><?= e(money($plan['monthly_price'])) ?>/month</span>
                                        <span class="plan-price-yearly"><?= $yearlyPrice !== '' ? e(money($yearlyPrice)) . '/year' : 'Yearly price not set' ?></span>
                                    </small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>

                <section class="checkout-panel" data-checkout-section="details">
                    <div class="section-head compact">
                        <div>
                            <span class="section-kicker" data-checkout-step>Step 4</span>
                            <h2>Customer details</h2>
                        </div>
                    </div>
                    <div class="checkout-form-grid">
                        <label><span>First name</span><input name="first_name" value="<?= e($old['first_name'] ?? '') ?>" autocomplete="given-name" required></label>
                        <label><span>Last name</span><input name="last_name" value="<?= e($old['last_name'] ?? '') ?>" autocomplete="family-name" required></label>
                        <label><span>Email</span><input name="email" type="email" value="<?= e($old['email'] ?? '') ?>" autocomplete="email" required></label>
                        <label><span>Phone</span><input name="phone" value="<?= e($old['phone'] ?? '') ?>" autocomplete="tel" required></label>
                        <label class="wide"><span>Address</span><input name="address" value="<?= e($old['address'] ?? '') ?>" autocomplete="address-line1" required></label>
                        <label><span>City</span><input name="city" value="<?= e($old['city'] ?? '') ?>" autocomplete="address-level2" required></label>
                        <label><span>State / county</span><input name="state" value="<?= e($old['state'] ?? '') ?>" autocomplete="address-level1" required></label>
                        <label><span>Postcode</span><input name="postcode" value="<?= e($old['postcode'] ?? '') ?>" autocomplete="postal-code" required></label>
                        <label>
                            <span>Country</span>
                            <select name="country" autocomplete="country" required>
                                <?php foreach ($countries as $code => $name): ?>
                                    <option value="<?= e($code) ?>" <?= (($old['country'] ?? 'GB') === $code) ? 'selected' : '' ?>><?= e($name) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="wide"><span>Password</span><input name="password" type="password" autocomplete="new-password" minlength="8" required></label>
                    </div>
                </section>
            </div>

            <aside class="checkout-summary">
                <div class="checkout-summary-card" data-order-summary aria-live="polite" aria-atomic="true">
                    <span class="section-kicker">Order summary</span>
                    <h2>Order Summary</h2>
                    <div class="order-summary-lines" data-summary-lines></div>
                    <div class="order-summary-totals">
                        <div><span>Subtotal</span><strong data-summary-subtotal>£0.00</strong></div>
                        <div><span>Today’s Payment</span><strong data-summary-today>£0.00</strong></div>
                        <div><span>Monthly Recurring</span><strong data-summary-monthly>£0.00/month</strong></div>
                        <div><span>Yearly Hosting Renewal</span><strong data-summary-yearly-hosting>£0.00/year</strong></div>
                        <div><span>Yearly Domain Renewal</span><strong data-summary-yearly>£0.00/year</strong></div>
                    </div>
                    <p class="checkout-help">Secure SSL encrypted payment. Your invoice and service management remain available in your client area.</p>
                    <button class="btn btn-primary" type="submit">Continue to Payment <?= icon('arrow') ?></button>
                    <a class="btn btn-outline" href="<?= e($whmcs->clientAreaUrl()) ?>">Existing Client Login</a>
                </div>
            </aside>
        </form>
    </div>
</section>
