/* Script de la page employe.html */

/* Déjà connecté en employé ou admin → redirection directe */
(async () => {
    const res = await fetch('/api/auth/me', { credentials: 'include' });
    if (!res.ok) return;
    const user = await res.json();
    if (user.role === 'employe' || user.role === 'administrateur') {
        window.location.replace('employe-dashboard.html');
    }
})();

document.getElementById('form-employe').addEventListener('submit', async e => {
    e.preventDefault();
    const email    = document.getElementById('emp-email').value.trim();
    const password = document.getElementById('emp-password').value;
    const erreur   = document.getElementById('emp-erreur');
    const btn      = document.getElementById('btn-submit-emp');

    btn.disabled    = true;
    btn.textContent = 'Connexion…';
    erreur.hidden   = true;

    try {
        const res  = await fetch('/api/auth/login', {
            method:      'POST',
            credentials: 'include',
            headers:     { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        const data = await res.json();

        if (res.ok) {
            if (data.role === 'employe' || data.role === 'administrateur') {
                window.location.href = 'employe-dashboard.html';
                return;
            }
            await fetch('/api/auth/logout', { method: 'POST', credentials: 'include' });
            erreur.textContent = 'Accès refusé : ce compte n\'a pas les droits employé.';
            erreur.hidden = false;
        } else {
            erreur.textContent = data.error || 'Identifiants incorrects.';
            erreur.hidden = false;
        }
    } catch {
        erreur.textContent = 'Service temporairement indisponible. Réessayez.';
        erreur.hidden = false;
    }

    btn.disabled    = false;
    btn.textContent = 'Accéder à l\'espace employé';
});

