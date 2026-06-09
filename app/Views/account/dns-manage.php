<section class="account-page">
    <div class="container account-layout">
        <?php require APP_PATH . '/Views/account/partials/nav.php'; ?>
        <main class="account-main">
            <?php
            $domainName = strtolower((string) ($domainName ?? $domain['domainname'] ?? $domain['domain'] ?? 'Domain'));
            $recordAction = url('/account/domains/' . rawurlencode($domainName) . '/dns/records');
            $nameserverAction = url('/account/domains/' . rawurlencode($domainName) . '/dns/nameservers');
            $recordTypes = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'SRV', 'CAA'];
            ?>
            <div class="account-head">
                <span class="section-kicker">DNS management</span>
                <h1><?= e($domainName) ?></h1>
                <p>Manage DNS records when Cloudflare is connected, and update registrar nameservers through your Planetic account.</p>
            </div>

            <?php if (!empty($recordSaved)): ?>
                <div class="notice success" role="status">DNS record was saved successfully.</div>
            <?php endif; ?>
            <?php if (!empty($recordDeleted)): ?>
                <div class="notice success" role="status">DNS record was deleted successfully.</div>
            <?php endif; ?>
            <?php if (!empty($nameserversSaved) || !empty($saved)): ?>
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
                        <span class="section-kicker">DNS records</span>
                        <h2>Records</h2>
                    </div>
                    <div class="account-row-actions">
                        <span class="status-pill"><?= e($dnsProviderStatus ?? 'DNS records unavailable') ?></span>
                        <a class="btn btn-outline btn-small" href="<?= e(url('/account/dns')) ?>">Back to Domains</a>
                    </div>
                </div>

                <?php if (empty($dnsRecordsAvailable)): ?>
                    <div class="dns-help-card">
                        <strong>Record management is not connected</strong>
                        <p><?= e($dnsRecordsMessage ?? 'DNS records are not available yet. Please connect this domain to Planetic DNS or Cloudflare to manage records here.') ?></p>
                    </div>
                <?php else: ?>
                    <form method="post" action="<?= e($recordAction) ?>" class="dns-record-form">
                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                        <div class="dns-record-grid">
                            <label class="field">
                                <span>Type</span>
                                <select name="type">
                                    <?php foreach ($recordTypes as $type): ?>
                                        <option value="<?= e($type) ?>"><?= e($type) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label class="field">
                                <span>Name</span>
                                <input type="text" name="name" value="@" placeholder="@ or www">
                            </label>
                            <label class="field wide">
                                <span>Content / target</span>
                                <textarea name="content" rows="3" placeholder="IP address, hostname, TXT value, mail server, or CAA value"></textarea>
                            </label>
                            <label class="field">
                                <span>Priority</span>
                                <input type="number" name="priority" value="10" min="0" max="65535">
                            </label>
                            <label class="field">
                                <span>TTL</span>
                                <input type="number" name="ttl" value="3600" min="60" max="86400">
                            </label>
                            <label class="field">
                                <span>SRV service</span>
                                <input type="text" name="service" placeholder="_sip">
                            </label>
                            <label class="field">
                                <span>SRV protocol</span>
                                <input type="text" name="proto" placeholder="_tcp">
                            </label>
                            <label class="field">
                                <span>SRV port</span>
                                <input type="number" name="port" min="1" max="65535">
                            </label>
                            <label class="field">
                                <span>CAA tag</span>
                                <select name="tag">
                                    <option value="issue">issue</option>
                                    <option value="issuewild">issuewild</option>
                                    <option value="iodef">iodef</option>
                                </select>
                            </label>
                            <label class="switch-row wide">
                                <input type="checkbox" name="proxied" value="1">
                                <span>Proxy eligible A, AAAA, or CNAME records through Cloudflare</span>
                            </label>
                        </div>
                        <button class="btn btn-primary" type="submit">Add Record <?= icon('arrow') ?></button>
                    </form>

                    <?php if (!empty($dnsRecords)): ?>
                        <div class="dns-record-list">
                            <?php foreach ($dnsRecords as $record): ?>
                                <?php
                                $recordId = (string) ($record['id'] ?? '');
                                $recordData = is_array($record['data'] ?? null) ? $record['data'] : [];
                                ?>
                                <article class="dns-record-card">
                                    <form method="post" action="<?= e($recordAction) ?>" class="dns-record-form compact">
                                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="record_id" value="<?= e($recordId) ?>">
                                        <input type="hidden" name="target" value="<?= e($recordData['target'] ?? '') ?>">
                                        <input type="hidden" name="service" value="<?= e($recordData['service'] ?? '') ?>">
                                        <input type="hidden" name="proto" value="<?= e($recordData['proto'] ?? '') ?>">
                                        <input type="hidden" name="weight" value="<?= e((string) ($recordData['weight'] ?? '')) ?>">
                                        <input type="hidden" name="port" value="<?= e((string) ($recordData['port'] ?? '')) ?>">
                                        <input type="hidden" name="flag" value="<?= e((string) ($recordData['flags'] ?? '')) ?>">
                                        <input type="hidden" name="tag" value="<?= e((string) ($recordData['tag'] ?? 'issue')) ?>">
                                        <div class="dns-record-grid compact">
                                            <label class="field">
                                                <span>Type</span>
                                                <select name="type">
                                                    <?php foreach ($recordTypes as $type): ?>
                                                        <option value="<?= e($type) ?>" <?= ($record['type'] ?? '') === $type ? 'selected' : '' ?>><?= e($type) ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </label>
                                            <label class="field">
                                                <span>Name</span>
                                                <input type="text" name="name" value="<?= e($record['name'] ?? '') ?>">
                                            </label>
                                            <label class="field wide">
                                                <span>Content</span>
                                                <textarea name="content" rows="2"><?= e($record['content'] ?? '') ?></textarea>
                                            </label>
                                            <label class="field">
                                                <span>Priority</span>
                                                <input type="number" name="priority" value="<?= e((string) ($record['priority'] ?? $recordData['priority'] ?? 10)) ?>" min="0" max="65535">
                                            </label>
                                            <label class="field">
                                                <span>TTL</span>
                                                <input type="number" name="ttl" value="<?= e((string) ($record['ttl'] ?? 3600)) ?>" min="1" max="86400">
                                            </label>
                                            <label class="switch-row wide">
                                                <input type="checkbox" name="proxied" value="1" <?= !empty($record['proxied']) ? 'checked' : '' ?>>
                                                <span>Cloudflare proxy</span>
                                            </label>
                                        </div>
                                        <div class="account-row-actions">
                                            <button class="btn btn-outline btn-small" type="submit">Update</button>
                                        </div>
                                    </form>
                                    <form method="post" action="<?= e(url('/account/domains/' . rawurlencode($domainName) . '/dns/records/' . rawurlencode($recordId) . '/delete')) ?>" onsubmit="return confirm('Delete this DNS record?');">
                                        <input type="hidden" name="_csrf" value="<?= e($csrfToken) ?>">
                                        <button class="btn btn-danger btn-small" type="submit">Delete</button>
                                    </form>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="dns-help-card">
                            <strong>No DNS records found</strong>
                            <p>Add the first record above when you are ready.</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </section>

            <section class="account-card">
                <div class="section-head compact">
                    <div>
                        <span class="section-kicker">Nameservers</span>
                        <h2>Registrar nameservers</h2>
                    </div>
                </div>

                <form method="post" action="<?= e($nameserverAction) ?>" class="dns-form">
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
