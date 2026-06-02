<section class="account-page">
    <div class="container account-layout">
        <?php require APP_PATH . '/Views/account/partials/nav.php'; ?>
        <main class="account-main">
            <?php
            $domainName = (string) ($domain['domainname'] ?? $domain['domain'] ?? 'Domain');
            ?>
            <div class="account-head">
                <span class="section-kicker">DNS management</span>
                <h1><?= e($domainName) ?></h1>
                <p>Update nameservers for this domain. Nameserver changes can take time to propagate across DNS networks.</p>
            </div>

            <?php if (!empty($saved)): ?>
                <div class="notice success" role="status">Nameservers were updated successfully.</div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <div class="notice error" role="alert">
                    <?php foreach ($errors as $error): ?>
                        <p><?= e($error) ?></p>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <section class="account-card">
                <div class="section-head compact">
                    <div>
                        <span class="section-kicker">Nameservers</span>
                        <h2>Domain DNS settings</h2>
                    </div>
                    <a class="btn btn-outline btn-small" href="<?= e(url('/account/dns')) ?>">Back to Domains</a>
                </div>

                <form method="post" action="<?= e(url('/account/dns/' . (int) ($domain['id'] ?? $domain['domainid'] ?? 0))) ?>" class="dns-form">
                    <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                    <div class="dns-nameserver-grid">
                        <?php for ($index = 1; $index <= 5; $index++): ?>
                            <label class="field">
                                <span>Nameserver <?= e((string) $index) ?><?= $index <= 2 ? ' *' : '' ?></span>
                                <input
                                    type="text"
                                    name="ns<?= e((string) $index) ?>"
                                    value="<?= e((string) ($nameservers[$index] ?? '')) ?>"
                                    placeholder="ns<?= e((string) $index) ?>.example.com"
                                    autocomplete="off"
                                    <?= $index <= 2 ? 'required' : '' ?>
                                >
                            </label>
                        <?php endfor; ?>
                    </div>

                    <div class="dns-help-card">
                        <strong>Before you save</strong>
                        <p>Use nameservers supplied by your hosting provider, Cloudflare or DNS provider. Incorrect nameservers can take your website or email offline.</p>
                    </div>

                    <button class="btn btn-primary" type="submit">Update Nameservers <?= icon('arrow') ?></button>
                </form>
            </section>
        </main>
    </div>
</section>
