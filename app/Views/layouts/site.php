<?php
$siteName = $settings['company_name'] ?? 'Planetic Solutions';
$siteUrl = rtrim((string) ($settings['app_url'] ?? env('APP_URL', '')), '/');
$canonical = $meta['canonical_url'] ?: $siteUrl . current_path();
$logo = upload_url($settings['logo_url'] ?? '');
$favicon = upload_url($settings['favicon_url'] ?? '');
$organizationSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'Organization',
    'name' => $siteName,
    'url' => $siteUrl,
    'logo' => $logo ?: null,
    'email' => $settings['admin_email'] ?? null,
    'telephone' => $settings['phone'] ?? null,
    'sameAs' => array_values(array_filter([
        $settings['facebook_url'] ?? '',
        $settings['instagram_url'] ?? '',
        $settings['linkedin_url'] ?? '',
        $settings['x_url'] ?? '',
    ])),
];
$localSchema = [
    '@context' => 'https://schema.org',
    '@type' => 'ProfessionalService',
    'name' => $siteName,
    'url' => $siteUrl,
    'description' => $meta['description'],
    'address' => $settings['address'] ?? '',
    'telephone' => $settings['phone'] ?? '',
    'email' => $settings['admin_email'] ?? '',
    'priceRange' => '££',
];
$allSchemas = array_values(array_filter([$organizationSchema, $localSchema, ...($schemas ?? [])]));
?>
<!doctype html>
<html lang="en-GB">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($meta['title']) ?></title>
    <meta name="description" content="<?= e($meta['description']) ?>">
    <?php if (!empty($meta['keywords'])): ?><meta name="keywords" content="<?= e($meta['keywords']) ?>"><?php endif; ?>
    <link rel="canonical" href="<?= e($canonical) ?>">
    <?php if ($favicon): ?><link rel="icon" href="<?= e($favicon) ?>"><?php endif; ?>
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="<?= e($siteName) ?>">
    <meta property="og:title" content="<?= e($meta['og_title'] ?: $meta['title']) ?>">
    <meta property="og:description" content="<?= e($meta['description']) ?>">
    <meta property="og:url" content="<?= e($canonical) ?>">
    <?php if (!empty($meta['og_image'])): ?><meta property="og:image" content="<?= e($meta['og_image']) ?>"><?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="<?= e($meta['og_title'] ?: $meta['title']) ?>">
    <meta name="twitter:description" content="<?= e($meta['description']) ?>">
    <?php if (!empty($meta['og_image'])): ?><meta name="twitter:image" content="<?= e($meta['og_image']) ?>"><?php endif; ?>
    <link rel="preload" href="<?= e(asset('css/style.css')) ?>?v=20260515-domain-search" as="style">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>?v=20260515-domain-search">
    <?php foreach ($allSchemas as $schema): ?>
        <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
    <?php endforeach; ?>
    <?= $settings['google_analytics'] ?? '' ?>
</head>
<body>
<?php require APP_PATH . '/Views/partials/header.php'; ?>
<main id="main-content">
    <?= $content ?>
</main>
<?php require APP_PATH . '/Views/partials/footer.php'; ?>
<script src="<?= e(asset('js/app.js')) ?>?v=20260515-domain-search" defer></script>
<?php if (($settings['recaptcha_enabled'] ?? '0') === '1' && !empty($settings['recaptcha_site_key'])): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>
</body>
</html>
