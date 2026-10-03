/* Script de la page SeConnecter.html */

const retourRaw = new URLSearchParams(window.location.search).get('retour');
const retour = (retourRaw && !retourRaw.includes('://') && !retourRaw.startsWith('//') && !/^javascript:/i.test(retourRaw) && !/^data:/i.test(retourRaw)) ? retourRaw : 'MonCompte.html';

function showError(elId, msg) {
    const el = document.getElementById(elId);
    el.textContent = msg;
    el.hidden = false;
}
function hideError(elId) { document.getElementById(elId).hidden = true; }

document.getElementById('form-connexion').addEventListener('submit', async e => {
    e.preventDefault();
    hideError('erreur-login');

    const email    = document.getElementById('co-email').value.trim();
    const password = document.getElementById('co-password').value;
    const btn      = e.target.querySelector('button[type="submit"]');

    btn.disabled    = true;
    btn.textContent = 'Connexion…';

    try {
        const res  = await fetch('/api/auth/login', {
            method: 'POST',
            credentials: 'include',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        const data = await res.json();

        if (!res.ok) {
            showError('erreur-login', data.error || 'Identifiants incorrects.');
            btn.disabled = false;
            btn.textContent = 'Se connecter';
            return;
        }

        if (data.role === 'administrateur') {
            window.location.href = 'admin-dashboard.html';
        } else if (data.role === 'employe') {
            window.location.href = 'employe-dashboard.html';
        } else {
            window.location.href = retour || 'MonCompte.html';
        }
    } catch {
        showError('erreur-login', 'Service temporairement indisponible. Réessayez.');
        btn.disabled = false;
        btn.textContent = 'Se connecter';
    }
});

document.getElementById('lien-mdp-oublie').addEventListener('click', e => {
    e.preventDefault();
    const bloc = document.getElementById('bloc-mdp-oublie');
    bloc.hidden = !bloc.hidden;
    if (!bloc.hidden) document.getElementById('oubli-email').focus();
});

document.getElementById('btn-envoyer-oubli').addEventListener('click', async () => {
    const email = document.getElementById('oubli-email').value.trim();
    if (!email) { document.getElementById('oubli-email').focus(); return; }
    const btn = document.getElementById('btn-envoyer-oubli');
    btn.disabled = true;
    btn.textContent = 'Envoi…';

    try {
        await fetch('/api/auth/forgot-password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email })
        });
    } catch { /* on affiche le meme message quoi qu'il arrive, cf. ci-dessous */ }

    hideError('erreur-login');
    document.getElementById('bloc-mdp-oublie').hidden = true;
    const info = document.getElementById('info-login');
    info.textContent = 'Si ce compte existe, un email de réinitialisation a été envoyé.';
    info.hidden = false;
    btn.disabled = false;
    btn.textContent = 'Envoyer';
});

