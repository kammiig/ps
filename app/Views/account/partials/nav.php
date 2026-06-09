<aside class="account-sidebar">
    <div class="account-sidebar-card">
        <strong><?= e($customerUser['name'] ?? 'My Account') ?></strong>
        <span><?= e($customerUser['email'] ?? '') ?></span>
        <nav aria-label="Account navigation">
            <a class="<?= e(is_active('/account/dashboard')) ?>" href="<?= e(url('/account/dashboard')) ?>">Dashboard</a>
            <a class="<?= e(is_active('/account/domains')) ?>" href="<?= e(url('/account/domains')) ?>">Domains</a>
            <a class="<?= e(is_active('/account/hosting')) ?>" href="<?= e(url('/account/hosting')) ?>">Hosting</a>
            <a class="<?= e(is_active('/account/website-development')) ?>" href="<?= e(url('/account/website-development')) ?>">Website Development</a>
            <a class="<?= e(is_active('/account/billing')) ?>" href="<?= e(url('/account/billing')) ?>">Billing</a>
            <a class="<?= e(str_starts_with(current_path(), '/account/tickets') ? 'is-active' : '') ?>" href="<?= e(url('/account/tickets')) ?>">Support Tickets</a>
            <a class="<?= e(is_active('/account/profile')) ?>" href="<?= e(url('/account/profile')) ?>">Profile</a>
            <a href="<?= e(url('/account/logout')) ?>">Logout</a>
        </nav>
    </div>
</aside>
