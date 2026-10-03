/* Script de la page employe-dashboard.html */

/* ── Auth guard ── */
let user = {};
(async () => {
    const res = await fetch('/api/auth/me', { credentials: 'include' });
    if (!res.ok) { window.location.replace('employe.html'); return; }
    user = await res.json();
    if (user.role !== 'employe' && user.role !== 'administrateur') {
        alert('Accès réservé aux employés.');
        window.location.replace('employe.html');
        return;
    }
    document.getElementById('sb-prenom').textContent = user.prenom || user.email || '';
    if (user.role === 'administrateur') {
        document.querySelector('.emp-badge').textContent = 'Administrateur';
    }
    chargerKpis();
})();

const e = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');

const HEADERS = { 'Content-Type': 'application/json' };

/* ── Navigation sections ── */
const TITRES = {
    accueil:   ['Accueil',              'Vue d\'ensemble de votre activité'],
    commandes: ['Gestion des commandes','Changez les statuts et suivez les prestations'],
    avis:      ['Avis clients',         'Validez ou refusez les avis déposés'],
    stock:     ['Gestion des stocks',   'Mettez à jour les disponibilités des menus'],
};

/* ── BURGER SIDEBAR (mobile) ── */
const empBurger   = document.getElementById('emp-burger');
const empSidebar  = document.querySelector('.emp-sidebar');
const empBackdrop = document.getElementById('emp-backdrop');
function toggleEmpSidebar(open) {
    const isOpen = open ?? !empSidebar.classList.contains('open');
    empSidebar.classList.toggle('open', isOpen);
    empBackdrop.classList.toggle('open', isOpen);
    empBurger.classList.toggle('active', isOpen);
    empBurger.setAttribute('aria-expanded', isOpen);
}
empBurger.addEventListener('click', () => toggleEmpSidebar());
empBackdrop.addEventListener('click', () => toggleEmpSidebar(false));

function changerSection(id, btn) {
    document.querySelectorAll('.emp-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.emp-nav-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('section-' + id).classList.add('active');
    btn.classList.add('active');
    document.getElementById('page-titre').textContent = TITRES[id][0];
    document.getElementById('page-sous').textContent  = TITRES[id][1];
    if (window.innerWidth <= 900) toggleEmpSidebar(false);

    if (id === 'commandes') chargerCommandes();
    if (id === 'avis')      chargerAvis();
    if (id === 'stock')     chargerMenusStock();
}

async function deconnecter() {
    await fetch('/api/auth/logout', { method: 'POST', credentials: 'include' });
    window.location.href = 'employe.html';
}

function afficherMessage(txt, ok = true) {
    const el = document.getElementById('emp-message');
    el.textContent = txt;
    el.className   = 'emp-message ' + (ok ? 'ok' : 'err');
    setTimeout(() => { el.className = 'emp-message'; }, 4000);
}

/* ── Statut helpers ── */
const STATUT_LABELS = {
    en_attente:           'En attente',
    'acceptée':           'Acceptée',
    en_preparation:       'En préparation',
    en_cours_livraison:   'En livraison',
    'livrée':             'Livrée',
    retour_materiel:      'Retour matériel',
    'terminée':           'Terminée',
    'annulée':            'Annulée',
};
const STATUT_CLASS = {
    en_attente:           'attente',
    'acceptée':           'acceptee',
    en_preparation:       'preparation',
    en_cours_livraison:   'livraison',
    'livrée':             'livree',
    retour_materiel:      'retour',
    'terminée':           'terminee',
    'annulée':            'annulee',
};
const TRANSITIONS = {
    en_attente:           [['acceptée', 'Accepter', 'green'], ['annulée', 'Refuser', 'red']],
    'acceptée':           [['en_preparation', 'Lancer préparation', '']],
    en_preparation:       [['en_cours_livraison', 'En livraison', '']],
    en_cours_livraison:   [['livrée', 'Marquer livrée', 'or']],
    'livrée':             [['retour_materiel', 'Retour matériel', '']],
    retour_materiel:      [['terminée', 'Clôturer', 'or']],
    'terminée':           [],
    'annulée':            [],
};

function badgeStatut(s) {
    return `<span class="statut-badge statut-badge--${STATUT_CLASS[s] || 'attente'}">${STATUT_LABELS[s] || s}</span>`;
}

function boutonsTransition(cmd) {
    const trans = TRANSITIONS[cmd.statut_commande] || [];
    if (!trans.length) return '—';
    return '<div class="btn-emp-action-group">' +
        trans.map(([dest, label, variant]) =>
            `<button class="btn-emp-action${variant ? ' btn-emp-action--' + variant : ''}"
                data-action="changerStatut" data-args="${dataArgs(cmd.commande_id, dest, cmd.statut_commande)}">${label}</button>`
        ).join('') +
    '</div>';
}

/* ── Commandes ── */
let cmdCache = [];

async function chargerCommandes() {
    const q      = document.getElementById('f-cmd-q').value.trim();
    const statut = document.getElementById('f-cmd-statut').value;
    const params = new URLSearchParams();
    if (q)      params.set('q',      q);
    if (statut) params.set('statut', statut);

    const tb = document.getElementById('tb-commandes');
    tb.innerHTML = '<tr><td colspan="8" class="emp-table-vide">Chargement…</td></tr>';

    try {
        const res  = await fetch('/api/commandes?' + params, { credentials: 'include', headers: HEADERS });
        const data = res.ok ? await res.json() : [];
        cmdCache   = Array.isArray(data) ? data : (data.commandes || []);
    } catch {
        cmdCache = COMMANDES_FALLBACK.filter(c => (!statut || c.statut_commande === statut));
    }

    remplirTableauCommandes(cmdCache, 'tb-commandes', 8);
    document.getElementById('cmd-tableau-titre').textContent =
        cmdCache.length + ' commande' + (cmdCache.length > 1 ? 's' : '');
}

function remplirTableauCommandes(liste, tbId, colspan) {
    const tb = document.getElementById(tbId);
    if (!liste.length) {
        tb.innerHTML = `<tr><td colspan="${colspan}" class="emp-table-vide">Aucune commande trouvée.</td></tr>`;
        return;
    }
    tb.innerHTML = liste.map(c => `
        <tr>
            <td><strong>#${c.commande_id}</strong></td>
            <td>${e(c.prenom)} ${e(c.nom)}<br><small style="color:var(--brun)">${e(c.email)}</small></td>
            <td>${e(c.titre_menu || c.menu_titre) || '—'}</td>
            <td>${c.nombre_personne || '—'}</td>
            <td>${formatDate(c.date_prestation)}</td>
            <td style="font-size:12px; max-width:160px;">${e(c.adresse_livraison) || '—'}</td>
            <td>${badgeStatut(c.statut_commande)}${c.retour_materiel_en_retard ? ` <span class="statut-badge" style="background:#fee2e2;color:#991b1b;font-weight:700" title="Matériel non rendu depuis ${c.jours_depuis_retour} jours (retard > 10 j)">⚠ ${c.jours_depuis_retour}j</span>` : ''}</td>
            <td>${boutonsTransition(c)}</td>
        </tr>`).join('');
}

async function changerStatut(id, nouveauStatut, ancienStatut) {
    if (nouveauStatut === 'annulée') {
        ouvrirModalAnnulation(id);
        return;
    }
    try {
        const res = await fetch(`/api/commandes/${id}/statut`, {
            method: 'PUT', credentials: 'include', headers: HEADERS,
            body: JSON.stringify({ statut: nouveauStatut })
        });
        const data = await res.json();
        if (res.ok) {
            afficherMessage(`Commande #${id} → ${STATUT_LABELS[nouveauStatut]}`);
            chargerCommandes();
            chargerKpis();
        } else {
            afficherMessage(data.error || 'Erreur lors du changement.', false);
        }
    } catch {
        /* fallback dev */
        const c = cmdCache.find(x => x.commande_id === id);
        if (c) { c.statut_commande = nouveauStatut; remplirTableauCommandes(cmdCache, 'tb-commandes', 8); }
        afficherMessage(`Statut mis à jour (mode hors-ligne).`);
    }
}

/* ── Modal annulation ── */
let cmdIdAnnuler = null;
function ouvrirModalAnnulation(id) {
    cmdIdAnnuler = id;
    document.getElementById('motif-annulation').value = '';
    document.getElementById('mode-contact-annulation').value = '';
    document.getElementById('modal-annulation').classList.add('open');
}
function fermerModalAnnulation() {
    cmdIdAnnuler = null;
    document.getElementById('modal-annulation').classList.remove('open');
}
document.getElementById('btn-confirmer-annulation').addEventListener('click', async () => {
    const motif       = document.getElementById('motif-annulation').value.trim();
    const modeContact = document.getElementById('mode-contact-annulation').value;
    if (!modeContact) { alert('Veuillez indiquer comment le client a été contacté.'); return; }
    if (!motif) { alert('Veuillez indiquer le motif d\'annulation.'); return; }
    try {
        const res = await fetch(`/api/commandes/${cmdIdAnnuler}/statut`, {
            method: 'PUT', credentials: 'include', headers: HEADERS,
            body: JSON.stringify({ statut: 'annulée', motif, mode_contact: modeContact })
        });
        const data = await res.json();
        if (res.ok) {
            afficherMessage(`Commande #${cmdIdAnnuler} annulée.`);
        } else {
            afficherMessage(data.error || 'Erreur.', false);
        }
    } catch {
        afficherMessage('Annulation enregistrée (mode hors-ligne).');
    }
    fermerModalAnnulation();
    chargerCommandes(); chargerKpis();
});

/* ── Avis ── */
async function chargerAvis() {
    const statut = document.getElementById('f-avis-statut').value;
    const tb     = document.getElementById('tb-avis');
    tb.innerHTML = '<tr><td colspan="7" class="emp-table-vide">Chargement…</td></tr>';

    let avis = [];
    try {
        const res = await fetch(`/api/admin/avis?statut=${encodeURIComponent(statut)}`, { credentials: 'include', headers: HEADERS });
        avis = res.ok ? await res.json() : [];
        if (!Array.isArray(avis)) avis = avis.avis || [];
    } catch {
        avis = AVIS_FALLBACK.filter(a => a.statut_validation === statut);
    }

    if (!avis.length) {
        tb.innerHTML = '<tr><td colspan="7" class="emp-table-vide">Aucun avis.</td></tr>';
        return;
    }
    tb.innerHTML = avis.map(a => `
        <tr>
            <td>${e(a.prenom)} ${e(a.nom)}</td>
            <td>${e(a.titre_menu || a.menu) || '—'}</td>
            <td><span class="note-etoiles">${'★'.repeat(a.note || 0)}${'☆'.repeat(5 - (a.note || 0))}</span> <small>${a.note || 0}/5</small></td>
            <td style="max-width:240px; font-size:12px;">${e(a.description) || '—'}</td>
            <td style="font-size:12px;">${formatDate(a.date_avis)}</td>
            <td>${badgeAvis(a.statut_validation)}</td>
            <td>
                ${a.statut_validation === 'en_attente' ? `
                <div class="btn-emp-action-group">
                    <button class="btn-emp-action btn-emp-action--green" data-action="validerAvis" data-args="[${a.avis_id}, true]">Valider</button>
                    <button class="btn-emp-action btn-emp-action--red"   data-action="validerAvis" data-args="[${a.avis_id}, false]">Refuser</button>
                </div>` : '—'}
            </td>
        </tr>`).join('');
}

function badgeAvis(s) {
    const map = { en_attente: ['#fef3c7','#92400e'], 'validé': ['#d1fae5','#065f46'], 'refusé': ['#fee2e2','#991b1b'] };
    const [bg, c] = map[s] || ['#f0f0f0','#555'];
    return `<span class="statut-badge" style="background:${bg};color:${c}">${s}</span>`;
}

function badgeStatut(s) {
    const map = { en_attente: ['#fef3c7','#92400e'], 'approuvé': ['#d1fae5','#065f46'], 'refusé': ['#fee2e2','#991b1b'] };
    const [bg, c] = map[s] || ['#f0f0f0','#555'];
    return `<span class="statut-badge" style="background:${bg};color:${c}">${s}</span>`;
}

async function validerAvis(id, valider) {
    try {
        const res  = await fetch(`/api/avis/${id}/validation`, {
            method: 'PUT', credentials: 'include', headers: HEADERS,
            body: JSON.stringify({ statut: valider ? 'validé' : 'refusé' })
        });
        const data = await res.json();
        if (res.ok) {
            afficherMessage(`Avis ${valider ? 'validé' : 'refusé'}.`);
        } else {
            afficherMessage(data.error || 'Erreur.', false);
        }
    } catch {
        afficherMessage(`Avis ${valider ? 'validé' : 'refusé'} (mode hors-ligne).`);
    }
    chargerAvis();
    chargerKpis();
}

/* ── KPIs accueil ── */
async function chargerKpis() {
    let toutes = [];
    try {
        const res = await fetch('/api/commandes', { credentials: 'include', headers: HEADERS });
        toutes = res.ok ? await res.json() : [];
        if (!Array.isArray(toutes)) toutes = toutes.commandes || [];
    } catch { toutes = COMMANDES_FALLBACK; }

    const now   = new Date();
    const mois  = now.getMonth(); const an = now.getFullYear();

    document.getElementById('kpi-attente').textContent      = toutes.filter(c => c.statut_commande === 'en_attente').length;
    document.getElementById('kpi-preparation').textContent  = toutes.filter(c => c.statut_commande === 'en_preparation').length;
    document.getElementById('kpi-livraison').textContent    = toutes.filter(c => c.statut_commande === 'en_cours_livraison').length;
    document.getElementById('kpi-terminees').textContent    = toutes.filter(c => {
        const d = new Date(c.date_commande || c.date_prestation || '');
        return c.statut_commande === 'terminée' && d.getMonth() === mois && d.getFullYear() === an;
    }).length;

    /* Badge sidebar */
    const nbAttente = toutes.filter(c => c.statut_commande === 'en_attente').length;
    const bdg = document.getElementById('badge-cmd');
    bdg.hidden      = nbAttente === 0;
    bdg.textContent = nbAttente;

    /* Table accueil */
    const enAttente = toutes.filter(c => c.statut_commande === 'en_attente').slice(0, 5);
    remplirTableauCommandes(enAttente, 'tb-accueil-attente', 6);

    /* Badge avis */
    let avisEn = 0;
    try {
        const ra = await fetch('/api/admin/avis?statut=en_attente', { credentials: 'include', headers: HEADERS });
        const da = ra.ok ? await ra.json() : [];
        avisEn   = Array.isArray(da) ? da.length : (da.avis || []).length;
    } catch { avisEn = AVIS_FALLBACK.filter(a => a.statut_validation === 'en_attente').length; }
    const bdgA = document.getElementById('badge-avis');
    bdgA.hidden      = avisEn === 0;
    bdgA.textContent = avisEn;
}

/* ── Helpers ── */
function formatDate(iso) {
    if (!iso) return '—';
    try { return new Date(iso).toLocaleDateString('fr-FR', { day: '2-digit', month: 'short', year: 'numeric' }); }
    catch { return iso; }
}

/* ── Données de fallback (hors-ligne / dev) ── */
const COMMANDES_FALLBACK = [
    { commande_id:1, prenom:'Jean', nom:'Martin',  email:'jean.martin@email.fr', titre_menu:'Menu Prestige Bordeaux', nombre_personne:25, date_prestation:'2026-08-15', adresse_livraison:'12 rue des Chartrons, Bordeaux', statut_commande:'en_attente',        date_commande:'2026-06-01' },
    { commande_id:2, prenom:'Sophie', nom:'Bernard', email:'sophie.b@email.fr',  titre_menu:'Menu Saveurs d\'Été',    nombre_personne:12, date_prestation:'2026-07-20', adresse_livraison:'5 allée de la Tour, Bordeaux',    statut_commande:'acceptée',          date_commande:'2026-06-05' },
    { commande_id:3, prenom:'Pierre', nom:'Dupont', email:'pierre.d@email.fr',   titre_menu:'Menu Végétarien',        nombre_personne:8,  date_prestation:'2026-07-05', adresse_livraison:'Avenue Thiers, Bordeaux',          statut_commande:'en_preparation',    date_commande:'2026-05-20' },
    { commande_id:4, prenom:'Marie',  nom:'Leblanc', email:'marie@email.fr',     titre_menu:'Menu Tradition Girondine',nombre_personne:15, date_prestation:'2026-07-01', adresse_livraison:'Rue Sainte-Catherine, Bordeaux',  statut_commande:'en_cours_livraison',date_commande:'2026-05-10' },
    { commande_id:5, prenom:'Luc',    nom:'Fontaine', email:'luc@email.fr',      titre_menu:'Menu Gastronomique',     nombre_personne:30, date_prestation:'2026-06-28', adresse_livraison:'Cours du Chapeau Rouge, Bordeaux', statut_commande:'terminée',          date_commande:'2026-05-01' },
];
const AVIS_FALLBACK = [
    { avis_id:1, prenom:'Jean',   nom:'Martin',  titre_menu:'Menu Prestige',    note:5, description:'Excellent service, équipe au top !',   date_avis:'2026-06-20', statut_validation:'en_attente' },
    { avis_id:2, prenom:'Sophie', nom:'Bernard', titre_menu:'Menu Été',         note:4, description:'Très bon repas, livraison ponctuelle.', date_avis:'2026-06-15', statut_validation:'en_attente' },
    { avis_id:3, prenom:'Pierre', nom:'Dupont',  titre_menu:'Menu Végétarien',  note:5, description:'Parfait pour notre événement.',         date_avis:'2026-06-10', statut_validation:'validé'     },
];
/* ── STOCK — Mise à jour des disponibilités ── */
let stockCache = [];

async function chargerMenusStock() {
    const tb = document.getElementById('tb-stock');
    tb.innerHTML = '<tr><td colspan="6" class="emp-table-vide">Chargement…</td></tr>';
    try {
        const res   = await fetch('/api/menus', { credentials: 'include', headers: HEADERS });
        stockCache  = res.ok ? await res.json() : [];
        if (!Array.isArray(stockCache)) stockCache = stockCache.menus || [];
        if (!stockCache.length) { tb.innerHTML = '<tr><td colspan="6" class="emp-table-vide">Aucun menu.</td></tr>'; return; }
        tb.innerHTML = stockCache.map(m => `
            <tr>
                <td><strong>${m.titre}</strong></td>
                <td style="font-size:12px">${m.theme || '—'}</td>
                <td>${parseFloat(m.prix_par_personne || 0).toFixed(2)} €</td>
                <td>
                    <span style="font-weight:700;color:${m.quantite_restante > 0 ? 'var(--bordeaux)' : '#ef4444'}">
                        ${m.quantite_restante ?? 0}
                    </span>
                    ${m.quantite_restante == 0 ? '<span style="font-size:11px;color:#ef4444;margin-left:4px">Complet</span>' : ''}
                </td>
                <td>
                    <div style="display:flex;align-items:center;gap:8px">
                        <button class="btn-emp-action" style="padding:4px 8px;font-size:13px" data-action="ajusterStock" data-args="[${m.menu_id}, -1]">−</button>
                        <input type="number" id="stock-input-${m.menu_id}" value="${m.quantite_restante ?? 0}" min="0"
                            style="width:70px;padding:5px 8px;border:1.5px solid #e5e0d5;border-radius:6px;font-size:13px;text-align:center">
                        <button class="btn-emp-action" style="padding:4px 8px;font-size:13px" data-action="ajusterStock" data-args="[${m.menu_id}, 1]">+</button>
                    </div>
                </td>
                <td><button class="btn-emp-action btn-emp-action--green" data-action="sauvegarderStock" data-args="[${m.menu_id}]">Enregistrer</button></td>
            </tr>`).join('');
    } catch { tb.innerHTML = '<tr><td colspan="6" class="emp-table-vide">API non disponible.</td></tr>'; }
}

function ajusterStock(menuId, delta) {
    const inp = document.getElementById(`stock-input-${menuId}`);
    inp.value = Math.max(0, (parseInt(inp.value) || 0) + delta);
}

async function sauvegarderStock(menuId) {
    const val = parseInt(document.getElementById(`stock-input-${menuId}`).value);
    if (isNaN(val) || val < 0) { afficherMessage('Valeur invalide.', false); return; }
    try {
        const res = await fetch(`/api/menus/${menuId}`, {
            method: 'PUT', credentials: 'include', headers: HEADERS,
            body: JSON.stringify({ quantite_restante: val })
        });
        const d = await res.json();
        afficherMessage(res.ok ? `Stock mis à jour : ${val} prestation${val > 1 ? 's' : ''}` : (d.error || 'Erreur.'), res.ok);
        if (res.ok) chargerMenusStock();
    } catch { afficherMessage('API non disponible.', false); }
}

/* ── Filtres commandes temps réel ── */
document.getElementById('f-cmd-q').addEventListener('input', () => {
    const q = document.getElementById('f-cmd-q').value.toLowerCase();
    const filtered = cmdCache.filter(c =>
        (c.prenom + ' ' + c.nom + ' ' + (c.titre_menu || '') + ' ' + c.email).toLowerCase().includes(q)
    );
    remplirTableauCommandes(filtered, 'tb-commandes', 8);
});
document.getElementById('f-cmd-statut').addEventListener('change', chargerCommandes);
document.getElementById('f-avis-statut').addEventListener('change', chargerAvis);

/* ── Liaison des boutons (remplace les anciens attributs onclick du HTML) ── */
document.querySelectorAll('.emp-nav-btn').forEach(btn =>
    btn.addEventListener('click', () => changerSection(btn.dataset.section, btn))
);

/** Raccourci « Voir toutes » : même effet qu'un clic sur l'onglet de la section. */
function allerSection(id) {
    document.querySelector(`.emp-nav-btn[data-section="${id}"]`).click();
}

bindActions({
    allerSection, deconnecter,
    chargerCommandes, changerStatut, fermerModalAnnulation,
    chargerAvis, validerAvis,
    chargerMenusStock, ajusterStock, sauvegarderStock,
});
