document.addEventListener('DOMContentLoaded', () => {
    const header = document.querySelector('[data-header]');
    const nav = document.querySelector('[data-nav]');
    const toggle = document.querySelector('[data-nav-toggle]');
    const actions = document.querySelector('.nav-actions');

    if (toggle && nav) {
        const setMenuState = (isOpen) => {
            toggle.setAttribute('aria-expanded', String(isOpen));
            toggle.setAttribute('aria-label', isOpen ? 'Close navigation' : 'Open navigation');
            nav.classList.toggle('is-open', isOpen);
            actions?.classList.toggle('is-open', isOpen);
        };

        toggle.addEventListener('click', () => {
            const open = toggle.getAttribute('aria-expanded') === 'true';
            setMenuState(!open);
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape' && toggle.getAttribute('aria-expanded') === 'true') {
                setMenuState(false);
                toggle.focus();
            }
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

    const stripePayment = document.querySelector('[data-stripe-payment]');
    if (stripePayment) {
        initStripePayment(stripePayment);
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
    const domainBenefits = form.querySelector('[data-domain-benefits]');
    const continueButton = form.querySelector('[data-checkout-continue]');
    const billingInputs = [...form.querySelectorAll('input[name="billing_cycle"]')];
    const planInputs = [...form.querySelectorAll('input[name="hosting_plan"]')];
    const domainPricing = parseJsonData(form.dataset.domainPricing, {});
    const websitePackage = parseJsonData(form.dataset.websitePackage, {
        title: 'Bespoke Website Development',
        price: '199.00',
    });

    const update = () => {
        const type = form.querySelector('input[name="order_type"]:checked')?.value || 'bundle';
        const isDomainOnly = type === 'domain';
        const isHostingOnly = type === 'hosting';
        const usesHosting = type === 'hosting' || type === 'bundle';
        const usesDomain = type === 'domain' || type === 'bundle' || type === 'website';
        const billingCycle = form.querySelector('input[name="billing_cycle"]:checked')?.value || 'monthly';
        form.dataset.billingCycle = billingCycle;

        if (domainSection) {
            domainSection.hidden = false;
        }

        if (hostingSection) {
            hostingSection.hidden = !usesHosting;
        }

        if (domainBenefits) {
            domainBenefits.hidden = isHostingOnly;
        }

        if (domainInput) {
            domainInput.required = usesDomain && !isHostingOnly;
            domainInput.placeholder = isHostingOnly ? 'your-existing-domain.com' : 'example.com';
        }

        billingInputs.forEach((input) => {
            input.disabled = !usesHosting;
        });

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
                domainHelp.textContent = 'This domain will be checked again before your secure payment is created.';
            } else if (type === 'website') {
                domainHelp.textContent = 'Enter the domain you want for the website package. Domain registration is included for the first year where available.';
            } else {
                domainHelp.textContent = 'This domain will be checked again before your secure payment is created.';
            }
        }

        updateCheckoutSteps(form);
        updateOrderSummary(form, domainPricing, websitePackage);
    };

    typeInputs.forEach((input) => input.addEventListener('change', update));
    billingInputs.forEach((input) => input.addEventListener('change', update));
    planInputs.forEach((input) => input.addEventListener('change', update));
    domainInput?.addEventListener('input', update);
    continueButton?.addEventListener('click', () => focusNextCheckoutSection(form, continueButton));
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
        ? 'Live availability must be confirmed before you can order this domain. Please try again shortly.'
        : available
            ? 'Secure it now or start a complete website package with the domain included.'
            : 'The exact match is taken, but these alternatives may still work for your business.';

    root.innerHTML = `
        <div class="domain-result-message ${available ? 'is-available' : 'is-unavailable'}" role="status">
            <span>${badgeText}</span>
            <h2>${headline}</h2>
            <p>${messageText}</p>
        </div>
        <div class="domain-feature-grid">
            ${renderExactDomainCard(match)}
            ${renderWebsitePackageCard(match)}
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
            <p>${available ? 'Exact match domain ready for secure checkout.' : unavailableDomainText(item)}</p>
            <div class="domain-price">${price}<small>/yr</small></div>
            ${button}
        </article>
    `;
}

function renderWebsitePackageCard(item) {
    const packageInfo = item.website_package || {};
    const packageTitle = packageInfo.title || 'Bespoke Website Development';
    const packageDescription = packageInfo.description || 'Launch a professional business website with domain, hosting setup, Elementor, premium Envato elements, stock photos, content writing and Cloudflare integration included.';
    const packagePrice = packageInfo.price || '199.00';
    const packageCta = packageInfo.cta_text || 'Get Complete Website Package';
    const packageFeatures = Array.isArray(packageInfo.features) && packageInfo.features.length
        ? packageInfo.features
        : [
            'Free domain and hosting for 1 year',
            'Free Elementor',
            'Free Envato premium elements',
            'Free stock photos',
            'Free premium content writing',
            'Free Cloudflare integration',
            'Free SSL setup',
            'Free domain and hosting setup support',
        ];
    const checkoutUrl = item.website_checkout_url || `/checkout?type=website${item.available === true ? `&domain=${encodeURIComponent(item.domain)}` : ''}`;
    const domainNote = item.available === true
        ? `${escapeHtml(item.domain)} can be included with your website package.`
        : 'Start the website package and choose an available domain at checkout.';

    return `
        <article class="domain-result-card bundle-card">
            <div class="card-topline"><span class="result-badge best">Best Value</span><span>Website package</span></div>
            <h3>${escapeHtml(packageTitle)}</h3>
            <p>${escapeHtml(packageDescription)}</p>
            <div class="domain-price"><strong>${formatPackagePrice(packagePrice)}</strong><small>one-time</small></div>
            <ul class="feature-list">
                ${packageFeatures.slice(0, 9).map((feature) => `<li>${escapeHtml(feature)}</li>`).join('')}
            </ul>
            <p class="package-note">${domainNote}</p>
            <a class="btn btn-primary" href="${escapeAttr(checkoutUrl)}">${escapeHtml(packageCta)}</a>
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
        return 'Live availability could not be confirmed for this extension.';
    }

    return 'This exact domain is already registered.';
}

function renderDomainError(root, message) {
    root.innerHTML = `
        <div class="domain-error" role="alert">
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

function updateOrderSummary(form, domainPricing, websitePackage) {
    const summary = form.querySelector('[data-order-summary]');
    const lines = form.querySelector('[data-summary-lines]');
    if (!summary || !lines) {
        return;
    }

    const type = form.querySelector('input[name="order_type"]:checked')?.value || 'bundle';
    const billingCycle = form.querySelector('input[name="billing_cycle"]:checked')?.value || 'monthly';
    const domain = normaliseDomainValue(form.querySelector('input[name="domain"]')?.value || '');
    const plan = selectedPlanData(form);
    const websitePrice = parseMoney(websitePackage.price || '199.00');
    const registersDomain = ['domain', 'bundle', 'website'].includes(type);
    const usesHosting = ['hosting', 'bundle'].includes(type);
    const isWebsite = type === 'website';
    const domainPrice = registersDomain && domain ? priceForDomain(domain, domainPricing) : 0;
    const domainCharge = registersDomain && !isWebsite ? domainPrice : 0;
    const hostingToday = usesHosting ? (billingCycle === 'annually' ? plan.yearly : plan.monthly) : 0;
    const monthlyRecurring = usesHosting && billingCycle === 'monthly' ? plan.monthly : 0;
    const yearlyHostingRecurring = usesHosting && billingCycle === 'annually' ? plan.yearly : 0;
    const yearlyDomainRenewal = registersDomain && domain ? domainPrice : 0;
    const todayPayment = (isWebsite ? websitePrice : 0) + domainCharge + hostingToday;

    const packageTitle = {
        domain: 'Domain Registration',
        hosting: 'Hosting Package',
        bundle: 'Domain + Hosting',
        website: websitePackage.title || 'Bespoke Website Development',
    }[type] || 'Selected Package';

    const rows = [
        summaryRow('Selected Package', packageTitle, packagePriceText(type, websitePrice, hostingToday, billingCycle)),
    ];

    if (domain) {
        const domainText = isWebsite
            ? 'Free for the first year with your website package'
            : registersDomain
                ? `${formatMoney(domainPrice)} / year`
                : 'Existing domain, no registration charge';
        rows.push(summaryRow('Domain', domain, domainText));
    } else if (registersDomain) {
        rows.push(summaryRow('Domain', 'Choose your domain', isWebsite ? 'Included for first year when available' : 'Price shown after domain entry'));
    }

    if (isWebsite) {
        rows.push(summaryRow('Hosting', 'Included hosting setup', 'Free for 1 year'));
    } else if (usesHosting) {
        rows.push(summaryRow('Hosting', plan.title || 'Selected hosting', billingCycle === 'annually' ? `${formatMoney(plan.yearly)} / year` : `${formatMoney(plan.monthly)} / month`));
    }

    if (isWebsite) {
        rows.push(`
            <div class="summary-row summary-addons">
                <span>Optional Add-ons</span>
                <ul>
                    <li>Cloudflare Integration <strong>Free</strong></li>
                    <li>Premium Content Writing <strong>Free</strong></li>
                    <li>Stock Photos <strong>Free</strong></li>
                    <li>Elementor Setup <strong>Free</strong></li>
                    <li>Envato Premium Elements <strong>Free</strong></li>
                </ul>
            </div>
        `);
    }

    lines.innerHTML = rows.join('');
    form.querySelector('[data-summary-subtotal]').textContent = subtotalText(type, {
        websitePrice,
        domainCharge,
        domainPrice,
        hostingToday,
        monthlyRecurring,
        yearlyHostingRecurring,
        billingCycle,
        isWebsite,
        registersDomain,
        usesHosting,
    });
    form.querySelector('[data-summary-today]').textContent = formatMoney(todayPayment);
    form.querySelector('[data-summary-monthly]').textContent = `${formatMoney(monthlyRecurring)}/month`;
    form.querySelector('[data-summary-yearly-hosting]').textContent = `${formatMoney(yearlyHostingRecurring)}/year`;
    form.querySelector('[data-summary-yearly]').textContent = isWebsite && domain
        ? `${formatMoney(yearlyDomainRenewal)}/year after first year`
        : `${formatMoney(yearlyDomainRenewal)}/year`;
}

function selectedPlanData(form) {
    const selected = form.querySelector('input[name="hosting_plan"]:checked')?.closest('[data-plan-title]');
    const monthly = parseMoney(selected?.dataset.planMonthly || '0');
    let yearly = parseMoney(selected?.dataset.planYearly || '0');
    if (yearly <= 0 && monthly > 0) {
        yearly = monthly * 12;
    }

    return {
        title: selected?.dataset.planTitle || 'Starter Hosting',
        monthly,
        yearly,
    };
}

function summaryRow(label, title, meta) {
    return `
        <div class="summary-row">
            <span>${escapeHtml(label)}</span>
            <strong>${escapeHtml(title)}</strong>
            <small>${escapeHtml(meta)}</small>
        </div>
    `;
}

function packagePriceText(type, websitePrice, hostingToday, billingCycle) {
    if (type === 'website') {
        return `${formatPackagePrice(websitePrice)} one-time`;
    }
    if (type === 'hosting') {
        return billingCycle === 'annually' ? `${formatMoney(hostingToday)} / year` : `${formatMoney(hostingToday)} / month`;
    }
    if (type === 'domain') {
        return 'Domain registration only';
    }

    return 'Domain registration with hosting';
}

function subtotalText(type, values) {
    const parts = [];
    if (type === 'website') {
        parts.push(`${formatPackagePrice(values.websitePrice)} one-time website package`);
        if (values.registersDomain) {
            parts.push('domain included first year');
        }
        parts.push('hosting included first year');
    } else {
        if (values.domainCharge > 0) {
            parts.push(`${formatMoney(values.domainCharge)} domain`);
        }
        if (values.usesHosting) {
            parts.push(values.billingCycle === 'annually'
                ? `${formatMoney(values.hostingToday)} yearly hosting`
                : `${formatMoney(values.hostingToday)} first month hosting`);
        }
    }

    return parts.length ? parts.join(' + ') : 'No billable item selected yet';
}

function priceForDomain(domain, pricing) {
    const lower = domain.toLowerCase();
    const tld = Object.keys(pricing || {})
        .sort((a, b) => b.length - a.length)
        .find((key) => lower.endsWith(key.toLowerCase()));
    if (!tld) {
        return 0;
    }

    return parseMoney(pricing[tld]?.price || '0');
}

function parseMoney(value) {
    const number = Number(String(value ?? '').replace(/[^0-9.]/g, ''));
    return Number.isFinite(number) ? number : 0;
}

function formatMoney(value) {
    return `£${Number(value || 0).toFixed(2)}`;
}

function formatPackagePrice(value) {
    const amount = parseMoney(value);
    return Number.isInteger(amount) ? `£${amount.toFixed(0)}` : formatMoney(amount);
}

function normaliseDomainValue(value) {
    return String(value || '').trim().toLowerCase();
}

function parseJsonData(value, fallback) {
    try {
        return value ? JSON.parse(value) : fallback;
    } catch (error) {
        return fallback;
    }
}

function focusNextCheckoutSection(form, button) {
    const sections = [...form.querySelectorAll('[data-checkout-section]')].filter((section) => !section.hidden);
    const current = button.closest('[data-checkout-section]');
    const index = sections.indexOf(current);
    const next = sections[index + 1];
    const target = next?.querySelector('input:not([type="hidden"]), select, textarea, button, a[href]');
    target?.focus({ preventScroll: true });
    next?.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

async function initStripePayment(root) {
    const form = document.querySelector('#stripe-payment-form');
    const submit = document.querySelector('#stripe-submit');
    const message = document.querySelector('#payment-message');
    const publishableKey = root.dataset.publishableKey || '';
    const clientSecret = root.dataset.clientSecret || '';
    const returnUrl = root.dataset.returnUrl || window.location.href;
    const failedUrl = root.dataset.failedUrl || '';

    if (!form || !submit || !publishableKey || !clientSecret || typeof Stripe === 'undefined') {
        if (message) {
            message.hidden = false;
            message.textContent = 'Secure payment could not be loaded. Please refresh or contact support.';
        }
        return;
    }

    const stripe = Stripe(publishableKey);
    const elements = stripe.elements({
        clientSecret,
        appearance: {
            theme: 'stripe',
            variables: {
                colorPrimary: '#087f75',
                colorText: '#06172b',
                borderRadius: '8px',
                fontFamily: 'Inter, system-ui, sans-serif',
            },
        },
    });
    elements.create('payment').mount('#payment-element');

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        submit.disabled = true;
        submit.dataset.originalText = submit.dataset.originalText || submit.textContent;
        submit.textContent = 'Processing secure payment...';
        if (message) {
            message.hidden = true;
            message.textContent = '';
        }

        const { error, paymentIntent } = await stripe.confirmPayment({
            elements,
            confirmParams: { return_url: returnUrl },
            redirect: 'if_required',
        });

        if (error) {
            if (message) {
                message.hidden = false;
                message.textContent = error.message || 'Payment could not be completed. Please check your details and try again.';
            }
            submit.disabled = false;
            submit.textContent = submit.dataset.originalText;
            return;
        }

        if (paymentIntent && paymentIntent.status === 'succeeded') {
            window.location.href = returnUrl;
            return;
        }

        if (paymentIntent && paymentIntent.status === 'requires_payment_method' && failedUrl) {
            window.location.href = failedUrl;
            return;
        }

        window.location.href = returnUrl;
    });
}
