/* Utilitaire XSS — échappe les caractères HTML avant injection via innerHTML */
window.escapeHtml = function(str) {
    return String(str ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
};

/* Bannière de consentement cookies (CNIL) */
(function () {
    if (localStorage.getItem('vg_cookie_consent')) return;

    const banner = document.createElement('div');
    banner.id = 'cookie-banner';
    banner.setAttribute('role', 'dialog');
    banner.setAttribute('aria-label', 'Consentement cookies');
    banner.innerHTML = `
        <p class="cookie-txt">
            Ce site utilise uniquement des cookies techniques nécessaires à son fonctionnement
            (session, préférences). Aucun traceur publicitaire n'est utilisé.
            <a href="rgpd.html#cookies" class="cookie-link">En savoir plus</a>
        </p>
        <div class="cookie-btns">
            <button id="cookie-refuser" class="cookie-btn cookie-btn--outline">Refuser les non-essentiels</button>
            <button id="cookie-accepter" class="cookie-btn cookie-btn--solid">Accepter</button>
        </div>`;
    document.body.appendChild(banner);

    function fermer(choix) {
        localStorage.setItem('vg_cookie_consent', choix);
        banner.classList.add('cookie-banner--hide');
        setTimeout(() => banner.remove(), 400);
    }
    document.getElementById('cookie-accepter').addEventListener('click', () => fermer('accepted'));
    document.getElementById('cookie-refuser').addEventListener('click', () => fermer('refused'));
})();

/* Navbar partagée : badge panier + état de connexion (session PHP) */
(async function () {
    /* ── Badge panier ── */
    const panier = JSON.parse(localStorage.getItem('vg_panier') || '[]');
    const badge  = document.getElementById('panier-badge');
    if (badge) badge.textContent = panier.length;

    /* ── Auth state ── */
    const navCta  = document.querySelector('.navbar-cta');
    const navUser = document.getElementById('navbar-user');

    async function seDeconnecter() {
        await fetch('/api/auth/logout', { method: 'POST', credentials: 'include' });
        window.location.href = 'index.html';
    }
    window.seDeconnecter = seDeconnecter;

    let user = null;
    try {
        const res = await fetch('/api/auth/me', { credentials: 'include' });
        if (res.ok) user = await res.json();
    } catch {}
    window.vgUser = user;

    if (user && navUser) {
        /* ── Desktop : swap CTA → user block ── */
        if (navCta) navCta.hidden = true;
        navUser.hidden = false;

        /* ── Mobile : swap CTA → user block ── */
        const mobileCta  = document.querySelector('.navbar-mobile-cta');
        const mobileUser = document.getElementById('navbar-mobile-user');
        if (mobileCta)  mobileCta.hidden  = true;
        if (mobileUser) mobileUser.hidden = false;

        const prenom        = user.prenom || (user.email ? user.email.split('@')[0] : 'Client');
        const prenomFormate = prenom.charAt(0).toUpperCase() + prenom.slice(1);

        ['navbar-user-prenom', 'navbar-mobile-prenom'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = prenomFormate;
        });
        ['navbar-user-avatar', 'navbar-mobile-avatar'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = prenomFormate.charAt(0);
        });

        /* Boutons déconnexion */
        ['navbar-deco-btn', 'navbar-mobile-deco-btn'].forEach(id => {
            const btn = document.getElementById(id);
            if (btn) btn.addEventListener('click', seDeconnecter);
        });
    } else {
        /* Pas connecté : s'assurer que les CTA sont visibles */
        if (navCta) navCta.hidden = false;
        if (navUser) navUser.hidden = true;
        const mobileUser = document.getElementById('navbar-mobile-user');
        if (mobileUser) mobileUser.hidden = true;
    }
})();

/* ── Horaires du pied de page (chargés depuis la base) ── */
(async function () {
    const corps = document.getElementById('footer-horaires-body');
    if (!corps) return;

    try {
        const res = await fetch('/api/horaires');
        if (!res.ok) return;
        const horaires = await res.json();
        if (!horaires.length) return;

        corps.innerHTML = horaires.map(h => {
            const ferme = h.heure_ouverture === h.heure_fermeture;
            const creneau = ferme ? 'Fermé' : h.heure_ouverture.slice(0, 5).replace(':', 'h') + ' – ' + h.heure_fermeture.slice(0, 5).replace(':', 'h');
            return `<tr><td>${escapeHtml(h.jour)}</td><td>${creneau}</td></tr>`;
        }).join('');
    } catch { /* API non disponible — horaires par défaut affichés */ }
})();

/* ── Burger menu mobile ── */
(function () {
    const burger    = document.getElementById('burger');
    const mobileNav = document.getElementById('mobile-nav');
    if (!burger || !mobileNav) return;
    burger.addEventListener('click', () => {
        const isOpen = mobileNav.classList.toggle('open');
        burger.setAttribute('aria-expanded', isOpen);
        mobileNav.setAttribute('aria-hidden', !isOpen);
        burger.classList.toggle('active', isOpen);
    });
})();
