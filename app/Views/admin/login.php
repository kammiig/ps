<main class="login-card">
    <div class="brand login-brand">
        <span class="brand-mark">PS</span>
        <span><?= e($settings['company_name'] ?? 'Planetic Solutions') ?></span>
    </div>
    <h1>Admin Login</h1>
    <p>Manage website content, plans, WHMCS links, SEO, blog posts and inquiries.</p>
    <?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php endif; ?>
    <form action="<?= e(url('/admin/login')) ?>" method="post">
        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
        <label>Email<input type="email" name="email" autocomplete="email" required></label>
        <label>Password<input type="password" name="password" autocomplete="current-password" required></label>
        <button class="btn btn-primary" type="submit">Login</button>
    </form>
</main>
