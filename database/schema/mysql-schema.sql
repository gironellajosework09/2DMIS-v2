/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;
DROP TABLE IF EXISTS `gender`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `gender` (
  `id` int(4) DEFAULT NULL,
  `program` varchar(5) DEFAULT NULL,
  `full_name` varchar(42) DEFAULT NULL,
  `sex` varchar(6) DEFAULT NULL,
  KEY `idx_full_name_gender` (`full_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `password_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `changed_by` varchar(100) NOT NULL,
  `changed_for` varchar(100) NOT NULL,
  `changed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_changed_at` (`changed_at`),
  KEY `idx_changed_for` (`changed_for`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_absent`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_absent` (
  `full_name` varchar(36) DEFAULT NULL,
  `Count` varchar(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_audit_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_audit_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `target_table` varchar(100) NOT NULL,
  `target_id` int(11) NOT NULL,
  `old_value` text DEFAULT NULL,
  `new_value` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_audit_table_date` (`target_table`,`created_at`),
  KEY `idx_audit_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_barangays`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_barangays` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `municipality_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `municipality_id` (`municipality_id`),
  CONSTRAINT `tbl_barangays_ibfk_1` FOREIGN KEY (`municipality_id`) REFERENCES `tbl_municipalities` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_client_aff_orgs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_client_aff_orgs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `organization` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `tbl_client_aff_orgs_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `tbl_clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_client_photos`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_client_photos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `photo_path` varchar(255) NOT NULL,
  `captured_from` enum('UPLOAD','CAMERA') DEFAULT 'UPLOAD',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `tbl_client_photos_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `tbl_clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_clients` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `family_id` int(11) DEFAULT NULL,
  `household_id` int(11) DEFAULT NULL,
  `lastname` varchar(100) NOT NULL,
  `firstname` varchar(100) NOT NULL,
  `middlename` varchar(100) DEFAULT NULL,
  `extensionname` varchar(20) DEFAULT NULL,
  `region` varchar(100) NOT NULL DEFAULT 'Region I',
  `province` varchar(100) NOT NULL DEFAULT 'Ilocos Sur',
  `city_municipality` varchar(100) NOT NULL,
  `barangay` varchar(100) NOT NULL,
  `house_no` varchar(50) DEFAULT NULL,
  `mobile_no` varchar(15) DEFAULT NULL,
  `email` varchar(255) NOT NULL,
  `birthdate` date NOT NULL,
  `age` int(11) NOT NULL,
  `sex` enum('MALE','FEMALE') NOT NULL,
  `civil_status` enum('SINGLE','MARRIED','WIDOWED') NOT NULL,
  `pwd` enum('YES','NO') DEFAULT NULL,
  `ip` enum('YES','NO') DEFAULT NULL,
  `ip_group` varchar(255) DEFAULT NULL,
  `occupation` varchar(100) DEFAULT NULL,
  `monthly_income` decimal(10,2) DEFAULT NULL,
  `category` enum('MINOR (0-17)','YOUTH (18-29)','ADULT (30-59)','SENIOR CITIZEN (60 AND ABOVE)') NOT NULL,
  `aff_org` varchar(255) NOT NULL,
  `precinct_no` varchar(50) DEFAULT NULL,
  `voter_id` varchar(50) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `full_name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `match_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_fullname_location` (`lastname`,`firstname`,`middlename`,`barangay`,`precinct_no`),
  KEY `idx_clients_name` (`lastname`,`firstname`,`middlename`),
  KEY `idx_clients_muni` (`city_municipality`),
  KEY `idx_clients_brgy` (`barangay`),
  KEY `idx_fullname` (`full_name`),
  KEY `idx_client_match_name` (`match_name`),
  KEY `idx_full_name_clients` (`full_name`),
  KEY `fk_client_household` (`household_id`),
  FULLTEXT KEY `idx_clients_fullname` (`lastname`,`firstname`,`middlename`),
  CONSTRAINT `fk_client_household` FOREIGN KEY (`household_id`) REFERENCES `tbl_household` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_details` (
  `id` int(4) DEFAULT NULL,
  `status` varchar(8) DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `email` varchar(44) DEFAULT NULL,
  `BARANGAY` varchar(56) DEFAULT NULL,
  `TOWN` varchar(18) DEFAULT NULL,
  `birthdate` varchar(10) DEFAULT NULL,
  `civil_status` varchar(14) DEFAULT NULL,
  `mobile_no` varchar(33) DEFAULT NULL,
  KEY `idx_full_name` (`full_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_exam`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_exam` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_no` varchar(255) NOT NULL,
  `fullname` varchar(255) NOT NULL,
  `barangay` varchar(255) NOT NULL,
  `town` varchar(255) NOT NULL,
  `email_address` varchar(255) NOT NULL,
  `contact` varchar(255) NOT NULL,
  `school` varchar(255) NOT NULL,
  `course` varchar(255) NOT NULL,
  `year` int(11) NOT NULL,
  `scholarship` varchar(255) NOT NULL,
  `exam_date` varchar(255) NOT NULL,
  `exam_time` varchar(255) NOT NULL,
  `permit_confirmed` tinyint(1) DEFAULT 0,
  `score` varchar(255) NOT NULL,
  `normalized_name` varchar(255) GENERATED ALWAYS AS (lcase(trim(`fullname`))) STORED,
  PRIMARY KEY (`id`),
  KEY `idx_exam_exam_no` (`exam_no`),
  KEY `idx_exam_fullname` (`fullname`),
  KEY `idx_exam_scholarship` (`scholarship`),
  KEY `idx_normalized_name_exam` (`normalized_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_family_members`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_family_members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `relative_id` int(11) NOT NULL,
  `relationship` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `client_id` (`client_id`,`relative_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_gip_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_gip_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `valid_govt_id` varchar(100) DEFAULT NULL,
  `id_number` varchar(150) DEFAULT NULL,
  `insurance_beneficiary` varchar(255) DEFAULT NULL,
  `emergency_contact` varchar(255) DEFAULT NULL,
  `ecp_contact_number` varchar(50) DEFAULT NULL,
  `ecp_address` text DEFAULT NULL,
  `college` varchar(255) DEFAULT NULL,
  `course` varchar(255) DEFAULT NULL,
  `year_graduated` year(4) DEFAULT NULL,
  `high_school` varchar(255) DEFAULT NULL,
  `elementary_school` varchar(255) DEFAULT NULL,
  `latest_work_experience` text DEFAULT NULL,
  `position` varchar(255) DEFAULT NULL,
  `period_of_engagement` varchar(150) DEFAULT NULL,
  `special_skills` text DEFAULT NULL,
  `achievements` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `normalized_name` varchar(255) DEFAULT NULL,
  `match_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_household`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_household` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `household_id` varchar(20) NOT NULL,
  `head_household` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `household_id` (`household_id`),
  UNIQUE KEY `household_id_2` (`household_id`),
  KEY `fk_head_household` (`head_household`),
  CONSTRAINT `fk_head_household` FOREIGN KEY (`head_household`) REFERENCES `tbl_clients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_kababaihan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_kababaihan` (
  `full_name` varchar(41) DEFAULT NULL,
  `town` varchar(18) DEFAULT NULL,
  `barangay` varchar(38) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_multi_device_exemptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_multi_device_exemptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_municipalities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_municipalities` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `code` varchar(5) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `m_n` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_payout_scans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_payout_scans` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) NOT NULL,
  `scanned_text` varchar(255) NOT NULL,
  `scanned_by` int(11) NOT NULL,
  `scanned_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_scan` (`transaction_id`),
  KEY `idx_transaction_id` (`transaction_id`),
  KEY `idx_scanned_by` (`scanned_by`),
  KEY `idx_scanned_at` (`scanned_at`),
  KEY `ps_tid` (`transaction_id`),
  KEY `ps_sb` (`scanned_by`),
  KEY `ps_sa` (`scanned_at`),
  CONSTRAINT `tbl_payout_scans_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `tbl_transactions` (`id`),
  CONSTRAINT `tbl_payout_scans_ibfk_2` FOREIGN KEY (`scanned_by`) REFERENCES `tbl_users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_payout_scans2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_payout_scans2` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) NOT NULL,
  `scanned_text` varchar(255) NOT NULL,
  `scanned_by` int(11) NOT NULL,
  `scanned_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_scan` (`transaction_id`),
  KEY `idx_transaction_id` (`transaction_id`),
  KEY `idx_scanned_by` (`scanned_by`),
  KEY `idx_scanned_at` (`scanned_at`),
  KEY `ps_tid` (`transaction_id`),
  KEY `ps_sb` (`scanned_by`),
  KEY `ps_sa` (`scanned_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_payout_scans_unpaid`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_payout_scans_unpaid` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` int(11) NOT NULL,
  `scanned_text` varchar(255) DEFAULT NULL,
  `scanned_by` int(11) NOT NULL,
  `scanned_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_scan` (`transaction_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `page_name` varchar(100) NOT NULL,
  `can_access` tinyint(1) DEFAULT 1,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_photo_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_photo_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `remarks` text DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `log_date` datetime DEFAULT current_timestamp(),
  `before_photo` varchar(255) DEFAULT NULL,
  `after_photo` varchar(255) DEFAULT NULL,
  `program` varchar(20) DEFAULT NULL,
  `ip_address` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  CONSTRAINT `tbl_photo_logs_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `tbl_clients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_program_permissions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_program_permissions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `program_name` varchar(100) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_results` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `exam_no` varchar(255) NOT NULL,
  `score` varchar(255) NOT NULL,
  `approved` varchar(255) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_results_exam_no` (`exam_no`),
  KEY `idx_results_score` (`score`),
  KEY `idx_results_approved` (`approved`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_scholar_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_scholar_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `program` enum('CEDSSG','CEAP','CEDSSG_NEW','CEAP_NEW','OTEA','OTCES') NOT NULL,
  `school` varchar(255) NOT NULL,
  `school_type` varchar(255) DEFAULT NULL,
  `campus` varchar(255) DEFAULT NULL,
  `college_department` varchar(255) DEFAULT NULL,
  `course` varchar(255) DEFAULT NULL,
  `year_level` varchar(50) DEFAULT NULL,
  `is_regular` tinyint(1) DEFAULT 1,
  `year_started` varchar(255) NOT NULL,
  `landbank_no` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `normalized_name` varchar(255) GENERATED ALWAYS AS (lcase(trim(`full_name`))) STORED,
  `match_name` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `idx_normalized_name` (`normalized_name`),
  KEY `idx_scholar_match_name` (`match_name`),
  CONSTRAINT `tbl_scholar_info_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `tbl_clients` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_seats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_seats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `program` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `town` varchar(100) DEFAULT NULL,
  `section` varchar(100) DEFAULT NULL,
  `box` varchar(50) DEFAULT NULL,
  `row` varchar(50) DEFAULT NULL,
  `seat` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `s_pn` (`program`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_seats2`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_seats2` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `program` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `town` varchar(100) DEFAULT NULL,
  `section` varchar(100) DEFAULT NULL,
  `box` varchar(50) DEFAULT NULL,
  `row` varchar(50) DEFAULT NULL,
  `seat` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `s_pn` (`program`,`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_transactions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `program` enum('AICS','AKAP','MAIP','TUPAD','CEDSSG','CEAP','CEAP_NEW','OTCES','OTEA','CEDSSG_NEW','COFFEE GROWERS','PUSO TI KABABAIHAN','PUSO TI AGTUTUBO','PUSO TI MANNALON','TESDA','GIP','TODA') NOT NULL,
  `patient_name` varchar(255) DEFAULT NULL,
  `date_applied` date NOT NULL,
  `type` varchar(255) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `comments` text DEFAULT NULL,
  `suggested_amount` decimal(10,2) DEFAULT NULL,
  `status` varchar(50) DEFAULT NULL,
  `amount_paid` decimal(10,2) DEFAULT NULL,
  `payout_date` date DEFAULT NULL,
  `date_paid` date DEFAULT NULL,
  `gwa` varchar(255) DEFAULT NULL,
  `units` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_program` (`program`),
  KEY `idx_client_id` (`client_id`),
  KEY `idx_date_applied` (`date_applied`),
  KEY `idx_payout_date` (`payout_date`),
  KEY `idx_date_paid` (`date_paid`),
  KEY `t_prg` (`program`),
  KEY `t_cid` (`client_id`),
  KEY `t_da` (`date_applied`),
  KEY `t_pd` (`payout_date`),
  KEY `t_dp` (`date_paid`),
  CONSTRAINT `tbl_transactions_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `tbl_clients` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_unpaid_verifications`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_unpaid_verifications` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) NOT NULL,
  `municipality_id` int(11) NOT NULL,
  `is_proxy` tinyint(1) DEFAULT 0,
  `proxy_lastname` varchar(100) DEFAULT NULL,
  `proxy_firstname` varchar(100) DEFAULT NULL,
  `proxy_middlename` varchar(100) DEFAULT NULL,
  `proxy_relationship` varchar(100) DEFAULT NULL,
  `proxy_phone` varchar(50) DEFAULT NULL,
  `proxy_birthdate` date DEFAULT NULL,
  `proxy_gender` varchar(20) DEFAULT NULL,
  `proxy_occupation` varchar(100) DEFAULT NULL,
  `proxy_monthlyincome` varchar(50) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `client_id` (`client_id`),
  KEY `municipality_id` (`municipality_id`),
  CONSTRAINT `tbl_unpaid_verifications_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `tbl_clients` (`id`),
  CONSTRAINT `tbl_unpaid_verifications_ibfk_2` FOREIGN KEY (`municipality_id`) REFERENCES `tbl_municipalities` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_update_logs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_update_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `client_id` int(11) DEFAULT NULL,
  `full_name` varchar(255) DEFAULT NULL,
  `ip_address` varchar(100) DEFAULT NULL,
  `action` varchar(255) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `tbl_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `tbl_users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_activity` datetime DEFAULT NULL,
  `session_token` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  KEY `u_un` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
DROP TABLE IF EXISTS `temp_details`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `temp_details` (
  `full_name` varchar(255) DEFAULT NULL,
  `mobile_no` varchar(33) DEFAULT NULL,
  `birthdate` varchar(10) DEFAULT NULL,
  `civil_status` varchar(14) DEFAULT NULL,
  `email` varchar(44) DEFAULT NULL,
  KEY `idx_full_name` (`full_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

