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
});
