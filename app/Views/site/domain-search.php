<section class="domain-results-page">
    <div class="container">
        <div class="domain-results-head">
            <span class="section-kicker">Domain search</span>
            <h1>Find and Register Your Domain</h1>
            <p>Check domain availability on the Planetic Solutions website, then continue securely to WHMCS checkout when you are ready.</p>
            <form class="domain-search domain-result-search" action="<?= e(url('/domain-search')) ?>" method="get" role="search">
                <label class="sr-only" for="domain-results-input">Search domain name</label>
                <input id="domain-results-input" name="domain" type="text" inputmode="url" autocomplete="off" placeholder="Find your perfect domain" value="<?= e($domain ?? '') ?>" required>
                <button class="btn btn-primary" type="submit">Search Domain <?= icon('arrow') ?></button>
            </form>
        </div>

        <div class="domain-results-shell" data-domain-results data-domain="<?= e($domain ?? '') ?>">
            <?php if (!empty($domain)): ?>
                <div class="domain-loading">
                    <span class="loader"></span>
                    <strong>Checking <?= e($domain) ?>...</strong>
                    <p>Availability and live WHMCS pricing are loading.</p>
                </div>
            <?php else: ?>
                <div class="domain-empty">
                    <h2>Search for a domain to get started</h2>
                    <p>Try a full domain such as <strong>example.com</strong>. Results will appear here.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
