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

    const checkoutForm = document.querySelector('[data-checkout-form]');
    if (checkoutForm) {
        initCheckoutForm(checkoutForm);
    }
});

function initCheckoutForm(form) {
    const typeInputs = [...form.querySelectorAll('input[name="order_type"]')];
    const domainSection = form.querySelector('[data-checkout-section="domain"]');
    const hostingSection = form.querySelector('[data-checkout-section="hosting"]');
    const domainInput = form.querySelector('input[name="domain"]');
    const domainTitle = form.querySelector('[data-domain-title]');
    const domainLabel = form.querySelector('[data-domain-label]');
    const domainHelp = form.querySelector('[data-domain-help]');

    const update = () => {
        const type = form.querySelector('input[name="order_type"]:checked')?.value || 'bundle';
        const isDomainOnly = type === 'domain';
        const isHostingOnly = type === 'hosting';
        const usesHosting = type === 'hosting' || type === 'bundle';
        const usesDomain = type === 'domain' || type === 'bundle' || type === 'website';

        if (domainSection) {
            domainSection.hidden = false;
        }

        if (hostingSection) {
            hostingSection.hidden = !usesHosting;
        }

        if (domainInput) {
            domainInput.required = usesDomain && !isHostingOnly;
            domainInput.placeholder = isHostingOnly ? 'your-existing-domain.com' : 'example.com';
        }

        if (domainTitle) {
            domainTitle.textContent = isHostingOnly ? 'Existing domain' : 'Domain';
        }

        if (domainLabel) {
            domainLabel.textContent = isHostingOnly ? 'Existing domain name (optional)' : 'Domain name';
        }

        if (domainHelp) {
            if (isHostingOnly) {
                domainHelp.textContent = 'Optional. Add the domain you want this hosting account linked to, or leave blank and provide it later.';
            } else if (isDomainOnly) {
                domainHelp.textContent = 'This domain will be registered through WHMCS after checkout creates your invoice.';
            } else if (type === 'website') {
                domainHelp.textContent = 'Enter the domain you want for the website package. It will be checked server-side where domain registration is included.';
            } else {
                domainHelp.textContent = 'This domain will be checked again server-side before the WHMCS order is created.';
            }
        }

        updateCheckoutSteps(form);
    };

    typeInputs.forEach((input) => input.addEventListener('change', update));
    update();
}

function updateCheckoutSteps(form) {
    let step = 1;
    [...form.querySelectorAll('[data-checkout-section]')].forEach((section) => {
        const stepLabel = section.querySelector('[data-checkout-step]');
        if (section.hidden || !stepLabel) {
            return;
        }

        stepLabel.textContent = `Step ${step}`;
        step += 1;
    });
}

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
    const availabilityChecked = data.availability_checked !== false;
    const available = availabilityChecked && match.available === true;
    const headline = !availabilityChecked
        ? `Live check unavailable for ${escapeHtml(data.searched)}`
        : available
        ? `${escapeHtml(data.searched)} is available!`
        : `${escapeHtml(data.searched)} is unavailable`;
    const badgeText = !availabilityChecked ? 'Check unavailable' : (available ? 'Available' : 'Taken');
    const messageText = !availabilityChecked
        ? 'Live WHMCS availability must be confirmed before you can order this domain. Please try again shortly.'
        : available
            ? 'Secure it now or bundle it with cloud hosting.'
            : 'The exact match is taken, but these alternatives may still work for your business.';

    root.innerHTML = `
        <div class="domain-result-message ${available ? 'is-available' : 'is-unavailable'}">
            <span>${badgeText}</span>
            <h2>${headline}</h2>
            <p>${messageText}</p>
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
    const available = item.available === true;
    const badge = item.available === null || item.available === undefined
        ? '<span class="result-badge muted">Check unavailable</span>'
        : item.available ? '<span class="result-badge">Match</span>' : '<span class="result-badge muted">Taken</span>';
    const button = available
        ? `<a class="btn btn-primary" href="${escapeAttr(item.checkout_url || item.domain_url)}">Get domain</a>`
        : '<button class="btn btn-light" type="button" disabled>Unavailable</button>';

    return `
        <article class="domain-result-card exact-card">
            <div class="card-topline">${badge}<span>${escapeHtml(item.tld)}</span></div>
            <h3>${escapeHtml(item.domain)}</h3>
            <p>${available ? 'Exact match domain ready for main-site checkout and WHMCS-backed billing.' : unavailableDomainText(item)}</p>
            <div class="domain-price">${price}<small>/yr</small></div>
            ${button}
        </article>
    `;
}

function renderHostingBundleCard(item, hostingPid) {
    const disabled = item.available !== true || !hostingPid || hostingPid === 'HOSTING_PID_HERE';
    const button = disabled
        ? '<button class="btn btn-light" type="button" disabled>Hosting bundle unavailable</button>'
        : `<a class="btn btn-primary" href="${escapeAttr(item.bundle_checkout_url || item.hosting_url)}">Get domain + hosting</a>`;

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
    const available = item.available === true;
    const status = item.available === null || item.available === undefined
        ? '<span class="availability-pill taken">Check unavailable</span>'
        : item.available ? '<span class="availability-pill">Available</span>' : '<span class="availability-pill taken">Taken</span>';
    const action = available
        ? `<a class="btn btn-outline" href="${escapeAttr(item.checkout_url || item.domain_url)}">Get domain</a>`
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

function unavailableDomainText(item) {
    if (item.available === null || item.available === undefined) {
        return 'Live WHMCS availability could not be confirmed for this extension.';
    }

    return 'This exact domain is already registered.';
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
