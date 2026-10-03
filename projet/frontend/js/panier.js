/* Utilitaires panier (localStorage) partagés entre menu.html et panier.html —
   évite de dupliquer ces deux fonctions sur les deux pages. */
function getPanier() {
    return JSON.parse(localStorage.getItem('vg_panier') || '[]');
}

function savePanier(p) {
    localStorage.setItem('vg_panier', JSON.stringify(p));
    const b = document.getElementById('panier-badge');
    if (b) b.textContent = p.length;
}
