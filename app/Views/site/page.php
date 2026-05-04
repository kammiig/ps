<section class="page-hero compact legal-hero">
    <div class="container narrow">
        <h1><?= e($page['title']) ?></h1>
    </div>
</section>

<section class="section">
    <div class="container narrow content-body">
        <?= safe_html($page['body']) ?>
    </div>
</section>
