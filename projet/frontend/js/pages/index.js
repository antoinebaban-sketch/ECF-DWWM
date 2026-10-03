/* Script de la page index.html */

/* ── AVIS CLIENTS ── */
const COULEURS_AVATAR = ['#6B8F5E','#8B6914','#4A6B8A','#7B5EA7','#C0644A','#4A7B7B'];

function etoiles(note) {
    return '★'.repeat(note) + '☆'.repeat(5 - note);
}

function formatDate(dateStr) {
    const d = new Date(dateStr);
    return d.toLocaleDateString('fr-FR', { month: 'long', year: 'numeric' });
}

function construireCarteAvis(avis) {
    const prenom = avis.prenom || 'Client';
    const initiale = prenom[0].toUpperCase();
    const couleur = COULEURS_AVATAR[prenom.charCodeAt(0) % COULEURS_AVATAR.length];
    const div = document.createElement('div');
    div.className = 'avis-card';
    div.innerHTML = `
        <div class="avis-header">
            <span class="avis-avatar" style="background:${couleur};">${escapeHtml(initiale)}</span>
            <div>
                <strong>${escapeHtml(prenom)}</strong>
                <span class="avis-date">${formatDate(avis.date_avis)}</span>
            </div>
        </div>
        <div class="avis-stars">${etoiles(avis.note)}</div>
        <p class="avis-texte">« ${escapeHtml(avis.description)} »</p>`;
    return div;
}

async function chargerAvis() {
    try {
        /* Récupère les avis validés avec note >= 3, triés par date décroissante */
        const res = await fetch('/api/avis?statut=validé&note_min=3&limit=3');
        if (!res.ok) return;
        const avis = await res.json();
        if (!avis.length) return;
        const section = document.getElementById('avis-section');
        section.innerHTML = '';
        avis.forEach(a => section.appendChild(construireCarteAvis(a)));
    } catch { /* API non connectée — fallback statique affiché */ }
}
chargerAvis();

/* ── CAROUSEL MENUS POPULAIRES ── */
const MENUS_FALLBACK = [
    {
        titre: 'Filet de Poulet Grillé et Mousseline Maison',
        description: 'Un classique revisité avec élégance. Filet de poulet doré, servi sur un lit de purée maison fondante aux herbes fraîches.',
        prix: '16,90',
        image: 'images/filet_poulet.png',
        commandes: 142
    },
    {
        titre: 'Bowl Santé Gourmand',
        description: 'Quinoa, avocat frais, tomates cerises, feta crumble et vinaigrette citron-miel. Frais, nourrissant et plein de saveurs.',
        prix: '12,00',
        image: 'images/avocat_bowl.png',
        commandes: 118
    },
    {
        titre: 'Plateau de Charcuterie Aragonaise',
        description: 'Jambon serrano affiné, chorizo pimenté, manchego fondant et olives marinées aux herbes. Idéal pour vos apéritifs.',
        prix: '18,00',
        image: 'images/plateau_charcuterie.png',
        commandes: 97
    }
];

let indexActuel = 0;
let menus = [];

function construireCarteCarousel(menu) {
    const div = document.createElement('div');
    div.className = 'carousel-card';
    div.innerHTML = `
        <div class="carousel-card-img">
            <img src="${menu.image ?? menu.image_url ?? ''}" alt="${menu.titre ?? menu.titre_plat}">
        </div>
        <div class="carousel-card-body">
            <p class="carousel-card-commandes">${menu.nb_commandes ?? menu.commandes ?? ''} commandes</p>
            <h3 class="carousel-card-titre">${menu.titre ?? menu.titre_plat}</h3>
            <p class="carousel-card-desc">${menu.description}</p>
            <div class="carousel-card-footer">
                <span class="carousel-card-prix">À partir de ${menu.prix ?? menu.prix_par_personne} €</span>
                <a href="menu.html" class="btn-or" style="font-size:12px;padding:9px 20px;">Voir les menus</a>
            </div>
        </div>`;
    return div;
}

function afficherCarousel() {
    const track = document.getElementById('carousel-track');
    const dotsWrap = document.getElementById('carousel-dots');
    track.innerHTML = '';
    dotsWrap.innerHTML = '';

    menus.forEach((m, i) => {
        const card = construireCarteCarousel(m);
        if (i === indexActuel) card.classList.add('active');
        track.appendChild(card);

        const dot = document.createElement('button');
        dot.className = 'carousel-dot' + (i === indexActuel ? ' active' : '');
        dot.setAttribute('aria-label', 'Menu ' + (i + 1));
        dot.addEventListener('click', () => allerA(i));
        dotsWrap.appendChild(dot);
    });
}

function allerA(index) {
    const cards = document.querySelectorAll('.carousel-card');
    const dots  = document.querySelectorAll('.carousel-dot');
    cards[indexActuel].classList.remove('active');
    dots[indexActuel].classList.remove('active');
    indexActuel = (index + menus.length) % menus.length;
    cards[indexActuel].classList.add('active');
    dots[indexActuel].classList.add('active');
}

document.getElementById('carousel-prev').addEventListener('click', () => allerA(indexActuel - 1));
document.getElementById('carousel-next').addEventListener('click', () => allerA(indexActuel + 1));

async function chargerMenusPopulaires() {
    try {
        const res = await fetch('/api/menus/populaires');
        if (res.ok) {
            const data = await res.json();
            if (data.length) { menus = data.slice(0, 3); afficherCarousel(); return; }
        }
    } catch { /* API non connectée — fallback */ }
    menus = MENUS_FALLBACK;
    afficherCarousel();
}
chargerMenusPopulaires();

