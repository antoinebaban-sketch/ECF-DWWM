/* Source unique du header (nav desktop + nav mobile), partagée par toutes les
   pages publiques du site, pour ne plus dupliquer ce HTML page par page.
   Utilise document.write() : le script s'exécute pendant le parsing du HTML,
   exactement à l'endroit où <script src="navbar-inject.js"> est placé, donc
   la nav existe déjà dans le DOM avant les scripts de page qui la lisent en
   synchrone (ex: panier.html initialise #panier-badge dès son <script>). Un
   fetch() asynchrone casserait cet ordre.
   Le comportement (burger, état connecté, badge panier) reste dans
   navbar.js, chargé en fin de page comme avant — ce fichier ne fait que
   fournir le HTML. */
(function () {
    var page = location.pathname.split('/').pop() || 'index.html';

    function lien(href, label, extraClass, extraAttrs) {
        var classes = extraClass ? [extraClass] : [];
        if (href === page) classes.push(extraClass === 'btn-panier' ? 'active-page' : 'active');
        var cls = classes.length ? ' class="' + classes.join(' ') + '"' : '';
        return '<a href="' + href + '"' + cls + (extraAttrs || '') + '>' + label + '</a>';
    }

    var pages = [
        ['index.html', 'Accueil'],
        ['menu.html', 'Nos Menus'],
        ['contact.html', 'Contact'],
        ['MonCompte.html', 'Mon Compte']
    ];
    var navLinks = pages.map(function (p) { return '<li>' + lien(p[0], p[1]) + '</li>'; }).join('');

    var panier = lien(
        'panier.html',
        '<span class="panier-ico">🛒</span> Mon panier <span class="panier-badge" id="panier-badge">0</span>',
        'btn-panier',
        ' id="btn-panier-nav"'
    );

    var cta = lien('SeConnecter.html', 'Se connecter', 'btn-outline')
            + lien('SInscrire.html', "S'inscrire", 'btn-filled');

    document.write(
        '<nav class="navbar">'
            + '<span class="navbar-logo">Vite et <em>Gourmand</em></span>'
            + '<ul class="navbar-links">' + navLinks + '</ul>'
            + panier
            + '<div class="navbar-cta">' + cta + '</div>'
            + '<div class="navbar-user" id="navbar-user" hidden>'
                + '<a href="MonCompte.html" class="navbar-user-link">'
                    + '<span class="navbar-user-avatar" id="navbar-user-avatar">?</span>'
                    + '<span id="navbar-user-prenom">Mon compte</span>'
                + '</a>'
                + '<button class="navbar-user-deco" id="navbar-deco-btn">Déconnexion</button>'
            + '</div>'
            + '<button class="navbar-burger" id="burger" aria-label="Ouvrir le menu" aria-expanded="false">'
                + '<span></span><span></span><span></span>'
            + '</button>'
        + '</nav>'
        + '<!-- Menu mobile déroulant -->'
        + '<nav class="navbar-mobile" id="mobile-nav" aria-hidden="true">'
            + '<ul>' + navLinks + '</ul>'
            + '<div class="navbar-mobile-cta">' + cta + '</div>'
            + '<div class="navbar-mobile-user" id="navbar-mobile-user" hidden>'
                + '<div class="navbar-mobile-user-info">'
                    + '<span class="navbar-user-avatar" id="navbar-mobile-avatar">?</span>'
                    + '<span id="navbar-mobile-prenom">Mon compte</span>'
                + '</div>'
                + '<a href="MonCompte.html" class="btn-outline" style="text-align:center">Mon Compte</a>'
                + '<button class="navbar-user-deco" id="navbar-mobile-deco-btn" style="width:100%">Déconnexion</button>'
            + '</div>'
        + '</nav>'
    );
})();
