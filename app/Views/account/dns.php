<section class="account-page">
    <div class="container account-layout">
        <?php require APP_PATH . '/Views/account/partials/nav.php'; ?>
        <main class="account-main">
            <div class="account-head">
                <span class="section-kicker">DNS management</span>
                <h1>Manage domain DNS</h1>
                <p>Manage nameservers for domains linked to your Planetic Solutions account without leaving the website.</p>
            </div>

            <section class="account-card dns-intro-card">
                <div>
                    <h2>Nameserver tools</h2>
                    <p>Choose a domain below to update the nameservers used by your hosting, website or DNS provider.</p>
                </div>
                <a class="btn btn-outline" href="<?= e(url('/contact')) ?>">Need Help?</a>
            </section>

            <section class="account-card">
                <div class="section-head compact">
                    <div>
                        <span class="section-kicker">Your domains</span>
                        <h2>DNS-ready domains</h2>
                    </div>
                </div>

                <?php if ($domains): ?>
                    <div class="account-list">
                        <?php foreach ($domains as $domain): ?>
                            <?php
                            $domainName = (string) ($domain['domainname'] ?? $domain['domain'] ?? 'Domain');
                            $renewal = (string) ($domain['nextduedate'] ?? 'Not available');
                            $status = (string) ($domain['status'] ?? 'Processing');
                            $dnsUrl = (string) ($domain['dns_url'] ?? url('/account/dns'));
                            ?>
                            <div class="account-row">
                                <div>
                                    <strong><?= e($domainName) ?></strong>
                                    <span>Renewal: <?= e($renewal) ?></span>
                                </div>
                                <div class="account-row-actions">
                                    <span class="status-pill"><?= e($status) ?></span>
                                    <a class="btn btn-outline btn-small" href="<?= e($dnsUrl) ?>">Manage DNS</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <p>No domain records are available yet. Registered domains will appear here once they are linked to your account.</p>
                <?php endif; ?>
            </section>

            <section class="account-card dns-support-card">
                <h2>Need help with DNS?</h2>
                <p>Contact support if you want us to connect your domain to hosting, email, Cloudflare, or a new website launch.</p>
                <a class="btn btn-outline" href="<?= e(url('/contact')) ?>">Contact Support</a>
            </section>
        </main>
    </div>
</section>
