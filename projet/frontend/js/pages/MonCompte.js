/* Script de la page MonCompte.html */

function flashConfirm(id, duree = 3000) {
    const el = document.getElementById(id);
    if (!el) return;
    el.hidden = false;
    setTimeout(() => { el.hidden = true; }, duree);
}

/* ── CHARGEMENT DU PROFIL ── */
function majAffichageProfil(prenom, nom, email) {
    const nomComplet = [prenom, nom].filter(Boolean).join(' ');
    document.getElementById('compte-avatar').textContent       = (prenom || '?')[0].toUpperCase();
    document.getElementById('compte-nom-affiche').textContent  = nomComplet || prenom || '';
    document.getElementById('compte-email-affiche').textContent = email || '';
    /* Mise à jour navbar desktop + mobile */
    const prenomFormate = prenom ? prenom[0].toUpperCase() + prenom.slice(1) : '';
    ['navbar-user-prenom', 'navbar-mobile-prenom'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = prenomFormate;
    });
    ['navbar-user-avatar', 'navbar-mobile-avatar'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.textContent = prenomFormate[0] || '?';
    });
}

async function chargerProfil() {
    /* ── GARDE : redirige si non connecté ── */
    const res = await fetch('/api/utilisateurs/moi', { credentials: 'include' });
    if (res.status === 401) { window.location.replace('SeConnecter.html'); return; }

    const u = await res.json();

    majAffichageProfil(u.prenom, u.nom, u.email);

    document.getElementById('p-prenom').value  = u.prenom    || '';
    document.getElementById('p-nom').value     = u.nom       || '';
    document.getElementById('p-email').value   = u.email     || '';
    document.getElementById('p-tel').value     = u.telephone || '';
    document.getElementById('p-adresse').value = u.adresse   || '';
    document.getElementById('p-ville').value   = u.ville     || '';
    document.getElementById('p-pays').value    = u.pays      || '';

    if (u.role === 'administrateur') {
        const lien = document.getElementById('lien-admin-dashboard');
        lien.hidden = false; lien.style.display = 'block';
    } else if (u.role === 'employe') {
        const lien = document.getElementById('lien-employe-dashboard');
        lien.hidden = false; lien.style.display = 'block';
    }
}
chargerProfil();

/* ── BURGER SIDEBAR (mobile) ── */
const sidebarBurger = document.getElementById('compte-sidebar-burger');
const sidebar       = document.querySelector('.compte-sidebar');
sidebarBurger.addEventListener('click', () => {
    const isOpen = sidebar.classList.toggle('open');
    sidebarBurger.classList.toggle('active', isOpen);
    sidebarBurger.setAttribute('aria-expanded', isOpen);
});

/* ── NAVIGATION SECTIONS ── */
document.querySelectorAll('.compte-nav-btn').forEach(btn => {
    btn.addEventListener('click', () => {
        document.querySelectorAll('.compte-nav-btn').forEach(b => b.classList.remove('active'));
        document.querySelectorAll('.compte-section').forEach(s => s.classList.remove('active'));
        btn.classList.add('active');
        document.getElementById('section-' + btn.dataset.section).classList.add('active');
        if (btn.dataset.section === 'avis')          chargerAvis();
        if (btn.dataset.section === 'notifications') chargerNotifs();
        if (btn.dataset.section === 'preferences')   chargerPreferences();
        sidebar.classList.remove('open');
        sidebarBurger.classList.remove('active');
        sidebarBurger.setAttribute('aria-expanded', 'false');
    });
});

/* ── SAUVEGARDE PROFIL ── */
document.getElementById('form-profil').addEventListener('submit', async e => {
    e.preventDefault();
    const btn     = e.target.querySelector('button[type=submit]');
    const confEl  = document.getElementById('conf-profil');
    const errEl   = document.getElementById('err-profil');
    confEl.hidden = true;
    errEl.hidden  = true;
    btn.disabled  = true;
    btn.textContent = 'Enregistrement…';

    const prenom    = document.getElementById('p-prenom').value.trim();
    const nom       = document.getElementById('p-nom').value.trim();
    const email     = document.getElementById('p-email').value.trim();
    const telephone = document.getElementById('p-tel').value.trim();
    const adresse   = document.getElementById('p-adresse').value.trim();
    const ville     = document.getElementById('p-ville').value.trim();
    const pays      = document.getElementById('p-pays').value.trim();

    try {
        const res = await fetch('/api/utilisateurs/moi', {
            method:      'PUT',
            credentials: 'include',
            headers:     { 'Content-Type': 'application/json' },
            body: JSON.stringify({ prenom, nom, email, telephone, adresse, ville, pays })
        });
        if (res.status === 401) { window.location.replace('SeConnecter.html'); return; }
        const json = await res.json();
        if (!res.ok) {
            errEl.textContent = json.error || 'Erreur lors de la sauvegarde.';
            errEl.hidden = false;
            return;
        }
        /* Mise à jour de l'affichage */
        majAffichageProfil(prenom, nom, email);
        flashConfirm('conf-profil');
    } catch {
        errEl.textContent = 'Impossible de contacter le serveur.';
        errEl.hidden = false;
    } finally {
        btn.disabled = false;
        btn.textContent = 'Enregistrer les modifications';
    }
});

/* ── CHANGEMENT MOT DE PASSE ── */
document.getElementById('form-mdp').addEventListener('submit', async e => {
    e.preventDefault();
    const nouveau  = document.getElementById('s-nouveau').value;
    const confirm  = document.getElementById('s-confirm').value;
    const erreur   = document.getElementById('erreur-mdp-compte');
    if (!nouveau) { erreur.textContent = 'Veuillez saisir un nouveau mot de passe.'; erreur.hidden = false; return; }
    if (nouveau !== confirm) { erreur.textContent = 'Les mots de passe ne correspondent pas.'; erreur.hidden = false; return; }
    erreur.hidden = true;
    try {
        const res = await fetch('/api/utilisateurs/moi', {
            method:      'PUT',
            credentials: 'include',
            headers:     { 'Content-Type': 'application/json' },
            body: JSON.stringify({ password: nouveau })
        });
        if (!res.ok) { const d = await res.json(); erreur.textContent = d.error || 'Erreur'; erreur.hidden = false; return; }
    } catch { /* API pas encore connectée */ }
    e.target.reset();
    flashConfirm('conf-mdp');
});

/* ── COMMANDES ── */
function chargerCommandes() {
    const liste = document.getElementById('commandes-liste');

    async function depuisAPI() {
        const res = await fetch('/api/commandes/mes-commandes', { credentials: 'include' });
        if (!res.ok) throw new Error();
        return await res.json();
    }

    const STATUT_COULEUR = {
        'en_attente':          '#f59e0b',
        'acceptée':            '#10b981',
        'en_preparation':      '#3b82f6',
        'en_cours_livraison':  '#6366f1',
        'livrée':              '#6b7280',
        'retour_materiel':     '#9ca3af',
        'terminée':            '#6b7280',
        'annulée':             '#ef4444',
    };

    function rendreCommandes(commandes) {
        if (!commandes.length) {
            liste.innerHTML = '<p class="commandes-vide">Aucune commande pour le moment.</p>';
            return;
        }
        liste.innerHTML = '';
        commandes.forEach(cmd => {
            const d    = new Date(cmd.date_commande || cmd.date_creation);
            const date = d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'long', year: 'numeric' });
            const statut  = cmd.statut_commande || '';
            const couleur = STATUT_COULEUR[statut] || '#888';
            const label   = escapeHtml(cmd.statut_label || statut);
            const total   = cmd.prix_commande ? (parseFloat(cmd.prix_commande).toFixed(2).replace('.', ',') + ' €') : '—';
            const article = cmd.menu_titre ? `<li>${cmd.nombre_personne} personne${cmd.nombre_personne > 1 ? 's' : ''} — ${escapeHtml(cmd.menu_titre)}</li>` : '';

            const card = document.createElement('div');
            card.className = 'commande-card';
            card.innerHTML = `
                <div class="commande-card-header">
                    <div>
                        <p class="commande-ref">Commande n°${cmd.commande_id}</p>
                        <p class="commande-date">${date}</p>
                    </div>
                    <div style="text-align:right;">
                        <span class="commande-statut" style="background:${couleur}20;color:${couleur};border:1px solid ${couleur}40;">${label}</span>
                        <p class="commande-total">${total}</p>
                    </div>
                </div>
                ${article ? `<ul class="commande-articles">${article}</ul>` : ''}
                ${cmd.date_prestation ? `<p class="commande-livraison">Prestation prévue le ${new Date(cmd.date_prestation).toLocaleDateString('fr-FR')}</p>` : ''}
                <div class="commande-card-actions">
                    ${statut === 'en_attente' ? `
                        <button type="button" class="btn-facture btn-modifier-cmd" data-id="${cmd.commande_id}">Modifier</button>
                        <button type="button" class="btn-facture btn-annuler-cmd" data-id="${cmd.commande_id}" style="color:#ef4444;border-color:#ef4444;">Annuler</button>
                    ` : `
                        <button type="button" class="btn-facture btn-suivi-cmd" data-id="${cmd.commande_id}">Suivi de commande</button>
                    `}
                    <button type="button" class="btn-facture" data-id="${cmd.commande_id || ''}" data-total="${total}" data-date="${date}">
                        Demander une facture
                    </button>
                </div>`;
            liste.appendChild(card);
        });

        /* Boutons facture */
        liste.querySelectorAll('.btn-facture:not(.btn-modifier-cmd):not(.btn-annuler-cmd):not(.btn-suivi-cmd)').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('facture-ref').textContent   = btn.dataset.id;
                document.getElementById('facture-total').textContent = btn.dataset.total;
                document.getElementById('facture-date').textContent  = btn.dataset.date;
                if (window.vgUser) {
                    document.getElementById('facture-email').value = window.vgUser.email || '';
                }
                document.getElementById('modal-facture').classList.add('open');
            });
        });

        /* Bouton modifier */
        liste.querySelectorAll('.btn-modifier-cmd').forEach(btn => {
            btn.addEventListener('click', () => {
                const cmd = commandes.find(c => String(c.commande_id) === btn.dataset.id);
                if (!cmd) return;
                document.getElementById('modif-cmd-id').value = cmd.commande_id;
                document.getElementById('modif-personnes').value = cmd.nombre_personne || '';
                if (cmd.date_prestation) {
                    document.getElementById('modif-date').value = cmd.date_prestation.replace(' ', 'T').slice(0, 16);
                }
                document.getElementById('modif-adresse').value = cmd.adresse_livraison || '';
                document.getElementById('modal-modifier-commande').classList.add('open');
            });
        });

        /* Bouton annuler */
        liste.querySelectorAll('.btn-annuler-cmd').forEach(btn => {
            btn.addEventListener('click', async () => {
                if (!confirm('Annuler cette commande ?')) return;
                try {
                    const res = await fetch(`/api/commandes/${btn.dataset.id}/annuler`, {
                        method: 'PUT', credentials: 'include',
                    });
                    if (!res.ok) { const d = await res.json(); alert(d.error || 'Erreur.'); return; }
                    chargerCommandes();
                } catch { alert('Service temporairement indisponible.'); }
            });
        });

        /* Bouton suivi de commande */
        liste.querySelectorAll('.btn-suivi-cmd').forEach(btn => {
            btn.addEventListener('click', async () => {
                const corps = document.getElementById('suivi-corps');
                corps.innerHTML = '<p style="font-size:13px;color:#666">Chargement…</p>';
                document.getElementById('modal-suivi-commande').classList.add('open');
                try {
                    const res = await fetch(`/api/commandes/${btn.dataset.id}/historique`, { credentials: 'include' });
                    const hist = res.ok ? await res.json() : [];
                    if (!hist.length) { corps.innerHTML = '<p style="font-size:13px;color:#666">Aucun historique disponible.</p>'; return; }
                    corps.innerHTML = '<ul class="commande-articles">' + hist.map(h => {
                        const dt = h.date_changement_statut ? new Date(h.date_changement_statut.replace(' ', 'T')).toLocaleString('fr-FR') : '';
                        return `<li><strong>${escapeHtml(h.statut_label)}</strong> — ${dt}</li>`;
                    }).join('') + '</ul>';
                } catch {
                    corps.innerHTML = '<p style="font-size:13px;color:#666">Service temporairement indisponible.</p>';
                }
            });
        });
    }

    depuisAPI()
        .then(rendreCommandes)
        .catch(() => {
            const local = JSON.parse(localStorage.getItem('vg_commandes') || '[]');
            rendreCommandes(local);
        });
}
chargerCommandes();

/* ── MODAL FACTURE ── */
document.getElementById('modal-facture-fermer').addEventListener('click', () => {
    document.getElementById('modal-facture').classList.remove('open');
});
document.getElementById('modal-facture').addEventListener('click', e => {
    if (e.target === document.getElementById('modal-facture')) {
        document.getElementById('modal-facture').classList.remove('open');
    }
});
document.getElementById('form-facture').addEventListener('submit', async e => {
    e.preventDefault();
    const email      = document.getElementById('facture-email').value;
    const entreprise = document.getElementById('facture-entreprise').value.trim();
    const ref        = document.getElementById('facture-ref').textContent;
    try {
        await fetch('/api/factures', {
            method:      'POST',
            credentials: 'include',
            headers:     { 'Content-Type': 'application/json' },
            body: JSON.stringify({ commande_id: parseInt(ref), email, entreprise })
        });
    } catch { /* API non connectée */ }
    document.getElementById('facture-ok').hidden = false;
    setTimeout(() => {
        document.getElementById('modal-facture').classList.remove('open');
        document.getElementById('facture-ok').hidden = true;
        document.getElementById('form-facture').reset();
    }, 2000);
});

/* ── MODAL MODIFIER COMMANDE ── */
document.getElementById('modal-modifier-fermer').addEventListener('click', () => {
    document.getElementById('modal-modifier-commande').classList.remove('open');
});
document.getElementById('modal-modifier-commande').addEventListener('click', e => {
    if (e.target === document.getElementById('modal-modifier-commande')) {
        document.getElementById('modal-modifier-commande').classList.remove('open');
    }
});
document.getElementById('form-modifier-commande').addEventListener('submit', async e => {
    e.preventDefault();
    const errEl = document.getElementById('modif-erreur');
    errEl.hidden = true;
    const id = document.getElementById('modif-cmd-id').value;
    try {
        const res = await fetch(`/api/commandes/${id}`, {
            method:      'PUT',
            credentials: 'include',
            headers:     { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                nombre_personne:   parseInt(document.getElementById('modif-personnes').value),
                date_prestation:   document.getElementById('modif-date').value,
                adresse_livraison: document.getElementById('modif-adresse').value,
            })
        });
        const d = await res.json();
        if (!res.ok) { errEl.textContent = d.error || 'Erreur lors de la modification.'; errEl.hidden = false; return; }
        document.getElementById('modal-modifier-commande').classList.remove('open');
        chargerCommandes();
    } catch {
        errEl.textContent = 'Service temporairement indisponible.';
        errEl.hidden = false;
    }
});

/* ── MODAL SUIVI COMMANDE ── */
document.getElementById('modal-suivi-fermer').addEventListener('click', () => {
    document.getElementById('modal-suivi-commande').classList.remove('open');
});
document.getElementById('modal-suivi-commande').addEventListener('click', e => {
    if (e.target === document.getElementById('modal-suivi-commande')) {
        document.getElementById('modal-suivi-commande').classList.remove('open');
    }
});

/* ── AVIS ── */
const ETOILES = n => '★'.repeat(n) + '☆'.repeat(5 - n);

function buildFormulaireAvis(cmdId, labelTitre, labelSous) {
    const key = cmdId ?? 'general';
    return `
    <div class="commande-card" style="margin-bottom:16px;" id="form-avis-${key}">
        <p style="font-family:'Cormorant Garamond',serif;font-size:17px;color:var(--bordeaux);margin-bottom:4px;">${escapeHtml(labelTitre)}</p>
        ${labelSous ? `<p style="font-size:12px;color:var(--brun);margin-bottom:12px;">${escapeHtml(labelSous)}</p>` : ''}
        <div style="display:flex;gap:6px;margin-bottom:10px;" role="group" aria-label="Note">
            ${[1,2,3,4,5].map(n => `<button type="button"
                data-note="${n}" data-cmd="${key}"
                class="btn-etoile"
                style="background:none;border:none;font-size:28px;cursor:pointer;color:#d9c9b0;padding:0;line-height:1;"
                aria-label="${n} étoile${n>1?'s':''}">★</button>`).join('')}
        </div>
        <input type="hidden" id="note-${key}" value="0">
        <textarea id="desc-${key}" rows="3" placeholder="Décrivez votre expérience…"
            style="width:100%;box-sizing:border-box;padding:10px 12px;border:1.5px solid #d9c9b0;border-radius:8px;font-family:'DM Sans',sans-serif;font-size:13px;resize:vertical;margin-bottom:10px;"></textarea>
        <button type="button" data-action="soumettrAvis" data-args="[${cmdId !== null ? cmdId : 'null'}]"
            style="background:var(--bordeaux);color:var(--or);border:none;padding:10px 22px;border-radius:6px;font-family:'DM Sans',sans-serif;font-weight:600;font-size:13px;cursor:pointer;letter-spacing:.04em;">
            Envoyer l'avis
        </button>
        <p id="avis-ok-${key}" hidden style="margin-top:8px;font-size:13px;color:var(--bordeaux);font-weight:600;">
            Merci ! Votre avis sera publié après validation.
        </p>
    </div>`;
}

async function chargerAvis() {
    const eligiblesDiv = document.getElementById('avis-eligibles');
    const listDiv      = document.getElementById('avis-liste');
    eligiblesDiv.innerHTML = '<p style="font-size:13px;color:var(--brun)">Chargement…</p>';
    listDiv.innerHTML      = '';

    let commandes = [], mesAvis = [];

    try {
        const [rCmd, rAvis] = await Promise.all([
            fetch('/api/commandes/mes-commandes', { credentials: 'include' }),
            fetch('/api/avis/mes-avis',           { credentials: 'include' }),
        ]);
        if (rCmd.ok)  commandes = await rCmd.json();
        if (rAvis.ok) mesAvis   = await rAvis.json();
    } catch { /* API non disponible */ }

    const avisParCommande = {};
    mesAvis.forEach(a => { if (a.commande_id) avisParCommande[a.commande_id] = a; });
    const avisGeneralExiste = mesAvis.some(a => !a.commande_id);

    // Commandes terminées sans avis
    const eligibles = commandes.filter(c => c.statut_commande === 'terminée' && !avisParCommande[c.commande_id]);

    let htmlEligibles = '';
    if (eligibles.length) {
        htmlEligibles += `<p style="font-family:'DM Sans',sans-serif;font-size:14px;color:var(--bordeaux);font-weight:600;margin-bottom:12px;">
            Vous pouvez laisser un avis pour ${eligibles.length === 1 ? 'cette prestation' : 'ces prestations'} :
        </p>`;
        htmlEligibles += eligibles.map(c => buildFormulaireAvis(
            c.commande_id,
            `${c.menu_titre || 'Prestation'} — Commande n°${c.commande_id}`,
            c.date_prestation ? new Date(c.date_prestation).toLocaleDateString('fr-FR', { day:'2-digit', month:'long', year:'numeric' }) : ''
        )).join('');
    }

    // Aucun avis et aucune commande éligible : renvoyer vers la page Contact
    if (!avisGeneralExiste && !eligibles.length) {
        htmlEligibles += `
        <p class="commandes-vide">
            Vous n'avez pas encore publié d'avis. Rendez-vous sur la page
            <a href="contact.html" style="color:var(--bordeaux);font-weight:600;">Contact</a>
            pour déposer votre premier avis !
        </p>`;
    }

    eligiblesDiv.innerHTML = htmlEligibles || '';

    // Gestion des étoiles (sur le container complet)
    eligiblesDiv.querySelectorAll('.btn-etoile').forEach(btn => {
        btn.addEventListener('click', () => {
            const n   = parseInt(btn.dataset.note);
            const cmd = btn.dataset.cmd;
            document.getElementById('note-' + cmd).value = n;
            eligiblesDiv.querySelectorAll(`.btn-etoile[data-cmd="${cmd}"]`).forEach(b => {
                b.style.color = parseInt(b.dataset.note) <= n ? 'var(--or)' : '#d9c9b0';
            });
        });
    });

    // Avis déjà déposés
    if (mesAvis.length) {
        const STATUT_BADGE = {
            'en_attente': { bg: '#fef3c7', color: '#92400e', label: 'En attente de validation' },
            'validé':     { bg: '#f0fdf4', color: '#166534', label: 'Publié' },
            'refusé':     { bg: '#fee2e2', color: '#991b1b', label: 'Refusé' },
        };
        listDiv.innerHTML = `
            <p style="font-family:'DM Sans',sans-serif;font-size:14px;color:var(--bordeaux);font-weight:600;margin:24px 0 12px;">
                Mes avis déposés :
            </p>` +
        mesAvis.map(a => {
            const s = STATUT_BADGE[a.statut_validation] || STATUT_BADGE['en_attente'];
            const d = a.date_avis ? new Date(a.date_avis).toLocaleDateString('fr-FR', { day:'2-digit', month:'long', year:'numeric' }) : '';
            return `<div class="commande-card" style="margin-bottom:12px;">
                <div class="commande-card-header">
                    <div>
                        <p style="font-family:'Cormorant Garamond',serif;font-size:17px;color:var(--bordeaux);margin-bottom:2px;">${a.menu_titre || 'Témoignage général'}</p>
                        <p style="font-size:12px;color:var(--brun);">${d}</p>
                    </div>
                    <div style="text-align:right">
                        <span style="color:var(--or);font-size:18px;">${ETOILES(a.note)}</span><br>
                        <span style="background:${s.bg};color:${s.color};font-size:11px;padding:2px 8px;border-radius:4px;font-weight:600;">${s.label}</span>
                    </div>
                </div>
                ${a.description ? `<p style="font-size:13px;color:#555;margin-top:8px;font-style:italic;">"${escapeHtml(a.description)}"</p>` : ''}
            </div>`;
        }).join('');
    } else if (!eligibles.length && avisGeneralExiste) {
        listDiv.innerHTML = '';
    } else if (!eligibles.length) {
        listDiv.innerHTML = '<p class="commandes-vide">Aucun avis déposé pour le moment.</p>';
    }
}

window.soumettrAvis = async function(commandeId) {
    const key  = commandeId !== null ? commandeId : 'general';
    const note = parseInt(document.getElementById('note-' + key).value);
    if (!note) { alert('Veuillez sélectionner une note (1 à 5 étoiles).'); return; }
    const desc = document.getElementById('desc-' + key).value.trim();
    const body = { note, description: desc };
    if (commandeId !== null) body.commande_id = commandeId;

    const btn = document.querySelector(`#form-avis-${key} button[data-action="soumettrAvis"]`);
    if (btn) { btn.disabled = true; btn.textContent = 'Envoi…'; }

    try {
        const res = await fetch('/api/avis', {
            method:      'POST',
            credentials: 'include',
            headers:     { 'Content-Type': 'application/json' },
            body:        JSON.stringify(body),
        });
        const data = await res.json();
        if (!res.ok) {
            alert(data.error || 'Erreur lors de l\'envoi.');
            if (btn) { btn.disabled = false; btn.textContent = 'Envoyer l\'avis'; }
            return;
        }
    } catch {
        /* API non disponible — confirmation locale */
    }
    document.getElementById('avis-ok-' + key).hidden = false;
    if (btn) btn.disabled = true;
    setTimeout(chargerAvis, 1800);
};

/* ── DÉCONNEXION ── */
document.getElementById('btn-deconnexion').addEventListener('click', () => seDeconnecter());

/* ── EXPORT DONNÉES (RGPD Art. 20) ── */
document.getElementById('btn-exporter').addEventListener('click', async e => {
    e.preventDefault();
    try {
        const res  = await fetch('/api/utilisateurs/moi/export', { credentials: 'include' });
        const blob = await res.blob();
        const url  = URL.createObjectURL(blob);
        const a    = document.createElement('a');
        a.href = url; a.download = 'mes-donnees-viteetgourmand.json';
        a.click(); URL.revokeObjectURL(url);
    } catch { alert('Export non disponible pour le moment.'); }
});

/* ── PRÉFÉRENCES ALIMENTAIRES ── */

function renderPrefGrid(containerId, items, idKey, name, selectedIds) {
    document.getElementById(containerId).innerHTML = items.map(item => `
        <label class="pref-chip">
            <input type="checkbox" name="${name}" value="${item[idKey]}"${selectedIds.includes(item[idKey]) ? ' checked' : ''}>
            ${escapeHtml(item.libelle)}
        </label>
    `).join('');
}

async function chargerPreferences() {
    try {
        const [rReg, rAll, rPrefs] = await Promise.all([
            fetch('/api/regimes'),
            fetch('/api/allergenes'),
            fetch('/api/utilisateurs/moi/preferences', { credentials: 'include' }),
        ]);
        const regimes    = rReg.ok   ? await rReg.json()   : [];
        const allergenes = rAll.ok   ? await rAll.json()   : [];
        let selR = [], selA = [];
        if (rPrefs.ok) {
            const prefs = await rPrefs.json();
            selR = (prefs.regimes    || []).map(r => r.regime_id);
            selA = (prefs.allergenes || []).map(a => a.allergene_id);
        }
        renderPrefGrid('pref-regimes',   regimes,                                       'regime_id',   'regime',   selR);
        renderPrefGrid('pref-allergenes', allergenes.filter(a => a.allergene_id !== 1), 'allergene_id', 'allergene', selA);
    } catch { /* API non disponible */ }
}

document.getElementById('form-preferences').addEventListener('submit', async e => {
    e.preventDefault();
    const btn    = e.target.querySelector('button[type=submit]');
    const confEl = document.getElementById('conf-preferences');
    const errEl  = document.getElementById('err-preferences');
    confEl.hidden = true;
    errEl.hidden  = true;
    btn.disabled  = true;
    btn.textContent = 'Enregistrement…';

    const regimes    = [...document.querySelectorAll('[name=regime]:checked')].map(el => parseInt(el.value));
    const allergenes = [...document.querySelectorAll('[name=allergene]:checked')].map(el => parseInt(el.value));

    try {
        const res = await fetch('/api/utilisateurs/moi/preferences', {
            method:      'PUT',
            credentials: 'include',
            headers:     { 'Content-Type': 'application/json' },
            body:        JSON.stringify({ regimes, allergenes }),
        });
        if (!res.ok) {
            const d = await res.json();
            errEl.textContent = d.error || 'Erreur lors de l\'enregistrement.';
            errEl.hidden = false;
        } else {
            flashConfirm('conf-preferences');
        }
    } catch {
        flashConfirm('conf-preferences');
    }
    btn.disabled = false;
    btn.textContent = 'Enregistrer mes préférences';
});

/* ── PRÉFÉRENCES NOTIFICATIONS ── */
function chargerNotifs() {
    const prefs = JSON.parse(localStorage.getItem('vg_notifs') || '{"commande":true,"livraison":true,"promo":false,"rappel":true}');
    document.getElementById('notif-commande').checked = !!prefs.commande;
    document.getElementById('notif-livraison').checked = !!prefs.livraison;
    document.getElementById('notif-promo').checked    = !!prefs.promo;
    document.getElementById('notif-rappel').checked   = !!prefs.rappel;
}
chargerNotifs();

document.getElementById('form-notifs').addEventListener('submit', e => {
    e.preventDefault();
    const prefs = {
        commande: document.getElementById('notif-commande').checked,
        livraison: document.getElementById('notif-livraison').checked,
        promo:    document.getElementById('notif-promo').checked,
        rappel:   document.getElementById('notif-rappel').checked,
    };
    localStorage.setItem('vg_notifs', JSON.stringify(prefs));
    flashConfirm('conf-notifs');
});

/* ── SUPPRESSION COMPTE ── */
document.getElementById('btn-supprimer').addEventListener('click', async () => {
    if (!confirm('Supprimer définitivement votre compte ? Cette action est irréversible.')) return;
    try {
        await fetch('/api/utilisateurs/moi', {
            method: 'DELETE',
            credentials: 'include',
        });
    } catch { /* API pas encore connectée */ }
    seDeconnecter();
});


/* ── Liaison des boutons (remplace les anciens attributs onclick du HTML) ── */
bindActions({ soumettrAvis: commandeId => window.soumettrAvis(commandeId) });
