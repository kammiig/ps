<?php
$orderType = $old['order_type'] ?? 'bundle';
$selectedPlan = $old['hosting_plan'] ?? ($plans[0]['slug'] ?? '');
$domain = $old['domain'] ?? '';
$countries = ['GB' => 'United Kingdom', 'US' => 'United States', 'PK' => 'Pakistan', 'IE' => 'Ireland', 'CA' => 'Canada', 'AU' => 'Australia'];
$websitePid = (int) (($checkoutConfig['website_package']['pid'] ?? 0));
?>
<section class="checkout-page">
    <div class="container">
        <div class="checkout-head">
            <span class="section-kicker">Secure website checkout</span>
            <h1>Order Domains, Hosting and Website Packages</h1>
            <p>Your order is placed on the Planetic Solutions website and securely sent to WHMCS for billing, invoices, provisioning and client records.</p>
        </div>

        <?php if ($errors): ?>
            <div class="notice error checkout-notice">
                <?php foreach ($errors as $error): ?><p><?= e($error) ?></p><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form class="checkout-layout" action="<?= e(url('/checkout')) ?>" method="post">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">

            <div class="checkout-main">
                <section class="checkout-panel">
                    <div class="section-head compact">
                        <div>
                            <span class="section-kicker">Step 1</span>
                            <h2>Choose what you need</h2>
                        </div>
                    </div>
                    <div class="checkout-choice-grid">
                        <?php foreach ([
                            'domain' => ['Domain only', 'Register a new domain name.'],
                            'hosting' => ['Hosting only', 'Use an existing domain with hosting.'],
                            'bundle' => ['Domain + hosting', 'Register a domain with a hosting package.'],
                            'website' => ['£200 website', 'Complete business website package.'],
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

                <section class="checkout-panel">
                    <div class="section-head compact">
                        <div>
                            <span class="section-kicker">Step 2</span>
                            <h2>Domain</h2>
                        </div>
                    </div>
                    <label>
                        <span>Domain name</span>
                        <input name="domain" type="text" inputmode="url" autocomplete="off" placeholder="example.com" value="<?= e($domain) ?>" required>
                    </label>
                    <p class="checkout-help">For domain-only, domain + hosting and website package orders, this domain will be checked again server-side before the WHMCS order is created.</p>
                </section>

                <section class="checkout-panel">
                    <div class="section-head compact">
                        <div>
                            <span class="section-kicker">Step 3</span>
                            <h2>Hosting package</h2>
                        </div>
                    </div>
                    <div class="checkout-plan-list">
                        <?php foreach ($plans as $plan): ?>
                            <label class="checkout-plan">
                                <input type="radio" name="hosting_plan" value="<?= e($plan['slug']) ?>" <?= $selectedPlan === $plan['slug'] ? 'checked' : '' ?>>
                                <span>
                                    <strong><?= e($plan['title']) ?></strong>
                                    <small><?= e(money($plan['monthly_price'])) ?>/month · PID <?= e($plan['checkout_pid'] ?: 'not set') ?></small>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="billing_cycle" value="<?= e($old['billing_cycle'] ?? '') ?>">
                </section>

                <section class="checkout-panel">
                    <div class="section-head compact">
                        <div>
                            <span class="section-kicker">Step 4</span>
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
                <div class="checkout-summary-card">
                    <span class="section-kicker">Order summary</span>
                    <h2>Main-site checkout</h2>
                    <ul class="feature-list">
                        <li><?= icon('check') ?> WHMCS client created or matched by email</li>
                        <li><?= icon('check') ?> WHMCS order and invoice created server-side</li>
                        <li><?= icon('check') ?> Prices and product IDs resolved server-side</li>
                        <li><?= icon('check') ?> Provisioning remains handled by WHMCS after payment</li>
                    </ul>
                    <?php if ($websitePid > 0): ?>
                        <p class="checkout-help">Website package product ID: <strong><?= e($websitePid) ?></strong></p>
                    <?php endif; ?>
                    <button class="btn btn-primary" type="submit">Create Order &amp; Pay Invoice <?= icon('arrow') ?></button>
                    <a class="btn btn-outline" href="<?= e($whmcs->clientAreaUrl()) ?>">Existing Client Login</a>
                </div>
            </aside>
        </form>
    </div>
</section>
