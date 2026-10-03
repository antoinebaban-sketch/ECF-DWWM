/* Script de la page reset-password.html */

const token = new URLSearchParams(window.location.search).get('token');

function showError(elId, msg) {
    const el = document.getElementById(elId);
    el.textContent = msg;
    el.hidden = false;
}
function hideError(elId) { document.getElementById(elId).hidden = true; }

document.getElementById('form-reset').addEventListener('submit', async e => {
    e.preventDefault();
    hideError('erreur-reset');

    if (!token) {
        showError('erreur-reset', 'Lien invalide. Refaites une demande depuis la page de connexion.');
        return;
    }

    const password = document.getElementById('new-password').value;
    const btn      = e.target.querySelector('button[type="submit"]');
    btn.disabled    = true;
    btn.textContent = 'Réinitialisation…';

    try {
        const res  = await fetch('/api/auth/reset-password', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ token, password })
        });
        const data = await res.json();

        if (!res.ok) {
            showError('erreur-reset', data.error || 'Impossible de réinitialiser le mot de passe.');
            btn.disabled = false;
            btn.textContent = 'Réinitialiser';
            return;
        }

        document.getElementById('form-reset').hidden = true;
        const info = document.getElementById('info-reset');
        info.textContent = 'Mot de passe modifié. Vous pouvez maintenant vous connecter.';
        info.hidden = false;
    } catch {
        showError('erreur-reset', 'Service temporairement indisponible. Réessayez.');
        btn.disabled = false;
        btn.textContent = 'Réinitialiser';
    }
});

