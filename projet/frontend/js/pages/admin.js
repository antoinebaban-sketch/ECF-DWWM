/* Script de la page admin.html */

/* Déjà connecté en admin → redirection directe */
(async () => {
    const res = await fetch('/api/auth/me', { credentials: 'include' });
    if (!res.ok) return;
    const user = await res.json();
    if (user.role === 'administrateur') {
        window.location.replace('admin-dashboard.html');
    }
})();

document.getElementById('form-admin').addEventListener('submit', async e => {
    e.preventDefault();
    const email    = document.getElementById('admin-email').value.trim();
    const password = document.getElementById('admin-password').value;
    const erreur   = document.getElementById('admin-erreur');
    const btn      = e.target.querySelector('button[type="submit"]');
    btn.disabled   = true; btn.textContent = 'Connexion…';
    erreur.hidden  = true;

    try {
        const res  = await fetch('/api/auth/login', {
            method:      'POST',
            credentials: 'include',
            headers:     { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        const data = await res.json();
        if (res.ok && data.role === 'administrateur') {
            window.location.href = 'admin-dashboard.html';
            return;
        }
        if (res.ok) await fetch('/api/auth/logout', { method: 'POST', credentials: 'include' });
        erreur.textContent = data.error || 'Identifiants incorrects ou accès non autorisé.';
        erreur.hidden = false;
    } catch {
        erreur.textContent = 'Service temporairement indisponible. Réessayez.';
        erreur.hidden = false;
    }
    btn.disabled = false; btn.textContent = 'Accéder au tableau de bord';
});

