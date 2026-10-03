/* Script de la page admin-dashboard.html */

/* ── Auth guard ── */
(async () => {
    const res = await fetch('/api/auth/me', { credentials: 'include' });
    if (!res.ok) { window.location.replace('admin.html'); return; }
    const user = await res.json();
    if (user.role !== 'administrateur') {
        alert('Accès réservé aux administrateurs.');
        window.location.replace('admin.html');
        return;
    }
    document.getElementById('sb-prenom').textContent = user.prenom ? user.prenom + ' — Admin' : '';
    chargerDashboard();
})();

const H = { 'Content-Type': 'application/json' };
const e = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');

/* ── BURGER SIDEBAR (mobile) ── */
const adminBurger   = document.getElementById('admin-burger');
const adminSidebar  = document.querySelector('.admin-sidebar');
const adminBackdrop = document.getElementById('admin-backdrop');
function toggleAdminSidebar(open) {
    const isOpen = open ?? !adminSidebar.classList.contains('open');
    adminSidebar.classList.toggle('open', isOpen);
    adminBackdrop.classList.toggle('open', isOpen);
    adminBurger.classList.toggle('active', isOpen);
    adminBurger.setAttribute('aria-expanded', isOpen);
}
adminBurger.addEventListener('click', () => toggleAdminSidebar());
adminBackdrop.addEventListener('click', () => toggleAdminSidebar(false));

/* ── Navigation ── */
function nav(id, titre, sous, btn) {
    document.querySelectorAll('.admin-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.admin-nav-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('section-' + id).classList.add('active');
    btn.classList.add('active');
    document.getElementById('page-titre').textContent = titre;
    document.getElementById('page-sous').textContent  = sous;
    if (window.innerWidth <= 768) toggleAdminSidebar(false);

    const loaders = { menus: chargerMenus, plats: chargerPlats, themes: chargerThemes, horaires: chargerHoraires, commandes: chargerCommandes, avis: chargerAvis, utilisateurs: chargerUtilisateurs, employes: chargerEmployes };
    if (loaders[id]) loaders[id]();
}

async function deconnecter() {
    await fetch('/api/auth/logout', { method: 'POST', credentials: 'include' });
    window.location.href = 'admin.html';
}

function flash(msg, ok = true) {
    const el = document.getElementById('ad-flash');
    el.textContent = msg; el.className = 'ad-flash ' + (ok ? 'ok' : 'err');
    setTimeout(() => { el.className = 'ad-flash'; }, 4000);
}

/* ── Helpers formatage ── */
function fmtDate(iso) { if (!iso) return '—'; try { return new Date(iso).toLocaleDateString('fr-FR', { day:'2-digit', month:'short', year:'numeric' }); } catch { return iso; } }
function fmtPrix(n)  { return n !== null && n !== undefined ? parseFloat(n).toFixed(2).replace('.',',') + ' €' : '—'; }

const STATUT_LABELS = { en_attente:'En attente', 'acceptée':'Acceptée', en_preparation:'En préparation', en_cours_livraison:'En livraison', 'livrée':'Livrée', retour_materiel:'Retour matériel', 'terminée':'Terminée', 'annulée':'Annulée' };
const STATUT_CLS    = { en_attente:'attente', 'acceptée':'acceptee', en_preparation:'preparation', en_cours_livraison:'livraison', 'livrée':'livree', retour_materiel:'retour', 'terminée':'terminee', 'annulée':'annulee' };
function sbCmd(s)  { return `<span class="sb sb-${STATUT_CLS[s]||'attente'}">${STATUT_LABELS[s]||s}</span>`; }
function sbRole(r) { const m={'administrateur':'sb-admin','employe':'sb-employe','client':'sb-client'}; return `<span class="sb ${m[r]||'sb-client'}">${r}</span>`; }
function sbActif(v){ return `<span class="sb ${v?'sb-actif':'sb-inactif'}">${v?'Actif':'Inactif'}</span>`; }
function sbAvis(s) { const m={'validé':'sb-valide','refusé':'sb-refuse','en_attente':'sb-en_attente'}; return `<span class="sb ${m[s]||'sb-en_attente'}">${s}</span>`; }

/* ── TABLEAU DE BORD ── */
async function chargerDashboard() {
    try {
        const res  = await fetch('/api/admin/stats', { credentials: 'include', headers: H });
        if (!res.ok) throw new Error();
        const data = await res.json();

        document.getElementById('kpi-attente').textContent = data.globales?.en_attente ?? '—';
        document.getElementById('kpi-ca').textContent      = data.globales?.ca_total ? parseFloat(data.globales.ca_total).toFixed(0) : '—';
        document.getElementById('kpi-total').textContent   = data.globales?.total_commandes ?? '—';
        document.getElementById('kpi-clients').textContent = data.nb_clients ?? '—';

        /* Chiffre d'affaires par menu (MySQL) */
        const tbTop = document.getElementById('tb-top-menus');
        if (data.ca_par_menu?.length) {
            tbTop.innerHTML = data.ca_par_menu.map((m, i) => `
                <tr>
                    <td>${i + 1}</td>
                    <td><strong>${m.titre}</strong></td>
                    <td>${m.nb}</td>
                    <td>${fmtPrix(m.ca_menu)}</td>
                </tr>`).join('');
        } else { tbTop.innerHTML = '<tr><td colspan="4" class="admin-vide">Aucune donnée.</td></tr>'; }

        /* Commandes par menu (MongoDB) — graphique en barres */
        const chart = document.getElementById('chart-commandes-par-menu');
        if (data.commandes_par_menu?.length) {
            const max = Math.max(...data.commandes_par_menu.map(m => m.nb_commandes));
            chart.innerHTML = data.commandes_par_menu.map(m => {
                const pct = max > 0 ? Math.round((m.nb_commandes / max) * 100) : 0;
                return `
                    <div>
                        <div style="display:flex;justify-content:space-between;font-size:13px;margin-bottom:4px">
                            <span>${e(m.menu_titre)}</span><strong>${m.nb_commandes}</strong>
                        </div>
                        <div style="background:#f0ebe0;border-radius:6px;height:14px;overflow:hidden">
                            <div style="background:var(--bordeaux);height:100%;width:${pct}%"></div>
                        </div>
                    </div>`;
            }).join('');
        } else {
            chart.innerHTML = '<p style="color:var(--brun);font-size:13px">Aucune donnée (MongoDB non configuré ?).</p>';
        }

    } catch {
        ['kpi-attente', 'kpi-ca', 'kpi-total', 'kpi-clients'].forEach(id => {
            document.getElementById(id).textContent = '—';
        });
        document.getElementById('tb-top-menus').innerHTML = '<tr><td colspan="4" class="admin-vide">Impossible de charger les statistiques.</td></tr>';
        document.getElementById('chart-commandes-par-menu').innerHTML = '<p style="color:var(--brun);font-size:13px">Impossible de charger les statistiques.</p>';
    }

    /* Dernières commandes */
    await chargerDernieresCommandes();
}

async function chargerDernieresCommandes() {
    const tb = document.getElementById('tb-dashboard-cmds');
    try {
        const res = await fetch('/api/commandes', { credentials: 'include', headers: H });
        const cmds = res.ok ? await res.json() : [];
        const liste = Array.isArray(cmds) ? cmds : (cmds.commandes || []);
        remplirTableauCommandes(liste.slice(0, 8), 'tb-dashboard-cmds', 7, false);

        const nbAttente = liste.filter(c => c.statut_commande === 'en_attente').length;
        const bdg = document.getElementById('badge-cmd');
        bdg.hidden = nbAttente === 0; bdg.textContent = nbAttente;
    } catch {
        tb.innerHTML = '<tr><td colspan="7" class="admin-vide">API non connectée.</td></tr>';
    }
}

/* ── MENUS ── */
async function chargerMenus() {
    const tb = document.getElementById('tb-menus');
    try {
        const res   = await fetch('/api/menus', { credentials: 'include', headers: H });
        const menus = res.ok ? await res.json() : [];
        document.getElementById('menus-titre').textContent = `Menus (${menus.length})`;
        if (!menus.length) { tb.innerHTML = '<tr><td colspan="7" class="admin-vide">Aucun menu.</td></tr>'; return; }
        tb.innerHTML = menus.map(m => `
            <tr>
                <td>${m.menu_id}</td>
                <td><strong>${m.titre}</strong></td>
                <td>${fmtPrix(m.prix_par_personne)}</td>
                <td>${m.nombre_personne_mini}</td>
                <td>
                    <span style="font-weight:600;color:${m.quantite_restante > 0 ? 'var(--bordeaux)' : '#ef4444'}">${m.quantite_restante ?? 0}</span>
                </td>
                <td>${m.theme || '—'}</td>
                <td><div class="btn-ad-group">
                    <button class="btn-ad" data-action="ouvrirModalStock" data-args="${dataArgs(m.menu_id, m.titre, m.quantite_restante ?? 0)}">Stock</button>
                    <button class="btn-ad" data-action="ouvrirModalPhotos" data-args="${dataArgs(m.menu_id, m.titre)}">Photos</button>
                    <button class="btn-ad btn-ad--red" data-action="supprimerMenu" data-args="${dataArgs(m.menu_id, m.titre)}">Supprimer</button>
                </div></td>
            </tr>`).join('');
    } catch { tb.innerHTML = '<tr><td colspan="7" class="admin-vide">API non connectée.</td></tr>'; }
}

async function supprimerMenu(id, titre) {
    if (!confirm(`Supprimer le menu "${titre}" ? Cette action est irréversible.`)) return;
    try {
        const res = await fetch('/api/menus/' + id, { method: 'DELETE', credentials: 'include', headers: H });
        flash(res.ok ? 'Menu supprimé.' : 'Erreur lors de la suppression.', res.ok);
        chargerMenus();
    } catch { flash('API non disponible.', false); }
}

/* ── PLATS ── */
async function chargerPlats() {
    const tb = document.getElementById('tb-plats');
    try {
        const res   = await fetch('/api/plats', { credentials: 'include', headers: H });
        const plats = res.ok ? await res.json() : [];
        document.getElementById('plats-titre').textContent = `Plats (${plats.length})`;
        if (!plats.length) { tb.innerHTML = '<tr><td colspan="5" class="admin-vide">Aucun plat.</td></tr>'; return; }
        tb.innerHTML = plats.map(p => `
            <tr>
                <td>${p.plat_id}</td>
                <td><strong>${p.titre_plat}</strong></td>
                <td>${p.type_plat || '—'}</td>
                <td>${fmtPrix(p.prix_unitaire)}</td>
                <td style="font-size:12px;color:var(--brun);max-width:200px">${p.description ? p.description.slice(0,80) + (p.description.length > 80 ? '…' : '') : '—'}</td>
                <td><div class="btn-ad-group">
                    <button class="btn-ad" data-action="ouvrirModalPlat" data-args="${dataArgs(p.plat_id, p.titre_plat, p.type_plat || 'Plat', Number(p.prix_unitaire), p.description || '')}">Modifier</button>
                    <button class="btn-ad btn-ad--red" data-action="supprimerPlat" data-args="${dataArgs(p.plat_id, p.titre_plat)}">Supprimer</button>
                </div></td>
            </tr>`).join('');
    } catch { tb.innerHTML = '<tr><td colspan="5" class="admin-vide">API non connectée.</td></tr>'; }
}

async function supprimerPlat(id, titre) {
    if (!confirm(`Supprimer le plat "${titre}" ?`)) return;
    try {
        const res = await fetch('/api/plats/' + id, { method: 'DELETE', credentials: 'include', headers: H });
        flash(res.ok ? 'Plat supprimé.' : 'Erreur.', res.ok);
        chargerPlats();
    } catch { flash('API non disponible.', false); }
}

/* ── THÈMES ── */
async function chargerThemes() {
    const tb = document.getElementById('tb-themes');
    try {
        const res    = await fetch('/api/themes', { credentials: 'include', headers: H });
        const themes = res.ok ? await res.json() : [];
        if (!themes.length) { tb.innerHTML = '<tr><td colspan="3" class="admin-vide">Aucun thème.</td></tr>'; return; }
        tb.innerHTML = themes.map(t => `
            <tr>
                <td>${t.theme_id}</td>
                <td>${t.libelle}</td>
                <td><button class="btn-ad btn-ad--red" data-action="supprimerTheme" data-args="${dataArgs(t.theme_id, t.libelle)}">Supprimer</button></td>
            </tr>`).join('');
    } catch { tb.innerHTML = '<tr><td colspan="3" class="admin-vide">API non connectée.</td></tr>'; }
}

async function ajouterTheme() {
    const libelle = prompt('Nom du thème (ex : Mariage, Noël…)');
    if (!libelle?.trim()) return;
    try {
        const res = await fetch('/api/themes', { method: 'POST', credentials: 'include', headers: H, body: JSON.stringify({ libelle: libelle.trim() }) });
        flash(res.ok ? `Thème "${libelle}" ajouté.` : 'Erreur.', res.ok);
        chargerThemes();
    } catch { flash('API non disponible.', false); }
}

async function supprimerTheme(id, libelle) {
    if (!confirm(`Supprimer le thème "${libelle}" ?`)) return;
    try {
        const res = await fetch('/api/themes/' + id, { method: 'DELETE', credentials: 'include', headers: H });
        flash(res.ok ? 'Thème supprimé.' : 'Erreur.', res.ok);
        chargerThemes();
    } catch { flash('API non disponible.', false); }
}

/* ── COMMANDES ── */
let cmdCache = [];

async function chargerCommandes() {
    const q      = document.getElementById('f-cmd-q').value.trim();
    const statut = document.getElementById('f-cmd-statut').value;
    const params = new URLSearchParams();
    if (q) params.set('q', q); if (statut) params.set('statut', statut);
    try {
        const res   = await fetch('/api/commandes?' + params, { credentials: 'include', headers: H });
        const data  = await res.json();
        cmdCache    = Array.isArray(data) ? data : (data.commandes || []);
    } catch {
        cmdCache = CMD_FALLBACK.filter(c => !statut || c.statut_commande === statut);
    }
    remplirTableauCommandes(cmdCache, 'tb-commandes', 9, true);
    document.getElementById('cmd-total-lbl').textContent = `${cmdCache.length} commande${cmdCache.length > 1 ? 's' : ''}`;
}

function remplirTableauCommandes(liste, tbId, colspan, avecActions) {
    const tb = document.getElementById(tbId);
    if (!liste.length) { tb.innerHTML = `<tr><td colspan="${colspan}" class="admin-vide">Aucune commande.</td></tr>`; return; }
    tb.innerHTML = liste.map(c => `
        <tr>
            <td><strong>#${c.commande_id}</strong></td>
            <td>${e(c.prenom)} ${e(c.nom)}<br><small style="color:var(--brun)">${e(c.email)}</small></td>
            <td>${e(c.titre_menu || c.menu_titre) || '—'}</td>
            <td>${c.nombre_personne}</td>
            <td>${fmtDate(c.date_prestation)}</td>
            ${avecActions ? `<td style="font-size:12px;max-width:140px">${e(c.adresse_livraison) || '—'}</td>` : ''}
            <td>${fmtPrix(c.prix_commande)}</td>
            <td>${sbCmd(c.statut_commande)}${c.retour_materiel_en_retard ? ` <span class="sb" style="background:#fee2e2;color:#991b1b;font-weight:700" title="Matériel non rendu depuis ${c.jours_depuis_retour} jours (retard > 10 j)">⚠ ${c.jours_depuis_retour}j</span>` : ''}</td>
            ${avecActions ? `<td>
                <div class="btn-ad-group">
                    <select class="admin-filtre-bar select" id="sel-${c.commande_id}" style="padding:4px 8px;font-size:11px;border-radius:4px;border:1px solid #ddd">
                        ${Object.entries(STATUT_LABELS).map(([v,l]) => `<option value="${v}"${v===c.statut_commande?' selected':''}>${l}</option>`).join('')}
                    </select>
                    <button class="btn-ad" data-action="changerStatut" data-args="[${c.commande_id}]">OK</button>
                </div>
            </td>` : ''}
        </tr>`).join('');
}

async function changerStatut(id) {
    const sel     = document.getElementById('sel-' + id);
    const statut  = sel.value;
    const body    = { statut };

    if (statut === 'annulée') {
        const modeContact = prompt('Comment le client a-t-il été contacté avant l\'annulation ? (Téléphone / Email)');
        if (!modeContact) return;
        const motif = prompt('Motif de l\'annulation (visible par le client) :');
        if (!motif) return;
        body.mode_contact = modeContact;
        body.motif = motif;
    }

    try {
        const res = await fetch(`/api/commandes/${id}/statut`, { method: 'PUT', credentials: 'include', headers: H, body: JSON.stringify(body) });
        const d   = await res.json();
        flash(res.ok ? `Commande #${id} → ${STATUT_LABELS[statut]}` : (d.error || 'Erreur.'), res.ok);
        chargerCommandes();
    } catch { flash('API non disponible.', false); }
}

/* ── AVIS ── */
async function chargerAvis() {
    const statut = document.getElementById('f-avis-statut').value;
    const tb     = document.getElementById('tb-avis');
    try {
        const res  = await fetch(`/api/admin/avis?statut=${encodeURIComponent(statut)}`, { credentials: 'include', headers: H });
        const avis = res.ok ? (await res.json()) : [];
        const liste = Array.isArray(avis) ? avis : (avis.avis || []);

        const nbAtt = liste.filter(a => a.statut_validation === 'en_attente').length;
        const bdg   = document.getElementById('badge-avis');
        if (statut === 'en_attente' || !statut) { bdg.hidden = nbAtt === 0; bdg.textContent = nbAtt; }

        if (!liste.length) { tb.innerHTML = '<tr><td colspan="6" class="admin-vide">Aucun avis.</td></tr>'; return; }
        tb.innerHTML = liste.map(a => `
            <tr>
                <td>${e(a.prenom)} ${e(a.nom)}</td>
                <td><span style="color:var(--or)">${'★'.repeat(a.note || 0)}${'☆'.repeat(5-(a.note||0))}</span> ${a.note}/5</td>
                <td style="max-width:260px;font-size:12px">${e(a.description) || '—'}</td>
                <td style="font-size:12px">${fmtDate(a.date_avis)}</td>
                <td>${sbAvis(a.statut_validation)}</td>
                <td>
                    ${a.statut_validation === 'en_attente' ? `
                    <div class="btn-ad-group">
                        <button class="btn-ad btn-ad--green" data-action="validerAvis" data-args="${dataArgs(a.avis_id, 'validé')}">Valider</button>
                        <button class="btn-ad btn-ad--red"   data-action="validerAvis" data-args="${dataArgs(a.avis_id, 'refusé')}">Refuser</button>
                    </div>` : '—'}
                </td>
            </tr>`).join('');
    } catch { tb.innerHTML = '<tr><td colspan="6" class="admin-vide">API non connectée.</td></tr>'; }
}

async function validerAvis(id, statut) {
    try {
        const res = await fetch(`/api/avis/${id}/validation`, { method: 'PUT', credentials: 'include', headers: H, body: JSON.stringify({ statut }) });
        flash(res.ok ? `Avis ${statut}.` : 'Erreur.', res.ok);
        chargerAvis();
    } catch { flash('API non disponible.', false); }
}

/* ── UTILISATEURS ── */
async function chargerUtilisateurs() {
    const role = document.getElementById('f-user-role').value;
    const tb   = document.getElementById('tb-utilisateurs');
    try {
        const url   = '/api/admin/utilisateurs' + (role ? '?role=' + encodeURIComponent(role) : '');
        const res   = await fetch(url, { credentials: 'include', headers: H });
        const users = res.ok ? await res.json() : [];
        document.getElementById('users-total-lbl').textContent = `${users.length} utilisateur${users.length > 1 ? 's' : ''}`;
        if (!users.length) { tb.innerHTML = '<tr><td colspan="8" class="admin-vide">Aucun utilisateur.</td></tr>'; return; }
        tb.innerHTML = users.map(u => `
            <tr>
                <td>${u.utilisateur_id}</td>
                <td>${u.prenom || ''} ${u.nom || ''}</td>
                <td>${u.email}</td>
                <td>${u.ville || '—'}</td>
                <td>${sbRole(u.role)}</td>
                <td style="font-size:12px">${fmtDate(u.date_creation)}</td>
                <td>${sbActif(u.statut_compte)}</td>
                <td>
                    ${u.role !== 'administrateur' ? `
                    <button class="btn-ad" data-action="toggleCompte" data-args="[${u.utilisateur_id}, ${u.statut_compte ? 0 : 1}]">
                        ${u.statut_compte ? 'Désactiver' : 'Activer'}
                    </button>` : '—'}
                </td>
            </tr>`).join('');
    } catch { tb.innerHTML = '<tr><td colspan="8" class="admin-vide">API non connectée.</td></tr>'; }
}

async function toggleCompte(id, activer) {
    if (!confirm(activer ? 'Réactiver ce compte ?' : 'Désactiver ce compte ?')) return;
    const action = activer ? 'activer' : 'desactiver';
    try {
        const res = await fetch(`/api/admin/employes/${id}/${action}`, { method: 'PUT', credentials: 'include', headers: H });
        const d = await res.json();
        flash(res.ok ? `Compte ${activer ? 'activé' : 'désactivé'}.` : (d.error || 'Erreur.'), res.ok);
        if (res.ok) { chargerUtilisateurs(); chargerEmployes(); }
    } catch { flash('API non disponible.', false); }
}

/* ── EMPLOYÉS ── */
async function chargerEmployes() {
    const tb = document.getElementById('tb-employes');
    try {
        const res   = await fetch('/api/admin/utilisateurs?role=employe', { credentials: 'include', headers: H });
        const users = res.ok ? await res.json() : [];
        if (!users.length) { tb.innerHTML = '<tr><td colspan="7" class="admin-vide">Aucun employé.</td></tr>'; return; }
        tb.innerHTML = users.map(u => `
            <tr>
                <td>${u.utilisateur_id}</td>
                <td>${e(u.prenom)} ${e(u.nom)}</td>
                <td>${e(u.email)}</td>
                <td>${e(u.telephone) || '—'}</td>
                <td style="font-size:12px">${fmtDate(u.date_creation)}</td>
                <td>${sbActif(u.statut_compte)}</td>
                <td><button class="btn-ad ${u.statut_compte ? 'btn-ad--red' : 'btn-ad--green'}" data-action="toggleCompte" data-args="[${u.utilisateur_id}, ${u.statut_compte ? 0 : 1}]">
                    ${u.statut_compte ? 'Désactiver' : 'Réactiver'}
                </button></td>
            </tr>`).join('');
    } catch { tb.innerHTML = '<tr><td colspan="7" class="admin-vide">API non connectée.</td></tr>'; }
}

/* ── Modal créer employé ── */
function ouvrirModalEmploye() { document.getElementById('modal-employe').classList.add('open'); }
function fermerModalEmploye() { document.getElementById('modal-employe').classList.remove('open'); }
document.getElementById('modal-employe').addEventListener('click', e => { if (e.target === e.currentTarget) fermerModalEmploye(); });

async function creerEmploye() {
    const prenom = document.getElementById('emp-prenom').value.trim();
    const nom    = document.getElementById('emp-nom').value.trim();
    const email  = document.getElementById('emp-email').value.trim();
    const tel    = document.getElementById('emp-tel').value.trim();
    if (!prenom || !nom || !email || !tel) { alert('Tous les champs sont requis.'); return; }

    try {
        const res  = await fetch('/api/admin/employes', { method: 'POST', credentials: 'include', headers: H, body: JSON.stringify({ prenom, nom, email, telephone: tel }) });
        const data = await res.json();
        if (res.ok) {
            flash(`Employé ${e(prenom)} ${e(nom)} créé. Un email lui a été envoyé.`);
            fermerModalEmploye();
            chargerEmployes();
        } else { flash(data.error || 'Erreur lors de la création.', false); }
    } catch { flash('API non disponible.', false); }
}

/* ── MODAL PLAT ── */
function ouvrirModalPlat(id, titre, type, prix, desc) {
    document.getElementById('plat-edit-id').value  = id   ?? '';
    document.getElementById('plat-titre').value    = titre ?? '';
    document.getElementById('plat-type').value     = type  ?? 'Plat';
    document.getElementById('plat-prix').value     = prix  ?? '';
    document.getElementById('plat-desc').value     = desc  ?? '';
    document.getElementById('modal-plat-titre').textContent = id ? 'Modifier le plat' : 'Ajouter un plat';
    document.getElementById('modal-plat').classList.add('open');
}
function fermerModalPlat() { document.getElementById('modal-plat').classList.remove('open'); }
document.getElementById('modal-plat').addEventListener('click', e => { if (e.target === e.currentTarget) fermerModalPlat(); });

async function sauvegarderPlat() {
    const id    = document.getElementById('plat-edit-id').value;
    const titre = document.getElementById('plat-titre').value.trim();
    const type  = document.getElementById('plat-type').value;
    const prix  = parseFloat(document.getElementById('plat-prix').value);
    const desc  = document.getElementById('plat-desc').value.trim();
    if (!titre || !type || isNaN(prix)) { alert('Titre, type et prix sont requis.'); return; }

    const url    = id ? `/api/plats/${id}` : '/api/plats';
    const method = id ? 'PUT' : 'POST';
    try {
        const res = await fetch(url, { method, credentials: 'include', headers: H, body: JSON.stringify({ titre_plat: titre, type_plat: type, prix_unitaire: prix, description: desc }) });
        const d   = await res.json();
        flash(res.ok ? (id ? 'Plat modifié.' : 'Plat ajouté.') : (d.error || 'Erreur.'), res.ok);
        if (res.ok) { fermerModalPlat(); chargerPlats(); }
    } catch { flash('API non disponible.', false); }
}

/* ── MODAL STOCK MENU ── */
function ouvrirModalStock(id, titre, stock) {
    document.getElementById('stock-menu-id').value    = id;
    document.getElementById('stock-menu-nom').textContent = titre;
    document.getElementById('stock-valeur').value     = stock;
    document.getElementById('modal-stock').classList.add('open');
}
function fermerModalStock() { document.getElementById('modal-stock').classList.remove('open'); }
document.getElementById('modal-stock').addEventListener('click', e => { if (e.target === e.currentTarget) fermerModalStock(); });

async function sauvegarderStock() {
    const id    = document.getElementById('stock-menu-id').value;
    const stock = parseInt(document.getElementById('stock-valeur').value);
    if (isNaN(stock) || stock < 0) { alert('Valeur invalide.'); return; }
    try {
        const res = await fetch(`/api/menus/${id}`, { method: 'PUT', credentials: 'include', headers: H, body: JSON.stringify({ quantite_restante: stock }) });
        const d   = await res.json();
        flash(res.ok ? `Disponibilité mise à jour → ${stock} prestation${stock > 1 ? 's' : ''}.` : (d.error || 'Erreur.'), res.ok);
        if (res.ok) { fermerModalStock(); chargerMenus(); }
    } catch { flash('API non disponible.', false); }
}

/* ── MODAL AJOUTER MENU ── */
async function ouvrirModalMenu() {
    document.getElementById('menu-titre').value = '';
    document.getElementById('menu-desc').value = '';
    document.getElementById('menu-prix').value = '';
    document.getElementById('menu-min-pers').value = '';
    document.getElementById('menu-stock').value = '';
    document.getElementById('menu-delai').value = '7';
    document.getElementById('menu-conditions').value = '';

    const selTheme = document.getElementById('menu-theme');
    selTheme.innerHTML = '<option value="">— Aucun —</option>';
    try {
        const res    = await fetch('/api/themes', { credentials: 'include', headers: H });
        const themes = res.ok ? await res.json() : [];
        selTheme.innerHTML += themes.map(t => `<option value="${t.theme_id}">${t.libelle}</option>`).join('');
    } catch { /* thème restera vide si l'API ne répond pas */ }

    const listePlats = document.getElementById('menu-plats-liste');
    listePlats.innerHTML = 'Chargement…';
    try {
        const res   = await fetch('/api/plats', { credentials: 'include', headers: H });
        const plats = res.ok ? await res.json() : [];
        listePlats.innerHTML = plats.length ? plats.map(p => `
            <label style="display:flex;align-items:center;gap:8px;padding:4px 0;font-size:.85rem;cursor:pointer">
                <input type="checkbox" value="${p.plat_id}" class="menu-plat-check">
                ${p.titre_plat} <span style="color:var(--brun);font-size:.75rem">(${p.type_plat || '—'})</span>
            </label>`).join('') : 'Aucun plat disponible — créez-en dans l\'onglet Plats.';
    } catch { listePlats.innerHTML = 'API non disponible.'; }

    document.getElementById('modal-menu').classList.add('open');
}
function fermerModalMenu() { document.getElementById('modal-menu').classList.remove('open'); }
document.getElementById('modal-menu').addEventListener('click', e => { if (e.target === e.currentTarget) fermerModalMenu(); });

async function sauvegarderMenu() {
    const titre    = document.getElementById('menu-titre').value.trim();
    const desc     = document.getElementById('menu-desc').value.trim();
    const prix     = parseFloat(document.getElementById('menu-prix').value);
    const minPers  = parseInt(document.getElementById('menu-min-pers').value);
    const stock    = parseInt(document.getElementById('menu-stock').value);
    const delai    = parseInt(document.getElementById('menu-delai').value) || 7;
    const themeId  = document.getElementById('menu-theme').value;
    const conditions = document.getElementById('menu-conditions').value.trim();
    const plats    = [...document.querySelectorAll('.menu-plat-check:checked')].map(c => parseInt(c.value));

    if (!titre || !desc || isNaN(prix) || isNaN(minPers) || isNaN(stock)) {
        alert('Merci de remplir tous les champs obligatoires (*).');
        return;
    }
    if (!plats.length) {
        alert('Sélectionnez au moins un plat pour composer le menu.');
        return;
    }

    try {
        const res = await fetch('/api/menus', {
            method: 'POST', credentials: 'include', headers: H,
            body: JSON.stringify({
                titre, description: desc, prix_par_personne: prix,
                nombre_personne_mini: minPers, quantite_restante: stock,
                delai_prevenance: delai, theme_id: themeId || null,
                conditions: conditions || null, plats,
            }),
        });
        const d = await res.json();
        flash(res.ok ? 'Menu créé.' : (d.error || 'Erreur.'), res.ok);
        if (res.ok) { fermerModalMenu(); chargerMenus(); }
    } catch { flash('API non disponible.', false); }
}

/* ── MODAL PHOTOS MENU ── */
const IMAGES_DISPONIBLES = [
    'avocat_bowl.png', 'brochettes_crevettes.jpg', 'burger.png', 'creme_brulee.png',
    'filet_poulet.png', 'fondant_chocolat.png', 'macarons.png', 'magret_canard.png',
    'pizza.png', 'pizza_jambon_roquette.jpg', 'plateau_charcuterie.png',
    'poulet_mijote_epice.jpg', 'riz_saute.png', 'toasts_apero.jpg',
    'verrine_chocolat_groseille.jpg', 'wraps_orientaux.png',
];

function remplirSelectImages(select, valeurActuelle) {
    select.innerHTML = '<option value="">— Aucune —</option>' +
        IMAGES_DISPONIBLES.map(f => `<option value="images/${f}">${f}</option>`).join('');
    select.value = valeurActuelle || '';
}

async function ouvrirModalPhotos(id, titre) {
    document.getElementById('photos-menu-id').value = id;
    document.getElementById('photos-menu-nom').textContent = titre;
    const sel1 = document.getElementById('photos-img-1');
    const sel2 = document.getElementById('photos-img-2');
    remplirSelectImages(sel1, '');
    remplirSelectImages(sel2, '');
    try {
        const res  = await fetch(`/api/menus/${id}`, { credentials: 'include', headers: H });
        const menu = res.ok ? await res.json() : null;
        const imgs = menu?.images || [];
        if (imgs[0]) sel1.value = imgs[0];
        if (imgs[1]) sel2.value = imgs[1];
    } catch { /* champs restent vides si l'API ne répond pas */ }
    document.getElementById('modal-photos').classList.add('open');
}
function fermerModalPhotos() { document.getElementById('modal-photos').classList.remove('open'); }
document.getElementById('modal-photos').addEventListener('click', e => { if (e.target === e.currentTarget) fermerModalPhotos(); });

async function sauvegarderPhotos() {
    const id     = document.getElementById('photos-menu-id').value;
    const images = [document.getElementById('photos-img-1').value, document.getElementById('photos-img-2').value]
        .filter(v => v);
    try {
        const res = await fetch(`/api/menus/${id}`, { method: 'PUT', credentials: 'include', headers: H, body: JSON.stringify({ images }) });
        const d   = await res.json();
        flash(res.ok ? 'Photos mises à jour.' : (d.error || 'Erreur.'), res.ok);
        if (res.ok) fermerModalPhotos();
    } catch { flash('API non disponible.', false); }
}

/* ── HORAIRES ── */
async function chargerHoraires() {
    const tb = document.getElementById('tb-horaires');
    try {
        const res      = await fetch('/api/horaires', { credentials: 'include', headers: H });
        const horaires = res.ok ? await res.json() : [];
        if (!horaires.length) { tb.innerHTML = '<tr><td colspan="5" class="admin-vide">Aucun horaire.</td></tr>'; return; }
        tb.innerHTML = horaires.map(h => {
            const ferme = h.heure_ouverture === '00:00:00' && h.heure_fermeture === '00:00:00';
            return `<tr id="h-row-${h.horaire_id}">
                <td><strong>${h.jour}</strong></td>
                <td><input type="time" id="h-ouv-${h.horaire_id}" value="${h.heure_ouverture?.slice(0,5) || '09:00'}"
                    ${ferme ? 'disabled' : ''}
                    style="padding:5px 8px;border:1.5px solid #e5e0d5;border-radius:6px;font-size:13px;${ferme ? 'opacity:.4' : ''}"></td>
                <td><input type="time" id="h-ferm-${h.horaire_id}" value="${h.heure_fermeture?.slice(0,5) || '18:00'}"
                    ${ferme ? 'disabled' : ''}
                    style="padding:5px 8px;border:1.5px solid #e5e0d5;border-radius:6px;font-size:13px;${ferme ? 'opacity:.4' : ''}"></td>
                <td>
                    <label style="display:flex;align-items:center;gap:6px;cursor:pointer;font-size:13px">
                        <input type="checkbox" id="h-ferme-${h.horaire_id}" ${ferme ? 'checked' : ''}
                            data-change="toggleFerme" data-args="[${h.horaire_id}]">
                        Fermé
                    </label>
                </td>
                <td><button class="btn-ad" data-action="sauvegarderHoraire" data-args="[${h.horaire_id}]">Enregistrer</button></td>
            </tr>`;
        }).join('');
    } catch { tb.innerHTML = '<tr><td colspan="5" class="admin-vide">API non connectée.</td></tr>'; }
}

function toggleFerme(id) {
    const ferme = document.getElementById(`h-ferme-${id}`).checked;
    const inp1  = document.getElementById(`h-ouv-${id}`);
    const inp2  = document.getElementById(`h-ferm-${id}`);
    inp1.disabled = ferme; inp2.disabled = ferme;
    inp1.style.opacity = ferme ? '.4' : '1';
    inp2.style.opacity = ferme ? '.4' : '1';
    if (ferme) { inp1.value = '00:00'; inp2.value = '00:00'; }
}

async function sauvegarderHoraire(id) {
    const ferme = document.getElementById(`h-ferme-${id}`).checked;
    const ouv   = ferme ? '00:00' : document.getElementById(`h-ouv-${id}`).value;
    const ferm  = ferme ? '00:00' : document.getElementById(`h-ferm-${id}`).value;
    if (!ouv || !ferm) { flash('Heures invalides.', false); return; }
    try {
        const res = await fetch(`/api/horaires/${id}`, { method: 'PUT', credentials: 'include', headers: H, body: JSON.stringify({ heure_ouverture: ouv, heure_fermeture: ferm }) });
        flash(res.ok ? 'Horaire mis à jour.' : 'Erreur.', res.ok);
    } catch { flash('API non disponible.', false); }
}


/* ── Filtres temps réel ── */
document.getElementById('f-cmd-q').addEventListener('input', () => {
    const q = document.getElementById('f-cmd-q').value.toLowerCase();
    const filtered = cmdCache.filter(c => (c.prenom + ' ' + c.nom + ' ' + (c.titre_menu || '') + ' ' + c.email).toLowerCase().includes(q));
    remplirTableauCommandes(filtered, 'tb-commandes', 9, true);
});
document.getElementById('f-cmd-statut').addEventListener('change', chargerCommandes);
document.getElementById('f-avis-statut').addEventListener('change', chargerAvis);
document.getElementById('f-user-role').addEventListener('change', chargerUtilisateurs);

/* ── Données fallback dev ── */
const CMD_FALLBACK = [
    { commande_id:1, prenom:'Jean', nom:'Martin',  email:'jean@email.fr', titre_menu:'Menu Prestige Bordeaux', nombre_personne:25, date_prestation:'2026-08-15', adresse_livraison:'12 rue des Chartrons', prix_commande:2125, statut_commande:'en_attente' },
    { commande_id:2, prenom:'Sophie', nom:'Bernard', email:'sophie@email.fr', titre_menu:'Menu Saveurs d\'Été', nombre_personne:12, date_prestation:'2026-07-20', adresse_livraison:'5 allée de la Tour', prix_commande:660, statut_commande:'acceptée' },
    { commande_id:3, prenom:'Pierre', nom:'Dupont',  email:'pierre@email.fr', titre_menu:'Menu Végétarien', nombre_personne:8, date_prestation:'2026-07-05', adresse_livraison:'Avenue Thiers', prix_commande:360, statut_commande:'terminée' },
];

/* ── Liaison des boutons (remplace les anciens attributs onclick du HTML) ── */
document.querySelectorAll('.admin-nav-btn').forEach(btn =>
    btn.addEventListener('click', () => nav(btn.dataset.section, btn.dataset.titre, btn.dataset.sousTitre, btn))
);

/** Raccourci « Voir toutes » : même effet qu'un clic sur l'onglet de la section. */
function allerSection(id) {
    document.querySelector(`.admin-nav-btn[data-section="${id}"]`).click();
}

bindActions({
    allerSection, deconnecter,
    ouvrirModalMenu, fermerModalMenu, sauvegarderMenu, supprimerMenu,
    ouvrirModalStock, fermerModalStock, sauvegarderStock,
    ouvrirModalPhotos, fermerModalPhotos, sauvegarderPhotos,
    ouvrirModalPlat, fermerModalPlat, sauvegarderPlat, supprimerPlat,
    ajouterTheme, supprimerTheme,
    chargerCommandes, changerStatut,
    chargerAvis, validerAvis,
    chargerUtilisateurs, toggleCompte,
    ouvrirModalEmploye, fermerModalEmploye, creerEmploye,
    toggleFerme, sauvegarderHoraire,
});
