<article class="page-hero">
    <div class="container narrow">
        <span class="section-kicker"><?= e($post['category_name'] ?? 'Blog') ?></span>
        <h1><?= e($post['title']) ?></h1>
        <?php if (!empty($post['published_at'])): ?><p>Published <?= e(date('j F Y', strtotime($post['published_at']))) ?></p><?php endif; ?>
    </div>
</article>

<section class="section">
    <div class="container narrow content-body">
        <?php if (!empty($post['featured_image'])): ?><img class="featured-image" src="<?= e(upload_url($post['featured_image'])) ?>" alt="<?= e($post['featured_alt'] ?: $post['title']) ?>" loading="lazy"><?php endif; ?>
        <?= safe_html($post['content']) ?>
    </div>
</section>

<?php require APP_PATH . '/Views/partials/cta.php'; ?>
