/* Script de la page panier.html */

/* ── PANIER (getPanier/savePanier partagées, voir js/panier.js) ── */
function rendrePanier() {
    const panier   = getPanier();
    const liste    = document.getElementById('panier-liste');
    const vide     = document.getElementById('panier-vide');
    const footer   = document.getElementById('panier-footer');
    const badge    = document.getElementById('panier-badge');
    badge.textContent = panier.length;

    if (!panier.length) {
        vide.hidden   = false;
        footer.hidden = true;
        liste.innerHTML = '';
        return;
    }

    vide.hidden   = true;
    footer.hidden = false;

    liste.innerHTML = panier.map((item, idx) => {
        const mini = item.nombre_personne_mini || 1;
        const prix = item.prix_par_personne ? (parseFloat(item.prix_par_personne).toFixed(2).replace('.', ',') + ' €/pers.') : '—';
        const img  = item.image || 'images/en_tete.png';
        return `
        <div class="panier-card" data-idx="${idx}">
            <div class="panier-card-img">
                <img src="${img}" alt="${item.titre}" data-fallback="images/en_tete.png">
            </div>
            <div class="panier-card-body">
                <h2 class="panier-card-titre">${item.titre}</h2>
                <p class="panier-card-meta">
                    À partir de <strong>${prix}</strong> · Min. ${mini} personnes
                </p>
            </div>
            <div class="panier-card-actions">
                <a href="commande.html?menu_id=${item.menu_id}&personnes=${mini}"
                   class="panier-btn-commander">
                    Commander →
                </a>
                <button class="panier-btn-supprimer" data-idx="${idx}" aria-label="Retirer ${item.titre}">
                    ✕ Retirer
                </button>
            </div>
        </div>`;
    }).join('');

    /* Boutons supprimer */
    liste.querySelectorAll('.panier-btn-supprimer').forEach(btn => {
        btn.addEventListener('click', () => {
            const p = getPanier();
            p.splice(parseInt(btn.dataset.idx), 1);
            savePanier(p);
            rendrePanier();
        });
    });
}

document.getElementById('btn-vider').addEventListener('click', () => {
    if (!confirm('Vider tout le panier ?')) return;
    savePanier([]);
    rendrePanier();
});

rendrePanier();

