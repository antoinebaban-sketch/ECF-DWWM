/* Script de la page contact.html */

/* ── ÉTOILES ── */
const etoiles = document.querySelectorAll('.etoile');
const noteInput = document.getElementById('avis-note');
etoiles.forEach(btn => {
    btn.addEventListener('click', () => {
        const val = parseInt(btn.dataset.val);
        noteInput.value = val;
        etoiles.forEach(e => e.classList.toggle('active', parseInt(e.dataset.val) <= val));
    });
    btn.addEventListener('mouseenter', () => {
        const val = parseInt(btn.dataset.val);
        etoiles.forEach(e => e.classList.toggle('survol', parseInt(e.dataset.val) <= val));
    });
    btn.addEventListener('mouseleave', () => {
        etoiles.forEach(e => e.classList.remove('survol'));
    });
});

/* ── FORMULAIRE RÉCLAMATION ── */
document.getElementById('form-reclamation').addEventListener('submit', async e => {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;
    try {
        await fetch('/api/contact', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                titre:           document.getElementById('rec-type').value + ' — ' + document.getElementById('rec-commande').value,
                email:           document.getElementById('rec-email').value,
                description:     'De : ' + document.getElementById('rec-nom').value + '\n\n' + document.getElementById('rec-message').value,
            }),
        });
    } catch { /* API non disponible */ }
    document.getElementById('conf-reclamation').hidden = false;
    e.target.reset();
    btn.disabled = false;
});

/* ── FORMULAIRE AVIS ── */
document.getElementById('form-avis').addEventListener('submit', async e => {
    e.preventDefault();
    if (!noteInput.value) { alert('Veuillez choisir une note.'); return; }
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;
    try {
        const res = await fetch('/api/avis', {
            method:      'POST',
            credentials: 'include',
            headers:     { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                note:            parseInt(noteInput.value),
                description:     document.getElementById('avis-message').value,
            }),
        });
        const data = await res.json();
        if (!res.ok) {
            alert(data.error === 'Vous devez être connecté'
                ? 'Vous devez être connecté pour déposer un avis. Connectez-vous depuis votre espace Mon Compte.'
                : (data.error || 'Une erreur est survenue.'));
            btn.disabled = false;
            return;
        }
        document.getElementById('conf-avis').hidden = false;
        e.target.reset();
        noteInput.value = '';
        etoiles.forEach(el => el.classList.remove('active'));
    } catch {
        alert('Impossible de contacter le serveur. Réessayez plus tard.');
    }
    btn.disabled = false;
});

