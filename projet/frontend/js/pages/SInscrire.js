/* Script de la page SInscrire.html */

const form    = document.getElementById('form-inscription');
const errMdp  = document.getElementById('erreur-mdp');
const champMdp = document.getElementById('ins-password');

/* Contrôle de saisie côté navigateur : mêmes règles que PasswordPolicy.php côté serveur.
   Il sert le confort de l'utilisateur (il voit tout de suite ce qui manque) ;
   le serveur refait toujours la vérification, car le navigateur peut être modifié. */
function verifierMotDePasse(mdp) {
    const regles = [
        { ok: mdp.length >= 10,      message: 'au moins 10 caractères' },
        { ok: /[A-Z]/.test(mdp),     message: 'une majuscule' },
        { ok: /[a-z]/.test(mdp),     message: 'une minuscule' },
        { ok: /[0-9]/.test(mdp),     message: 'un chiffre' },
        { ok: /[\W_]/.test(mdp),    message: 'un caractère spécial' },
    ];
    // On renvoie la liste des règles qui ne sont pas respectées (vide si tout va bien).
    return regles.filter(regle => !regle.ok).map(regle => regle.message);
}

function afficherErreur(message) {
    errMdp.textContent = message;
    errMdp.hidden = false;
    champMdp.setAttribute('aria-invalid', 'true');   // signalé aux lecteurs d'écran
    champMdp.focus();
}

form.addEventListener('submit', async e => {
    e.preventDefault();                               // on vérifie AVANT d'envoyer
    const mdp  = champMdp.value;
    const conf = document.getElementById('ins-confirm').value;

    const manquants = verifierMotDePasse(mdp);
    if (manquants.length > 0) {
        afficherErreur('Le mot de passe doit contenir : ' + manquants.join(', ') + '.');
        return;                                       // le formulaire n'est pas envoyé
    }
    if (mdp !== conf) { afficherErreur('Les mots de passe ne correspondent pas.'); return; }

    errMdp.hidden = true;
    champMdp.removeAttribute('aria-invalid');

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

