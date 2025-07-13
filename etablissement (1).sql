-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Hôte : 127.0.0.1:3306
-- Généré le : sam. 12 juil. 2025 à 14:43
-- Version du serveur : 9.1.0
-- Version de PHP : 8.1.31

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Base de données : `etablissement`
--

-- --------------------------------------------------------

--
-- Structure de la table `absences`
--

DROP TABLE IF EXISTS `absences`;
CREATE TABLE IF NOT EXISTS `absences` (
  `id` int NOT NULL AUTO_INCREMENT,
  `etudiant_id` int DEFAULT NULL,
  `date` date DEFAULT NULL,
  `justifie` tinyint(1) DEFAULT '0',
  `commentaire` text,
  PRIMARY KEY (`id`),
  KEY `etudiant_id` (`etudiant_id`)
) ;

--
-- Déchargement des données de la table `absences`
--

INSERT INTO `absences` (`id`, `etudiant_id`, `date`, `justifie`, `commentaire`) VALUES
(1, 1, '2025-06-29', 1, 'j\'etais  Malade'),
(2, 1, '2025-07-06', 1, '');

-- --------------------------------------------------------

--
-- Structure de la table `classes`
--

DROP TABLE IF EXISTS `classes`;
CREATE TABLE IF NOT EXISTS `classes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(50) DEFAULT NULL,
  `niveau` varchar(50) DEFAULT NULL,
  `annee_scolaire` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ;

--
-- Déchargement des données de la table `classes`
--

INSERT INTO `classes` (`id`, `nom`, `niveau`, `annee_scolaire`) VALUES
(1, 'L1 Info', 'Licence 1', '2024-2025'),
(2, 'Amphie A', 'L2', '2024-2025');

-- --------------------------------------------------------

--
-- Structure de la table `cours`
--

DROP TABLE IF EXISTS `cours`;
CREATE TABLE IF NOT EXISTS `cours` (
  `id` int NOT NULL AUTO_INCREMENT,
  `titre` varchar(255) DEFAULT NULL,
  `description` text,
  `fichier` varchar(255) DEFAULT NULL,
  `type_fichier` enum('pdf','word','texte','autre') DEFAULT NULL,
  `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP,
  `enseignant_id` int DEFAULT NULL,
  `classe_id` int DEFAULT NULL,
  `matiere_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `enseignant_id` (`enseignant_id`),
  KEY `classe_id` (`classe_id`),
  KEY `matiere_id` (`matiere_id`)
) ;

--
-- Déchargement des données de la table `cours`
--

INSERT INTO `cours` (`id`, `titre`, `description`, `fichier`, `type_fichier`, `date_ajout`, `enseignant_id`, `classe_id`, `matiere_id`) VALUES
(1, 'Introduction à la Programmation', 'Ceci est le premier chapitre du cours. Il couvre les bases des variables, des conditions et des boucles.', 'chapitre1.pdf', 'pdf', '2025-06-12 19:45:24', 1, 1, 1),
(2, 'NODE  JS', 'langage de programmation coté Backend', '', 'autre', '2025-06-29 14:05:49', 1, 1, 1),
(3, 'Vole de Nuit', 'BIEN', '686ad8c39d227-Projet Collectif Professionnel.pdf', 'pdf', '2025-07-06 20:12:51', 1, 1, 1);

-- --------------------------------------------------------

--
-- Structure de la table `cours_telecharges`
--

DROP TABLE IF EXISTS `cours_telecharges`;
CREATE TABLE IF NOT EXISTS `cours_telecharges` (
  `id` int NOT NULL AUTO_INCREMENT,
  `cours_id` int DEFAULT NULL,
  `etudiant_id` int DEFAULT NULL,
  `date_telechargement` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `cours_id` (`cours_id`),
  KEY `etudiant_id` (`etudiant_id`)
) ;

-- --------------------------------------------------------

--
-- Structure de la table `deliberations`
--

DROP TABLE IF EXISTS `deliberations`;
CREATE TABLE IF NOT EXISTS `deliberations` (
  `id` int NOT NULL AUTO_INCREMENT,
  `etudiant_id` int DEFAULT NULL,
  `moyenne` decimal(5,2) DEFAULT NULL,
  `decision` enum('Admis','Ajourné','Ajourne cond') DEFAULT NULL,
  `annee_scolaire` varchar(20) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `etudiant_id` (`etudiant_id`)
) ;

--
-- Déchargement des données de la table `deliberations`
--

INSERT INTO `deliberations` (`id`, `etudiant_id`, `moyenne`, `decision`, `annee_scolaire`) VALUES
(1, 1, 15.00, 'Admis', '2024-2025');

-- --------------------------------------------------------

--
-- Structure de la table `emplois_du_temps`
--

DROP TABLE IF EXISTS `emplois_du_temps`;
CREATE TABLE IF NOT EXISTS `emplois_du_temps` (
  `id` int NOT NULL AUTO_INCREMENT,
  `classe_id` int DEFAULT NULL,
  `jour_semaine` varchar(20) DEFAULT NULL,
  `heure_debut` time DEFAULT NULL,
  `heure_fin` time DEFAULT NULL,
  `matiere_id` int DEFAULT NULL,
  `enseignant_id` int DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `classe_id` (`classe_id`),
  KEY `matiere_id` (`matiere_id`),
  KEY `enseignant_id` (`enseignant_id`)
) ;

--
-- Déchargement des données de la table `emplois_du_temps`
--

INSERT INTO `emplois_du_temps` (`id`, `classe_id`, `jour_semaine`, `heure_debut`, `heure_fin`, `matiere_id`, `enseignant_id`) VALUES
(1, 1, 'Lundi', '10:00:00', '12:00:00', 1, 1);

-- --------------------------------------------------------

--
-- Structure de la table `enseignants`
--

DROP TABLE IF EXISTS `enseignants`;
CREATE TABLE IF NOT EXISTS `enseignants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int DEFAULT NULL,
  `specialite` varchar(100) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `utilisateur_id` (`utilisateur_id`)
) ;

--
-- Déchargement des données de la table `enseignants`
--

INSERT INTO `enseignants` (`id`, `utilisateur_id`, `specialite`) VALUES
(1, 4, 'Developpeur Fulstack');

-- --------------------------------------------------------

--
-- Structure de la table `etudiants`
--

DROP TABLE IF EXISTS `etudiants`;
CREATE TABLE IF NOT EXISTS `etudiants` (
  `id` int NOT NULL AUTO_INCREMENT,
  `utilisateur_id` int DEFAULT NULL,
  `classe_id` int DEFAULT NULL,
  `matricule` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `utilisateur_id` (`utilisateur_id`),
  KEY `classe_id` (`classe_id`)
);

--
-- Déchargement des données de la table `etudiants`
--

INSERT INTO `etudiants` (`id`, `utilisateur_id`, `classe_id`, `matricule`) VALUES
(1, 1, 1, 'N01252820201');

-- --------------------------------------------------------

--
-- Structure de la table `matieres`
--

DROP TABLE IF EXISTS `matieres`;
CREATE TABLE IF NOT EXISTS `matieres` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) DEFAULT NULL,
  `coefficient` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ;

--
-- Déchargement des données de la table `matieres`
--

INSERT INTO `matieres` (`id`, `nom`, `coefficient`) VALUES
(1, 'Algorithmique', 4),
(2, 'PHP', 6);

-- --------------------------------------------------------

--
-- Structure de la table `notes`
--

DROP TABLE IF EXISTS `notes`;
CREATE TABLE IF NOT EXISTS `notes` (
  `id` int NOT NULL AUTO_INCREMENT,
  `etudiant_id` int DEFAULT NULL,
  `matiere_id` int DEFAULT NULL,
  `trimestre` enum('T1','T2','T3') DEFAULT NULL,
  `note` decimal(5,2) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_note_par_etudiant` (`etudiant_id`,`matiere_id`,`trimestre`),
  KEY `matiere_id` (`matiere_id`)
) ;

--
-- Déchargement des données de la table `notes`
--

INSERT INTO `notes` (`id`, `etudiant_id`, `matiere_id`, `trimestre`, `note`) VALUES
(1, 1, 1, 'T1', 15.00);

-- --------------------------------------------------------

--
-- Structure de la table `utilisateurs`
--

DROP TABLE IF EXISTS `utilisateurs`;
CREATE TABLE IF NOT EXISTS `utilisateurs` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nom` varchar(100) DEFAULT NULL,
  `prenom` varchar(100) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `mot_de_passe` varchar(255) DEFAULT NULL,
  `role` enum('admin','enseignant','etudiant') DEFAULT NULL,
  `statut` enum('actif','inactif') DEFAULT 'actif',
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ;

--
-- Déchargement des données de la table `utilisateurs`
--

INSERT INTO `utilisateurs` (`id`, `nom`, `prenom`, `email`, `mot_de_passe`, `role`, `statut`) VALUES
(1, 'Faye', 'Ibrahima', 'ibsibzo97@gmail.com', '$2y$10$FRVL6K/LUfDGdy.7BMCiGenGG6SP4dpDYmBF5FLIfcU4f9MmXpaxq', 'etudiant', 'actif'),
(3, 'Diouf', 'Birame', 'birame01@gmail.com', '$2y$10$ulxGNjD3Y9CXnjSIGWTTyewwovJ5Jkaqd8H8fgG6jjyt3sLgqLAZm', 'admin', 'actif'),
(4, 'Faye', 'IBOU KHALIL', 'ibou.faye@gmail.com', '$2y$10$ywyjZOqUg4w3Ao5kzUC/6OP/n1yqB4gQifYSSClzYKzEAGqxJxUey', 'enseignant', 'actif');
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
