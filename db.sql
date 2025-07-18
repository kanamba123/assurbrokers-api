
CREATE DATABASE gisaanalytica_platform ;
ALTER DATABASE gisaanalytica_platform CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;


USE gisaanalytica_platform;

-- TABLE : utilisateurs
CREATE TABLE users (
  id int PRIMARY KEY NOT NULL AUTO_INCREMENT,
  name varchar(100) DEFAULT NULL,
  email varchar(100) UNIQUE DEFAULT NULL,
  password varchar(255) DEFAULT NULL,
  role enum('client','expert','admin','assistant') DEFAULT 'client',
  email_verified_at datetime DEFAULT NULL,
  status enum('actif','inactif','suspendu') DEFAULT 'actif',
  created_at timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at timestamp NULL DEFAULT NULL,
  verification_token varchar(100) DEFAULT NULL,
  reset_token varchar(100) DEFAULT NULL,
  last_seen DATETIME DEFAULT CURRENT_TIMESTAMP,
  reset_requested_at datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



-- TABLE : profils experts (liée à users)
CREATE TABLE experts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    secteur VARCHAR(100),
    pays VARCHAR(100),
    langues_parlees VARCHAR(100),
    description TEXT,
    cv_path VARCHAR(255),
    image_path VARCHAR(255),
    disponible BOOLEAN DEFAULT TRUE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE customer (
  id int PRIMARY KEY NOT NULL AUTO_INCREMENT,
  customer_id varchar(50) DEFAULT NULL,
  customer_name varchar(100) DEFAULT NULL,
  tp_type varchar(5) DEFAULT NULL,
  contact_number varchar(30) DEFAULT NULL,
  customer_address text,
  email varchar(30) DEFAULT NULL,
  customer_TIN varchar(50) DEFAULT NULL,
  customer_trade_number varchar(20) DEFAULT NULL,
  vat_customer_payer int DEFAULT NULL,
  total_buy float(15,2) NOT NULL DEFAULT '0.00',
  total_paid float(15,2) NOT NULL DEFAULT '0.00',
  total_due float(15,2) NOT NULL DEFAULT '0.00',
  reg_date date NOT NULL,
  update_by int DEFAULT NULL,
  date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  date_updated datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE customer_balance (
  id int PRIMARY KEY NOT NULL AUTO_INCREMENT,
  cus_id int DEFAULT NULL,
  due_balance float(15,2) NOT NULL DEFAULT '0.00',
  update_at date DEFAULT NULL,
  create_at timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (cus_id) REFERENCES customer(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb3;

-- TABLE : demandes client > expert
CREATE TABLE demandes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    client_id INT,
    expert_id INT,
    titre VARCHAR(255),
    description TEXT,
    statut ENUM('envoyée', 'en traitement', 'terminée', 'refusée') DEFAULT 'envoyée',
    date_demande DATETIME DEFAULT CURRENT_TIMESTAMP,
    document_joint VARCHAR(255),
    validation_admin ENUM('en attente', 'validée', 'refusée') DEFAULT 'en attente',
    statut_expert ENUM('en attente', 'en cours', 'terminé') DEFAULT 'en attente',
    livrable_path VARCHAR(255) DEFAULT NULL,
    date_validation DATETIME DEFAULT NULL,
    FOREIGN KEY (client_id) REFERENCES customer(id),
    FOREIGN KEY (expert_id) REFERENCES experts(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE livrables (
  id INT AUTO_INCREMENT PRIMARY KEY,
  demande_id INT NOT NULL,
  nom VARCHAR(255) NOT NULL,
  chemin VARCHAR(255) NOT NULL,
  date_creation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (demande_id) REFERENCES demandes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- TABLE : messages (messagerie interne)
CREATE TABLE messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_id INT,
    to_id INT,
    contenu TEXT,
    lu BOOLEAN DEFAULT FALSE,
    date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (from_id) REFERENCES users(id),
    FOREIGN KEY (to_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL, -- destinataire
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    titre VARCHAR(255),
    message TEXT,
    est_lu BOOLEAN DEFAULT FALSE,
    lien VARCHAR(255) DEFAULT NULL,
    date_creation DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



-- TABLE : paiements
CREATE TABLE paiements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    montant DECIMAL(10,2),
    devise VARCHAR(10),
    moyen_paiement VARCHAR(50),
    statut ENUM('en attente', 'réussi', 'échoué') DEFAULT 'en attente',
    date_paiement DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(100) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    type ENUM('service', 'bien') DEFAULT 'service'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- Insertion des nouvelles catégories pour les biens
INSERT INTO categories (nom, slug, description, type) VALUES
('Maisons', 'maisons', 'Biens immobiliers à usage résidentiel', 'bien'),
('Parcelles', 'parcelles', 'Terrains à bâtir ou agricoles', 'bien'),
('Véhicules', 'vehicules', 'Voitures, motos et autres véhicules', 'bien');

CREATE TABLE biens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titre VARCHAR(255),
  description TEXT,
  prix DECIMAL(12,2),
  categorie_id INT,
  user_id INT, -- vendeur
  localisation VARCHAR(255),
  surface VARCHAR(100),
  statut ENUM('disponible', 'vendu', 'réservé') DEFAULT 'disponible',
  date_ajout DATETIME DEFAULT CURRENT_TIMESTAMP,
  actif BOOLEAN DEFAULT TRUE,
  FOREIGN KEY (categorie_id) REFERENCES categories(id),
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE biens_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bien_id INT,
  image_path VARCHAR(255),
  FOREIGN KEY (bien_id) REFERENCES biens(id) ON DELETE CASCADE
);

CREATE TABLE commandes_biens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  bien_id INT,
  acheteur_id INT,
  statut ENUM('en attente', 'confirmée', 'annulée') DEFAULT 'en attente',
  date_commande DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (bien_id) REFERENCES biens(id),
  FOREIGN KEY (acheteur_id) REFERENCES users(id)
);




CREATE TABLE services (
  id INT AUTO_INCREMENT PRIMARY KEY,
  category_id INT,
  titre VARCHAR(255),
  description TEXT,
  contenu TEXT,
  image VARCHAR(255),
  actif BOOLEAN DEFAULT TRUE,
  FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- TABLE : transactions (détails des paiements)
CREATE TABLE transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    paiement_id INT,
    reference_transaction VARCHAR(100) UNIQUE,
    montant DECIMAL(10,2) NOT NULL,
    devise VARCHAR(10) NOT NULL,
    moyen_paiement VARCHAR(50),
    statut ENUM('en attente', 'confirmée', 'échouée', 'remboursée') DEFAULT 'en attente',
    date_transaction DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (paiement_id) REFERENCES paiements(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- TABLE : factures
CREATE TABLE factures (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    numero_facture VARCHAR(50) UNIQUE,
    date_emission DATETIME DEFAULT CURRENT_TIMESTAMP,
    date_echeance DATETIME,
    montant_total DECIMAL(10,2) NOT NULL,
    statut ENUM('brouillon', 'envoyée', 'payée', 'annulée') DEFAULT 'brouillon',
    notes TEXT,
    demande_id INT DEFAULT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (demande_id) REFERENCES demandes(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



-- TABLE : lignes_facture
CREATE TABLE lignes_facture (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facture_id INT,
    description VARCHAR(255),
    quantite INT DEFAULT 1,
    prix_unitaire DECIMAL(10,2),
    total DECIMAL(10,2) GENERATED ALWAYS AS (quantite * prix_unitaire) STORED,
    FOREIGN KEY (facture_id) REFERENCES factures(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titre VARCHAR(255),
    contenu TEXT,
    resume TEXT,              -- à ajouter pour résumé court
    auteur_id INT,
    image_path VARCHAR(255),
    date_publication DATETIME DEFAULT CURRENT_TIMESTAMP,
    actif BOOLEAN DEFAULT TRUE,
    prix DECIMAL(10,2) DEFAULT 0, -- à ajouter pour prix accès
    category_id INT DEFAULT NULL,
    FOREIGN KEY (auteur_id) REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


CREATE TABLE tags (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100) NOT NULL UNIQUE,
  slug VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE article_tag (
  article_id INT NOT NULL,
  tag_id INT NOT NULL,
  PRIMARY KEY(article_id, tag_id),
  FOREIGN KEY(article_id) REFERENCES articles(id) ON DELETE CASCADE,
  FOREIGN KEY(tag_id) REFERENCES tags(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE achats_articles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    article_id INT NOT NULL,
    date_achat DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (article_id) REFERENCES articles(id),
    UNIQUE KEY unique_achat (user_id, article_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;




-- TABLE : témoignages clients

CREATE TABLE temoignages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    auteur VARCHAR(255) NOT NULL,
    role VARCHAR(100), -- client, expert, etc.
    message TEXT NOT NULL,
    image VARCHAR(255), -- chemin de la photo
    date_posted DATETIME DEFAULT CURRENT_TIMESTAMP,
    is_active BOOLEAN DEFAULT FALSE
)ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;


-- TABLE : logs de connexion
CREATE TABLE connexions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    ip VARCHAR(45),
    user_agent TEXT,
    date_connexion DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

-- TABLE : reseaux_sociaux
CREATE TABLE reseaux_sociaux (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nom VARCHAR(50) NOT NULL,
    url VARCHAR(255) NOT NULL,
    icone VARCHAR(100),
    actif BOOLEAN DEFAULT TRUE,
    date_ajout DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;



CREATE TABLE publications (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titre VARCHAR(255),
  slug VARCHAR(255) UNIQUE,
  image_path VARCHAR(255),
  auteur VARCHAR(100),
  date_publication DATETIME DEFAULT CURRENT_TIMESTAMP,
  resume TEXT,
  contenu TEXT,
  prix DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  actif BOOLEAN DEFAULT TRUE
);



CREATE TABLE commandes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT,
  total DECIMAL(10,2),
  statut ENUM('en attente', 'payée', 'échouée') DEFAULT 'en attente',
  date_commande DATETIME DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
);

CREATE TABLE commande_details (
  id INT AUTO_INCREMENT PRIMARY KEY,
  commande_id INT,
  publication_id INT,
  quantite INT DEFAULT 1,
  prix_unitaire DECIMAL(10,2),
  FOREIGN KEY (commande_id) REFERENCES commandes(id),
  FOREIGN KEY (publication_id) REFERENCES publications(id)
);

CREATE TABLE publication_images (
  id INT AUTO_INCREMENT PRIMARY KEY,
  publication_id INT,
  image_path VARCHAR(255),
  FOREIGN KEY (publication_id) REFERENCES publications(id) ON DELETE CASCADE
);

CREATE TABLE messages_contact (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nom VARCHAR(100),
  email VARCHAR(150),
  message TEXT,
  date_envoi DATETIME DEFAULT CURRENT_TIMESTAMP
);



-- USERS (clients, experts, admin, assistant)
INSERT INTO users (name, email, password, role, status, email_verified_at) VALUES
('Alice Dupont', 'alice@example.com',  -- mot de passe : test123
  '$2y$10$XG/XPGQ/vrG0vEDpl8cgEuP7yFB1SRVFZSxq6Wmk8x08LOgqc33S6', 'client', 'actif', NOW()),
('Bob Martin', 'bob@example.com',       -- mot de passe : test123
  '$2y$10$XG/XPGQ/vrG0vEDpl8cgEuP7yFB1SRVFZSxq6Wmk8x08LOgqc33S6', 'expert', 'actif', NOW()),
('Caroline Admin', 'admin@gisaanalytica.com',
  '$2y$10$XG/XPGQ/vrG0vEDpl8cgEuP7yFB1SRVFZSxq6Wmk8x08LOgqc33S6', 'admin', 'actif', NOW()),
('David Assistant', 'assistant@gisaanalytica.com',
  '$2y$10$XG/XPGQ/vrG0vEDpl8cgEuP7yFB1SRVFZSxq6Wmk8x08LOgqc33S6', 'assistant', 'actif', NOW());


INSERT INTO categories (nom, slug, description) VALUES
('Institutionnels', 'institutionnels', 'Services pour administrations et organisations publiques'),
('Particuliers', 'particuliers', 'Services destinés aux citoyens ou PME individuelles');


INSERT INTO customer (id, customer_id, customer_name, tp_type, contact_number, customer_address, email, customer_TIN, customer_trade_number, vat_customer_payer, total_buy, total_paid, total_due, reg_date, update_by, date_created, date_updated) VALUES
(1, 'C1746004365', 'ANDRE', '1', '69391093', 'Nyanza lac', '', '', '', 0, 0.00, 0.00, 0.00, '2025-04-30', 1, '2025-04-30 09:12:45', '2025-04-30 09:12:45'),
(2, 'C1746004432', 'DIVINE MICHAEL', '1', '79659234', 'BCM', '', '', '', 0, 0.00, 0.00, 0.00, '2025-04-30', 1, '2025-04-30 09:13:52', '2025-05-05 00:00:00'),
(3, 'C1746004559', 'MAMAN WILSON', '1', '67572147', 'Kinindo', '', '', '', 0, 0.00, 0.00, 0.00, '2025-04-30', 1, '2025-04-30 09:15:59', '2025-04-30 09:15:59'),
(4, 'C1746004832', 'ZAMALECK', '1', '68971375', 'Kamenge', '', '', '', 0, 0.00, 0.00, 0.00, '2025-04-30', 1, '2025-04-30 09:20:32', '2025-04-30 09:20:32'),
(5, 'C1746004946', 'MELANCE', '1', '68366364', 'Kamenge', '', '', '', 0, 0.00, 0.00, 0.00, '2025-04-30', 1, '2025-04-30 09:22:26', '2025-04-30 09:22:26');

-- EXPERTS (lié à Bob Martin - user_id = 2 supposé)
INSERT INTO experts (user_id, secteur, pays, langues_parlees, description, disponible) VALUES
(2, 'Finance', 'Burundi', 'Français, Anglais', 'Expert en finance et gestion des risques.', TRUE);

INSERT INTO publications (titre, slug, image_path, auteur, resume, contenu, actif)
VALUES (
  'Article scientifique',
  'article-scientifique',
  'assets/img/publications/science1.jpg',
  'Dr. Jean Example',
  'Résumé rapide de l’article scientifique ici.',
  'Contenu complet, long et détaillé de l’article scientifique…',
  1
);

INSERT INTO services (category_id,titre, description, contenu, image, actif) VALUES
(1,'Études & Recherches', 'Appui méthodologique aux projets institutionnels.', 'Contenu complet ici…', 'assets/img/services/etudes.jpg',  1),
(2,'Assistance à la déclaration d’impôts', 'Service aux particuliers pour gérer la fiscalité.', 'Contenu détaillé ici…', 'assets/img/services/impots.jpg', 1);

-- SERVICES
INSERT INTO services (category_id,titre, description, contenu, image, actif) VALUES
(1,'Audit financier', 'Analyse des états financiers.', 'Détail du service...', 'assets/img/services/audit.jpg', 1),
(2,'Conseil fiscal', 'Accompagnement en fiscalité.', 'Description complète...', 'assets/img/services/conseil_fiscal.jpg', 1);


-- MESSAGES (échanges entre Alice et Bob)
INSERT INTO messages (from_id, to_id, contenu, lu, date_envoi) VALUES
(1, 2, 'Bonjour Bob, pouvez-vous m’aider avec ma déclaration ?', FALSE, NOW() - INTERVAL 2 DAY),
(2, 1, 'Bonjour Alice, bien sûr. Pouvez-vous m’envoyer vos documents ?', FALSE, NOW() - INTERVAL 1 DAY);

-- PAIEMENTS (Alice)
INSERT INTO paiements (user_id, montant, devise, moyen_paiement, statut, date_paiement) VALUES
(1, 150.00, 'BIF', 'PayPal', 'réussi', NOW() - INTERVAL 10 DAY),
(1, 75.00, 'BIF', 'CB', 'en attente', NOW() - INTERVAL 1 DAY);

-- TRANSACTIONS (liées aux paiements)
INSERT INTO transactions (paiement_id, reference_transaction, montant, devise, moyen_paiement, statut, date_transaction) VALUES
(1, 'TXN123456', 150.00, 'BIF', 'PayPal', 'confirmée', NOW() - INTERVAL 10 DAY),
(2, 'TXN123457', 75.00, 'BIF', 'CB', 'en attente', NOW() - INTERVAL 1 DAY);

-- FACTURES (pour Alice)
INSERT INTO factures (user_id, numero_facture, date_emission, date_echeance, montant_total, statut, notes) VALUES
(1, 'FAC-2025001', NOW() - INTERVAL 15 DAY, NOW() + INTERVAL 15 DAY, 150.00, 'payée', 'Facture pour service PayPal.'),
(1, 'FAC-2025002', NOW() - INTERVAL 5 DAY, NOW() + INTERVAL 25 DAY, 75.00, 'envoyée', 'Facture en attente de paiement.');

-- LIGNES FACTURE
INSERT INTO lignes_facture (facture_id, description, quantite, prix_unitaire) VALUES
(1, 'Consultation financière', 1, 150.00),
(2, 'Suivi fiscal', 1, 75.00);

-- ARTICLES (blog)
INSERT INTO articles (titre, contenu, resume, auteur_id, image_path, actif, prix, date_publication) VALUES
('Comment gérer sa fiscalité en 2025', 'Contenu complet de l’article...', 'Conseils pratiques pour la fiscalité.', 2, 'assets/img/articles/fiscalite.jpg', TRUE, 0.00, NOW() - INTERVAL 10 DAY),
('L’importance de l’audit interne', 'Article détaillé...', 'Pourquoi l’audit est crucial.', 2, 'assets/img/articles/audit.jpg', TRUE, 10.00, NOW() - INTERVAL 7 DAY);

-- ACHATS ARTICLES (Alice achète l’article payant)
INSERT INTO achats_articles (user_id, article_id, date_achat) VALUES
(1, 2, NOW() - INTERVAL 5 DAY);


-- CONNEXIONS (logs)
INSERT INTO connexions (user_id, ip, user_agent,date_connexion) VALUES
(1, '192.168.1.100', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)', NOW()),
(2, '192.168.1.101', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)', NOW());

-- RESEAUX SOCIAUX
INSERT INTO reseaux_sociaux (nom, url, icone, actif) VALUES
('Facebook', 'https://facebook.com/gisa.analytica', 'fab fa-facebook', 1),
('Twitter', 'https://twitter.com/gisa_analytica', 'fab fa-twitter', 1),
('LinkedIn', 'https://linkedin.com/company/gisa-analytica', 'fab fa-linkedin', 1),
('YouTube', 'https://youtube.com/@gisa_analytica', 'fab fa-youtube', 1);
