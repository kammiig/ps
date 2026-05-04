<section class="page-hero">
    <div class="container narrow">
        <span class="section-kicker">Contact</span>
        <h1><?= e($page['title'] ?? 'Tell Us What You Need') ?></h1>
        <?php if (!empty($page['body'])): ?>
            <div class="content-body"><?= safe_html($page['body']) ?></div>
        <?php else: ?>
            <p>Ask about hosting, reseller hosting, domain registration, Cloudflare setup or the £200 website package.</p>
        <?php endif; ?>
    </div>
</section>

<section class="section">
    <div class="container contact-grid">
        <form class="contact-form" action="<?= e(url('/contact')) ?>" method="post">
            <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
            <?php if ($sent): ?><div class="notice success">Thank you. Your inquiry has been saved and sent to the team.</div><?php endif; ?>
            <?php if ($errors): ?><div class="notice error"><?= e(implode(' ', $errors)) ?></div><?php endif; ?>
            <label>Full Name<input name="full_name" type="text" value="<?= e($old['full_name'] ?? '') ?>" required></label>
            <label>Email<input name="email" type="email" value="<?= e($old['email'] ?? '') ?>" required></label>
            <label>Phone<input name="phone" type="tel" value="<?= e($old['phone'] ?? '') ?>"></label>
            <label>Service Interested In
                <select name="service" required>
                    <?php foreach (['WordPress Hosting', 'cPanel Hosting', 'Reseller Hosting', 'Domain Registration', 'Website Development £200', 'Cloudflare CDN Setup'] as $service): ?>
                        <option value="<?= e($service) ?>" <?= (($old['service'] ?? '') === $service) ? 'selected' : '' ?>><?= e($service) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>Message<textarea name="message" rows="7" required><?= e($old['message'] ?? '') ?></textarea></label>
            <?php if (($settings['recaptcha_enabled'] ?? '0') === '1' && !empty($settings['recaptcha_site_key'])): ?>
                <div class="g-recaptcha" data-sitekey="<?= e($settings['recaptcha_site_key']) ?>"></div>
            <?php endif; ?>
            <button class="btn btn-primary" type="submit">Send Inquiry <?= icon('arrow') ?></button>
        </form>
        <aside class="contact-panel">
            <h2>Support and sales</h2>
            <p>Hosting orders, domain registration and billing are completed through the WHMCS client area. Website package inquiries can also be handled here.</p>
            <?php if (!empty($settings['admin_email'])): ?><a href="mailto:<?= e($settings['admin_email']) ?>"><?= icon('mail') ?> <?= e($settings['admin_email']) ?></a><?php endif; ?>
            <?php if (!empty($settings['phone'])): ?><a href="tel:<?= e(preg_replace('/\s+/', '', $settings['phone'])) ?>"><?= icon('phone') ?> <?= e($settings['phone']) ?></a><?php endif; ?>
            <a href="<?= e($whmcs->clientAreaUrl()) ?>"><?= icon('panel') ?> WHMCS Client Area</a>
        </aside>
    </div>
</section>
