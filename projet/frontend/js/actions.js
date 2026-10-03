/**
 * Liaison des actions déclarées dans le HTML, à la place des attributs
 * onclick / onchange / onerror : le JavaScript reste dans les fichiers .js
 * et le HTML ne contient plus aucun code, ce qui permet une
 * Content-Security-Policy stricte (script-src 'self', sans 'unsafe-inline').
 *
 *   <button data-action="supprimerMenu" data-args="[3,&quot;Menu&quot;]">
 *   <input  data-change="toggleFerme" data-args="[2]">
 *   <img    data-fallback="images/en_tete.png">   → image de secours si le chargement échoue
 *
 * Chaque page déclare les fonctions qu'elle expose : bindActions({ valider, aller }).
 */
function bindActions(actions) {
    const executer = (el, nom) => {
        const fn = actions[nom];
        if (typeof fn !== 'function') {
            console.error('Action inconnue : ' + nom);
            return;
        }
        const args = el.dataset.args ? JSON.parse(el.dataset.args) : [];
        fn(...args);
    };

    document.addEventListener('click', e => {
        const el = e.target.closest('[data-action]');
        if (el) executer(el, el.dataset.action);
    });
    document.addEventListener('change', e => {
        const el = e.target.closest('[data-change]');
        if (el) executer(el, el.dataset.change);
    });
}

/**
 * Sérialise des arguments pour un attribut data-args généré en JavaScript
 * (gabarits HTML) : JSON échappé pour rester valide quel que soit le texte
 * (apostrophes et guillemets d'un titre de menu, par exemple).
 */
function dataArgs(...args) {
    return JSON.stringify(args)
        .replace(/&/g, '&amp;')
        .replace(/"/g, '&quot;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
}

// Image de secours : l'événement "error" ne remonte pas, on l'intercepte en phase de capture.
document.addEventListener('error', e => {
    const img = e.target;
    if (img.tagName === 'IMG' && img.dataset.fallback) {
        const secours = img.dataset.fallback;
        delete img.dataset.fallback; // une seule tentative, pas de boucle si le secours échoue aussi
        img.src = secours;
    }
}, true);
