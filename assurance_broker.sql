-

DELIMITER $$
--
-- Procédures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `CalculateAdvancedCommission` (IN `contract_id_param` INT)   BEGIN
    DECLARE base_amount DECIMAL(10,2);
    DECLARE company_id_val INT;
    DECLARE product_id_val INT;
    DECLARE final_commission DECIMAL(10,2);
    DECLARE applicable_rule_id INT;
    
    -- Récupération des données du contrat
    SELECT c.premium, c.company_id, cp.product_id
    INTO base_amount, company_id_val, product_id_val
    FROM contracts c
    LEFT JOIN company_products cp ON c.product_id = cp.product_id
    WHERE c.contract_id = contract_id_param;
    
    -- Trouver la règle applicable (avec priorité)
    SELECT rule_id INTO applicable_rule_id
    FROM commission_rules
    WHERE company_id = company_id_val
    AND (product_id IS NULL OR product_id = product_id_val)
    AND (valid_from <= CURDATE() AND (valid_to IS NULL OR valid_to >= CURDATE()))
    AND is_active = TRUE
    ORDER BY priority DESC, rule_id DESC
    LIMIT 1;
    
    -- Calcul selon le type de règle
    IF applicable_rule_id IS NOT NULL THEN
        SELECT calculation_type INTO @calc_type
        FROM commission_rules
        WHERE rule_id = applicable_rule_id;
        
        CASE @calc_type
            WHEN 'fixed' THEN
                SET final_commission = (SELECT base_value FROM commission_rules WHERE rule_id = applicable_rule_id);
            
            WHEN 'percentage' THEN
                SET final_commission = base_amount * 
                    (SELECT base_value FROM commission_rules WHERE rule_id = applicable_rule_id) / 100;
            
            WHEN 'tiered' THEN
                SET final_commission = 0;
                
                -- Calcul par tranches
                SELECT SUM(
                    LEAST(IFNULL(t.max_amount, base_amount), base_amount) - t.min_amount
                ) * t.rate / 100
                INTO final_commission
                FROM commission_tiers t
                WHERE t.rule_id = applicable_rule_id
                AND t.min_amount < base_amount;
        END CASE;
        
        -- Enregistrement de la commission
        INSERT INTO commissions (contract_id, company_id, amount, commission_rate, calculation_base, status, rule_id)
        VALUES (
            contract_id_param, 
            company_id_val, 
            final_commission,
            (final_commission / base_amount * 100),
            base_amount,
            'pending',
            applicable_rule_id
        );
        
        -- Mise à jour du contrat
        UPDATE contracts 
        SET commission_amount = final_commission,
            commission_calculated = TRUE
        WHERE contract_id = contract_id_param;
    END IF;
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `CalculateCommission` (IN `contract_id_param` INT)   BEGIN
    DECLARE base_amount DECIMAL(10,2);
    DECLARE rate DECIMAL(5,2);
    DECLARE commission DECIMAL(10,2);
    DECLARE company_id_val INT;
    
    -- Récupération des données du contrat
    SELECT c.premium, c.commission_rate, c.id
    INTO base_amount, rate, company_id_val
    FROM contracts c
    WHERE c.id = contract_id_param;
    
    -- Calcul de la commission
    SET commission = base_amount * (rate / 100);
    
    -- Mise à jour du contrat
    UPDATE contracts 
    SET commission_amount = commission,
        commission_calculated = TRUE
    WHERE id = contract_id_param;
    
    -- Création de l'enregistrement de commission
    INSERT INTO commissions (id, company_id, amount, commission_rate, calculation_base, status)
    VALUES (contract_id_param, company_id_val, commission, rate, base_amount, 'pending');
END$$

CREATE DEFINER=`root`@`localhost` PROCEDURE `RefreshAnalyticsData` ()   BEGIN
    -- Mise à jour de la table des dates
    INSERT INTO analytics_dim_date (date_id, day, month, quarter, year, day_of_week, is_weekend)
    SELECT 
        date_field,
        DAY(date_field),
        MONTH(date_field),
        QUARTER(date_field),
        YEAR(date_field),
        DAYOFWEEK(date_field),
        DAYOFWEEK(date_field) IN (1,7)
    FROM (
        SELECT DISTINCT DATE(payment_date) as date_field FROM online_payments
        UNION
        SELECT DISTINCT DATE(created_at) FROM commissions
    ) dates
    ON DUPLICATE KEY UPDATE is_holiday = VALUES(is_holiday);
    
    -- Nettoyage des données existantes
    TRUNCATE TABLE analytics_commissions;
    
    -- Chargement des données de commission
    INSERT INTO analytics_commissions
    (date_id, company_id, product_id, user_id, contract_id, amount, fees, net_amount, status, payment_gateway, days_to_payment)
    SELECT 
        DATE(c.created_at) as date_id,
        c.id,
        co.id,
        co.created_by as user_id,
        c.id,
        c.amount,
        IFNULL(op.fees, 0) as fees,
        c.amount - IFNULL(op.fees, 0) as net_amount,
        c.status,
        op.gateway as payment_gateway,
        DATEDIFF(IFNULL(op.payment_date, CURDATE()), c.created_at) as days_to_payment
    FROM commissions c
    JOIN contracts co ON c.id = co.id
    LEFT JOIN online_payments op ON c.id = op.commission_id;
    
    -- Calcul des KPIs agrégés
    TRUNCATE TABLE analytics_kpis;
    
    INSERT INTO analytics_kpis
    (date_id, company_id, user_id, total_commissions, paid_commissions, pending_commissions, avg_payment_days, conversion_rate)
    SELECT 
        d.date_id,
        NULL as company_id,
        NULL as user_id,
        SUM(ac.amount) as total_commissions,
        SUM(CASE WHEN ac.status = 'paid' THEN ac.amount ELSE 0 END) as paid_commissions,
        SUM(CASE WHEN ac.status = 'pending' THEN ac.amount ELSE 0 END) as pending_commissions,
        AVG(CASE WHEN ac.status = 'paid' THEN ac.days_to_payment ELSE NULL END) as avg_payment_days,
        COUNT(DISTINCT CASE WHEN ac.status = 'paid' THEN ac.contract_id ELSE NULL END) / 
        COUNT(DISTINCT ac.contract_id) * 100 as conversion_rate
    FROM analytics_commissions ac
    JOIN analytics_dim_date d ON ac.date_id = d.date_id
    GROUP BY d.date_id;
END$$

--
-- Fonctions
--
CREATE DEFINER=`root`@`localhost` FUNCTION `CheckCommissionStatus` (`contract_id_param` INT) RETURNS VARCHAR(20) CHARSET utf8mb4 DETERMINISTIC BEGIN
    DECLARE status_val VARCHAR(20);
    
    SELECT status INTO status_val
    FROM commissions
    WHERE id = contract_id_param
    LIMIT 1;
    
    RETURN IFNULL(status_val, 'not_found');
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `analytics_commissions`
--

CREATE TABLE `analytics_commissions` (
  `fact_id` int NOT NULL,
  `date_id` date NOT NULL,
  `company_id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `contract_id` int DEFAULT NULL,
  `amount` decimal(12,2) NOT NULL,
  `fees` decimal(12,2) NOT NULL,
  `net_amount` decimal(12,2) NOT NULL,
  `status` enum('pending','paid','cancelled') NOT NULL,
  `payment_gateway` varchar(20) DEFAULT NULL,
  `days_to_payment` int DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `analytics_dim_date`
--

CREATE TABLE `analytics_dim_date` (
  `date_id` date NOT NULL,
  `day` int NOT NULL,
  `month` int NOT NULL,
  `quarter` int NOT NULL,
  `year` int NOT NULL,
  `day_of_week` int NOT NULL,
  `is_weekend` tinyint(1) NOT NULL,
  `is_holiday` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `analytics_kpis`
--

CREATE TABLE `analytics_kpis` (
  `kpi_id` int NOT NULL,
  `date_id` date NOT NULL,
  `company_id` int DEFAULT NULL,
  `user_id` int DEFAULT NULL,
  `total_commissions` decimal(12,2) NOT NULL,
  `paid_commissions` decimal(12,2) NOT NULL,
  `pending_commissions` decimal(12,2) NOT NULL,
  `avg_payment_days` decimal(5,2) NOT NULL,
  `conversion_rate` decimal(5,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `appointments`
--

CREATE TABLE `appointments` (
  `appointment_id` int NOT NULL,
  `customer_id` int DEFAULT NULL,
  `user_id` int NOT NULL,
  `title` varchar(100) NOT NULL,
  `description` text,
  `start_time` datetime NOT NULL,
  `end_time` datetime NOT NULL,
  `status` enum('Planifié','Confirmé','Annulé','Terminé') DEFAULT 'Planifié',
  `location` varchar(100) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `categories`
--

CREATE TABLE `categories` (
  `id` int NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

--
-- Déchargement des données de la table `categories`
--

INSERT INTO `categories` (`id`, `name`, `description`, `created_at`) VALUES
(1, 'Auto', 'Assurance automobile', '2025-06-30 09:00:44'),
(2, 'Santé', 'Assurance santé', '2025-06-30 09:00:44'),
(3, 'Habitation', 'Assurance habitation', '2025-06-30 09:00:44');

-- --------------------------------------------------------

--
-- Structure de la table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` int NOT NULL,
  `sender_id` int NOT NULL,
  `receiver_id` int NOT NULL,
  `message` text,
  `file_path` text,
  `audio_path` text,
  `seen` tinyint(1) DEFAULT '0',
  `sent_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `chat_notifications`
--

CREATE TABLE `chat_notifications` (
  `id` int NOT NULL,
  `user_id` int NOT NULL,
  `message_id` int NOT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `chat_sessions`
--

CREATE TABLE `chat_sessions` (
  `id` int NOT NULL,
  `user_id` int DEFAULT NULL,
  `guest_id` varchar(100) DEFAULT NULL,
  `started_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `claims`
--

CREATE TABLE `claims` (
  `claim_id` int NOT NULL,
  `contract_id` int NOT NULL,
  `claim_date` date NOT NULL,
  `description` text NOT NULL,
  `amount` decimal(10,2) DEFAULT NULL,
  `status` enum('Déclaré','En cours','Accepté','Refusé','Indemnisé') DEFAULT 'Déclaré',
  `decision_date` date DEFAULT NULL,
  `decision_notes` text,
  `processed_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `declaration_method` enum('en ligne','papier') DEFAULT 'en ligne',
  `documents_path` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `commissions`
--

CREATE TABLE `commissions` (
  `id` int NOT NULL,
  `contract_id` int NOT NULL,
  `company_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `commission_rate` decimal(5,2) NOT NULL,
  `calculation_base` decimal(10,2) NOT NULL,
  `status` enum('pending','confirmed','paid','cancelled') DEFAULT 'pending',
  `expected_payment_date` date DEFAULT NULL,
  `actual_payment_date` date DEFAULT NULL,
  `payment_reference` varchar(100) DEFAULT NULL,
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `commission_payments`
--

CREATE TABLE `commission_payments` (
  `payment_id` int NOT NULL,
  `company_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `method` enum('Virement','Chèque','Prélèvement') DEFAULT 'Virement',
  `reference` varchar(100) NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `status` enum('pending','confirmed','cancelled') DEFAULT 'pending',
  `bank_details` text,
  `notes` text,
  `processed_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `commission_rules`
--

CREATE TABLE `commission_rules` (
  `id` int NOT NULL,
  `company_id` int NOT NULL,
  `product_id` int DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `calculation_type` enum('fixed','percentage','tiered') NOT NULL,
  `base_value` decimal(10,2) DEFAULT NULL,
  `min_threshold` decimal(10,2) DEFAULT NULL,
  `max_threshold` decimal(10,2) DEFAULT NULL,
  `valid_from` date NOT NULL,
  `valid_to` date DEFAULT NULL,
  `priority` int DEFAULT '0',
  `is_active` tinyint(1) DEFAULT '1'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `commission_tiers`
--

CREATE TABLE `commission_tiers` (
  `tier_id` int NOT NULL,
  `rule_id` int NOT NULL,
  `min_amount` decimal(10,2) NOT NULL,
  `max_amount` decimal(10,2) DEFAULT NULL,
  `rate` decimal(5,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `company_products`
--

CREATE TABLE `company_products` (
  `id` int NOT NULL,
  `company_id` int NOT NULL,
  `type_id` int NOT NULL,
  `product_code` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `base_price` decimal(10,2) NOT NULL,
  `commission_rate` decimal(5,2) NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `terms_conditions` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `garanties` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

--
-- Déchargement des données de la table `company_products`
--

INSERT INTO `company_products` (`id`, `company_id`, `type_id`, `product_code`, `name`, `description`, `base_price`, `commission_rate`, `is_active`, `terms_conditions`, `created_at`, `updated_at`, `garanties`) VALUES
(1, 1, 1, '0001', 'Assurance Automobile tous risques', 'Assurance tous risques Affaires et Promenades', 125000.00, 15.00, 1, 'Une année', '2025-07-01 14:36:20', '2025-07-01 17:00:23', 'Tous dommages automobiles');

-- --------------------------------------------------------

--
-- Structure de la table `contracts`
--

CREATE TABLE `contracts` (
  `id` int NOT NULL,
  `customer_id` int NOT NULL,
  `type_id` int NOT NULL,
  `reference` varchar(50) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `premium` decimal(10,2) NOT NULL,
  `payment_frequency` enum('Mensuel','Trimestriel','Semestriel','Annuel') DEFAULT 'Mensuel',
  `status` enum('En attente','Actif','Résilié','Expiré') DEFAULT 'En attente',
  `documents_path` text,
  `notes` text,
  `company_id` int NOT NULL,
  `created_by` int NOT NULL,
  `is_commission_paid` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `commission_rate` decimal(5,2) DEFAULT NULL,
  `commission_calculated` tinyint(1) DEFAULT '0',
  `commission_amount` decimal(10,2) DEFAULT '0.00',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

--
-- Déchargement des données de la table `contracts`
--

INSERT INTO `contracts` (`id`, `customer_id`, `type_id`, `reference`, `start_date`, `end_date`, `premium`, `payment_frequency`, `status`, `documents_path`, `notes`, `company_id`, `created_by`, `is_commission_paid`, `created_at`, `commission_rate`, `commission_calculated`, `commission_amount`, `updated_at`) VALUES
(3, 1, 1, 'CTR-001', '2024-01-01', '2025-01-01', 800.00, 'Mensuel', 'Actif', NULL, NULL, 1, 2, 0, '2025-06-30 09:01:52', 10.00, 0, 0.00, '2025-06-30 09:01:52'),
(4, 2, 2, 'CTR-002', '2024-03-01', '2025-03-01', 600.00, 'Mensuel', 'Actif', NULL, NULL, 2, 3, 0, '2025-06-30 09:01:52', 12.00, 0, 0.00, '2025-06-30 09:01:52');

--
-- Déclencheurs `contracts`
--
DELIMITER $$
CREATE TRIGGER `after_contract_insert` AFTER INSERT ON `contracts` FOR EACH ROW BEGIN
    CALL CalculateCommission(NEW.id);
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Structure de la table `contrats_vie`
--

CREATE TABLE `contrats_vie` (
  `id` int NOT NULL,
  `customer_id` int NOT NULL,
  `type` enum('vie','deces','mixte') NOT NULL,
  `capital` decimal(12,2) NOT NULL,
  `duree` int NOT NULL,
  `prime_mensuelle` decimal(12,2) NOT NULL,
  `date_souscription` date NOT NULL,
  `end_date` date NOT NULL,
  `statut` enum('actif','résilié') DEFAULT 'actif'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `courtiers`
--

CREATE TABLE `courtiers` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `contact_person` varchar(100) DEFAULT NULL,
  `city` varchar(100) DEFAULT NULL,
  `address` text,
  `user_id` int NOT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `customers`
--

CREATE TABLE `customers` (
  `id` int NOT NULL,
  `civility` enum('MR','Mme','Mlle','Personne morale') NOT NULL,
  `first_name` varchar(100) NOT NULL,
  `last_name` varchar(100) NOT NULL,
  `birth_date` date DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address_line1` text,
  `address_line2` text,
  `postal_code` varchar(10) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `country` varchar(50) DEFAULT 'BURUNDI',
  `marital_status` enum('SINGLE','MARRIED','DIVORCED','WIDOWED') DEFAULT NULL,
  `profession` varchar(100) DEFAULT NULL,
  `tax_id` varchar(50) DEFAULT NULL,
  `kyc_status` enum('PENDING','VERIFIED','REJECTED') DEFAULT 'PENDING',
  `marketing_consent` tinyint(1) DEFAULT '0',
  `notes` text,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

--
-- Déchargement des données de la table `customers`
--

INSERT INTO `customers` (`id`, `civility`, `first_name`, `last_name`, `birth_date`, `email`, `phone`, `address_line1`, `address_line2`, `postal_code`, `city`, `country`, `marital_status`, `profession`, `tax_id`, `kyc_status`, `marketing_consent`, `notes`, `created_at`, `updated_at`) VALUES
(1, 'MR', 'Michel', 'Durand', '1980-05-15', 'michel.durand@example.com', '0601234567', '5 rue de Paris', NULL, '75001', 'Paris', 'BURUNDI', 'MARRIED', 'Comptable', 'TX12345678', 'PENDING', 0, NULL, '2025-06-30 09:00:44', '2025-06-30 09:00:44'),
(2, 'Mme', 'Sophie', 'Martin', '1990-10-20', 'sophie.martin@example.com', '0602345678', '8 boulevard Haussmann', NULL, '75009', 'Paris', 'BURUNDI', 'SINGLE', 'Infirmière', 'TX87654321', 'PENDING', 0, NULL, '2025-06-30 09:00:44', '2025-06-30 09:00:44');

-- --------------------------------------------------------

--
-- Structure de la table `customer_contacts`
--

CREATE TABLE `customer_contacts` (
  `id` int NOT NULL,
  `customer_id` int NOT NULL,
  `contact_type` enum('PRIMARY','SECONDARY','EMERGENCY','FAMILY') NOT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `relationship` varchar(50) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `email` varchar(100) DEFAULT NULL,
  `is_authorized` tinyint(1) DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `customer_documents`
--

CREATE TABLE `customer_documents` (
  `document_id` int NOT NULL,
  `customer_id` int NOT NULL,
  `document_type` enum('ID_PROOF','PROOF_OF_ADDRESS','TAX_FORM','CONTRACT') NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `upload_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `validated` tinyint(1) DEFAULT '0',
  `validation_date` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `documents`
--

CREATE TABLE `documents` (
  `id` int NOT NULL,
  `entity_type` enum('client','contract','claim','quote') NOT NULL,
  `entity_id` int NOT NULL,
  `document_type` varchar(100) NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `upload_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `uploaded_by` int NOT NULL,
  `description` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `insurance_companies`
--

CREATE TABLE `insurance_companies` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `NIF` varchar(14) DEFAULT NULL,
  `Registre_com` varchar(50) DEFAULT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `address` text,
  `postal_code` varchar(10) DEFAULT NULL,
  `city` varchar(50) DEFAULT NULL,
  `country` varchar(50) DEFAULT 'France',
  `contact_person` varchar(100) DEFAULT NULL,
  `contact_email` varchar(100) DEFAULT NULL,
  `contact_phone` varchar(20) DEFAULT NULL,
  `commission_rate` decimal(5,2) DEFAULT '15.00',
  `payment_terms` varchar(255) DEFAULT NULL,
  `contract_start_date` date DEFAULT NULL,
  `contract_end_date` date DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `notes` text,
  `logo_path` varchar(200) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

--
-- Déchargement des données de la table `insurance_companies`
--

INSERT INTO `insurance_companies` (`id`, `name`, `NIF`, `Registre_com`, `email`, `phone`, `address`, `postal_code`, `city`, `country`, `contact_person`, `contact_email`, `contact_phone`, `commission_rate`, `payment_terms`, `contract_start_date`, `contract_end_date`, `is_active`, `notes`, `logo_path`, `created_at`, `updated_at`) VALUES
(1, 'AssurPlus', 'NIF12345678', 'RC98765', 'contact@assurplus.fr', '0601020304', '10 rue des Lilas', NULL, 'Paris', 'France', 'M. Durand', 'durand@assurplus.fr', '0601020305', 15.00, '30 jours', '2024-01-01', '2026-01-01', 1, NULL, 'assurplus.png', '2025-06-30 09:00:44', '2025-06-30 09:00:44'),
(2, 'VitalAssur', 'NIF22334455', 'RC54321', 'contact@vitalassur.fr', '0602030405', '25 avenue de la République', NULL, 'Lyon', 'France', 'Mme Dupuis', 'dupuis@vitalassur.fr', '0602030406', 15.00, '60 jours', '2023-06-01', '2025-06-01', 1, NULL, 'vitalassur.png', '2025-06-30 09:00:44', '2025-06-30 09:00:44');

-- --------------------------------------------------------

--
-- Structure de la table `insurance_types`
--

CREATE TABLE `insurance_types` (
  `id` int NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text,
  `category_id` int NOT NULL,
  `company_id` int DEFAULT NULL,
  `is_active` tinyint(1) DEFAULT '1',
  `garanties` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

--
-- Déchargement des données de la table `insurance_types`
--

INSERT INTO `insurance_types` (`id`, `name`, `description`, `category_id`, `company_id`, `is_active`, `garanties`) VALUES
(1, 'Assurance auto classique', 'Formule standard pour véhicule particulier', 1, 1, 1, NULL),
(2, 'Assurance santé premium', 'Couverture santé étendue', 2, 2, 1, NULL),
(3, 'Assurance habitation éco', 'Protection de base pour logement', 3, 1, 1, NULL);

-- --------------------------------------------------------

--
-- Structure de la table `life_contracts`
--

CREATE TABLE `life_contracts` (
  `id` int NOT NULL,
  `client_id` int NOT NULL,
  `company_id` int NOT NULL,
  `contract_number` varchar(50) NOT NULL,
  `contract_type` enum('vie','décès','mixte') NOT NULL DEFAULT 'vie',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `duration_years` int NOT NULL,
  `premium_mode` enum('unique','mensuel','trimestriel','annuel') DEFAULT 'mensuel',
  `premium_amount` decimal(15,2) NOT NULL,
  `total_paid` decimal(15,2) DEFAULT '0.00',
  `insured_amount` decimal(15,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'BIF',
  `status` enum('actif','suspendu','résilié','échu') DEFAULT 'actif',
  `termination_reason` text,
  `beneficiary_name` varchar(255) DEFAULT NULL,
  `beneficiary_relation` varchar(100) DEFAULT NULL,
  `beneficiary_nid` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `life_contract_beneficiaries`
--

CREATE TABLE `life_contract_beneficiaries` (
  `id` int NOT NULL,
  `contract_id` int NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `relationship` varchar(100) DEFAULT NULL,
  `percentage` int DEFAULT '100',
  `national_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `messages`
--

CREATE TABLE `messages` (
  `id` int NOT NULL,
  `sender_id` int NOT NULL,
  `receiver_id` int NOT NULL,
  `message` text,
  `attachment_path` varchar(255) DEFAULT NULL,
  `is_read` tinyint(1) DEFAULT '0',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `online_payments`
--

CREATE TABLE `online_payments` (
  `payment_id` int NOT NULL,
  `commission_id` int NOT NULL,
  `gateway` enum('stripe','paypal','e_noti','lumicash') NOT NULL,
  `transaction_id` varchar(255) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `fees` decimal(10,2) NOT NULL,
  `net_amount` decimal(10,2) NOT NULL,
  `currency` char(3) DEFAULT 'EUR',
  `payment_date` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `status` enum('pending','completed','failed','refunded') DEFAULT 'pending',
  `metadata` json DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int NOT NULL,
  `email` varchar(100) NOT NULL,
  `token` varchar(255) NOT NULL,
  `expires_at` datetime NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

--
-- Déchargement des données de la table `password_resets`
--

INSERT INTO `password_resets` (`id`, `email`, `token`, `expires_at`, `created_at`) VALUES
(2, 'client1@example.com', 'a549e4bc92a9ece5c7eca5b3864ff5fc', '2025-07-02 13:57:19', '2025-07-02 12:57:19');

-- --------------------------------------------------------

--
-- Structure de la table `payments`
--

CREATE TABLE `payments` (
  `payment_id` int NOT NULL,
  `contract_id` int NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `payment_date` date NOT NULL,
  `method` enum('Carte','Prélèvement','Virement','Chèque','Espèces','Mobile Money') NOT NULL,
  `status` enum('En attente','Validé','Refusé','Remboursé') DEFAULT 'En attente',
  `reference` varchar(100) DEFAULT NULL,
  `notes` text,
  `recorded_by` int DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `quotes`
--

CREATE TABLE `quotes` (
  `quote_id` int NOT NULL,
  `customer_id` int DEFAULT NULL,
  `type_id` int NOT NULL,
  `reference` varchar(50) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `validity_date` date NOT NULL,
  `status` enum('Brouillon','Envoyé','Accepté','Refusé','Expiré') DEFAULT 'Brouillon',
  `notes` text,
  `created_by` int NOT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

--
-- Déchargement des données de la table `quotes`
--

INSERT INTO `quotes` (`quote_id`, `customer_id`, `type_id`, `reference`, `amount`, `validity_date`, `status`, `notes`, `created_by`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 'QTE-001', 750.00, '2025-07-31', 'Envoyé', 'Demande rapide d’un devis santé premium', 2, '2025-06-30 17:02:15', '2025-06-30 17:02:15');

-- --------------------------------------------------------

--
-- Structure de la table `reseaux_sociaux`
--

CREATE TABLE `reseaux_sociaux` (
  `id` int NOT NULL,
  `nom` varchar(50) NOT NULL,
  `url` varchar(255) NOT NULL,
  `icone` varchar(100) DEFAULT NULL,
  `actif` tinyint(1) DEFAULT '1',
  `date_ajout` datetime DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

--
-- Déchargement des données de la table `reseaux_sociaux`
--

INSERT INTO `reseaux_sociaux` (`id`, `nom`, `url`, `icone`, `actif`, `date_ajout`) VALUES
(1, 'Facebook', 'https://facebook.com/bi.insuranceBrokers', 'fab fa-facebook', 1, '2025-06-30 13:38:23'),
(2, 'Twitter', 'https://twitter.com/bi_insuranceBrokers', 'fab fa-twitter', 1, '2025-06-30 13:38:23'),
(3, 'LinkedIn', 'https://linkedin.com/company/bi-insuranceBrokers', 'fab fa-linkedin', 1, '2025-06-30 13:38:23'),
(4, 'YouTube', 'https://youtube.com/@bi_insuranceBrokers', 'fab fa-youtube', 1, '2025-06-30 13:38:23');

-- --------------------------------------------------------

--
-- Structure de la table `souscriptions`
--

CREATE TABLE `souscriptions` (
  `id` int NOT NULL,
  `client_id` int NOT NULL,
  `police_id` int NOT NULL,
  `date_souscription` date NOT NULL,
  `montant` decimal(10,2) NOT NULL,
  `duree` int NOT NULL,
  `mode_paiement` varchar(50) NOT NULL,
  `etat` varchar(50) DEFAULT 'en attente',
  `piece_identite` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

--
-- Déchargement des données de la table `souscriptions`
--

INSERT INTO `souscriptions` (`id`, `client_id`, `police_id`, `date_souscription`, `montant`, `duree`, `mode_paiement`, `etat`, `piece_identite`) VALUES
(1, 4, 1, '2025-07-02', 750000.00, 6, '', 'en attente', NULL),
(2, 4, 1, '2025-07-02', 1500000.00, 12, '', 'en attente', NULL);

-- --------------------------------------------------------

--
-- Structure de la table `souscription_documents`
--

CREATE TABLE `souscription_documents` (
  `id` int NOT NULL,
  `souscription_id` int NOT NULL,
  `file_path` varchar(255) NOT NULL,
  `uploaded_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

-- --------------------------------------------------------

--
-- Structure de la table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('admin','courtier','gestionnaire','client','partenaire') DEFAULT 'courtier',
  `first_name` varchar(50) DEFAULT NULL,
  `last_name` varchar(50) DEFAULT NULL,
  `gender` varchar(15) DEFAULT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `insurance_company_id` int DEFAULT NULL,
  `last_login` datetime DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `status` enum('activé','désactivé') DEFAULT 'activé'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 ;

--
-- Déchargement des données de la table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `email`, `role`, `first_name`, `last_name`, `gender`, `phone`, `insurance_company_id`, `last_login`, `created_at`, `updated_at`, `status`) VALUES
(1, 'admin', '$2y$10$vLgjIBkn7uM9lpDJ/X3NEOFHYsfNB2FtFAxtIbTteOijt4NAZbdPK', 'admin@example.com', 'admin', 'Alice', 'Admin', 'female', '0600000001', NULL, '2025-07-07 12:12:12', '2025-06-30 09:00:44', '2025-07-17 07:32:18', 'activé'),
(2, 'courtier1', '$2y$10$vLgjIBkn7uM9lpDJ/X3NEOFHYsfNB2FtFAxtIbTteOijt4NAZbdPK', 'courtier1@example.com', 'courtier', 'Jean', 'Courtier', NULL, '0600000002', 1, NULL, '2025-06-30 09:00:44', '2025-07-17 07:33:03', 'activé'),
(3, 'partenaire', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye...', 'partenaire@example.com', 'partenaire', 'Julie', 'Partenaire', NULL, '0600000003', 2, NULL, '2025-06-30 09:00:44', '2025-06-30 09:00:44', 'activé'),
(4, 'client1', '$2y$10$HTVHUOr9UPAroHnCL5tJju3YRSWEOLTYj2Mh4xod/HVJStPMtcOTC', 'client1@example.com', 'client', 'Paul', 'Client', NULL, '0600000004', NULL, '2025-07-07 12:09:42', '2025-06-30 09:00:44', '2025-07-07 10:09:42', 'activé');

--
-- Index pour les tables déchargées
--

--
-- Index pour la table `analytics_commissions`
--
ALTER TABLE `analytics_commissions`
  ADD PRIMARY KEY (`fact_id`),
  ADD KEY `idx_date` (`date_id`),
  ADD KEY `idx_company` (`company_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Index pour la table `analytics_dim_date`
--
ALTER TABLE `analytics_dim_date`
  ADD PRIMARY KEY (`date_id`);

--
-- Index pour la table `analytics_kpis`
--
ALTER TABLE `analytics_kpis`
  ADD PRIMARY KEY (`kpi_id`),
  ADD KEY `date_id` (`date_id`);

--
-- Index pour la table `appointments`
--
ALTER TABLE `appointments`
  ADD PRIMARY KEY (`appointment_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `categories`
--
ALTER TABLE `categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Index pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Index pour la table `chat_notifications`
--
ALTER TABLE `chat_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `message_id` (`message_id`);

--
-- Index pour la table `chat_sessions`
--
ALTER TABLE `chat_sessions`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `claims`
--
ALTER TABLE `claims`
  ADD PRIMARY KEY (`claim_id`),
  ADD KEY `contract_id` (`contract_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Index pour la table `commissions`
--
ALTER TABLE `commissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contract_id` (`contract_id`),
  ADD KEY `company_id` (`company_id`);

--
-- Index pour la table `commission_payments`
--
ALTER TABLE `commission_payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Index pour la table `commission_rules`
--
ALTER TABLE `commission_rules`
  ADD PRIMARY KEY (`id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Index pour la table `commission_tiers`
--
ALTER TABLE `commission_tiers`
  ADD PRIMARY KEY (`tier_id`),
  ADD KEY `rule_id` (`rule_id`);

--
-- Index pour la table `company_products`
--
ALTER TABLE `company_products`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `company_id` (`company_id`,`product_code`),
  ADD KEY `type_id` (`type_id`);

--
-- Index pour la table `contracts`
--
ALTER TABLE `contracts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `type_id` (`type_id`),
  ADD KEY `company_id` (`company_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Index pour la table `contrats_vie`
--
ALTER TABLE `contrats_vie`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Index pour la table `courtiers`
--
ALTER TABLE `courtiers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD KEY `user_id` (`user_id`);

--
-- Index pour la table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `idx_customer_name` (`last_name`,`first_name`),
  ADD KEY `idx_customer_email` (`email`);

--
-- Index pour la table `customer_contacts`
--
ALTER TABLE `customer_contacts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`);

--
-- Index pour la table `customer_documents`
--
ALTER TABLE `customer_documents`
  ADD PRIMARY KEY (`document_id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `idx_document_type` (`document_type`);

--
-- Index pour la table `documents`
--
ALTER TABLE `documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `uploaded_by` (`uploaded_by`);

--
-- Index pour la table `insurance_companies`
--
ALTER TABLE `insurance_companies`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `phone` (`phone`),
  ADD UNIQUE KEY `NIF` (`NIF`);

--
-- Index pour la table `insurance_types`
--
ALTER TABLE `insurance_types`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `company_id` (`company_id`);

--
-- Index pour la table `life_contracts`
--
ALTER TABLE `life_contracts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `contract_number` (`contract_number`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `company_id` (`company_id`);

--
-- Index pour la table `life_contract_beneficiaries`
--
ALTER TABLE `life_contract_beneficiaries`
  ADD PRIMARY KEY (`id`),
  ADD KEY `contract_id` (`contract_id`);

--
-- Index pour la table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `receiver_id` (`receiver_id`);

--
-- Index pour la table `online_payments`
--
ALTER TABLE `online_payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `commission_id` (`commission_id`);

--
-- Index pour la table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `email` (`email`);

--
-- Index pour la table `payments`
--
ALTER TABLE `payments`
  ADD PRIMARY KEY (`payment_id`),
  ADD KEY `contract_id` (`contract_id`),
  ADD KEY `recorded_by` (`recorded_by`);

--
-- Index pour la table `quotes`
--
ALTER TABLE `quotes`
  ADD PRIMARY KEY (`quote_id`),
  ADD UNIQUE KEY `reference` (`reference`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `type_id` (`type_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Index pour la table `reseaux_sociaux`
--
ALTER TABLE `reseaux_sociaux`
  ADD PRIMARY KEY (`id`);

--
-- Index pour la table `souscriptions`
--
ALTER TABLE `souscriptions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `police_id` (`police_id`);

--
-- Index pour la table `souscription_documents`
--
ALTER TABLE `souscription_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `souscription_id` (`souscription_id`);

--
-- Index pour la table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`),
  ADD KEY `insurance_company_id` (`insurance_company_id`);

--
-- AUTO_INCREMENT pour les tables déchargées
--

--
-- AUTO_INCREMENT pour la table `analytics_commissions`
--
ALTER TABLE `analytics_commissions`
  MODIFY `fact_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `analytics_kpis`
--
ALTER TABLE `analytics_kpis`
  MODIFY `kpi_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `appointments`
--
ALTER TABLE `appointments`
  MODIFY `appointment_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `categories`
--
ALTER TABLE `categories`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `chat_notifications`
--
ALTER TABLE `chat_notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `chat_sessions`
--
ALTER TABLE `chat_sessions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `claims`
--
ALTER TABLE `claims`
  MODIFY `claim_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `commissions`
--
ALTER TABLE `commissions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `commission_payments`
--
ALTER TABLE `commission_payments`
  MODIFY `payment_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `commission_rules`
--
ALTER TABLE `commission_rules`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `commission_tiers`
--
ALTER TABLE `commission_tiers`
  MODIFY `tier_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `company_products`
--
ALTER TABLE `company_products`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `contracts`
--
ALTER TABLE `contracts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `contrats_vie`
--
ALTER TABLE `contrats_vie`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `courtiers`
--
ALTER TABLE `courtiers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `customer_contacts`
--
ALTER TABLE `customer_contacts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `customer_documents`
--
ALTER TABLE `customer_documents`
  MODIFY `document_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `documents`
--
ALTER TABLE `documents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `insurance_companies`
--
ALTER TABLE `insurance_companies`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `insurance_types`
--
ALTER TABLE `insurance_types`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT pour la table `life_contracts`
--
ALTER TABLE `life_contracts`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `life_contract_beneficiaries`
--
ALTER TABLE `life_contract_beneficiaries`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `online_payments`
--
ALTER TABLE `online_payments`
  MODIFY `payment_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `payments`
--
ALTER TABLE `payments`
  MODIFY `payment_id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `quotes`
--
ALTER TABLE `quotes`
  MODIFY `quote_id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT pour la table `reseaux_sociaux`
--
ALTER TABLE `reseaux_sociaux`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT pour la table `souscriptions`
--
ALTER TABLE `souscriptions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT pour la table `souscription_documents`
--
ALTER TABLE `souscription_documents`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT pour la table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- Contraintes pour les tables déchargées
--

--
-- Contraintes pour la table `analytics_commissions`
--
ALTER TABLE `analytics_commissions`
  ADD CONSTRAINT `analytics_commissions_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `insurance_companies` (`id`),
  ADD CONSTRAINT `analytics_commissions_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `company_products` (`id`);

--
-- Contraintes pour la table `analytics_kpis`
--
ALTER TABLE `analytics_kpis`
  ADD CONSTRAINT `analytics_kpis_ibfk_1` FOREIGN KEY (`date_id`) REFERENCES `analytics_dim_date` (`date_id`);

--
-- Contraintes pour la table `appointments`
--
ALTER TABLE `appointments`
  ADD CONSTRAINT `appointments_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `appointments_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `chat_messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `chat_messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `chat_notifications`
--
ALTER TABLE `chat_notifications`
  ADD CONSTRAINT `chat_notifications_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `chat_notifications_ibfk_2` FOREIGN KEY (`message_id`) REFERENCES `chat_messages` (`id`);

--
-- Contraintes pour la table `claims`
--
ALTER TABLE `claims`
  ADD CONSTRAINT `claims_ibfk_1` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`),
  ADD CONSTRAINT `claims_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `commissions`
--
ALTER TABLE `commissions`
  ADD CONSTRAINT `commissions_ibfk_1` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`),
  ADD CONSTRAINT `commissions_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `insurance_companies` (`id`);

--
-- Contraintes pour la table `commission_payments`
--
ALTER TABLE `commission_payments`
  ADD CONSTRAINT `commission_payments_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `insurance_companies` (`id`),
  ADD CONSTRAINT `commission_payments_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `commission_rules`
--
ALTER TABLE `commission_rules`
  ADD CONSTRAINT `commission_rules_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `insurance_companies` (`id`),
  ADD CONSTRAINT `commission_rules_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `company_products` (`id`);

--
-- Contraintes pour la table `commission_tiers`
--
ALTER TABLE `commission_tiers`
  ADD CONSTRAINT `commission_tiers_ibfk_1` FOREIGN KEY (`rule_id`) REFERENCES `commission_rules` (`id`);

--
-- Contraintes pour la table `company_products`
--
ALTER TABLE `company_products`
  ADD CONSTRAINT `company_products_ibfk_1` FOREIGN KEY (`company_id`) REFERENCES `insurance_companies` (`id`),
  ADD CONSTRAINT `company_products_ibfk_2` FOREIGN KEY (`type_id`) REFERENCES `insurance_types` (`id`);

--
-- Contraintes pour la table `contracts`
--
ALTER TABLE `contracts`
  ADD CONSTRAINT `contracts_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `contracts_ibfk_2` FOREIGN KEY (`type_id`) REFERENCES `insurance_types` (`id`),
  ADD CONSTRAINT `contracts_ibfk_3` FOREIGN KEY (`company_id`) REFERENCES `insurance_companies` (`id`),
  ADD CONSTRAINT `contracts_ibfk_4` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `contrats_vie`
--
ALTER TABLE `contrats_vie`
  ADD CONSTRAINT `contrats_vie_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`);

--
-- Contraintes pour la table `courtiers`
--
ALTER TABLE `courtiers`
  ADD CONSTRAINT `courtiers_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `customer_contacts`
--
ALTER TABLE `customer_contacts`
  ADD CONSTRAINT `customer_contacts_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `customer_documents`
--
ALTER TABLE `customer_documents`
  ADD CONSTRAINT `customer_documents_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `documents`
--
ALTER TABLE `documents`
  ADD CONSTRAINT `documents_ibfk_1` FOREIGN KEY (`uploaded_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `insurance_types`
--
ALTER TABLE `insurance_types`
  ADD CONSTRAINT `insurance_types_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `categories` (`id`),
  ADD CONSTRAINT `insurance_types_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `insurance_companies` (`id`);

--
-- Contraintes pour la table `life_contracts`
--
ALTER TABLE `life_contracts`
  ADD CONSTRAINT `life_contracts_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `life_contracts_ibfk_2` FOREIGN KEY (`company_id`) REFERENCES `insurance_companies` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `life_contract_beneficiaries`
--
ALTER TABLE `life_contract_beneficiaries`
  ADD CONSTRAINT `life_contract_beneficiaries_ibfk_1` FOREIGN KEY (`contract_id`) REFERENCES `life_contracts` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `messages_ibfk_2` FOREIGN KEY (`receiver_id`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `online_payments`
--
ALTER TABLE `online_payments`
  ADD CONSTRAINT `online_payments_ibfk_1` FOREIGN KEY (`commission_id`) REFERENCES `commissions` (`id`);

--
-- Contraintes pour la table `payments`
--
ALTER TABLE `payments`
  ADD CONSTRAINT `payments_ibfk_1` FOREIGN KEY (`contract_id`) REFERENCES `contracts` (`id`),
  ADD CONSTRAINT `payments_ibfk_2` FOREIGN KEY (`recorded_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `quotes`
--
ALTER TABLE `quotes`
  ADD CONSTRAINT `quotes_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `quotes_ibfk_2` FOREIGN KEY (`type_id`) REFERENCES `insurance_types` (`id`),
  ADD CONSTRAINT `quotes_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Contraintes pour la table `souscriptions`
--
ALTER TABLE `souscriptions`
  ADD CONSTRAINT `souscriptions_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `souscriptions_ibfk_2` FOREIGN KEY (`police_id`) REFERENCES `company_products` (`id`);

--
-- Contraintes pour la table `souscription_documents`
--
ALTER TABLE `souscription_documents`
  ADD CONSTRAINT `souscription_documents_ibfk_1` FOREIGN KEY (`souscription_id`) REFERENCES `souscriptions` (`id`) ON DELETE CASCADE;

--
-- Contraintes pour la table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `users_ibfk_1` FOREIGN KEY (`insurance_company_id`) REFERENCES `insurance_companies` (`id`);
