<section class="page-hero">
    <div class="container page-hero-grid">
        <div>
            <span class="section-kicker">Blog</span>
            <h1>Hosting, Domains and Website Growth Guides</h1>
            <p>SEO-ready posts you can manage from the admin dashboard with slugs, metadata, categories and featured images.</p>
        </div>
        <aside class="page-hero-card">
            <span class="mini-label">CMS ready</span>
            <strong>SEO-friendly articles</strong>
            <p>Publish hosting guides, domain tips and website advice with editable metadata.</p>
        </aside>
    </div>
</section>

<section class="section">
    <div class="container blog-grid">
        <?php foreach ($posts as $post): ?>
            <article class="blog-card">
                <?php if (!empty($post['featured_image'])): ?><img src="<?= e(upload_url($post['featured_image'])) ?>" alt="<?= e($post['featured_alt'] ?: $post['title']) ?>" loading="lazy"><?php endif; ?>
                <span><?= e($post['category_name'] ?? 'Hosting') ?></span>
                <h2><a href="<?= e(url('/blog/' . $post['slug'])) ?>"><?= e($post['title']) ?></a></h2>
                <p><?= e($post['meta_description'] ?: excerpt($post['content'])) ?></p>
                <a class="text-link" href="<?= e(url('/blog/' . $post['slug'])) ?>">Read article <?= icon('arrow') ?></a>
            </article>
        <?php endforeach; ?>
    </div>
</section>
