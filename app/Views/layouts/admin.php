<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? 'Admin') ?> | <?= e($settings['company_name'] ?? 'Planetic Solutions') ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="admin-body">
    <aside class="admin-sidebar">
        <a class="brand admin-brand" href="<?= e(url('/admin')) ?>"><span class="brand-mark">PS</span><span>Admin</span></a>
        <nav>
            <a href="<?= e(url('/admin')) ?>">Dashboard</a>
            <a href="<?= e(url('/admin/homepage')) ?>">Homepage</a>
            <a href="<?= e(url('/admin/plans')) ?>">Hosting Plans</a>
            <a href="<?= e(url('/admin/package')) ?>">Website Package</a>
            <a href="<?= e(url('/admin/tlds')) ?>">Domains/TLDs</a>
            <a href="<?= e(url('/admin/pages')) ?>">Pages</a>
            <a href="<?= e(url('/admin/blog')) ?>">Blog</a>
            <a href="<?= e(url('/admin/testimonials')) ?>">Testimonials</a>
            <a href="<?= e(url('/admin/faqs')) ?>">FAQs</a>
            <a href="<?= e(url('/admin/inquiries')) ?>">Inquiries</a>
            <a href="<?= e(url('/admin/seo')) ?>">SEO</a>
            <a href="<?= e(url('/admin/settings')) ?>">Settings</a>
            <a href="<?= e(url('/admin/export')) ?>">Export Backup</a>
        </nav>
    </aside>
    <div class="admin-shell">
        <header class="admin-topbar">
            <div>
                <strong><?= e($settings['company_name'] ?? 'Planetic Solutions') ?></strong>
                <span><?= e($adminUser['email'] ?? '') ?></span>
            </div>
            <div class="admin-topbar-actions">
                <a class="btn btn-light" href="<?= e(url('/')) ?>" target="_blank" rel="noopener">View Site</a>
                <a class="btn btn-outline" href="<?= e(url('/admin/logout')) ?>">Logout</a>
            </div>
        </header>
        <main class="admin-main">
            <?php if (!empty($flash)): ?>
                <div class="flash <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endif; ?>
            <?= $content ?>
        </main>
    </div>
</body>
</html>
