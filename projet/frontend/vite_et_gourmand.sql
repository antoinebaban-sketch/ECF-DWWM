-- ============================================================
-- Vite & Gourmand — Base de données complète
-- Version : 2.0 | Date : 2026-06-28
-- Encodage : utf8mb4 | Moteur : InnoDB
--
-- Identifiants de test (hash mis à jour par setup_admin.php) :
--   admin@viteetgourmand.fr   / Admin2026!
--   employe@viteetgourmand.fr / Employe2026!
--   jean.martin@email.fr      / Client2026!
--   sophie.b@email.fr         / Client2026!
--   pierre.d@email.fr         / Client2026!
--
-- ⚠ Exécuter setup_admin.php?secret=vg-setup-2026 après import
--   pour générer les vrais hash bcrypt.
-- ============================================================

SET SQL_MODE       = "NO_AUTO_VALUE_ON_ZERO";
SET NAMES          utf8mb4;
SET time_zone      = "+00:00";
START TRANSACTION;

-- ============================================================
-- RECRÉATION PROPRE
-- ============================================================
DROP DATABASE IF EXISTS `vite_et_gourmand`;
CREATE DATABASE `vite_et_gourmand`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_0900_ai_ci;
USE `vite_et_gourmand`;

-- ============================================================
-- TABLE : role
-- ============================================================
CREATE TABLE `role` (
  `role_id`  INT          NOT NULL AUTO_INCREMENT,
  `libelle`  VARCHAR(50)  NOT NULL,
  PRIMARY KEY (`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `role` (`libelle`) VALUES
('client'),
('employe'),
('administrateur');

-- ============================================================
-- TABLE : utilisateur
-- ============================================================
CREATE TABLE `utilisateur` (
  `utilisateur_id`      INT          NOT NULL AUTO_INCREMENT,
  `email`               VARCHAR(250) NOT NULL UNIQUE,
  `password`            VARCHAR(250) NOT NULL,
  `prenom`              VARCHAR(100) NOT NULL,
  `nom`                 VARCHAR(100) NOT NULL DEFAULT '',
  `telephone`           VARCHAR(20)  NOT NULL DEFAULT '',
  `ville`               VARCHAR(100) NOT NULL DEFAULT '',
  `pays`                VARCHAR(100) NOT NULL DEFAULT 'France',
  `adresse`             VARCHAR(250) NOT NULL DEFAULT '',
  `role_id`             INT          NOT NULL,
  `statut_compte`       TINYINT(1)   NOT NULL DEFAULT 1,
  `date_creation`       DATE         NOT NULL,
  `consentement_rgpd`   TINYINT(1)   NOT NULL DEFAULT 1,
  `reset_token`         VARCHAR(64)  DEFAULT NULL,
  `reset_token_expires` DATETIME     DEFAULT NULL,
  PRIMARY KEY (`utilisateur_id`),
  CONSTRAINT `fk_utilisateur_role`
    FOREIGN KEY (`role_id`) REFERENCES `role`(`role_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE : allergene
-- ============================================================
CREATE TABLE `allergene` (
  `allergene_id` INT          NOT NULL AUTO_INCREMENT,
  `libelle`      VARCHAR(100) NOT NULL,
  PRIMARY KEY (`allergene_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `allergene` (`libelle`) VALUES
('Sans allergène'),
('Gluten'),
('Crustacés'),
('Œufs'),
('Poissons'),
('Arachides'),
('Soja'),
('Lait'),
('Fruits à coque'),
('Céleri'),
('Moutarde'),
('Sésame'),
('Sulfites'),
('Lupin'),
('Mollusques');

-- ============================================================
-- TABLE : regime
-- ============================================================
CREATE TABLE `regime` (
  `regime_id` INT         NOT NULL AUTO_INCREMENT,
  `libelle`   VARCHAR(50) NOT NULL,
  PRIMARY KEY (`regime_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `regime` (`libelle`) VALUES
('Sans régime particulier'),
('Végétarien'),
('Végétalien'),
('Sans gluten'),
('Halal'),
('Casher');

-- ============================================================
-- TABLE : utilisateur_regime  (préférences alimentaires client)
-- ============================================================
CREATE TABLE `utilisateur_regime` (
  `utilisateur_id` INT NOT NULL,
  `regime_id`      INT NOT NULL,
  PRIMARY KEY (`utilisateur_id`, `regime_id`),
  CONSTRAINT `fk_ur_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateur`(`utilisateur_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ur_regime`      FOREIGN KEY (`regime_id`)      REFERENCES `regime`(`regime_id`)           ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE : utilisateur_allergene  (allergènes à éviter)
-- ============================================================
CREATE TABLE `utilisateur_allergene` (
  `utilisateur_id` INT NOT NULL,
  `allergene_id`   INT NOT NULL,
  PRIMARY KEY (`utilisateur_id`, `allergene_id`),
  CONSTRAINT `fk_ua_utilisateur` FOREIGN KEY (`utilisateur_id`) REFERENCES `utilisateur`(`utilisateur_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ua_allergene`   FOREIGN KEY (`allergene_id`)   REFERENCES `allergene`(`allergene_id`)     ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ============================================================
-- TABLE : theme
-- ============================================================
CREATE TABLE `theme` (
  `theme_id` INT         NOT NULL AUTO_INCREMENT,
  `libelle`  VARCHAR(50) NOT NULL,
  PRIMARY KEY (`theme_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `theme` (`libelle`) VALUES
('Mariage'),
('Anniversaire'),
('Entreprise'),
('Baptême'),
('Soirée de gala'),
('Vin d''honneur'),
('Cocktail dinatoire');

-- ============================================================
-- TABLE : horaire
-- ============================================================
CREATE TABLE `horaire` (
  `horaire_id`      INT         NOT NULL AUTO_INCREMENT,
  `jour`            VARCHAR(20) NOT NULL,
  `heure_ouverture` TIME        NOT NULL,
  `heure_fermeture` TIME        NOT NULL,
  PRIMARY KEY (`horaire_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `horaire` (`jour`, `heure_ouverture`, `heure_fermeture`) VALUES
('Lundi',    '09:00:00', '18:00:00'),
('Mardi',    '09:00:00', '18:00:00'),
('Mercredi', '09:00:00', '18:00:00'),
('Jeudi',    '09:00:00', '18:00:00'),
('Vendredi', '09:00:00', '18:00:00'),
('Samedi',   '10:00:00', '16:00:00'),
('Dimanche', '00:00:00', '00:00:00');

-- ============================================================
-- TABLE : plat
-- ============================================================
CREATE TABLE `plat` (
  `plat_id`       INT                                        NOT NULL AUTO_INCREMENT,
  `titre_plat`    VARCHAR(100)                               NOT NULL,
  `type_plat`     ENUM('Entrée','Plat','Dessert','Boisson')  DEFAULT NULL,
  `description`   VARCHAR(500)                               DEFAULT NULL,
  `image_url`     VARCHAR(255)                               DEFAULT NULL,
  `prix_unitaire` DECIMAL(10,2)                              NOT NULL,
  PRIMARY KEY (`plat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `plat` (`titre_plat`, `type_plat`, `description`, `image_url`, `prix_unitaire`) VALUES
-- Entrées (id 1-4)
('Carpaccio de Saint-Jacques',          'Entrée', 'Noix de Saint-Jacques de plongée marinées à l''huile d''olive, citron vert et aneth frais',              NULL, 14.00),
('Terrine de Foie Gras Maison',         'Entrée', 'Foie gras de canard mi-cuit, confiture de figues maison et pain de campagne grillé',                     NULL, 16.50),
('Salade Landaise Revisitée',           'Entrée', 'Gésiers confits, magret fumé, pignons torréfiés et vinaigrette à la moutarde violette',                  NULL,  9.00),
('Gaspacho de Tomates Anciennes',       'Entrée', 'Soupe froide de tomates du jardin, copeaux de parmesan affiné et basilic frais',                         NULL,  7.50),
-- Plats (id 5-8)
('Chapon Farci aux Morilles',           'Plat',   'Chapon Label Rouge farci aux morilles et foie gras, jus corsé au Sauternes',                             NULL, 32.00),
('Magret de Canard à l''Orange',        'Plat',   'Magret de canard du Périgord rosé, sauce Grand Marnier, gratin dauphinois',                              NULL, 24.00),
('Tajine d''Agneau aux Pruneaux',       'Plat',   'Épaule d''agneau confite, abricots secs, pois chiches et coriandre fraîche, couscous artisanal',         NULL, 22.00),
('Curry de Légumes de Saison',          'Plat',   'Légumes du maraîcher local rôtis, lait de coco, gingembre frais, riz basmati — 100% végétalien',         NULL, 17.00),
-- Desserts (id 9-11)
('Dôme Chocolat Cœur Passion',          'Dessert','Mousse chocolat noir Valrhona 70%, cœur coulant fruit de la passion, tuile dorée',                       NULL, 11.00),
('Tarte Tatin à l''Ancienne',           'Dessert','Pommes Golden caramélisées au beurre salé, pâte feuilletée maison, glace vanille Bourbon',               NULL,  9.50),
('Crème Brûlée Grand-Mère',             'Dessert','Crème vanillée à l''ancienne, caramel craquant à la cassonade',                                          NULL,  7.50),
-- Boissons (id 12-13)
('Eau Minérale Plate / Gazeuse',        'Boisson','Bouteille 75cl par personne',                                                                            NULL,  2.50),
('Vin Rouge Bordeaux AOC',              'Boisson','Merlot-Cabernet Saint-Émilion — bouteille partagée (2 personnes)',                                        NULL,  9.00);

-- ============================================================
-- TABLE : plat_allergene_regime
-- ============================================================
CREATE TABLE `plat_allergene_regime` (
  `plat_id`      INT NOT NULL,
  `allergene_id` INT NOT NULL,
  `regime_id`    INT NOT NULL,
  PRIMARY KEY (`plat_id`, `allergene_id`, `regime_id`),
  CONSTRAINT `fk_par_plat`      FOREIGN KEY (`plat_id`)      REFERENCES `plat`(`plat_id`),
  CONSTRAINT `fk_par_allergene` FOREIGN KEY (`allergene_id`) REFERENCES `allergene`(`allergene_id`),
  CONSTRAINT `fk_par_regime`    FOREIGN KEY (`regime_id`)    REFERENCES `regime`(`regime_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Carpaccio (1) → mollusques, sans régime
INSERT INTO `plat_allergene_regime` VALUES (1, 15, 1);
-- Foie Gras (2) → sulfites, sans régime
INSERT INTO `plat_allergene_regime` VALUES (2, 13, 1);
-- Salade Landaise (3) → sulfites, sans régime
INSERT INTO `plat_allergene_regime` VALUES (3, 13, 1);
-- Gaspacho (4) → sans allergène, végétarien
INSERT INTO `plat_allergene_regime` VALUES (4,  1, 2);
-- Chapon (5) → sans allergène, sans régime
INSERT INTO `plat_allergene_regime` VALUES (5,  1, 1);
-- Magret (6) → sulfites, sans régime
INSERT INTO `plat_allergene_regime` VALUES (6, 13, 1);
-- Tajine (7) → fruits à coque, halal
INSERT INTO `plat_allergene_regime` VALUES (7,  9, 5);
-- Curry légumes (8) → sans allergène, végétalien
INSERT INTO `plat_allergene_regime` VALUES (8,  1, 3);
-- Dôme chocolat (9) → lait, végétarien
INSERT INTO `plat_allergene_regime` VALUES (9,  8, 2);
-- Tarte Tatin (10) → gluten + lait, végétarien
INSERT INTO `plat_allergene_regime` VALUES (10, 2, 2);
INSERT INTO `plat_allergene_regime` VALUES (10, 8, 2);
-- Crème brûlée (11) → lait + œufs, végétarien
INSERT INTO `plat_allergene_regime` VALUES (11, 8, 2);
INSERT INTO `plat_allergene_regime` VALUES (11, 4, 2);
-- Eau (12) → sans allergène, sans régime
INSERT INTO `plat_allergene_regime` VALUES (12, 1, 1);
-- Vin Rouge (13) → sulfites, sans régime
INSERT INTO `plat_allergene_regime` VALUES (13, 13, 1);

-- ============================================================
-- TABLE : menu
-- Conditions de prestation obligatoires (affichées en évidence)
-- ============================================================
CREATE TABLE `menu` (
  `menu_id`             INT           NOT NULL AUTO_INCREMENT,
  `titre`               VARCHAR(100)  NOT NULL,
  `nombre_personne_mini`INT           NOT NULL,
  `prix_par_personne`   DECIMAL(10,2) NOT NULL,
  `description`         TEXT          NOT NULL,
  `conditions`          TEXT          DEFAULT NULL COMMENT 'Conditions de prestation affichées en évidence',
  `quantite_restante`   INT           NOT NULL DEFAULT 10,
  `theme_id`            INT           DEFAULT NULL,
  `delai_prevenance`    INT           NOT NULL DEFAULT 7 COMMENT 'Délai minimum en jours avant la date de prestation',
  PRIMARY KEY (`menu_id`),
  CONSTRAINT `fk_menu_theme`
    FOREIGN KEY (`theme_id`) REFERENCES `theme`(`theme_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `menu` (`titre`, `nombre_personne_mini`, `prix_par_personne`, `description`, `conditions`, `quantite_restante`, `theme_id`, `delai_prevenance`) VALUES
(
  'Menu Prestige Bordeaux',
  20, 85.00,
  'L''excellence du terroir bordelais pour vos mariages et grandes cérémonies. Terrine de foie gras, chapon farci aux morilles et dôme chocolat signature.',
  'Réservation minimum 14 jours à l''avance. Acompte de 30% à la commande. Solde 7 jours avant la prestation. Annulation remboursable jusqu''à J-7, 50% remboursé entre J-7 et J-3, aucun remboursement sous J-3. Prix incluant le service, hors boissons.',
  15, 1, 14
),
(
  'Menu Végétarien Harmony',
  8, 45.00,
  'Un menu 100% végétarien, élaboré avec des légumes du maraîcher local. Gaspacho de tomates, curry de légumes de saison et tarte Tatin maison.',
  'Réservation minimum 5 jours à l''avance. Adapté aux régimes végétariens. Peut être décliné en version végétalienne sur demande (supplément 5€/personne). Annulation remboursable jusqu''à J-5. Prix incluant le service, hors boissons.',
  25, 2, 5
),
(
  'Menu Tradition Girondine',
  12, 65.00,
  'Plongez dans les saveurs authentiques du Sud-Ouest. Salade landaise, magret de canard à l''orange et crème brûlée grand-mère.',
  'Réservation minimum 7 jours à l''avance. Acompte de 25% à la commande. Annulation remboursable jusqu''à J-5. Les vins du Bordelais sont proposés en option (voir notre carte). Prix incluant le service.',
  18, 3, 7
),
(
  'Cocktail Dinatoire Gourmand',
  15, 38.00,
  'Une formule cocktail élégante pour vos soirées d''entreprise et réceptions. Assortiment de bouchées gourmandes, petits fours et desserts en verrines.',
  'Réservation minimum 5 jours à l''avance. Acompte de 20% à la commande. Annulation remboursable jusqu''à J-3. Service debout, mise en place incluse. Prix incluant le service, hors boissons.',
  20, 7, 5
);

-- ============================================================
-- TABLE : menu_composition
-- ============================================================
CREATE TABLE `menu_composition` (
  `menu_id` INT NOT NULL,
  `plat_id` INT NOT NULL,
  PRIMARY KEY (`menu_id`, `plat_id`),
  CONSTRAINT `fk_menucomp_menu` FOREIGN KEY (`menu_id`) REFERENCES `menu`(`menu_id`) ON DELETE CASCADE,
  CONSTRAINT `fk_menucomp_plat` FOREIGN KEY (`plat_id`) REFERENCES `plat`(`plat_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Menu 1 : Prestige Bordeaux (foie gras, chapon, dôme chocolat, vin rouge, eau)
INSERT INTO `menu_composition` VALUES (1,2),(1,5),(1,9),(1,13),(1,12);
-- Menu 2 : Végétarien Harmony (gaspacho, curry, tarte tatin, eau)
INSERT INTO `menu_composition` VALUES (2,4),(2,8),(2,10),(2,12);
-- Menu 3 : Tradition Girondine (salade landaise, magret, crème brûlée, vin rouge, eau)
INSERT INTO `menu_composition` VALUES (3,3),(3,6),(3,11),(3,13),(3,12);
-- Menu 4 : Cocktail Dinatoire (carpaccio, tajine, dôme chocolat, eau)
INSERT INTO `menu_composition` VALUES (4,1),(4,7),(4,9),(4,12);

-- ============================================================
-- TABLE : menu_image
-- ============================================================
CREATE TABLE `menu_image` (
  `image_id`  INT          NOT NULL AUTO_INCREMENT,
  `menu_id`   INT          NOT NULL,
  `image_url` VARCHAR(255) NOT NULL,
  PRIMARY KEY (`image_id`),
  CONSTRAINT `fk_menuimage_menu`
    FOREIGN KEY (`menu_id`) REFERENCES `menu`(`menu_id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `menu_image` (`menu_id`, `image_url`) VALUES
(1, 'images/plateau_charcuterie.png'),
(1, 'images/magret_canard.png'),
(2, 'images/avocat_bowl.png'),
(2, 'images/wraps_orientaux.png'),
(3, 'images/filet_poulet.png'),
(3, 'images/creme_brulee.png'),
(4, 'images/burger_gourmet.png'),
(4, 'images/fondant_chocolat.png'),
(4, 'images/macarons.png');

-- ============================================================
-- TABLE : menu_regime
-- ============================================================
CREATE TABLE `menu_regime` (
  `menu_id`   INT NOT NULL,
  `regime_id` INT NOT NULL,
  PRIMARY KEY (`menu_id`, `regime_id`),
  CONSTRAINT `fk_mr_menu`    FOREIGN KEY (`menu_id`)   REFERENCES `menu`(`menu_id`)   ON DELETE CASCADE,
  CONSTRAINT `fk_mr_regime`  FOREIGN KEY (`regime_id`) REFERENCES `regime`(`regime_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Menu 1 : Prestige → Sans régime
INSERT INTO `menu_regime` VALUES (1,1);
-- Menu 2 : Végétarien → Végétarien + Sans gluten sur demande
INSERT INTO `menu_regime` VALUES (2,2),(2,4);
-- Menu 3 : Tradition Girondine → Sans régime
INSERT INTO `menu_regime` VALUES (3,1);
-- Menu 4 : Cocktail Dinatoire → Sans régime
INSERT INTO `menu_regime` VALUES (4,1);

-- ============================================================
-- UTILISATEURS
-- Les mots de passe ci-dessous sont des placeholders.
-- Exécuter setup_admin.php?secret=vg-setup-2026 pour générer
-- les vrais hash bcrypt (coût 12).
-- ============================================================
INSERT INTO `utilisateur`
  (`email`, `password`, `prenom`, `nom`, `telephone`, `ville`, `pays`, `adresse`, `role_id`, `statut_compte`, `date_creation`, `consentement_rgpd`)
VALUES
-- Administrateur (role_id=3)
('admin@viteetgourmand.fr',
 '$2y$12$PLACEHOLDER_RUN_SETUP',
 'José', 'Martins', '0556781234', 'Bordeaux', 'France', '12 rue des Chartrons, 33000 Bordeaux', 3, 1, '2024-01-15', 1),

-- Employé (role_id=2)
('employe@viteetgourmand.fr',
 '$2y$12$PLACEHOLDER_RUN_SETUP',
 'Marie', 'Dupont', '0556782345', 'Bordeaux', 'France', '5 allée des Roses, 33000 Bordeaux', 2, 1, '2024-03-01', 1),

-- Clients (role_id=1)
('jean.martin@email.fr',
 '$2y$12$PLACEHOLDER_RUN_SETUP',
 'Jean', 'Martin', '0612345678', 'Bordeaux', 'France', '24 rue du Palais Gallien, 33000 Bordeaux', 1, 1, '2025-02-10', 1),

('sophie.b@email.fr',
 '$2y$12$PLACEHOLDER_RUN_SETUP',
 'Sophie', 'Bernard', '0698765432', 'Mérignac', 'France', '8 avenue de la République, 33700 Mérignac', 1, 1, '2025-04-22', 1),

('pierre.d@email.fr',
 '$2y$12$PLACEHOLDER_RUN_SETUP',
 'Pierre', 'Dubois', '0677889900', 'Pessac', 'France', '15 rue de la Paix, 33600 Pessac', 1, 1, '2025-09-05', 1);

-- ============================================================
-- TABLE : commande
-- ============================================================
CREATE TABLE `commande` (
  `commande_id`      INT           NOT NULL AUTO_INCREMENT,
  `client_id`        INT           NOT NULL,
  `menu_id`          INT           DEFAULT NULL,
  `nombre_personne`  INT           NOT NULL,
  `date_commande`    DATE          NOT NULL,
  `date_prestation`  DATETIME      NOT NULL,
  `theme_id`         INT           DEFAULT NULL,
  `prix_commande`    DECIMAL(10,2) NOT NULL,
  `statut_commande`  ENUM(
    'en_attente','acceptée','en_preparation',
    'en_cours_livraison','livrée','retour_materiel',
    'terminée','annulée'
  ) NOT NULL DEFAULT 'en_attente',
  `employe_id`       INT           DEFAULT NULL,
  `adresse_livraison`VARCHAR(250)  NOT NULL,
  `motif_annulation` VARCHAR(255)  DEFAULT NULL,
  `mode_contact`     VARCHAR(50)   DEFAULT NULL,
  PRIMARY KEY (`commande_id`),
  CONSTRAINT `fk_commande_client`
    FOREIGN KEY (`client_id`)  REFERENCES `utilisateur`(`utilisateur_id`),
  CONSTRAINT `fk_commande_menu`
    FOREIGN KEY (`menu_id`)    REFERENCES `menu`(`menu_id`) ON DELETE SET NULL,
  CONSTRAINT `fk_commande_theme`
    FOREIGN KEY (`theme_id`)   REFERENCES `theme`(`theme_id`),
  CONSTRAINT `fk_commande_employe`
    FOREIGN KEY (`employe_id`) REFERENCES `utilisateur`(`utilisateur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `commande`
  (`client_id`, `menu_id`, `nombre_personne`, `date_commande`, `date_prestation`, `theme_id`, `prix_commande`, `statut_commande`, `employe_id`, `adresse_livraison`)
VALUES
-- CMD1 : Jean, Menu Prestige, 30 pers, terminée ✅
(3, 1, 30, '2026-01-10', '2026-02-14 19:00:00', 1, 2550.00, 'terminée',       2, '24 rue du Palais Gallien, 33000 Bordeaux'),
-- CMD2 : Sophie, Menu Végétarien, 12 pers, acceptée
(4, 2, 12, '2026-05-20', '2026-07-12 12:30:00', 2,  540.00, 'acceptée',       2, '8 avenue de la République, 33700 Mérignac'),
-- CMD3 : Pierre, Menu Tradition, 15 pers, en_attente
(5, 3, 15, '2026-06-15', '2026-07-20 13:00:00', 3,  975.00, 'en_attente',     NULL, '15 rue de la Paix, 33600 Pessac'),
-- CMD4 : Jean, Cocktail Dinatoire, 20 pers, en_preparation
(3, 4, 20, '2026-06-01', '2026-07-05 12:00:00', 7,  760.00, 'en_preparation', 2, '24 rue du Palais Gallien, 33000 Bordeaux');

-- ============================================================
-- TABLE : historique_commande
-- ============================================================
CREATE TABLE `historique_commande` (
  `historique_id`          INT         NOT NULL AUTO_INCREMENT,
  `commande_id`            INT         NOT NULL,
  `statut`                 VARCHAR(50) NOT NULL,
  `date_changement_statut` DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`historique_id`),
  CONSTRAINT `fk_historique_commande`
    FOREIGN KEY (`commande_id`) REFERENCES `commande`(`commande_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Historique CMD1 (terminée — cycle complet)
INSERT INTO `historique_commande` (`commande_id`, `statut`, `date_changement_statut`) VALUES
(1, 'en_attente',         '2026-01-10 09:15:00'),
(1, 'acceptée',           '2026-01-11 10:30:00'),
(1, 'en_preparation',     '2026-02-13 08:00:00'),
(1, 'en_cours_livraison', '2026-02-14 17:00:00'),
(1, 'livrée',             '2026-02-14 19:30:00'),
(1, 'retour_materiel',    '2026-02-14 23:00:00'),
(1, 'terminée',           '2026-02-28 09:00:00');

-- Historique CMD2 (acceptée)
INSERT INTO `historique_commande` (`commande_id`, `statut`, `date_changement_statut`) VALUES
(2, 'en_attente', '2026-05-20 14:00:00'),
(2, 'acceptée',   '2026-05-21 09:00:00');

-- Historique CMD3 (en_attente)
INSERT INTO `historique_commande` (`commande_id`, `statut`, `date_changement_statut`) VALUES
(3, 'en_attente', '2026-06-15 11:20:00');

-- Historique CMD4 (en_preparation)
INSERT INTO `historique_commande` (`commande_id`, `statut`, `date_changement_statut`) VALUES
(4, 'en_attente',     '2026-06-01 10:00:00'),
(4, 'acceptée',       '2026-06-02 09:30:00'),
(4, 'en_preparation', '2026-07-04 08:00:00');

-- ============================================================
-- TABLE : livraison
-- ============================================================
CREATE TABLE `livraison` (
  `livraison_id`    INT                                             NOT NULL AUTO_INCREMENT,
  `commande_id`     INT                                             NOT NULL,
  `date_livraison`  DATETIME                                        NOT NULL,
  `statut_livraison`ENUM('planifiée','en_cours','livrée','annulée') NOT NULL DEFAULT 'planifiée',
  `liste_materiel`  TEXT                                            DEFAULT NULL,
  `employe_id`      INT                                             DEFAULT NULL,
  `distance_km`     DECIMAL(5,2)                                    NOT NULL DEFAULT 0.00,
  `frais_livraison` DECIMAL(10,2)                                   DEFAULT NULL,
  PRIMARY KEY (`livraison_id`),
  CONSTRAINT `fk_livraison_commande`
    FOREIGN KEY (`commande_id`) REFERENCES `commande`(`commande_id`),
  CONSTRAINT `fk_livraison_employe`
    FOREIGN KEY (`employe_id`)  REFERENCES `utilisateur`(`utilisateur_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `livraison` (`commande_id`, `date_livraison`, `statut_livraison`, `liste_materiel`, `employe_id`, `distance_km`, `frais_livraison`) VALUES
(1, '2026-02-14 19:00:00', 'livrée',    '20 tables rondes, 200 chaises, vaisselle complète', 2,  3.2,  6.89),
(2, '2026-07-12 12:30:00', 'planifiée', '15 tables, 120 chaises',                            2,  8.5, 10.02),
(4, '2026-07-05 12:00:00', 'planifiée', '20 tables hautes, mobilier cocktail',               2,  3.2,  6.89);

-- ============================================================
-- TABLE : avis
-- ============================================================
CREATE TABLE `avis` (
  `avis_id`           INT          NOT NULL AUTO_INCREMENT,
  `client_id`         INT          NOT NULL,
  `commande_id`       INT          DEFAULT NULL,
  `note`              INT          NOT NULL CHECK (`note` BETWEEN 1 AND 5),
  `description`       TEXT         DEFAULT NULL,
  `date_avis`         DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `statut_validation` ENUM('en_attente','validé','refusé') NOT NULL DEFAULT 'en_attente',
  PRIMARY KEY (`avis_id`),
  CONSTRAINT `fk_avis_client`
    FOREIGN KEY (`client_id`)   REFERENCES `utilisateur`(`utilisateur_id`),
  CONSTRAINT `fk_avis_commande`
    FOREIGN KEY (`commande_id`) REFERENCES `commande`(`commande_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO `avis` (`client_id`, `commande_id`, `note`, `description`, `date_avis`, `statut_validation`) VALUES
-- Avis validés (affichés sur la page d'accueil)
(3, 1, 5,
 'Une prestation absolument remarquable pour notre mariage. Le foie gras était divin et le chapon fondant. Le service impeccable, toute notre famille a été bluffée. Je recommande Vite & Gourmand les yeux fermés !',
 '2026-03-01 14:30:00', 'validé'),

(4, NULL, 5,
 'Excellent traiteur ! J''ai fait appel à eux pour l''anniversaire de mes 40 ans et tout était parfait : ponctualité, qualité des plats, présentation soignée. Le carpaccio de Saint-Jacques était exceptionnel.',
 '2026-04-15 10:00:00', 'validé'),

(5, NULL, 4,
 'Très bonne prestation d''ensemble. Le menu végétarien est une vraie réussite, des produits frais et un service attentionné. Légère attente au moment de la mise en place mais rien de grave.',
 '2026-05-10 16:45:00', 'validé'),

(3, NULL, 5,
 'Pour nos 25 ans de mariage, José et son équipe ont encore surpassé nos attentes. La soirée de gala était magique. Le millefeuille a reçu une standing ovation !',
 '2026-01-20 09:15:00', 'validé'),

(4, NULL, 4,
 'Magnifique prestation pour notre séminaire d''entreprise. Les saveurs du sud-ouest ont transporté nos collègues. Organisation sans faille, je referai appel à eux sans hésiter.',
 '2026-02-28 11:30:00', 'validé'),

-- Avis en attente de modération
(5, NULL, 3,
 'Globalement satisfait, les plats étaient bons mais la livraison a eu 45 minutes de retard ce qui a créé un peu de stress. À améliorer sur ce point.',
 '2026-06-20 18:00:00', 'en_attente'),

-- Avis refusé (contenu inapproprié / non conforme)
(3, NULL, 1,
 'Avis de test contenant des informations incorrectes.',
 '2026-06-01 08:00:00', 'refusé');

-- ============================================================
-- INDEX (performances sur les requêtes fréquentes)
-- ============================================================
CREATE INDEX idx_commande_client  ON `commande`(`client_id`);
CREATE INDEX idx_commande_statut  ON `commande`(`statut_commande`);
CREATE INDEX idx_commande_date    ON `commande`(`date_commande`);
CREATE INDEX idx_commande_menu    ON `commande`(`menu_id`);
CREATE INDEX idx_avis_statut      ON `avis`(`statut_validation`);
CREATE INDEX idx_avis_client      ON `avis`(`client_id`);
CREATE INDEX idx_utilisateur_role ON `utilisateur`(`role_id`);

-- ============================================================
-- VUE : menus les plus commandés (pour le dashboard admin)
-- ============================================================
CREATE VIEW `v_menus_populaires` AS
SELECT
    m.menu_id,
    m.titre,
    COUNT(c.commande_id)    AS nb_commandes,
    COALESCE(SUM(c.prix_commande), 0) AS ca_total,
    COALESCE(AVG(c.prix_commande), 0) AS panier_moyen
FROM `menu` m
LEFT JOIN `commande` c
       ON c.menu_id = m.menu_id
      AND c.statut_commande NOT IN ('annulée','en_attente')
GROUP BY m.menu_id, m.titre
ORDER BY nb_commandes DESC;

-- ============================================================
-- VUE : chiffre d'affaires par mois
-- ============================================================
CREATE VIEW `v_ca_mensuel` AS
SELECT
    DATE_FORMAT(c.date_commande, '%Y-%m') AS mois,
    COUNT(c.commande_id)                  AS nb_commandes,
    SUM(c.prix_commande)                  AS ca_total,
    AVG(c.prix_commande)                  AS panier_moyen
FROM `commande` c
WHERE c.statut_commande NOT IN ('annulée','en_attente')
GROUP BY DATE_FORMAT(c.date_commande, '%Y-%m')
ORDER BY mois DESC;

-- ============================================================
-- VUE : clients les plus actifs
-- ============================================================
CREATE VIEW `v_clients_actifs` AS
SELECT
    u.utilisateur_id,
    u.prenom,
    u.nom,
    u.email,
    COUNT(c.commande_id)    AS nb_commandes,
    SUM(c.prix_commande)    AS ca_total,
    MAX(c.date_commande)    AS derniere_commande
FROM `utilisateur` u
JOIN `commande` c
  ON c.client_id = u.utilisateur_id
 AND c.statut_commande NOT IN ('annulée')
GROUP BY u.utilisateur_id, u.prenom, u.nom, u.email
ORDER BY nb_commandes DESC;

-- ============================================================
-- VUE : note moyenne par menu (à partir des avis validés)
-- ============================================================
CREATE VIEW `v_stats_avis` AS
SELECT
    m.menu_id,
    m.titre,
    COUNT(a.avis_id)                           AS nb_avis,
    ROUND(AVG(a.note), 2)                      AS note_moyenne,
    MIN(a.note)                                AS note_min,
    MAX(a.note)                                AS note_max
FROM `menu` m
LEFT JOIN `commande` c  ON c.menu_id        = m.menu_id
LEFT JOIN `avis`     a  ON a.commande_id    = c.commande_id
                       AND a.statut_validation = 'validé'
GROUP BY m.menu_id, m.titre;

COMMIT;

-- ============================================================
-- ✅ Import terminé.
-- ⚠ ÉTAPE SUIVANTE : ouvrir dans le navigateur —
--   http://localhost/ViteEtGourmand/projet/api/setup_admin.php?secret=vg-setup-2026
-- Cela génère les vrais hash bcrypt pour tous les comptes.
-- ============================================================
