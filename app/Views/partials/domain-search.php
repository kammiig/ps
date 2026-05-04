<form class="domain-search" action="<?= e(url('/domain-search')) ?>" method="post" role="search">
    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
    <label class="sr-only" for="<?= e($id ?? 'domain') ?>">Search domain name</label>
    <input id="<?= e($id ?? 'domain') ?>" name="domain" type="text" inputmode="url" autocomplete="off" placeholder="Find your perfect domain" required>
    <button class="btn btn-primary" type="submit">Search Domain <?= icon('arrow') ?></button>
</form>
