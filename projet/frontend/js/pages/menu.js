/* Script de la page menu.html */

/* ── Filtres ── */
const inputRecherche = document.getElementById('recherche-plat');
const inputPrixMin   = document.getElementById('prix-min');
const inputPrixMax   = document.getElementById('prix-max');
const inputPersonnes = document.getElementById('nb-personnes');
const selectTheme    = document.getElementById('filtre-theme');
const selectRegime   = document.getElementById('filtre-regime');

function filtrerCartes() {
    const terme  = inputRecherche.value.toLowerCase().trim();
    const pMin   = parseFloat(inputPrixMin.value)  || 0;
    const pMax   = parseFloat(inputPrixMax.value)  || 99999;
    const nb     = parseInt(inputPersonnes.value)  || 1;
    const theme  = selectTheme.value;
    const regime = selectRegime.value;

    let visible = 0;
    document.querySelectorAll('.carte-plat').forEach(c => {
        const prixBase = parseFloat(c.dataset.prixMin || c.dataset.prix) || 0;
        const mini     = parseInt(c.dataset.personnesMini) || 1;
        const themeId  = c.dataset.themeId  || '';
        const regimeId = c.dataset.regimeId || '';
        const titre    = (c.querySelector('.carte-titre')?.textContent || '').toLowerCase();
        const desc     = (c.querySelector('.carte-desc')?.textContent  || '').toLowerCase();

        // Filtre prix sur le prix de base (par personne)
        const okPrix   = prixBase >= pMin && prixBase <= pMax;
        const okTheme  = !theme  || themeId  === theme;
        const okRegime = !regime || regimeId === regime;
        const okSearch = !terme  || titre.includes(terme) || desc.includes(terme);

        // Le filtre personnes ne masque plus les cartes — juste prix + badge
        const ok = okPrix && okTheme && okRegime && okSearch;
        c.style.display = ok ? '' : 'none';
        if (ok) visible++;

        // ── Mise à jour du prix affiché ──
        const prixEl = c.querySelector('.carte-prix');
        if (prixEl) {
            if (nb > 1) {
                const remise  = nb >= mini && (nb - mini) >= 5;
                const pxFinal = remise ? prixBase * 0.9 : prixBase;
                const total   = pxFinal * nb;
                prixEl.textContent = total.toFixed(2).replace('.', ',') + ' € pour ' + nb + ' pers.';
                prixEl.style.color = remise ? 'var(--or)' : '';
            } else {
                prixEl.textContent = prixBase.toFixed(2).replace('.', ',') + ' € / pers.';
                prixEl.style.color = '';
            }
        }

        // ── Badge "Pour au moins X personnes" ──
        let notif = c.querySelector('.carte-pers-notif');
        if (nb > 1 && mini > nb) {
            if (!notif) {
                notif = document.createElement('span');
                notif.className = 'carte-pers-notif';
                (prixEl || c).insertAdjacentElement('afterend', notif);
            }
            notif.textContent = '⚠ Pour au moins ' + mini + ' personnes';
        } else if (notif) {
            notif.remove();
        }
    });
    document.getElementById('catalogue-vide').hidden = visible > 0;
}

inputRecherche.addEventListener('input', filtrerCartes);
inputPrixMin.addEventListener('input', filtrerCartes);
inputPrixMax.addEventListener('input', filtrerCartes);
selectTheme.addEventListener('change', filtrerCartes);
selectRegime.addEventListener('change', filtrerCartes);

// Saisie directe + boutons +/- pour le filtre personnes
inputPersonnes.addEventListener('input', () => {
    let v = parseInt(inputPersonnes.value) || 1;
    if (v < 1) { inputPersonnes.value = 1; v = 1; }
    if (v > 500) { inputPersonnes.value = 500; v = 500; }
    filtrerCartes();
});
document.getElementById('btn-moins').addEventListener('click', () => {
    const v = parseInt(inputPersonnes.value) || 1;
    if (v > 1) { inputPersonnes.value = v - 1; filtrerCartes(); }
});
document.getElementById('btn-plus').addEventListener('click', () => {
    const v = parseInt(inputPersonnes.value) || 1;
    if (v < 500) { inputPersonnes.value = v + 1; filtrerCartes(); }
});

/* ── Chargement thèmes ── */
async function chargerThemes() {
    try {
        const res = await fetch('/api/themes');
        if (!res.ok) return;
        const themes = await res.json();
        themes.forEach(t => {
            const o = document.createElement('option');
            o.value = t.theme_id; o.textContent = t.libelle;
            selectTheme.appendChild(o);
        });
    } catch {}
}

/* ── Chargement régimes ── */
async function chargerRegimes() {
    try {
        const res = await fetch('/api/regimes');
        if (!res.ok) return;
        const regimes = await res.json();
        regimes.forEach(r => {
            const o = document.createElement('option');
            o.value = r.regime_id; o.textContent = r.libelle;
            selectRegime.appendChild(o);
        });
    } catch {}
}

/* ── Construction carte menu (depuis API) ── */
function construireCarteMenu(menu) {
    const img    = (menu.images && menu.images[0]) || menu.image || 'images/avocat_bowl.png';
    const theme  = menu.theme ? `<span class="carte-theme-tag">${escapeHtml(menu.theme)}</span>` : '';
    const regimes = (menu.regimes || []).map(r => r.libelle || r).join(', ');
    const regBadge = regimes && regimes !== 'Sans régime particulier'
        ? `<span class="carte-theme-tag carte-theme-tag--veggie">${escapeHtml(regimes)}</span>` : '';
    const stock  = menu.quantite_restante > 0
        ? `<span class="carte-stock-badge">${menu.quantite_restante} disponibles</span>`
        : `<span class="carte-stock-badge carte-stock-badge--complet">Complet</span>`;
    // Fallback regime_id : prend le premier de la liste
    const regId  = (menu.regimes && menu.regimes[0]?.regime_id) || menu.regime_id || '';

    const a = document.createElement('article');
    a.className = 'carte-plat';
    a.dataset.menuId       = menu.menu_id;
    a.dataset.prix         = menu.prix_par_personne;
    a.dataset.prixMin      = menu.prix_par_personne;
    a.dataset.themeId      = menu.theme_id || '';
    a.dataset.regimeId     = regId;
    a.dataset.personnesMini = menu.nombre_personne_mini;
    a.innerHTML = `
        <div class="carte-img">
            <img src="${img}" alt="${escapeHtml(menu.titre)}">
            ${stock}
        </div>
        <div class="carte-body">
            <div class="carte-meta-row">${theme}${regBadge}<span class="carte-mini-tag">min. ${menu.nombre_personne_mini} pers.</span></div>
            <h2 class="carte-titre">${escapeHtml(menu.titre)}</h2>
            <p class="carte-desc">${escapeHtml(menu.description || '')}</p>
            <div class="carte-footer">
                <span class="carte-prix">${parseFloat(menu.prix_par_personne).toFixed(2).replace('.',',')} € / pers.</span>
                <button class="btn-details">Détails et Commander</button>
            </div>
        </div>`;
    return a;
}

/* ── Chargement menus depuis API ── */
async function chargerMenus() {
    try {
        const res = await fetch('/api/menus');
        if (!res.ok) return;
        const menus = await res.json();
        const grille = document.getElementById('catalogue-grid');
        grille.innerHTML = '';
        menus.forEach(m => grille.appendChild(construireCarteMenu(m)));
        attacherDetails();
    } catch { /* cartes statiques conservées */ }
}

/* ── Modal détail ── */
const overlay      = document.getElementById('modal-overlay');
const modalImg     = document.getElementById('modal-img');
const modalThumbs  = document.getElementById('modal-thumbs');
const modalTitre   = document.getElementById('modal-titre');
const modalDesc    = document.getElementById('modal-desc');
const modalConds   = document.getElementById('modal-conditions');
const modalCondTxt = document.getElementById('modal-cond-txt');
const modalRegimes = document.getElementById('modal-regimes');
const modalCompo   = document.getElementById('modal-composition');
const modalAllerLi = document.getElementById('modal-allergenes-liste');
const modalNb      = document.getElementById('modal-nb');
const modalPrixLbl = document.getElementById('modal-prix-label');
const modalPrixTot = document.getElementById('modal-prix-total');
const modalMiniTxt = document.getElementById('modal-mini-txt');
const modalRemise  = document.getElementById('modal-remise');
const btnCmd       = document.getElementById('btn-commander');

let menuActuel = null;

function majPrixModal() {
    if (!menuActuel) return;
    const nb      = parseInt(modalNb.value) || 1;
    const mini    = menuActuel.nombre_personne_mini;
    const px      = parseFloat(menuActuel.prix_par_personne);
    const remise  = (nb - mini) >= 5;
    const pxFinal = remise ? px * 0.9 : px;
    const total   = pxFinal * nb;

    modalPrixLbl.textContent = remise
        ? `${nb} × ${pxFinal.toFixed(2).replace('.',',')} €/pers. (avec remise 10 %)`
        : `${nb} × ${px.toFixed(2).replace('.',',')} €/pers.`;
    modalPrixTot.textContent = total.toFixed(2).replace('.', ',') + ' €';
    modalRemise.hidden = !remise;
}

function ouvrirModal(carte) {
    const id = parseInt(carte.dataset.menuId);
    const px = parseFloat(carte.dataset.prix);
    const mini = parseInt(carte.dataset.personnesMini) || 1;

    menuActuel = { menu_id: id, prix_par_personne: px, nombre_personne_mini: mini,
                   titre: carte.querySelector('.carte-titre').textContent };

    modalImg.src  = carte.querySelector('.carte-img img')?.src || '';
    modalImg.alt  = menuActuel.titre;
    modalTitre.textContent = menuActuel.titre;
    modalDesc.textContent  = carte.querySelector('.carte-desc')?.textContent || '';
    modalConds.hidden      = true;
    modalRegimes.innerHTML = '';
    modalCompo.innerHTML   = '';
    modalAllerLi.innerHTML = '<li>Chargement…</li>';
    modalThumbs.innerHTML  = '';
    modalNb.min   = mini;
    modalNb.value = mini;
    modalMiniTxt.textContent = `(min. ${mini} personnes)`;
    majPrixModal();
    btnCmd.href = `commande.html?menu_id=${id}&personnes=${mini}`;

    overlay.classList.add('open');
    majBtnPanier();
    overlay.removeAttribute('aria-hidden');
    document.body.style.overflow = 'hidden';
    majBtnPanier();

    /* Fetch détail complet */
    fetch('/api/menus/' + id)
        .then(r => r.json())
        .then(m => {
            menuActuel = { ...menuActuel, ...m };

            /* Conditions */
            if (m.conditions) {
                modalCondTxt.textContent = m.conditions;
                modalConds.hidden = false;
            }

            /* Galerie */
            if (m.images && m.images.length) {
                modalImg.src = m.images[0];
                m.images.forEach((url, i) => {
                    const t = document.createElement('img');
                    t.src = url; t.alt = `Vue ${i+1}`;
                    t.className = i === 0 ? 'thumb active' : 'thumb';
                    t.addEventListener('click', () => {
                        modalImg.src = url;
                        modalThumbs.querySelectorAll('.thumb').forEach(x => x.classList.remove('active'));
                        t.classList.add('active');
                    });
                    modalThumbs.appendChild(t);
                });
            }

            /* Régimes */
            if (m.regimes && m.regimes.length) {
                modalRegimes.innerHTML = '<p class="modal-section-titre">Régimes</p>' +
                    m.regimes.map(r => `<span class="mf-badge">${r.libelle}</span>`).join('');
            }

            /* Composition (entrée / plat / dessert) */
            if (m.plats && m.plats.length) {
                const types = ['Entrée','Plat','Dessert','Boisson'];
                const html  = types.map(t => {
                    const liste = m.plats.filter(p => p.type_plat === t);
                    if (!liste.length) return '';
                    return `<div class="mf-plat-type"><strong>${t} :</strong> ${liste.map(p => p.titre_plat).join(', ')}</div>`;
                }).join('');
                if (html) modalCompo.innerHTML = '<p class="modal-section-titre">Composition</p>' + html;
            }

            /* Allergènes */
            if (m.allergenes && m.allergenes.length) {
                modalAllerLi.innerHTML = m.allergenes.map(a => `<li>${a.libelle}</li>`).join('');
            } else {
                modalAllerLi.innerHTML = '<li>Aucun allergène majeur identifié</li>';
            }

            majLienCommander();
        })
        .catch(() => { modalAllerLi.innerHTML = '<li>Non disponible (API non connectée)</li>'; });
}

function fermerModal() {
    overlay.classList.remove('open');
    overlay.setAttribute('aria-hidden', 'true');
    document.body.style.overflow = '';
    menuActuel = null;
}

document.getElementById('modal-fermer').addEventListener('click', fermerModal);
overlay.addEventListener('click', e => { if (e.target === overlay) fermerModal(); });
document.addEventListener('keydown', e => { if (e.key === 'Escape') fermerModal(); });

document.getElementById('modal-btn-moins').addEventListener('click', () => {
    const mini = menuActuel?.nombre_personne_mini || 1;
    const v    = parseInt(modalNb.value) || mini;
    if (v > mini) { modalNb.value = v - 1; majPrixModal(); majLienCommander(); }
});
document.getElementById('modal-btn-plus').addEventListener('click', () => {
    const v = parseInt(modalNb.value) || 1;
    if (v < 500) { modalNb.value = v + 1; majPrixModal(); majLienCommander(); }
});
modalNb.addEventListener('input', () => {
    const mini = menuActuel?.nombre_personne_mini || 1;
    let v = parseInt(modalNb.value);
    if (isNaN(v) || v < 1) return; // laisser taper sans bloquer
    if (v > 500) { modalNb.value = 500; v = 500; }
    majPrixModal();
    majLienCommander();
});
modalNb.addEventListener('blur', () => {
    const mini = menuActuel?.nombre_personne_mini || 1;
    const v    = parseInt(modalNb.value);
    if (isNaN(v) || v < mini) { modalNb.value = mini; majPrixModal(); majLienCommander(); }
});

function majLienCommander() {
    if (!menuActuel) return;
    const nb = parseInt(modalNb.value) || menuActuel.nombre_personne_mini;
    document.getElementById('btn-commander').href =
        `commande.html?menu_id=${menuActuel.menu_id}&personnes=${nb}`;
}

/* ── Panier (getPanier/savePanier partagées, voir js/panier.js) ── */
function majBtnPanier() {
    const btn = document.getElementById('btn-panier-modal');
    if (!btn || !menuActuel) return;
    const dejaDedans = getPanier().some(x => x.menu_id === menuActuel.menu_id);
    btn.textContent = dejaDedans ? '✓ Dans le panier' : '🛒 Ajouter au panier';
    btn.style.background = dejaDedans ? 'var(--brun)' : '';
    btn.disabled = dejaDedans;
}

document.getElementById('btn-panier-modal').addEventListener('click', () => {
    if (!menuActuel) return;
    const panier = getPanier();
    if (!panier.some(x => x.menu_id === menuActuel.menu_id)) {
        panier.push({
            menu_id:             menuActuel.menu_id,
            titre:               menuActuel.titre,
            prix_par_personne:   menuActuel.prix_par_personne,
            nombre_personne_mini:menuActuel.nombre_personne_mini,
            image:               document.getElementById('modal-img')?.src || '',
        });
        savePanier(panier);
    }
    majBtnPanier();
});


function attacherDetails() {
    document.querySelectorAll('.btn-details').forEach(btn => {
        btn.addEventListener('click', e => {
            e.preventDefault();
            ouvrirModal(btn.closest('.carte-plat'));
        });
    });
}
attacherDetails();

/* ── Chargement API ── */
chargerThemes();
chargerRegimes();
chargerMenus();

