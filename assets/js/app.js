document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('[data-header]');
    const nav = document.querySelector('[data-nav]');
    const toggle = document.querySelector('[data-nav-toggle]');
    const actions = document.querySelector('.nav-actions');

    if (toggle && nav) {
        toggle.addEventListener('click', () => {
            const open = toggle.getAttribute('aria-expanded') === 'true';
            toggle.setAttribute('aria-expanded', String(!open));
            nav.classList.toggle('is-open', !open);
            actions?.classList.toggle('is-open', !open);
        });
    }

    if (header) {
        const setScrolled = () => header.classList.toggle('is-scrolled', window.scrollY > 8);
        setScrolled();
        window.addEventListener('scroll', setScrolled, { passive: true });
    }

    const domainResults = document.querySelector('[data-domain-results]');
    if (domainResults) {
        const domain = domainResults.getAttribute('data-domain') || '';
        if (domain) {
            loadDomainResults(domainResults, domain);
        }
    }
});

async function loadDomainResults(root, domain) {
    try {
        const response = await fetch(`/api/domain-search?domain=${encodeURIComponent(domain)}`, {
            headers: { Accept: 'application/json' },
        });
        const data = await response.json();

        if (!response.ok || !data.ok) {
            renderDomainError(root, data.message || 'Unable to check this domain right now.');
            return;
        }

        renderDomainResults(root, data);
    } catch (error) {
        renderDomainError(root, 'The domain search service could not be reached. Please try again.');
    }
}

function renderDomainResults(root, data) {
    const results = Array.isArray(data.results) ? data.results : [];
    if (!results.length) {
        renderDomainError(root, 'No domain results were returned. Please try a different domain.');
        return;
    }

    const match = results.find((item) => item.type === 'match') || results[0];
    const alternatives = results.filter((item) => item.domain !== match.domain);
    const headline = data.available
        ? `${escapeHtml(data.searched)} is available!`
        : `${escapeHtml(data.searched)} is unavailable`;

    root.innerHTML = `
        <div class="domain-result-message ${data.available ? 'is-available' : 'is-unavailable'}">
            <span>${data.available ? 'Available' : 'Taken'}</span>
            <h2>${headline}</h2>
            <p>${data.available ? 'Secure it now or bundle it with cloud hosting.' : 'The exact match is taken, but these alternatives may still work for your business.'}</p>
        </div>
        <div class="domain-feature-grid">
            ${renderExactDomainCard(match)}
            ${renderHostingBundleCard(match, data.hosting_pid)}
        </div>
        <div class="domain-options-card">
            <div class="section-head compact">
                <div>
                    <span class="section-kicker">More options</span>
                    <h2>More domain extensions</h2>
                </div>
            </div>
            <div class="domain-options-list">
                ${alternatives.map(renderAlternativeRow).join('')}
            </div>
        </div>
    `;
}

function renderExactDomainCard(item) {
    const price = formatDomainPrice(item);
    const badge = item.available ? '<span class="result-badge">Match</span>' : '<span class="result-badge muted">Taken</span>';
    const button = item.available
        ? `<a class="btn btn-primary" href="${escapeAttr(item.domain_url)}">Get domain</a>`
        : '<button class="btn btn-light" type="button" disabled>Unavailable</button>';

    return `
        <article class="domain-result-card exact-card">
            <div class="card-topline">${badge}<span>${escapeHtml(item.tld)}</span></div>
            <h3>${escapeHtml(item.domain)}</h3>
            <p>${item.available ? 'Exact match domain ready for registration through WHMCS checkout.' : 'This exact domain is already registered.'}</p>
            <div class="domain-price">${price}<small>/yr</small></div>
            ${button}
        </article>
    `;
}

function renderHostingBundleCard(item, hostingPid) {
    const disabled = !item.available || !hostingPid || hostingPid === 'HOSTING_PID_HERE';
    const button = disabled
        ? '<button class="btn btn-light" type="button" disabled>Hosting bundle unavailable</button>'
        : `<a class="btn btn-primary" href="${escapeAttr(item.hosting_url)}">Get domain + hosting</a>`;

    return `
        <article class="domain-result-card bundle-card">
            <div class="card-topline"><span class="result-badge best">Best value</span><span>Cloud hosting</span></div>
            <h3>${escapeHtml(item.domain)} + Cloud Hosting</h3>
            <p>Register your domain with a hosting plan, cPanel access, SSL and Cloudflare CDN support.</p>
            <ul class="feature-list">
                <li>Free SSL setup</li>
                <li>cPanel hosting</li>
                <li>Cloudflare CDN support</li>
            </ul>
            ${button}
        </article>
    `;
}

function renderAlternativeRow(item) {
    const price = formatDomainPrice(item);
    const status = item.available ? '<span class="availability-pill">Available</span>' : '<span class="availability-pill taken">Taken</span>';
    const action = item.available
        ? `<a class="btn btn-outline" href="${escapeAttr(item.domain_url)}">Get domain</a>`
        : '<button class="btn btn-light" type="button" disabled>Unavailable</button>';

    return `
        <div class="domain-option-row">
            <div>
                <strong>${escapeHtml(item.domain)}</strong>
                ${status}
            </div>
            <span class="option-price">${price}<small>/yr</small></span>
            ${action}
        </div>
    `;
}

function renderDomainError(root, message) {
    root.innerHTML = `
        <div class="domain-error">
            <h2>Domain search unavailable</h2>
            <p>${escapeHtml(message)}</p>
        </div>
    `;
}

function formatDomainPrice(item) {
    if (!item.price) {
        return `<strong>${escapeHtml(item.currency || '£')}--</strong>`;
    }

    return `<strong>${escapeHtml(item.currency || '£')}${escapeHtml(item.price)}</strong>`;
}

function escapeHtml(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

function escapeAttr(value) {
    return escapeHtml(value);
}
