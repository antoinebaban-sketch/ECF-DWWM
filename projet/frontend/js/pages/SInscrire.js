/* Script de la page SInscrire.html */

const form   = document.getElementById('form-inscription');
const errMdp = document.getElementById('erreur-mdp');

form.addEventListener('submit', async e => {
    e.preventDefault();
    const mdp  = document.getElementById('ins-password').value;
    const conf = document.getElementById('ins-confirm').value;

    if (mdp !== conf) { errMdp.textContent = 'Les mots de passe ne correspondent pas.'; errMdp.hidden = false; return; }
    errMdp.hidden = true;

    const btn = form.querySelector('button[type="submit"]');
    btn.disabled = true;
    btn.textContent = 'Inscription…';

    const data = {
        prenom:            document.getElementById('ins-prenom').value.trim(),
        nom:               document.getElementById('ins-nom').value.trim(),
        email:             document.getElementById('ins-email').value.trim(),
        telephone:         document.getElementById('ins-tel').value.trim(),
        adresse:           document.getElementById('ins-adresse').value.trim(),
        ville:             document.getElementById('ins-ville').value.trim(),
        pays:              'France',
        password:          mdp,
        consentement_rgpd: document.getElementById('ins-rgpd').checked ? 1 : 0,
    };

    try {
        const res  = await fetch('/api/auth/register', {
            method:      'POST',
            credentials: 'include',
            headers:     { 'Content-Type': 'application/json' },
            body:        JSON.stringify(data),
        });
        const json = await res.json();

        if (!res.ok) {
            errMdp.textContent = json.error || 'Erreur lors de l\'inscription.';
            errMdp.hidden = false;
            btn.disabled = false;
            btn.textContent = 'S\'inscrire';
            return;
        }

        const retour = new URLSearchParams(window.location.search).get('retour');
        const dest = (retour && !retour.includes('://') && !retour.startsWith('//')) ? retour : 'MonCompte.html';
        window.location.href = dest;

    } catch {
        errMdp.textContent = 'Service temporairement indisponible. Réessayez.';
        errMdp.hidden = false;
        btn.disabled = false;
        btn.textContent = 'S\'inscrire';
    }
});

