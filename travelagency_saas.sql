-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 04, 2025 at 07:45 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `travelagency_saas`
--

-- --------------------------------------------------------

--
-- Table structure for table `activity_log`
--

CREATE TABLE `activity_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `table_name` varchar(100) NOT NULL,
  `record_id` int(11) DEFAULT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `activity_log`
--

INSERT INTO `activity_log` (`id`, `tenant_id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1115, 1, 1, 'update', 'settings', 1, '{\"id\":1,\"agency_name\":\"Al Moqadas\",\"title\":\"Al Moqadas Travel Agency\",\"phone\":\"0786011115\",\"email\":\"almuqadas_travel@yahoo.com\",\"address\":\"Jada-e-Maiwand, KABUL , AFGHANISTAN\",\"logo\":\"logo.png\"}', '{\"id\":1,\"agency_name\":\"Al Moqadas a\",\"title\":\"Al Moqadas Travel Agency\",\"phone\":\"0786011115\",\"email\":\"almuqadas_travel@yahoo.com\",\"address\":\"Jada-e-Maiwand, KABUL , AFGHANISTAN\",\"logo\":\"logo.png\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-26 07:49:30'),
(1116, 1, 1, 'update', 'settings', 1, '{\"id\":1,\"agency_name\":\"Al Moqadas a\",\"title\":\"Al Moqadas Travel Agency\",\"phone\":\"0786011115\",\"email\":\"almuqadas_travel@yahoo.com\",\"address\":\"Jada-e-Maiwand, KABUL , AFGHANISTAN\",\"logo\":\"logo.png\"}', '{\"id\":1,\"agency_name\":\"Al Moqadas\",\"title\":\"Al Moqadas Travel Agency\",\"phone\":\"0786011115\",\"email\":\"almuqadas_travel@yahoo.com\",\"address\":\"Jada-e-Maiwand, KABUL , AFGHANISTAN\",\"logo\":\"logo.png\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-26 07:49:36'),
(1117, 1, 1, 'add', 'main_account', 11, '[]', '{\"name\":\"AZIZI BANK\",\"account_type\":\"bank\",\"bank_account_number\":\"0325313513\",\"bank_name\":\"azizi\",\"usd_balance\":\"0\",\"afs_balance\":\"0\",\"status\":\"active\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-31 11:37:15'),
(1118, 1, 1, 'fund', 'main_account', 11, '{\"account_id\":\"11\",\"usd_balance\":\"3000.000\"}', '{\"account_id\":\"11\",\"usd_balance\":4000,\"amount\":1000,\"currency\":\"USD\",\"description\":\"Account funded by Sabaoon. Remarks: test. Receipt: t43t4tw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-31 11:39:28'),
(1119, 1, 1, 'update', 'main_account_transactions', 795, '{\"transaction_id\":\"795\",\"transaction_type\":\"main\",\"amount\":1000,\"type\":\"credit\",\"date\":\"2025-08-31 16:09:28\"}', '{\"amount\":1000,\"type\":\"credit\",\"date\":\"2025-08-31T11:39\",\"receipt\":\"t43t4tw\",\"description\":\"Account funded by Sabaoon. Remarks: test. Receipt: t43t4tw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-31 11:39:48'),
(1120, 1, 1, 'update', 'main_account', 11, '{\"name\":\"AZIZI BANK\",\"account_type\":\"bank\",\"bank_account_number\":\"0325313513\",\"status\":\"active\"}', '{\"name\":\"AZIZI BANK\",\"account_type\":\"bank\",\"bank_account_number\":\"0325313513\",\"status\":\"active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-31 11:45:43'),
(1121, 1, 1, 'transfer', 'main_account_transactions', NULL, '{}', '{\"from_account_id\":\"11\",\"from_account_name\":\"AZIZI BANK\",\"from_currency\":\"USD\",\"to_account_id\":\"11\",\"to_account_name\":\"AZIZI BANK\",\"to_currency\":\"AFS\",\"amount\":200,\"converted_amount\":14000,\"exchange_rate\":70,\"description\":\"tesdtd\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-31 11:53:44'),
(1122, 1, 1, 'delete', 'main_account_transactions', 799, '{\"main_account_id\":11,\"transaction_id\":799,\"amount\":\"14000.000\",\"currency\":\"AFS\",\"type\":\"credit\",\"created_at\":\"2025-08-31 16:23:44\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-31 11:55:36'),
(1123, 1, 1, 'update', 'main_account', 11, '{\"name\":\"AZIZI BANK\",\"account_type\":\"bank\",\"bank_account_number\":\"0325313513\",\"status\":\"active\"}', '{\"name\":\"AZIZI BANKadf\",\"account_type\":\"bank\",\"bank_account_number\":\"0325313513\",\"status\":\"active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-31 11:55:52'),
(1124, 1, 1, 'update', '0', 27, '{\"transaction_id\":\"27\",\"debtor_id\":\"31\",\"amount\":100,\"description\":\"Initial debt balance for NAVEED RASHIQ\",\"created_at\":\"2025-08-31 16:28:37\",\"transaction_type\":\"debit\",\"currency\":\"USD\"}', '{\"amount\":100,\"description\":\"Initial debt balance for NAVEED RASHIQ\",\"created_at\":\"2025-08-31 16:28:00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-08-31 12:12:43'),
(1125, 1, 1, '0', 'creditor_transactions', 15, '{\"transaction_id\":15,\"creditor_id\":8,\"amount\":20,\"description\":\"dfsgs\",\"created_at\":\"2025-09-01 08:24:42\",\"reference_number\":\"34641\",\"currency\":\"USD\"}', '{\"amount\":20,\"description\":\"dfsgs\",\"created_at\":\"2025-09-01 08:24:42\",\"reference_number\":\"34641\"}', '::1', '0', '2025-09-01 03:56:59'),
(1126, 1, 1, 'delete', 'sarafi_transactions', 25, '{\"transaction_id\":25,\"amount\":10,\"net_amount\":5,\"commission_amount\":\"5.00\",\"currency\":\"USD\",\"customer_id\":2,\"customer_name\":\"NAVEED RASHIQ\",\"main_account_id\":11,\"created_at\":\"2025-09-01 11:21:45\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 08:10:43'),
(1127, 1, 1, 'delete', 'sarafi_transactions', 20, '{\"transaction_id\":20,\"amount\":10,\"currency\":\"USD\",\"customer_id\":2,\"customer_name\":\"NAVEED RASHIQ\",\"main_account_id\":11,\"created_at\":\"2025-09-01 10:24:55\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 08:10:58'),
(1128, 1, 1, 'delete', 'sarafi_transactions', 30, '{\"transaction_id\":30,\"amount\":10,\"net_amount\":5,\"commission_amount\":\"5.00\",\"currency\":\"USD\",\"customer_id\":2,\"customer_name\":\"NAVEED RASHIQ\",\"main_account_id\":11,\"created_at\":\"2025-09-01 13:08:24\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 08:38:35'),
(1129, 1, 1, 'delete', 'sarafi_transactions', 33, '{\"transaction_id\":33,\"amount\":100,\"currency\":\"USD\",\"customer_id\":2,\"customer_name\":\"NAVEED RASHIQ\",\"main_account_id\":11,\"created_at\":\"2025-09-01 13:16:37\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 08:46:49'),
(1130, 1, 1, 'reinstate', 'users', 7, '{\"fired\":true}', '{\"fired\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 08:47:38'),
(1131, 1, 1, 'fund', 'main_account', 11, '{\"account_id\":\"11\",\"afs_balance\":\"0.000\"}', '{\"account_id\":\"11\",\"afs_balance\":5000,\"amount\":5000,\"currency\":\"AFS\",\"description\":\"Account funded by Sabaoon. Remarks: dfsadf. Receipt: t43t4tw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 08:53:30'),
(1132, 1, 1, 'update', '0', 17, '{\"payment_id\":17,\"user_id\":7,\"amount\":1000,\"description\":\"tets\",\"payment_date\":\"2025-09-01\",\"currency\":\"AFS\",\"payment_type\":\"advance\"}', '{\"amount\":1000,\"description\":\"tets\",\"payment_date\":\"2025-09-01\",\"payment_type\":\"advance\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 09:22:45'),
(1133, 1, 1, 'delete', 'salary_payments', 17, '{\"payment_id\":17,\"amount\":1000,\"currency\":\"AFS\",\"main_account_id\":11,\"payment_date\":\"2025-09-01 13:51:21\",\"payment_type\":\"advance\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 09:22:53'),
(1134, 1, 1, 'add', 'additional_payments', 45, NULL, '{\"id\":45,\"payment_type\":\"Vacine\",\"description\":\"test\",\"base_amount\":10,\"profit\":10,\"sold_amount\":20,\"currency\":\"USD\",\"main_account_id\":11,\"supplier_id\":null,\"is_from_supplier\":1,\"client_id\":null,\"is_for_client\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 09:41:37'),
(1135, 1, 1, 'add', 'main_account_transactions', 815, '[]', '{\"main_account_id\":11,\"amount\":10,\"currency\":\"USD\",\"description\":\"test\",\"payment_id\":45,\"balance\":2955,\"payment_datetime\":\"2025-09-01 14:11:39\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 09:51:00'),
(1136, 1, 1, 'update', 'main_account_transactions', 815, '{\"transaction_id\":815,\"payment_id\":45,\"amount\":\"10.000\",\"description\":\"test\",\"created_at\":\"2025-09-01 14:11:39\",\"receipt\":\"\"}', '{\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-09-01 14:11:39\",\"receipt\":\"1213231\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 09:51:13'),
(1137, 1, 1, 'delete', 'main_account_transactions', 815, '{\"main_account_id\":11,\"transaction_id\":815,\"payment_id\":45,\"amount\":\"10.000\",\"currency\":\"USD\",\"type\":\"credit\",\"created_at\":\"2025-09-01 14:11:39\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 09:55:59'),
(1138, 1, 1, 'update', 'maktobs', 4, '{\"subject\":\"\\u062f\\u0631\\u06cc\\u0627\\u0641\\u062a \\u0631\\u0633\\u06cc\\u062f\",\"content\":\"test\",\"company_name\":\"\\u0645\\u062d\\u0645\\u062f\\u0627\\u0644\\u0644\\u0647 \\u0634\\u0647\\u0632\\u0627\\u062f\",\"maktob_number\":\"01\",\"maktob_date\":\"2025-09-01\",\"language\":\"english\"}', '{\"subject\":\"\\u062f\\u0631\\u06cc\\u0627\\u0641\\u062a \\u0631\\u0633\\u06cc\\u062f\",\"content\":\"test\",\"company_name\":\"\\u0645\\u062d\\u0645\\u062f\\u0627\\u0644\\u0644\\u0647 \\u0634\\u0647\\u0632\\u0627\\u062f\",\"maktob_number\":\"01\",\"maktob_date\":\"2025-09-01\",\"language\":\"english\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 09:57:54'),
(1139, 1, 1, 'add', 'suppliers', 27, '[]', '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":1000,\"supplier_type\":\"External\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 10:34:06'),
(1140, 1, 1, 'add', 'suppliers', 28, '[]', '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":1000,\"supplier_type\":\"External\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 10:34:06'),
(1141, 1, 1, 'delete', 'suppliers', 28, '{\"supplier_id\":28}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 10:34:17'),
(1142, 1, 1, 'update', '0', 27, '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"1000.000\"}', '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"1000.000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 10:36:31'),
(1143, 1, 1, 'update', '0', 27, '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"1000.000\"}', '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"1000.000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 10:36:31'),
(1144, 1, 1, 'update', '0', 27, '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"1000.000\"}', '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"1000.000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 10:36:48'),
(1145, 1, 1, 'update', '0', 27, '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"1000.000\"}', '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"1000.000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 10:36:48'),
(1146, 1, 1, 'update', '0', 27, '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"1000.000\"}', '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"1000.000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 10:41:36'),
(1147, 1, 1, 'update', '0', 27, '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"1000.000\"}', '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"1000.000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 10:41:36'),
(1148, 1, 1, 'update', '0', 27, '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"1000.000\"}', '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"1000.000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 10:41:51'),
(1149, 1, 1, 'update', '0', 27, '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"1000.000\"}', '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"1000.000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 10:41:51'),
(1150, 1, 1, 'update', '0', 27, '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"1000.000\"}', '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"1000.000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 10:42:30'),
(1151, 1, 1, 'update', '0', 27, '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"1000.000\"}', '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"1000.000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 10:42:30'),
(1152, 1, 1, 'update', '0', 27, '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"1000.000\"}', '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"1000.000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 10:44:01'),
(1153, 1, 1, 'add', 'clients', 18, '[]', '{\"name\":\"DR SAHIB\",\"email\":\"admin@abc-construction.com\",\"client_type\":\"regular\",\"phone\":\"0777305730\",\"usd_balance\":\"0.00\",\"afs_balance\":\"0.00\",\"address\":\"Jada-e-Maiwand\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 10:44:22'),
(1154, 1, 1, 'add', 'expense_categories', 18, '[]', '{\"name\":\"Office\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 10:55:48'),
(1155, 1, 1, 'add', 'expenses', 211, '[]', '{\"category_id\":\"18\",\"date\":\"2025-09-01\",\"description\":\"this is for test\",\"amount\":\"100\",\"currency\":\"AFS\",\"main_account_id\":\"11\",\"allocation_id\":\"\",\"receipt_number\":\"\",\"receipt_file\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 10:56:11'),
(1156, 1, 1, 'update', 'expenses', 211, '{\"expense_id\":\"211\",\"previous_values\":{\"amount\":\"100.000\",\"currency\":\"AFS\",\"main_account_id\":11,\"allocation_id\":0,\"receipt_file\":null}}', '{\"category_id\":\"18\",\"date\":\"2025-09-01\",\"description\":\"this is for test\",\"amount\":\"100.000\",\"currency\":\"AFS\",\"main_account_id\":\"11\",\"allocation_id\":\"\",\"receipt_number\":\"\",\"receipt_file\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 11:14:33'),
(1157, 1, 1, 'update', 'expense_categories', 18, '{\"category_id\":\"18\"}', '{\"name\":\"Office\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 11:15:10'),
(1158, 1, 1, 'add', 'expense_categories', 19, '[]', '{\"name\":\"OFFICE EXPS\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 11:17:05'),
(1159, 1, 1, 'add', 'budget_allocations', 20, '[]', '{\"main_account_id\":11,\"category_id\":19,\"allocated_amount\":1000,\"remaining_amount\":1000,\"currency\":\"AFS\",\"allocation_date\":\"2025-09-01\",\"description\":\"test\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 11:32:18'),
(1160, 1, 1, 'add_funds', 'budget_allocations', 20, '{\"allocated_amount\":\"1000.00\",\"remaining_amount\":\"1000.00\"}', '{\"allocated_amount\":1100,\"remaining_amount\":1100}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 11:32:35'),
(1161, 1, 1, 'delete', 'main_account_transactions', 818, '{\"id\":818,\"tenant_id\":1,\"main_account_id\":11,\"type\":\"debit\",\"amount\":\"100.000\",\"balance\":\"3800.000\",\"currency\":\"AFS\",\"description\":\"Additional funding to budget allocation: OFFICE EXPS - test\",\"created_at\":\"2025-09-01 16:02:35\",\"transaction_of\":\"budget_allocation\",\"reference_id\":20,\"receipt\":\"\",\"original_transaction_id\":null}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 11:54:49'),
(1162, 1, 1, 'add', 'expenses', 212, '[]', '{\"category_id\":19,\"date\":\"2025-09-01\",\"description\":\"this is for test\",\"amount\":\"10\",\"currency\":\"AFS\",\"main_account_id\":11,\"allocation_id\":\"20\",\"receipt_number\":\"\",\"receipt_file\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 11:56:15'),
(1163, 1, 1, 'update', 'expenses', 212, '{\"expense_id\":\"212\",\"previous_values\":{\"amount\":\"10.000\",\"currency\":\"AFS\",\"main_account_id\":11,\"allocation_id\":20,\"receipt_file\":null}}', '{\"category_id\":\"\",\"date\":\"2025-09-01\",\"description\":\"this is for test\",\"amount\":\"10.000\",\"currency\":\"AFS\",\"main_account_id\":null,\"allocation_id\":\"20\",\"receipt_number\":\"\",\"receipt_file\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 11:56:58'),
(1164, 1, 1, 'add', 'ticket_bookings', 320, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"188JZ0\",\"origin\":\"MZR\",\"destination\":\"FRA\",\"airline\":\"A3\",\"departure_date\":\"2025-09-24\",\"total_base\":100,\"total_sold\":200,\"total_discount\":0,\"total_profit\":100,\"currency\":\"USD\",\"supplier_id\":27,\"supplier_name\":\"KamAir\",\"client_id\":18,\"client_name\":\"DR SAHIBs\",\"trip_type\":\"one_way\",\"exchange_rate\":71}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 12:56:58'),
(1165, 1, 1, 'update', '0', 320, '{\"ticket_id\":320,\"supplier\":\"27\",\"sold_to\":18,\"price\":\"100.000\",\"sold\":\"200.000\",\"currency\":\"USD\"}', '{\"supplier\":27,\"sold_to\":18,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"guli\",\"pnr\":\"188JZ0\",\"phone\":\"0771781576\",\"origin\":\"MZR\",\"destination\":\"FRA\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"A3\",\"issue_date\":\"2025-09-01\",\"departure_date\":\"2025-09-24\",\"return_date\":\"\",\"price\":100,\"sold\":200,\"profit\":100,\"currency\":\"USD\",\"description\":\"test\",\"paid_to\":11,\"exchange_rate\":71,\"market_exchange_rate\":70}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 12:58:34'),
(1166, 1, 1, 'update', '0', 320, '{\"ticket_id\":320,\"supplier\":\"27\",\"sold_to\":18,\"price\":\"100.000\",\"sold\":\"200.000\",\"currency\":\"USD\"}', '{\"supplier\":27,\"sold_to\":18,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"guli\",\"pnr\":\"188JZ0\",\"phone\":\"0771781576\",\"origin\":\"MZR\",\"destination\":\"FRA\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"A3\",\"issue_date\":\"2025-09-01\",\"departure_date\":\"2025-09-24\",\"return_date\":\"\",\"price\":100,\"sold\":200,\"profit\":100,\"currency\":\"USD\",\"description\":\"test\",\"paid_to\":11,\"exchange_rate\":71,\"market_exchange_rate\":70}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 13:01:37'),
(1167, 1, 1, 'add', 'main_account_transactions', 819, '[]', '{\"booking_id\":320,\"payment_date\":\"2025-09-01 17:31:37\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 13:02:07'),
(1168, 1, 1, 'add', 'main_account_transactions', 820, '[]', '{\"booking_id\":320,\"payment_date\":\"2025-09-01 17:31:37\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 13:02:45'),
(1169, 1, 1, 'update', '0', 819, '{\"transaction_id\":\"819\",\"ticket_id\":\"320\",\"amount\":10,\"description\":\"\",\"created_at\":\"2025-09-01 17:31:37\",\"type\":\"credit\",\"currency\":\"USD\"}', '{\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-09-01 17:31:37\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 13:03:00'),
(1170, 1, 1, 'update', '0', 820, '{\"transaction_id\":\"820\",\"ticket_id\":\"320\",\"amount\":10,\"description\":\"\",\"created_at\":\"2025-09-01 17:31:37\",\"type\":\"credit\",\"currency\":\"USD\"}', '{\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-09-01 17:31:37\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 13:03:06'),
(1171, 1, 1, 'delete', 'main_account_transactions', 820, '{\"transaction_id\":820,\"ticket_id\":320,\"amount\":\"10.000\",\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-01 17:31:37\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 13:05:47'),
(1172, 1, 1, 'delete', 'main_account_transactions', 819, '{\"transaction_id\":819,\"ticket_id\":320,\"amount\":\"10.000\",\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-01 17:31:37\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-01 13:06:05'),
(1173, 1, 1, 'add', 'refunded_tickets', 88, '{\"ticket_id\":320,\"passenger_name\":\"guli\",\"pnr\":\"188JZ0\",\"base\":100,\"sold\":200,\"supplier_penalty\":10,\"service_penalty\":20,\"currency\":\"USD\",\"status\":\"Refunded\",\"description\":\"test\",\"calculation_method\":\"base\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 03:39:01'),
(1174, 1, 1, 'add', 'date_change_tickets', 42, '{\"ticket_id\":\"320\",\"passenger_name\":\"guli\",\"pnr\":\"188JZ0\",\"base\":100,\"sold\":200,\"supplier_penalty\":20,\"service_penalty\":30,\"currency\":\"USD\",\"exchange_rate\":\"70\",\"status\":\"Date Changed\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 04:00:57'),
(1175, 1, 1, 'create', 'ticket_weights', 6, NULL, '{\"ticket_id\":320,\"weight\":20,\"base_price\":10,\"sold_price\":15,\"profit\":5,\"remarks\":\"dfsad\",\"supplier_name\":\"KamAir\",\"client_name\":\"DR SAHIBs\",\"currency\":\"USD\"}', '::1', '0', '2025-09-02 04:05:07'),
(1176, 1, 1, 'add', 'main_account_transactions', 821, '[]', '{\"booking_id\":88,\"payment_date\":\"2025-09-02 08:40:39\",\"description\":\"tt\",\"amount\":10,\"currency\":\"USD\",\"client_type\":\"regular\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 04:12:22'),
(1177, 1, 1, 'add', 'main_account_transactions', 822, '[]', '{\"booking_id\":88,\"payment_date\":\"2025-09-02 08:43:08\",\"description\":\"sdfs\",\"amount\":10,\"currency\":\"USD\",\"client_type\":\"regular\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 04:13:20'),
(1178, 1, 1, 'delete', 'main_account_transactions', 821, '{\"transaction_id\":821,\"ticket_id\":88,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 08:40:39\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 04:13:36'),
(1179, 1, 1, 'update', '0', 822, '{\"transaction_id\":\"822\",\"ticket_id\":\"88\",\"amount\":10,\"description\":\"\",\"created_at\":\"2025-09-02 08:43:08\",\"currency\":\"USD\",\"type\":\"debit\"}', '{\"amount\":10,\"description\":\"sdfs\",\"created_at\":\"2025-09-02 08:43:08\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 04:15:09'),
(1180, 1, 1, 'update', '0', 822, '{\"transaction_id\":\"822\",\"ticket_id\":\"88\",\"amount\":10,\"description\":\"\",\"created_at\":\"2025-09-02 08:43:08\",\"currency\":\"USD\",\"type\":\"debit\"}', '{\"amount\":10,\"description\":\"sdfs\",\"created_at\":\"2025-09-02 08:43:08\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 04:15:21'),
(1181, 1, 1, 'update', '0', 822, '{\"transaction_id\":\"822\",\"ticket_id\":\"88\",\"amount\":10,\"description\":\"\",\"created_at\":\"2025-09-02 08:43:08\",\"currency\":\"USD\",\"type\":\"debit\"}', '{\"amount\":10,\"description\":\"sdfs\",\"created_at\":\"2025-09-02 08:43:08\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 04:17:02'),
(1182, 1, 1, 'update', '0', 822, '{\"transaction_id\":\"822\",\"ticket_id\":\"88\",\"amount\":10,\"description\":\"\",\"created_at\":\"2025-09-02 08:43:08\",\"currency\":\"USD\",\"type\":\"debit\"}', '{\"amount\":10,\"description\":\"sdfs\",\"created_at\":\"2025-09-02 08:43:08\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 04:25:43'),
(1183, 1, 1, 'delete', 'main_account_transactions', 822, '{\"transaction_id\":822,\"ticket_id\":88,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 08:43:08\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 04:28:30'),
(1184, 1, 1, 'add', 'main_account_transactions', 823, '[]', '{\"booking_id\":88,\"payment_date\":\"2025-09-02 09:00:10\",\"description\":\"test\",\"amount\":20,\"currency\":\"USD\",\"client_type\":\"regular\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 04:30:21'),
(1185, 1, 1, 'delete', 'main_account_transactions', 823, '{\"transaction_id\":823,\"ticket_id\":88,\"amount\":20,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 09:00:10\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 04:30:34'),
(1186, 1, 1, 'add', 'main_account_transactions', 824, '[]', '{\"booking_id\":88,\"payment_date\":\"2025-09-02 09:06:29\",\"description\":\"dsaf\",\"amount\":20,\"currency\":\"USD\",\"client_type\":\"regular\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 04:36:38'),
(1187, 1, 1, 'update', '0', 824, '{\"transaction_id\":\"824\",\"ticket_id\":\"88\",\"amount\":20,\"description\":\"\",\"created_at\":\"2025-09-02 09:06:29\",\"currency\":\"USD\",\"type\":\"debit\"}', '{\"amount\":20,\"description\":\"dsaf\",\"created_at\":\"2025-09-02 09:06:29\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 04:36:43'),
(1188, 1, 1, 'delete', 'main_account_transactions', 824, '{\"transaction_id\":824,\"ticket_id\":88,\"amount\":20,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 09:06:29\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 04:36:48'),
(1189, 1, 1, 'add', 'main_account_transactions', 825, '[]', '{\"booking_id\":88,\"payment_date\":\"2025-09-02 09:27:28\",\"description\":\"test\",\"amount\":20,\"currency\":\"USD\",\"client_type\":\"regular\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 04:57:38'),
(1190, 1, 1, 'update', '0', 825, '{\"transaction_id\":\"825\",\"ticket_id\":\"88\",\"amount\":20,\"description\":\"\",\"created_at\":\"2025-09-02 09:27:28\",\"currency\":\"USD\",\"type\":\"debit\"}', '{\"amount\":20,\"description\":\"test\",\"created_at\":\"2025-09-02 09:27:28\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 04:57:43'),
(1191, 1, 1, 'delete', 'main_account_transactions', 825, '{\"transaction_id\":825,\"ticket_id\":88,\"amount\":20,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 09:27:28\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 04:58:00'),
(1192, 1, 1, 'delete', 'refunded_tickets', 88, '{\"refund_id\":88,\"client_id\":18,\"supplier_id\":\"27\",\"main_account_id\":11,\"currency\":\"USD\",\"client_type\":\"regular\",\"pnr\":\"188JZ0\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 05:33:26'),
(1193, 1, 1, 'add', 'refunded_tickets', 89, '{\"ticket_id\":320,\"passenger_name\":\"guli\",\"pnr\":\"188JZ0\",\"base\":100,\"sold\":200,\"supplier_penalty\":20,\"service_penalty\":30,\"currency\":\"USD\",\"status\":\"pending\",\"description\":\"test\",\"calculation_method\":\"sold\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 05:36:08'),
(1194, 1, 1, 'delete', 'refunded_tickets', 89, '{\"refund_id\":89,\"client_id\":18,\"supplier_id\":\"27\",\"main_account_id\":11,\"currency\":\"USD\",\"client_type\":\"regular\",\"pnr\":\"188JZ0\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 05:42:36'),
(1195, 1, 1, 'add', 'refunded_tickets', 90, '{\"ticket_id\":320,\"passenger_name\":\"guli\",\"pnr\":\"188JZ0\",\"base\":100,\"sold\":200,\"supplier_penalty\":20,\"service_penalty\":30,\"currency\":\"USD\",\"status\":\"pending\",\"description\":\"sfgf\",\"calculation_method\":\"sold\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 05:42:54'),
(1196, 1, 1, 'delete', 'refunded_tickets', 90, '{\"refund_id\":90,\"client_id\":18,\"supplier_id\":\"27\",\"main_account_id\":11,\"currency\":\"USD\",\"client_type\":\"regular\",\"pnr\":\"188JZ0\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 06:00:23'),
(1197, 1, 1, 'add', 'refunded_tickets', 91, '{\"ticket_id\":320,\"passenger_name\":\"guli\",\"pnr\":\"188JZ0\",\"base\":100,\"sold\":200,\"supplier_penalty\":1,\"service_penalty\":0,\"currency\":\"USD\",\"status\":\"pending\",\"description\":\"\",\"calculation_method\":\"sold\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 06:00:39'),
(1198, 1, 1, 'delete', 'refunded_tickets', 91, '{\"refund_id\":91,\"client_id\":18,\"supplier_id\":\"27\",\"main_account_id\":11,\"currency\":\"USD\",\"client_type\":\"regular\",\"pnr\":\"188JZ0\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 06:01:43'),
(1199, 1, 1, 'add', 'refunded_tickets', 92, '{\"ticket_id\":320,\"passenger_name\":\"guli\",\"pnr\":\"188JZ0\",\"base\":100,\"sold\":200,\"supplier_penalty\":0.01,\"service_penalty\":0,\"currency\":\"USD\",\"status\":\"pending\",\"description\":\"\",\"calculation_method\":\"sold\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 06:01:56'),
(1200, 1, 1, 'delete', 'refunded_tickets', 92, '{\"refund_id\":92,\"client_id\":18,\"supplier_id\":\"27\",\"main_account_id\":11,\"currency\":\"USD\",\"client_type\":\"regular\",\"pnr\":\"188JZ0\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 06:04:46'),
(1201, 1, 1, 'add', 'main_account_transactions', 826, '[]', '{\"booking_id\":42,\"payment_date\":\"2025-09-02 10:35:04\",\"description\":\"test\",\"amount\":20,\"currency\":\"AFS\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 06:05:15'),
(1202, 1, 1, 'update', 'main_account_transactions', 826, '{\"transaction_id\":\"826\",\"booking_id\":\"42\",\"amount\":20,\"description\":\"\",\"created_at\":\"2025-09-02 10:35:04\"}', '{\"amount\":20,\"description\":\"test\",\"created_at\":\"2025-09-02 10:35:04\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 06:48:53'),
(1203, 1, 1, 'add', 'main_account_transactions', 827, '[]', '{\"booking_id\":42,\"payment_date\":\"2025-09-02 11:18:46\",\"description\":\"szdfgsd\",\"amount\":10,\"currency\":\"AFS\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 06:49:10'),
(1204, 1, 1, 'delete', 'main_account_transactions', 827, '{\"transaction_id\":827,\"ticket_id\":42,\"amount\":10,\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-02 11:18:46\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 06:49:17'),
(1205, 1, 1, 'delete', 'date_change_tickets', 42, '{\"date_change_id\":42,\"client_id\":18,\"supplier_id\":\"27\",\"main_account_id\":11,\"currency\":\"USD\",\"client_type\":\"regular\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 06:59:59'),
(1206, 1, 1, 'add', 'date_change_tickets', 43, '{\"ticket_id\":\"320\",\"passenger_name\":\"guli\",\"pnr\":\"188JZ0\",\"base\":100,\"sold\":200,\"supplier_penalty\":10,\"service_penalty\":20,\"currency\":\"USD\",\"exchange_rate\":\"71.0000\",\"status\":\"Date Changed\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 07:20:20'),
(1207, 1, 1, 'add', 'main_account_transactions', 828, '[]', '{\"booking_id\":43,\"payment_date\":\"2025-09-02 11:50:40\",\"description\":\"rgff\",\"amount\":10,\"currency\":\"AFS\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 07:20:51'),
(1208, 1, 1, 'update', 'main_account_transactions', 828, '{\"transaction_id\":\"828\",\"booking_id\":\"43\",\"amount\":10,\"description\":\"\",\"created_at\":\"2025-09-02 11:50:40\"}', '{\"amount\":10,\"description\":\"rgff\",\"created_at\":\"2025-09-02 11:50:40\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 07:20:55'),
(1209, 1, 1, 'create', 'main_account_transactions', 830, NULL, '{\"weight_id\":6,\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-02 11:52\",\"remarks\":\"fdsfdsg\",\"main_account_id\":11,\"balance\":3035}', '::1', '0', '2025-09-02 07:25:32'),
(1210, 1, 1, 'update', 'ticket_weights', 6, '{\"weight_id\":6,\"weight\":\"20.00\",\"base_price\":\"10.00\",\"sold_price\":\"15.00\",\"profit\":\"5.00\",\"market_exchange_rate\":\"0.00000\",\"exchange_rate\":\"0.00000\",\"remarks\":\"dfsad\"}', '{\"weight_id\":6,\"weight\":20,\"base_price\":10,\"sold_price\":15,\"profit\":5,\"market_exchange_rate\":0,\"exchange_rate\":0,\"remarks\":\"dfsad\",\"supplier_name\":\"KamAir\",\"client_name\":\"DR SAHIBs\",\"base_price_difference\":0,\"sold_price_difference\":0}', '::1', '0', '2025-09-02 07:27:06'),
(1211, 1, 1, 'delete', 'main_account_transactions', 830, '{\"transaction_id\":830,\"weight_id\":6,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 11:52:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 07:37:13'),
(1212, 1, 1, 'create', 'main_account_transactions', 831, NULL, '{\"weight_id\":6,\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-02 12:07\",\"remarks\":\"ghsdhgs\",\"main_account_id\":11,\"balance\":3035}', '::1', '0', '2025-09-02 07:38:09'),
(1213, 1, 1, 'delete', 'main_account_transactions', 831, '{\"transaction_id\":831,\"weight_id\":6,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 12:07:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 07:40:22'),
(1214, 1, 1, 'create', 'main_account_transactions', 832, NULL, '{\"weight_id\":6,\"amount\":10,\"currency\":\"AFS\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-02 12:10\",\"remarks\":\"adfasf\",\"main_account_id\":11,\"balance\":3920}', '::1', '0', '2025-09-02 07:40:35'),
(1215, 1, 1, 'delete', 'main_account_transactions', 832, '{\"transaction_id\":832,\"weight_id\":6,\"amount\":10,\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-02 12:10:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 07:45:12'),
(1216, 1, 1, 'create', 'main_account_transactions', 833, NULL, '{\"weight_id\":6,\"amount\":10,\"currency\":\"AFS\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-02 12:49\",\"remarks\":\"dfgdsg\",\"main_account_id\":11,\"balance\":3920}', '::1', '0', '2025-09-02 08:19:42'),
(1217, 1, 1, 'delete', 'main_account_transactions', 833, '{\"transaction_id\":833,\"weight_id\":6,\"amount\":10,\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-02 12:49:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 08:23:33'),
(1218, 1, 1, 'create', 'main_account_transactions', 834, NULL, '{\"weight_id\":6,\"amount\":10,\"currency\":\"AFS\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-02 12:53\",\"remarks\":\"terst\",\"main_account_id\":11,\"balance\":3920}', '::1', '0', '2025-09-02 08:23:44'),
(1219, 1, 1, 'update', 'ticket_weights', 6, '{\"weight_id\":6,\"weight\":\"20.00\",\"base_price\":\"10.00\",\"sold_price\":\"15.00\",\"profit\":\"5.00\",\"market_exchange_rate\":\"0.00000\",\"exchange_rate\":\"0.00000\",\"remarks\":\"dfsad\"}', '{\"weight_id\":6,\"weight\":20,\"base_price\":10,\"sold_price\":15,\"profit\":5,\"market_exchange_rate\":0,\"exchange_rate\":0,\"remarks\":\"dfsad\",\"supplier_name\":\"KamAir\",\"client_name\":\"DR SAHIBs\",\"base_price_difference\":0,\"sold_price_difference\":0}', '::1', '0', '2025-09-02 08:23:52'),
(1220, 1, 1, 'delete', 'ticket_weights', 6, '{\"weight_id\":6,\"transactions\":[{\"transaction_id\":772,\"amount\":\"10.000\",\"transaction_type\":\"Debit\",\"transaction_date\":\"2025-09-02 08:35:07\",\"currency\":\"USD\",\"supplier_id\":27,\"supplier_type\":\"External\",\"sold_to\":18,\"ticket_currency\":\"USD\",\"paid_to\":11}]}', NULL, '::1', '0', '2025-09-02 08:28:02'),
(1221, 1, 1, 'create', 'ticket_weights', 7, NULL, '{\"ticket_id\":320,\"weight\":20,\"base_price\":30,\"sold_price\":50,\"profit\":20,\"remarks\":\"fdsaf\",\"supplier_name\":\"KamAir\",\"client_name\":\"DR SAHIBs\",\"currency\":\"USD\"}', '::1', '0', '2025-09-02 08:28:28'),
(1222, 1, 1, 'update', 'ticket_weights', 7, '{\"weight_id\":7,\"weight\":\"20.00\",\"base_price\":\"30.00\",\"sold_price\":\"50.00\",\"profit\":\"20.00\",\"market_exchange_rate\":\"0.00000\",\"exchange_rate\":\"0.00000\",\"remarks\":\"fdsaf\"}', '{\"weight_id\":7,\"weight\":20,\"base_price\":30,\"sold_price\":50,\"profit\":20,\"market_exchange_rate\":70,\"exchange_rate\":71,\"remarks\":\"fdsaf\",\"supplier_name\":\"KamAir\",\"client_name\":\"DR SAHIBs\",\"base_price_difference\":0,\"sold_price_difference\":0}', '::1', '0', '2025-09-02 08:28:51'),
(1223, 1, 1, 'create', 'main_account_transactions', 835, NULL, '{\"weight_id\":7,\"amount\":10,\"currency\":\"AFS\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-02 12:59\",\"remarks\":\"test\",\"main_account_id\":11,\"balance\":3920}', '::1', '0', '2025-09-02 08:29:15'),
(1224, 1, 1, 'delete', 'main_account_transactions', 835, '{\"transaction_id\":835,\"weight_id\":7,\"amount\":10,\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-02 12:59:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 08:29:21'),
(1225, 1, 1, 'create', 'main_account_transactions', 836, NULL, '{\"weight_id\":7,\"amount\":10,\"currency\":\"AFS\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-02 12:59\",\"remarks\":\"test\",\"main_account_id\":11,\"balance\":3920}', '::1', '0', '2025-09-02 08:29:49'),
(1226, 1, 1, 'delete', 'main_account_transactions', 836, '{\"transaction_id\":836,\"weight_id\":7,\"amount\":10,\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-02 12:59:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 08:29:54'),
(1227, 1, 1, 'create', 'main_account_transactions', 837, NULL, '{\"weight_id\":7,\"amount\":10,\"currency\":\"AFS\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-02 13:02\",\"remarks\":\"sdadf\",\"main_account_id\":11,\"balance\":3920}', '::1', '0', '2025-09-02 08:32:20'),
(1228, 1, 1, 'delete', 'main_account_transactions', 837, '{\"transaction_id\":837,\"weight_id\":7,\"amount\":10,\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-02 13:02:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 08:32:25'),
(1229, 1, 1, 'create', 'main_account_transactions', 838, NULL, '{\"weight_id\":7,\"amount\":10,\"currency\":\"AFS\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-02 13:02\",\"remarks\":\"sadfg\",\"main_account_id\":11,\"balance\":3920}', '::1', '0', '2025-09-02 08:32:39'),
(1230, 1, 1, 'delete', 'main_account_transactions', 838, '{\"transaction_id\":838,\"weight_id\":7,\"amount\":10,\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-02 13:02:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 08:32:43'),
(1231, 1, 1, 'create', 'main_account_transactions', 839, NULL, '{\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-02 13:03\",\"remarks\":\"kjhlk\",\"main_account_id\":11,\"balance\":3035}', '::1', '0', '2025-09-02 08:33:45'),
(1232, 1, 1, 'delete', 'main_account_transactions', 839, '{\"transaction_id\":839,\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 13:03:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 08:33:48'),
(1233, 1, 1, 'create', 'main_account_transactions', 840, NULL, '{\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-02 13:05\",\"remarks\":\"\",\"main_account_id\":11,\"balance\":3035}', '::1', '0', '2025-09-02 08:35:25'),
(1234, 1, 1, 'delete', 'main_account_transactions', 840, '{\"transaction_id\":840,\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 13:05:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 08:35:29'),
(1235, 1, 1, 'create', 'main_account_transactions', 841, NULL, '{\"weight_id\":7,\"amount\":20,\"currency\":\"USD\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-02 13:06\",\"remarks\":\"\",\"main_account_id\":11,\"balance\":3045}', '::1', '0', '2025-09-02 08:36:06');
INSERT INTO `activity_log` (`id`, `tenant_id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1236, 1, 1, 'delete', 'main_account_transactions', 841, '{\"transaction_id\":841,\"weight_id\":7,\"amount\":20,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 13:06:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 08:36:10'),
(1237, 1, 1, 'create', 'main_account_transactions', 842, NULL, '{\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-02 13:08\",\"remarks\":\"\",\"main_account_id\":11,\"balance\":3035}', '::1', '0', '2025-09-02 08:38:19'),
(1238, 1, 1, 'delete', 'main_account_transactions', 842, '{\"transaction_id\":842,\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 13:08:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 08:38:21'),
(1239, 1, 1, 'create', 'main_account_transactions', 843, NULL, '{\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-02 13:09\",\"remarks\":\"\",\"main_account_id\":11,\"balance\":3035}', '::1', '0', '2025-09-02 08:39:23'),
(1240, 1, 1, 'delete', 'main_account_transactions', 843, '{\"transaction_id\":843,\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 13:09:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 08:39:26'),
(1241, 1, 1, 'create', 'main_account_transactions', 844, NULL, '{\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-02 13:09\",\"remarks\":\"\",\"main_account_id\":11,\"balance\":3035}', '::1', '0', '2025-09-02 08:39:40'),
(1242, 1, 1, 'delete', 'main_account_transactions', 844, '{\"transaction_id\":844,\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 13:09:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 08:39:45'),
(1243, 1, 1, 'create', 'main_account_transactions', 845, NULL, '{\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-02 13:11\",\"remarks\":\"\",\"main_account_id\":11,\"balance\":3035}', '::1', '0', '2025-09-02 08:41:17'),
(1244, 1, 1, 'delete', 'main_account_transactions', 845, '{\"transaction_id\":845,\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 13:11:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 08:41:20'),
(1245, 1, 1, 'create', 'main_account_transactions', 846, NULL, '{\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-02 13:14\",\"remarks\":\"\",\"main_account_id\":11,\"balance\":3035}', '::1', '0', '2025-09-02 08:44:33'),
(1246, 1, 1, 'delete', 'main_account_transactions', 846, '{\"transaction_id\":846,\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 13:14:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 08:44:35'),
(1247, 1, 1, 'create', 'main_account_transactions', 847, NULL, '{\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-02 13:15\",\"remarks\":\"\",\"main_account_id\":11,\"balance\":3035}', '::1', '0', '2025-09-02 08:45:24'),
(1248, 1, 1, 'delete', 'main_account_transactions', 847, '{\"transaction_id\":847,\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 13:15:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 08:45:28'),
(1249, 1, 1, 'create', 'main_account_transactions', 848, NULL, '{\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-02 13:21\",\"remarks\":\"\",\"main_account_id\":11,\"balance\":3035}', '::1', '0', '2025-09-02 08:51:56'),
(1250, 1, 1, 'delete', 'main_account_transactions', 848, '{\"transaction_id\":848,\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 13:21:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 08:51:59'),
(1251, 1, 1, 'create', 'main_account_transactions', 849, NULL, '{\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-02 13:23\",\"remarks\":\"\",\"main_account_id\":11,\"balance\":3035}', '::1', '0', '2025-09-02 08:53:34'),
(1252, 1, 1, 'delete', 'main_account_transactions', 849, '{\"transaction_id\":849,\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 13:23:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 08:53:37'),
(1253, 1, 1, 'create', 'main_account_transactions', 850, NULL, '{\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-02 13:24\",\"remarks\":\"\",\"main_account_id\":11,\"balance\":3035}', '::1', '0', '2025-09-02 08:54:45'),
(1254, 1, 1, 'delete', 'main_account_transactions', 850, '{\"transaction_id\":850,\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 13:24:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 08:54:48'),
(1255, 1, 1, 'create', 'main_account_transactions', 851, NULL, '{\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-02 13:25\",\"remarks\":\"\",\"main_account_id\":11,\"balance\":3035}', '::1', '0', '2025-09-02 08:55:32'),
(1256, 1, 1, 'delete', 'main_account_transactions', 851, '{\"transaction_id\":851,\"weight_id\":7,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 13:25:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 08:55:38'),
(1257, 1, 1, 'delete', 'ticket_weights', 7, '{\"weight_id\":7,\"transactions\":[{\"transaction_id\":778,\"amount\":\"30.000\",\"transaction_type\":\"Debit\",\"transaction_date\":\"2025-09-02 12:58:28\",\"currency\":\"USD\",\"supplier_id\":27,\"supplier_type\":\"External\",\"sold_to\":18,\"ticket_currency\":\"USD\",\"paid_to\":11}]}', NULL, '::1', '0', '2025-09-02 08:55:56'),
(1258, 1, 1, 'add', 'ticket_reservations', 11, '{}', '{\"passenger_name\":\"guli\",\"pnr\":\"188JZ0\",\"origin\":\"MZR\",\"destination\":\"FRA\",\"airline\":\"A3\",\"departure_date\":\"2025-09-04\",\"base\":100,\"sold\":200,\"profit\":100,\"currency\":\"USD\",\"supplier\":27,\"supplier_name\":\"KamAir\",\"sold_to\":18,\"client_name\":\"DR SAHIBs\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 08:58:43'),
(1259, 1, 1, 'update', '0', 11, '{\"ticket_id\":11,\"supplier\":\"27\",\"sold_to\":18,\"price\":\"100.000\",\"sold\":\"200.000\",\"currency\":\"USD\"}', '{\"supplier\":27,\"sold_to\":18,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"guli\",\"pnr\":\"188JZ0\",\"phone\":\"0777305730\",\"origin\":\"MZR\",\"destination\":\"FRA\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"A3\",\"issue_date\":\"2025-09-02\",\"departure_date\":\"2025-09-04\",\"return_date\":\"\",\"price\":100,\"sold\":200,\"profit\":100,\"currency\":\"USD\",\"description\":\"test\",\"paid_to\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 08:58:58'),
(1260, 1, 1, 'add', 'main_account_transactions', 852, '[]', '{\"booking_id\":11,\"payment_date\":\"2025-09-02 13:29:00\",\"description\":\"fg\",\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 08:59:13'),
(1261, 1, 1, 'add', 'main_account_transactions', 853, '[]', '{\"booking_id\":11,\"payment_date\":\"2025-09-02 13:29:45\",\"description\":\"df\",\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 08:59:54'),
(1262, 1, 1, 'update', '0', 853, '{\"transaction_id\":\"853\",\"ticket_id\":\"11\",\"amount\":10,\"type\":\"credit\",\"currency\":\"USD\",\"created_at\":\"2025-09-02 13:29:45\",\"description\":\"\"}', '{\"amount\":10,\"description\":\"df\",\"created_at\":\"2025-09-02 13:29:45\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 09:08:59'),
(1263, 1, 1, 'delete', 'main_account_transactions', 853, '{\"transaction_id\":853,\"ticket_id\":11,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 13:29:45\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 09:09:11'),
(1264, 1, 1, 'add', 'hotel_bookings', 33, '[]', '{\"title\":\"Mr\",\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"gender\":\"Male\",\"check_in_date\":\"2025-09-02\",\"check_out_date\":\"2025-09-30\",\"accommodation_details\":\"sdfg\",\"supplier_id\":27,\"sold_to\":\"18\",\"base_amount\":10,\"sold_amount\":20,\"profit\":10,\"currency\":\"USD\",\"paid_to\":\"11\",\"exchange_rate\":70}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 09:15:50'),
(1265, 1, 1, 'update', 'hotel_bookings', 33, '{\"booking_id\":33,\"title\":\"\",\"first_name\":\"\",\"last_name\":\"\",\"base_amount\":\"10.000\",\"sold_amount\":\"20.000\",\"currency\":\"USD\",\"supplier_id\":27,\"sold_to\":\"18\"}', '{\"title\":\"Mr\",\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"gender\":\"Male\",\"contact_no\":\"777305730\",\"check_in_date\":\"2025-09-02\",\"check_out_date\":\"2025-09-30\",\"accommodation_details\":\"sdfg\",\"base_amount\":10,\"sold_amount\":20,\"profit\":10,\"currency\":\"USD\",\"supplier_id\":\"27\",\"sold_to\":\"18\",\"paid_to\":\"11\",\"remarks\":\"test\",\"exchange_rate\":\"70.00000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 09:41:13'),
(1266, 1, 1, 'add', 'main_account_transactions', 854, '[]', '{\"booking_id\":33,\"payment_date\":null,\"transaction_type\":\"credit\",\"description\":\"\",\"amount\":10,\"currency\":\"USD\",\"is_refund\":null,\"original_transaction_id\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 09:41:25'),
(1267, 1, 1, 'add', 'main_account_transactions', 855, '[]', '{\"booking_id\":33,\"payment_date\":null,\"transaction_type\":\"credit\",\"description\":\"ghjhjhjhj\",\"amount\":10,\"currency\":\"USD\",\"is_refund\":null,\"original_transaction_id\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 09:41:30'),
(1268, 1, 1, 'add', 'main_account_transactions', 856, '[]', '{\"booking_id\":33,\"payment_date\":null,\"transaction_type\":\"credit\",\"description\":\"ghjhjhjhj\",\"amount\":10,\"currency\":\"USD\",\"is_refund\":null,\"original_transaction_id\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 09:41:36'),
(1269, 1, 1, 'add', 'main_account_transactions', 857, '[]', '{\"booking_id\":33,\"payment_date\":null,\"transaction_type\":\"credit\",\"description\":\"ghjhjhjhj\",\"amount\":10,\"currency\":\"USD\",\"is_refund\":null,\"original_transaction_id\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 09:42:08'),
(1270, 1, 1, 'update', '0', 857, '{\"transaction_id\":\"857\",\"booking_id\":\"33\",\"amount\":10,\"type\":\"credit\",\"created_at\":\"2025-09-02 14:12:08\",\"description\":\"\",\"currency\":\"USD\"}', '{\"amount\":10,\"type\":\"credit\",\"description\":\"ghjhjhjhj\",\"created_at\":\"2025-09-02 14:12:08\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 09:47:28'),
(1271, 1, 1, 'delete', 'main_account_transactions', 857, '{\"transaction_id\":857,\"booking_id\":33,\"amount\":10,\"currency\":\"USD\",\"transaction_type\":\"credit\",\"is_refund\":false,\"main_account_id\":11,\"description\":\"ghjhjhjhj\",\"created_at\":\"2025-09-02 14:12:08\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 09:47:31'),
(1272, 1, 1, 'add', 'main_account_transactions', 858, '[]', '{\"refund_id\":5,\"payment_date\":\"2025-09-02 14:29:58 14:29:58\",\"description\":\"Refund payment for Hotel Booking #33 - Mr NAVEED RASHIQ\",\"amount\":10,\"currency\":\"USD\",\"client_type\":\"regular\",\"main_account_id\":11,\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"order_id\":\"1089734\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:00:17'),
(1273, 1, 1, 'add', 'main_account_transactions', 859, '[]', '{\"refund_id\":5,\"payment_date\":\"2025-09-02 14:29:58 14:29:58\",\"description\":\"Refund payment for Hotel Booking #33 - Mr NAVEED RASHIQ\",\"amount\":10,\"currency\":\"USD\",\"client_type\":\"regular\",\"main_account_id\":11,\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"order_id\":\"1089734\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:00:56'),
(1274, 1, 1, 'update', '0', 858, '{\"transaction_id\":\"858\",\"booking_id\":\"5\",\"amount\":10,\"description\":\"\",\"created_at\":\"2025-09-02 14:29:58\",\"currency\":\"USD\",\"type\":\"debit\"}', '{\"amount\":10,\"description\":\"Refund payment for Hotel Booking #33 - Mr NAVEED RASHIQ\",\"created_at\":\"2025-09-02 14:29:58\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:01:05'),
(1275, 1, 1, 'delete', 'main_account_transactions', 858, '{\"transaction_id\":858,\"refund_id\":5,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 14:29:58\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:01:09'),
(1276, 1, 1, 'update', 'hotel_bookings', 33, '{\"booking_id\":33,\"title\":\"\",\"first_name\":\"\",\"last_name\":\"\",\"base_amount\":\"10.000\",\"sold_amount\":\"20.000\",\"currency\":\"USD\",\"supplier_id\":27,\"sold_to\":\"18\"}', '{\"title\":\"Mr\",\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"gender\":\"Male\",\"contact_no\":\"777305730\",\"check_in_date\":\"2025-09-02\",\"check_out_date\":\"2025-09-30\",\"accommodation_details\":\"sdfg\",\"base_amount\":10,\"sold_amount\":20,\"profit\":10,\"currency\":\"USD\",\"supplier_id\":\"27\",\"sold_to\":\"18\",\"paid_to\":\"11\",\"remarks\":\"test\",\"exchange_rate\":\"70.00000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:18:04'),
(1277, 1, 1, 'update', 'hotel_bookings', 33, '{\"booking_id\":33,\"title\":\"\",\"first_name\":\"\",\"last_name\":\"\",\"base_amount\":\"10.000\",\"sold_amount\":\"20.000\",\"currency\":\"USD\",\"supplier_id\":27,\"sold_to\":\"18\"}', '{\"title\":\"Mr\",\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"gender\":\"Male\",\"contact_no\":\"777305730\",\"check_in_date\":\"2025-09-02\",\"check_out_date\":\"2025-09-30\",\"accommodation_details\":\"sdfg\",\"base_amount\":10,\"sold_amount\":20,\"profit\":10,\"currency\":\"USD\",\"supplier_id\":\"27\",\"sold_to\":\"18\",\"paid_to\":\"11\",\"remarks\":\"test\",\"exchange_rate\":\"70.00000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:18:31'),
(1278, 1, 1, 'update', 'hotel_bookings', 33, '{\"booking_id\":33,\"title\":\"\",\"first_name\":\"\",\"last_name\":\"\",\"base_amount\":\"10.000\",\"sold_amount\":\"20.000\",\"currency\":\"USD\",\"supplier_id\":27,\"sold_to\":\"18\"}', '{\"title\":\"Mr\",\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"gender\":\"Male\",\"contact_no\":\"777305730\",\"check_in_date\":\"2025-09-02\",\"check_out_date\":\"2025-09-30\",\"accommodation_details\":\"sdfg\",\"base_amount\":10,\"sold_amount\":20,\"profit\":10,\"currency\":\"USD\",\"supplier_id\":\"27\",\"sold_to\":\"18\",\"paid_to\":\"11\",\"remarks\":\"test\",\"exchange_rate\":\"70.00000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:18:32'),
(1279, 1, 1, 'update', 'hotel_bookings', 33, '{\"booking_id\":33,\"title\":\"\",\"first_name\":\"\",\"last_name\":\"\",\"base_amount\":\"10.000\",\"sold_amount\":\"20.000\",\"currency\":\"USD\",\"supplier_id\":27,\"sold_to\":\"18\"}', '{\"title\":\"Mr\",\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"gender\":\"Male\",\"contact_no\":\"777305730\",\"check_in_date\":\"2025-09-02\",\"check_out_date\":\"2025-09-30\",\"accommodation_details\":\"sdfg\",\"base_amount\":10,\"sold_amount\":20,\"profit\":10,\"currency\":\"USD\",\"supplier_id\":\"27\",\"sold_to\":\"18\",\"paid_to\":\"11\",\"remarks\":\"test\",\"exchange_rate\":\"70.00000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:18:33'),
(1280, 1, 1, 'update', 'hotel_bookings', 33, '{\"booking_id\":33,\"title\":\"\",\"first_name\":\"\",\"last_name\":\"\",\"base_amount\":\"10.000\",\"sold_amount\":\"20.000\",\"currency\":\"USD\",\"supplier_id\":27,\"sold_to\":\"18\"}', '{\"title\":\"Mr\",\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"gender\":\"Male\",\"contact_no\":\"777305730\",\"check_in_date\":\"2025-09-02\",\"check_out_date\":\"2025-09-30\",\"accommodation_details\":\"sdfg\",\"base_amount\":10,\"sold_amount\":20,\"profit\":10,\"currency\":\"USD\",\"supplier_id\":\"27\",\"sold_to\":\"18\",\"paid_to\":\"11\",\"remarks\":\"test\",\"exchange_rate\":\"70.00000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:18:35'),
(1281, 1, 1, 'update', 'hotel_bookings', 33, '{\"booking_id\":33,\"title\":\"\",\"first_name\":\"\",\"last_name\":\"\",\"base_amount\":\"10.000\",\"sold_amount\":\"20.000\",\"currency\":\"USD\",\"supplier_id\":27,\"sold_to\":\"18\"}', '{\"title\":\"Mr\",\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"gender\":\"Male\",\"contact_no\":\"777305730\",\"check_in_date\":\"2025-09-02\",\"check_out_date\":\"2025-09-30\",\"accommodation_details\":\"sdfg\",\"base_amount\":10,\"sold_amount\":20,\"profit\":10,\"currency\":\"USD\",\"supplier_id\":\"27\",\"sold_to\":\"18\",\"paid_to\":\"11\",\"remarks\":\"test\",\"exchange_rate\":\"70.00000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:19:43'),
(1282, 1, 1, 'update', '0', 856, '{\"transaction_id\":\"856\",\"booking_id\":\"33\",\"amount\":10,\"type\":\"credit\",\"created_at\":\"2025-09-02 14:11:36\",\"description\":\"\",\"currency\":\"USD\"}', '{\"amount\":10,\"type\":\"credit\",\"description\":\"ghjhjhjhj\",\"created_at\":\"2025-09-02 14:11:36\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:19:54'),
(1283, 1, 1, 'delete', 'main_account_transactions', 856, '{\"transaction_id\":856,\"booking_id\":33,\"amount\":10,\"currency\":\"USD\",\"transaction_type\":\"credit\",\"is_refund\":false,\"main_account_id\":11,\"description\":\"ghjhjhjhj\",\"created_at\":\"2025-09-02 14:11:36\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:20:00'),
(1284, 1, 1, 'add', 'main_account_transactions', 860, '[]', '{\"refund_id\":6,\"payment_date\":\"2025-09-02 14:50:39 14:50:39\",\"description\":\"Refund payment for Hotel Booking #33 - Mr NAVEED RASHIQ\",\"amount\":20,\"currency\":\"USD\",\"client_type\":\"regular\",\"main_account_id\":11,\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"order_id\":\"1089734\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:20:46'),
(1285, 1, 1, 'update', '0', 860, '{\"transaction_id\":\"860\",\"booking_id\":\"6\",\"amount\":20,\"description\":\"\",\"created_at\":\"2025-09-02 14:50:39\",\"currency\":\"USD\",\"type\":\"debit\"}', '{\"amount\":20,\"description\":\"Refund payment for Hotel Booking #33 - Mr NAVEED RASHIQ\",\"created_at\":\"2025-09-02 14:50:39\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:20:55'),
(1286, 1, 1, 'delete', 'main_account_transactions', 860, '{\"transaction_id\":860,\"refund_id\":6,\"amount\":20,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 14:50:39\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:20:59'),
(1287, 1, 1, 'delete', 'hotel_bookings', 33, '{\"booking_id\":33,\"client_id\":\"18\",\"supplier_id\":27,\"currency\":\"USD\",\"client_type\":\"regular\",\"supplier_type\":\"External\",\"paid_to\":11}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:24:10'),
(1288, 1, 1, 'update', '0', 8, '[]', '{\"family_id\":\"8\",\"head_of_family\":\"HAMEED\",\"contact\":\"0777555594\",\"address\":\"Jada-e-Maiwand\",\"package_type\":\"Full Package\",\"location\":\"Madina and Makkah\",\"tazmin\":\"Done\",\"visa_status\":\"Not Applied\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:26:40'),
(1289, 1, 1, 'add', 'umrah_bookings', 48, '[]', '{\"family_id\":\"8\",\"supplier\":\"27\",\"sold_to\":\"18\",\"paid_to\":\"11\",\"name\":\"KamAir\",\"passport_number\":\"P07592390\",\"flight_date\":\"\",\"return_date\":\"\",\"room_type\":\"Shared\",\"price\":\"1000\",\"sold_price\":\"1200\",\"profit\":\"200.00\",\"exchange_rate\":\"\",\"remarks\":\"Base amount of 1000 USD deducted for umrah.\",\"discount\":\"0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:28:32'),
(1290, 1, 1, 'update_umrah_member', 'umrah_bookings', 47, '{\"supplier\":27,\"sold_to\":18,\"paid_to\":11,\"entry_date\":\"2025-09-02\",\"name\":\"KamAir\",\"dob\":\"2025-09-02\",\"passport_number\":\"P07592390\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":\"0000-00-00\",\"return_date\":\"0000-00-00\",\"duration\":\"15 Days\",\"room_type\":\"Shared\",\"price\":\"1000.000\",\"sold_price\":\"1200.000\",\"profit\":\"200.000\",\"received_bank_payment\":\"0.000\",\"bank_receipt_number\":\"\",\"paid\":\"0.000\",\"due\":\"1200.000\",\"discount\":\"0.000\"}', '{\"booking_id\":47,\"family_id\":8,\"supplier\":27,\"sold_to\":18,\"paid_to\":11,\"entry_date\":\"2025-09-02\",\"name\":\"KamAir\",\"dob\":\"2025-09-02\",\"passport_number\":\"P07592390\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":\"2025-09-02\",\"return_date\":\"2025-10-02\",\"duration\":\"15 Days\",\"room_type\":\"Shared\",\"currency\":\"USD\",\"price\":1000,\"sold_price\":1200,\"profit\":200,\"received_bank_payment\":0,\"bank_receipt_number\":\"\",\"paid\":0,\"due\":1200,\"gender\":\"Male\",\"passport_expiry\":\"2026-04-08\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:28:58'),
(1291, 1, 1, 'update_umrah_member', 'umrah_bookings', 47, '{\"supplier\":27,\"sold_to\":18,\"paid_to\":11,\"entry_date\":\"2025-09-02\",\"name\":\"KamAir\",\"dob\":\"2025-09-02\",\"passport_number\":\"P07592390\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":\"2025-09-02\",\"return_date\":\"2025-10-02\",\"duration\":\"15 Days\",\"room_type\":\"Shared\",\"price\":\"1000.000\",\"sold_price\":\"1200.000\",\"profit\":\"200.000\",\"received_bank_payment\":\"0.000\",\"bank_receipt_number\":\"\",\"paid\":\"0.000\",\"due\":\"1200.000\",\"discount\":\"0.000\"}', '{\"booking_id\":47,\"family_id\":8,\"supplier\":27,\"sold_to\":18,\"paid_to\":11,\"entry_date\":\"2025-09-02\",\"name\":\"KamAir\",\"dob\":\"2025-09-02\",\"passport_number\":\"P07592390\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":\"2025-09-02\",\"return_date\":\"2025-10-02\",\"duration\":\"15 Days\",\"room_type\":\"Shared\",\"currency\":\"USD\",\"price\":1000,\"sold_price\":1200,\"profit\":200,\"received_bank_payment\":null,\"bank_receipt_number\":null,\"paid\":null,\"due\":null,\"gender\":\"Male\",\"passport_expiry\":\"2026-04-08\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:33:02'),
(1292, 1, 1, 'update_umrah_member', 'umrah_bookings', 47, '{\"supplier\":27,\"sold_to\":18,\"paid_to\":11,\"entry_date\":\"2025-09-02\",\"name\":\"KamAir\",\"dob\":\"2025-09-02\",\"passport_number\":\"P07592390\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":\"2025-09-02\",\"return_date\":\"2025-10-02\",\"duration\":\"15 Days\",\"room_type\":\"Shared\",\"price\":\"1000.000\",\"sold_price\":\"1200.000\",\"profit\":\"200.000\",\"received_bank_payment\":null,\"bank_receipt_number\":null,\"paid\":null,\"due\":null,\"discount\":\"0.000\"}', '{\"booking_id\":47,\"family_id\":8,\"supplier\":27,\"sold_to\":18,\"paid_to\":11,\"entry_date\":\"2025-09-02\",\"name\":\"KamAir\",\"dob\":\"2025-09-02\",\"passport_number\":\"P07592390\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":\"2025-09-02\",\"return_date\":\"2025-10-02\",\"duration\":\"15 Days\",\"room_type\":\"Shared\",\"currency\":\"USD\",\"price\":1000,\"sold_price\":1200,\"profit\":200,\"received_bank_payment\":null,\"bank_receipt_number\":null,\"paid\":null,\"due\":null,\"gender\":\"Male\",\"passport_expiry\":\"2026-04-08\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:42:30'),
(1293, 1, 1, 'add', 'umrah_bookings', 49, '[]', '{\"family_id\":\"8\",\"supplier\":\"27\",\"sold_to\":\"18\",\"paid_to\":\"11\",\"name\":\"NAVEED RASHIQ\",\"passport_number\":\"P07592390\",\"flight_date\":\"\",\"return_date\":\"\",\"room_type\":\"3 Beds\",\"price\":\"1000\",\"sold_price\":\"1200\",\"profit\":\"200.00\",\"exchange_rate\":\"70\",\"remarks\":\"Base amount of 1000 USD deducted for umrah.\",\"discount\":\"0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:46:40'),
(1294, 1, 1, 'update_umrah_member', 'umrah_bookings', 47, '{\"supplier\":27,\"sold_to\":18,\"paid_to\":11,\"entry_date\":\"2025-09-02\",\"name\":\"KamAir\",\"dob\":\"2025-09-02\",\"passport_number\":\"P07592390\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":\"2025-09-02\",\"return_date\":\"2025-10-02\",\"duration\":\"15 Days\",\"room_type\":\"Shared\",\"price\":\"1000.000\",\"sold_price\":\"1200.000\",\"profit\":\"200.000\",\"received_bank_payment\":null,\"bank_receipt_number\":null,\"paid\":null,\"due\":null,\"discount\":\"0.000\"}', '{\"booking_id\":47,\"family_id\":8,\"supplier\":27,\"sold_to\":18,\"paid_to\":11,\"entry_date\":\"2025-09-02\",\"name\":\"KamAir\",\"dob\":\"2025-09-02\",\"passport_number\":\"P07592390\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":\"2025-09-02\",\"return_date\":\"2025-10-02\",\"duration\":\"15 Days\",\"room_type\":\"Shared\",\"currency\":\"USD\",\"price\":1000,\"sold_price\":1200,\"profit\":200,\"received_bank_payment\":null,\"bank_receipt_number\":\"\",\"paid\":null,\"due\":1200,\"gender\":\"Male\",\"passport_expiry\":\"2026-04-08\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 10:48:02'),
(1295, 1, 1, 'add', 'umrah_transactions', 44, '[]', '{\"umrah_booking_id\":47,\"transaction_to\":\"Internal Account\",\"payment_amount\":100,\"payment_currency\":\"USD\",\"payment_description\":\"terst\",\"payment_date\":\"2025-09-02\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 11:34:38'),
(1296, 1, 1, 'update', 'umrah_transactions', 44, '{\"transaction_id\":\"44\",\"umrah_id\":\"47\",\"payment_amount\":\"100.000\",\"payment_date\":\"2025-09-02\",\"transaction_to\":\"Internal Account\"}', '{\"transaction_id\":\"44\",\"umrah_id\":\"47\",\"payment_amount\":100,\"payment_date\":\"2025-09-02 16:04:38\",\"payment_description\":\"terst\",\"transaction_to\":\"Internal Account\"}', '::1', '0', '2025-09-02 11:34:44'),
(1297, 1, 1, 'delete', 'umrah_transactions', 44, '{\"transaction_id\":44,\"umrah_id\":47,\"payment_amount\":100,\"currency\":\"USD\",\"transaction_to\":\"Internal Account\",\"payment_description\":\"terst\",\"is_refund\":false,\"supplier_id\":27,\"paid_to\":11}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 11:36:38'),
(1298, 1, 1, 'add', 'main_account_transactions', 862, '[]', '{\"refund_id\":17,\"payment_date\":\"2025-09-02 16:12:31 16:12:31\",\"description\":\"Refund payment for Umrah Booking #47 - KamAir\",\"amount\":200,\"currency\":\"USD\",\"client_type\":\"regular\",\"main_account_id\":11,\"name\":\"KamAir\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 11:42:45'),
(1299, 1, 1, 'add', 'main_account_transactions', 863, '[]', '{\"refund_id\":17,\"payment_date\":\"2025-09-02 16:12:31 16:12:31\",\"description\":\"Refund payment for Umrah Booking #47 - KamAir\",\"amount\":200,\"currency\":\"USD\",\"client_type\":\"regular\",\"main_account_id\":11,\"name\":\"KamAir\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 11:43:49'),
(1300, 1, 1, 'update', '0', 862, '{\"transaction_id\":\"862\",\"booking_id\":\"17\",\"amount\":200,\"description\":\"\",\"created_at\":\"2025-09-02 16:12:31\",\"currency\":\"USD\",\"type\":\"debit\"}', '{\"amount\":200,\"description\":\"Refund payment for Umrah Booking #47 - KamAir\",\"created_at\":\"2025-09-02 16:12:31\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 11:43:55'),
(1301, 1, 1, 'delete', 'main_account_transactions', 862, '{\"transaction_id\":862,\"refund_id\":17,\"amount\":200,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 16:12:31\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 11:43:59'),
(1302, 1, 1, 'delete', 'main_account_transactions', 863, '{\"transaction_id\":863,\"refund_id\":17,\"amount\":200,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 16:12:31\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 11:44:02'),
(1303, 1, 1, 'delete', 'umrah_bookings', 48, '{\"booking_id\":48,\"client_id\":18,\"supplier_id\":27,\"paid_to\":11,\"currency\":\"USD\",\"client_type\":\"regular\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 11:51:06'),
(1304, 1, 1, 'add', 'umrah_bookings', 50, '[]', '{\"family_id\":\"8\",\"supplier\":\"27\",\"sold_to\":\"18\",\"paid_to\":\"11\",\"name\":\"NAVEED RASHIQ\",\"passport_number\":\"P03241263\",\"flight_date\":\"\",\"return_date\":\"\",\"room_type\":\"1 Bed\",\"price\":\"1000\",\"sold_price\":\"1200\",\"profit\":\"200.00\",\"exchange_rate\":\"\",\"remarks\":\"Base amount of 1000 USD deducted for umrah.\",\"discount\":\"0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-02 12:45:15'),
(1305, 1, 1, 'add', 'visa_applications', 49, '[]', '{\"supplier\":27,\"sold_to\":\"18\",\"paid_to\":\"11\",\"applicant_name\":\"guli\",\"passport_number\":\"P8798765\",\"visa_type\":\"Tourist\",\"base\":100,\"sold\":200,\"profit\":100,\"currency\":\"USD\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 04:10:03'),
(1306, 1, 1, 'update', 'visa_applications', 49, '{\"id\":49,\"supplier\":27,\"sold_to\":18,\"base\":\"100.000\",\"sold\":\"200.000\",\"currency\":\"USD\"}', '{\"id\":49,\"supplier\":27,\"sold_to\":18,\"title\":\"Mr\",\"gender\":\"Male\",\"applicant_name\":\"guli\",\"passport_number\":\"P8798765\",\"country\":\"Pakistan\",\"visa_type\":\"Tourist\",\"receive_date\":\"2025-09-01\",\"applied_date\":\"2025-09-03\",\"issued_date\":\"2025-09-03\",\"base\":100,\"sold\":200,\"profit\":100,\"currency\":\"USD\",\"status\":\"Approved\",\"remarks\":\"test\",\"phone\":\"0780310431\",\"exchange_rate\":70}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 04:10:34'),
(1307, 1, 1, 'update', 'main_account_transactions', 864, '{\"transaction_id\":\"864\",\"visa_id\":\"49\",\"amount\":\"10.000\",\"currency\":\"USD\",\"type\":\"credit\",\"created_at\":\"2025-09-03 08:40:36\"}', '{\"transaction_id\":\"864\",\"visa_id\":\"49\",\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-09-03 08:40:36\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 04:10:53'),
(1308, 1, 1, 'add', 'main_account_transactions', 871, '[]', '{\"refund_id\":3,\"payment_date\":\"2025-09-03 09:52:20 09:52:20\",\"description\":\"Refund payment for Visa Application #49 - guli\",\"amount\":0.14,\"currency\":\"USD\",\"client_type\":\"regular\",\"main_account_id\":11,\"applicant_name\":\"guli\",\"passport_number\":\"P8798765\",\"country\":\"Pakistan\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 05:29:44'),
(1309, 1, 1, 'update', '0', 871, '{\"transaction_id\":\"871\",\"visa_id\":\"3\",\"amount\":0.14,\"description\":\"\",\"created_at\":\"2025-09-03 09:52:20\",\"currency\":\"USD\",\"type\":\"debit\"}', '{\"amount\":10,\"description\":\"Refund payment for Visa Application #49 - guli\",\"created_at\":\"2025-09-03 09:52:20\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 05:32:24'),
(1310, 1, 1, 'delete', 'main_account_transactions', 871, '{\"transaction_id\":871,\"refund_id\":3,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-03 09:52:20\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 05:32:28'),
(1311, 1, 1, 'add', 'main_account_transactions', 872, '[]', '{\"refund_id\":3,\"payment_date\":\"2025-09-03 09:59:45 09:59:45\",\"description\":\"Refund payment for Visa Application #49 - guli\",\"amount\":10,\"currency\":\"USD\",\"client_type\":\"regular\",\"main_account_id\":11,\"applicant_name\":\"guli\",\"passport_number\":\"P8798765\",\"country\":\"Pakistan\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 05:32:53'),
(1312, 1, 1, 'update', 'main_account_transactions', 813, '{\"transaction_id\":\"813\",\"transaction_type\":\"main\",\"amount\":5000,\"type\":\"credit\",\"date\":\"2025-09-01 13:23:30\"}', '{\"amount\":5000,\"type\":\"credit\",\"date\":\"2025-09-01T08:53\",\"receipt\":\"t43t4tw\",\"description\":\"Account funded by Sabaoon. Remarks: dfsadf. Receipt: t43t4tw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 06:23:07'),
(1313, 1, 1, 'update', 'main_account', 11, '{\"name\":\"AZIZI BANKadf\",\"account_type\":\"bank\",\"bank_account_number\":\"0325313513\",\"status\":\"active\"}', '{\"name\":\"AZIZI BANKadf\",\"account_type\":\"bank\",\"bank_account_number\":\"0325313513\",\"status\":\"active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 06:33:22'),
(1314, 1, 1, 'update', 'main_account', 11, '{\"name\":\"AZIZI BANKadf\",\"account_type\":\"bank\",\"bank_account_number\":\"0325313513\",\"status\":\"active\"}', '{\"name\":\"AZIZI BANKadf\",\"account_type\":\"bank\",\"bank_account_number\":\"0325313513\",\"status\":\"active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 06:38:07'),
(1315, 1, 1, 'add', 'refunded_tickets', 93, '{\"ticket_id\":320,\"passenger_name\":\"guli\",\"pnr\":\"188JZ0\",\"base\":100,\"sold\":200,\"supplier_penalty\":10,\"service_penalty\":10,\"currency\":\"USD\",\"status\":\"pending\",\"description\":\"dfsf\",\"calculation_method\":\"sold\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 06:49:04'),
(1316, 1, 1, 'add', 'hotel_bookings', 34, '[]', '{\"title\":\"Mr\",\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"gender\":\"Male\",\"check_in_date\":\"2025-09-01\",\"check_out_date\":\"2025-09-04\",\"accommodation_details\":\"dfgd\",\"supplier_id\":27,\"sold_to\":\"18\",\"base_amount\":10,\"sold_amount\":20,\"profit\":10,\"currency\":\"USD\",\"paid_to\":\"11\",\"exchange_rate\":70}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-03 06:57:23'),
(1317, 1, 1, 'add', 'main_account_transactions', 876, '[]', '{\"booking_id\":320,\"payment_date\":\"2025-09-07 11:27:52\",\"description\":\"test\",\"amount\":20,\"currency\":\"USD\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-07 06:58:13'),
(1318, 1, 1, 'delete', 'refunded_tickets', 93, '{\"refund_id\":93,\"client_id\":18,\"supplier_id\":\"27\",\"main_account_id\":11,\"currency\":\"USD\",\"client_type\":\"regular\",\"pnr\":\"188JZ0\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-07 08:02:05'),
(1319, 1, 1, 'add', 'ticket_bookings', 321, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"WKEPD1\",\"origin\":\"MZR\",\"destination\":\"DOH\",\"airline\":\"EI\",\"departure_date\":\"2025-10-01\",\"total_base\":200,\"total_sold\":220,\"total_discount\":0,\"total_profit\":20,\"currency\":\"USD\",\"supplier_id\":27,\"supplier_name\":\"KamAir\",\"client_id\":18,\"client_name\":\"DR SAHIBs\",\"trip_type\":\"one_way\",\"exchange_rate\":71}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-07 08:03:40'),
(1320, 1, 1, 'add', 'suppliers', 29, '[]', '{\"name\":\"NAVEED RASHIQ\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"AFS\",\"balance\":0,\"supplier_type\":\"Internal\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-07 08:41:35'),
(1321, 1, 1, 'add', 'clients', 19, '[]', '{\"name\":\"NAVEED RASHIQ\",\"email\":\"almuqadas_travel@yahoo.com\",\"client_type\":\"agency\",\"phone\":\"0777305730\",\"usd_balance\":\"0.00\",\"afs_balance\":\"0.00\",\"address\":\"Jada-e-Maiwand\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-07 08:42:27'),
(1322, 1, 1, 'update', '0', 321, '{\"ticket_id\":321,\"supplier\":\"27\",\"sold_to\":18,\"price\":\"200.000\",\"sold\":\"220.000\",\"currency\":\"USD\"}', '{\"supplier\":27,\"sold_to\":19,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"adult\",\"pnr\":\"WKEPD1\",\"phone\":\"0700907993\",\"origin\":\"MZR\",\"destination\":\"DOH\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"EI\",\"issue_date\":\"2025-09-09\",\"departure_date\":\"2025-10-01\",\"return_date\":\"\",\"price\":200,\"sold\":220,\"profit\":20,\"currency\":\"USD\",\"description\":\"CASH PAID BY MR MATIULLAH\",\"paid_to\":11,\"exchange_rate\":71,\"market_exchange_rate\":70}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-07 08:43:45'),
(1323, 1, 1, 'add', 'main_account_transactions', 877, '[]', '{\"booking_id\":321,\"payment_date\":\"2025-09-07 13:15:48\",\"description\":\"test\",\"amount\":20,\"currency\":\"USD\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-07 08:46:01'),
(1324, 1, 1, 'add', 'refunded_tickets', 94, '{\"ticket_id\":321,\"passenger_name\":\"adult\",\"pnr\":\"WKEPD1\",\"base\":200,\"sold\":220,\"supplier_penalty\":20,\"service_penalty\":20,\"currency\":\"USD\",\"status\":\"Refunded\",\"description\":\"CASH PAID BY MR MATIULLAH\",\"calculation_method\":\"base\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-07 08:54:23'),
(1325, 2, 7, 'update', 'settings', 2, '{\"id\":2,\"agency_name\":\"Al Wali\",\"title\":\"Al Wali Travel\",\"phone\":\"0786011115\",\"email\":\"alwali@gmail.com\",\"address\":\"kabul\",\"logo\":null}', '{\"id\":2,\"agency_name\":\"Al Wali\",\"title\":\"Al Wali Travel\",\"phone\":\"0786011115\",\"email\":\"alwali@gmail.com\",\"address\":\"kabul\",\"logo\":\"file_68b9695fea986_Blue_and_White_Modern_Travel_Instagram_Post (1).png\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-07 09:19:45'),
(1326, 2, 7, 'update', 'users', 7, '[]', '{\"name\":\"Matiullah Rahimi\",\"email\":\"mati@gmail.com\",\"phone\":\"0777555594\",\"address\":\"Jada-e-Maiwand\",\"profile_pic\":\"68bd5060a2eab_Blue and White Grunge Travel and Tourism Instagram Post.png\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-07 09:29:04'),
(1327, 2, 7, 'update', 'users', 7, '[]', '{\"name\":\"Matiullah Rahimi\",\"email\":\"mati@gmail.com\",\"phone\":\"0777555594\",\"address\":\"Jada-e-Maiwand\",\"profile_pic\":\"68bd507105c6e_Blue and White Grunge Travel and Tourism Instagram Post.png\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/139.0.0.0 Safari/537.36', '2025-09-07 09:29:21'),
(1328, 1, 1, 'create', 'ticket_weights', 8, NULL, '{\"ticket_id\":320,\"weight\":20,\"base_price\":10,\"sold_price\":20,\"profit\":10,\"remarks\":\"adfad\",\"supplier_name\":\"KamAir\",\"client_name\":\"DR SAHIBs\",\"currency\":\"USD\"}', '::1', '0', '2025-09-09 12:16:22'),
(1329, 1, 1, 'add', 'main_account_transactions', 878, '[]', '{\"booking_id\":320,\"payment_date\":\"2025-09-10 13:00:14\",\"description\":\"test\",\"amount\":30,\"currency\":\"AFS\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 08:40:54'),
(1330, 1, 1, 'add', 'main_account_transactions', 880, '[]', '{\"booking_id\":34,\"payment_date\":null,\"transaction_type\":\"credit\",\"description\":\"fadsfa\",\"amount\":20,\"currency\":\"USD\",\"is_refund\":null,\"original_transaction_id\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 09:47:23'),
(1331, 1, 1, 'add', 'main_account_transactions', 881, '[]', '{\"booking_id\":94,\"payment_date\":\"2025-09-10 14:17:55\",\"description\":\"test\",\"amount\":20,\"currency\":\"USD\",\"client_type\":\"agency\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 09:48:06'),
(1332, 1, 1, 'add', 'main_account_transactions', 882, '[]', '{\"booking_id\":94,\"payment_date\":\"2025-09-10 14:18:06\",\"description\":\"ytest\",\"amount\":30,\"currency\":\"USD\",\"client_type\":\"agency\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 09:50:59'),
(1333, 1, 1, 'create', 'main_account_transactions', 883, NULL, '{\"weight_id\":8,\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-10 14:29\",\"remarks\":\"test\",\"main_account_id\":11,\"balance\":3515.14}', '::1', '0', '2025-09-10 09:59:59'),
(1334, 2, 7, 'add', 'main_account', 12, '[]', '{\"name\":\"SELF BANK (SAFE)\",\"account_type\":\"internal\",\"bank_account_number\":null,\"bank_name\":null,\"usd_balance\":\"0\",\"afs_balance\":\"0\",\"status\":\"active\",\"tenant_id\":2}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 11:47:45'),
(1335, 2, 7, 'add', 'suppliers', 30, '[]', '{\"name\":\"NAVEED RASHIQ\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"AFS\",\"balance\":0,\"supplier_type\":\"External\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 11:48:12'),
(1336, 2, 7, 'add', 'clients', 20, '[]', '{\"name\":\"DR SAHIB\",\"email\":\"DRal@GMAIL.COM\",\"client_type\":\"regular\",\"phone\":\"0777305730\",\"usd_balance\":\"0.00\",\"afs_balance\":\"0.00\",\"address\":\"Jada-e-Maiwand\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 11:48:39'),
(1337, 2, 7, 'fund', 'main_account', 12, '{\"account_id\":\"12\",\"usd_balance\":\"0.000\"}', '{\"account_id\":\"12\",\"usd_balance\":1000,\"amount\":1000,\"currency\":\"USD\",\"description\":\"Account funded by Matiullah Rahimi. Remarks: test. Receipt: t43t4tw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 11:49:14'),
(1338, 2, 7, 'update', '0', 30, '{\"name\":\"NAVEED RASHIQ\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"0.000\"}', '{\"name\":\"NAVEED RASHIQ\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"USD\",\"balance\":\"0.000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 11:50:05'),
(1339, 2, 7, 'fund', 'suppliers', 30, '{\"supplier_id\":30,\"supplier_balance\":\"0.000\",\"main_account_id\":12,\"main_account_balance\":1000}', '{\"supplier_id\":30,\"supplier_balance\":100,\"main_account_id\":12,\"main_account_balance\":900,\"amount\":100,\"currency\":\"USD\",\"remarks\":\"test\",\"receipt_number\":\"2452345\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 11:52:08'),
(1340, 2, 7, 'add', 'ticket_bookings', 322, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"188JZ0\",\"origin\":\"MZR\",\"destination\":\"FRA\",\"airline\":\"KM\",\"departure_date\":\"2025-09-18\",\"total_base\":10,\"total_sold\":50,\"total_discount\":0,\"total_profit\":40,\"currency\":\"USD\",\"supplier_id\":30,\"supplier_name\":\"NAVEED RASHIQ\",\"client_id\":20,\"client_name\":\"DR SAHIB\",\"trip_type\":\"one_way\",\"exchange_rate\":71}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 11:53:35');
INSERT INTO `activity_log` (`id`, `tenant_id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1341, 2, 7, 'add', 'main_account_transactions', 886, '[]', '{\"booking_id\":322,\"payment_date\":\"2025-09-10 16:23:35\",\"description\":\"test\",\"amount\":30,\"currency\":\"USD\",\"main_account_id\":12}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 11:54:20'),
(1342, 2, 7, 'delete', 'main_account_transactions', 886, '{\"transaction_id\":886,\"ticket_id\":322,\"amount\":\"30.000\",\"currency\":\"USD\",\"main_account_id\":12,\"created_at\":\"2025-09-10 16:23:35\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 11:54:37'),
(1343, 2, 7, 'add', 'umrah_bookings', 51, '[]', '{\"family_id\":\"9\",\"supplier\":\"30\",\"sold_to\":\"20\",\"paid_to\":\"12\",\"name\":\"Matiullah\",\"passport_number\":\"P03241263\",\"flight_date\":\"\",\"return_date\":\"\",\"room_type\":\"Shared\",\"price\":\"50\",\"sold_price\":\"80\",\"profit\":\"30.00\",\"exchange_rate\":\"\",\"remarks\":\"Base amount of 50 USD deducted for umrah.\",\"discount\":\"0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 11:57:18'),
(1344, 2, 7, 'add', 'expense_categories', 20, '[]', '{\"name\":\"wali\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 11:58:59'),
(1345, 2, 7, 'fund', 'main_account', 12, '{\"account_id\":\"12\",\"afs_balance\":\"0.000\"}', '{\"account_id\":\"12\",\"afs_balance\":10000,\"amount\":10000,\"currency\":\"AFS\",\"description\":\"Account funded by Matiullah Rahimi. Remarks: teste. Receipt: 62452\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 11:59:56'),
(1346, 2, 7, 'add', 'expenses', 213, '[]', '{\"category_id\":\"20\",\"date\":\"2025-09-10\",\"description\":\"this is for test\",\"amount\":\"100\",\"currency\":\"AFS\",\"main_account_id\":\"12\",\"allocation_id\":\"\",\"receipt_number\":\"\",\"receipt_file\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 12:00:25'),
(1347, 2, 7, 'add', 'hotel_bookings', 35, '[]', '{\"title\":\"Mr\",\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"gender\":\"Male\",\"check_in_date\":\"2025-09-12\",\"check_out_date\":\"2025-09-23\",\"accommodation_details\":\"test\",\"supplier_id\":30,\"sold_to\":\"20\",\"base_amount\":10,\"sold_amount\":50,\"profit\":40,\"currency\":\"USD\",\"paid_to\":\"12\",\"exchange_rate\":71}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 12:18:28'),
(1348, 2, 7, 'add', 'main_account_transactions', 892, '[]', '{\"booking_id\":35,\"payment_date\":null,\"transaction_type\":\"credit\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"is_refund\":null,\"original_transaction_id\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 12:19:08'),
(1349, 2, 7, 'add', 'visa_applications', 50, '[]', '{\"supplier\":30,\"sold_to\":\"20\",\"paid_to\":\"12\",\"applicant_name\":\"guli\",\"passport_number\":\"P8798765\",\"visa_type\":\"Medical\",\"base\":10,\"sold\":30,\"profit\":20,\"currency\":\"USD\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 12:20:10'),
(1350, 2, 7, 'add', 'additional_payments', 46, NULL, '{\"id\":46,\"payment_type\":\"Vacine\",\"description\":\"test\",\"base_amount\":10,\"profit\":20,\"sold_amount\":30,\"currency\":\"USD\",\"main_account_id\":12,\"supplier_id\":30,\"is_from_supplier\":1,\"client_id\":20,\"is_for_client\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 12:20:39'),
(1351, 2, 7, 'add', 'main_account_transactions', 893, '[]', '{\"main_account_id\":12,\"amount\":20,\"currency\":\"USD\",\"description\":\"tets\",\"payment_id\":46,\"balance\":1930,\"payment_datetime\":\"2025-09-10 16:51:04\",\"tenant_id\":2}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 12:21:12'),
(1352, 2, 7, 'update', '0', 688, '{\"notification_id\":688,\"previous_status\":\"Read\"}', '{\"status\":\"read\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 12:29:59'),
(1353, 2, 7, 'add', 'clients', 21, '[]', '{\"name\":\"walkings\",\"email\":\"esmati@gmail.com\",\"client_type\":\"agency\",\"phone\":\"0777305730\",\"usd_balance\":\"0.00\",\"afs_balance\":\"0.00\",\"address\":\"Jada-e-Maiwand\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 12:31:12'),
(1354, 2, 7, 'add', 'ticket_bookings', 323, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"188JZdfg\",\"origin\":\" KBL\",\"destination\":\"ISB\",\"airline\":\"AV\",\"departure_date\":\"2025-10-03\",\"total_base\":60,\"total_sold\":100,\"total_discount\":0,\"total_profit\":40,\"currency\":\"USD\",\"supplier_id\":30,\"supplier_name\":\"NAVEED RASHIQ\",\"client_id\":21,\"client_name\":\"walkings\",\"trip_type\":\"one_way\",\"exchange_rate\":71}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 12:32:10'),
(1355, 2, 7, 'add', 'main_account_transactions', 894, '[]', '{\"booking_id\":323,\"payment_date\":\"2025-09-10 17:02:11\",\"description\":\"test\",\"amount\":2000,\"currency\":\"AFS\",\"main_account_id\":12}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 12:32:32'),
(1356, 2, 7, 'update', '0', 689, '{\"notification_id\":689,\"previous_status\":\"Read\"}', '{\"status\":\"read\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 12:33:19'),
(1357, 2, 7, 'add', 'hotel_bookings', 36, '[]', '{\"title\":\"Mrs\",\"first_name\":\"NAVEEDdfsdf\",\"last_name\":\"RASHIQgdfg\",\"gender\":\"Female\",\"check_in_date\":\"2025-09-12\",\"check_out_date\":\"2025-09-22\",\"accommodation_details\":\"dsafsd\",\"supplier_id\":30,\"sold_to\":\"21\",\"base_amount\":50,\"sold_amount\":60,\"profit\":10,\"currency\":\"USD\",\"paid_to\":\"12\",\"exchange_rate\":70}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 12:37:21'),
(1358, 2, 7, 'add', 'main_account_transactions', 895, '[]', '{\"booking_id\":36,\"payment_date\":null,\"transaction_type\":\"credit\",\"description\":\"tesst\",\"amount\":20,\"currency\":\"USD\",\"is_refund\":null,\"original_transaction_id\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 12:37:54'),
(1359, 2, 7, 'add', 'umrah_transactions', 45, '[]', '{\"umrah_booking_id\":51,\"transaction_to\":\"Internal Account\",\"payment_amount\":20,\"payment_currency\":\"USD\",\"payment_description\":\"ytertse\",\"payment_date\":\"2025-09-10\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 12:47:26'),
(1360, 2, 7, 'add', 'umrah_transactions', 46, '[]', '{\"umrah_booking_id\":51,\"transaction_to\":\"Bank\",\"payment_amount\":60,\"payment_currency\":\"USD\",\"payment_description\":\"tets\",\"payment_date\":\"2025-09-10\",\"receipt_number\":\"2452345\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 12:48:01'),
(1361, 2, 7, 'add', 'jv_payments', 2, '{}', '{\"jv_payment_id\":\"2\",\"jv_name\":\"Client-Supplier Payment\",\"client_id\":20,\"client_name\":\"DR SAHIB\",\"supplier_id\":30,\"supplier_name\":\"NAVEED RASHIQ\",\"amount\":50,\"supplier_amount\":50,\"currency\":\"USD\",\"supplier_currency\":\"USD\",\"exchange_rate\":71,\"receipt\":\"1213231\",\"remarks\":\"dsfgs\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-10 13:03:52'),
(1362, 1, 1, 'add', 'main_account_transactions', 900, '[]', '{\"booking_id\":321,\"payment_date\":\"2025-09-11 10:36:34\",\"description\":\"tet (Exchange Rate: 2000)\",\"amount\":1000,\"currency\":\"AFS\",\"exchange_rate\":2000,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 06:10:04'),
(1363, 1, 1, 'update', '0', 900, '{\"transaction_id\":\"900\",\"ticket_id\":\"321\",\"amount\":1000,\"description\":\"\",\"created_at\":\"2025-09-11 10:36:34\",\"type\":\"credit\",\"currency\":\"AFS\",\"exchange_rate\":null}', '{\"amount\":1000,\"description\":\"tet (Exchange Rate: 2000)\",\"created_at\":\"2025-09-11 10:36:34\",\"exchange_rate\":71}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 06:10:24'),
(1364, 1, 1, 'update', '0', 900, '{\"transaction_id\":\"900\",\"ticket_id\":\"321\",\"amount\":1000,\"description\":\"\",\"created_at\":\"2025-09-11 10:36:34\",\"type\":\"credit\",\"currency\":\"AFS\",\"exchange_rate\":null}', '{\"amount\":1000,\"description\":\"tet (Exchange Rate: 2000)\",\"created_at\":\"2025-09-11 10:36:34\",\"exchange_rate\":71}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 06:10:30'),
(1365, 1, 1, 'update', '0', 900, '{\"transaction_id\":\"900\",\"ticket_id\":\"321\",\"amount\":1000,\"description\":\"\",\"created_at\":\"2025-09-11 10:36:34\",\"type\":\"credit\",\"currency\":\"AFS\",\"exchange_rate\":null}', '{\"amount\":1000,\"description\":\"tet (Exchange Rate: 2000)\",\"created_at\":\"2025-09-11 10:36:34\",\"exchange_rate\":71}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 06:10:37'),
(1366, 1, 1, 'update', '0', 900, '{\"transaction_id\":\"900\",\"ticket_id\":\"321\",\"amount\":1000,\"description\":\"\",\"created_at\":\"2025-09-11 10:36:34\",\"type\":\"credit\",\"currency\":\"AFS\",\"exchange_rate\":null}', '{\"amount\":1000,\"description\":\"tet (Exchange Rate: 2000) (Exchange Rate: 71)\",\"created_at\":\"2025-09-11 10:36:34\",\"exchange_rate\":71}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 06:12:12'),
(1367, 1, 1, 'add', 'main_account_transactions', 901, '[]', '{\"booking_id\":321,\"payment_date\":\"2025-09-11 10:42:17\",\"description\":\"test (Exchange Rate: 71)\",\"amount\":100,\"currency\":\"AFS\",\"exchange_rate\":71,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 06:12:59'),
(1368, 1, 1, 'update', '0', 900, '{\"transaction_id\":\"900\",\"ticket_id\":\"321\",\"amount\":1000,\"description\":\"tet (Exchange Rate: 2000) (Exchange Rate: 71)\",\"created_at\":\"2025-09-11 10:36:34\",\"type\":\"credit\",\"currency\":\"AFS\",\"exchange_rate\":\"2000\"}', '{\"amount\":1000,\"description\":\"tet (Exchange Rate: 71)\",\"created_at\":\"2025-09-11 10:36:34\",\"exchange_rate\":71}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 06:20:11'),
(1369, 1, 1, 'add', 'main_account_transactions', 902, '[]', '{\"booking_id\":321,\"payment_date\":\"2025-09-11 10:56:54\",\"description\":\"asdf (Exchange Rate: 0.9)\",\"amount\":20,\"currency\":\"EUR\",\"exchange_rate\":0.9,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 06:29:10'),
(1370, 1, 1, 'add', 'main_account_transactions', 903, '[]', '{\"booking_id\":321,\"payment_date\":\"2025-09-11 10:59:10\",\"description\":\"adfdf (Exchange Rate: 3.71)\",\"amount\":100,\"currency\":\"DARHAM\",\"exchange_rate\":3.71,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 06:38:15'),
(1371, 1, 1, 'delete', 'main_account_transactions', 900, '{\"transaction_id\":900,\"ticket_id\":321,\"amount\":\"1000.000\",\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-11 10:36:34\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 06:46:03'),
(1372, 1, 1, 'delete', 'main_account_transactions', 901, '{\"transaction_id\":901,\"ticket_id\":321,\"amount\":\"100.000\",\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-11 10:42:17\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 06:46:22'),
(1373, 1, 1, 'delete', 'main_account_transactions', 902, '{\"transaction_id\":902,\"ticket_id\":321,\"amount\":\"20.000\",\"currency\":\"EUR\",\"main_account_id\":11,\"created_at\":\"2025-09-11 10:56:54\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 06:48:30'),
(1374, 1, 1, 'delete', 'main_account_transactions', 903, '{\"transaction_id\":903,\"ticket_id\":321,\"amount\":\"100.000\",\"currency\":\"DARHAM\",\"main_account_id\":11,\"created_at\":\"2025-09-11 10:59:10\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 06:49:03'),
(1375, 1, 1, 'add', 'main_account_transactions', 904, '[]', '{\"booking_id\":321,\"payment_date\":\"2025-09-11 11:26:12\",\"description\":\"asfds (Exchange Rate: 70)\",\"amount\":1000,\"currency\":\"AFS\",\"exchange_rate\":70,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 06:56:34'),
(1376, 1, 1, 'add', 'main_account_transactions', 905, '[]', '{\"booking_id\":321,\"payment_date\":\"2025-09-11 11:26:40\",\"description\":\"dfasdf (Exchange Rate: 0.9)\",\"amount\":50,\"currency\":\"EUR\",\"exchange_rate\":0.9,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 06:57:23'),
(1377, 1, 1, 'add', 'main_account_transactions', 906, '[]', '{\"booking_id\":321,\"payment_date\":\"2025-09-11 11:27:23\",\"description\":\"asdfasf (Exchange Rate: 3.61)\",\"amount\":100,\"currency\":\"DARHAM\",\"exchange_rate\":3.61,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 06:57:50'),
(1378, 1, 1, 'add', 'main_account_transactions', 907, '[]', '{\"booking_id\":94,\"payment_date\":\"2025-09-11 11:55:08\",\"description\":\"fsadfd (Exchange Rate: 70)\",\"amount\":1000,\"currency\":\"AFS\",\"exchange_rate\":70,\"client_type\":\"agency\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 07:25:43'),
(1379, 1, 1, 'add', 'main_account_transactions', 908, '[]', '{\"booking_id\":94,\"payment_date\":\"2025-09-11 12:13:06\",\"description\":\"fga (Exchange Rate: 0.9)\",\"amount\":20,\"currency\":\"EUR\",\"exchange_rate\":0.9,\"client_type\":\"agency\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 07:43:33'),
(1380, 1, 1, 'add', 'main_account_transactions', 909, '[]', '{\"booking_id\":94,\"payment_date\":\"2025-09-11 12:13:33\",\"description\":\"adfsadsf (Exchange Rate: 3.61)\",\"amount\":100,\"currency\":\"DARHAM\",\"exchange_rate\":3.61,\"client_type\":\"agency\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 07:43:52'),
(1381, 1, 1, 'delete', 'main_account_transactions', 828, '{\"transaction_id\":828,\"ticket_id\":43,\"amount\":10,\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-02 11:50:40\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 07:57:46'),
(1382, 1, 1, 'add', 'main_account_transactions', 910, '[]', '{\"booking_id\":43,\"payment_date\":\"2025-09-11 12:27:17\",\"description\":\"asfdsaf (Exchange Rate: 70)\",\"amount\":1000,\"currency\":\"AFS\",\"exchange_rate\":70,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 07:58:06'),
(1383, 1, 1, 'add', 'main_account_transactions', 911, '[]', '{\"booking_id\":43,\"payment_date\":\"2025-09-11 12:28:28\",\"description\":\"asdfasdf (Exchange Rate: 0.9)\",\"amount\":10,\"currency\":\"EUR\",\"exchange_rate\":0.9,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 07:59:20'),
(1384, 1, 1, 'create', 'main_account_transactions', 912, NULL, '{\"weight_id\":8,\"amount\":500,\"currency\":\"AFS\",\"exchange_rate\":70,\"transaction_date\":\"2025-09-11 12:40\",\"remarks\":\"adfadf (Exchange Rate: 70)\",\"main_account_id\":11,\"balance\":-15690}', '::1', '0', '2025-09-11 08:10:41'),
(1385, 1, 1, 'delete', 'main_account_transactions', 912, '{\"transaction_id\":912,\"weight_id\":8,\"amount\":500,\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-11 12:40:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 09:04:40'),
(1386, 1, 1, 'create', 'main_account_transactions', 913, NULL, '{\"weight_id\":8,\"amount\":500,\"currency\":\"AFS\",\"exchange_rate\":70,\"transaction_date\":\"2025-09-11 13:34\",\"remarks\":\"xgbcxb (Exchange Rate: 70)\",\"main_account_id\":11,\"balance\":-15690}', '::1', '0', '2025-09-11 09:05:26'),
(1387, 1, 1, 'create', 'main_account_transactions', 914, NULL, '{\"weight_id\":8,\"amount\":1,\"currency\":\"EUR\",\"exchange_rate\":0.9,\"transaction_date\":\"2025-09-11 13:39\",\"remarks\":\"safgsadf (Exchange Rate: 0.9)\",\"main_account_id\":11,\"balance\":61}', '::1', '0', '2025-09-11 09:22:41'),
(1388, 1, 1, 'create', 'main_account_transactions', 915, NULL, '{\"weight_id\":8,\"amount\":10,\"currency\":\"DARHAM\",\"exchange_rate\":3.61,\"transaction_date\":\"2025-09-11 13:52\",\"remarks\":\"fsdafd (Exchange Rate: 3.61)\",\"main_account_id\":11,\"balance\":110}', '::1', '0', '2025-09-11 09:23:28'),
(1389, 1, 1, 'delete', 'main_account_transactions', 915, '{\"transaction_id\":915,\"weight_id\":8,\"amount\":10,\"currency\":\"DARHAM\",\"main_account_id\":11,\"created_at\":\"2025-09-11 13:52:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 09:31:00'),
(1390, 1, 1, 'create', 'main_account_transactions', 916, NULL, '{\"weight_id\":8,\"amount\":5,\"currency\":\"DARHAM\",\"exchange_rate\":3.61,\"transaction_date\":\"2025-09-11 14:01\",\"remarks\":\"dsxgsdf (Exchange Rate: 3.61)\",\"main_account_id\":11,\"balance\":105}', '::1', '0', '2025-09-11 09:31:30'),
(1391, 1, 1, 'add', 'main_account_transactions', 917, '[]', '{\"booking_id\":11,\"payment_date\":\"2025-09-11 15:37:46\",\"description\":\"zvdgvf (Exchange Rate: 70)\",\"amount\":0,\"currency\":\"AFS\",\"exchange_rate\":70,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 11:08:04'),
(1392, 1, 1, 'add', 'main_account_transactions', 918, '[]', '{\"booking_id\":11,\"payment_date\":\"2025-09-11 15:37:46\",\"description\":\"zvdgvf (Exchange Rate: 70)\",\"amount\":0,\"currency\":\"AFS\",\"exchange_rate\":70,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 11:10:31'),
(1393, 1, 1, 'add', 'main_account_transactions', 919, '[]', '{\"booking_id\":11,\"payment_date\":\"2025-09-11 15:40:46\",\"description\":\"ghdfhg (Exchange Rate: 70)\",\"amount\":0,\"currency\":\"AFS\",\"exchange_rate\":70,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 11:11:05'),
(1394, 1, 1, 'add', 'main_account_transactions', 920, '[]', '{\"booking_id\":11,\"payment_date\":\"2025-09-11 15:43:18\",\"description\":\"xbvb (Exchange Rate: 70)\",\"amount\":0,\"currency\":\"AFS\",\"exchange_rate\":70,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 11:13:38'),
(1395, 1, 1, 'add', 'main_account_transactions', 921, '[]', '{\"booking_id\":11,\"payment_date\":\"2025-09-11 15:47:13\",\"description\":\"adfadsf (Exchange Rate: 70)\",\"amount\":1000,\"currency\":\"AFS\",\"exchange_rate\":70,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 11:17:33'),
(1396, 1, 1, 'add', 'main_account_transactions', 922, '[]', '{\"booking_id\":11,\"payment_date\":\"2025-09-11 15:58:57\",\"description\":\"adfadfs (Exchange Rate: 0.9)\",\"amount\":50,\"currency\":\"EUR\",\"exchange_rate\":0.9,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 11:29:30'),
(1397, 1, 1, 'add', 'main_account_transactions', 923, '[]', '{\"booking_id\":11,\"payment_date\":\"2025-09-11 15:59:30\",\"description\":\"adfadf (Exchange Rate: 3.61)\",\"amount\":200,\"currency\":\"DARHAM\",\"exchange_rate\":3.61,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 11:30:17'),
(1398, 1, 1, 'delete', 'main_account_transactions', 880, '{\"transaction_id\":880,\"booking_id\":34,\"amount\":20,\"currency\":\"USD\",\"transaction_type\":\"credit\",\"is_refund\":false,\"main_account_id\":11,\"description\":\"fadsfa\",\"created_at\":\"2025-09-10 14:17:23\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 12:08:55'),
(1399, 1, 1, 'add', 'main_account_transactions', 924, '[]', '{\"booking_id\":34,\"payment_date\":null,\"transaction_type\":\"credit\",\"description\":\"sgfgsdf\",\"amount\":100,\"currency\":\"AFS\",\"is_refund\":null,\"original_transaction_id\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 12:27:55'),
(1400, 1, 1, 'update', '0', 924, '{\"transaction_id\":\"924\",\"booking_id\":\"34\",\"amount\":100,\"type\":\"credit\",\"created_at\":\"2025-09-11 16:57:55\",\"description\":\"\",\"currency\":\"USD\"}', '{\"amount\":100,\"type\":\"credit\",\"description\":\"sgfgsdf\",\"created_at\":\"2025-09-11 16:57:55\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 12:29:54'),
(1401, 1, 1, 'update', '0', 924, '{\"transaction_id\":\"924\",\"booking_id\":\"34\",\"amount\":100,\"type\":\"credit\",\"created_at\":\"2025-09-11 16:57:55\",\"description\":\"\",\"currency\":\"USD\"}', '{\"amount\":100,\"type\":\"credit\",\"description\":\"sgfgsdf\",\"created_at\":\"2025-09-11 16:57:55\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 12:33:02'),
(1402, 1, 1, 'add', 'main_account_transactions', 925, '[]', '{\"booking_id\":34,\"payment_date\":null,\"transaction_type\":\"credit\",\"description\":\"adfadsf\",\"amount\":2,\"currency\":\"EUR\",\"is_refund\":null,\"original_transaction_id\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-11 12:40:18'),
(1403, 1, 1, 'add', 'main_account_transactions', 926, '[]', '{\"refund_id\":7,\"payment_date\":\"2025-09-13 09:31:24 00:00:00\",\"description\":\"adfaf\",\"amount\":100,\"currency\":\"AFS\",\"client_type\":\"regular\",\"main_account_id\":0,\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"order_id\":\"1089734\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 05:01:37'),
(1404, 1, 1, 'add', 'main_account_transactions', 927, '[]', '{\"refund_id\":7,\"payment_date\":\"2025-09-13 09:35:23 00:00:00\",\"description\":\"adfadfs\",\"amount\":100,\"currency\":\"AFS\",\"client_type\":\"regular\",\"main_account_id\":0,\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"order_id\":\"1089734\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 05:05:58'),
(1405, 1, 1, 'add', 'main_account_transactions', 928, '[]', '{\"refund_id\":7,\"payment_date\":\"2025-09-13 09:39:00 00:00:00\",\"description\":\"adfadf\",\"amount\":100,\"currency\":\"AFS\",\"client_type\":\"regular\",\"main_account_id\":0,\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"order_id\":\"1089734\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 05:09:18'),
(1406, 1, 1, 'add', 'main_account_transactions', 929, '[]', '{\"refund_id\":7,\"payment_date\":\"2025-09-13 09:44:21 00:00:00\",\"description\":\"adfafs\",\"amount\":100,\"currency\":\"AFS\",\"client_type\":\"regular\",\"main_account_id\":0,\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"order_id\":\"1089734\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 05:14:38'),
(1407, 1, 1, 'update', '0', 929, '{\"transaction_id\":\"929\",\"booking_id\":\"7\",\"amount\":100,\"description\":\"\",\"created_at\":\"2025-09-13 09:44:21\",\"currency\":\"AFS\",\"type\":\"debit\"}', '{\"amount\":100,\"description\":\"adfafs\",\"created_at\":\"2025-09-13 09:44:21\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 05:32:06'),
(1408, 1, 1, 'add', 'main_account_transactions', 930, '[]', '{\"refund_id\":7,\"payment_date\":\"2025-09-13 10:10:12 10:10:12\",\"description\":\"Refund payment for Hotel Booking #34 - Mr NAVEED RASHIQ\",\"amount\":200,\"currency\":\"AFS\",\"client_type\":\"regular\",\"main_account_id\":11,\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"order_id\":\"1089734\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 05:40:31'),
(1409, 1, 1, 'delete', 'main_account_transactions', 930, '{\"transaction_id\":930,\"refund_id\":7,\"amount\":200,\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-13 10:10:12\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 05:42:50'),
(1410, 1, 1, 'add', 'main_account_transactions', 931, '[]', '{\"refund_id\":7,\"payment_date\":\"2025-09-13 10:12:55 10:12:55\",\"description\":\"Refund payment for Hotel Booking #34 - Mr NAVEED RASHIQ\",\"amount\":200,\"currency\":\"AFS\",\"client_type\":\"regular\",\"main_account_id\":11,\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"order_id\":\"1089734\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 05:43:10'),
(1411, 1, 1, 'add', 'main_account_transactions', 932, '[]', '{\"refund_id\":7,\"payment_date\":\"2025-09-13 10:27:59 10:27:59\",\"description\":\"Refund payment for Hotel Booking #34 - Mr NAVEED RASHIQ\",\"amount\":5,\"currency\":\"EUR\",\"client_type\":\"regular\",\"main_account_id\":11,\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"order_id\":\"1089734\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 05:58:52'),
(1412, 1, 1, 'add', 'main_account_transactions', 933, '[]', '{\"refund_id\":7,\"payment_date\":\"2025-09-13 10:28:52 10:28:52\",\"description\":\"adsfas\",\"amount\":10,\"currency\":\"DARHAM\",\"client_type\":\"regular\",\"main_account_id\":11,\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"order_id\":\"1089734\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 05:59:18'),
(1413, 1, 1, 'add', 'main_account_transactions', 934, '[]', '{\"refund_id\":7,\"payment_date\":\"2025-09-13 10:29:18 10:29:18\",\"description\":\"adsfad\",\"amount\":2,\"currency\":\"USD\",\"client_type\":\"regular\",\"main_account_id\":11,\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"order_id\":\"1089734\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 05:59:36'),
(1414, 1, 1, 'update', '0', 933, '{\"transaction_id\":\"933\",\"booking_id\":\"7\",\"amount\":10,\"description\":\"\",\"created_at\":\"2025-09-13 10:28:52\",\"currency\":\"DARHAM\",\"type\":\"debit\"}', '{\"amount\":10,\"description\":\"adsfas\",\"created_at\":\"2025-09-13 10:28:52\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 06:23:11'),
(1415, 1, 1, 'update', '0', 933, '{\"transaction_id\":\"933\",\"booking_id\":\"7\",\"amount\":10,\"description\":\"\",\"created_at\":\"2025-09-13 10:28:52\",\"currency\":\"DARHAM\",\"type\":\"debit\"}', '{\"amount\":12,\"description\":\"adsfas\",\"created_at\":\"2025-09-13 10:28:52\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 06:25:00'),
(1416, 1, 1, 'delete', 'main_account_transactions', 934, '{\"transaction_id\":934,\"refund_id\":7,\"amount\":2,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-13 10:29:18\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 06:25:29'),
(1417, 1, 1, 'update', 'main_account_transactions', 937, '{\"transaction_id\":\"937\",\"visa_id\":\"49\",\"amount\":\"50.000\",\"currency\":\"EUR\",\"type\":\"credit\",\"created_at\":\"2025-09-13 12:00:25\"}', '{\"transaction_id\":\"937\",\"visa_id\":\"49\",\"amount\":50,\"description\":\"fadsfdf\",\"created_at\":\"2025-09-13 12:00:25\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 07:36:11'),
(1418, 1, 1, 'update', 'main_account_transactions', 937, '{\"transaction_id\":\"937\",\"visa_id\":\"49\",\"amount\":\"50.000\",\"currency\":\"EUR\",\"type\":\"credit\",\"created_at\":\"2025-09-13 12:00:25\"}', '{\"transaction_id\":\"937\",\"visa_id\":\"49\",\"amount\":60,\"description\":\"fadsfdf\",\"created_at\":\"2025-09-13 12:00:25\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 07:40:29'),
(1419, 1, 1, 'add', 'main_account_transactions', 938, '[]', '{\"refund_id\":3,\"payment_date\":\"2025-09-13 12:33:32 12:33:32\",\"description\":\"Refund payment for Visa Application #49 - guli\",\"amount\":1000,\"currency\":\"AFS\",\"client_type\":\"regular\",\"main_account_id\":11,\"applicant_name\":\"guli\",\"passport_number\":\"P8798765\",\"country\":\"Pakistan\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 08:04:58'),
(1420, 1, 1, 'add', 'main_account_transactions', 939, '[]', '{\"refund_id\":3,\"payment_date\":\"2025-09-13 13:10:06 13:10:06\",\"description\":\"Refund payment for Visa Application #49 - guli\",\"amount\":1000,\"currency\":\"AFS\",\"client_type\":\"regular\",\"main_account_id\":11,\"applicant_name\":\"guli\",\"passport_number\":\"P8798765\",\"country\":\"Pakistan\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 08:40:25'),
(1421, 1, 1, 'update', '0', 939, '{\"transaction_id\":\"939\",\"visa_id\":\"3\",\"amount\":1000,\"description\":\"\",\"created_at\":\"2025-09-13 13:10:06\",\"currency\":\"AFS\",\"type\":\"debit\"}', '{\"amount\":1000,\"description\":\"Refund payment for Visa Application #49 - guli\",\"created_at\":\"2025-09-13 13:10:06\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 08:47:40'),
(1422, 1, 1, 'add', 'main_account_transactions', 940, '[]', '{\"refund_id\":3,\"payment_date\":\"2025-09-13 13:17:28 13:17:28\",\"description\":\"Refund payment for Visa Application #49 - guli\",\"amount\":200,\"currency\":\"AFS\",\"client_type\":\"regular\",\"main_account_id\":11,\"applicant_name\":\"guli\",\"passport_number\":\"P8798765\",\"country\":\"Pakistan\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 08:48:22'),
(1423, 1, 1, 'add', 'main_account_transactions', 941, '[]', '{\"refund_id\":3,\"payment_date\":\"2025-09-13 13:24:27 13:24:27\",\"description\":\"Refund payment for Visa Application #49 - guli\",\"amount\":300,\"currency\":\"AFS\",\"exchange_rate\":70,\"client_type\":\"regular\",\"main_account_id\":11,\"applicant_name\":\"guli\",\"passport_number\":\"P8798765\",\"country\":\"Pakistan\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 08:54:59'),
(1424, 1, 1, 'update', '0', 940, '{\"transaction_id\":\"940\",\"visa_id\":\"3\",\"amount\":200,\"description\":\"\",\"created_at\":\"2025-09-13 13:17:28\",\"currency\":\"AFS\",\"type\":\"debit\"}', '{\"amount\":200,\"description\":\"Refund payment for Visa Application #49 - guli\",\"created_at\":\"2025-09-13 13:17:28\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 08:55:19'),
(1425, 1, 1, 'delete', 'main_account_transactions', 938, '{\"transaction_id\":938,\"refund_id\":3,\"amount\":1000,\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-13 12:33:32\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 08:57:16'),
(1426, 1, 1, 'delete', 'main_account_transactions', 939, '{\"transaction_id\":939,\"refund_id\":3,\"amount\":1000,\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-13 13:10:06\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 08:57:19'),
(1427, 1, 1, 'delete', 'main_account_transactions', 940, '{\"transaction_id\":940,\"refund_id\":3,\"amount\":200,\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-13 13:17:28\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 08:57:21'),
(1428, 1, 1, 'delete', 'main_account_transactions', 941, '{\"transaction_id\":941,\"refund_id\":3,\"amount\":300,\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-13 13:24:27\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 08:57:24'),
(1429, 1, 1, 'add', 'main_account_transactions', 942, '[]', '{\"refund_id\":3,\"payment_date\":\"2025-09-13 13:27:35 13:27:35\",\"description\":\"Refund payment for Visa Application #49 - guli\",\"amount\":1000,\"currency\":\"AFS\",\"exchange_rate\":70,\"client_type\":\"regular\",\"main_account_id\":11,\"applicant_name\":\"guli\",\"passport_number\":\"P8798765\",\"country\":\"Pakistan\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 08:58:56'),
(1430, 1, 1, 'add', 'main_account_transactions', 943, '[]', '{\"refund_id\":3,\"payment_date\":\"2025-09-13 13:35:42 13:35:42\",\"description\":\"Refund payment for Visa Application #49 - guli\",\"amount\":500,\"currency\":\"AFS\",\"exchange_rate\":70,\"client_type\":\"regular\",\"main_account_id\":11,\"applicant_name\":\"guli\",\"passport_number\":\"P8798765\",\"country\":\"Pakistan\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 09:06:01'),
(1431, 1, 1, 'update', 'visa_applications', 49, '{\"id\":49,\"supplier\":27,\"sold_to\":18,\"base\":\"100.000\",\"sold\":\"200.000\",\"currency\":\"USD\"}', '{\"id\":49,\"supplier\":27,\"sold_to\":19,\"title\":\"Mr\",\"gender\":\"Male\",\"applicant_name\":\"guli\",\"passport_number\":\"P8798765\",\"country\":\"Pakistan\",\"visa_type\":\"Tourist\",\"receive_date\":\"2025-09-01\",\"applied_date\":\"2025-09-03\",\"issued_date\":\"2025-09-03\",\"base\":100,\"sold\":200,\"profit\":0,\"currency\":\"USD\",\"status\":\"\",\"remarks\":\"test\",\"phone\":\"0780310431\",\"exchange_rate\":70}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 09:10:56'),
(1432, 1, 1, 'add', 'main_account_transactions', 944, '[]', '{\"refund_id\":4,\"payment_date\":\"2025-09-13 13:41:45 13:41:45\",\"description\":\"Refund payment for Visa Application #49 - guli\",\"amount\":1000,\"currency\":\"AFS\",\"exchange_rate\":70,\"client_type\":\"agency\",\"main_account_id\":11,\"applicant_name\":\"guli\",\"passport_number\":\"P8798765\",\"country\":\"Pakistan\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 09:12:18'),
(1433, 1, 1, 'add', 'main_account_transactions', 945, '[]', '{\"refund_id\":4,\"payment_date\":\"2025-09-13 13:44:34 13:44:34\",\"description\":\"Refund payment for Visa Application #49 - guli\",\"amount\":50,\"currency\":\"EUR\",\"exchange_rate\":0.9,\"client_type\":\"agency\",\"main_account_id\":11,\"applicant_name\":\"guli\",\"passport_number\":\"P8798765\",\"country\":\"Pakistan\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 09:15:39'),
(1434, 1, 1, 'add', 'main_account_transactions', 946, '[]', '{\"refund_id\":4,\"payment_date\":\"2025-09-13 13:45:40 13:45:40\",\"description\":\"adfaf\",\"amount\":100,\"currency\":\"DARHAM\",\"exchange_rate\":3.61,\"client_type\":\"agency\",\"main_account_id\":11,\"applicant_name\":\"guli\",\"passport_number\":\"P8798765\",\"country\":\"Pakistan\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 09:16:07'),
(1435, 1, 1, 'add', 'main_account_transactions', 947, '[]', '{\"refund_id\":4,\"payment_date\":\"2025-09-13 13:46:09 13:46:09\",\"description\":\"afdadf\",\"amount\":20,\"currency\":\"USD\",\"exchange_rate\":0,\"client_type\":\"agency\",\"main_account_id\":11,\"applicant_name\":\"guli\",\"passport_number\":\"P8798765\",\"country\":\"Pakistan\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 09:16:24'),
(1436, 1, 1, 'update', '0', 944, '{\"transaction_id\":\"944\",\"visa_id\":\"4\",\"amount\":1000,\"description\":\"\",\"created_at\":\"2025-09-13 13:41:45\",\"currency\":\"AFS\",\"type\":\"debit\"}', '{\"amount\":1000,\"description\":\"Refund payment for Visa Application #49 - guli\",\"created_at\":\"2025-09-13 13:41:45\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 09:26:56'),
(1437, 1, 1, 'update', '0', 944, '{\"transaction_id\":\"944\",\"visa_id\":\"4\",\"amount\":1000,\"description\":\"\",\"created_at\":\"2025-09-13 13:41:45\",\"currency\":\"AFS\",\"type\":\"debit\"}', '{\"amount\":1000,\"description\":\"Refund payment for Visa Application #49 - guli\",\"created_at\":\"2025-09-13 13:41:45\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 09:28:38'),
(1438, 1, 1, 'add', 'umrah_transactions', 50, '[]', '{\"umrah_booking_id\":50,\"transaction_to\":\"Internal Account\",\"payment_amount\":1000,\"payment_currency\":\"AFS\",\"payment_description\":\"adfadsf\",\"payment_date\":\"2025-09-13\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 11:42:23'),
(1439, 1, 1, 'add', 'umrah_transactions', 51, '[]', '{\"umrah_booking_id\":50,\"transaction_to\":\"Internal Account\",\"payment_amount\":100,\"payment_currency\":\"EUR\",\"payment_description\":\"afdadsf\",\"payment_date\":\"2025-09-13\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 12:25:45'),
(1440, 1, 1, 'delete', 'umrah_transactions', 51, '{\"transaction_id\":51,\"umrah_id\":50,\"payment_amount\":100,\"currency\":\"EUR\",\"transaction_to\":\"Internal Account\",\"payment_description\":\"afdadsf\",\"is_refund\":false,\"supplier_id\":27,\"paid_to\":11}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 12:30:26'),
(1441, 1, 1, 'delete', 'umrah_transactions', 50, '{\"transaction_id\":50,\"umrah_id\":50,\"payment_amount\":1000,\"currency\":\"AFS\",\"transaction_to\":\"Internal Account\",\"payment_description\":\"adfadsf\",\"is_refund\":false,\"supplier_id\":27,\"paid_to\":11}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 12:30:52'),
(1442, 1, 1, 'add', 'umrah_transactions', 52, '[]', '{\"umrah_booking_id\":50,\"transaction_to\":\"Internal Account\",\"payment_amount\":1000,\"payment_currency\":\"AFS\",\"payment_description\":\"fdgsdfg\",\"payment_date\":\"2025-09-13\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 12:31:26'),
(1443, 1, 1, 'add', 'umrah_transactions', 53, '[]', '{\"umrah_booking_id\":50,\"transaction_to\":\"Internal Account\",\"payment_amount\":200,\"payment_currency\":\"EUR\",\"payment_description\":\"adfasf\",\"payment_date\":\"2025-09-13\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 12:32:05'),
(1444, 1, 1, 'add', 'umrah_transactions', 54, '[]', '{\"umrah_booking_id\":50,\"transaction_to\":\"Internal Account\",\"payment_amount\":100,\"payment_currency\":\"DARHAM\",\"payment_description\":\"adfadsf\",\"payment_date\":\"2025-09-13\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 12:32:37'),
(1445, 1, 1, 'add', 'umrah_transactions', 55, '[]', '{\"umrah_booking_id\":50,\"transaction_to\":\"Internal Account\",\"payment_amount\":1000,\"payment_currency\":\"AFS\",\"payment_description\":\"fadsfadf\",\"payment_date\":\"2025-09-13\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 12:53:23'),
(1446, 1, 1, 'delete', 'umrah_transactions', 55, '{\"transaction_id\":55,\"umrah_id\":50,\"payment_amount\":1000,\"currency\":\"AFS\",\"transaction_to\":\"Internal Account\",\"payment_description\":\"fadsfadf\",\"is_refund\":false,\"supplier_id\":27,\"paid_to\":11}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 12:55:31'),
(1447, 1, 1, 'delete', 'umrah_transactions', 54, '{\"transaction_id\":54,\"umrah_id\":50,\"payment_amount\":100,\"currency\":\"DAR\",\"transaction_to\":\"Internal Account\",\"payment_description\":\"adfadsf\",\"is_refund\":false,\"supplier_id\":27,\"paid_to\":11}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 12:55:36'),
(1448, 1, 1, 'delete', 'umrah_transactions', 53, '{\"transaction_id\":53,\"umrah_id\":50,\"payment_amount\":200,\"currency\":\"EUR\",\"transaction_to\":\"Internal Account\",\"payment_description\":\"adfasf\",\"is_refund\":false,\"supplier_id\":27,\"paid_to\":11}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 12:55:40'),
(1449, 1, 1, 'delete', 'umrah_transactions', 52, '{\"transaction_id\":52,\"umrah_id\":50,\"payment_amount\":1000,\"currency\":\"AFS\",\"transaction_to\":\"Internal Account\",\"payment_description\":\"fdgsdfg\",\"is_refund\":false,\"supplier_id\":27,\"paid_to\":11}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 12:55:44'),
(1450, 1, 1, 'add', 'umrah_transactions', 56, '[]', '{\"umrah_booking_id\":50,\"transaction_to\":\"Internal Account\",\"payment_amount\":100,\"payment_currency\":\"USD\",\"payment_description\":\"ghshg\",\"payment_date\":\"2025-09-13\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 12:56:07'),
(1451, 1, 1, 'add', 'umrah_transactions', 57, '[]', '{\"umrah_booking_id\":50,\"transaction_to\":\"Internal Account\",\"payment_amount\":1000,\"payment_currency\":\"AFS\",\"payment_description\":\"afdasf\",\"payment_date\":\"2025-09-13\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 12:56:44'),
(1452, 1, 1, 'add', 'umrah_transactions', 58, '[]', '{\"umrah_booking_id\":50,\"transaction_to\":\"Internal Account\",\"payment_amount\":100,\"payment_currency\":\"EUR\",\"payment_description\":\"fghdgh\",\"payment_date\":\"2025-09-13\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 12:57:14'),
(1453, 1, 1, 'add', 'umrah_transactions', 59, '[]', '{\"umrah_booking_id\":50,\"transaction_to\":\"Internal Account\",\"payment_amount\":200,\"payment_currency\":\"DARHAM\",\"payment_description\":\"afdasfsaf\",\"payment_date\":\"2025-09-13\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-13 12:57:40'),
(1454, 1, 1, 'delete', 'umrah_transactions', 56, '{\"transaction_id\":56,\"umrah_id\":50,\"payment_amount\":100,\"currency\":\"USD\",\"transaction_to\":\"Internal Account\",\"payment_description\":\"ghshg\",\"is_refund\":false,\"supplier_id\":27,\"paid_to\":11}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 03:43:33'),
(1455, 1, 1, 'delete', 'umrah_transactions', 57, '{\"transaction_id\":57,\"umrah_id\":50,\"payment_amount\":1000,\"currency\":\"AFS\",\"transaction_to\":\"Internal Account\",\"payment_description\":\"afdasf\",\"is_refund\":false,\"supplier_id\":27,\"paid_to\":11}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 03:43:36'),
(1456, 1, 1, 'delete', 'umrah_transactions', 58, '{\"transaction_id\":58,\"umrah_id\":50,\"payment_amount\":100,\"currency\":\"EUR\",\"transaction_to\":\"Internal Account\",\"payment_description\":\"fghdgh\",\"is_refund\":false,\"supplier_id\":27,\"paid_to\":11}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 03:43:39'),
(1457, 1, 1, 'delete', 'umrah_transactions', 59, '{\"transaction_id\":59,\"umrah_id\":50,\"payment_amount\":200,\"currency\":\"DAR\",\"transaction_to\":\"Internal Account\",\"payment_description\":\"afdasfsaf\",\"is_refund\":false,\"supplier_id\":27,\"paid_to\":11}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 03:43:42');
INSERT INTO `activity_log` (`id`, `tenant_id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1458, 1, 1, 'add', 'umrah_transactions', 60, '[]', '{\"umrah_booking_id\":50,\"transaction_to\":\"Internal Account\",\"payment_amount\":20,\"payment_currency\":\"USD\",\"payment_description\":\"dfadsf\",\"payment_date\":\"2025-09-14\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 03:43:58'),
(1459, 1, 1, 'add', 'umrah_transactions', 61, '[]', '{\"umrah_booking_id\":50,\"transaction_to\":\"Internal Account\",\"payment_amount\":1000,\"payment_currency\":\"AFS\",\"payment_description\":\"adfads\",\"payment_date\":\"2025-09-14\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 03:44:38'),
(1460, 1, 1, 'add', 'umrah_transactions', 62, '[]', '{\"umrah_booking_id\":50,\"transaction_to\":\"Internal Account\",\"payment_amount\":20,\"payment_currency\":\"EUR\",\"payment_description\":\"afdsfa\",\"payment_date\":\"2025-09-14\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 03:46:06'),
(1461, 1, 1, 'add', 'umrah_transactions', 63, '[]', '{\"umrah_booking_id\":50,\"transaction_to\":\"Internal Account\",\"payment_amount\":100,\"payment_currency\":\"DARHAM\",\"payment_description\":\"adsfasd\",\"payment_date\":\"2025-09-14\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 03:47:09'),
(1462, 1, 1, 'delete', 'umrah_transactions', 63, '{\"transaction_id\":63,\"umrah_id\":50,\"payment_amount\":100,\"currency\":\"DAR\",\"transaction_to\":\"Internal Account\",\"payment_description\":\"adsfasd\",\"is_refund\":false,\"supplier_id\":27,\"paid_to\":11}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 04:04:21'),
(1463, 1, 1, 'add', 'main_account_transactions', 964, '[]', '{\"refund_id\":19,\"payment_date\":\"2025-09-14 09:10:23 09:10:23\",\"description\":\"Refund payment for Umrah Booking #47 - KamAir (Exchange Rate: 70.00000)\",\"amount\":1200,\"currency\":\"AFS\",\"client_type\":\"regular\",\"main_account_id\":11,\"name\":\"KamAir\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 04:41:00'),
(1464, 1, 1, 'add', 'main_account_transactions', 965, '[]', '{\"refund_id\":19,\"payment_date\":\"2025-09-14 09:14:56 09:14:56\",\"description\":\"Refund payment for Umrah Booking #47 - KamAir (Exchange Rate: 70.00000)\",\"amount\":1200,\"currency\":\"AFS\",\"client_type\":\"regular\",\"main_account_id\":11,\"name\":\"KamAir\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 04:45:09'),
(1465, 1, 1, 'delete', 'main_account_transactions', 964, '{\"transaction_id\":964,\"refund_id\":19,\"amount\":1200,\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-14 09:10:23\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 05:14:58'),
(1466, 1, 1, 'delete', 'main_account_transactions', 965, '{\"transaction_id\":965,\"refund_id\":19,\"amount\":1200,\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-14 09:14:56\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 05:15:02'),
(1467, 1, 1, 'add', 'main_account_transactions', 966, '[]', '{\"refund_id\":19,\"payment_date\":\"2025-09-14 09:45:06 09:45:06\",\"description\":\"Refund payment for Umrah Booking #47 - KamAir (Exchange Rate: 70.00000)\",\"amount\":1000,\"currency\":\"AFS\",\"client_type\":\"regular\",\"main_account_id\":11,\"name\":\"KamAir\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 05:15:20'),
(1468, 1, 1, 'add', 'main_account_transactions', 967, '[]', '{\"refund_id\":19,\"payment_date\":\"2025-09-14 09:45:26 09:45:26\",\"description\":\"adfadsf (Exchange Rate: 0.90000)\",\"amount\":100,\"currency\":\"EUR\",\"client_type\":\"regular\",\"main_account_id\":11,\"name\":\"KamAir\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 05:15:39'),
(1469, 1, 1, 'add', 'main_account_transactions', 968, '[]', '{\"refund_id\":19,\"payment_date\":\"2025-09-14 09:45:45 09:45:45\",\"description\":\"sghsdfhg (Exchange Rate: 3.61000)\",\"amount\":200,\"currency\":\"DARHAM\",\"client_type\":\"regular\",\"main_account_id\":11,\"name\":\"KamAir\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 05:16:01'),
(1470, 1, 1, 'update', '0', 968, '{\"transaction_id\":\"968\",\"booking_id\":\"19\",\"amount\":200,\"description\":\"\",\"created_at\":\"2025-09-14 09:45:45\",\"currency\":\"DARHAM\",\"type\":\"debit\"}', '{\"amount\":200,\"description\":\"sghsdfhg (Exchange Rate: 3.61000)\",\"created_at\":\"2025-09-14 09:45:45\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 05:54:35'),
(1471, 1, 1, 'update', '0', 968, '{\"transaction_id\":\"968\",\"booking_id\":\"19\",\"amount\":200,\"description\":\"\",\"created_at\":\"2025-09-14 09:45:45\",\"currency\":\"DARHAM\",\"type\":\"debit\"}', '{\"amount\":200,\"description\":\"sghsdfhg (Exchange Rate: 3.61000)\",\"created_at\":\"2025-09-14 09:45:45\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 05:54:47'),
(1472, 1, 1, 'update', '0', 968, '{\"transaction_id\":\"968\",\"booking_id\":\"19\",\"amount\":200,\"description\":\"\",\"created_at\":\"2025-09-14 09:45:45\",\"currency\":\"DARHAM\",\"type\":\"debit\"}', '{\"amount\":200,\"description\":\"sghsdfhg (Exchange Rate: 3.71000)\",\"created_at\":\"2025-09-14 09:45:45\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 06:06:52'),
(1473, 1, 1, 'update', '0', 968, '{\"transaction_id\":\"968\",\"booking_id\":\"19\",\"amount\":200,\"description\":\"\",\"created_at\":\"2025-09-14 09:45:45\",\"currency\":\"DARHAM\",\"type\":\"debit\"}', '{\"amount\":200,\"description\":\"sghsdfhg (Exchange Rate: 3.71000)\",\"created_at\":\"2025-09-14 09:45:45\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 06:10:01'),
(1474, 1, 1, 'update', '0', 968, '{\"transaction_id\":\"968\",\"booking_id\":\"19\",\"amount\":200,\"description\":\"\",\"created_at\":\"2025-09-14 09:45:45\",\"currency\":\"DARHAM\",\"type\":\"debit\"}', '{\"amount\":200,\"description\":\"sghsdfhg (Exchange Rate: 3.71000)\",\"created_at\":\"2025-09-14 09:45:45\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 06:15:05'),
(1475, 1, 1, 'update', 'umrah_transactions', 62, '{\"transaction_id\":\"62\",\"umrah_id\":\"50\",\"payment_amount\":\"20.000\",\"payment_date\":\"2025-09-14\",\"transaction_to\":\"Internal Account\"}', '{\"transaction_id\":\"62\",\"umrah_id\":\"50\",\"payment_amount\":20,\"payment_date\":\"2025-09-14 08:16:06\",\"payment_description\":\"afdsfa\",\"transaction_to\":\"Internal Account\"}', '::1', '0', '2025-09-14 06:39:10'),
(1476, 1, 1, 'update', 'umrah_transactions', 62, '{\"transaction_id\":\"62\",\"umrah_id\":\"50\",\"payment_amount\":\"20.000\",\"payment_date\":\"2025-09-14\",\"transaction_to\":\"Internal Account\",\"exchange_rate\":\"0.900\"}', '{\"transaction_id\":\"62\",\"umrah_id\":\"50\",\"payment_amount\":29.14,\"payment_date\":\"2025-09-14 08:16:06\",\"payment_description\":\"afdsfa\",\"transaction_to\":\"Internal Account\",\"exchange_rate\":null}', '::1', '0', '2025-09-14 07:03:30'),
(1477, 1, 1, 'update', 'umrah_transactions', 62, '{\"transaction_id\":\"62\",\"umrah_id\":\"50\",\"payment_amount\":\"29.140\",\"payment_date\":\"2025-09-14\",\"transaction_to\":\"Internal Account\",\"exchange_rate\":null}', '{\"transaction_id\":\"62\",\"umrah_id\":\"50\",\"payment_amount\":29.14,\"payment_date\":\"2025-09-14 08:16:06\",\"payment_description\":\"afdsfa\",\"transaction_to\":\"Internal Account\",\"exchange_rate\":0.9}', '::1', '0', '2025-09-14 07:22:29'),
(1478, 1, 1, 'update', 'main_account_transactions', 937, '{\"transaction_id\":\"937\",\"visa_id\":\"49\",\"amount\":\"60.000\",\"currency\":\"EUR\",\"type\":\"credit\",\"created_at\":\"2025-09-13 12:00:25\"}', '{\"transaction_id\":\"937\",\"visa_id\":\"49\",\"amount\":60,\"description\":\"fadsfdf\",\"created_at\":\"2025-09-13 12:00:25\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 07:23:44'),
(1479, 1, 1, 'update', 'main_account_transactions', 937, '{\"transaction_id\":\"937\",\"visa_id\":\"49\",\"amount\":\"60.000\",\"currency\":\"EUR\",\"type\":\"credit\",\"created_at\":\"2025-09-13 12:00:25\"}', '{\"transaction_id\":\"937\",\"visa_id\":\"49\",\"amount\":70,\"description\":\"fadsfdf\",\"created_at\":\"2025-09-13 12:00:25\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 07:24:02'),
(1480, 1, 1, 'update', '0', 946, '{\"transaction_id\":\"946\",\"visa_id\":\"4\",\"amount\":100,\"description\":\"\",\"created_at\":\"2025-09-13 13:45:40\",\"currency\":\"DARHAM\",\"type\":\"debit\"}', '{\"amount\":100,\"description\":\"adfaf\",\"created_at\":\"2025-09-13 13:45:40\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 07:30:02'),
(1481, 1, 1, 'update', 'hotel_bookings', 34, '{\"booking_id\":34,\"title\":\"\",\"first_name\":\"\",\"last_name\":\"\",\"base_amount\":\"10.000\",\"sold_amount\":\"20.000\",\"currency\":\"USD\",\"supplier_id\":27,\"sold_to\":\"18\"}', '{\"title\":\"Mr\",\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"gender\":\"Male\",\"contact_no\":\"777305730\",\"check_in_date\":\"2025-09-01\",\"check_out_date\":\"2025-09-04\",\"accommodation_details\":\"dfgd\",\"base_amount\":10,\"sold_amount\":200,\"profit\":190,\"currency\":\"USD\",\"supplier_id\":\"27\",\"sold_to\":\"18\",\"paid_to\":\"11\",\"remarks\":\"fhg\",\"exchange_rate\":\"70.00000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 07:39:49'),
(1482, 1, 1, 'update', '0', 925, '{\"transaction_id\":\"925\",\"booking_id\":\"34\",\"amount\":2,\"type\":\"credit\",\"created_at\":\"2025-09-11 17:10:18\",\"description\":\"\",\"currency\":\"USD\"}', '{\"amount\":2,\"type\":\"credit\",\"description\":\"adfadsf\",\"created_at\":\"2025-09-11 17:10:18\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 07:40:10'),
(1483, 1, 1, 'update', '0', 925, '{\"transaction_id\":\"925\",\"booking_id\":\"34\",\"amount\":2,\"type\":\"credit\",\"created_at\":\"2025-09-11 17:10:18\",\"description\":\"\",\"currency\":\"USD\"}', '{\"amount\":20,\"type\":\"credit\",\"description\":\"adfadsf\",\"created_at\":\"2025-09-11 17:10:18\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 07:40:20'),
(1484, 1, 1, 'update', '0', 933, '{\"transaction_id\":\"933\",\"booking_id\":\"7\",\"amount\":12,\"description\":\"\",\"created_at\":\"2025-09-13 10:28:52\",\"currency\":\"DARHAM\",\"type\":\"debit\"}', '{\"amount\":12,\"description\":\"adsfas\",\"created_at\":\"2025-09-13 10:28:52\",\"exchange_rate\":3.71}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 08:07:35'),
(1485, 1, 1, 'update', '0', 923, '{\"transaction_id\":\"923\",\"ticket_id\":\"11\",\"amount\":200,\"type\":\"credit\",\"currency\":\"DARHAM\",\"created_at\":\"2025-09-11 15:59:30\",\"description\":\"\"}', '{\"amount\":200,\"description\":\"adfadf (Exchange Rate: 3.61)\",\"created_at\":\"2025-09-11 15:59:30\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 08:08:39'),
(1486, 1, 1, 'update', '0', 923, '{\"transaction_id\":\"923\",\"ticket_id\":\"11\",\"amount\":200,\"type\":\"credit\",\"currency\":\"DARHAM\",\"created_at\":\"2025-09-11 15:59:30\",\"description\":\"\"}', '{\"amount\":200,\"description\":\"adfadf (Exchange Rate: 3.61)\",\"created_at\":\"2025-09-11 15:59:30\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 08:22:45'),
(1487, 1, 1, 'update', '0', 923, '{\"transaction_id\":\"923\",\"ticket_id\":\"11\",\"amount\":200,\"type\":\"credit\",\"currency\":\"DARHAM\",\"created_at\":\"2025-09-11 15:59:30\",\"description\":\"\"}', '{\"amount\":200,\"description\":\"adfadf (Exchange Rate: 3.61)\",\"created_at\":\"2025-09-11 15:59:30\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 08:25:02'),
(1488, 1, 1, 'update', '0', 923, '{\"transaction_id\":\"923\",\"ticket_id\":\"11\",\"amount\":200,\"type\":\"credit\",\"currency\":\"DARHAM\",\"created_at\":\"2025-09-11 15:59:30\",\"description\":\"\"}', '{\"amount\":200,\"description\":\"adfadf (Exchange Rate: 3.61)\",\"created_at\":\"2025-09-11 15:59:30\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 08:29:13'),
(1489, 1, 1, 'update', '0', 923, '{\"transaction_id\":\"923\",\"ticket_id\":\"11\",\"amount\":200,\"type\":\"credit\",\"currency\":\"DARHAM\",\"created_at\":\"2025-09-11 15:59:30\",\"description\":\"\"}', '{\"amount\":200,\"description\":\"adfadf (Exchange Rate: 3.61)\",\"created_at\":\"2025-09-11 15:59:30\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 08:31:04'),
(1490, 1, 1, 'update', '0', 921, '{\"transaction_id\":\"921\",\"ticket_id\":\"11\",\"amount\":1000,\"type\":\"credit\",\"currency\":\"AFS\",\"created_at\":\"2025-09-11 15:47:13\",\"description\":\"\"}', '{\"amount\":1000,\"description\":\"adfadsf (Exchange Rate: 70)\",\"created_at\":\"2025-09-11 15:47:13\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 08:31:20'),
(1491, 1, 1, 'update', '0', 921, '{\"transaction_id\":\"921\",\"ticket_id\":\"11\",\"amount\":1000,\"type\":\"credit\",\"currency\":\"AFS\",\"created_at\":\"2025-09-11 15:47:13\",\"description\":\"\"}', '{\"amount\":1000,\"description\":\"adfadsf (Exchange Rate: 70)\",\"created_at\":\"2025-09-11 15:47:13\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 08:31:40'),
(1492, 1, 1, 'update', '0', 923, '{\"transaction_id\":\"923\",\"ticket_id\":\"11\",\"amount\":200,\"type\":\"credit\",\"currency\":\"DARHAM\",\"created_at\":\"2025-09-11 15:59:30\",\"description\":\"\"}', '{\"amount\":200,\"description\":\"adfadf (Exchange Rate: 3.71)\",\"created_at\":\"2025-09-11 15:59:30\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 08:41:08'),
(1493, 1, 1, 'update', 'main_account_transactions', 910, '{\"transaction_id\":\"910\",\"booking_id\":\"43\",\"amount\":1000,\"description\":\"\",\"created_at\":\"2025-09-11 12:27:17\"}', '{\"amount\":1000,\"description\":\"asfdsaf (Exchange Rate: 70)\",\"created_at\":\"2025-09-11 12:27:17\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 09:31:10'),
(1494, 1, 1, 'update', 'main_account_transactions', 910, '{\"transaction_id\":\"910\",\"booking_id\":\"43\",\"amount\":1000,\"description\":\"\",\"created_at\":\"2025-09-11 12:27:17\"}', '{\"amount\":1000,\"description\":\"asfdsaf (Exchange Rate: 70)\",\"created_at\":\"2025-09-11 12:27:17\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 09:31:24'),
(1495, 1, 1, 'update', 'main_account_transactions', 910, '{\"transaction_id\":\"910\",\"booking_id\":\"43\",\"amount\":1000,\"description\":\"\",\"created_at\":\"2025-09-11 12:27:17\"}', '{\"amount\":1000,\"description\":\"asfdsaf (Exchange Rate: 70)\",\"created_at\":\"2025-09-11 12:27:17\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 09:34:04'),
(1496, 1, 1, 'update', 'main_account_transactions', 910, '{\"transaction_id\":\"910\",\"booking_id\":\"43\",\"amount\":1000,\"description\":\"\",\"created_at\":\"2025-09-11 12:27:17\"}', '{\"amount\":1000,\"description\":\"asfdsaf (Exchange Rate: 70)\",\"created_at\":\"2025-09-11 12:27:17\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 09:41:54'),
(1497, 1, 1, 'update', 'main_account_transactions', 910, '{\"transaction_id\":\"910\",\"booking_id\":\"43\",\"amount\":1000,\"description\":\"\",\"created_at\":\"2025-09-11 12:27:17\"}', '{\"amount\":1000,\"description\":\"asfdsaf (Exchange Rate: 70)\",\"created_at\":\"2025-09-11 12:27:17\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 09:43:42'),
(1498, 1, 1, 'delete', 'main_account_transactions', 910, '{\"transaction_id\":910,\"ticket_id\":43,\"amount\":1000,\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-11 12:27:17\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 09:43:51'),
(1499, 1, 1, 'update', '0', 923, '{\"transaction_id\":\"923\",\"ticket_id\":\"11\",\"amount\":200,\"type\":\"credit\",\"currency\":\"DARHAM\",\"created_at\":\"2025-09-11 15:59:30\",\"description\":\"\"}', '{\"amount\":200,\"description\":\"adfadf (Exchange Rate: 3.7)\",\"created_at\":\"2025-09-11 15:59:30\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 09:51:45'),
(1500, 1, 1, 'update', '0', 923, '{\"transaction_id\":\"923\",\"ticket_id\":\"11\",\"amount\":200,\"type\":\"credit\",\"currency\":\"DARHAM\",\"created_at\":\"2025-09-11 15:59:30\",\"description\":\"\"}', '{\"amount\":200,\"description\":\"adfadf\",\"created_at\":\"2025-09-11 15:59:30\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 09:53:40'),
(1501, 1, 1, 'update', '0', 923, '{\"transaction_id\":\"923\",\"ticket_id\":\"11\",\"amount\":200,\"type\":\"credit\",\"currency\":\"DARHAM\",\"created_at\":\"2025-09-11 15:59:30\",\"description\":\"\"}', '{\"amount\":200,\"description\":\"adfadf\",\"created_at\":\"2025-09-11 15:59:30\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 09:59:10'),
(1502, 1, 1, 'delete', 'main_account_transactions', 852, '{\"transaction_id\":852,\"ticket_id\":11,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-02 13:29:00\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 10:01:20'),
(1503, 1, 1, 'create', 'main_account_transactions', 969, NULL, '{\"weight_id\":8,\"amount\":1000,\"currency\":\"AFS\",\"exchange_rate\":70,\"transaction_date\":\"2025-09-14 14:40\",\"remarks\":\"sgfdgsa\",\"main_account_id\":11,\"balance\":13258}', '::1', '0', '2025-09-14 10:11:13'),
(1504, 1, 1, 'delete', 'main_account_transactions', 883, '{\"transaction_id\":883,\"weight_id\":8,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-10 14:29:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 10:15:49'),
(1505, 1, 1, 'delete', 'main_account_transactions', 913, '{\"transaction_id\":913,\"weight_id\":8,\"amount\":500,\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-11 13:34:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 10:15:56'),
(1506, 1, 1, 'delete', 'main_account_transactions', 914, '{\"transaction_id\":914,\"weight_id\":8,\"amount\":1,\"currency\":\"EUR\",\"main_account_id\":11,\"created_at\":\"2025-09-11 13:39:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 10:16:02'),
(1507, 1, 1, 'update', '0', 909, '{\"transaction_id\":\"909\",\"ticket_id\":\"94\",\"amount\":100,\"description\":\"\",\"created_at\":\"2025-09-11 12:13:33\",\"currency\":\"DARHAM\",\"type\":\"debit\",\"exchange_rate\":\"3.61000\"}', '{\"amount\":100,\"description\":\"adfsadsf (Exchange Rate: 3.61)\",\"created_at\":\"2025-09-11 12:13:33\",\"exchange_rate\":3.65}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 10:25:12'),
(1508, 1, 1, 'delete', 'main_account_transactions', 881, '{\"transaction_id\":881,\"ticket_id\":94,\"amount\":20,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-10 14:17:55\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 10:25:39'),
(1509, 1, 1, 'delete', 'main_account_transactions', 877, '{\"transaction_id\":877,\"ticket_id\":321,\"amount\":\"20.000\",\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-07 13:15:48\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 10:49:02'),
(1510, 1, 1, 'update', '0', 906, '{\"transaction_id\":\"906\",\"ticket_id\":\"321\",\"amount\":100,\"description\":null,\"created_at\":\"2025-09-11 11:27:23\",\"type\":\"credit\",\"currency\":\"DARHAM\",\"exchange_rate\":null}', '{\"amount\":100,\"description\":\"asdfasf (Exchange Rate: 3.61)\",\"created_at\":\"2025-09-11 11:27:23\",\"exchange_rate\":3.65}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 10:53:31'),
(1511, 1, 1, 'update', '0', 906, '{\"transaction_id\":\"906\",\"ticket_id\":\"321\",\"amount\":100,\"description\":null,\"created_at\":\"2025-09-11 11:27:23\",\"type\":\"credit\",\"currency\":\"DARHAM\",\"exchange_rate\":null}', '{\"amount\":100,\"description\":\"asdfasf (Exchange Rate: 3.61)\",\"created_at\":\"2025-09-11 11:27:23\",\"exchange_rate\":3.65}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 10:53:38'),
(1512, 1, 1, 'update', '0', 906, '{\"transaction_id\":\"906\",\"ticket_id\":\"321\",\"amount\":100,\"description\":\"asdfasf (Exchange Rate: 3.61)\",\"created_at\":\"2025-09-11 11:27:23\",\"type\":\"credit\",\"currency\":\"DARHAM\",\"exchange_rate\":null}', '{\"amount\":100,\"description\":\"asdfasf (Exchange Rate: 3.61)\",\"created_at\":\"2025-09-11 11:27:23\",\"exchange_rate\":3.65}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 10:54:18'),
(1513, 1, 1, 'delete', 'main_account_transactions', 904, '{\"transaction_id\":904,\"ticket_id\":321,\"amount\":\"1000.000\",\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-11 11:26:12\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 10:54:31'),
(1514, 1, 1, 'delete', 'main_account_transactions', 905, '{\"transaction_id\":905,\"ticket_id\":321,\"amount\":\"50.000\",\"currency\":\"EUR\",\"main_account_id\":11,\"created_at\":\"2025-09-11 11:26:40\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 10:55:18'),
(1515, 1, 1, 'add', 'main_account_transactions', 970, '[]', '{\"booking_id\":321,\"payment_date\":\"2025-09-14 15:29:19\",\"description\":\"sdhgsgfhg\",\"amount\":1000,\"currency\":\"AFS\",\"exchange_rate\":70,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 10:59:30'),
(1516, 1, 1, 'delete', 'main_account_transactions', 970, '{\"transaction_id\":970,\"ticket_id\":321,\"amount\":\"1000.000\",\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-14 15:29:19\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 11:00:46'),
(1517, 1, 1, 'add', 'main_account_transactions', 971, '[]', '{\"booking_id\":321,\"payment_date\":\"2025-09-14 15:33:04\",\"description\":\"afgsafdg\",\"amount\":1000,\"currency\":\"AFS\",\"exchange_rate\":70,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 11:03:23'),
(1518, 1, 1, 'delete', 'main_account_transactions', 971, '{\"transaction_id\":971,\"ticket_id\":321,\"amount\":\"1000.000\",\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-14 15:33:04\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 11:03:42'),
(1519, 1, 1, 'update', '0', 906, '{\"transaction_id\":\"906\",\"ticket_id\":\"321\",\"amount\":100,\"description\":\"asdfasf (Exchange Rate: 3.61)\",\"created_at\":\"2025-09-11 11:27:23\",\"type\":\"credit\",\"currency\":\"DARHAM\",\"exchange_rate\":null}', '{\"amount\":100,\"description\":\"asdfasf\",\"created_at\":\"2025-09-11 11:27:23\",\"exchange_rate\":3.66}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 11:04:00'),
(1520, 1, 1, 'update', '0', 11, '{\"ticket_id\":11,\"supplier\":\"27\",\"sold_to\":18,\"price\":\"100.000\",\"sold\":\"200.000\",\"currency\":\"USD\"}', '{\"supplier\":27,\"sold_to\":19,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"guli\",\"pnr\":\"188JZ0\",\"phone\":\"0777305730\",\"origin\":\"MZR\",\"destination\":\"FRA\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"A3\",\"issue_date\":\"2025-09-02\",\"departure_date\":\"2025-09-04\",\"return_date\":\"\",\"price\":100,\"sold\":200,\"profit\":100,\"currency\":\"USD\",\"description\":\"test\",\"paid_to\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 11:09:13'),
(1521, 1, 1, 'add', 'main_account_transactions', 973, '[]', '{\"booking_id\":11,\"payment_date\":\"2025-09-14 15:39:13\",\"description\":\"fgsfg\",\"amount\":20,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 11:09:30'),
(1522, 1, 1, 'add', 'main_account_transactions', 974, '[]', '{\"main_account_id\":11,\"amount\":100,\"currency\":\"AFS\",\"description\":\"cxvzxcv | Exchange rate: 1 AFS = 70 USD\",\"payment_id\":45,\"balance\":11858,\"payment_datetime\":\"2025-09-14 15:45:36\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 11:16:59'),
(1523, 1, 1, 'add', 'main_account_transactions', 975, '[]', '{\"main_account_id\":11,\"amount\":100,\"currency\":\"AFS\",\"description\":\"testa\",\"payment_id\":45,\"balance\":11958,\"exchange_rate\":70,\"payment_datetime\":\"2025-09-14 15:54:07\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 11:24:19'),
(1524, 1, 1, 'add', 'main_account_transactions', 976, '[]', '{\"main_account_id\":11,\"amount\":2,\"currency\":\"EUR\",\"description\":\"\",\"payment_id\":45,\"balance\":141.14,\"exchange_rate\":0.9,\"payment_datetime\":\"2025-09-14 16:03:49\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 11:34:24'),
(1525, 1, 1, 'add', 'main_account_transactions', 977, '[]', '{\"main_account_id\":11,\"amount\":5,\"currency\":\"DARHAM\",\"description\":\"sdgfdg\",\"payment_id\":45,\"balance\":408,\"exchange_rate\":3.61,\"payment_datetime\":\"2025-09-14 16:03:49\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 11:34:46'),
(1526, 1, 1, 'add', 'main_account_transactions', 978, '[]', '{\"main_account_id\":11,\"amount\":2,\"currency\":\"USD\",\"description\":\"dghdfg\",\"payment_id\":45,\"balance\":8417.14,\"exchange_rate\":0,\"payment_datetime\":\"2025-09-14 16:07:32\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 11:37:44'),
(1527, 1, 1, 'add', 'main_account_transactions', 979, '[]', '{\"main_account_id\":11,\"amount\":8.46,\"currency\":\"USD\",\"description\":\"fdsgf\",\"payment_id\":45,\"balance\":8425.599999999999,\"exchange_rate\":0,\"payment_datetime\":\"2025-09-14 16:26:59\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 11:57:07'),
(1528, 1, 1, 'add', 'main_account_transactions', 980, '[]', '{\"main_account_id\":11,\"amount\":3.08,\"currency\":\"USD\",\"description\":\"sdfgsdf\",\"payment_id\":45,\"balance\":8428.68,\"exchange_rate\":0,\"payment_datetime\":\"2025-09-14 16:27:26\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 11:57:35'),
(1529, 1, 1, 'delete', 'main_account_transactions', 974, '{\"main_account_id\":11,\"transaction_id\":974,\"payment_id\":45,\"amount\":\"100.000\",\"currency\":\"AFS\",\"type\":\"credit\",\"created_at\":\"2025-09-14 15:45:36\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-14 12:00:32'),
(1530, 1, 1, 'update', 'visa_applications', 49, '{\"id\":49,\"supplier\":27,\"sold_to\":19,\"base\":\"100.000\",\"sold\":\"200.000\",\"currency\":\"USD\"}', '{\"id\":49,\"supplier\":27,\"sold_to\":19,\"title\":\"Mr\",\"gender\":\"Male\",\"applicant_name\":\"guli\",\"passport_number\":\"P8798765\",\"country\":\"Pakistan\",\"visa_type\":\"Tourist\",\"receive_date\":\"2025-09-01\",\"applied_date\":\"2025-09-03\",\"issued_date\":\"2025-09-03\",\"base\":100,\"sold\":200,\"profit\":100,\"currency\":\"USD\",\"status\":\"\",\"remarks\":\"test\",\"phone\":\"0780310431\",\"exchange_rate\":70}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-15 04:48:42'),
(1531, 1, 1, 'add', 'ticket_bookings', 324, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"SZQXJU\",\"origin\":\"MZR\",\"destination\":\"FRA\",\"airline\":\"NF\",\"departure_date\":\"2025-09-30\",\"total_base\":5000,\"total_sold\":5500,\"total_discount\":0,\"total_profit\":500,\"currency\":\"AFS\",\"supplier_id\":29,\"supplier_name\":\"NAVEED RASHIQ\",\"client_id\":19,\"client_name\":\"NAVEED RASHIQ\",\"trip_type\":\"one_way\",\"exchange_rate\":71}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-15 06:01:18'),
(1532, 1, 1, 'add', 'additional_payments', 47, NULL, '{\"id\":47,\"payment_type\":\"Vacine\",\"description\":\"adsf\",\"base_amount\":600,\"profit\":50,\"sold_amount\":650,\"currency\":\"AFS\",\"main_account_id\":11,\"supplier_id\":null,\"is_from_supplier\":1,\"client_id\":null,\"is_for_client\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-15 06:01:52'),
(1533, 1, 1, 'add', 'hotel_bookings', 38, '[]', '{\"title\":\"Mr\",\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"gender\":\"Male\",\"check_in_date\":\"2025-09-15\",\"check_out_date\":\"2025-10-02\",\"accommodation_details\":\"hdg\",\"supplier_id\":29,\"sold_to\":\"19\",\"base_amount\":1000,\"sold_amount\":1500,\"profit\":500,\"currency\":\"AFS\",\"paid_to\":\"11\",\"exchange_rate\":71}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-15 06:10:28'),
(1534, 1, 1, 'add', 'visa_applications', 51, '[]', '{\"supplier\":29,\"sold_to\":\"19\",\"paid_to\":\"11\",\"applicant_name\":\"HAMID ACHAKZAI \",\"passport_number\":\"P8798765\",\"visa_type\":\"Business\",\"base\":1000,\"sold\":1500,\"profit\":500,\"currency\":\"AFS\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-15 06:11:17'),
(1535, 1, 1, 'add', 'refunded_tickets', 95, '{\"ticket_id\":324,\"passenger_name\":\"FAZALHAQ PARDES\",\"pnr\":\"SZQXJU\",\"base\":5000,\"sold\":5500,\"supplier_penalty\":200,\"service_penalty\":500,\"currency\":\"AFS\",\"status\":\"Refunded\",\"description\":\"CASH PAID BY MR MATIULLAH\",\"calculation_method\":\"base\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-15 06:13:51'),
(1536, 1, 1, 'add', 'date_change_tickets', 45, '{\"ticket_id\":\"324\",\"passenger_name\":\"FAZALHAQ PARDES\",\"pnr\":\"SZQXJU\",\"base\":5000,\"sold\":5500,\"supplier_penalty\":200,\"service_penalty\":500,\"currency\":\"AFS\",\"exchange_rate\":\"71\",\"status\":\"Date Changed\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-15 06:15:18'),
(1537, 1, 1, 'create', 'ticket_weights', 11, NULL, '{\"ticket_id\":324,\"weight\":20,\"base_price\":200,\"sold_price\":700,\"profit\":500,\"remarks\":\"adfdsf\",\"supplier_name\":\"NAVEED RASHIQ\",\"client_name\":\"NAVEED RASHIQ\",\"currency\":\"AFS\"}', '::1', '0', '2025-09-15 06:17:03'),
(1538, 1, 1, 'add', 'ticket_reservations', 12, '{}', '{\"passenger_name\":\"HAMID ACHAKZAI \",\"pnr\":\"SZQXJU\",\"origin\":\"MZR\",\"destination\":\"FRA\",\"airline\":\"EI\",\"departure_date\":\"2025-10-02\",\"base\":1000,\"sold\":1500,\"profit\":500,\"currency\":\"AFS\",\"supplier\":29,\"supplier_name\":\"NAVEED RASHIQ\",\"sold_to\":19,\"client_name\":\"NAVEED RASHIQ\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-15 06:18:52'),
(1539, 1, 1, 'update_umrah_member', 'umrah_bookings', 50, '{\"supplier\":27,\"sold_to\":18,\"paid_to\":11,\"entry_date\":\"2025-09-02\",\"name\":\"NAVEED RASHIQ\",\"dob\":\"2025-09-02\",\"passport_number\":\"P03241263\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":\"0000-00-00\",\"return_date\":\"0000-00-00\",\"duration\":\"5 Days\",\"room_type\":\"1 Bed\",\"price\":\"1000.000\",\"sold_price\":\"1200.000\",\"profit\":\"200.000\",\"received_bank_payment\":\"0.000\",\"bank_receipt_number\":\"\",\"paid\":\"66.663\",\"due\":\"1133.337\",\"discount\":\"0.000\"}', '{\"booking_id\":50,\"family_id\":8,\"supplier\":27,\"sold_to\":19,\"paid_to\":11,\"entry_date\":\"2025-09-02\",\"name\":\"NAVEED RASHIQ\",\"dob\":\"2025-09-02\",\"passport_number\":\"P03241263\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":null,\"return_date\":null,\"duration\":\"5 Days\",\"room_type\":\"1 Bed\",\"currency\":\"USD\",\"price\":1000,\"sold_price\":1200,\"profit\":200,\"received_bank_payment\":0,\"bank_receipt_number\":\"\",\"paid\":66.663,\"due\":1133.337,\"gender\":\"Male\",\"passport_expiry\":\"2026-04-08\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-15 11:20:13'),
(1540, 1, 1, 'add', 'umrah_bookings', 52, '[]', '{\"family_id\":\"10\",\"supplier\":\"29\",\"sold_to\":\"19\",\"paid_to\":\"11\",\"name\":\"Idrees\",\"passport_number\":\"P07592390\",\"flight_date\":\"\",\"return_date\":\"\",\"room_type\":\"1 Bed\",\"price\":\"1000\",\"sold_price\":\"1200\",\"profit\":\"200.00\",\"exchange_rate\":\"\",\"remarks\":\"Base amount of 1000 AFS deducted for umrah.\",\"discount\":\"0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-16 10:04:26'),
(1541, 1, 1, 'update_umrah_member', 'umrah_bookings', 52, '{\"supplier\":29,\"sold_to\":19,\"paid_to\":11,\"entry_date\":\"2025-09-16\",\"name\":\"Idrees\",\"dob\":\"2025-09-16\",\"passport_number\":\"P07592390\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":\"0000-00-00\",\"return_date\":\"0000-00-00\",\"duration\":\"21 Days\",\"room_type\":\"1 Bed\",\"price\":\"1000.000\",\"sold_price\":\"1200.000\",\"profit\":\"200.000\",\"received_bank_payment\":\"0.000\",\"bank_receipt_number\":\"\",\"paid\":\"0.000\",\"due\":\"1200.000\",\"discount\":\"0.000\"}', '{\"booking_id\":52,\"family_id\":10,\"supplier\":29,\"sold_to\":19,\"paid_to\":11,\"entry_date\":\"2025-09-16\",\"name\":\"Idrees\",\"dob\":\"2025-09-16\",\"passport_number\":\"P07592390\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":\"2025-09-02\",\"return_date\":\"2025-09-17\",\"duration\":\"15 Days\",\"room_type\":\"1 Bed\",\"currency\":\"AFS\",\"price\":1000,\"sold_price\":1200,\"profit\":200,\"received_bank_payment\":0,\"bank_receipt_number\":\"\",\"paid\":0,\"due\":1200,\"gender\":\"Male\",\"passport_expiry\":\"2026-03-31\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-17 11:48:51'),
(1542, 1, 1, 'add', 'umrah_bookings', 53, '[]', '{\"family_id\":\"10\",\"supplier\":\"27\",\"sold_to\":\"19\",\"paid_to\":\"11\",\"name\":\"SABAOON CAR REPAIR\",\"passport_number\":\"P07592390\",\"flight_date\":\"\",\"return_date\":\"\",\"room_type\":\"1 Bed\",\"price\":\"1000\",\"sold_price\":\"1200\",\"profit\":\"200.00\",\"exchange_rate\":\"\",\"remarks\":\"Base amount of 1000 USD deducted for umrah.\",\"discount\":\"0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-17 12:54:41'),
(1543, 1, 1, 'update_umrah_member', 'umrah_bookings', 52, '{\"supplier\":29,\"sold_to\":19,\"paid_to\":11,\"entry_date\":\"2025-09-16\",\"name\":\"Idrees\",\"dob\":\"2025-09-16\",\"passport_number\":\"P07592390\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":\"2025-09-02\",\"return_date\":\"2025-09-20\",\"duration\":\"18 Days\",\"room_type\":\"1 Bed\",\"price\":\"1050.000\",\"sold_price\":\"1450.000\",\"profit\":\"400.000\",\"received_bank_payment\":\"0.000\",\"bank_receipt_number\":\"\",\"paid\":\"0.000\",\"due\":\"1200.000\",\"discount\":\"0.000\"}', '{\"booking_id\":52,\"family_id\":10,\"supplier\":27,\"sold_to\":19,\"paid_to\":11,\"entry_date\":\"2025-09-16\",\"name\":\"Idrees\",\"dob\":\"2025-09-16\",\"passport_number\":\"P07592390\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":\"2025-09-02\",\"return_date\":\"2025-09-20\",\"duration\":\"18 Days\",\"room_type\":\"1 Bed\",\"currency\":\"USD\",\"price\":1050,\"sold_price\":1450,\"profit\":400,\"received_bank_payment\":0,\"bank_receipt_number\":\"\",\"paid\":0,\"due\":1450,\"gender\":\"Male\",\"passport_expiry\":\"2026-03-31\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-17 12:56:40'),
(1544, 1, 1, 'add', 'umrah_transactions', 64, '[]', '{\"umrah_booking_id\":52,\"transaction_to\":\"Internal Account\",\"payment_amount\":100,\"payment_currency\":\"USD\",\"payment_description\":\"sxfgsgf\",\"payment_date\":\"2025-09-17\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-17 12:57:05'),
(1545, 1, 1, 'add', 'umrah_transactions', 65, '[]', '{\"umrah_booking_id\":52,\"transaction_to\":\"Internal Account\",\"payment_amount\":100,\"payment_currency\":\"USD\",\"payment_description\":\"gdfgdf\",\"payment_date\":\"2025-09-18\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-18 05:38:06'),
(1546, 1, 1, 'add', 'umrah_transactions', 66, '[]', '{\"umrah_booking_id\":52,\"transaction_to\":\"Internal Account\",\"payment_amount\":50,\"payment_currency\":\"USD\",\"payment_description\":\"adfads\",\"payment_date\":\"2025-09-18\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-18 06:46:27'),
(1547, 1, 1, 'update_umrah_member', 'umrah_bookings', 53, '{\"supplier\":27,\"sold_to\":19,\"paid_to\":11,\"entry_date\":\"2025-09-17\",\"name\":\"SABAOON CAR REPAIR\",\"dob\":\"2025-09-17\",\"passport_number\":\"P07592390\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":\"0000-00-00\",\"return_date\":\"0000-00-00\",\"duration\":\"15 Days\",\"room_type\":\"1 Bed\",\"price\":\"1000.000\",\"sold_price\":\"1200.000\",\"profit\":\"200.000\",\"received_bank_payment\":\"0.000\",\"bank_receipt_number\":\"\",\"paid\":\"0.000\",\"due\":\"1200.000\",\"discount\":\"0.000\"}', '{\"booking_id\":53,\"family_id\":10,\"supplier\":27,\"sold_to\":19,\"paid_to\":11,\"entry_date\":\"2025-09-17\",\"name\":\"SABAOON CAR REPAIR\",\"dob\":\"2025-09-17\",\"passport_number\":\"P07592390\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":\"2025-09-01\",\"return_date\":\"2025-09-15\",\"duration\":\"15 Days\",\"room_type\":\"1 Bed\",\"currency\":\"USD\",\"price\":1000,\"sold_price\":1200,\"profit\":200,\"received_bank_payment\":0,\"bank_receipt_number\":\"\",\"paid\":0,\"due\":1200,\"gender\":\"Male\",\"passport_expiry\":\"2026-03-31\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-18 06:47:03'),
(1548, 1, 1, 'add', 'main_account_transactions', 984, '[]', '{\"booking_id\":324,\"payment_date\":\"2025-09-18 15:38:58\",\"description\":\"sfgdf\",\"amount\":50,\"currency\":\"USD\",\"exchange_rate\":70,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-18 11:09:24'),
(1549, 1, 1, 'add', 'main_account_transactions', 985, '[]', '{\"booking_id\":324,\"payment_date\":\"2025-09-20 11:50:42\",\"description\":\"adfads\",\"amount\":5,\"currency\":\"EUR\",\"exchange_rate\":87,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 07:21:39'),
(1550, 1, 1, 'add', 'main_account_transactions', 986, '[]', '{\"booking_id\":324,\"payment_date\":\"2025-09-20 11:51:39\",\"description\":\"dfgvdfsg\",\"amount\":10,\"currency\":\"DARHAM\",\"exchange_rate\":18.5,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 07:23:21'),
(1551, 1, 1, 'add', 'main_account_transactions', 987, '[]', '{\"booking_id\":324,\"payment_date\":\"2025-09-20 11:53:21\",\"description\":\"xgdsfg\",\"amount\":100,\"currency\":\"AFS\",\"exchange_rate\":null,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 07:23:33'),
(1552, 1, 1, 'add', 'main_account_transactions', 988, '[]', '{\"booking_id\":321,\"payment_date\":\"2025-09-20 12:19:31\",\"description\":\"dfgsdfg\",\"amount\":1000,\"currency\":\"AFS\",\"exchange_rate\":71,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 07:51:01'),
(1553, 1, 1, 'add', 'main_account_transactions', 989, '[]', '{\"booking_id\":321,\"payment_date\":\"2025-09-20 12:21:14\",\"description\":\"afdsfdsa\",\"amount\":50,\"currency\":\"EUR\",\"exchange_rate\":0.9,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 07:51:52'),
(1554, 1, 1, 'add', 'main_account_transactions', 990, '[]', '{\"booking_id\":321,\"payment_date\":\"2025-09-20 12:21:52\",\"description\":\"xfghfgh\",\"amount\":50,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 07:52:11'),
(1555, 1, 1, 'add', 'main_account_transactions', 991, '[]', '{\"booking_id\":95,\"payment_date\":\"2025-09-20 13:23:47\",\"description\":\"fadfads\",\"amount\":100,\"currency\":\"AFS\",\"exchange_rate\":0,\"client_type\":\"agency\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 08:53:59'),
(1556, 1, 1, 'add', 'main_account_transactions', 992, '[]', '{\"booking_id\":95,\"payment_date\":\"2025-09-20 13:23:59\",\"description\":\"adfadsf\",\"amount\":5,\"currency\":\"USD\",\"exchange_rate\":70,\"client_type\":\"agency\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 08:54:19'),
(1557, 1, 1, 'add', 'main_account_transactions', 993, '[]', '{\"booking_id\":95,\"payment_date\":\"2025-09-20 13:24:19\",\"description\":\"fdasfads\",\"amount\":5,\"currency\":\"EUR\",\"exchange_rate\":87,\"client_type\":\"agency\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 08:54:39'),
(1558, 1, 1, 'add', 'main_account_transactions', 994, '[]', '{\"booking_id\":95,\"payment_date\":\"2025-09-20 13:24:39\",\"description\":\"dsadfds\",\"amount\":100,\"currency\":\"DARHAM\",\"exchange_rate\":18.5,\"client_type\":\"agency\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 08:55:14'),
(1559, 1, 1, 'add', 'main_account_transactions', 995, '[]', '{\"booking_id\":95,\"payment_date\":\"2025-09-20 13:26:28\",\"description\":\"afdadsf\",\"amount\":22.36,\"currency\":\"USD\",\"exchange_rate\":70,\"client_type\":\"agency\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 08:59:12'),
(1560, 1, 1, 'add', 'main_account_transactions', 996, '[]', '{\"booking_id\":94,\"payment_date\":\"2025-09-20 13:29:17\",\"description\":\"adfadsf\",\"amount\":4626.64,\"currency\":\"AFS\",\"exchange_rate\":70,\"client_type\":\"agency\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 08:59:41'),
(1561, 1, 1, 'delete', 'main_account_transactions', 991, '{\"transaction_id\":991,\"ticket_id\":95,\"amount\":100,\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-20 13:23:47\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 09:06:09'),
(1562, 1, 1, 'add', 'main_account_transactions', 997, '[]', '{\"booking_id\":43,\"payment_date\":\"2025-09-20 13:37:07\",\"description\":\"adfasf (Exchange Rate: 70)\",\"amount\":100,\"currency\":\"AFS\",\"exchange_rate\":70,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 09:07:33'),
(1563, 1, 1, 'add', 'main_account_transactions', 998, '[]', '{\"booking_id\":43,\"payment_date\":\"2025-09-20 13:37:33\",\"description\":\"adfadsf (Exchange Rate: 3.61)\",\"amount\":20,\"currency\":\"DARHAM\",\"exchange_rate\":3.61,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 09:07:56');
INSERT INTO `activity_log` (`id`, `tenant_id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1564, 1, 1, 'add', 'main_account_transactions', 999, '[]', '{\"booking_id\":43,\"payment_date\":\"2025-09-20 13:37:56\",\"description\":\"fasdfas\",\"amount\":5,\"currency\":\"USD\",\"exchange_rate\":0,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 09:08:11'),
(1565, 1, 1, 'add', 'main_account_transactions', 1000, '[]', '{\"booking_id\":45,\"payment_date\":\"2025-09-20 13:39:45\",\"description\":\"adfadsf (Exchange Rate: 70)\",\"amount\":2,\"currency\":\"USD\",\"exchange_rate\":70,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 09:10:00'),
(1566, 1, 1, 'add', 'main_account_transactions', 1001, '[]', '{\"booking_id\":45,\"payment_date\":\"2025-09-20 13:40:00\",\"description\":\"afdsfad (Exchange Rate: 80)\",\"amount\":2,\"currency\":\"EUR\",\"exchange_rate\":80,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 09:10:31'),
(1567, 1, 1, 'add', 'main_account_transactions', 1002, '[]', '{\"booking_id\":45,\"payment_date\":\"2025-09-20 13:40:31\",\"description\":\" (Exchange Rate: 18.49)\",\"amount\":20,\"currency\":\"DARHAM\",\"exchange_rate\":18.49,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 09:11:02'),
(1568, 1, 1, 'add', 'main_account_transactions', 1003, '[]', '{\"booking_id\":45,\"payment_date\":\"2025-09-20 13:41:02\",\"description\":\"dxfg\",\"amount\":100,\"currency\":\"AFS\",\"exchange_rate\":0,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 09:11:47'),
(1569, 1, 1, 'create', 'main_account_transactions', 1004, NULL, '{\"weight_id\":8,\"amount\":5,\"currency\":\"EUR\",\"exchange_rate\":0.9,\"transaction_date\":\"2025-09-20 14:34\",\"remarks\":\"adsfa\",\"main_account_id\":11,\"balance\":203.14}', '::1', '0', '2025-09-20 10:04:39'),
(1570, 1, 1, 'create', 'main_account_transactions', 1005, NULL, '{\"weight_id\":11,\"amount\":100,\"currency\":\"AFS\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-20 17:01\",\"remarks\":\"adfadsf\",\"main_account_id\":11,\"balance\":8526.36}', '::1', '0', '2025-09-20 12:31:53'),
(1571, 1, 1, 'create', 'main_account_transactions', 1006, NULL, '{\"weight_id\":11,\"amount\":2,\"currency\":\"USD\",\"exchange_rate\":70,\"transaction_date\":\"2025-09-20 17:01\",\"remarks\":\"asdfadsf\",\"main_account_id\":11,\"balance\":8760.32}', '::1', '0', '2025-09-20 12:32:06'),
(1572, 1, 1, 'create', 'main_account_transactions', 1007, NULL, '{\"weight_id\":11,\"amount\":2,\"currency\":\"EUR\",\"exchange_rate\":77,\"transaction_date\":\"2025-09-20 17:02\",\"remarks\":\"adfadsf\",\"main_account_id\":11,\"balance\":205.14}', '::1', '0', '2025-09-20 12:32:23'),
(1573, 1, 1, 'create', 'main_account_transactions', 1008, NULL, '{\"weight_id\":11,\"amount\":10,\"currency\":\"DARHAM\",\"exchange_rate\":18.5,\"transaction_date\":\"2025-09-20 17:02\",\"remarks\":\"adsfads\",\"main_account_id\":11,\"balance\":468}', '::1', '0', '2025-09-20 12:32:39'),
(1574, 1, 1, 'delete', 'main_account_transactions', 1008, '{\"transaction_id\":1008,\"weight_id\":11,\"amount\":10,\"currency\":\"DARHAM\",\"main_account_id\":11,\"created_at\":\"2025-09-20 17:02:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 13:13:10'),
(1575, 1, 1, 'create', 'main_account_transactions', 1009, NULL, '{\"weight_id\":11,\"amount\":10,\"currency\":\"DARHAM\",\"exchange_rate\":18.5,\"transaction_date\":\"2025-09-20 17:43\",\"remarks\":\"cfgfxbn\",\"main_account_id\":11,\"balance\":468}', '::1', '0', '2025-09-20 13:13:34'),
(1576, 1, 1, 'create', 'main_account_transactions', 1010, NULL, '{\"weight_id\":11,\"amount\":121,\"currency\":\"AFS\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-20 17:49\",\"remarks\":\"afdadsf\",\"main_account_id\":11,\"balance\":8647.36}', '::1', '0', '2025-09-20 13:19:46'),
(1577, 1, 1, 'add', 'main_account_transactions', 1011, '[]', '{\"booking_id\":12,\"payment_date\":\"2025-09-20 17:51:40\",\"description\":\"hfghj\",\"amount\":100,\"currency\":\"AFS\",\"exchange_rate\":null,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 13:22:07'),
(1578, 1, 1, 'add', 'main_account_transactions', 1012, '[]', '{\"booking_id\":12,\"payment_date\":\"2025-09-20 17:52:07\",\"description\":\"xfgvhbf (Exchange Rate: 70)\",\"amount\":2,\"currency\":\"USD\",\"exchange_rate\":70,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 13:22:23'),
(1579, 1, 1, 'add', 'main_account_transactions', 1013, '[]', '{\"booking_id\":12,\"payment_date\":\"2025-09-20 17:52:23\",\"description\":\"hgbsdfgf (Exchange Rate: 18.5)\",\"amount\":10,\"currency\":\"DARHAM\",\"exchange_rate\":18.5,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 13:22:57'),
(1580, 1, 1, 'add', 'main_account_transactions', 1014, '[]', '{\"booking_id\":12,\"payment_date\":\"2025-09-20 17:52:57\",\"description\":\"xzdfgbvdfzgbv (Exchange Rate: 77)\",\"amount\":2,\"currency\":\"EUR\",\"exchange_rate\":77,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-20 13:23:18'),
(1581, 1, 1, 'add', 'main_account_transactions', 1015, '[]', '{\"booking_id\":12,\"payment_date\":\"2025-09-21 08:04:24\",\"description\":\"adsfads (Exchange Rate: 70)\",\"amount\":13.16,\"currency\":\"USD\",\"exchange_rate\":70,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 03:36:45'),
(1582, 1, 1, 'add', 'main_account_transactions', 1016, '[]', '{\"booking_id\":34,\"payment_date\":null,\"transaction_type\":\"credit\",\"description\":\"fadsf\",\"amount\":100,\"currency\":\"DARHAM\",\"is_refund\":null,\"original_transaction_id\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 03:45:19'),
(1583, 1, 1, 'add', 'main_account_transactions', 1017, '[]', '{\"booking_id\":34,\"payment_date\":null,\"transaction_type\":\"credit\",\"description\":\"adfadsf\",\"amount\":20,\"currency\":\"USD\",\"is_refund\":null,\"original_transaction_id\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 03:45:35'),
(1584, 1, 1, 'update', '0', 1016, '{\"transaction_id\":\"1016\",\"booking_id\":\"34\",\"amount\":100,\"type\":\"credit\",\"created_at\":\"2025-09-21 08:15:19\",\"description\":\"\",\"currency\":\"USD\"}', '{\"amount\":100,\"type\":\"credit\",\"description\":\"fadsf\",\"created_at\":\"2025-09-21 08:15:19\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 03:45:55'),
(1585, 1, 1, 'add', 'main_account_transactions', 1018, '[]', '{\"booking_id\":34,\"payment_date\":null,\"transaction_type\":\"credit\",\"description\":\"adfadsf\",\"amount\":100,\"currency\":\"DARHAM\",\"is_refund\":null,\"original_transaction_id\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 03:46:33'),
(1586, 1, 1, 'delete', 'main_account_transactions', 1018, '{\"transaction_id\":1018,\"booking_id\":34,\"amount\":100,\"currency\":\"DARHAM\",\"transaction_type\":\"credit\",\"is_refund\":false,\"main_account_id\":11,\"description\":\"adfadsf\",\"created_at\":\"2025-09-21 08:16:33\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 03:53:04'),
(1587, 1, 1, 'add', 'main_account_transactions', 1019, '[]', '{\"booking_id\":34,\"payment_date\":null,\"transaction_type\":null,\"description\":\"sgsdfg\",\"amount\":100,\"currency\":\"DARHAM\",\"exchange_rate\":3.61}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 03:54:06'),
(1588, 1, 1, 'add', 'main_account_transactions', 1020, '[]', '{\"booking_id\":38,\"payment_date\":null,\"description\":\"fgsdfg\",\"amount\":100,\"currency\":\"AFS\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 04:03:42'),
(1589, 1, 1, 'add', 'main_account_transactions', 1021, '[]', '{\"booking_id\":38,\"payment_date\":null,\"description\":\"afdsf\",\"amount\":2,\"currency\":\"USD\",\"exchange_rate\":70}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 04:03:58'),
(1590, 1, 1, 'add', 'main_account_transactions', 1022, '[]', '{\"booking_id\":38,\"payment_date\":null,\"description\":\"adfasf\",\"amount\":10,\"currency\":\"DARHAM\",\"exchange_rate\":18.5}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 04:04:23'),
(1591, 1, 1, 'add', 'main_account_transactions', 1023, '[]', '{\"booking_id\":38,\"payment_date\":null,\"description\":\"ffadsf\",\"amount\":5,\"currency\":\"EUR\",\"exchange_rate\":77.2}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 04:04:50'),
(1592, 1, 1, 'add', 'umrah_bookings', 54, '[]', '{\"family_id\":\"11\",\"supplier\":\"29\",\"sold_to\":\"19\",\"paid_to\":\"11\",\"name\":\"Matiullah\",\"passport_number\":\"P07592390\",\"flight_date\":\"\",\"return_date\":\"\",\"room_type\":\"1 Bed\",\"price\":\"10000\",\"sold_price\":\"12000\",\"profit\":\"2000.00\",\"exchange_rate\":\"\",\"remarks\":\"Base amount of 10000 AFS deducted for umrah.\",\"discount\":\"0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 04:47:51'),
(1593, 1, 1, 'add', 'umrah_transactions', 67, '[]', '{\"umrah_booking_id\":54,\"transaction_to\":\"Internal Account\",\"payment_amount\":1000,\"payment_currency\":\"AFS\",\"payment_description\":\"dfdsaf\",\"payment_date\":\"2025-09-21\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 04:48:37'),
(1594, 1, 1, 'add', 'umrah_transactions', 68, '[]', '{\"umrah_booking_id\":54,\"transaction_to\":\"Internal Account\",\"payment_amount\":10,\"payment_currency\":\"USD\",\"payment_description\":\"fadss\",\"payment_date\":\"2025-09-21\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 04:48:53'),
(1595, 1, 1, 'add', 'umrah_transactions', 69, '[]', '{\"umrah_booking_id\":54,\"transaction_to\":\"Internal Account\",\"payment_amount\":10,\"payment_currency\":\"EUR\",\"payment_description\":\"afdsf\",\"payment_date\":\"2025-09-21\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 04:49:13'),
(1596, 1, 1, 'add', 'umrah_transactions', 70, '[]', '{\"umrah_booking_id\":54,\"transaction_to\":\"Internal Account\",\"payment_amount\":100,\"payment_currency\":\"DARHAM\",\"payment_description\":\"afdsafds\",\"payment_date\":\"2025-09-21\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 04:49:34'),
(1597, 1, 1, 'delete', 'umrah_transactions', 67, '{\"transaction_id\":67,\"umrah_id\":54,\"payment_amount\":1000,\"currency\":\"AFS\",\"transaction_to\":\"Internal Account\",\"payment_description\":\"dfdsaf\",\"is_refund\":false,\"supplier_id\":29,\"paid_to\":11}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 08:13:58'),
(1598, 1, 1, 'delete', 'umrah_transactions', 68, '{\"transaction_id\":68,\"umrah_id\":54,\"payment_amount\":10,\"currency\":\"USD\",\"transaction_to\":\"Internal Account\",\"payment_description\":\"fadss\",\"is_refund\":false,\"supplier_id\":29,\"paid_to\":11}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 08:14:04'),
(1599, 1, 1, 'delete', 'umrah_transactions', 69, '{\"transaction_id\":69,\"umrah_id\":54,\"payment_amount\":10,\"currency\":\"EUR\",\"transaction_to\":\"Internal Account\",\"payment_description\":\"afdsf\",\"is_refund\":false,\"supplier_id\":29,\"paid_to\":11}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 08:14:06'),
(1600, 1, 1, 'delete', 'umrah_transactions', 70, '{\"transaction_id\":70,\"umrah_id\":54,\"payment_amount\":100,\"currency\":\"DAR\",\"transaction_to\":\"Internal Account\",\"payment_description\":\"afdsafds\",\"is_refund\":false,\"supplier_id\":29,\"paid_to\":11}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 08:14:10'),
(1601, 1, 1, 'add', 'umrah_transactions', 71, '[]', '{\"umrah_booking_id\":54,\"transaction_to\":\"Internal Account\",\"payment_amount\":1000,\"payment_currency\":\"AFS\",\"payment_description\":\"zcvcxzv\",\"payment_date\":\"2025-09-21\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 08:14:31'),
(1602, 1, 1, 'add', 'umrah_transactions', 72, '[]', '{\"umrah_booking_id\":54,\"transaction_to\":\"Internal Account\",\"payment_amount\":100,\"payment_currency\":\"USD\",\"payment_description\":\"szdfvadsf\",\"payment_date\":\"2025-09-21\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 08:14:54'),
(1603, 1, 1, 'add', 'umrah_transactions', 73, '[]', '{\"umrah_booking_id\":54,\"transaction_to\":\"Internal Account\",\"payment_amount\":20,\"payment_currency\":\"EUR\",\"payment_description\":\"dgbdfsg\",\"payment_date\":\"2025-09-21\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 08:15:30'),
(1604, 1, 1, 'add', 'umrah_transactions', 74, '[]', '{\"umrah_booking_id\":54,\"transaction_to\":\"Internal Account\",\"payment_amount\":100,\"payment_currency\":\"DARHAM\",\"payment_description\":\"sdfgsf\",\"payment_date\":\"2025-09-21\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 08:23:58'),
(1605, 1, 1, 'add', 'umrah_transactions', 75, '[]', '{\"umrah_booking_id\":50,\"transaction_to\":\"Internal Account\",\"payment_amount\":20,\"payment_currency\":\"EUR\",\"payment_description\":\"fgsdfg\",\"payment_date\":\"2025-09-21\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-21 08:25:28'),
(1606, 1, 1, 'fund', 'main_account', 11, '{\"account_id\":\"11\",\"usd_balance\":\"0.000\"}', '{\"account_id\":\"11\",\"usd_balance\":10000,\"amount\":10000,\"currency\":\"USD\",\"description\":\"Account funded by Sabaoon. Remarks: sgfgsd. Receipt: safgsdf\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 11:35:33'),
(1607, 1, 1, 'fund', 'main_account', 11, '{\"account_id\":\"11\",\"afs_balance\":\"0.000\"}', '{\"account_id\":\"11\",\"afs_balance\":10000,\"amount\":10000,\"currency\":\"AFS\",\"description\":\"Account funded by Sabaoon. Remarks: gfdgsdf. Receipt: t43t4tw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 11:35:49'),
(1608, 1, 1, 'fund', 'suppliers', 27, '{\"supplier_id\":27,\"supplier_balance\":\"0.000\",\"main_account_id\":11,\"main_account_balance\":10000}', '{\"supplier_id\":27,\"supplier_balance\":1000,\"main_account_id\":11,\"main_account_balance\":9000,\"amount\":1000,\"currency\":\"USD\",\"remarks\":\"adsfdsaf\",\"receipt_number\":\"2452345\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 11:36:15'),
(1609, 1, 1, 'fund', 'suppliers', 29, '{\"supplier_id\":29,\"supplier_balance\":\"0.000\",\"main_account_id\":11,\"main_account_balance\":10000}', '{\"supplier_id\":29,\"supplier_balance\":1000,\"main_account_id\":11,\"main_account_balance\":9000,\"amount\":1000,\"currency\":\"AFS\",\"remarks\":\"adfadsf\",\"receipt_number\":\"adfadsf\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 11:36:38'),
(1610, 1, 1, 'update', '0', 855, '{\"notification_id\":855,\"previous_status\":\"Read\"}', '{\"status\":\"read\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 11:38:38'),
(1611, 1, 1, 'update', '0', 854, '{\"notification_id\":854,\"previous_status\":\"Read\"}', '{\"status\":\"read\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 11:38:39'),
(1612, 1, 1, 'update', '0', 857, '{\"notification_id\":857,\"previous_status\":\"Read\"}', '{\"status\":\"read\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 11:39:59'),
(1613, 1, 1, 'update', '0', 856, '{\"notification_id\":856,\"previous_status\":\"Read\"}', '{\"status\":\"read\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 11:40:00'),
(1614, 1, 1, 'add', 'ticket_bookings', 325, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"188JZ0\",\"origin\":\"MZR\",\"destination\":\"FRA\",\"airline\":\"I5\",\"departure_date\":\"2025-09-24\",\"total_base\":100,\"total_sold\":120,\"total_discount\":0,\"total_profit\":20,\"currency\":\"USD\",\"supplier_id\":27,\"supplier_name\":\"KamAir\",\"client_id\":19,\"client_name\":\"Walking Customers\",\"trip_type\":\"one_way\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 12:06:34'),
(1615, 1, 1, 'add', 'ticket_bookings', 326, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"188JZ0\",\"origin\":\"MZR\",\"destination\":\"FRA\",\"airline\":\"I5\",\"departure_date\":\"2025-09-24\",\"total_base\":100,\"total_sold\":120,\"total_discount\":0,\"total_profit\":20,\"currency\":\"USD\",\"supplier_id\":27,\"supplier_name\":\"KamAir\",\"client_id\":19,\"client_name\":\"Walking Customers\",\"trip_type\":\"one_way\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 12:06:40'),
(1616, 1, 1, 'delete', 'ticket_bookings', 326, '{\"ticket_id\":326,\"client_id\":19,\"supplier_id\":27,\"paid_to_id\":11,\"ticket_currency\":\"USD\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 12:13:05'),
(1617, 1, 1, 'add', 'main_account_transactions', 1048, '[]', '{\"booking_id\":325,\"payment_date\":\"2025-09-22 16:43:05\",\"description\":\"test\",\"amount\":1000,\"currency\":\"AFS\",\"exchange_rate\":70,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 12:13:50'),
(1618, 1, 1, 'add', 'main_account_transactions', 1049, '[]', '{\"booking_id\":325,\"payment_date\":\"2025-09-22 16:45:49\",\"description\":\"dfdsa\",\"amount\":20,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 12:16:04'),
(1619, 1, 1, 'add', 'main_account_transactions', 1050, '[]', '{\"booking_id\":325,\"payment_date\":\"2025-09-22 16:46:04\",\"description\":\"fdsafds\",\"amount\":20,\"currency\":\"EUR\",\"exchange_rate\":77,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 12:16:19'),
(1620, 1, 1, 'update', '0', 1050, '{\"transaction_id\":\"1050\",\"ticket_id\":\"325\",\"amount\":20,\"description\":\"fdsafds\",\"created_at\":\"2025-09-22 16:46:04\",\"type\":\"credit\",\"currency\":\"EUR\",\"exchange_rate\":null}', '{\"amount\":20,\"description\":\"fdsafds\",\"created_at\":\"2025-09-22 16:46:04\",\"exchange_rate\":0.9}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 12:28:09'),
(1621, 1, 1, 'add', 'main_account_transactions', 1051, '[]', '{\"booking_id\":325,\"payment_date\":\"2025-09-22 16:58:50\",\"description\":\"vzdfvdcx\",\"amount\":100,\"currency\":\"DARHAM\",\"exchange_rate\":3.61,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 12:29:33'),
(1622, 1, 1, 'update', '0', 858, '{\"notification_id\":858,\"previous_status\":\"Read\"}', '{\"status\":\"read\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 12:31:44'),
(1623, 1, 1, 'add', 'ticket_bookings', 327, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"SZQXJU\",\"origin\":\" KBL\",\"destination\":\"TAS\",\"airline\":\"JL\",\"departure_date\":\"2025-10-03\",\"total_base\":10000,\"total_sold\":15000,\"total_discount\":0,\"total_profit\":5000,\"currency\":\"AFS\",\"supplier_id\":29,\"supplier_name\":\"Ariana\",\"client_id\":19,\"client_name\":\"Walking Customers\",\"trip_type\":\"one_way\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 12:33:35'),
(1624, 1, 1, 'delete', 'ticket_bookings', 327, '{\"ticket_id\":327,\"client_id\":19,\"supplier_id\":29,\"paid_to_id\":11,\"ticket_currency\":\"AFS\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 12:36:52'),
(1625, 1, 1, 'update', '0', 29, '{\"name\":\"Ariana\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"AFS\",\"balance\":\"1000.000\"}', '{\"name\":\"Ariana\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"AFS\",\"balance\":\"1000.000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 12:36:59'),
(1626, 1, 1, 'add', 'ticket_bookings', 328, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"SZQXJU\",\"origin\":\"MZR\",\"destination\":\"DOH\",\"airline\":\"AK\",\"departure_date\":\"2025-10-07\",\"total_base\":10000,\"total_sold\":15000,\"total_discount\":0,\"total_profit\":5000,\"currency\":\"AFS\",\"supplier_id\":29,\"supplier_name\":\"Ariana\",\"client_id\":19,\"client_name\":\"Walking Customers\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 12:38:06'),
(1627, 1, 1, 'delete', 'ticket_bookings', 328, '{\"ticket_id\":328,\"client_id\":19,\"supplier_id\":29,\"paid_to_id\":11,\"ticket_currency\":\"AFS\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-22 12:38:47'),
(1628, 1, 1, 'update', '0', 29, '{\"name\":\"Ariana\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"AFS\",\"balance\":\"1000.000\"}', '{\"name\":\"Ariana\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"AFS\",\"balance\":\"1000.000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 03:50:03'),
(1629, 1, 1, 'update', '0', 29, '{\"name\":\"Ariana\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"AFS\",\"balance\":\"1000.000\"}', '{\"name\":\"Ariana\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"AFS\",\"balance\":\"1000.000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 03:50:21'),
(1630, 1, 1, 'update', '0', 29, '{\"name\":\"Ariana\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"AFS\",\"balance\":\"1000.000\"}', '{\"name\":\"Ariana\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"RAHIMI107@GAMIL.COM\",\"address\":\"Jada-e-Maiwand\",\"currency\":\"AFS\",\"balance\":\"1000.000\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 03:53:05'),
(1631, 1, 1, 'add', 'ticket_bookings', 329, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"HAUPSE\",\"origin\":\" KBL\",\"destination\":\"ISB\",\"airline\":\"FG\",\"departure_date\":\"2025-10-02\",\"total_base\":10000,\"total_sold\":15000,\"total_discount\":0,\"total_profit\":5000,\"currency\":\"AFS\",\"supplier_id\":29,\"supplier_name\":\"Ariana\",\"client_id\":19,\"client_name\":\"Walking Customers\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 03:54:20'),
(1632, 1, 1, 'add', 'main_account_transactions', 1052, '[]', '{\"booking_id\":329,\"payment_date\":\"2025-09-23 08:24:20\",\"description\":\"dsfsdf\",\"amount\":1000,\"currency\":\"AFS\",\"exchange_rate\":null,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 03:55:12'),
(1633, 1, 1, 'add', 'main_account_transactions', 1053, '[]', '{\"booking_id\":329,\"payment_date\":\"2025-09-23 08:25:12\",\"description\":\"dfads\",\"amount\":50,\"currency\":\"USD\",\"exchange_rate\":70,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 03:55:25'),
(1634, 1, 1, 'add', 'main_account_transactions', 1054, '[]', '{\"booking_id\":329,\"payment_date\":\"2025-09-23 08:25:25\",\"description\":\"dsfasd\",\"amount\":50,\"currency\":\"EUR\",\"exchange_rate\":77,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 03:55:35'),
(1635, 1, 1, 'add', 'main_account_transactions', 1055, '[]', '{\"booking_id\":329,\"payment_date\":\"2025-09-23 08:25:35\",\"description\":\"dfsafds\",\"amount\":200,\"currency\":\"DARHAM\",\"exchange_rate\":18.5,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 03:55:48'),
(1636, 1, 1, 'add', 'main_account_transactions', 1056, '[]', '{\"booking_id\":329,\"payment_date\":\"2025-09-23 08:27:42\",\"description\":\"fadf\",\"amount\":2950,\"currency\":\"AFS\",\"exchange_rate\":null,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 03:58:58'),
(1637, 1, 1, 'add', 'refunded_tickets', 96, '{\"ticket_id\":329,\"passenger_name\":\"SHIRIN AGHA MUTAWAKIL\",\"pnr\":\"HAUPSE\",\"base\":10000,\"sold\":15000,\"supplier_penalty\":1000,\"service_penalty\":2000,\"currency\":\"AFS\",\"status\":\"Refunded\",\"description\":\"test\",\"calculation_method\":\"base\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 05:53:49'),
(1638, 1, 1, 'add', 'date_change_tickets', 46, '{\"ticket_id\":\"329\",\"passenger_name\":\"SHIRIN AGHA MUTAWAKIL\",\"pnr\":\"HAUPSE\",\"base\":10000,\"sold\":15000,\"supplier_penalty\":1000,\"service_penalty\":1000,\"currency\":\"AFS\",\"status\":\"Date Changed\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 05:54:12'),
(1639, 1, 1, 'create', 'ticket_weights', 12, NULL, '{\"ticket_id\":329,\"weight\":20,\"base_price\":1000,\"sold_price\":2000,\"profit\":1000,\"remarks\":\"tet\",\"supplier_name\":\"Ariana\",\"client_name\":\"Walking Customers\",\"currency\":\"AFS\"}', '::1', '0', '2025-09-23 05:54:31'),
(1640, 1, 1, 'add', 'main_account_transactions', 1057, '[]', '{\"booking_id\":96,\"payment_date\":\"2025-09-23 10:24:47\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":70,\"client_type\":\"agency\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 05:57:33'),
(1641, 1, 1, 'add', 'main_account_transactions', 1058, '[]', '{\"booking_id\":96,\"payment_date\":\"2025-09-23 10:27:33\",\"description\":\"test\",\"amount\":1000,\"currency\":\"AFS\",\"exchange_rate\":0,\"client_type\":\"agency\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 05:58:01'),
(1642, 1, 1, 'add', 'main_account_transactions', 1059, '[]', '{\"booking_id\":96,\"payment_date\":\"2025-09-23 10:28:01\",\"description\":\"test\",\"amount\":10,\"currency\":\"EUR\",\"exchange_rate\":77,\"client_type\":\"agency\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 05:58:27'),
(1643, 1, 1, 'add', 'main_account_transactions', 1060, '[]', '{\"booking_id\":96,\"payment_date\":\"2025-09-23 10:28:27\",\"description\":\"tets\",\"amount\":100,\"currency\":\"DARHAM\",\"exchange_rate\":18.5,\"client_type\":\"agency\",\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 05:58:43'),
(1644, 1, 1, 'add', 'main_account_transactions', 1061, '[]', '{\"booking_id\":46,\"payment_date\":\"2025-09-23 10:34:37\",\"description\":\"test (Exchange Rate: 70)\",\"amount\":2,\"currency\":\"USD\",\"exchange_rate\":70,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 06:05:10'),
(1645, 1, 1, 'add', 'main_account_transactions', 1062, '[]', '{\"booking_id\":46,\"payment_date\":\"2025-09-23 10:35:10\",\"description\":\"test (Exchange Rate: 77)\",\"amount\":5,\"currency\":\"EUR\",\"exchange_rate\":77,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 06:05:30'),
(1646, 1, 1, 'add', 'main_account_transactions', 1063, '[]', '{\"booking_id\":46,\"payment_date\":\"2025-09-23 10:35:30\",\"description\":\"tets\",\"amount\":500,\"currency\":\"AFS\",\"exchange_rate\":0,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 06:05:43'),
(1647, 1, 1, 'add', 'main_account_transactions', 1064, '[]', '{\"booking_id\":46,\"payment_date\":\"2025-09-23 10:35:43\",\"description\":\"tet (Exchange Rate: 18.5)\",\"amount\":100,\"currency\":\"DARHAM\",\"exchange_rate\":18.5,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 06:06:05'),
(1648, 1, 1, 'delete', 'main_account_transactions', 1064, '{\"transaction_id\":1064,\"ticket_id\":46,\"amount\":100,\"currency\":\"DARHAM\",\"main_account_id\":11,\"created_at\":\"2025-09-23 10:35:43\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 06:14:30'),
(1649, 1, 1, 'delete', 'main_account_transactions', 1063, '{\"transaction_id\":1063,\"ticket_id\":46,\"amount\":500,\"currency\":\"AFS\",\"main_account_id\":11,\"created_at\":\"2025-09-23 10:35:30\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 07:25:22'),
(1650, 1, 1, 'delete', 'main_account_transactions', 1062, '{\"transaction_id\":1062,\"ticket_id\":46,\"amount\":5,\"currency\":\"EUR\",\"main_account_id\":11,\"created_at\":\"2025-09-23 10:35:10\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 07:25:24'),
(1651, 1, 1, 'delete', 'main_account_transactions', 1061, '{\"transaction_id\":1061,\"ticket_id\":46,\"amount\":2,\"currency\":\"USD\",\"main_account_id\":11,\"created_at\":\"2025-09-23 10:34:37\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 07:25:26'),
(1652, 1, 1, 'add', 'main_account_transactions', 1065, '[]', '{\"booking_id\":46,\"payment_date\":\"2025-09-23 11:55:35\",\"description\":\"test (Exchange Rate: 70)\",\"amount\":2,\"currency\":\"USD\",\"exchange_rate\":70,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 07:25:58'),
(1653, 1, 1, 'add', 'main_account_transactions', 1066, '[]', '{\"booking_id\":46,\"payment_date\":\"2025-09-23 11:55:58\",\"description\":\"test (Exchange Rate: 77)\",\"amount\":5,\"currency\":\"EUR\",\"exchange_rate\":77,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 07:26:24'),
(1654, 1, 1, 'add', 'main_account_transactions', 1067, '[]', '{\"booking_id\":46,\"payment_date\":\"2025-09-23 11:56:24\",\"description\":\"test (Exchange Rate: 18)\",\"amount\":10,\"currency\":\"DARHAM\",\"exchange_rate\":18,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 07:26:53'),
(1655, 1, 1, 'add', 'main_account_transactions', 1068, '[]', '{\"booking_id\":46,\"payment_date\":\"2025-09-23 11:56:53\",\"description\":\"test\",\"amount\":1000,\"currency\":\"AFS\",\"exchange_rate\":0,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 07:27:16'),
(1656, 1, 1, 'update', 'ticket_weights', 12, '{\"weight_id\":12,\"weight\":\"20.00\",\"base_price\":\"1000.00\",\"sold_price\":\"2000.00\",\"profit\":\"1000.00\",\"market_exchange_rate\":null,\"exchange_rate\":null,\"remarks\":\"tet\"}', '{\"weight_id\":12,\"weight\":20,\"base_price\":1000,\"sold_price\":2000,\"profit\":1000,\"market_exchange_rate\":null,\"exchange_rate\":null,\"remarks\":\"tet\",\"supplier_name\":\"Ariana\",\"client_name\":\"Walking Customers\",\"base_price_difference\":0,\"sold_price_difference\":0}', '::1', '0', '2025-09-23 07:34:48'),
(1657, 1, 1, 'update', 'ticket_weights', 12, '{\"weight_id\":12,\"weight\":\"20.00\",\"base_price\":\"1000.00\",\"sold_price\":\"2000.00\",\"profit\":\"1000.00\",\"market_exchange_rate\":null,\"exchange_rate\":null,\"remarks\":\"tet\"}', '{\"weight_id\":12,\"weight\":20,\"base_price\":1000,\"sold_price\":2000,\"profit\":1000,\"market_exchange_rate\":null,\"exchange_rate\":null,\"remarks\":\"tet\",\"supplier_name\":\"Ariana\",\"client_name\":\"Walking Customers\",\"base_price_difference\":0,\"sold_price_difference\":0}', '::1', '0', '2025-09-23 07:34:54'),
(1658, 1, 1, 'update', 'ticket_weights', 12, '{\"weight_id\":12,\"weight\":\"20.00\",\"base_price\":\"1000.00\",\"sold_price\":\"2000.00\",\"profit\":\"1000.00\",\"remarks\":\"tet\"}', '{\"weight_id\":12,\"weight\":20,\"base_price\":1000,\"sold_price\":2000,\"profit\":1000,\"remarks\":\"tet\",\"supplier_name\":\"Ariana\",\"client_name\":\"Walking Customers\",\"base_price_difference\":0,\"sold_price_difference\":0}', '::1', '0', '2025-09-23 07:35:14'),
(1659, 1, 1, 'create', 'main_account_transactions', 1069, NULL, '{\"weight_id\":12,\"amount\":2,\"currency\":\"USD\",\"exchange_rate\":70,\"transaction_date\":\"2025-09-23 12:05\",\"remarks\":\"\",\"main_account_id\":11,\"balance\":9096}', '::1', '0', '2025-09-23 07:36:39'),
(1660, 1, 1, 'create', 'main_account_transactions', 1070, NULL, '{\"weight_id\":12,\"amount\":5,\"currency\":\"EUR\",\"exchange_rate\":77,\"transaction_date\":\"2025-09-23 12:06\",\"remarks\":\"tet\",\"main_account_id\":11,\"balance\":80}', '::1', '0', '2025-09-23 07:36:57'),
(1661, 1, 1, 'create', 'main_account_transactions', 1071, NULL, '{\"weight_id\":12,\"amount\":1000,\"currency\":\"AFS\",\"exchange_rate\":null,\"transaction_date\":\"2025-09-23 12:06\",\"remarks\":\"test\",\"main_account_id\":11,\"balance\":4840}', '::1', '0', '2025-09-23 07:37:15'),
(1662, 1, 1, 'create', 'main_account_transactions', 1072, NULL, '{\"weight_id\":12,\"amount\":10,\"currency\":\"DARHAM\",\"exchange_rate\":18,\"transaction_date\":\"2025-09-23 12:07\",\"remarks\":\"test\",\"main_account_id\":11,\"balance\":320}', '::1', '0', '2025-09-23 07:37:38'),
(1663, 1, 1, 'add', 'ticket_reservations', 13, '{}', '{\"passenger_name\":\"BAKHTIAR STANIKZAI\",\"pnr\":\"188JZ0\",\"origin\":\"MZR\",\"destination\":\"FRA\",\"airline\":\"EI\",\"departure_date\":\"2025-09-26\",\"base\":0,\"sold\":50,\"profit\":50,\"currency\":\"USD\",\"supplier\":27,\"supplier_name\":\"KamAir\",\"sold_to\":18,\"client_name\":\"DR SAHIBs\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 08:17:50'),
(1664, 1, 1, 'add', 'ticket_reservations', 14, '{}', '{\"passenger_name\":\"HAMID ACHAKZAI \",\"pnr\":\"SZQXJU\",\"origin\":\"Kabul\",\"destination\":\"ISB\",\"airline\":\"QZ\",\"departure_date\":\"2025-09-25\",\"base\":0,\"sold\":50,\"profit\":50,\"currency\":\"USD\",\"supplier\":27,\"supplier_name\":\"KamAir\",\"sold_to\":19,\"client_name\":\"Walking Customers\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 08:19:06'),
(1665, 1, 1, 'add', 'main_account_transactions', 1073, '[]', '{\"booking_id\":14,\"payment_date\":\"2025-09-23 12:49:39\",\"description\":\"teste\",\"amount\":5,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 08:19:49'),
(1666, 1, 1, 'add', 'main_account_transactions', 1074, '[]', '{\"booking_id\":14,\"payment_date\":\"2025-09-23 12:49:50\",\"description\":\"test (Exchange Rate: 0.9)\",\"amount\":5,\"currency\":\"EUR\",\"exchange_rate\":0.9,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 08:20:05'),
(1667, 1, 1, 'add', 'main_account_transactions', 1075, '[]', '{\"booking_id\":14,\"payment_date\":\"2025-09-23 12:50:05\",\"description\":\"test (Exchange Rate: 70)\",\"amount\":1000,\"currency\":\"AFS\",\"exchange_rate\":70,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 08:20:22'),
(1668, 1, 1, 'add', 'main_account_transactions', 1076, '[]', '{\"booking_id\":14,\"payment_date\":\"2025-09-23 12:50:22\",\"description\":\"test (Exchange Rate: 3.61)\",\"amount\":100,\"currency\":\"DARHAM\",\"exchange_rate\":3.61,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 08:20:38'),
(1669, 1, 1, 'add', 'ticket_reservations', 15, '{}', '{\"passenger_name\":\"SIDDIQULALAH STANIKZAI\",\"pnr\":\"WKEPD1\",\"origin\":\"MZR\",\"destination\":\"ISB\",\"airline\":\"D7\",\"departure_date\":\"2025-09-26\",\"base\":10,\"sold\":60,\"profit\":50,\"currency\":\"AFS\",\"supplier\":29,\"supplier_name\":\"Ariana\",\"sold_to\":19,\"client_name\":\"Walking Customers\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 09:06:13'),
(1670, 1, 1, 'update', '0', 15, '{\"ticket_id\":15,\"supplier\":\"29\",\"sold_to\":19,\"price\":\"10.000\",\"sold\":\"60.000\",\"currency\":\"AFS\"}', '{\"supplier\":29,\"sold_to\":19,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"SIDDIQULALAH STANIKZAI\",\"pnr\":\"WKEPD1\",\"phone\":\"0780310431\",\"origin\":\"MZR\",\"destination\":\"ISB\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"D7\",\"issue_date\":\"2025-09-23\",\"departure_date\":\"2025-09-26\",\"return_date\":\"\",\"price\":1000,\"sold\":6000,\"profit\":5000,\"currency\":\"AFS\",\"description\":\"test\",\"paid_to\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 09:11:28'),
(1671, 1, 1, 'add', 'main_account_transactions', 1077, '[]', '{\"booking_id\":15,\"payment_date\":\"2025-09-23 13:41:29\",\"description\":\"test\",\"amount\":500,\"currency\":\"AFS\",\"exchange_rate\":null,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 09:12:06'),
(1672, 1, 1, 'add', 'main_account_transactions', 1078, '[]', '{\"booking_id\":15,\"payment_date\":\"2025-09-23 13:42:06\",\"description\":\"test (Exchange Rate: 70)\",\"amount\":5,\"currency\":\"USD\",\"exchange_rate\":70,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 09:12:18'),
(1673, 1, 1, 'add', 'main_account_transactions', 1079, '[]', '{\"booking_id\":15,\"payment_date\":\"2025-09-23 13:42:18\",\"description\":\"test (Exchange Rate: 77)\",\"amount\":10,\"currency\":\"EUR\",\"exchange_rate\":77,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 09:12:35'),
(1674, 1, 1, 'add', 'main_account_transactions', 1080, '[]', '{\"booking_id\":15,\"payment_date\":\"2025-09-23 13:42:35\",\"description\":\"test (Exchange Rate: 18.5)\",\"amount\":10,\"currency\":\"DARHAM\",\"exchange_rate\":18.5,\"main_account_id\":11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 09:12:52'),
(1675, 1, 1, 'add', 'hotel_bookings', 39, '[]', '{\"title\":\"Mr\",\"first_name\":\"NAVEED\",\"last_name\":\"RASHIQ\",\"gender\":\"Male\",\"check_in_date\":\"2025-09-23\",\"check_out_date\":\"2025-10-10\",\"accommodation_details\":\"t5et\",\"supplier_id\":27,\"sold_to\":\"19\",\"base_amount\":10,\"sold_amount\":60,\"profit\":50,\"currency\":\"USD\",\"paid_to\":\"11\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 10:15:18'),
(1676, 1, 1, 'add', 'main_account_transactions', 1081, '[]', '{\"booking_id\":39,\"payment_date\":null,\"description\":\"test\",\"amount\":5,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 10:22:46'),
(1677, 1, 1, 'add', 'main_account_transactions', 1082, '[]', '{\"booking_id\":39,\"payment_date\":null,\"description\":\"tet\",\"amount\":5,\"currency\":\"EUR\",\"exchange_rate\":0.9}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 10:23:07'),
(1678, 1, 1, 'add', 'main_account_transactions', 1083, '[]', '{\"booking_id\":39,\"payment_date\":null,\"description\":\"test\",\"amount\":1000,\"currency\":\"AFS\",\"exchange_rate\":77}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 10:23:29'),
(1679, 1, 1, 'add', 'main_account_transactions', 1084, '[]', '{\"booking_id\":39,\"payment_date\":null,\"description\":\"test\",\"amount\":100,\"currency\":\"DARHAM\",\"exchange_rate\":3.61}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 10:23:47'),
(1680, 1, 1, 'add', 'hotel_bookings', 41, '[]', '{\"title\":\"Mr\",\"first_name\":\"NAVEED1\",\"last_name\":\"RASHIQ1\",\"gender\":\"Male\",\"check_in_date\":\"2025-09-23\",\"check_out_date\":\"2025-10-01\",\"accommodation_details\":\"test\",\"supplier_id\":29,\"sold_to\":\"19\",\"base_amount\":1000,\"sold_amount\":5000,\"profit\":4000,\"currency\":\"AFS\",\"paid_to\":\"11\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 10:24:59'),
(1681, 1, 1, 'add', 'main_account_transactions', 1085, '[]', '{\"booking_id\":41,\"payment_date\":null,\"description\":\"test\",\"amount\":5,\"currency\":\"USD\",\"exchange_rate\":70}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 10:29:38'),
(1682, 1, 1, 'add', 'main_account_transactions', 1086, '[]', '{\"booking_id\":41,\"payment_date\":null,\"description\":\"test\",\"amount\":5,\"currency\":\"EUR\",\"exchange_rate\":77}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 10:29:54'),
(1683, 1, 1, 'add', 'main_account_transactions', 1087, '[]', '{\"booking_id\":41,\"payment_date\":null,\"description\":\"tets\",\"amount\":1000,\"currency\":\"AFS\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 10:30:30'),
(1684, 1, 1, 'add', 'main_account_transactions', 1088, '[]', '{\"booking_id\":41,\"payment_date\":null,\"description\":\"test\",\"amount\":100,\"currency\":\"DARHAM\",\"exchange_rate\":18}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 10:31:01'),
(1685, 1, 1, 'add', 'umrah_bookings', 55, '[]', '{\"family_id\":\"12\",\"supplier\":\"27\",\"sold_to\":\"19\",\"paid_to\":\"11\",\"name\":\"Matiullah\",\"passport_number\":\"P07592390\",\"flight_date\":\"\",\"return_date\":\"\",\"room_type\":\"3 Beds\",\"price\":\"500\",\"sold_price\":\"1000\",\"profit\":\"500.00\",\"exchange_rate\":null,\"remarks\":\"Base amount of 500 USD deducted for umrah.\",\"discount\":\"0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 10:49:46'),
(1686, 1, 1, 'add', 'umrah_transactions', 76, '[]', '{\"umrah_booking_id\":55,\"transaction_to\":\"Internal Account\",\"payment_amount\":100,\"payment_currency\":\"USD\",\"payment_description\":\"test\",\"payment_date\":\"2025-09-23\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 10:50:34'),
(1687, 1, 1, 'add', 'umrah_transactions', 77, '[]', '{\"umrah_booking_id\":55,\"transaction_to\":\"Internal Account\",\"payment_amount\":7000,\"payment_currency\":\"AFS\",\"payment_description\":\"test\",\"payment_date\":\"2025-09-23\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 10:50:52');
INSERT INTO `activity_log` (`id`, `tenant_id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(1688, 1, 1, 'add', 'umrah_transactions', 78, '[]', '{\"umrah_booking_id\":55,\"transaction_to\":\"Internal Account\",\"payment_amount\":110,\"payment_currency\":\"EUR\",\"payment_description\":\"\",\"payment_date\":\"2025-09-23\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 10:51:19'),
(1689, 1, 1, 'add', 'umrah_transactions', 79, '[]', '{\"umrah_booking_id\":55,\"transaction_to\":\"Internal Account\",\"payment_amount\":200,\"payment_currency\":\"DARHAM\",\"payment_description\":\"tets\",\"payment_date\":\"2025-09-23\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 10:51:56'),
(1690, 1, 1, 'add', 'umrah_bookings', 56, '[]', '{\"family_id\":\"13\",\"supplier\":\"29\",\"sold_to\":\"19\",\"paid_to\":\"11\",\"name\":\"Idrees\",\"passport_number\":\"P07592390\",\"flight_date\":\"\",\"return_date\":\"\",\"room_type\":\"Shared\",\"price\":\"10000\",\"sold_price\":\"12000\",\"profit\":\"2000.00\",\"remarks\":\"Base amount of 10000 AFS deducted for umrah.\",\"discount\":\"0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 12:02:39'),
(1691, 1, 1, 'add', 'umrah_transactions', 80, '[]', '{\"umrah_booking_id\":56,\"transaction_to\":\"Internal Account\",\"payment_amount\":1000,\"payment_currency\":\"AFS\",\"payment_description\":\"test\",\"payment_date\":\"2025-09-23\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 12:04:41'),
(1692, 1, 1, 'add', 'umrah_transactions', 81, '[]', '{\"umrah_booking_id\":56,\"transaction_to\":\"Internal Account\",\"payment_amount\":50,\"payment_currency\":\"USD\",\"payment_description\":\"test\",\"payment_date\":\"2025-09-23\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 12:10:10'),
(1693, 1, 1, 'add', 'umrah_transactions', 82, '[]', '{\"umrah_booking_id\":56,\"transaction_to\":\"Internal Account\",\"payment_amount\":50,\"payment_currency\":\"EUR\",\"payment_description\":\"test\",\"payment_date\":\"2025-09-23\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 12:11:12'),
(1694, 1, 1, 'add', 'umrah_transactions', 83, '[]', '{\"umrah_booking_id\":56,\"transaction_to\":\"Internal Account\",\"payment_amount\":50,\"payment_currency\":\"DARHAM\",\"payment_description\":\"test\",\"payment_date\":\"2025-09-23\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-23 12:12:23'),
(1695, 1, 1, 'reinstate', 'users', 8, '{\"fired\":true}', '{\"fired\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 08:41:32'),
(1696, 1, 1, 'add', 'additional_payments', 48, NULL, '{\"id\":48,\"payment_type\":\"Vacine\",\"description\":\"fdadfadsf\",\"base_amount\":100,\"profit\":50,\"sold_amount\":150,\"currency\":\"USD\",\"main_account_id\":11,\"supplier_id\":null,\"is_from_supplier\":1,\"client_id\":null,\"is_for_client\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 09:14:46'),
(1697, 1, 1, 'add', 'main_account_transactions', 1097, '[]', '{\"main_account_id\":11,\"amount\":20,\"currency\":\"USD\",\"description\":\"test\",\"payment_id\":48,\"balance\":9286,\"exchange_rate\":0,\"payment_datetime\":\"2025-09-25 13:44:57\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 09:15:14'),
(1698, 1, 1, 'add', 'main_account_transactions', 1098, '[]', '{\"main_account_id\":11,\"amount\":1000,\"currency\":\"AFS\",\"description\":\"test\",\"payment_id\":48,\"balance\":17960,\"exchange_rate\":70,\"payment_datetime\":\"2025-09-25 13:44:57\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 09:15:52'),
(1699, 1, 1, 'add', 'main_account_transactions', 1099, '[]', '{\"main_account_id\":11,\"amount\":100,\"currency\":\"DARHAM\",\"description\":\"test\",\"payment_id\":48,\"balance\":530,\"exchange_rate\":3.61,\"payment_datetime\":\"2025-09-25 13:44:57\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 09:16:15'),
(1700, 1, 1, 'add', 'main_account_transactions', 1100, '[]', '{\"main_account_id\":11,\"amount\":50,\"currency\":\"EUR\",\"description\":\"test\",\"payment_id\":48,\"balance\":145,\"exchange_rate\":0.9,\"payment_datetime\":\"2025-09-25 13:44:57\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 09:16:38'),
(1701, 1, 1, 'add', 'additional_payments', 49, NULL, '{\"id\":49,\"payment_type\":\"Vacine\",\"description\":\"tatfdsf\",\"base_amount\":2000,\"profit\":500,\"sold_amount\":2500,\"currency\":\"AFS\",\"main_account_id\":11,\"supplier_id\":null,\"is_from_supplier\":1,\"client_id\":null,\"is_for_client\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 09:18:16'),
(1702, 1, 1, 'add', 'main_account_transactions', 1101, '[]', '{\"main_account_id\":11,\"amount\":500,\"currency\":\"AFS\",\"description\":\"test\",\"payment_id\":49,\"balance\":18460,\"exchange_rate\":0,\"payment_datetime\":\"2025-09-25 13:48:21\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 09:18:33'),
(1703, 1, 1, 'add', 'main_account_transactions', 1102, '[]', '{\"main_account_id\":11,\"amount\":10,\"currency\":\"USD\",\"description\":\"test\",\"payment_id\":49,\"balance\":9296,\"exchange_rate\":70,\"payment_datetime\":\"2025-09-25 13:48:21\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 09:18:49'),
(1704, 1, 1, 'add', 'main_account_transactions', 1103, '[]', '{\"main_account_id\":11,\"amount\":10,\"currency\":\"EUR\",\"description\":\"test\",\"payment_id\":49,\"balance\":155,\"exchange_rate\":77,\"payment_datetime\":\"2025-09-25 13:48:21\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 09:19:16'),
(1705, 1, 1, 'add', 'main_account_transactions', 1104, '[]', '{\"main_account_id\":11,\"amount\":10,\"currency\":\"DARHAM\",\"description\":\"test\",\"payment_id\":49,\"balance\":540,\"exchange_rate\":18.5,\"payment_datetime\":\"2025-09-25 13:48:21\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 09:19:51'),
(1706, 1, 1, 'add', 'main_account_transactions', 1105, '[]', '{\"refund_id\":21,\"payment_date\":\"2025-09-25 14:44:25 14:44:25\",\"description\":\"Refund payment for Umrah Booking #56 - Idrees\",\"amount\":1000,\"currency\":\"AFS\",\"client_type\":\"agency\",\"main_account_id\":11,\"name\":\"Idrees\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 10:14:38'),
(1707, 1, 1, 'add', 'main_account_transactions', 1106, '[]', '{\"refund_id\":21,\"payment_date\":\"2025-09-25 14:44:41 14:44:41\",\"description\":\"test (Exchange Rate: 70.00000)\",\"amount\":100,\"currency\":\"USD\",\"client_type\":\"agency\",\"main_account_id\":11,\"name\":\"Idrees\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 10:14:56'),
(1708, 1, 1, 'add', 'main_account_transactions', 1107, '[]', '{\"refund_id\":21,\"payment_date\":\"2025-09-25 14:55:40 14:55:40\",\"description\":\"Refund payment for Umrah Booking #56 - Idrees\",\"amount\":5,\"currency\":\"EUR\",\"client_type\":\"agency\",\"main_account_id\":11,\"name\":\"Idrees\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 10:25:49'),
(1709, 1, 1, 'add', 'main_account_transactions', 1108, '[]', '{\"refund_id\":21,\"payment_date\":\"2025-09-25 14:56:02 14:56:02\",\"description\":\"test\",\"amount\":10,\"currency\":\"DARHAM\",\"client_type\":\"agency\",\"main_account_id\":11,\"name\":\"Idrees\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 10:26:14'),
(1710, 1, 1, 'update', '0', 1108, '{\"transaction_id\":\"1108\",\"booking_id\":\"21\",\"amount\":10,\"description\":\"\",\"created_at\":\"2025-09-25 14:56:02\",\"currency\":\"DARHAM\",\"type\":\"debit\"}', '{\"amount\":10,\"description\":\"test updated\",\"created_at\":\"2025-09-25 14:56:02\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 10:30:25'),
(1711, 1, 1, 'delete', 'main_account_transactions', 1108, '{\"transaction_id\":1108,\"refund_id\":21,\"amount\":10,\"currency\":\"DARHAM\",\"main_account_id\":11,\"created_at\":\"2025-09-25 14:56:02\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 10:31:38'),
(1712, 1, 1, 'update_umrah_member', 'umrah_bookings', 55, '{\"supplier\":27,\"sold_to\":19,\"paid_to\":11,\"entry_date\":\"2025-09-23\",\"name\":\"Matiullah\",\"dob\":\"2025-09-23\",\"passport_number\":\"P07592390\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":\"0000-00-00\",\"return_date\":\"0000-00-00\",\"duration\":\"23 Days\",\"room_type\":\"3 Beds\",\"price\":\"500.000\",\"sold_price\":\"1000.000\",\"profit\":\"500.000\",\"received_bank_payment\":\"0.000\",\"bank_receipt_number\":\"\",\"paid\":\"377.624\",\"due\":\"622.376\",\"discount\":\"0.000\"}', '{\"booking_id\":55,\"family_id\":12,\"supplier\":27,\"sold_to\":19,\"paid_to\":11,\"entry_date\":\"2025-09-23\",\"name\":\"Matiullah\",\"dob\":\"2025-09-23\",\"passport_number\":\"P07592390\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":\"2025-09-01\",\"return_date\":\"2025-09-25\",\"duration\":\"23 Days\",\"room_type\":\"3 Beds\",\"currency\":\"USD\",\"price\":500,\"sold_price\":1000,\"profit\":500,\"received_bank_payment\":0,\"bank_receipt_number\":\"\",\"paid\":377.624,\"due\":622.376,\"gender\":\"Male\",\"passport_expiry\":\"2026-04-02\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 10:33:02'),
(1713, 1, 1, 'add', 'visa_applications', 53, '[]', '{\"supplier\":27,\"sold_to\":\"19\",\"paid_to\":\"11\",\"applicant_name\":\"BAKHTIAR STANIKZAI\",\"passport_number\":\"P8798765\",\"visa_type\":\"Tourist\",\"base\":100,\"sold\":120,\"profit\":20,\"currency\":\"USD\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 11:26:02'),
(1714, 1, 1, 'add', 'visa_applications', 54, '[]', '{\"supplier\":29,\"sold_to\":\"19\",\"paid_to\":\"11\",\"applicant_name\":\"HAMID ACHAKZAI \",\"passport_number\":\"P879876\",\"visa_type\":\"Tourist\",\"base\":2000,\"sold\":2500,\"profit\":500,\"currency\":\"AFS\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 11:32:51'),
(1715, 1, 1, 'update', 'visa_applications', 54, '{\"id\":54,\"supplier\":29,\"sold_to\":19,\"base\":\"2000.000\",\"sold\":\"2500.000\",\"currency\":\"AFS\"}', '{\"id\":54,\"supplier\":29,\"sold_to\":19,\"title\":\"Mr\",\"gender\":\"Male\",\"applicant_name\":\"HAMID ACHAKZAI \",\"passport_number\":\"P879876\",\"country\":\"Pakistan\",\"visa_type\":\"Tourist\",\"receive_date\":\"2025-09-25\",\"applied_date\":\"2025-09-25\",\"issued_date\":\"\",\"base\":2000,\"sold\":2500,\"profit\":500,\"currency\":\"AFS\",\"status\":\"Approved\",\"remarks\":\"test\",\"phone\":\"0775172181\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 11:39:13'),
(1716, 1, 1, 'add', 'main_account_transactions', 1117, '[]', '{\"refund_id\":5,\"payment_date\":\"2025-09-25 16:23:53 16:23:53\",\"description\":\"Refund payment for Visa Application #54 - HAMID ACHAKZAI \",\"amount\":500,\"currency\":\"AFS\",\"exchange_rate\":0,\"client_type\":\"agency\",\"main_account_id\":11,\"applicant_name\":\"HAMID ACHAKZAI \",\"passport_number\":\"P879876\",\"country\":\"Pakistan\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 11:54:18'),
(1717, 1, 1, 'add', 'main_account_transactions', 1118, '[]', '{\"refund_id\":5,\"payment_date\":\"2025-09-25 16:24:20 16:24:20\",\"description\":\"test\",\"amount\":5,\"currency\":\"USD\",\"exchange_rate\":70,\"client_type\":\"agency\",\"main_account_id\":11,\"applicant_name\":\"HAMID ACHAKZAI \",\"passport_number\":\"P879876\",\"country\":\"Pakistan\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 11:54:45'),
(1718, 1, 1, 'add', 'main_account_transactions', 1119, '[]', '{\"refund_id\":5,\"payment_date\":\"2025-09-25 16:26:05 16:26:05\",\"description\":\"Refund payment for Visa Application #54 - HAMID ACHAKZAI \",\"amount\":5,\"currency\":\"EUR\",\"exchange_rate\":77,\"client_type\":\"agency\",\"main_account_id\":11,\"applicant_name\":\"HAMID ACHAKZAI \",\"passport_number\":\"P879876\",\"country\":\"Pakistan\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 11:56:24'),
(1719, 1, 1, 'add', 'main_account_transactions', 1120, '[]', '{\"refund_id\":5,\"payment_date\":\"2025-09-25 16:26:25 16:26:25\",\"description\":\"xdfg\",\"amount\":10,\"currency\":\"DARHAM\",\"exchange_rate\":18.5,\"client_type\":\"agency\",\"main_account_id\":11,\"applicant_name\":\"HAMID ACHAKZAI \",\"passport_number\":\"P879876\",\"country\":\"Pakistan\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-25 11:56:46'),
(1720, 1, 1, 'add', 'main_account_transactions', 1121, '[]', '{\"refund_id\":9,\"payment_date\":\"2025-09-27 11:19:22 11:19:22\",\"description\":\"Refund payment for Hotel Booking #41 - Mr NAVEED1 RASHIQ1\",\"amount\":500,\"currency\":\"AFS\",\"client_type\":\"agency\",\"main_account_id\":11,\"first_name\":\"NAVEED1\",\"last_name\":\"RASHIQ1\",\"order_id\":\"108973\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-27 06:49:41'),
(1721, 1, 1, 'add', 'main_account_transactions', 1122, '[]', '{\"refund_id\":9,\"payment_date\":\"2025-09-27 11:19:41 11:19:41\",\"description\":\"adsfadsf\",\"amount\":5,\"currency\":\"USD\",\"client_type\":\"agency\",\"main_account_id\":11,\"first_name\":\"NAVEED1\",\"last_name\":\"RASHIQ1\",\"order_id\":\"108973\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-27 06:49:55'),
(1722, 1, 1, 'add', 'main_account_transactions', 1123, '[]', '{\"refund_id\":9,\"payment_date\":\"2025-09-27 11:19:55 11:19:55\",\"description\":\"safdfas\",\"amount\":5,\"currency\":\"EUR\",\"client_type\":\"agency\",\"main_account_id\":11,\"first_name\":\"NAVEED1\",\"last_name\":\"RASHIQ1\",\"order_id\":\"108973\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-27 06:50:09'),
(1723, 1, 1, 'add', 'main_account_transactions', 1124, '[]', '{\"refund_id\":9,\"payment_date\":\"2025-09-27 11:20:09 11:20:09\",\"description\":\"sdfds\",\"amount\":10,\"currency\":\"DARHAM\",\"client_type\":\"agency\",\"main_account_id\":11,\"first_name\":\"NAVEED1\",\"last_name\":\"RASHIQ1\",\"order_id\":\"108973\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-27 06:50:30'),
(1724, 1, 1, 'add', 'umrah_bookings', 58, '[]', '{\"family_id\":\"12\",\"sold_to\":\"19\",\"paid_to\":\"11\",\"name\":\"Matiullah\",\"passport_number\":\"P07592390\",\"flight_date\":\"\",\"return_date\":\"\",\"room_type\":\"Shared\",\"total_base_price\":110,\"total_sold_price\":120,\"total_profit\":10,\"services\":[{\"service_type\":\"all\",\"supplier_id\":\"27\",\"currency\":\"USD\",\"base_price\":\"110\",\"sold_price\":\"120\",\"profit\":10}],\"remarks\":\"Base amount of 110 USD deducted for umrah all.\",\"discount\":\"0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-27 10:59:41'),
(1725, 1, 1, 'delete', 'umrah_bookings', 58, '{\"booking_id\":58,\"client_id\":19,\"supplier_id\":0,\"paid_to\":11,\"currency\":\"USD\",\"client_type\":\"agency\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-27 11:06:35'),
(1726, 1, 1, 'delete', 'umrah_bookings', 57, '{\"booking_id\":57,\"client_id\":19,\"supplier_id\":0,\"paid_to\":11,\"currency\":\"USD\",\"client_type\":\"agency\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-27 11:06:52'),
(1727, 1, 1, 'add', 'umrah_bookings', 59, '[]', '{\"family_id\":\"12\",\"sold_to\":\"19\",\"paid_to\":\"11\",\"name\":\"Matiullah\",\"passport_number\":\"P03241263\",\"flight_date\":\"\",\"return_date\":\"\",\"room_type\":\"Shared\",\"total_base_price\":100,\"total_sold_price\":120,\"total_profit\":20,\"services\":[{\"service_type\":\"all\",\"supplier_id\":\"27\",\"currency\":\"USD\",\"base_price\":\"100\",\"sold_price\":\"120\",\"profit\":20}],\"remarks\":\"Base amount of 100 USD deducted for umrah all.\",\"discount\":\"0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-27 12:13:27'),
(1728, 1, 1, 'add', 'umrah_bookings', 60, '[]', '{\"family_id\":\"12\",\"sold_to\":\"19\",\"paid_to\":\"11\",\"name\":\"Idrees\",\"passport_number\":\"P879876\",\"flight_date\":\"\",\"return_date\":\"\",\"room_type\":\"Shared\",\"total_base_price\":100,\"total_sold_price\":120,\"total_profit\":20,\"services\":[{\"service_type\":\"all\",\"supplier_id\":\"27\",\"currency\":\"USD\",\"base_price\":\"100\",\"sold_price\":\"120\",\"profit\":20}],\"remarks\":\"Base amount of 100 USD deducted for umrah all.\",\"discount\":\"0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-27 12:16:44'),
(1729, 1, 1, 'delete', 'umrah_bookings', 60, '{\"booking_id\":60,\"client_id\":19,\"services\":[{\"service_id\":6,\"supplier_id\":27,\"service_type\":\"\",\"base_price\":\"100.000\",\"sold_price\":\"120.000\",\"profit\":\"20.000\",\"currency\":\"USD\",\"supplier_type\":\"External\"}],\"paid_to\":11,\"currency\":\"USD\",\"client_type\":\"agency\",\"total_base_price\":\"100.000\",\"total_sold_price\":\"120.000\",\"total_profit\":\"20.000\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-27 12:27:27'),
(1730, 1, 1, 'add', 'umrah_bookings', 61, '[]', '{\"family_id\":\"12\",\"sold_to\":\"19\",\"paid_to\":\"11\",\"name\":\"DR SAHIB\",\"passport_number\":\"P00130999\",\"flight_date\":\"\",\"return_date\":\"\",\"room_type\":\"3 Beds\",\"total_base_price\":100,\"total_sold_price\":120,\"total_profit\":20,\"services\":[{\"service_type\":\"all\",\"supplier_id\":\"27\",\"currency\":\"USD\",\"base_price\":\"100\",\"sold_price\":\"120\",\"profit\":20}],\"remarks\":\"Base amount of 100 USD deducted for umrah all.\",\"discount\":\"0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-28 06:39:11'),
(1733, 1, 1, 'update_umrah_member', 'umrah_bookings', 61, '{\"sold_to\":19,\"family_id\":12,\"paid_to\":11,\"entry_date\":\"2025-09-28\",\"name\":\"DR SAHIB\",\"dob\":\"2025-09-22\",\"passport_number\":\"P00130999\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":\"0000-00-00\",\"return_date\":\"0000-00-00\",\"duration\":\"22 Days\",\"room_type\":\"3 Beds\",\"price\":\"100.000\",\"sold_price\":\"120.000\",\"profit\":\"20.000\",\"received_bank_payment\":\"0.000\",\"bank_receipt_number\":\"\",\"paid\":\"0.000\",\"due\":\"0.000\",\"discount\":\"0.000\"}', '{\"booking_id\":61,\"family_id\":12,\"suppliers\":{\"1\":{\"service_type\":\"all\",\"supplier_id\":\"27\",\"currency\":\"USD\",\"base_price\":\"110.000\",\"sold_price\":\"120.000\",\"profit\":\"10.00\"}},\"sold_to\":19,\"paid_to\":11,\"entry_date\":\"2025-09-28\",\"name\":\"DR SAHIB\",\"dob\":\"2025-09-22\",\"passport_number\":\"P00130999\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":null,\"return_date\":null,\"duration\":\"22 Days\",\"room_type\":\"3 Beds\",\"total_base_price\":110,\"total_sold_price\":120,\"total_profit\":10,\"received_bank_payment\":null,\"bank_receipt_number\":null,\"paid\":null,\"due\":120,\"gender\":\"Male\",\"passport_expiry\":\"2026-04-09\",\"remarks\":\"adfasdfasdf\",\"relation\":\"Cousin\",\"g_name\":\"HAJI MIR GHOUSUDDIN\",\"father_name\":\"RAHIMI\",\"discount\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-28 07:54:25'),
(1734, 1, 1, 'update_umrah_member', 'umrah_bookings', 61, '{\"sold_to\":19,\"family_id\":12,\"paid_to\":11,\"entry_date\":\"2025-09-28\",\"name\":\"DR SAHIB\",\"dob\":\"2025-09-22\",\"passport_number\":\"P00130999\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":null,\"return_date\":null,\"duration\":\"22 Days\",\"room_type\":\"3 Beds\",\"price\":\"110.000\",\"sold_price\":\"120.000\",\"profit\":\"10.000\",\"received_bank_payment\":null,\"bank_receipt_number\":null,\"paid\":null,\"due\":\"120.000\",\"discount\":\"0.000\"}', '{\"booking_id\":61,\"family_id\":12,\"suppliers\":{\"1\":{\"service_type\":\"all\",\"supplier_id\":\"27\",\"currency\":\"USD\",\"base_price\":\"110.000\",\"sold_price\":\"130.000\",\"profit\":\"20.00\"}},\"sold_to\":19,\"paid_to\":11,\"entry_date\":\"2025-09-28\",\"name\":\"DR SAHIB\",\"dob\":\"2025-09-22\",\"passport_number\":\"P00130999\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":null,\"return_date\":null,\"duration\":\"22 Days\",\"room_type\":\"3 Beds\",\"total_base_price\":110,\"total_sold_price\":130,\"total_profit\":20,\"received_bank_payment\":null,\"bank_receipt_number\":null,\"paid\":null,\"due\":130,\"gender\":\"Male\",\"passport_expiry\":\"2026-04-09\",\"remarks\":\"adfasdfasdf\",\"relation\":\"Cousin\",\"g_name\":\"HAJI MIR GHOUSUDDIN\",\"father_name\":\"RAHIMI\",\"discount\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-28 08:42:47'),
(1735, 1, 1, 'update_umrah_member', 'umrah_bookings', 61, '{\"sold_to\":19,\"family_id\":12,\"paid_to\":11,\"entry_date\":\"2025-09-28\",\"name\":\"DR SAHIB\",\"dob\":\"2025-09-22\",\"passport_number\":\"P00130999\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":null,\"return_date\":null,\"duration\":\"22 Days\",\"room_type\":\"3 Beds\",\"price\":\"110.000\",\"sold_price\":\"130.000\",\"profit\":\"20.000\",\"received_bank_payment\":null,\"bank_receipt_number\":null,\"paid\":null,\"due\":\"130.000\",\"discount\":\"0.000\"}', '{\"booking_id\":61,\"family_id\":12,\"suppliers\":{\"1\":{\"service_type\":\"all\",\"supplier_id\":\"27\",\"currency\":\"USD\",\"base_price\":\"110.000\",\"sold_price\":\"140.000\",\"profit\":\"30.00\"}},\"sold_to\":19,\"paid_to\":11,\"entry_date\":\"2025-09-28\",\"name\":\"DR SAHIB\",\"dob\":\"2025-09-22\",\"passport_number\":\"P00130999\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":null,\"return_date\":null,\"duration\":\"22 Days\",\"room_type\":\"3 Beds\",\"total_base_price\":110,\"total_sold_price\":140,\"total_profit\":30,\"received_bank_payment\":null,\"bank_receipt_number\":null,\"paid\":null,\"due\":140,\"gender\":\"Male\",\"passport_expiry\":\"2026-04-09\",\"remarks\":\"adfasdfasdf\",\"relation\":\"Cousin\",\"g_name\":\"HAJI MIR GHOUSUDDIN\",\"father_name\":\"RAHIMI\",\"discount\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-28 08:53:29'),
(1736, 1, 1, 'update_umrah_member', 'umrah_bookings', 61, '{\"sold_to\":19,\"family_id\":12,\"paid_to\":11,\"entry_date\":\"2025-09-28\",\"name\":\"DR SAHIB\",\"dob\":\"2025-09-22\",\"passport_number\":\"P00130999\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":null,\"return_date\":null,\"duration\":\"22 Days\",\"room_type\":\"3 Beds\",\"price\":\"110.000\",\"sold_price\":\"140.000\",\"profit\":\"30.000\",\"received_bank_payment\":null,\"bank_receipt_number\":null,\"paid\":null,\"due\":\"140.000\",\"discount\":\"0.000\"}', '{\"booking_id\":61,\"family_id\":12,\"suppliers\":{\"1\":{\"service_type\":\"all\",\"supplier_id\":\"27\",\"currency\":\"USD\",\"base_price\":\"120.000\",\"sold_price\":\"150.000\",\"profit\":\"30.00\"}},\"sold_to\":19,\"paid_to\":11,\"entry_date\":\"2025-09-28\",\"name\":\"DR SAHIB\",\"dob\":\"2025-09-22\",\"passport_number\":\"P00130999\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":null,\"return_date\":null,\"duration\":\"22 Days\",\"room_type\":\"3 Beds\",\"total_base_price\":120,\"total_sold_price\":150,\"total_profit\":30,\"received_bank_payment\":null,\"bank_receipt_number\":null,\"paid\":null,\"due\":150,\"gender\":\"Male\",\"passport_expiry\":\"2026-04-09\",\"remarks\":\"adfasdfasdf\",\"relation\":\"Cousin\",\"g_name\":\"HAJI MIR GHOUSUDDIN\",\"father_name\":\"RAHIMI\",\"discount\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-28 09:34:14'),
(1737, 1, 1, 'add', 'umrah_transactions', 84, '[]', '{\"umrah_booking_id\":61,\"transaction_to\":\"Internal Account\",\"payment_amount\":20,\"payment_currency\":\"USD\",\"payment_description\":\"dsfsa\",\"payment_date\":\"2025-09-28\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-28 09:40:35'),
(1738, 1, 1, 'add', 'umrah_transactions', 85, '[]', '{\"umrah_booking_id\":61,\"transaction_to\":\"Internal Account\",\"payment_amount\":10,\"payment_currency\":\"USD\",\"payment_description\":\"erdfgv\",\"payment_date\":\"2025-09-28\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-28 09:53:11'),
(1739, 1, 1, 'delete', 'umrah_bookings', 59, '{\"booking_id\":59,\"client_id\":19,\"services\":[{\"service_id\":5,\"supplier_id\":27,\"service_type\":\"all\",\"base_price\":\"100.000\",\"sold_price\":\"120.000\",\"profit\":\"20.000\",\"currency\":\"USD\",\"supplier_type\":\"External\"}],\"paid_to\":11,\"currency\":\"USD\",\"client_type\":\"agency\",\"total_base_price\":null,\"total_sold_price\":null,\"total_profit\":null}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-28 09:53:42'),
(1740, 1, 1, 'add', 'umrah_transactions', 86, '[]', '{\"umrah_booking_id\":61,\"transaction_to\":\"Internal Account\",\"payment_amount\":10,\"payment_currency\":\"USD\",\"payment_description\":\"adfdsf\",\"payment_date\":\"2025-09-28\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-28 09:58:15'),
(1741, 1, 1, 'add', 'umrah_transactions', 87, '[]', '{\"umrah_booking_id\":61,\"transaction_to\":\"Internal Account\",\"payment_amount\":10,\"payment_currency\":\"USD\",\"payment_description\":\"dghdf\",\"payment_date\":\"2025-09-28\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-28 10:00:25'),
(1742, 1, 1, 'add', 'umrah_transactions', 88, '[]', '{\"umrah_booking_id\":61,\"transaction_to\":\"Bank\",\"payment_amount\":10,\"payment_currency\":\"USD\",\"payment_description\":\"teted\",\"payment_date\":\"2025-09-28\",\"receipt_number\":\"2452345\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-28 10:01:22'),
(1743, 1, 1, 'add', 'umrah_transactions', 89, '[]', '{\"umrah_booking_id\":61,\"transaction_to\":\"Bank\",\"payment_amount\":10,\"payment_currency\":\"USD\",\"payment_description\":\"fstgsdf\",\"payment_date\":\"2025-09-28\",\"receipt_number\":\"6524652\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-28 10:03:21'),
(1744, 1, 1, 'update_umrah_member', 'umrah_bookings', 61, '{\"sold_to\":19,\"family_id\":12,\"paid_to\":11,\"entry_date\":\"2025-09-28\",\"name\":\"DR SAHIB\",\"dob\":\"2025-09-22\",\"passport_number\":\"P00130999\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":null,\"return_date\":null,\"duration\":\"22 Days\",\"room_type\":\"3 Beds\",\"price\":\"120.000\",\"sold_price\":\"150.000\",\"profit\":\"30.000\",\"received_bank_payment\":\"20.000\",\"bank_receipt_number\":\"6524652\",\"paid\":\"70.000\",\"due\":\"80.000\",\"discount\":\"0.000\"}', '{\"booking_id\":61,\"family_id\":12,\"suppliers\":{\"1\":{\"service_type\":\"all\",\"supplier_id\":\"27\",\"currency\":\"USD\",\"base_price\":\"130.000\",\"sold_price\":\"150.000\",\"profit\":\"20.00\"}},\"sold_to\":19,\"paid_to\":11,\"entry_date\":\"2025-09-28\",\"name\":\"DR SAHIB\",\"dob\":\"2025-09-22\",\"passport_number\":\"P00130999\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":null,\"return_date\":null,\"duration\":\"22 Days\",\"room_type\":\"3 Beds\",\"total_base_price\":130,\"total_sold_price\":150,\"total_profit\":20,\"received_bank_payment\":null,\"bank_receipt_number\":null,\"paid\":null,\"due\":150,\"gender\":\"Male\",\"passport_expiry\":\"2026-04-09\",\"remarks\":\"adfasdfasdf\",\"relation\":\"Cousin\",\"g_name\":\"HAJI MIR GHOUSUDDIN\",\"father_name\":\"RAHIMI\",\"discount\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-28 10:04:21'),
(1745, 1, 1, 'add', 'umrah_transactions', 90, '[]', '{\"umrah_booking_id\":61,\"transaction_to\":\"Internal Account\",\"payment_amount\":10,\"payment_currency\":\"USD\",\"payment_description\":\"vbscxv\",\"payment_date\":\"2025-09-28\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-28 10:15:18'),
(1746, 1, 1, 'add', 'umrah_transactions', 91, '[]', '{\"umrah_booking_id\":61,\"transaction_to\":\"Bank\",\"payment_amount\":10,\"payment_currency\":\"USD\",\"payment_description\":\"bv\",\"payment_date\":\"2025-09-28\",\"receipt_number\":\"217687\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-28 10:15:47'),
(1747, 1, 1, 'update_umrah_member', 'umrah_bookings', 61, '{\"sold_to\":19,\"family_id\":12,\"paid_to\":11,\"entry_date\":\"2025-09-28\",\"name\":\"DR SAHIB\",\"dob\":\"2025-09-22\",\"passport_number\":\"P00130999\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":null,\"return_date\":null,\"duration\":\"22 Days\",\"room_type\":\"3 Beds\",\"price\":\"130.000\",\"sold_price\":\"150.000\",\"profit\":\"20.000\",\"received_bank_payment\":\"10.000\",\"bank_receipt_number\":\"217687\",\"paid\":\"90.000\",\"due\":\"60.000\",\"discount\":\"0.000\"}', '{\"booking_id\":61,\"family_id\":12,\"suppliers\":{\"1\":{\"service_type\":\"all\",\"supplier_id\":\"27\",\"currency\":\"USD\",\"base_price\":\"130.000\",\"sold_price\":\"160.000\",\"profit\":\"30.00\"}},\"sold_to\":19,\"paid_to\":11,\"entry_date\":\"2025-09-28\",\"name\":\"DR SAHIB\",\"dob\":\"2025-09-22\",\"passport_number\":\"P00130999\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":null,\"return_date\":null,\"duration\":\"22 Days\",\"room_type\":\"3 Beds\",\"total_base_price\":130,\"total_sold_price\":160,\"total_profit\":30,\"received_bank_payment\":null,\"bank_receipt_number\":null,\"paid\":null,\"due\":160,\"gender\":\"Male\",\"passport_expiry\":\"2026-04-09\",\"remarks\":\"adfasdfasdf\",\"relation\":\"Cousin\",\"g_name\":\"HAJI MIR GHOUSUDDIN\",\"father_name\":\"RAHIMI\",\"discount\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-28 10:16:28'),
(1748, 1, 1, 'delete', 'umrah_transactions', 91, '{\"transaction_id\":91,\"umrah_id\":61,\"payment_amount\":10,\"currency\":\"USD\",\"transaction_to\":\"Bank\",\"payment_description\":\"bv\",\"is_refund\":false,\"supplier_id\":27,\"paid_to\":11}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-28 10:21:58'),
(1749, 1, 1, 'update_umrah_member', 'umrah_bookings', 61, '{\"sold_to\":19,\"family_id\":12,\"paid_to\":11,\"entry_date\":\"2025-09-28\",\"name\":\"DR SAHIB\",\"dob\":\"2025-09-22\",\"passport_number\":\"P00130999\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":null,\"return_date\":null,\"duration\":\"22 Days\",\"room_type\":\"3 Beds\",\"price\":\"130.000\",\"sold_price\":\"160.000\",\"profit\":\"30.000\",\"received_bank_payment\":\"0.000\",\"bank_receipt_number\":\"217687\",\"paid\":\"80.000\",\"due\":\"80.000\",\"discount\":\"0.000\"}', '{\"booking_id\":61,\"family_id\":12,\"suppliers\":{\"1\":{\"service_type\":\"all\",\"supplier_id\":\"27\",\"currency\":\"USD\",\"base_price\":\"130.000\",\"sold_price\":\"160.000\",\"profit\":\"30.00\"}},\"sold_to\":19,\"paid_to\":11,\"entry_date\":\"2025-09-28\",\"name\":\"DR SAHIB\",\"dob\":\"2025-09-22\",\"passport_number\":\"P00130999\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":\"2025-09-01\",\"return_date\":\"2025-09-15\",\"duration\":\"15 Days\",\"room_type\":\"3 Beds\",\"total_base_price\":130,\"total_sold_price\":160,\"total_profit\":30,\"received_bank_payment\":null,\"bank_receipt_number\":null,\"paid\":null,\"due\":160,\"gender\":\"Male\",\"passport_expiry\":\"2026-04-09\",\"remarks\":\"adfasdfasdf\",\"relation\":\"Cousin\",\"g_name\":\"HAJI MIR GHOUSUDDIN\",\"father_name\":\"RAHIMI\",\"discount\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-09-28 10:41:05');

-- --------------------------------------------------------

--
-- Table structure for table `additional_payments`
--

CREATE TABLE `additional_payments` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `payment_type` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `base_amount` decimal(15,2) NOT NULL,
  `sold_amount` decimal(15,2) NOT NULL,
  `profit` decimal(15,2) NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `main_account_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `receipt` varchar(100) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `is_from_supplier` tinyint(1) DEFAULT 0,
  `client_id` int(11) DEFAULT NULL,
  `is_for_client` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `additional_payments`
--

INSERT INTO `additional_payments` (`id`, `tenant_id`, `payment_type`, `description`, `base_amount`, `sold_amount`, `profit`, `currency`, `main_account_id`, `created_by`, `created_at`, `updated_at`, `receipt`, `supplier_id`, `is_from_supplier`, `client_id`, `is_for_client`) VALUES
(48, 1, 'Vacine', 'fdadfadsf', 100.00, 150.00, 50.00, 'USD', 11, 1, '2025-09-25 09:14:46', '2025-09-25 09:14:46', NULL, NULL, 1, NULL, 1),
(49, 1, 'Vacine', 'tatfdsf', 2000.00, 2500.00, 500.00, 'AFS', 11, 1, '2025-09-25 09:18:16', '2025-09-25 09:18:16', NULL, NULL, 1, NULL, 1);

-- --------------------------------------------------------

--
-- Table structure for table `assets`
--

CREATE TABLE `assets` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `category` varchar(100) NOT NULL,
  `purchase_date` date NOT NULL,
  `purchase_value` decimal(15,2) NOT NULL,
  `current_value` decimal(15,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `description` text DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `serial_number` varchar(100) DEFAULT NULL,
  `warranty_expiry` date DEFAULT NULL,
  `status` enum('active','inactive','maintenance','sold','disposed') NOT NULL DEFAULT 'active',
  `assigned_to` varchar(255) DEFAULT NULL,
  `condition_state` varchar(100) DEFAULT NULL,
  `document` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `assets`
--

INSERT INTO `assets` (`id`, `tenant_id`, `name`, `category`, `purchase_date`, `purchase_value`, `current_value`, `currency`, `description`, `location`, `serial_number`, `warranty_expiry`, `status`, `assigned_to`, `condition_state`, `document`, `created_at`, `updated_at`) VALUES
(1, 1, 'KamAir', 'Electronics', '2025-09-01', 100.00, 100.00, 'USD', 'test', 'Madina and Makkah', '2452345', '2025-10-08', 'disposed', 'office', 'New', '', '2025-09-01 09:59:11', '2025-09-01 10:00:18');

-- --------------------------------------------------------

--
-- Table structure for table `audit_logs`
--

CREATE TABLE `audit_logs` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(100) NOT NULL,
  `entity_type` varchar(50) NOT NULL,
  `entity_id` int(11) NOT NULL,
  `details` text DEFAULT NULL,
  `ip_address` varchar(45) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `created_at`) VALUES
(1, 1, 'create_tenant', 'tenant', 1, '{\"tenant_name\": \"Travel Agency Alpha\"}', '192.168.1.1', '2025-08-23 19:30:01'),
(2, 1, 'update_subscription', 'subscription', 1, '{\"plan_id\": \"basic\", \"status\": \"active\"}', '192.168.1.1', '2025-08-23 19:30:02'),
(3, 1, 'update_platform_setting', 'platform_setting', 1, '{\"key\": \"default_currency\", \"value\": \"USD\"}', '192.168.1.1', '2025-08-23 19:30:03'),
(4, 2, 'view_usage_report', 'tenant', 2, '{\"metric_type\": \"api_calls\", \"date\": \"2025-08-23\"}', '192.168.1.2', '2025-08-23 19:30:04'),
(5, 14, 'delete_tenant', 'tenant', 4, '{\"name\":\"Suspended Tours Delta\"}', '::1', '2025-08-26 11:12:34'),
(6, 14, 'create_tenant', 'tenant', 6, '{\"name\":\"KamAir\",\"subdomain\":\"mtravels\",\"identifier\":\"travelalmuqadas\",\"plan\":\"basic\"}', '::1', '2025-08-26 11:39:54'),
(7, 14, 'delete_tenant', 'tenant', 6, '{\"name\":\"KamAir\"}', '::1', '2025-08-26 11:41:22'),
(8, 14, 'update_platform_settings', 'platform_setting', 0, '{\"agency_name\":\"MTravels\",\"default_currency\":\"AFN\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"api_enabled\":\"true\",\"max_users_per_tenant\":\"20\",\"logo_updated\":true}', '::1', '2025-08-26 12:32:02'),
(9, 14, 'update_platform_settings', 'platform_setting', 0, '{\"agency_name\":\"MTravels\",\"default_currency\":\"AFN\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"api_enabled\":\"true\",\"max_users_per_tenant\":\"20\",\"logo_updated\":true}', '::1', '2025-08-26 12:37:54'),
(10, 14, 'update_platform_settings', 'platform_setting', 0, '{\"agency_name\":\"MTravels\",\"default_currency\":\"AFN\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"api_enabled\":\"true\",\"max_users_per_tenant\":\"20\",\"logo_updated\":true}', '::1', '2025-08-26 12:40:47'),
(11, 14, 'extend_subscription', 'subscription', 1, 'Extended subscription by 1 months', '', '2025-08-27 12:46:29'),
(12, 14, 'extend_subscription', 'subscription', 1, 'Extended subscription by 3 months', '', '2025-08-27 12:48:27'),
(13, 14, 'update_plan', 'plan', 0, '{\"old_name\":\"basic\",\"new_name\":\"basic\",\"description\":\"Basic plan with access to ticket-related tasks only\",\"price\":\"200\",\"max_users\":\"20\",\"trial_days\":\"10\",\"status\":\"active\"}', '::1', '2025-08-28 07:26:25'),
(14, 14, 'update_plan', 'plan', 0, '{\"old_name\":\"basic\",\"new_name\":\"basic\",\"description\":\"Basic plan with access to ticket-related tasks only\",\"price\":\"200.00\",\"max_users\":\"20\",\"trial_days\":\"10\",\"status\":\"active\"}', '::1', '2025-08-28 10:20:33'),
(15, 14, 'update_subscription', 'subscription', 5, '{\"tenant_id\":5,\"plan_id\":\"2\",\"status\":\"pending\",\"billing_cycle\":\"monthly\"}', '::1', '2025-08-30 07:39:31'),
(16, 14, 'update_plan', 'plan', 0, '{\"old_name\":\"basic\",\"new_name\":\"basic\",\"description\":\"Basic plan with access to ticket-related tasks only\",\"price\":\"200.00\",\"max_users\":\"20\",\"trial_days\":\"10\",\"status\":\"active\"}', '::1', '2025-08-30 07:40:19'),
(17, 14, 'update_tenant', 'tenant', 5, '{\"tenant_id\":5,\"name\":\"New Ventures Epsilon\",\"subdomain\":\"epsilon\",\"status\":\"active\"}', '::1', '2025-08-30 07:43:52'),
(18, 14, 'update_platform_settings', 'platform_setting', 0, '{\"agency_name\":\"MTravels\",\"default_currency\":\"AFN\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"api_enabled\":\"false\",\"max_users_per_tenant\":\"20\",\"logo_updated\":false}', '::1', '2025-08-30 07:56:11'),
(19, 14, 'update_tenant', 'tenant', 1, '{\"tenant_id\":1,\"name\":\"Travel Agency Alpha\",\"subdomain\":\"alpha\",\"status\":\"active\"}', '::1', '2025-09-01 12:25:47'),
(20, 14, 'update_tenant', 'tenant', 2, '{\"tenant_id\":2,\"name\":\"Global Tours Beta\",\"subdomain\":\"beta\",\"status\":\"active\"}', '::1', '2025-09-01 12:25:54'),
(21, 14, 'update_tenant', 'tenant', 5, '{\"tenant_id\":5,\"name\":\"New Ventures Epsilon\",\"subdomain\":\"epsilon\",\"status\":\"active\"}', '::1', '2025-09-01 12:26:00'),
(22, 14, 'update_subscription', 'subscription', 1, '{\"tenant_id\":1,\"plan_id\":\"2\",\"status\":\"active\",\"billing_cycle\":\"monthly\"}', '::1', '2025-09-01 12:46:02'),
(23, 14, 'update_subscription', 'subscription', 1, '{\"tenant_id\":1,\"plan_id\":\"3\",\"status\":\"active\",\"billing_cycle\":\"monthly\"}', '::1', '2025-09-01 12:46:15'),
(24, 14, 'update_plan', 'plan', 0, '{\"old_name\":\"enterprise\",\"new_name\":\"enterprise\",\"description\":\"Enterprise plan with all Pro features plus Umrah management\",\"price\":\"0.00\",\"max_users\":\"0\",\"trial_days\":\"0\",\"status\":\"active\"}', '::1', '2025-09-03 06:15:43'),
(25, 14, 'update_subscription', 'subscription', 2, '{\"tenant_id\":2,\"plan_id\":\"3\",\"status\":\"active\",\"billing_cycle\":\"yearly\"}', '::1', '2025-09-04 12:32:25'),
(26, 14, 'update_platform_settings', 'platform_setting', 0, '{\"platform_name\":\"MTravels\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"contact_email\":\"allahdadmuhammadi01@gmail.com\",\"website_url\":\"https:\\/\\/construct360.com\",\"contact_phone\":\"0780310431\",\"support_phone\":\"+93780310431\",\"contact_address\":\"Kabul,Afghanistan\",\"contact_facebook\":\"https:\\/\\/mtravels.com\",\"contact_twitter\":\"https:\\/\\/mtravels.com\",\"contact_linkedin\":\"https:\\/\\/mtravels.com\",\"contact_instagram\":\"https:\\/\\/mtravels.com\",\"default_currency\":\"AFN\",\"max_users_per_tenant\":\"20\",\"api_enabled\":\"false\",\"logo_updated\":false,\"favicon_updated\":false}', '::1', '2025-09-09 06:01:54'),
(27, 14, 'update_platform_settings', 'platform_setting', 0, '{\"platform_name\":\"MTravels\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"contact_email\":\"allahdadmuhammadi01@gmail.com\",\"website_url\":\"https:\\/\\/mtravels.com\",\"contact_phone\":\"0780310431\",\"support_phone\":\"+93780310431\",\"contact_address\":\"Kabul,Afghanistan\",\"contact_facebook\":\"https:\\/\\/mtravels.com\",\"contact_twitter\":\"https:\\/\\/mtravels.com\",\"contact_linkedin\":\"https:\\/\\/mtravels.com\",\"contact_instagram\":\"https:\\/\\/mtravels.com\",\"default_currency\":\"AFN\",\"max_users_per_tenant\":\"20\",\"api_enabled\":\"false\",\"logo_updated\":false,\"favicon_updated\":false}', '::1', '2025-09-09 06:02:09'),
(28, 14, 'update_platform_settings', 'platform_setting', 0, '{\"platform_name\":\"MTravels\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"contact_email\":\"allahdadmuhammadi01@gmail.com\",\"website_url\":\"https:\\/\\/mtravels.com\",\"contact_phone\":\"0780310431\",\"support_phone\":\"+93780310431\",\"contact_address\":\"Kabul,Afghanistan\",\"contact_facebook\":\"https:\\/\\/mtravels.com\",\"contact_twitter\":\"https:\\/\\/mtravels.com\",\"contact_linkedin\":\"https:\\/\\/mtravels.com\",\"contact_instagram\":\"https:\\/\\/mtravels.com\",\"default_currency\":\"AFN\",\"max_users_per_tenant\":\"20\",\"api_enabled\":\"false\",\"logo_updated\":false,\"favicon_updated\":false}', '::1', '2025-09-09 06:02:23'),
(29, 14, 'update_platform_settings', 'platform_setting', 0, '{\"platform_name\":\"MTravels\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"contact_email\":\"allahdadmuhammadi01@gmail.com\",\"website_url\":\"https:\\/\\/mtravels.com\",\"contact_phone\":\"0780310431\",\"support_phone\":\"+93780310431\",\"contact_address\":\"Kabul,Afghanistan\",\"contact_facebook\":\"https:\\/\\/mtravels.com\",\"contact_twitter\":\"https:\\/\\/mtravels.com\",\"contact_linkedin\":\"https:\\/\\/mtravels.com\",\"contact_instagram\":\"https:\\/\\/mtravels.com\",\"default_currency\":\"AFN\",\"max_users_per_tenant\":\"20\",\"api_enabled\":\"false\",\"logo_updated\":false,\"favicon_updated\":false}', '::1', '2025-09-09 06:05:17'),
(30, 14, 'update_platform_settings', 'platform_setting', 0, '{\"platform_name\":\"MTravels\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"contact_email\":\"allahdadmuhammadi01@gmail.com\",\"website_url\":\"https:\\/\\/mtravels.com\",\"contact_phone\":\"0780310431\",\"support_phone\":\"+93780310431\",\"contact_address\":\"Kabul,Afghanistan\",\"contact_facebook\":\"https:\\/\\/mtravels.com\",\"contact_twitter\":\"https:\\/\\/mtravels.com\",\"contact_linkedin\":\"https:\\/\\/mtravels.com\",\"contact_instagram\":\"https:\\/\\/mtravels.com\",\"default_currency\":\"AFN\",\"max_users_per_tenant\":\"20\",\"api_enabled\":\"false\",\"logo_updated\":false,\"favicon_updated\":false}', '::1', '2025-09-09 06:07:01'),
(31, 14, 'delete_tenant', 'tenant', 3, '{\"name\":\"Elite Pilgrimages Gamma\"}', '::1', '2025-09-09 06:52:26'),
(32, 14, 'delete_tenant', 'tenant', 5, '{\"name\":\"New Ventures Epsilon\"}', '::1', '2025-09-09 06:52:30'),
(33, 14, 'update_subscription', 'subscription', 1, '{\"tenant_id\":1,\"plan_id\":\"3\",\"status\":\"active\",\"billing_cycle\":\"monthly\"}', '::1', '2025-09-17 04:30:47'),
(34, 14, 'update_subscription', 'subscription', 2, '{\"tenant_id\":2,\"plan_id\":\"3\",\"status\":\"active\",\"billing_cycle\":\"yearly\"}', '::1', '2025-09-17 05:42:36'),
(35, 14, 'update_subscription', 'subscription', 2, '{\"tenant_id\":2,\"plan_id\":\"3\",\"status\":\"active\",\"billing_cycle\":\"yearly\"}', '::1', '2025-09-17 06:40:29'),
(36, 14, 'update_subscription', 'subscription', 1, '{\"subscription_id\":1,\"plan_id\":\"3\",\"status\":\"active\",\"billing_cycle\":\"monthly\"}', '::1', '2025-09-18 09:19:08'),
(37, 14, 'update_subscription', 'subscription', 1, '{\"subscription_id\":1,\"plan_id\":\"3\",\"status\":\"active\",\"billing_cycle\":\"monthly\"}', '::1', '2025-09-18 10:04:53'),
(38, 14, 'update_platform_settings', 'platform_setting', 0, '{\"platform_name\":\"MTravels\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"contact_email\":\"allahdadmuhammadi01@gmail.com\",\"website_url\":\"https:\\/\\/mtravels.com\",\"contact_phone\":\"0780310431\",\"support_phone\":\"+93780310431\",\"contact_address\":\"Kabul,Afghanistan\",\"contact_facebook\":\"https:\\/\\/mtravels.com\",\"contact_twitter\":\"https:\\/\\/mtravels.com\",\"contact_linkedin\":\"https:\\/\\/mtravels.com\",\"contact_instagram\":\"https:\\/\\/mtravels.com\",\"default_currency\":\"AFN\",\"max_users_per_tenant\":\"20\",\"api_enabled\":\"false\",\"logo_updated\":true,\"favicon_updated\":false}', '::1', '2025-10-02 11:52:07');

-- --------------------------------------------------------

--
-- Table structure for table `blog_posts`
--

CREATE TABLE `blog_posts` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `content` mediumtext DEFAULT NULL,
  `excerpt` mediumtext DEFAULT NULL,
  `featured_image` varchar(255) DEFAULT NULL,
  `author` varchar(255) DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `status` enum('draft','published') DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `budget_allocations`
--

CREATE TABLE `budget_allocations` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `main_account_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `allocated_amount` decimal(10,2) NOT NULL,
  `remaining_amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `allocation_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `created_by` int(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_messages`
--

CREATE TABLE `chat_messages` (
  `id` bigint(20) NOT NULL,
  `room_id` varchar(50) NOT NULL,
  `from_user_id` int(11) NOT NULL,
  `to_user_id` int(11) NOT NULL,
  `tenant_id_from` int(11) NOT NULL,
  `content` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `seen_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `room_id`, `from_user_id`, `to_user_id`, `tenant_id_from`, `content`, `created_at`, `seen_at`) VALUES
(1, 'u-1-6', 1, 6, 1, 'hi', '2025-09-03 10:57:21', '2025-09-04 10:38:32'),
(2, 'u-1-6', 6, 1, 1, 'heloo', '2025-09-03 10:59:46', '2025-09-04 12:01:07'),
(3, 'u-1-6', 1, 6, 1, 'hiy', '2025-09-03 11:11:09', '2025-09-04 10:38:32'),
(4, 'u-1-6', 1, 6, 1, 'hi', '2025-09-03 12:22:06', '2025-09-04 10:38:32'),
(5, 'u-1-6', 6, 1, 1, 'hi', '2025-09-03 12:42:15', '2025-09-04 12:01:07'),
(6, 'u-1-6', 1, 6, 1, '{\"type\":\"file\",\"name\":\"01.pdf\",\"size\":368254,\"mimeType\":\"application\\/pdf\",\"filePath\":\"file_68b83d78d693d_01.pdf\"}', '2025-09-03 13:07:04', '2025-09-04 10:38:32'),
(7, 'u-1-6', 1, 6, 1, 'adf', '2025-09-03 13:11:37', '2025-09-04 10:38:32'),
(8, 'u-1-6', 1, 6, 1, '{\"type\":\"file\",\"name\":\"01.pdf\",\"size\":368254,\"mimeType\":\"application\\/pdf\",\"filePath\":\"file_68b83e8e935f5_01.pdf\"}', '2025-09-03 13:11:42', '2025-09-04 10:38:32'),
(9, 'u-1-6', 1, 6, 1, 'adf', '2025-09-03 13:12:17', '2025-09-04 10:38:32'),
(10, 'u-1-6', 1, 6, 1, '{\"type\":\"file\",\"name\":\"Blue Modern Travel Poster Portrait.png\",\"size\":13431638,\"mimeType\":\"image\\/png\",\"filePath\":\"file_68b83eb814e5a_Blue_Modern_Travel_Poster_Portrait.png\"}', '2025-09-03 13:12:24', '2025-09-04 10:38:32'),
(11, 'u-1-6', 1, 6, 1, 'adf', '2025-09-04 03:42:12', '2025-09-04 10:38:32'),
(12, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"01.pdf\",\"size\":368254,\"mimeType\":\"application\\/pdf\",\"filePath\":\"file_68b90ab369be6_01.pdf\"}', '2025-09-04 03:42:43', '2025-09-04 12:01:07'),
(13, 'u-1-6', 1, 6, 1, '{\"type\":\"file\",\"name\":\"Blue and White Grunge Travel and Tourism Instagram Post.png\",\"size\":1030459,\"mimeType\":\"image\\/png\",\"filePath\":\"file_68b967379e0fe_Blue_and_White_Grunge_Travel_and_Tourism_Instagram_Post.png\"}', '2025-09-04 10:17:27', '2025-09-04 10:38:32'),
(14, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"Blue and White Modern Travel Instagram Post.png\",\"size\":1363535,\"mimeType\":\"image\\/png\",\"filePath\":\"file_68b9695fea986_Blue_and_White_Modern_Travel_Instagram_Post.png\"}', '2025-09-04 10:26:39', '2025-09-04 12:01:07'),
(15, 'u-1-6', 6, 1, 1, 'hi', '2025-09-04 11:21:34', '2025-09-04 12:01:07'),
(16, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"01.pdf\",\"size\":368254,\"mimeType\":\"application\\/pdf\",\"filePath\":\"file_68b97885ca8a6_01.pdf\"}', '2025-09-04 11:31:17', '2025-09-04 12:01:07'),
(17, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"01.pdf\",\"size\":368254,\"mimeType\":\"application\\/pdf\",\"filePath\":\"file_68b97b27811c2_01.pdf\"}', '2025-09-04 11:42:31', '2025-09-04 12:01:07'),
(18, 'u-1-6', 6, 1, 1, 'fa', '2025-09-04 11:42:42', '2025-09-04 12:01:07'),
(19, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"03.pdf\",\"size\":356854,\"mimeType\":\"application\\/pdf\",\"filePath\":\"file_68b97b39d3e1d_03.pdf\"}', '2025-09-04 11:42:49', '2025-09-04 12:01:07'),
(20, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"\\u0631\\u0633\\u06cc\\u062f \\u0628\\u0627\\u0646\\u06a9\\u06cc - Al Moqadas.pdf\",\"size\":196990,\"mimeType\":\"application\\/pdf\",\"filePath\":\"file_68b97b6e505d6_____________________-_Al_Moqadas.pdf\"}', '2025-09-04 11:43:42', '2025-09-04 12:01:07'),
(21, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"Blue Modern Travel Poster Portrait.png\",\"size\":13431638,\"mimeType\":\"image\\/png\",\"filePath\":\"file_68b97b73d9136_Blue_Modern_Travel_Poster_Portrait.png\"}', '2025-09-04 11:43:47', '2025-09-04 12:01:07'),
(22, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"03.pdf\",\"size\":356854,\"mimeType\":\"application\\/pdf\",\"filePath\":\"file_68b97c5c366e6_03.pdf\"}', '2025-09-04 11:47:40', '2025-09-04 12:01:07'),
(23, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"Yellow And Blue Modern Open Trip To England Instagram Post.png\",\"size\":722868,\"mimeType\":\"image\\/png\",\"filePath\":\"file_68b97fad61f31_Yellow_And_Blue_Modern_Open_Trip_To_England_Instagram_Post.png\"}', '2025-09-04 12:01:49', '2025-09-04 12:02:45'),
(24, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"aisalmot_travelagency (14).sql\",\"size\":2133029,\"mimeType\":\"text\\/plain\",\"filePath\":\"file_68b97fcfb0ed6_aisalmot_travelagency__14_.sql\"}', '2025-09-04 12:02:23', '2025-09-04 12:02:45'),
(25, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"03.pdf\",\"size\":356854,\"mimeType\":\"application\\/pdf\",\"filePath\":\"file_68b98187cb4ec_03.pdf\"}', '2025-09-04 12:09:43', '2025-09-04 12:17:06'),
(26, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"voice-1756988605345.webm\",\"size\":122843,\"mimeType\":\"video\\/webm\",\"filePath\":\"file_68b984bd583ae_voice-1756988605345.webm\"}', '2025-09-04 12:23:25', '2025-09-04 12:28:57'),
(27, 'u-1-7', 1, 7, 1, 'hello', '2025-09-04 12:58:00', '2025-09-04 12:58:13'),
(28, 'u-1-6', 1, 6, 1, 'heloo', '2025-09-08 08:27:42', '2025-09-08 08:29:06'),
(30, 'u-1-7', 1, 7, 1, '{\"type\":\"reply\",\"replyTo\":\"27\",\"replyText\":\"hello\",\"content\":\"fadf\"}', '2025-09-08 10:19:26', '2025-09-09 07:09:30'),
(31, 'u-1-6', 1, 6, 1, 'hello', '2025-09-08 10:23:11', '2025-09-08 11:52:31'),
(32, 'u-1-7', 1, 7, 1, 'hi', '2025-09-08 10:29:45', '2025-09-09 07:09:30'),
(34, 'u-1-6', 1, 6, 1, '{\"type\":\"reply\",\"replyTo\":\"18\",\"replyText\":\"fa\",\"content\":\"hi\"}', '2025-09-08 10:36:34', '2025-09-08 11:52:31'),
(35, 'u-1-7', 1, 7, 1, '{\"type\":\"file\",\"name\":\"Yellow And Blue Modern Open Trip To England Instagram Post.png\",\"size\":722868,\"mimeType\":\"image\\/png\",\"filePath\":\"file_68beb2d9b6d9b_Yellow_And_Blue_Modern_Open_Trip_To_England_Instagram_Post.png\"}', '2025-09-08 10:41:29', '2025-09-09 07:09:30'),
(36, 'u-1-6', 1, 6, 1, '{\"type\":\"reply\",\"replyTo\":\"23\",\"replyText\":\"📷 Photo\",\"content\":\"good\"}', '2025-09-08 10:41:54', '2025-09-08 11:52:31'),
(37, 'u-1-6', 1, 6, 1, '{\"type\":\"reply\",\"replyTo\":\"34\",\"replyText\":\"hi\",\"content\":\"helo\"}', '2025-09-08 10:53:42', '2025-09-08 11:52:31'),
(38, 'u-1-7', 1, 7, 1, 'helo', '2025-09-08 11:00:34', '2025-09-09 07:09:30');

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `image` varchar(100) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `usd_balance` decimal(10,3) DEFAULT 0.000,
  `afs_balance` decimal(10,3) DEFAULT 0.000,
  `address` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `client_type` enum('regular','agency') DEFAULT 'regular',
  `totp_enabled` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `tenant_id`, `image`, `name`, `email`, `password_hash`, `phone`, `usd_balance`, `afs_balance`, `address`, `created_at`, `updated_at`, `status`, `client_type`, `totp_enabled`) VALUES
(18, 1, '', 'DR SAHIBs', 'admin@abc-construction.com', '$2y$10$wNU7te9YSBzmM86Q3Z9D6eLp2SloDOyIz29TUmz/JDc9ZJMwT2EG.', '0777305730', -50.000, 0.000, 'Jada-e-Maiwand', '2025-09-01 10:44:22', '2025-09-23 08:17:50', 'active', 'regular', 0),
(19, 1, '', 'Walking Customers', 'almuqadas_travel@yahoo.com', '$2y$10$RCql5Ieq91Jkd8FaykSbKOxX/QNNJ9LD4jKJORz6Zkw9rlalh0qVS', '0777305730', 0.000, 0.000, 'Jada-e-Maiwand', '2025-09-07 08:42:27', '2025-09-22 05:36:50', 'active', 'agency', 0),
(20, 2, '', 'DR SAHIB', 'DRal@GMAIL.COM', '$2y$10$AgeoMovkpOzvOgWKT7b5tegnAHDaUcLYwr3SJ80aDsw0o20llzJrm', '0777305730', 0.000, 0.000, 'Jada-e-Maiwand', '2025-09-10 11:48:39', '2025-09-10 13:11:15', 'active', 'regular', 0),
(21, 2, '', 'walkings', 'esmati@gmail.com', '$2y$10$JWMeEbMm9oA4FxG9DhK6.uMNh.NLazftrApSj/oG3KPJcM1MikAJK', '0777305730', 0.000, 0.000, 'Jada-e-Maiwand', '2025-09-10 12:31:12', '2025-09-10 12:31:12', 'active', 'agency', 0);

-- --------------------------------------------------------

--
-- Table structure for table `client_transactions`
--

CREATE TABLE `client_transactions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `client_id` int(11) NOT NULL,
  `type` enum('credit','debit') NOT NULL,
  `amount` decimal(15,3) NOT NULL,
  `balance` decimal(15,3) NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `transaction_of` enum('ticket_sale','visa_sale','ticket_refund','date_change','fund','umrah','hotel','hotel_refund','ticket_reserve','jv_payment','additional_payment','visa_refund','hotel_refund','umrah_refund','additional_payment','weight_sale','umrah_date_change') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `receipt` int(100) NOT NULL,
  `exchange_rate` decimal(10,5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `client_transactions`
--

INSERT INTO `client_transactions` (`id`, `tenant_id`, `client_id`, `type`, `amount`, `balance`, `currency`, `description`, `created_at`, `transaction_of`, `reference_id`, `receipt`, `exchange_rate`) VALUES
(508, 1, 19, 'debit', 120.000, -120.000, 'USD', 'Ticket booked for Mr FAZALHAQ PARDES with PNR: 188JZ0 from MZR to FRA.', '2025-09-22 12:06:34', 'ticket_sale', 325, 0, NULL),
(512, 1, 19, 'debit', 15000.000, -15000.000, 'AFS', 'Ticket booked for Mr SHIRIN AGHA MUTAWAKIL with PNR: HAUPSE from  KBL to ISB.', '2025-09-23 03:54:20', 'ticket_sale', 329, 0, NULL),
(513, 1, 19, 'debit', 2000.000, -2000.000, 'AFS', 'Weight transaction: 20kg at 2000 AFS.', '2025-09-23 05:54:31', 'weight_sale', 12, 0, NULL),
(514, 1, 18, 'debit', 50.000, -50.000, 'USD', 'Ticket reservation for passenger BAKHTIAR STANIKZAI', '2025-09-23 08:17:50', 'ticket_reserve', 13, 0, NULL),
(515, 1, 19, 'debit', 50.000, -50.000, 'USD', 'Ticket reservation for passenger HAMID ACHAKZAI ', '2025-09-23 08:19:06', 'ticket_reserve', 14, 0, NULL),
(516, 1, 19, 'debit', 60.000, -60.000, 'AFS', 'Ticket reservation for passenger SIDDIQULALAH STANIKZAI', '2025-09-23 09:06:13', 'ticket_reserve', 15, 0, NULL),
(517, 1, 19, 'debit', 1000.000, -1000.000, 'USD', 'Client was debited for umrah booking for Matiullah', '2025-09-23 10:49:46', 'umrah', 55, 0, NULL),
(518, 1, 19, 'debit', 12000.000, -12000.000, 'AFS', 'Client was debited for umrah booking for Idrees', '2025-09-23 12:02:39', 'umrah', 56, 0, NULL),
(519, 1, 19, 'credit', 12000.000, 0.000, 'AFS', 'Refund for umrah booking #56 - utytt', '2025-09-25 10:11:27', 'umrah_refund', 21, 0, NULL),
(521, 1, 19, 'debit', 120.000, 0.000, 'USD', 'Visa booking for BAKHTIAR STANIKZAI', '2025-09-25 11:26:02', 'visa_sale', 53, 0, NULL),
(522, 1, 19, 'debit', 2500.000, 0.000, 'AFS', 'Visa booking for HAMID ACHAKZAI ', '2025-09-25 11:32:51', 'visa_sale', 54, 0, NULL),
(523, 1, 19, 'credit', 2500.000, 0.000, 'AFS', 'Refund for visa application #54 - test', '2025-09-25 11:39:24', 'visa_refund', 5, 0, NULL),
(524, 1, 19, 'credit', 5000.000, 0.000, 'AFS', 'Refund for hotel booking #41 - tets', '2025-09-27 05:22:48', 'hotel_refund', 9, 0, NULL),
(528, 1, 19, 'debit', -160.000, -120.000, 'USD', 'Updated: Updated: Client was debited for umrah booking for DR SAHIB', '2025-09-28 06:39:11', 'umrah', 61, 0, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `contact_messages`
--

CREATE TABLE `contact_messages` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `status` enum('unread','read','replied') NOT NULL DEFAULT 'unread'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `contact_messages`
--

INSERT INTO `contact_messages` (`id`, `name`, `email`, `subject`, `message`, `created_at`, `status`) VALUES
(1, 'NAVEED RASHIQ', 'RAHIMI107@GAMIL.COM', 'دریافت رسید', 'test', '2025-09-09 05:03:36', 'unread');

-- --------------------------------------------------------

--
-- Table structure for table `creditors`
--

CREATE TABLE `creditors` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` mediumtext DEFAULT NULL,
  `balance` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `creditors`
--

INSERT INTO `creditors` (`id`, `tenant_id`, `name`, `email`, `phone`, `address`, `balance`, `currency`, `status`, `created_at`) VALUES
(11, 1, 'NAVEED RASHIQ', 'RAHIMI107@GAMIL.COM', '0777305730', 'Jada-e-Maiwand', 100.00, 'USD', 'active', '2025-09-22 11:38:04');

-- --------------------------------------------------------

--
-- Table structure for table `creditor_transactions`
--

CREATE TABLE `creditor_transactions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `creditor_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `transaction_type` enum('debit','credit') NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `payment_date` date NOT NULL,
  `reference_number` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` mediumtext DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `tenant_id`, `name`, `email`, `phone`, `address`, `status`, `created_at`, `updated_at`) VALUES
(4, 1, 'NAVEED RASHIQ', 'RAHIMI107@GAMIL.COM', '0777305730', 'Jada-e-Maiwand', 'active', '2025-09-22 11:39:16', '2025-09-22 11:39:16');

-- --------------------------------------------------------

--
-- Table structure for table `customer_wallets`
--

CREATE TABLE `customer_wallets` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `balance` decimal(15,2) DEFAULT 0.00,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_wallets`
--

INSERT INTO `customer_wallets` (`id`, `tenant_id`, `customer_id`, `currency`, `balance`, `created_at`, `updated_at`) VALUES
(11, 1, 4, 'USD', 30.00, '2025-09-22 11:39:26', '2025-09-22 11:40:32');

-- --------------------------------------------------------

--
-- Table structure for table `date_change_tickets`
--

CREATE TABLE `date_change_tickets` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `supplier` varchar(255) NOT NULL,
  `sold_to` int(11) NOT NULL,
  `paid_to` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `passenger_name` varchar(255) NOT NULL,
  `pnr` varchar(255) NOT NULL,
  `origin` varchar(255) NOT NULL,
  `destination` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `airline` varchar(255) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `issue_date` date NOT NULL,
  `departure_date` date NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `sold` decimal(10,3) NOT NULL,
  `base` decimal(10,3) NOT NULL,
  `supplier_penalty` decimal(10,3) NOT NULL,
  `service_penalty` decimal(10,3) NOT NULL,
  `status` enum('Refunded','Pending','Declined') NOT NULL,
  `receipt` int(11) NOT NULL,
  `remarks` mediumtext NOT NULL,
  `created_by` int(50) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `date_change_tickets`
--

INSERT INTO `date_change_tickets` (`id`, `tenant_id`, `ticket_id`, `supplier`, `sold_to`, `paid_to`, `title`, `passenger_name`, `pnr`, `origin`, `destination`, `phone`, `airline`, `gender`, `issue_date`, `departure_date`, `currency`, `sold`, `base`, `supplier_penalty`, `service_penalty`, `status`, `receipt`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES
(46, 1, 329, '29', 19, 11, 'Mr', 'SHIRIN AGHA MUTAWAKIL', 'HAUPSE', ' KBL', 'ISB', '0771781576', 'FG', 'Male', '2025-09-23', '2025-09-24', 'AFS', 15000.000, 10000.000, 1000.000, 1000.000, '', 0, 'test', 1, '2025-09-23 10:24:12', '2025-09-23 10:24:12');

-- --------------------------------------------------------

--
-- Table structure for table `date_change_umrah`
--

CREATE TABLE `date_change_umrah` (
  `id` int(11) NOT NULL,
  `umrah_booking_id` int(11) NOT NULL,
  `family_id` int(11) NOT NULL,
  `supplier` int(11) DEFAULT NULL,
  `sold_to` int(11) DEFAULT NULL,
  `paid_to` int(11) DEFAULT NULL,
  `passenger_name` varchar(255) NOT NULL,
  `old_flight_date` date DEFAULT NULL,
  `new_flight_date` date DEFAULT NULL,
  `old_return_date` date DEFAULT NULL,
  `new_return_date` date DEFAULT NULL,
  `old_duration` varchar(50) DEFAULT NULL,
  `new_duration` varchar(50) DEFAULT NULL,
  `old_price` decimal(10,2) DEFAULT 0.00,
  `new_price` decimal(10,2) DEFAULT 0.00,
  `price_difference` decimal(10,2) DEFAULT 0.00,
  `supplier_penalty` decimal(10,2) DEFAULT 0.00,
  `service_penalty` decimal(10,2) DEFAULT 0.00,
  `total_penalty` decimal(10,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'USD',
  `exchange_rate` decimal(10,4) DEFAULT 1.0000,
  `status` enum('Pending','Approved','Rejected','Completed') DEFAULT 'Pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `processed_at` datetime DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `deals`
--

CREATE TABLE `deals` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `location` varchar(255) DEFAULT NULL,
  `duration` varchar(100) DEFAULT NULL,
  `old_price` decimal(10,2) DEFAULT NULL,
  `new_price` decimal(10,2) DEFAULT NULL,
  `discount` int(11) DEFAULT NULL,
  `start_date` date DEFAULT NULL,
  `end_date` date DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `debtors`
--

CREATE TABLE `debtors` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `address` mediumtext DEFAULT NULL,
  `balance` decimal(10,2) DEFAULT 0.00,
  `currency` varchar(10) DEFAULT 'USD',
  `status` enum('active','paid','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `main_account_id` int(11) DEFAULT NULL,
  `agreement_terms` mediumtext DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `debtors`
--

INSERT INTO `debtors` (`id`, `tenant_id`, `name`, `email`, `phone`, `address`, `balance`, `currency`, `status`, `created_at`, `updated_at`, `main_account_id`, `agreement_terms`) VALUES
(33, 1, 'NAVEED RASHIQ', 'RAHIMI107@GAMIL.COM', '0777305730', 'Jada-e-Maiwand', 100.00, 'USD', 'active', '2025-09-22 11:37:31', '2025-09-22 11:37:31', 11, '');

-- --------------------------------------------------------

--
-- Table structure for table `debtor_transactions`
--

CREATE TABLE `debtor_transactions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `debtor_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `transaction_type` enum('debit','credit') NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `reference_number` varchar(100) DEFAULT NULL,
  `payment_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `debtor_transactions`
--

INSERT INTO `debtor_transactions` (`id`, `tenant_id`, `debtor_id`, `amount`, `currency`, `transaction_type`, `description`, `reference_number`, `payment_date`, `created_at`) VALUES
(33, 1, 33, 100.00, 'USD', 'debit', 'Initial debt balance for NAVEED RASHIQ', 'DEBT-20250922133731-33', '2025-09-22', '2025-09-22 11:37:31');

-- --------------------------------------------------------

--
-- Table structure for table `debt_records`
--

CREATE TABLE `debt_records` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `due_date` date DEFAULT NULL,
  `status` enum('active','paid','overdue') DEFAULT 'active',
  `notes` mediumtext DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `demo_requests`
--

CREATE TABLE `demo_requests` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `company` varchar(255) NOT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `company_size` varchar(20) DEFAULT NULL,
  `preferred_date` date DEFAULT NULL,
  `preferred_time` time DEFAULT NULL,
  `message` text DEFAULT NULL,
  `status` enum('pending','contacted','scheduled','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `destinations`
--

CREATE TABLE `destinations` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `short_description` mediumtext DEFAULT NULL,
  `long_description` mediumtext DEFAULT NULL,
  `image` varchar(255) DEFAULT NULL,
  `featured` tinyint(1) DEFAULT 0,
  `category` varchar(50) DEFAULT NULL,
  `price` decimal(10,2) DEFAULT NULL,
  `rating` decimal(3,2) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exchange_rates`
--

CREATE TABLE `exchange_rates` (
  `id` int(11) NOT NULL,
  `tenant_id` int(10) NOT NULL,
  `from_currency` varchar(10) NOT NULL,
  `to_currency` varchar(10) NOT NULL,
  `rate` decimal(15,6) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exchange_transactions`
--

CREATE TABLE `exchange_transactions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `transaction_id` int(11) NOT NULL,
  `from_amount` decimal(15,2) NOT NULL,
  `from_currency` varchar(10) NOT NULL,
  `to_amount` decimal(15,2) NOT NULL,
  `to_currency` varchar(10) NOT NULL,
  `rate` decimal(15,6) NOT NULL,
  `profit_amount` decimal(15,2) DEFAULT NULL,
  `profit_currency` varchar(10) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expenses`
--

CREATE TABLE `expenses` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `main_account_id` int(20) NOT NULL,
  `date` date NOT NULL,
  `description` mediumtext NOT NULL,
  `amount` decimal(10,3) NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `allocation_id` int(11) DEFAULT NULL,
  `receipt_file` varchar(50) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expense_categories`
--

INSERT INTO `expense_categories` (`id`, `tenant_id`, `name`, `created_at`) VALUES
(18, 1, 'Office', '2025-09-01 10:55:48'),
(19, 1, 'OFFICE EXPS', '2025-09-01 11:17:05'),
(20, 2, 'wali', '2025-09-10 11:58:59');

-- --------------------------------------------------------

--
-- Table structure for table `families`
--

CREATE TABLE `families` (
  `family_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `head_of_family` varchar(100) DEFAULT NULL,
  `contact` varchar(50) DEFAULT NULL,
  `address` mediumtext DEFAULT NULL,
  `province` varchar(50) NOT NULL,
  `district` varchar(50) NOT NULL,
  `total_members` int(11) DEFAULT NULL,
  `package_type` varchar(100) DEFAULT NULL,
  `location` varchar(100) DEFAULT NULL,
  `tazmin` varchar(50) DEFAULT NULL,
  `visa_status` enum('Applied','Issued','Not Applied') NOT NULL DEFAULT 'Applied',
  `total_price` decimal(10,2) DEFAULT NULL,
  `total_paid` decimal(10,2) DEFAULT NULL,
  `total_paid_to_bank` decimal(10,2) DEFAULT NULL,
  `total_due` decimal(10,2) DEFAULT NULL,
  `created_by` int(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `families`
--

INSERT INTO `families` (`family_id`, `tenant_id`, `head_of_family`, `contact`, `address`, `province`, `district`, `total_members`, `package_type`, `location`, `tazmin`, `visa_status`, `total_price`, `total_paid`, `total_paid_to_bank`, `total_due`, `created_by`, `created_at`, `updated_at`) VALUES
(12, 1, 'Ali', '0777305730', 'Jada-e-Maiwand', 'Kabul', 'dehsabz', 2, 'Full Package', 'Madina and Makkah', 'Not Done', 'Not Applied', 1160.00, 457.62, 0.00, 702.38, 0, '2025-09-23 10:35:44', '2025-09-28 10:49:52'),
(13, 1, 'wali', '0777305730', 'Jada-e-Maiwand', 'LAGHMAN', 'CHAHAR ASIA', 1, 'Full Package', 'Madina and Makkah', 'Done', 'Not Applied', 12000.00, 9250.00, 0.00, 0.00, 0, '2025-09-23 12:01:54', '2025-09-25 10:11:27');

-- --------------------------------------------------------

--
-- Table structure for table `family_cancellations`
--

CREATE TABLE `family_cancellations` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `family_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `reason` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `funding_transactions`
--

CREATE TABLE `funding_transactions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `transaction_type` enum('credit','debit') NOT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `funded_by` int(10) NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `transaction_date` datetime DEFAULT current_timestamp(),
  `remarks` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `general_ledger`
--

CREATE TABLE `general_ledger` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `account_type` enum('asset','liability','income','expense') NOT NULL,
  `entry_type` enum('debit','credit') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `balance` decimal(15,2) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `general_ledger`
--

INSERT INTO `general_ledger` (`id`, `tenant_id`, `transaction_id`, `account_type`, `entry_type`, `amount`, `currency`, `balance`, `created_at`) VALUES
(3, 1, NULL, 'income', 'credit', 5.00, 'USD', 5.00, '2025-09-01 06:51:45'),
(4, 1, NULL, 'income', 'credit', 5.00, 'USD', 10.00, '2025-09-01 08:38:24'),
(5, 2, 34, 'asset', 'credit', 1000.00, 'USD', 1000.00, '2025-09-10 12:02:43'),
(6, 1, NULL, 'income', 'credit', 2.00, 'USD', 12.00, '2025-09-22 11:40:32');

-- --------------------------------------------------------

--
-- Table structure for table `hawala_transfers`
--

CREATE TABLE `hawala_transfers` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `sender_transaction_id` int(11) DEFAULT NULL,
  `receiver_transaction_id` int(11) DEFAULT NULL,
  `secret_code` varchar(50) DEFAULT NULL,
  `commission_amount` decimal(15,2) DEFAULT NULL,
  `commission_currency` varchar(10) DEFAULT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'pending',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hawala_transfers`
--

INSERT INTO `hawala_transfers` (`id`, `tenant_id`, `sender_transaction_id`, `receiver_transaction_id`, `secret_code`, `commission_amount`, `commission_currency`, `status`, `created_at`, `updated_at`) VALUES
(8, 1, 38, NULL, 'hi', 2.00, 'USD', 'pending', '2025-09-22 11:40:32', '2025-09-22 11:40:32');

-- --------------------------------------------------------

--
-- Table structure for table `hotel_bookings`
--

CREATE TABLE `hotel_bookings` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `title` enum('Mr','Mrs','Child') DEFAULT NULL,
  `first_name` varchar(100) DEFAULT NULL,
  `last_name` varchar(100) DEFAULT NULL,
  `gender` enum('Male','Female') DEFAULT NULL,
  `order_id` varchar(50) NOT NULL,
  `check_in_date` date NOT NULL,
  `check_out_date` date NOT NULL,
  `accommodation_details` mediumtext DEFAULT NULL,
  `issue_date` date DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `sold_to` varchar(100) DEFAULT NULL,
  `paid_to` int(100) NOT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `base_amount` decimal(10,3) DEFAULT NULL,
  `sold_amount` decimal(10,3) DEFAULT NULL,
  `profit` decimal(10,3) DEFAULT NULL,
  `currency` enum('USD','AFS') DEFAULT NULL,
  `remarks` mediumtext DEFAULT NULL,
  `receipt` varchar(100) NOT NULL,
  `created_by` int(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','refunded') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hotel_bookings`
--

INSERT INTO `hotel_bookings` (`id`, `tenant_id`, `title`, `first_name`, `last_name`, `gender`, `order_id`, `check_in_date`, `check_out_date`, `accommodation_details`, `issue_date`, `supplier_id`, `sold_to`, `paid_to`, `contact_no`, `base_amount`, `sold_amount`, `profit`, `currency`, `remarks`, `receipt`, `created_by`, `created_at`, `updated_at`, `status`) VALUES
(39, 1, 'Mr', 'NAVEED', 'RASHIQ', 'Male', '1089734', '2025-09-23', '2025-10-10', 't5et', '2025-09-23', 27, '19', 11, '777305730', 10.000, 60.000, 50.000, 'USD', 'test', '', 1, '2025-09-23 10:15:18', '2025-09-23 10:15:18', 'active'),
(41, 1, 'Mr', 'NAVEED1', 'RASHIQ1', 'Male', '108973', '2025-09-23', '2025-10-01', 'test', '2025-09-23', 29, '19', 11, '777305730', 1000.000, 5000.000, 0.000, 'AFS', 'test', '', 1, '2025-09-23 10:24:59', '2025-09-27 05:22:48', 'refunded');

-- --------------------------------------------------------

--
-- Table structure for table `hotel_refunds`
--

CREATE TABLE `hotel_refunds` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `refund_type` enum('full','partial') NOT NULL,
  `refund_amount` decimal(10,2) NOT NULL,
  `reason` text NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `exchange_rate` decimal(10,4) DEFAULT 1.0000,
  `processed` tinyint(1) DEFAULT 0,
  `processed_by` int(11) DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hotel_refunds`
--

INSERT INTO `hotel_refunds` (`id`, `tenant_id`, `booking_id`, `refund_type`, `refund_amount`, `reason`, `currency`, `exchange_rate`, `processed`, `processed_by`, `transaction_id`, `created_at`, `updated_at`) VALUES
(9, 1, 41, 'full', 5000.00, 'tets', 'AFS', 1.0000, 0, NULL, NULL, '2025-09-27 05:22:48', '2025-09-27 05:22:48');

-- --------------------------------------------------------

--
-- Table structure for table `invoices`
--

CREATE TABLE `invoices` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `invoice_number` varchar(50) NOT NULL,
  `client_id` int(11) NOT NULL,
  `type` enum('ticket','refund_ticket','date_change_ticket','visa') DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `main_account_id` int(11) NOT NULL,
  `total_amount` decimal(10,2) NOT NULL,
  `invoice_date` date NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jv_payments`
--

CREATE TABLE `jv_payments` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `jv_name` varchar(255) NOT NULL,
  `exchange_rate` decimal(10,5) NOT NULL DEFAULT 0.00000,
  `total_amount` decimal(15,3) NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `receipt` varchar(100) NOT NULL,
  `remarks` text DEFAULT NULL,
  `client_id` int(11) DEFAULT NULL,
  `supplier_id` int(11) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `jv_transactions`
--

CREATE TABLE `jv_transactions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `jv_payment_id` int(11) NOT NULL,
  `transaction_type` enum('Debit','Credit') NOT NULL,
  `amount` decimal(15,3) NOT NULL,
  `balance` decimal(15,3) NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `description` text NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `receipt` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `time` datetime NOT NULL,
  `ip_address` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `login_history`
--

CREATE TABLE `login_history` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `action` enum('login','logout') DEFAULT NULL,
  `action_time` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_history`
--

INSERT INTO `login_history` (`id`, `tenant_id`, `user_id`, `action`, `action_time`) VALUES
(82, 1, 1, 'logout', '2025-08-26 10:27:01'),
(83, 1, 1, 'logout', '2025-08-26 10:27:13'),
(84, 1, 1, 'logout', '2025-08-27 12:57:16'),
(86, 1, 1, 'logout', '2025-09-01 12:24:56'),
(87, 1, 1, 'logout', '2025-09-01 12:45:34'),
(88, 1, 1, 'logout', '2025-09-03 06:14:11'),
(89, 1, 1, 'logout', '2025-09-04 12:29:18'),
(90, 1, 6, 'logout', '2025-09-04 12:30:54'),
(91, 1, 1, 'logout', '2025-09-07 08:59:33'),
(92, 1, 6, 'logout', '2025-09-07 09:00:10'),
(93, 2, 7, 'logout', '2025-09-07 09:25:53'),
(94, 2, 7, 'logout', '2025-09-07 09:32:58'),
(95, 1, 1, 'logout', '2025-09-08 08:12:30'),
(96, 2, 7, 'logout', '2025-09-08 08:13:36'),
(97, 1, 6, 'logout', '2025-09-08 08:24:57'),
(98, 1, 1, 'logout', '2025-09-08 11:52:10'),
(99, 1, 6, 'logout', '2025-09-08 12:57:22'),
(100, 2, 7, 'logout', '2025-09-09 07:30:49'),
(101, 2, 7, 'logout', '2025-09-09 09:32:18'),
(102, 1, 1, 'logout', '2025-09-10 10:49:57'),
(103, 1, 1, 'logout', '2025-09-11 05:59:35'),
(104, 1, 1, 'logout', '2025-09-16 06:49:06'),
(105, 1, 6, 'logout', '2025-09-16 06:52:06'),
(107, 1, 1, 'logout', '2025-09-16 06:59:55'),
(109, 1, 1, 'logout', '2025-09-16 07:02:08'),
(111, 1, 1, 'logout', '2025-09-17 05:42:44'),
(112, 2, 7, 'logout', '2025-09-17 06:42:33'),
(113, 2, 7, 'logout', '2025-09-17 06:44:06'),
(114, 1, 1, 'logout', '2025-09-17 06:46:54'),
(115, 1, 1, 'logout', '2025-09-18 09:18:43');

-- --------------------------------------------------------

--
-- Table structure for table `main_account`
--

CREATE TABLE `main_account` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `account_type` enum('internal','bank') NOT NULL DEFAULT 'internal',
  `bank_account_number` varchar(50) DEFAULT NULL,
  `bank_account_afs_number` varchar(100) DEFAULT NULL,
  `bank_name` varchar(100) DEFAULT NULL,
  `usd_balance` decimal(15,3) NOT NULL DEFAULT 0.000,
  `afs_balance` decimal(15,3) NOT NULL DEFAULT 0.000,
  `euro_balance` decimal(10,3) NOT NULL,
  `darham_balance` decimal(10,3) NOT NULL,
  `last_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `main_account`
--

INSERT INTO `main_account` (`id`, `tenant_id`, `name`, `account_type`, `bank_account_number`, `bank_account_afs_number`, `bank_name`, `usd_balance`, `afs_balance`, `euro_balance`, `darham_balance`, `last_updated`, `status`) VALUES
(11, 1, 'AZIZI BANKadf', 'bank', '0325313513', '3254524afs453', 'azizi', 9271.000, 17930.000, 175.000, 660.000, '2025-09-28 14:45:18', 'active'),
(12, 2, 'SELF BANK (SAFE)', 'internal', NULL, NULL, NULL, 0.000, 0.000, 0.000, 0.000, '2025-09-22 10:09:36', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `main_account_transactions`
--

CREATE TABLE `main_account_transactions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `main_account_id` int(11) NOT NULL,
  `type` enum('credit','debit') NOT NULL,
  `amount` decimal(15,3) NOT NULL,
  `balance` decimal(15,3) NOT NULL,
  `currency` enum('USD','AFS','EUR','DARHAM') NOT NULL,
  `description` mediumtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `transaction_of` enum('ticket_sale','visa_sale','ticket_refund','date_change','fund','umrah','hotel','hotel_refund','expense','debtor','supplier_fund','client_fund','budget_allocation','ticket_reserve','transfer','additional_payment','creditor','jv_payment','salary_payment','visa_refund','hotel_refund','deposit_sarafi','hawala_sarafi','withdrawal_sarafi','umrah_refund','weight','supplier_fund_withdrawal') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `receipt` varchar(100) NOT NULL,
  `exchange_rate` decimal(10,5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `main_account_transactions`
--

INSERT INTO `main_account_transactions` (`id`, `tenant_id`, `main_account_id`, `type`, `amount`, `balance`, `currency`, `description`, `created_at`, `transaction_of`, `reference_id`, `receipt`, `exchange_rate`) VALUES
(1038, 1, 11, 'credit', 10000.000, 10000.000, 'USD', 'Account funded by Sabaoon. Remarks: sgfgsd. Receipt: safgsdf', '2025-09-22 11:35:33', 'fund', 1, 'safgsdf', NULL),
(1039, 1, 11, 'credit', 10000.000, 10000.000, 'AFS', 'Account funded by Sabaoon. Remarks: gfdgsdf. Receipt: t43t4tw', '2025-09-22 11:35:49', 'fund', 1, 't43t4tw', NULL),
(1040, 1, 11, 'debit', 1000.000, 9000.000, 'USD', 'Supplier: KamAir, Funded by main account: AZIZI BANKadf, processed by: Sabaoon, Remarks: adsfdsaf', '2025-09-22 11:36:15', 'supplier_fund', 828, '2452345', NULL),
(1041, 1, 11, 'debit', 1000.000, 9000.000, 'AFS', 'Supplier: Ariana, Funded by main account: AZIZI BANKadf, processed by: Sabaoon, Remarks: adfadsf', '2025-09-22 11:36:38', 'supplier_fund', 829, 'adfadsf', NULL),
(1042, 1, 11, 'debit', 100.000, 8900.000, 'USD', 'Initial debt balance for NAVEED RASHIQ', '2025-09-22 11:37:31', 'debtor', 33, 'DEBT-20250922133731-33', NULL),
(1043, 1, 11, 'credit', 100.000, 9000.000, 'USD', 'Initial credit balance for creditor: NAVEED RASHIQ', '2025-09-22 11:38:04', 'creditor', 11, '', NULL),
(1044, 1, 11, 'credit', 100.000, 9100.000, 'USD', '', '2025-09-22 11:39:26', 'deposit_sarafi', 36, 'DEP68d1356497ef4', NULL),
(1045, 1, 11, 'debit', 50.000, 9050.000, 'USD', '', '2025-09-22 11:39:48', 'withdrawal_sarafi', 37, 'WDR68d1356e6b372', NULL),
(1046, 1, 11, 'debit', 18.000, 9032.000, 'USD', 'fdsafd', '2025-09-22 11:40:32', 'hawala_sarafi', 38, 'HWL68d135b0bb0ad', NULL),
(1047, 1, 11, 'debit', 10000.000, -1000.000, 'AFS', 'adfads', '2025-09-22 11:42:52', 'salary_payment', 21, 'SP20250922134252-1', NULL),
(1048, 1, 11, 'credit', 1000.000, 0.000, 'AFS', 'test | Additional Remarks: test', '2025-09-22 12:13:05', 'ticket_sale', 325, '45324', 70.00000),
(1049, 1, 11, 'credit', 20.000, 9052.000, 'USD', 'dfdsa | Additional Remarks: test', '2025-09-22 12:15:49', 'ticket_sale', 325, '34142', NULL),
(1050, 1, 11, 'credit', 20.000, 20.000, 'EUR', 'fdsafds | Additional Remarks: test', '2025-09-22 12:16:04', 'ticket_sale', 325, '6524652', 0.90000),
(1051, 1, 11, 'credit', 100.000, 100.000, 'DARHAM', 'vzdfvdcx | Additional Remarks: test', '2025-09-22 12:28:50', 'ticket_sale', 325, '2452345', 3.61000),
(1052, 1, 11, 'credit', 1000.000, 1000.000, 'AFS', 'dsfsdf | Additional Remarks: test', '2025-09-23 03:54:20', 'ticket_sale', 329, '245234', NULL),
(1053, 1, 11, 'credit', 50.000, 9102.000, 'USD', 'dfads | Additional Remarks: test', '2025-09-23 03:55:12', 'ticket_sale', 329, '45324', 70.00000),
(1054, 1, 11, 'credit', 50.000, 70.000, 'EUR', 'dsfasd | Additional Remarks: test', '2025-09-23 03:55:25', 'ticket_sale', 329, '34142', 77.00000),
(1055, 1, 11, 'credit', 200.000, 300.000, 'DARHAM', 'dfsafds | Additional Remarks: test', '2025-09-23 03:55:35', 'ticket_sale', 329, '6524652', 18.50000),
(1056, 1, 11, 'credit', 2950.000, 3950.000, 'AFS', 'fadf | Additional Remarks: test', '2025-09-23 03:57:42', 'ticket_sale', 329, '2452345', NULL),
(1057, 1, 11, 'debit', 10.000, 9092.000, 'USD', 'test | Additional Remarks: test', '2025-09-23 05:54:47', 'ticket_refund', 96, '45324', 70.00000),
(1058, 1, 11, 'debit', 1000.000, 2950.000, 'AFS', 'test | Additional Remarks: test', '2025-09-23 05:57:33', 'ticket_refund', 96, '34142', 0.00000),
(1059, 1, 11, 'debit', 10.000, 2940.000, 'EUR', 'test | Additional Remarks: test', '2025-09-23 05:58:01', 'ticket_refund', 96, '6524652', 77.00000),
(1060, 1, 11, 'debit', 100.000, 2840.000, 'DARHAM', 'tets | Additional Remarks: test', '2025-09-23 05:58:27', 'ticket_refund', 96, '2452345', 18.50000),
(1065, 1, 11, 'credit', 2.000, 9094.000, 'USD', 'test (Exchange Rate: 70) | Additional Remarks: d', '2025-09-23 07:25:35', 'date_change', 46, 'du', 70.00000),
(1066, 1, 11, 'credit', 5.000, 75.000, 'EUR', 'test (Exchange Rate: 77) | Additional Remarks: d', '2025-09-23 07:25:58', 'date_change', 46, 'de', 77.00000),
(1067, 1, 11, 'credit', 10.000, 310.000, 'DARHAM', 'test (Exchange Rate: 18) | Additional Remarks: test', '2025-09-23 07:26:24', 'date_change', 46, 'dd', 18.00000),
(1068, 1, 11, 'credit', 1000.000, 3840.000, 'AFS', 'test | Additional Remarks: test', '2025-09-23 07:26:53', 'date_change', 46, 'd', 0.00000),
(1069, 1, 11, 'credit', 2.000, 9096.000, 'USD', ' | Additional Remarks: dd', '2025-09-23 07:35:00', 'weight', 12, 'du', 70.00000),
(1070, 1, 11, 'credit', 5.000, 80.000, 'EUR', 'tet | Additional Remarks: d', '2025-09-23 07:36:00', 'weight', 12, 'de', 77.00000),
(1071, 1, 11, 'credit', 1000.000, 4840.000, 'AFS', 'test | Additional Remarks: d', '2025-09-23 07:36:00', 'weight', 12, 'da', NULL),
(1072, 1, 11, 'credit', 10.000, 320.000, 'DARHAM', 'test | Additional Remarks: d', '2025-09-23 07:37:00', 'weight', 12, 'dd', 18.00000),
(1073, 1, 11, 'credit', 5.000, 9101.000, 'USD', 'teste | Additional Remarks: test', '2025-09-23 08:19:39', 'ticket_reserve', 14, '45324', NULL),
(1074, 1, 11, 'credit', 5.000, 85.000, 'EUR', 'test (Exchange Rate: 0.9) | Additional Remarks: test', '2025-09-23 08:19:50', 'ticket_reserve', 14, '34142', 0.90000),
(1075, 1, 11, 'credit', 1000.000, 5840.000, 'AFS', 'test (Exchange Rate: 70) | Additional Remarks: test', '2025-09-23 08:20:05', 'ticket_reserve', 14, '6524652', 70.00000),
(1076, 1, 11, 'credit', 100.000, 420.000, 'DARHAM', 'test (Exchange Rate: 3.61) | Additional Remarks: test', '2025-09-23 08:20:22', 'ticket_reserve', 14, '2452345', 3.61000),
(1077, 1, 11, 'credit', 500.000, 6340.000, 'AFS', 'test | Additional Remarks: ', '2025-09-23 09:11:29', 'ticket_reserve', 15, '11', NULL),
(1078, 1, 11, 'credit', 5.000, 9106.000, 'USD', 'test (Exchange Rate: 70) | Additional Remarks: ', '2025-09-23 09:12:06', 'ticket_reserve', 15, '9', 70.00000),
(1079, 1, 11, 'credit', 10.000, 95.000, 'EUR', 'test (Exchange Rate: 77) | Additional Remarks: ', '2025-09-23 09:12:18', 'ticket_reserve', 15, '8', 77.00000),
(1080, 1, 11, 'credit', 10.000, 430.000, 'DARHAM', 'test (Exchange Rate: 18.5) | Additional Remarks: ', '2025-09-23 09:12:35', 'ticket_reserve', 15, '7', 18.50000),
(1081, 1, 11, 'credit', 5.000, 9111.000, 'USD', 'test | Additional Remarks: ', '2025-09-23 10:22:46', 'hotel', 39, '6', NULL),
(1082, 1, 11, 'credit', 5.000, 6345.000, 'EUR', 'tet | Additional Remarks: ', '2025-09-23 10:23:07', 'hotel', 39, '5', 0.90000),
(1083, 1, 11, 'credit', 1000.000, 7345.000, 'AFS', 'test | Additional Remarks: ', '2025-09-23 10:23:29', 'hotel', 39, '4', 77.00000),
(1084, 1, 11, 'credit', 100.000, 7445.000, 'DARHAM', 'test | Additional Remarks: ', '2025-09-23 10:23:47', 'hotel', 39, '3', 3.61000),
(1085, 1, 11, 'credit', 5.000, 9116.000, 'USD', 'test | Additional Remarks: ', '2025-09-23 10:29:38', 'hotel', 41, '2', 70.00000),
(1086, 1, 11, 'credit', 5.000, 7450.000, 'EUR', 'test | Additional Remarks: ', '2025-09-23 10:29:54', 'hotel', 41, '1', 77.00000),
(1087, 1, 11, 'credit', 1000.000, 8450.000, 'AFS', 'tets | Additional Remarks: ', '2025-09-23 10:30:30', 'hotel', 41, '6524652', NULL),
(1088, 1, 11, 'credit', 100.000, 8550.000, 'DARHAM', 'test | Additional Remarks: ', '2025-09-23 10:31:01', 'hotel', 41, '2452345', 18.00000),
(1089, 1, 11, 'credit', 100.000, 9216.000, 'USD', 'test', '2025-09-23 10:50:34', 'umrah', 76, '', NULL),
(1090, 1, 11, 'credit', 7000.000, 15550.000, 'AFS', 'test', '2025-09-23 10:50:52', 'umrah', 77, '', 70.00000),
(1091, 1, 11, 'credit', 110.000, 15660.000, 'EUR', '', '2025-09-23 10:51:19', 'umrah', 78, '', 0.90000),
(1092, 1, 11, 'credit', 200.000, 15860.000, 'DARHAM', 'tets', '2025-09-23 10:51:56', 'umrah', 79, '', 3.61000),
(1093, 1, 11, 'credit', 1000.000, 16860.000, 'AFS', 'test', '2025-09-23 12:04:41', 'umrah', 80, '', NULL),
(1094, 1, 11, 'credit', 50.000, 9266.000, 'USD', 'test', '2025-09-23 12:10:09', 'umrah', 81, '', 70.00000),
(1095, 1, 11, 'credit', 50.000, 16910.000, 'EUR', 'test', '2025-09-23 12:11:12', 'umrah', 82, '', 77.00000),
(1096, 1, 11, 'credit', 50.000, 16960.000, 'DARHAM', 'test', '2025-09-23 12:12:23', 'umrah', 83, '', 18.00000),
(1097, 1, 11, 'credit', 20.000, 9286.000, 'USD', 'test', '2025-09-25 09:14:57', 'additional_payment', 48, '', 0.00000),
(1098, 1, 11, 'credit', 1000.000, 17960.000, 'AFS', 'test', '2025-09-25 09:14:57', 'additional_payment', 48, '', 70.00000),
(1099, 1, 11, 'credit', 100.000, 530.000, 'DARHAM', 'test', '2025-09-25 09:14:57', 'additional_payment', 48, '', 3.61000),
(1100, 1, 11, 'credit', 50.000, 145.000, 'EUR', 'test', '2025-09-25 09:14:57', 'additional_payment', 48, '', 0.90000),
(1101, 1, 11, 'credit', 500.000, 18460.000, 'AFS', 'test', '2025-09-25 09:18:21', 'additional_payment', 49, '', 0.00000),
(1102, 1, 11, 'credit', 10.000, 9296.000, 'USD', 'test', '2025-09-25 09:18:21', 'additional_payment', 49, '', 70.00000),
(1103, 1, 11, 'credit', 10.000, 155.000, 'EUR', 'test', '2025-09-25 09:18:21', 'additional_payment', 49, '', 77.00000),
(1104, 1, 11, 'credit', 10.000, 540.000, 'DARHAM', 'test', '2025-09-25 09:18:21', 'additional_payment', 49, '', 18.50000),
(1105, 1, 11, 'debit', 1000.000, 17460.000, 'AFS', 'Refund payment for Umrah Booking #56 - Idrees', '2025-09-25 10:14:25', 'umrah_refund', 21, '', 0.00000),
(1106, 1, 11, 'debit', 100.000, 9196.000, 'USD', 'test (Exchange Rate: 70.00000) | Additional Remarks: This is a visa', '2025-09-25 10:14:41', 'umrah_refund', 21, '2452345', 70.00000),
(1107, 1, 11, 'debit', 5.000, 150.000, 'EUR', 'Refund payment for Umrah Booking #56 - Idrees | Additional Remarks: test', '2025-09-25 10:25:40', 'umrah_refund', 21, '2452345', 77.00000),
(1109, 1, 11, 'credit', 1000.000, 18460.000, 'AFS', 'test', '2025-09-25 11:28:03', 'visa_sale', 53, '', 70.00000),
(1110, 1, 11, 'credit', 20.000, 9216.000, 'USD', 'test', '2025-09-25 11:28:55', 'visa_sale', 53, '', 0.00000),
(1111, 1, 11, 'credit', 20.000, 170.000, 'EUR', 'test', '2025-09-25 11:29:08', 'visa_sale', 53, '', 0.90000),
(1112, 1, 11, 'credit', 100.000, 640.000, 'DARHAM', 'test', '2025-09-25 11:29:40', 'visa_sale', 53, '', 3.60000),
(1113, 1, 11, 'credit', 500.000, 18960.000, 'AFS', 'afadsf', '2025-09-25 11:32:53', 'visa_sale', 54, '', 0.00000),
(1114, 1, 11, 'credit', 5.000, 9221.000, 'USD', 'test', '2025-09-25 11:35:57', 'visa_sale', 54, '', 70.00000),
(1115, 1, 11, 'credit', 5.000, 175.000, 'EUR', 'tae', '2025-09-25 11:36:11', 'visa_sale', 54, '', 77.00000),
(1116, 1, 11, 'credit', 20.000, 660.000, 'DARHAM', 'adfd | Additional Remarks: test', '2025-09-25 11:36:25', 'visa_sale', 54, '2452345', 18.50000),
(1117, 1, 11, 'debit', 500.000, 18460.000, 'AFS', 'Refund payment for Visa Application #54 - HAMID ACHAKZAI ', '2025-09-25 11:53:53', 'visa_refund', 5, '', 0.00000),
(1118, 1, 11, 'debit', 5.000, 9216.000, 'USD', 'test', '2025-09-25 11:54:20', 'visa_refund', 5, '', 70.00000),
(1119, 1, 11, 'debit', 5.000, 18455.000, 'EUR', 'Refund payment for Visa Application #54 - HAMID ACHAKZAI  | Additional Remarks: test', '2025-09-25 11:56:05', 'visa_refund', 5, '2452345', 77.00000),
(1120, 1, 11, 'debit', 10.000, 18445.000, 'DARHAM', 'xdfg | Additional Remarks: This is a visa', '2025-09-25 11:56:25', 'visa_refund', 5, '2452345', 18.50000),
(1121, 1, 11, 'debit', 500.000, 17945.000, 'AFS', 'Refund payment for Hotel Booking #41 - Mr NAVEED1 RASHIQ1', '2025-09-27 06:49:22', 'hotel_refund', 9, '', NULL),
(1122, 1, 11, 'debit', 5.000, 9211.000, 'USD', 'adsfadsf', '2025-09-27 06:49:41', 'hotel_refund', 9, '', 70.00000),
(1123, 1, 11, 'debit', 5.000, 17940.000, 'EUR', 'safdfas', '2025-09-27 06:49:55', 'hotel_refund', 9, '', 77.00000),
(1124, 1, 11, 'debit', 10.000, 17930.000, 'DARHAM', 'sdfds | Additional Remarks: test', '2025-09-27 06:50:09', 'hotel_refund', 9, '2452345', 18.50000),
(1125, 1, 11, 'credit', 20.000, 9231.000, 'USD', 'dsfsa', '2025-09-28 09:40:35', 'umrah', 84, '', NULL),
(1126, 1, 11, 'credit', 10.000, 9241.000, 'USD', 'erdfgv', '2025-09-28 09:53:11', 'umrah', 85, '', NULL),
(1127, 1, 11, 'credit', 10.000, 9251.000, 'USD', 'adfdsf', '2025-09-28 09:58:15', 'umrah', 86, '', NULL),
(1128, 1, 11, 'credit', 10.000, 9261.000, 'USD', 'dghdf', '2025-09-28 10:00:25', 'umrah', 87, '', NULL),
(1129, 1, 11, 'credit', 10.000, 9271.000, 'USD', 'vbscxv', '2025-09-28 10:15:18', 'umrah', 90, '', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `maktobs`
--

CREATE TABLE `maktobs` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `maktob_number` varchar(50) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `content` text NOT NULL,
  `company_name` varchar(255) NOT NULL,
  `maktob_date` date NOT NULL,
  `sender_id` int(11) NOT NULL,
  `status` enum('draft','sent') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `language` varchar(10) DEFAULT 'english',
  `file_path` varchar(255) DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `messages`
--

CREATE TABLE `messages` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `subject` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `sender_id` int(11) NOT NULL,
  `recipient_type` enum('all','users','clients','individual') NOT NULL,
  `recipient_id` int(11) DEFAULT NULL,
  `recipient_table` enum('users','clients') DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `status` enum('unread','read') NOT NULL DEFAULT 'unread',
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `transaction_type` enum('visa','supplier','ticket_date_change','ticket_refund','umrah','hotel','hotel_refund','ticket_sale','ticket_reserve','additional_payment','debtor','creditor','deposit_sarafi','hawala_sarafi','withdrawal_sarafi','supplier_fund','client_fund','weight','expense','expense_update','expense_delete','supplier_bonus','umrah_refund','hotel_refund','visa_refund','supplier_fund_withdrawal','mtravels') NOT NULL DEFAULT 'supplier',
  `message` mediumtext NOT NULL,
  `recipient_role` enum('Admin','Sales','Finance') NOT NULL,
  `status` enum('Unread','Read') DEFAULT 'Unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `tenant_id`, `transaction_id`, `transaction_type`, `message`, `recipient_role`, `status`, `created_at`) VALUES
(854, 1, 828, 'supplier_fund', 'Supplier: KamAir, Funded 1000 USD by main account: AZIZI BANKadf, processed by: Sabaoon, Remarks: adsfdsaf', 'Admin', 'Read', '2025-09-22 11:36:15'),
(855, 1, 829, 'supplier_fund', 'Supplier: Ariana, Funded 1000 AFS by main account: AZIZI BANKadf, processed by: Sabaoon, Remarks: adfadsf', 'Admin', 'Read', '2025-09-22 11:36:38'),
(856, 1, 1044, 'deposit_sarafi', 'New deposit from NAVEED RASHIQ: USD 100 - Reference: DEP68d1356497ef4', 'Admin', 'Read', '2025-09-22 11:39:26'),
(857, 1, 1045, 'withdrawal_sarafi', 'New withdrawal by NAVEED RASHIQ: USD 50 - Reference: WDR68d1356e6b372', 'Admin', 'Read', '2025-09-22 11:39:48'),
(858, 1, 1046, 'hawala_sarafi', 'new_hawala_transfer_notification', 'Admin', 'Read', '2025-09-22 11:40:32'),
(859, 1, 1048, 'ticket_sale', 'New payment received for ticket booking #188JZ0 - Mr FAZALHAQ PARDES: Amount AFS 1000.00', 'Admin', 'Read', '2025-09-22 12:13:50'),
(860, 1, 1049, 'ticket_sale', 'New payment received for ticket booking #188JZ0 - Mr FAZALHAQ PARDES: Amount USD 20.00', 'Admin', 'Read', '2025-09-22 12:16:04'),
(861, 1, 1050, 'ticket_sale', 'New payment received for ticket booking #188JZ0 - Mr FAZALHAQ PARDES: Amount EUR 20.00', 'Admin', 'Read', '2025-09-22 12:16:19'),
(862, 1, 1051, 'ticket_sale', 'New payment received for ticket booking #188JZ0 - Mr FAZALHAQ PARDES: Amount DARHAM 100.00', 'Admin', 'Read', '2025-09-22 12:29:33'),
(863, 1, 1052, 'ticket_sale', 'New payment received for ticket booking #HAUPSE - Mr SHIRIN AGHA MUTAWAKIL: Amount AFS 1000.00', 'Admin', 'Read', '2025-09-23 03:55:12'),
(864, 1, 1053, 'ticket_sale', 'New payment received for ticket booking #HAUPSE - Mr SHIRIN AGHA MUTAWAKIL: Amount USD 50.00', 'Admin', 'Read', '2025-09-23 03:55:25'),
(865, 1, 1054, 'ticket_sale', 'New payment received for ticket booking #HAUPSE - Mr SHIRIN AGHA MUTAWAKIL: Amount EUR 50.00', 'Admin', 'Read', '2025-09-23 03:55:35'),
(866, 1, 1055, 'ticket_sale', 'New payment received for ticket booking #HAUPSE - Mr SHIRIN AGHA MUTAWAKIL: Amount DARHAM 200.00', 'Admin', 'Read', '2025-09-23 03:55:48'),
(867, 1, 1056, 'ticket_sale', 'New payment received for ticket booking #HAUPSE - Mr SHIRIN AGHA MUTAWAKIL: Amount AFS 2950.00', 'Admin', 'Read', '2025-09-23 03:58:58'),
(868, 1, 1057, 'ticket_refund', 'Refund payment for Agency client ticket #HAUPSE - Mr SHIRIN AGHA MUTAWAKIL: Amount USD 10.00', 'Admin', 'Read', '2025-09-23 05:57:32'),
(869, 1, 1058, 'ticket_refund', 'Refund payment for Agency client ticket #HAUPSE - Mr SHIRIN AGHA MUTAWAKIL: Amount AFS 1000.00', 'Admin', 'Read', '2025-09-23 05:58:01'),
(870, 1, 1059, 'ticket_refund', 'Refund payment for Agency client ticket #HAUPSE - Mr SHIRIN AGHA MUTAWAKIL: Amount EUR 10.00', 'Admin', 'Read', '2025-09-23 05:58:27'),
(871, 1, 1060, 'ticket_refund', 'Refund payment for Agency client ticket #HAUPSE - Mr SHIRIN AGHA MUTAWAKIL: Amount DARHAM 100.00', 'Admin', 'Read', '2025-09-23 05:58:43'),
(872, 1, 1061, 'ticket_date_change', 'New payment received for date change #HAUPSE - Mr SHIRIN AGHA MUTAWAKIL: Amount USD 2.00', 'Admin', 'Read', '2025-09-23 06:05:10'),
(873, 1, 1062, 'ticket_date_change', 'New payment received for date change #HAUPSE - Mr SHIRIN AGHA MUTAWAKIL: Amount EUR 5.00', 'Admin', 'Read', '2025-09-23 06:05:30'),
(874, 1, 1063, 'ticket_date_change', 'New payment received for date change #HAUPSE - Mr SHIRIN AGHA MUTAWAKIL: Amount AFS 500.00', 'Admin', 'Read', '2025-09-23 06:05:43'),
(875, 1, 1064, 'ticket_date_change', 'New payment received for date change #HAUPSE - Mr SHIRIN AGHA MUTAWAKIL: Amount DARHAM 100.00', 'Admin', 'Read', '2025-09-23 06:06:05'),
(876, 1, 1065, 'ticket_date_change', 'New payment received for date change #HAUPSE - Mr SHIRIN AGHA MUTAWAKIL: Amount USD 2.00', 'Admin', 'Read', '2025-09-23 07:25:58'),
(877, 1, 1066, 'ticket_date_change', 'New payment received for date change #HAUPSE - Mr SHIRIN AGHA MUTAWAKIL: Amount EUR 5.00', 'Admin', 'Read', '2025-09-23 07:26:24'),
(878, 1, 1067, 'ticket_date_change', 'New payment received for date change #HAUPSE - Mr SHIRIN AGHA MUTAWAKIL: Amount DARHAM 10.00', 'Admin', 'Read', '2025-09-23 07:26:53'),
(879, 1, 1068, 'ticket_date_change', 'New payment received for date change #HAUPSE - Mr SHIRIN AGHA MUTAWAKIL: Amount AFS 1000.00', 'Admin', 'Read', '2025-09-23 07:27:16'),
(880, 1, 1069, 'weight', 'New payment received for weight charge #HAUPSE - Mr SHIRIN AGHA MUTAWAKIL: Amount USD 2.00', 'Admin', 'Read', '2025-09-23 07:36:39'),
(881, 1, 1070, 'weight', 'New payment received for weight charge #HAUPSE - Mr SHIRIN AGHA MUTAWAKIL: Amount EUR 5.00', 'Admin', 'Read', '2025-09-23 07:36:57'),
(882, 1, 1071, 'weight', 'New payment received for weight charge #HAUPSE - Mr SHIRIN AGHA MUTAWAKIL: Amount AFS 1000.00', 'Admin', 'Read', '2025-09-23 07:37:15'),
(883, 1, 1072, 'weight', 'New payment received for weight charge #HAUPSE - Mr SHIRIN AGHA MUTAWAKIL: Amount DARHAM 10.00', 'Admin', 'Read', '2025-09-23 07:37:38'),
(884, 1, 1073, 'ticket_reserve', 'New payment received for ticket booking #SZQXJU - Mr HAMID ACHAKZAI : Amount USD 5.00', 'Admin', 'Read', '2025-09-23 08:19:49'),
(885, 1, 1074, 'ticket_reserve', 'New payment received for ticket booking #SZQXJU - Mr HAMID ACHAKZAI : Amount EUR 5.00', 'Admin', 'Read', '2025-09-23 08:20:05'),
(886, 1, 1075, 'ticket_reserve', 'New payment received for ticket booking #SZQXJU - Mr HAMID ACHAKZAI : Amount AFS 1000.00', 'Admin', 'Read', '2025-09-23 08:20:22'),
(887, 1, 1076, 'ticket_reserve', 'New payment received for ticket booking #SZQXJU - Mr HAMID ACHAKZAI : Amount DARHAM 100.00', 'Admin', 'Read', '2025-09-23 08:20:38'),
(888, 1, 1077, 'ticket_reserve', 'New payment received for ticket booking #WKEPD1 - Mr SIDDIQULALAH STANIKZAI: Amount AFS 500.00', 'Admin', 'Read', '2025-09-23 09:12:06'),
(889, 1, 1078, 'ticket_reserve', 'New payment received for ticket booking #WKEPD1 - Mr SIDDIQULALAH STANIKZAI: Amount USD 5.00', 'Admin', 'Read', '2025-09-23 09:12:18'),
(890, 1, 1079, 'ticket_reserve', 'New payment received for ticket booking #WKEPD1 - Mr SIDDIQULALAH STANIKZAI: Amount EUR 10.00', 'Admin', 'Read', '2025-09-23 09:12:35'),
(891, 1, 1080, 'ticket_reserve', 'New payment received for ticket booking #WKEPD1 - Mr SIDDIQULALAH STANIKZAI: Amount DARHAM 10.00', 'Admin', 'Read', '2025-09-23 09:12:52'),
(892, 1, 1081, 'hotel', 'New payment received for hotel booking #1089734 - Mr NAVEED RASHIQ: Amount USD 5.00', 'Admin', 'Read', '2025-09-23 10:22:46'),
(893, 1, 1082, 'hotel', 'New payment received for hotel booking #1089734 - Mr NAVEED RASHIQ: Amount EUR 5.00', 'Admin', 'Read', '2025-09-23 10:23:07'),
(894, 1, 1083, 'hotel', 'New payment received for hotel booking #1089734 - Mr NAVEED RASHIQ: Amount AFS 1000.00', 'Admin', 'Read', '2025-09-23 10:23:29'),
(895, 1, 1084, 'hotel', 'New payment received for hotel booking #1089734 - Mr NAVEED RASHIQ: Amount DARHAM 100.00', 'Admin', 'Read', '2025-09-23 10:23:47'),
(896, 1, 1085, 'hotel', 'New payment received for hotel booking #108973 - Mr NAVEED1 RASHIQ1: Amount USD 5.00', 'Admin', 'Read', '2025-09-23 10:29:38'),
(897, 1, 1086, 'hotel', 'New payment received for hotel booking #108973 - Mr NAVEED1 RASHIQ1: Amount EUR 5.00', 'Admin', 'Read', '2025-09-23 10:29:54'),
(898, 1, 1087, 'hotel', 'New payment received for hotel booking #108973 - Mr NAVEED1 RASHIQ1: Amount AFS 1000.00', 'Admin', 'Read', '2025-09-23 10:30:30'),
(899, 1, 1088, 'hotel', 'New payment received for hotel booking #108973 - Mr NAVEED1 RASHIQ1: Amount DARHAM 100.00', 'Admin', 'Read', '2025-09-23 10:31:01'),
(900, 1, 76, 'umrah', 'Customer: Matiullah has paid: 100 USD to Internal Account processed by Sabaoon for the Umrah booking.', 'Admin', 'Unread', '2025-09-23 10:50:34'),
(901, 1, 77, 'umrah', 'Customer: Matiullah has paid: 7000 AFS to Internal Account processed by Sabaoon for the Umrah booking.', 'Admin', 'Unread', '2025-09-23 10:50:52'),
(902, 1, 78, 'umrah', 'Customer: Matiullah has paid: 110 EUR to Internal Account processed by Sabaoon for the Umrah booking.', 'Admin', 'Unread', '2025-09-23 10:51:19'),
(903, 1, 79, 'umrah', 'Customer: Matiullah has paid: 200 DARHAM to Internal Account processed by Sabaoon for the Umrah booking.', 'Admin', 'Unread', '2025-09-23 10:51:56'),
(904, 1, 80, 'umrah', 'Customer: Idrees has paid: 1000 AFS to Internal Account processed by Sabaoon for the Umrah booking.', 'Admin', 'Unread', '2025-09-23 12:04:41'),
(905, 1, 81, 'umrah', 'Customer: Idrees has paid: 50 USD to Internal Account processed by Sabaoon for the Umrah booking.', 'Admin', 'Unread', '2025-09-23 12:10:09'),
(906, 1, 82, 'umrah', 'Customer: Idrees has paid: 50 EUR to Internal Account processed by Sabaoon for the Umrah booking.', 'Admin', 'Unread', '2025-09-23 12:11:12'),
(907, 1, 83, 'umrah', 'Customer: Idrees has paid: 50 DARHAM to Internal Account processed by Sabaoon for the Umrah booking.', 'Admin', 'Unread', '2025-09-23 12:12:23'),
(908, 1, 1097, 'additional_payment', 'New additional payment received: Amount USD 20.00 - test', 'Admin', 'Unread', '2025-09-25 09:15:14'),
(909, 1, 1098, 'additional_payment', 'New additional payment received: Amount AFS 1000.00 - test', 'Admin', 'Unread', '2025-09-25 09:15:52'),
(910, 1, 1099, 'additional_payment', 'New additional payment received: Amount DARHAM 100.00 - test', 'Admin', 'Unread', '2025-09-25 09:16:15'),
(911, 1, 1100, 'additional_payment', 'New additional payment received: Amount EUR 50.00 - test', 'Admin', 'Unread', '2025-09-25 09:16:38'),
(912, 1, 1101, 'additional_payment', 'New additional payment received: Amount AFS 500.00 - test', 'Admin', 'Unread', '2025-09-25 09:18:33'),
(913, 1, 1102, 'additional_payment', 'New additional payment received: Amount USD 10.00 - test', 'Admin', 'Unread', '2025-09-25 09:18:49'),
(914, 1, 1103, 'additional_payment', 'New additional payment received: Amount EUR 10.00 - test', 'Admin', 'Unread', '2025-09-25 09:19:16'),
(915, 1, 1104, 'additional_payment', 'New additional payment received: Amount DARHAM 10.00 - test', 'Admin', 'Unread', '2025-09-25 09:19:51'),
(916, 1, 1105, 'umrah_refund', 'Umrah refund payment for Agency client - Idrees Amount AFS 1000.00', 'Admin', 'Unread', '2025-09-25 10:14:38'),
(917, 1, 1106, 'umrah_refund', 'Umrah refund payment for Agency client - Idrees Amount USD 100.00', 'Admin', 'Read', '2025-09-25 10:14:56'),
(918, 1, 1107, 'umrah_refund', 'Umrah refund payment for Agency client - Idrees Amount EUR 5.00', 'Admin', 'Read', '2025-09-25 10:25:49'),
(919, 1, 1108, 'umrah_refund', 'Umrah refund payment for Agency client - Idrees Amount DARHAM 10.00', 'Admin', 'Unread', '2025-09-25 10:26:14'),
(920, 1, 1109, 'visa', 'New payment received for visa application #53 - BAKHTIAR STANIKZAI: Amount AFS 1000.00', 'Admin', 'Unread', '2025-09-25 11:28:54'),
(921, 1, 1110, 'visa', 'New payment received for visa application #53 - BAKHTIAR STANIKZAI: Amount USD 20.00', 'Admin', 'Unread', '2025-09-25 11:29:07'),
(922, 1, 1111, 'visa', 'New payment received for visa application #53 - BAKHTIAR STANIKZAI: Amount EUR 20.00', 'Admin', 'Unread', '2025-09-25 11:29:39'),
(923, 1, 1112, 'visa', 'New payment received for visa application #53 - BAKHTIAR STANIKZAI: Amount DARHAM 100.00', 'Admin', 'Unread', '2025-09-25 11:29:58'),
(924, 1, 1113, 'visa', 'New payment received for visa application #54 - HAMID ACHAKZAI : Amount AFS 500.00', 'Admin', 'Unread', '2025-09-25 11:35:55'),
(925, 1, 1114, 'visa', 'New payment received for visa application #54 - HAMID ACHAKZAI : Amount USD 5.00', 'Admin', 'Unread', '2025-09-25 11:36:09'),
(926, 1, 1115, 'visa', 'New payment received for visa application #54 - HAMID ACHAKZAI : Amount EUR 5.00', 'Admin', 'Unread', '2025-09-25 11:36:24'),
(927, 1, 1116, 'visa', 'New payment received for visa application #54 - HAMID ACHAKZAI : Amount DARHAM 20.00', 'Admin', 'Read', '2025-09-25 11:36:40'),
(928, 1, 1117, 'visa_refund', 'Visa refund payment for Agency client - HAMID ACHAKZAI  (P879876) from Pakistan: Amount AFS 500.00', 'Admin', 'Unread', '2025-09-25 11:54:18'),
(929, 1, 1118, 'visa_refund', 'Visa refund payment for Agency client - HAMID ACHAKZAI  (P879876) from Pakistan: Amount USD 5.00', 'Admin', 'Unread', '2025-09-25 11:54:45'),
(930, 1, 1119, 'visa_refund', 'Visa refund payment for Agency client - HAMID ACHAKZAI  (P879876) from Pakistan: Amount EUR 5.00', 'Admin', 'Read', '2025-09-25 11:56:24'),
(931, 1, 1120, 'visa_refund', 'Visa refund payment for Agency client - HAMID ACHAKZAI  (P879876) from Pakistan: Amount DARHAM 10.00', 'Admin', 'Read', '2025-09-25 11:56:46'),
(932, 1, 1121, 'hotel_refund', 'Hotel refund payment for Agency client - NAVEED1 (RASHIQ1) Amount AFS 500.00', 'Admin', 'Unread', '2025-09-27 06:49:41'),
(933, 1, 1122, 'hotel_refund', 'Hotel refund payment for Agency client - NAVEED1 (RASHIQ1) Amount USD 5.00', 'Admin', 'Unread', '2025-09-27 06:49:54'),
(934, 1, 1123, 'hotel_refund', 'Hotel refund payment for Agency client - NAVEED1 (RASHIQ1) Amount EUR 5.00', 'Admin', 'Unread', '2025-09-27 06:50:09'),
(935, 1, 1124, 'hotel_refund', 'Hotel refund payment for Agency client - NAVEED1 (RASHIQ1) Amount DARHAM 10.00', 'Admin', 'Read', '2025-09-27 06:50:30'),
(936, 1, 84, 'umrah', 'Customer: DR SAHIB has paid: 20 USD to Internal Account processed by Sabaoon for the Umrah booking.', 'Admin', 'Unread', '2025-09-28 09:40:35'),
(937, 1, 85, 'umrah', 'Customer: DR SAHIB has paid: 10 USD to Internal Account processed by Sabaoon for the Umrah booking.', 'Admin', 'Unread', '2025-09-28 09:53:11'),
(938, 1, 86, 'umrah', 'Customer: DR SAHIB has paid: 10 USD to Internal Account processed by Sabaoon for the Umrah booking.', 'Admin', 'Unread', '2025-09-28 09:58:15'),
(939, 1, 87, 'umrah', 'Customer: DR SAHIB has paid: 10 USD to Internal Account processed by Sabaoon for the Umrah booking.', 'Admin', 'Unread', '2025-09-28 10:00:25'),
(940, 1, 88, 'umrah', 'Customer: DR SAHIB has paid: 10 USD to Bank processed by Sabaoon for the Umrah booking.', 'Admin', 'Unread', '2025-09-28 10:01:22'),
(941, 1, 89, 'umrah', 'Customer: DR SAHIB has paid: 10 USD to Bank processed by Sabaoon for the Umrah booking.', 'Admin', 'Unread', '2025-09-28 10:03:21'),
(942, 1, 90, 'umrah', 'Customer: DR SAHIB has paid: 10 USD to Internal Account processed by Sabaoon for the Umrah booking.', 'Admin', 'Unread', '2025-09-28 10:15:18'),
(944, 1, NULL, 'supplier', 'A payment of 10 USD has been deleted by Sabaoon for the Umrah booking.', 'Admin', 'Unread', '2025-09-28 10:21:58');

-- --------------------------------------------------------

--
-- Table structure for table `payroll_details`
--

CREATE TABLE `payroll_details` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `payroll_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `base_salary` decimal(15,2) NOT NULL,
  `bonus` decimal(15,2) NOT NULL DEFAULT 0.00,
  `deductions` decimal(15,2) NOT NULL DEFAULT 0.00,
  `advance_deduction` decimal(15,2) NOT NULL DEFAULT 0.00,
  `net_salary` decimal(15,2) NOT NULL,
  `payment_status` enum('pending','paid') NOT NULL DEFAULT 'pending',
  `payment_date` date DEFAULT NULL,
  `receipt` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payroll_records`
--

CREATE TABLE `payroll_records` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `pay_period` varchar(20) NOT NULL COMMENT 'Format: YYYY-MM',
  `generated_date` date NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `status` enum('draft','processed','paid') NOT NULL DEFAULT 'draft',
  `generated_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `plans`
--

CREATE TABLE `plans` (
  `id` int(11) NOT NULL,
  `name` varchar(50) NOT NULL,
  `description` text DEFAULT NULL,
  `features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`features`)),
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `max_users` int(11) NOT NULL DEFAULT 0,
  `trial_days` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `plans`
--

INSERT INTO `plans` (`id`, `name`, `description`, `features`, `price`, `max_users`, `trial_days`, `status`, `created_at`, `updated_at`) VALUES
(1, 'basic', 'Basic plan with access to ticket-related tasks only', '[\r\n    \"ticket_bookings\",\r\n    \"ticket_reservations\",\r\n    \"refunded_tickets\",\r\n    \"date_change_tickets\",\r\n    \"ticket_weights\"\r\n  ]', 200.00, 20, 10, 'active', '2025-08-23 11:28:15', '2025-08-30 07:40:19'),
(2, 'pro', 'Pro plan with ticket-related tasks, visa-related tasks, and inter-tenant chat', '[\r\n    \"ticket_bookings\",\r\n    \"ticket_reservations\",\r\n    \"refunded_tickets\",\r\n    \"date_change_tickets\",\r\n    \"ticket_weights\",\r\n    \"visa_applications\",\r\n    \"visa_refunds\",\r\n    \"visa_transactions\",\r\n    \"inter_tenant_chat\"\r\n  ]', 0.00, 0, 0, 'active', '2025-08-23 11:28:15', '2025-08-23 11:28:15'),
(3, 'enterprise', 'Enterprise plan with all Pro features plus Umrah management', '[\r\n    \"ticket_bookings\",\r\n    \"ticket_reservations\", \r\n    \"refunded_tickets\",\r\n    \"date_change_tickets\",\r\n    \"ticket_weights\",\r\n    \"hotel_bookings\",\r\n    \"hotel_refunds\",\r\n    \"visa_applications\",\r\n    \"visa_refunds\",\r\n    \"visa_transactions\", \r\n    \"inter_tenant_chat\",\r\n    \"umrah_bookings\",\r\n    \"umrah_refunds\",\r\n    \"debtors\",\r\n    \"creditors\",\r\n    \"sarafi\",\r\n    \"salary\",\r\n    \"additional_payments\",\r\n    \"jv_payments\",\r\n    \"manage_maktobs\",\r\n    \"assets\",\r\n    \"financial_statements\",\r\n    \"expense_management\"\r\n]', 0.00, 0, 0, 'active', '2025-08-23 11:28:15', '2025-09-03 06:15:43');

-- --------------------------------------------------------

--
-- Table structure for table `platform_settings`
--

CREATE TABLE `platform_settings` (
  `id` int(11) NOT NULL,
  `key` varchar(100) NOT NULL,
  `value` text NOT NULL,
  `type` enum('string','integer','boolean','json') NOT NULL DEFAULT 'string',
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `platform_settings`
--

INSERT INTO `platform_settings` (`id`, `key`, `value`, `type`, `description`, `created_at`, `updated_at`) VALUES
(1, 'primary_color', '#b7c5f0', 'string', NULL, '2025-08-04 06:34:16', '2025-08-04 06:34:16'),
(2, 'secondary_color', '#858796', 'string', NULL, '2025-08-04 06:34:16', '2025-08-04 06:34:16'),
(3, 'accent_color', '#1cc88a', 'string', NULL, '2025-08-04 06:34:16', '2025-08-04 06:34:16'),
(4, 'sidebar_style', 'compact', 'string', NULL, '2025-08-04 06:34:16', '2025-08-12 07:59:01'),
(5, 'theme_mode', 'light', 'string', NULL, '2025-08-04 06:34:16', '2025-08-12 07:14:38'),
(21, 'platform_name', 'MTravels', 'string', NULL, '2025-08-12 07:04:53', '2025-10-02 11:52:07'),
(22, 'platform_description', 'Comprehensive construction management platform', 'string', NULL, '2025-08-12 07:04:53', '2025-08-12 07:04:53'),
(23, 'contact_email', 'allahdadmuhammadi01@gmail.com', 'string', NULL, '2025-08-12 07:04:53', '2025-10-02 11:52:07'),
(24, 'support_phone', '+93780310431', 'string', NULL, '2025-08-12 07:04:53', '2025-10-02 11:52:07'),
(25, 'website_url', 'https://mtravels.com', 'string', NULL, '2025-08-12 07:04:53', '2025-10-02 11:52:07'),
(31, 'platform_logo', 'logo_1759405927_68de67675a380.png', 'string', NULL, '2025-08-12 07:13:25', '2025-10-02 11:52:07'),
(42, 'platform_favicon', 'logo_1756212047_68adab4f626b7.png', 'string', NULL, '2025-08-12 07:25:25', '2025-08-27 12:01:37'),
(43, 'contact_address', 'Kabul,Afghanistan', 'string', NULL, '2025-08-12 07:31:12', '2025-10-02 11:52:07'),
(44, 'contact_phone', '0780310431', 'string', NULL, '2025-08-12 07:31:12', '2025-10-02 11:52:07'),
(46, 'contact_website', 'https://construct360.com', 'string', NULL, '2025-08-12 07:31:12', '2025-08-12 23:17:29'),
(47, 'contact_facebook', 'https://mtravels.com', 'string', NULL, '2025-08-12 07:31:12', '2025-10-02 11:52:07'),
(48, 'contact_twitter', 'https://mtravels.com', 'string', NULL, '2025-08-12 07:31:12', '2025-10-02 11:52:07'),
(49, 'contact_linkedin', 'https://mtravels.com', 'string', NULL, '2025-08-12 07:31:12', '2025-10-02 11:52:07'),
(50, 'contact_instagram', 'https://mtravels.com', 'string', NULL, '2025-08-12 07:31:12', '2025-10-02 11:52:07'),
(84, 'email_notifications', '1', 'string', NULL, '2025-08-13 01:46:05', '2025-08-13 01:46:05'),
(85, 'sms_notifications', '0', 'string', NULL, '2025-08-13 01:46:05', '2025-08-13 01:46:05'),
(86, 'push_notifications', '1', 'string', NULL, '2025-08-13 01:46:05', '2025-08-13 01:46:05'),
(87, 'notification_sound', '1', 'string', NULL, '2025-08-13 01:46:05', '2025-08-13 01:46:05'),
(88, 'vapid_subject', 'mailto:allahdadmuhammadi01@gmail.com', 'string', NULL, '2025-08-13 05:40:16', '2025-08-13 05:40:16'),
(89, 'vapid_public_key', 'BPrcke06b6zEa_k2loCLVatIG83YOjNByloONBeeFzC4c4tlwW4ww3a9JoX5dp58dLekgAoOn2FtS9sPZKxbjjA', 'string', NULL, '2025-08-13 05:40:16', '2025-08-13 05:40:16'),
(90, 'vapid_private_key', 'XvR9FAF5YCNFbtJcLkJkBkQIYZ6bULJiGoPRsjV56UI', 'string', NULL, '2025-08-13 05:40:16', '2025-08-13 05:40:16'),
(91, 'agency_name', 'MTravels', 'string', 'Platform agency name', '2025-08-26 12:32:02', '2025-08-30 07:56:11'),
(92, 'default_currency', 'AFN', 'string', 'Default currency for new tenants', '2025-08-26 12:32:02', '2025-10-02 11:52:07'),
(93, 'support_email', 'allahdadmuhammadi01@gmail.com', 'string', 'Contact email for platform support', '2025-08-26 12:32:02', '2025-10-02 11:52:07'),
(94, 'api_enabled', 'false', 'boolean', 'Whether API access is enabled globally', '2025-08-26 12:32:02', '2025-10-02 11:52:07'),
(95, 'max_users_per_tenant', '20', 'integer', 'Maximum users allowed per tenant on basic plan', '2025-08-26 12:32:02', '2025-10-02 11:52:07'),
(96, 'logo', 'logo_1756211874_68adaaa2805b8.png', 'string', 'Platform logo file name', '2025-08-26 12:32:02', '2025-08-26 12:37:54');

-- --------------------------------------------------------

--
-- Table structure for table `refunded_tickets`
--

CREATE TABLE `refunded_tickets` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `paid_to` int(11) NOT NULL,
  `sold_to` int(11) NOT NULL,
  `supplier` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `passenger_name` varchar(255) NOT NULL,
  `pnr` varchar(255) NOT NULL,
  `origin` varchar(255) NOT NULL,
  `destination` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `airline` varchar(255) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `issue_date` date NOT NULL,
  `departure_date` date NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `sold` decimal(10,3) NOT NULL,
  `base` decimal(10,3) NOT NULL,
  `supplier_penalty` decimal(10,3) NOT NULL,
  `service_penalty` decimal(10,3) NOT NULL,
  `refund_to_passenger` decimal(10,3) NOT NULL,
  `status` enum('Refunded','Paid','Declined') NOT NULL,
  `receipt` varchar(100) NOT NULL,
  `remarks` mediumtext NOT NULL,
  `created_by` int(50) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `calculation_method` varchar(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `refunded_tickets`
--

INSERT INTO `refunded_tickets` (`id`, `tenant_id`, `ticket_id`, `paid_to`, `sold_to`, `supplier`, `title`, `passenger_name`, `pnr`, `origin`, `destination`, `phone`, `airline`, `gender`, `issue_date`, `departure_date`, `currency`, `sold`, `base`, `supplier_penalty`, `service_penalty`, `refund_to_passenger`, `status`, `receipt`, `remarks`, `created_by`, `created_at`, `updated_at`, `calculation_method`) VALUES
(96, 1, 329, 11, 19, '29', 'Mr', 'SHIRIN AGHA MUTAWAKIL', 'HAUPSE', ' KBL', 'ISB', '0771781576', 'FG', 'Male', '2025-09-23', '2025-10-02', 'AFS', 15000.000, 10000.000, 1000.000, 2000.000, 7000.000, 'Refunded', '', 'test', 1, '2025-09-23 10:23:49', '2025-09-23 10:23:49', 'base');

-- --------------------------------------------------------

--
-- Table structure for table `salary_adjustments`
--

CREATE TABLE `salary_adjustments` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `adjustment_type` enum('increment','decrement') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `percentage` decimal(5,2) DEFAULT NULL COMMENT 'If adjustment is percentage based',
  `effective_date` date NOT NULL,
  `previous_salary` decimal(15,2) NOT NULL,
  `new_salary` decimal(15,2) NOT NULL,
  `reason` mediumtext NOT NULL,
  `approved_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `salary_advances`
--

CREATE TABLE `salary_advances` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `main_account_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `advance_date` date NOT NULL,
  `repayment_status` enum('pending','partially_paid','paid') NOT NULL DEFAULT 'pending',
  `amount_paid` decimal(15,2) NOT NULL DEFAULT 0.00,
  `description` mediumtext DEFAULT NULL,
  `receipt` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `salary_bonuses`
--

CREATE TABLE `salary_bonuses` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` mediumtext NOT NULL,
  `bonus_date` date NOT NULL,
  `type` enum('performance','holiday','other') NOT NULL DEFAULT 'other',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `salary_deductions`
--

CREATE TABLE `salary_deductions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `description` mediumtext NOT NULL,
  `deduction_date` date NOT NULL,
  `type` enum('absence','penalty','tax','other') NOT NULL DEFAULT 'other',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `salary_management`
--

CREATE TABLE `salary_management` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `base_salary` decimal(15,2) NOT NULL DEFAULT 0.00,
  `currency` enum('USD','AFS') NOT NULL DEFAULT 'USD',
  `joining_date` date NOT NULL,
  `payment_day` int(2) NOT NULL DEFAULT 1 COMMENT 'Day of month when salary is paid',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `salary_management`
--

INSERT INTO `salary_management` (`id`, `tenant_id`, `user_id`, `base_salary`, `currency`, `joining_date`, `payment_day`, `status`, `created_at`, `updated_at`) VALUES
(8, 1, 6, 10000.00, 'AFS', '2025-09-22', 28, 'active', '2025-09-22 11:41:12', '2025-09-22 11:41:12');

-- --------------------------------------------------------

--
-- Table structure for table `salary_payments`
--

CREATE TABLE `salary_payments` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `main_account_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `payment_date` date NOT NULL,
  `payment_for_month` date NOT NULL COMMENT 'First day of the month this payment is for',
  `payment_type` enum('regular','bonus','advance','other') NOT NULL DEFAULT 'regular',
  `description` mediumtext DEFAULT NULL,
  `receipt` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `salary_payments`
--

INSERT INTO `salary_payments` (`id`, `tenant_id`, `user_id`, `main_account_id`, `amount`, `currency`, `payment_date`, `payment_for_month`, `payment_type`, `description`, `receipt`, `created_at`) VALUES
(21, 1, 6, 11, 10000.00, 'AFS', '2025-09-22', '2025-09-01', 'regular', 'adfads', 'SP20250922134252-1', '2025-09-22 11:42:52');

-- --------------------------------------------------------

--
-- Table structure for table `sarafi_transactions`
--

CREATE TABLE `sarafi_transactions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `customer_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` varchar(10) NOT NULL,
  `type` enum('deposit','withdrawal','hawala_send','hawala_receive','exchange','adjustment') NOT NULL,
  `status` enum('pending','completed','cancelled') DEFAULT 'completed',
  `notes` mediumtext DEFAULT NULL,
  `reference_number` varchar(50) DEFAULT NULL,
  `receipt_path` varchar(255) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sarafi_transactions`
--

INSERT INTO `sarafi_transactions` (`id`, `tenant_id`, `customer_id`, `amount`, `currency`, `type`, `status`, `notes`, `reference_number`, `receipt_path`, `created_at`, `updated_at`) VALUES
(36, 1, 4, 100.00, 'USD', 'deposit', 'completed', '', 'DEP68d1356497ef4', NULL, '2025-09-22 11:39:26', '2025-09-22 11:39:26'),
(37, 1, 4, 50.00, 'USD', 'withdrawal', 'completed', '', 'WDR68d1356e6b372', NULL, '2025-09-22 11:39:48', '2025-09-22 11:39:48'),
(38, 1, 4, 20.00, 'USD', 'hawala_send', 'completed', 'fdsafd', 'HWL68d135b0bb0ad', NULL, '2025-09-22 11:40:32', '2025-09-22 11:40:32');

-- --------------------------------------------------------

--
-- Table structure for table `settings`
--

CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `agency_name` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `phone` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `address` mediumtext NOT NULL,
  `logo` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `tenant_id`, `agency_name`, `title`, `phone`, `email`, `address`, `logo`, `created_at`, `updated_at`) VALUES
(1, 1, 'Al Moqadas', 'Al Moqadas Travel Agency', '0786011115', 'almuqadas_travel@yahoo.com', 'Jada-e-Maiwand, KABUL , AFGHANISTAN', 'logo.png', '2025-01-18 04:43:58', '2025-08-26 07:49:36'),
(2, 2, 'Al Wali', 'Al Wali Travel', '0786011115', 'alwali@gmail.com', 'kabul', 'file_68b9695fea986_Blue_and_White_Modern_Travel_Instagram_Post (1).png', '2025-09-04 12:34:02', '2025-09-07 09:19:45');

-- --------------------------------------------------------

--
-- Table structure for table `subscription_payments`
--

CREATE TABLE `subscription_payments` (
  `id` int(11) NOT NULL,
  `subscription_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `payment_date` date NOT NULL,
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `receipt_number` varchar(100) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `subscription_payments`
--

INSERT INTO `subscription_payments` (`id`, `subscription_id`, `amount`, `currency`, `payment_date`, `payment_method`, `transaction_id`, `receipt_number`, `notes`, `processed_by`, `created_at`, `updated_at`) VALUES
(1, 1, 0.00, 'AFS', '2025-09-17', 'Cash', '34213', '2452345', 'adfaf', 14, '2025-09-17 04:46:57', '2025-09-17 04:46:57'),
(2, 1, 0.00, 'AFS', '2025-09-17', 'Cash', '34213', '2452345', 'adfaf', 14, '2025-09-17 04:47:33', '2025-09-17 04:47:33'),
(3, 2, 1000.00, 'AFS', '2025-09-17', 'Cash', 'gdfg', '2452345', 'agff', 14, '2025-09-17 06:47:33', '2025-09-17 06:47:33'),
(4, 1, 0.00, 'AFS', '2025-09-17', 'Cash', 'sgf', '2452345', 'sfgsdf', 14, '2025-09-17 06:50:55', '2025-09-17 06:50:55'),
(5, 1, 50.00, 'AFN', '2025-09-18', 'Hesabpay', NULL, 'hesabpay_68cbd15976adc', NULL, 1, '2025-09-18 09:31:05', '2025-09-18 09:31:05'),
(6, 1, 50.00, 'AFN', '2025-09-18', 'Hesabpay', NULL, 'hesabpay_68cbd15d22905', NULL, 1, '2025-09-18 09:31:09', '2025-09-18 09:31:09');

-- --------------------------------------------------------

--
-- Table structure for table `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `contact_person` varchar(255) DEFAULT NULL,
  `supplier_type` enum('Internal','External') NOT NULL DEFAULT 'External',
  `phone` varchar(15) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` mediumtext DEFAULT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `balance` decimal(10,3) NOT NULL DEFAULT 0.000,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `tenant_id`, `name`, `contact_person`, `supplier_type`, `phone`, `email`, `address`, `currency`, `balance`, `created_at`, `updated_at`, `status`) VALUES
(27, 1, 'KamAir', 'NAVEED RASHIQ', 'External', '0777305730', 'RAHIMI107@GAMIL.COM', 'Jada-e-Maiwand', 'USD', 70.000, '2025-09-01 10:34:06', '2025-09-28 10:49:52', 'active'),
(29, 1, 'Ariana', 'NAVEED RASHIQ', 'External', '0777305730', 'RAHIMI107@GAMIL.COM', 'Jada-e-Maiwand', 'AFS', -3000.000, '2025-09-07 08:41:35', '2025-09-27 05:22:48', 'active'),
(30, 2, 'NAVEED RASHIQ', 'NAVEED RASHIQ', 'External', '0777305730', 'RAHIMI107@GAMIL.COM', 'Jada-e-Maiwand', 'USD', 10.000, '2025-09-10 11:48:12', '2025-09-10 13:03:52', 'active');

-- --------------------------------------------------------

--
-- Table structure for table `supplier_transactions`
--

CREATE TABLE `supplier_transactions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `reference_id` int(100) NOT NULL,
  `transaction_type` enum('Debit','Credit') NOT NULL,
  `transaction_of` enum('ticket_sale','visa_sale','ticket_refund','date_change','fund','umrah','hotel','hotel_refund','ticket_reserve','jv_payment','visa_refund','hotel_refund','umrah_refund','additional_payment','weight_sale','supplier_bonus','fund_withdrawal','umrah_date_change') NOT NULL,
  `amount` decimal(10,3) NOT NULL,
  `balance` decimal(15,3) NOT NULL,
  `remarks` text DEFAULT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `receipt` varchar(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_transactions`
--

INSERT INTO `supplier_transactions` (`id`, `tenant_id`, `supplier_id`, `reference_id`, `transaction_type`, `transaction_of`, `amount`, `balance`, `remarks`, `transaction_date`, `updated_at`, `receipt`) VALUES
(828, 1, 27, 1, 'Credit', 'fund', 1000.000, 1000.000, 'Supplier: KamAir, Funded by main account: AZIZI BANKadf, processed by: Sabaoon, Remarks: adsfdsaf', '2025-09-22 11:36:15', '2025-09-22 11:36:15', '2452345'),
(829, 1, 29, 1, 'Credit', 'fund', 1000.000, 1000.000, 'Supplier: Ariana, Funded by main account: AZIZI BANKadf, processed by: Sabaoon, Remarks: adfadsf', '2025-09-22 11:36:38', '2025-09-22 11:36:38', 'adfadsf'),
(830, 1, 27, 325, 'Debit', 'ticket_sale', 100.000, 900.000, 'Base amount of 100 USD deducted for ticket booking for Mr FAZALHAQ PARDES with PNR: 188JZ0.', '2025-09-22 12:06:34', '2025-09-22 12:06:34', ''),
(834, 1, 29, 329, 'Debit', 'ticket_sale', 10000.000, -9000.000, 'Base amount of 10000 AFS deducted for ticket booking for Mr SHIRIN AGHA MUTAWAKIL with PNR: HAUPSE.', '2025-09-23 03:54:20', '2025-09-23 03:54:20', ''),
(835, 1, 29, 96, 'Credit', 'ticket_refund', 9000.000, 0.000, 'Refund for ticket SHIRIN AGHA MUTAWAKIL added to account.', '2025-09-23 05:53:49', '2025-09-23 05:53:49', ''),
(836, 1, 29, 46, 'Debit', 'date_change', 1000.000, -1000.000, 'Penalty for ticket Name SHIRIN AGHA MUTAWAKIL date change deducted from account', '2025-09-23 05:54:12', '2025-09-23 05:54:12', ''),
(837, 1, 29, 12, 'Debit', 'weight_sale', 1000.000, -2000.000, 'Base amount of 1000 AFS deducted for weight transaction.', '2025-09-23 05:54:31', '2025-09-23 05:54:31', ''),
(838, 1, 27, 13, 'Debit', 'ticket_reserve', 0.000, 900.000, 'Base amount of 0 USD deducted for ticket reservation.', '2025-09-23 08:17:50', '2025-09-23 08:17:50', ''),
(839, 1, 27, 14, 'Debit', 'ticket_reserve', 0.000, 900.000, 'Base amount of 0 USD deducted for ticket reservation.', '2025-09-23 08:19:06', '2025-09-23 08:19:06', ''),
(840, 1, 29, 15, 'Debit', 'ticket_reserve', 1000.000, -3000.000, 'Updated: Base amount of 10 AFS deducted for ticket reservation.', '2025-09-23 09:06:13', '2025-09-23 09:11:28', ''),
(841, 1, 27, 39, 'Debit', 'hotel', 10.000, 890.000, 'Hotel booking for Mr NAVEED RASHIQ', '2025-09-23 10:15:18', '2025-09-23 10:15:18', ''),
(842, 1, 29, 41, 'Debit', 'hotel', 1000.000, -4000.000, 'Hotel booking for Mr NAVEED1 RASHIQ1', '2025-09-23 10:24:59', '2025-09-23 10:24:59', ''),
(843, 1, 27, 55, 'Debit', 'umrah', 500.000, 390.000, 'Base amount of 500 USD deducted for umrah.', '2025-09-23 10:49:46', '2025-09-23 10:49:46', ''),
(844, 1, 29, 56, 'Debit', 'umrah', 10000.000, -14000.000, 'Base amount of 10000 AFS deducted for umrah.', '2025-09-23 12:02:39', '2025-09-23 12:02:39', ''),
(845, 1, 29, 21, 'Credit', 'umrah_refund', 10000.000, -4000.000, 'Refund for umrah booking #56 - utytt', '2025-09-25 10:11:27', '2025-09-25 10:11:27', ''),
(848, 1, 27, 53, 'Debit', 'visa_sale', 100.000, 290.000, 'Visa purchase for BAKHTIAR STANIKZAI - P8798765', '2025-09-25 11:26:02', '2025-09-25 11:26:02', ''),
(849, 1, 29, 54, 'Debit', 'visa_sale', 2000.000, -6000.000, 'Visa purchase for HAMID ACHAKZAI  - P879876', '2025-09-25 11:32:51', '2025-09-25 11:32:51', ''),
(850, 1, 29, 5, 'Credit', 'visa_refund', 2000.000, -4000.000, 'Refund for visa application #54 - test', '2025-09-25 11:39:24', '2025-09-25 11:39:24', ''),
(852, 1, 29, 9, 'Credit', 'hotel_refund', 1000.000, -3000.000, 'Refund for hotel booking #41 - tets', '2025-09-27 05:22:48', '2025-09-27 05:22:48', ''),
(853, 1, 27, 58, 'Debit', 'umrah', 110.000, 180.000, 'Base amount of 110 USD deducted for umrah all.', '2025-09-27 10:59:41', '2025-09-27 10:59:41', ''),
(856, 1, 27, 61, 'Debit', 'umrah', 130.000, 60.000, 'Updated: Updated: Updated: Base amount of 100 USD deducted for umrah all.', '2025-09-28 06:39:11', '2025-09-28 10:04:21', ''),
(862, 1, 27, 88, 'Credit', 'umrah', 10.000, 60.000, 'teted', '2025-09-28 10:01:22', '2025-09-28 10:04:21', '2452345'),
(863, 1, 27, 89, 'Credit', 'umrah', 10.000, 70.000, 'fstgsdf', '2025-09-28 10:03:21', '2025-09-28 10:04:21', '6524652');

-- --------------------------------------------------------

--
-- Table structure for table `tenants`
--

CREATE TABLE `tenants` (
  `id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `subdomain` varchar(100) DEFAULT NULL,
  `identifier` varchar(100) NOT NULL,
  `status` enum('active','suspended','trial','deleted') NOT NULL DEFAULT 'trial',
  `plan` enum('basic','pro','enterprise') DEFAULT 'basic',
  `billing_email` varchar(255) DEFAULT NULL,
  `chat_max_file_bytes` int(11) NOT NULL DEFAULT 26214400,
  `chat_allowed_mime_prefixes` varchar(500) DEFAULT 'image/,video/,audio/,application/pdf,text/',
  `chat_default_auto_download` tinyint(1) NOT NULL DEFAULT 0,
  `payment_status` enum('current','warning','overdue','suspended') NOT NULL DEFAULT 'current',
  `payment_due_date` date DEFAULT NULL,
  `last_payment_date` date DEFAULT NULL,
  `payment_warning_sent` tinyint(1) NOT NULL DEFAULT 0,
  `last_warning_sent` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tenants`
--

INSERT INTO `tenants` (`id`, `name`, `subdomain`, `identifier`, `status`, `plan`, `billing_email`, `chat_max_file_bytes`, `chat_allowed_mime_prefixes`, `chat_default_auto_download`, `payment_status`, `payment_due_date`, `last_payment_date`, `payment_warning_sent`, `last_warning_sent`, `created_at`, `updated_at`, `deleted_at`) VALUES
(1, 'Al Moqadas Travel Agency ', 'alpha', 'tenant-alpha-001', 'active', 'enterprise', 'billing@alpha.com', 26214400, 'image/,video/,audio/,application/pdf,text/', 0, 'current', '2025-10-17', '2025-09-17', 0, NULL, '2025-06-30 19:30:00', '2025-09-17 06:50:55', NULL),
(2, 'Al Wali', 'beta', 'tenant-beta-002', 'active', 'enterprise', 'billing@beta.com', 26214400, 'image/,video/,audio/,application/pdf,text/', 0, 'current', '2026-09-17', '2025-09-17', 0, '2025-09-17 11:09:30', '2025-07-14 19:30:00', '2025-09-17 06:47:33', NULL),
(3, 'Elite Pilgrimages Gamma', 'gamma', 'tenant-gamma-003', 'deleted', 'enterprise', 'billing@gamma.com', 26214400, 'image/,video/,audio/,application/pdf,text/', 0, 'current', NULL, NULL, 0, NULL, '2025-05-31 19:30:00', '2025-09-09 06:52:26', '2025-09-09 06:52:26'),
(4, 'Suspended Tours Delta', 'delta', 'tenant-delta-004', 'deleted', 'basic', 'billing@delta.com', 26214400, 'image/,video/,audio/,application/pdf,text/', 0, 'current', NULL, NULL, 0, NULL, '2024-12-31 19:30:00', '2025-08-26 11:12:34', '2025-08-26 11:12:34'),
(5, 'New Ventures Epsilon', 'epsilon', 'tenant-epsilon-005', 'deleted', 'enterprise', 'billing@epsilon.com', 26214400, 'image/,video/,audio/,application/pdf,text/', 0, 'current', NULL, NULL, 0, NULL, '2025-08-19 19:30:00', '2025-09-09 06:52:30', '2025-09-09 06:52:30'),
(6, 'KamAir', 'mtravels', 'travelalmuqadas', 'deleted', 'basic', 'RAHIMI107@GAMIL.COM', 26214400, 'image/,video/,audio/,application/pdf,text/', 0, 'current', NULL, NULL, 0, NULL, '2025-08-26 11:39:54', '2025-08-26 11:41:22', '2025-08-26 11:41:22');

-- --------------------------------------------------------

--
-- Table structure for table `tenant_peering`
--

CREATE TABLE `tenant_peering` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `peer_tenant_id` int(11) NOT NULL,
  `status` enum('approved','pending','blocked') NOT NULL DEFAULT 'approved',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tenant_peering`
--

INSERT INTO `tenant_peering` (`id`, `tenant_id`, `peer_tenant_id`, `status`, `created_at`) VALUES
(3, 2, 1, 'approved', '2025-09-08 08:13:20');

-- --------------------------------------------------------

--
-- Table structure for table `tenant_subscriptions`
--

CREATE TABLE `tenant_subscriptions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `plan_id` varchar(50) NOT NULL,
  `status` enum('active','pending','cancelled','expired') NOT NULL DEFAULT 'pending',
  `billing_cycle` enum('monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
  `start_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `end_date` timestamp NULL DEFAULT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `payment_method` varchar(50) DEFAULT NULL,
  `last_payment_date` timestamp NULL DEFAULT NULL,
  `next_billing_date` timestamp NULL DEFAULT NULL,
  `transaction_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tenant_subscriptions`
--

INSERT INTO `tenant_subscriptions` (`id`, `tenant_id`, `plan_id`, `status`, `billing_cycle`, `start_date`, `end_date`, `amount`, `currency`, `payment_method`, `last_payment_date`, `next_billing_date`, `transaction_id`, `created_at`, `updated_at`) VALUES
(1, 1, '3', 'active', 'monthly', '2025-07-23 19:30:00', NULL, 1.00, 'AFN', 'stripe', '2025-09-16 19:30:00', '0000-00-00 00:00:00', 'txn_123456789', '2025-07-23 19:30:00', '2025-09-18 10:04:53'),
(2, 2, '3', 'active', 'yearly', '2025-07-31 19:30:00', NULL, 0.00, 'USD', 'paypal', '2025-09-16 19:30:00', '2026-09-16 19:30:00', 'txn_987654321', '2025-07-31 19:30:00', '2025-09-17 06:47:33'),
(3, 3, '3', 'active', 'quarterly', '2025-05-31 19:30:00', NULL, 999.99, 'USD', 'stripe', '2025-05-31 19:30:00', '2025-08-31 19:30:00', 'txn_456789123', '2025-05-31 19:30:00', '2025-08-28 12:12:19'),
(4, 4, '1', 'expired', 'monthly', '2024-12-31 19:30:00', '2025-01-31 19:30:00', 49.99, 'USD', 'stripe', '2024-12-31 19:30:00', NULL, 'txn_111222333', '2024-12-31 19:30:00', '2025-08-28 12:12:23'),
(5, 5, '2', 'pending', 'monthly', '2025-08-19 19:30:00', NULL, 99.99, 'USD', 'Cash', NULL, '0000-00-00 00:00:00', NULL, '2025-08-19 19:30:00', '2025-08-30 07:39:31');

-- --------------------------------------------------------

--
-- Table structure for table `testimonials`
--

CREATE TABLE `testimonials` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `photo` varchar(255) DEFAULT NULL,
  `testimonial` mediumtext DEFAULT NULL,
  `destination` varchar(255) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `testimonials`
--

INSERT INTO `testimonials` (`id`, `tenant_id`, `name`, `photo`, `testimonial`, `destination`, `rating`, `active`, `created_at`, `updated_at`) VALUES
(4, 1, 'Ahmad Rahimi', '', 'MTravels has completely transformed our travel agency operations. The flight booking system is incredibly efficient and our customer satisfaction has increased by 40%.', 'Dubai', 5, 1, '2025-09-09 04:04:20', '2025-09-09 04:04:20'),
(5, 1, 'Fawad Hassan', '', 'The visa processing feature is a game-changer. What used to take days now takes hours. Our clients love the transparency and speed of the process.', 'Turkey', 5, 1, '2025-09-09 04:04:20', '2025-09-09 04:04:20'),
(6, 1, 'Mohammad Ali', '', 'Outstanding hotel booking system with real-time availability. The integration with major hotel chains has made our job so much easier.', 'Malaysia', 5, 1, '2025-09-09 04:04:20', '2025-09-09 04:04:20'),
(7, 1, 'Ali Khan', '', 'The financial management tools are excellent. Multi-currency support and automated invoicing have streamlined our accounting processes significantly.', 'UAE', 4, 1, '2025-09-09 04:04:20', '2025-09-09 04:04:20'),
(8, 1, 'Omar Farooq', '', 'Customer management has never been easier. The CRM features help us personalize experiences and track customer preferences effectively.', 'Saudi Arabia', 5, 1, '2025-09-09 04:04:20', '2025-09-09 04:04:20'),
(9, 1, 'Zakir Ahmad', '', 'The analytics dashboard provides valuable insights into our business performance. We can now make data-driven decisions to grow our agency.', 'Pakistan', 4, 1, '2025-09-09 04:04:20', '2025-09-09 04:04:20');

-- --------------------------------------------------------

--
-- Table structure for table `ticket_bookings`
--

CREATE TABLE `ticket_bookings` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `group_booking_id` int(11) DEFAULT NULL,
  `supplier` varchar(255) NOT NULL,
  `sold_to` int(10) NOT NULL,
  `paid_to` int(10) NOT NULL,
  `title` varchar(10) NOT NULL,
  `passenger_name` varchar(255) NOT NULL,
  `pnr` varchar(100) NOT NULL,
  `origin` varchar(100) NOT NULL,
  `destination` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `airline` varchar(100) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `issue_date` date NOT NULL,
  `departure_date` date NOT NULL,
  `currency` varchar(10) NOT NULL,
  `price` decimal(10,3) NOT NULL,
  `sold` decimal(10,3) NOT NULL,
  `discount` decimal(10,3) DEFAULT NULL,
  `profit` decimal(10,3) NOT NULL,
  `status` enum('Borrowed','Paid','Date Changed','Refunded','Booked') DEFAULT 'Booked',
  `receipt` varchar(10) NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `trip_type` enum('one_way','round_trip') NOT NULL DEFAULT 'one_way',
  `return_date` date DEFAULT NULL,
  `return_origin` varchar(100) DEFAULT NULL,
  `return_destination` varchar(100) DEFAULT NULL,
  `created_by` int(20) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_bookings`
--

INSERT INTO `ticket_bookings` (`id`, `tenant_id`, `group_booking_id`, `supplier`, `sold_to`, `paid_to`, `title`, `passenger_name`, `pnr`, `origin`, `destination`, `phone`, `airline`, `gender`, `issue_date`, `departure_date`, `currency`, `price`, `sold`, `discount`, `profit`, `status`, `receipt`, `description`, `created_at`, `updated_at`, `trip_type`, `return_date`, `return_origin`, `return_destination`, `created_by`) VALUES
(325, 1, NULL, '27', 19, 11, 'Mr', 'FAZALHAQ PARDES', '188JZ0', 'MZR', 'FRA', '0700907993', 'I5', 'Male', '2025-09-22', '2025-09-24', 'USD', 100.000, 120.000, 0.000, 20.000, 'Booked', '', 'test', '2025-09-22 12:06:34', '2025-09-22 12:06:34', 'one_way', '0000-00-00', NULL, '', 1),
(329, 1, NULL, '29', 19, 11, 'Mr', 'SHIRIN AGHA MUTAWAKIL', 'HAUPSE', ' KBL', 'ISB', '0771781576', 'FG', 'Male', '2025-09-23', '2025-10-02', 'AFS', 10000.000, 15000.000, 0.000, 5000.000, 'Date Changed', '', 'test', '2025-09-23 03:54:20', '2025-09-23 05:54:12', 'one_way', '0000-00-00', NULL, '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `ticket_reservations`
--

CREATE TABLE `ticket_reservations` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `supplier` varchar(255) NOT NULL,
  `sold_to` int(10) NOT NULL,
  `paid_to` int(10) NOT NULL,
  `title` varchar(10) NOT NULL,
  `passenger_name` varchar(255) NOT NULL,
  `pnr` varchar(100) NOT NULL,
  `origin` varchar(100) NOT NULL,
  `destination` varchar(100) NOT NULL,
  `phone` varchar(15) NOT NULL,
  `airline` varchar(100) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `issue_date` date NOT NULL,
  `departure_date` date NOT NULL,
  `currency` varchar(10) NOT NULL,
  `price` decimal(10,3) NOT NULL,
  `sold` decimal(10,3) NOT NULL,
  `profit` decimal(10,3) NOT NULL,
  `status` enum('Reserved','Paid','Date Changed','Refunded') DEFAULT 'Reserved',
  `receipt` varchar(10) NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `trip_type` enum('one_way','round_trip') NOT NULL DEFAULT 'one_way',
  `return_date` date DEFAULT NULL,
  `return_origin` varchar(100) DEFAULT NULL,
  `return_destination` varchar(100) DEFAULT NULL,
  `created_by` int(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_reservations`
--

INSERT INTO `ticket_reservations` (`id`, `tenant_id`, `supplier`, `sold_to`, `paid_to`, `title`, `passenger_name`, `pnr`, `origin`, `destination`, `phone`, `airline`, `gender`, `issue_date`, `departure_date`, `currency`, `price`, `sold`, `profit`, `status`, `receipt`, `description`, `created_at`, `updated_at`, `trip_type`, `return_date`, `return_origin`, `return_destination`, `created_by`) VALUES
(13, 1, '27', 18, 11, 'Mr', 'BAKHTIAR STANIKZAI', '188JZ0', 'MZR', 'FRA', '0777305730', 'EI', 'Male', '2025-09-23', '2025-09-26', 'USD', 0.000, 50.000, 50.000, 'Reserved', '', 'test', '2025-09-23 08:17:50', '2025-09-23 08:17:50', 'one_way', '0000-00-00', NULL, '', 1),
(14, 1, '27', 19, 11, 'Mr', 'HAMID ACHAKZAI ', 'SZQXJU', 'Kabul', 'ISB', '0775172181', 'QZ', 'Male', '2025-09-23', '2025-09-25', 'USD', 0.000, 50.000, 50.000, 'Reserved', '', 'Ticket booked for Mr SIDDIQULALAH STANIKZAI with PNR: HAUPSE from Jeddah - JED to Kabul - KBL.TKT ISSUED FOR WAIKING CUSTOMER / JED KBL UMRA DATE CHANGE ISSUED FROM ZIA TRAVEL', '2025-09-23 08:19:06', '2025-09-23 08:19:06', 'one_way', '0000-00-00', NULL, '', 1),
(15, 1, '29', 19, 11, 'Mr', 'SIDDIQULALAH STANIKZAI', 'WKEPD1', 'MZR', 'ISB', '0780310431', 'D7', 'Male', '2025-09-23', '2025-09-26', 'AFS', 1000.000, 6000.000, 5000.000, 'Reserved', '', 'test', '2025-09-23 09:06:13', '2025-09-23 09:11:28', 'one_way', '0000-00-00', '', '', 1);

-- --------------------------------------------------------

--
-- Table structure for table `ticket_weights`
--

CREATE TABLE `ticket_weights` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `weight` decimal(10,2) NOT NULL COMMENT 'Weight in kilograms',
  `base_price` decimal(10,2) NOT NULL,
  `sold_price` decimal(10,2) NOT NULL,
  `profit` decimal(10,2) NOT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(50) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_weights`
--

INSERT INTO `ticket_weights` (`id`, `tenant_id`, `ticket_id`, `weight`, `base_price`, `sold_price`, `profit`, `remarks`, `created_by`, `created_at`, `updated_at`) VALUES
(12, 1, 329, 20.00, 1000.00, 2000.00, 1000.00, 'tet', 0, '2025-09-23 10:24:31', '2025-09-23 12:05:14');

-- --------------------------------------------------------

--
-- Table structure for table `totp_recovery_codes`
--

CREATE TABLE `totp_recovery_codes` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('staff','client') NOT NULL,
  `recovery_code` varchar(20) NOT NULL,
  `is_used` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `used_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `totp_recovery_codes`
--

INSERT INTO `totp_recovery_codes` (`id`, `tenant_id`, `user_id`, `user_type`, `recovery_code`, `is_used`, `created_at`, `used_at`) VALUES
(33, 1, 1, 'staff', 'KXX6-V7JW-JT36-AQHT', 0, '2025-08-26 13:34:22', NULL),
(34, 1, 1, 'staff', '8XNW-FWZL-K1WY-H1H7', 0, '2025-08-26 13:34:22', NULL),
(35, 1, 1, 'staff', '1PO5-O3G9-T88O-SAO7', 0, '2025-08-26 13:34:22', NULL),
(36, 1, 1, 'staff', '4EHI-7VPQ-6SVN-955Y', 0, '2025-08-26 13:34:22', NULL),
(37, 1, 1, 'staff', 'Y15E-PX9N-7SNM-70EQ', 0, '2025-08-26 13:34:22', NULL),
(38, 1, 1, 'staff', 'BSYW-TVQ2-A8OH-XNUP', 0, '2025-08-26 13:34:22', NULL),
(39, 1, 1, 'staff', 'WKF8-JRO2-P21I-3JMS', 0, '2025-08-26 13:34:22', NULL),
(40, 1, 1, 'staff', 'XIVN-XVAP-WJXJ-MF4S', 0, '2025-08-26 13:34:22', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `totp_secrets`
--

CREATE TABLE `totp_secrets` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `user_type` enum('staff','client') NOT NULL,
  `secret` varchar(255) NOT NULL,
  `is_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `last_used` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `totp_secrets`
--

INSERT INTO `totp_secrets` (`id`, `tenant_id`, `user_id`, `user_type`, `secret`, `is_enabled`, `created_at`, `last_used`) VALUES
(9, 1, 1, 'staff', 'F2K56HUCIFQ4YVVBLBL4N6CQ6AJHXXFAI4TFXRG3SYEJYINWXT74A4FPR6XZBIJ42U7JSF7IGWQVTWU7PTA73DMX25BQN77V5FT66PI', 0, '2025-08-26 13:34:22', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `umrah_agreements`
--

CREATE TABLE `umrah_agreements` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `umrah_agreements`
--

INSERT INTO `umrah_agreements` (`id`, `tenant_id`, `booking_id`, `filename`, `created_by`, `created_at`) VALUES
(13, 1, 47, 'umrah_agreement_KamAir_2025-09-02_140655.pdf', 1, '2025-09-02 16:36:55'),
(14, 1, 47, 'umrah_agreement_KamAir_2025-09-02_140728.pdf', 1, '2025-09-02 16:37:28'),
(15, 1, 47, 'umrah_agreement_KamAir_2025-09-02_142524.pdf', 1, '2025-09-02 16:55:24'),
(16, 1, 55, 'umrah_agreement_Matiullah_2025-09-23_131320.pdf', 1, '2025-09-23 15:43:20'),
(17, 1, 55, 'umrah_agreement_Matiullah_2025-09-25_131149.pdf', 1, '2025-09-25 15:41:49');

-- --------------------------------------------------------

--
-- Table structure for table `umrah_bookings`
--

CREATE TABLE `umrah_bookings` (
  `booking_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `family_id` int(11) DEFAULT NULL,
  `sold_to` int(11) NOT NULL,
  `paid_to` int(11) NOT NULL,
  `entry_date` date DEFAULT NULL,
  `name` varchar(50) DEFAULT NULL,
  `fname` varchar(50) NOT NULL,
  `gfname` varchar(50) NOT NULL,
  `relation` varchar(50) NOT NULL,
  `dob` date DEFAULT NULL,
  `gender` varchar(10) DEFAULT NULL COMMENT 'Gender of pilgrim (Male/Female)',
  `passport_number` varchar(20) DEFAULT NULL,
  `passport_expiry` date DEFAULT NULL COMMENT 'Passport expiry date - must be valid for at least 6 months',
  `id_type` varchar(50) DEFAULT NULL,
  `flight_date` date DEFAULT NULL,
  `return_date` date DEFAULT NULL,
  `duration` varchar(11) DEFAULT NULL,
  `room_type` varchar(50) DEFAULT NULL,
  `price` decimal(10,3) DEFAULT NULL,
  `sold_price` decimal(10,3) DEFAULT NULL,
  `discount` decimal(10,3) NOT NULL,
  `profit` decimal(10,3) DEFAULT NULL,
  `received_bank_payment` decimal(10,3) DEFAULT NULL,
  `bank_receipt_number` varchar(50) DEFAULT NULL,
  `paid` decimal(10,3) DEFAULT NULL,
  `due` decimal(10,3) DEFAULT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `created_by` int(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `remarks` varchar(100) NOT NULL,
  `status` enum('active','refunded') NOT NULL DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `umrah_bookings`
--

INSERT INTO `umrah_bookings` (`booking_id`, `tenant_id`, `family_id`, `sold_to`, `paid_to`, `entry_date`, `name`, `fname`, `gfname`, `relation`, `dob`, `gender`, `passport_number`, `passport_expiry`, `id_type`, `flight_date`, `return_date`, `duration`, `room_type`, `price`, `sold_price`, `discount`, `profit`, `received_bank_payment`, `bank_receipt_number`, `paid`, `due`, `currency`, `created_by`, `created_at`, `updated_at`, `remarks`, `status`) VALUES
(55, 1, 12, 19, 11, '2025-09-23', 'Matiullah', 'FAIZ MOHAMMAD', 'ESMAT ULLAH', 'Cousin', '2025-09-23', 'Male', 'P07592390', '2026-04-02', 'ID Original + Passport Original', '2025-09-01', '2025-09-20', '19 Days', '3 Beds', 500.000, 1000.000, 0.000, 500.000, 0.000, '', 377.624, 622.376, 'USD', 1, '2025-09-23 10:49:46', '2025-09-28 09:59:35', 'terts', 'active'),
(56, 1, 13, 19, 11, '2025-09-23', 'Idrees', 'MOHAMMAD SIDDIQ AHMADZAI ', 'HAJI MIR GHOUSUDDIN ', 'Daughter-in-law', '2025-09-23', 'Male', 'P07592390', '2026-04-09', 'ID Original + Passport Original', '0000-00-00', '0000-00-00', '22 Days', 'Shared', 10000.000, 12000.000, 0.000, 0.000, 0.000, '', 9250.000, 0.000, 'AFS', 1, '2025-09-23 12:02:39', '2025-09-27 08:21:10', 'test', 'refunded'),
(61, 1, 12, 19, 11, '2025-09-28', 'DR SAHIB', 'RAHIMI', 'HAJI MIR GHOUSUDDIN', 'Cousin', '2025-09-22', 'Male', 'P00130999', '2026-04-09', 'ID Original + Passport Original', '2025-09-01', '2025-09-28', '27 Days', '3 Beds', 130.000, 160.000, 0.000, 30.000, 0.000, '217687', 80.000, 80.000, 'USD', 1, '2025-09-28 06:39:11', '2025-09-28 10:49:52', 'adfasdfasdf', 'active');

--
-- Triggers `umrah_bookings`
--
DELIMITER $$
CREATE TRIGGER `after_booking_delete` AFTER DELETE ON `umrah_bookings` FOR EACH ROW BEGIN
    UPDATE families f
    SET 
        f.total_members = (SELECT COUNT(*) FROM umrah_bookings ub WHERE ub.family_id = OLD.family_id),
        f.total_price = (SELECT COALESCE(SUM(ub.sold_price), 0) FROM umrah_bookings ub WHERE ub.family_id = OLD.family_id),
        f.total_paid = (SELECT COALESCE(SUM(ub.paid), 0) FROM umrah_bookings ub WHERE ub.family_id = OLD.family_id),
        f.total_paid_to_bank = (SELECT COALESCE(SUM(ub.received_bank_payment), 0) FROM umrah_bookings ub WHERE ub.family_id = OLD.family_id),
        f.total_due = (SELECT COALESCE(SUM(ub.due), 0) FROM umrah_bookings ub WHERE ub.family_id = OLD.family_id)
    WHERE f.family_id = OLD.family_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_booking_insert` AFTER INSERT ON `umrah_bookings` FOR EACH ROW BEGIN
    UPDATE families f
    SET 
        f.total_members = (SELECT COUNT(*) FROM umrah_bookings ub WHERE ub.family_id = NEW.family_id),
        f.total_price = (SELECT COALESCE(SUM(ub.sold_price), 0) FROM umrah_bookings ub WHERE ub.family_id = NEW.family_id),
        f.total_paid = (SELECT COALESCE(SUM(ub.paid), 0) FROM umrah_bookings ub WHERE ub.family_id = NEW.family_id),
        f.total_paid_to_bank = (SELECT COALESCE(SUM(ub.received_bank_payment), 0) FROM umrah_bookings ub WHERE ub.family_id = NEW.family_id),
        f.total_due = (SELECT COALESCE(SUM(ub.due), 0) FROM umrah_bookings ub WHERE ub.family_id = NEW.family_id)
    WHERE f.family_id = NEW.family_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `after_booking_update` AFTER UPDATE ON `umrah_bookings` FOR EACH ROW BEGIN
    UPDATE families f
    SET 
        f.total_members = (SELECT COUNT(*) FROM umrah_bookings ub WHERE ub.family_id = NEW.family_id),
        f.total_paid = (SELECT COALESCE(SUM(ub.paid), 0) FROM umrah_bookings ub WHERE ub.family_id = NEW.family_id),
        f.total_paid_to_bank = (SELECT COALESCE(SUM(ub.received_bank_payment), 0) FROM umrah_bookings ub WHERE ub.family_id = NEW.family_id),
        f.total_due = (SELECT COALESCE(SUM(ub.due), 0) FROM umrah_bookings ub WHERE ub.family_id = NEW.family_id)
    WHERE f.family_id = NEW.family_id;
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `before_umrah_booking_update` BEFORE UPDATE ON `umrah_bookings` FOR EACH ROW BEGIN
    -- Ensure due is recalculated only when paid is updated
    IF NEW.paid <> OLD.paid THEN
        SET NEW.due = NEW.sold_price - NEW.paid;
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `umrah_booking_services`
--

CREATE TABLE `umrah_booking_services` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `service_type` enum('ticket','visa','hotel','transport','all') NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `base_price` decimal(10,3) NOT NULL DEFAULT 0.000,
  `sold_price` decimal(10,3) NOT NULL DEFAULT 0.000,
  `profit` decimal(10,3) NOT NULL DEFAULT 0.000,
  `currency` enum('USD','AFS') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `umrah_booking_services`
--

INSERT INTO `umrah_booking_services` (`id`, `tenant_id`, `booking_id`, `service_type`, `supplier_id`, `base_price`, `sold_price`, `profit`, `currency`, `created_at`, `updated_at`) VALUES
(1, 1, 55, 'ticket', 27, 500.000, 1000.000, 500.000, 'USD', '2025-09-27 08:21:10', '2025-09-27 08:21:10'),
(2, 1, 56, 'ticket', 29, 10000.000, 12000.000, 0.000, 'AFS', '2025-09-27 08:21:10', '2025-09-27 08:21:10'),
(21, 1, 61, 'all', 27, 130.000, 160.000, 30.000, 'USD', '2025-09-28 10:41:05', '2025-09-28 10:41:05');

-- --------------------------------------------------------

--
-- Table structure for table `umrah_refunds`
--

CREATE TABLE `umrah_refunds` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `refund_type` enum('full','partial') NOT NULL,
  `refund_amount` decimal(10,2) NOT NULL,
  `reason` text NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `processed` tinyint(1) DEFAULT 0,
  `processed_by` int(11) DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `umrah_refunds`
--

INSERT INTO `umrah_refunds` (`id`, `tenant_id`, `booking_id`, `refund_type`, `refund_amount`, `reason`, `currency`, `processed`, `processed_by`, `transaction_id`, `created_at`, `updated_at`) VALUES
(21, 1, 56, 'full', 12000.00, 'utytt', 'AFS', 0, NULL, NULL, '2025-09-25 10:11:27', '2025-09-25 10:11:27');

-- --------------------------------------------------------

--
-- Table structure for table `umrah_transactions`
--

CREATE TABLE `umrah_transactions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `umrah_booking_id` int(11) NOT NULL,
  `transaction_type` enum('Debit','Credit','','') NOT NULL,
  `transaction_to` varchar(50) NOT NULL,
  `payment_date` date NOT NULL,
  `payment_description` varchar(255) DEFAULT NULL,
  `payment_amount` decimal(10,3) NOT NULL,
  `receipt` varchar(10) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `exchange_rate` decimal(10,3) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `umrah_transactions`
--

INSERT INTO `umrah_transactions` (`id`, `tenant_id`, `umrah_booking_id`, `transaction_type`, `transaction_to`, `payment_date`, `payment_description`, `payment_amount`, `receipt`, `currency`, `exchange_rate`, `created_at`) VALUES
(76, 1, 55, 'Credit', 'Internal Account', '2025-09-23', 'test', 100.000, '', 'USD', NULL, '2025-09-23 10:50:34'),
(77, 1, 55, 'Credit', 'Internal Account', '2025-09-23', 'test', 7000.000, '', 'AFS', 70.000, '2025-09-23 10:50:52'),
(78, 1, 55, 'Credit', 'Internal Account', '2025-09-23', '', 110.000, '', 'EUR', 0.900, '2025-09-23 10:51:19'),
(79, 1, 55, 'Credit', 'Internal Account', '2025-09-23', 'tets', 200.000, '', 'DAR', 3.610, '2025-09-23 10:51:56'),
(80, 1, 56, 'Credit', 'Internal Account', '2025-09-23', 'test', 1000.000, '', 'AFS', NULL, '2025-09-23 12:04:41'),
(81, 1, 56, 'Credit', 'Internal Account', '2025-09-23', 'test', 50.000, '', 'USD', 70.000, '2025-09-23 12:10:09'),
(82, 1, 56, 'Credit', 'Internal Account', '2025-09-23', 'test', 50.000, '', 'EUR', 77.000, '2025-09-23 12:11:12'),
(83, 1, 56, 'Credit', 'Internal Account', '2025-09-23', 'test', 50.000, '', 'DAR', 18.000, '2025-09-23 12:12:23'),
(84, 1, 61, 'Credit', 'Internal Account', '2025-09-28', 'dsfsa', 20.000, '', 'USD', NULL, '2025-09-28 09:40:35'),
(85, 1, 61, 'Credit', 'Internal Account', '2025-09-28', 'erdfgv', 10.000, '', 'USD', NULL, '2025-09-28 09:53:11'),
(86, 1, 61, 'Credit', 'Internal Account', '2025-09-28', 'adfdsf', 10.000, '', 'USD', NULL, '2025-09-28 09:58:15'),
(87, 1, 61, 'Credit', 'Internal Account', '2025-09-28', 'dghdf', 10.000, '', 'USD', NULL, '2025-09-28 10:00:25'),
(88, 1, 61, 'Credit', 'Bank', '2025-09-28', 'teted', 10.000, '2452345', 'USD', NULL, '2025-09-28 10:01:22'),
(89, 1, 61, 'Credit', 'Bank', '2025-09-28', 'fstgsdf', 10.000, '6524652', 'USD', NULL, '2025-09-28 10:03:21'),
(90, 1, 61, 'Credit', 'Internal Account', '2025-09-28', 'vbscxv', 10.000, '', 'USD', NULL, '2025-09-28 10:15:18');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `role` varchar(50) DEFAULT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `address` mediumtext DEFAULT NULL,
  `hire_date` date DEFAULT NULL,
  `profile_pic` varchar(255) DEFAULT 'assets/images/user/avatar-2.jpg',
  `totp_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `fired` tinyint(1) DEFAULT 0,
  `fired_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `tenant_id`, `name`, `email`, `password`, `created_at`, `role`, `phone`, `address`, `hire_date`, `profile_pic`, `totp_enabled`, `fired`, `fired_at`, `deleted_at`) VALUES
(1, 1, 'Sabaoon', 'almuqadas_travel@yahoo.com', '$2y$10$GvGY.vFB3m8xE/a5OaCVzuWgb.bGOr/6GyqUuLUMjsbx4a2D1uHV2', '2024-12-24 13:11:23', 'admin', '0786011115', 'kabul, jada-ee-mewand', '2024-12-04', '676d4e9f7b06e7.57761284Capture45.PNG', 0, 0, NULL, NULL),
(6, 1, 'Idrees', 'idress@gmail.com', '$2y$10$IKpXU4ZmD.QbVVXKNbMtXOGfaYLkYZXRacwfiQd67WPVlG3/DYEJu', '2025-04-09 10:29:29', 'umrah', '0777555594', 'Jada-e-Maiwand', '2025-04-09', '67f60cc0da261.jpg', 0, 0, NULL, NULL),
(7, 2, 'Matiullah Rahimi', 'mati@gmail.com', '$2y$10$Yr61g65gW/w8UjFfG3kLmephVrFKf2m6zGqMrTdgvmPcy8pg/Bv/e', '2025-04-09 10:30:25', 'admin', '0777555594', 'Jada-e-Maiwand', '2025-04-09', '68bd507105c6e_Blue and White Grunge Travel and Tourism Instagram Post.png', 0, 0, '2025-09-01 13:22:36', NULL),
(8, 1, 'Umrah', 'umrah@gmail.com', '$2y$10$GpFOnhGmF/iBrZ/D7sM9k.UWSxuphmfZyiMNLH8N8yBFMCklga5ou', '2025-04-09 10:30:56', 'umrah', '0777555594', 'Jada-e-Maiwand', '2025-04-09', '680dcddf08e7a_Orange and Blue Modern Flight Ticket Promo Facebook Cover.png', 0, 0, '2025-09-25 13:11:32', NULL),
(14, NULL, 'ALLAH DAD MUHAMMADI', 'allahdadmuhammadi01@gmail.com', '$2y$10$5BbWc37e43gokcY5etVUauiZZFP/uLeYQrGFJaUkrEGSxvvXnsQnS', '2025-05-19 08:31:47', 'super_admin', '0780310431', 'KABUL AFGHANISTAN', '2025-05-19', '682aadaaef90b.jpg', 0, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `user_agreements`
--

CREATE TABLE `user_agreements` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `agreement_type` enum('employment','confidentiality','performance') NOT NULL,
  `position` varchar(100) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_blocks`
--

CREATE TABLE `user_blocks` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `blocked_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_documents`
--

CREATE TABLE `user_documents` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_type` varchar(50) NOT NULL,
  `uploaded_at` datetime NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_mutes`
--

CREATE TABLE `user_mutes` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `muted_user_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `visa_applications`
--

CREATE TABLE `visa_applications` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `supplier` int(11) NOT NULL,
  `sold_to` int(11) NOT NULL,
  `paid_to` int(10) NOT NULL,
  `phone` varchar(20) NOT NULL,
  `title` enum('Mr','Mrs','Child') NOT NULL,
  `gender` enum('Male','Female') NOT NULL,
  `applicant_name` varchar(100) NOT NULL,
  `passport_number` varchar(50) NOT NULL,
  `country` varchar(50) NOT NULL,
  `visa_type` varchar(50) NOT NULL,
  `receive_date` date NOT NULL,
  `applied_date` date NOT NULL,
  `issued_date` date DEFAULT NULL,
  `base` decimal(10,3) NOT NULL,
  `sold` decimal(10,3) NOT NULL,
  `profit` decimal(10,3) DEFAULT NULL,
  `currency` varchar(10) NOT NULL,
  `status` varchar(50) DEFAULT 'Pending',
  `remarks` mediumtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `visa_applications`
--

INSERT INTO `visa_applications` (`id`, `tenant_id`, `supplier`, `sold_to`, `paid_to`, `phone`, `title`, `gender`, `applicant_name`, `passport_number`, `country`, `visa_type`, `receive_date`, `applied_date`, `issued_date`, `base`, `sold`, `profit`, `currency`, `status`, `remarks`, `created_at`, `updated_at`, `created_by`) VALUES
(53, 1, 27, 19, 11, '0780119316', 'Mr', 'Male', 'BAKHTIAR STANIKZAI', 'P8798765', 'Pakistan', 'Tourist', '2025-09-25', '2025-09-26', '0000-00-00', 100.000, 120.000, 20.000, 'USD', 'Pending', 'test', '2025-09-25 11:26:02', '2025-09-25 11:26:02', 1),
(54, 1, 29, 19, 11, '0775172181', 'Mr', 'Male', 'HAMID ACHAKZAI ', 'P879876', 'Pakistan', 'Tourist', '2025-09-25', '2025-09-25', '0000-00-00', 2000.000, 2500.000, 0.000, 'AFS', 'refunded', 'test', '2025-09-25 11:32:51', '2025-09-25 11:39:24', 1);

-- --------------------------------------------------------

--
-- Table structure for table `visa_refunds`
--

CREATE TABLE `visa_refunds` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `visa_id` int(11) NOT NULL,
  `refund_type` varchar(50) NOT NULL,
  `refund_amount` decimal(10,2) NOT NULL,
  `reason` mediumtext NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `refund_date` datetime DEFAULT current_timestamp(),
  `processed` tinyint(1) DEFAULT 0,
  `processed_by` int(11) DEFAULT NULL,
  `transaction_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `visa_refunds`
--

INSERT INTO `visa_refunds` (`id`, `tenant_id`, `visa_id`, `refund_type`, `refund_amount`, `reason`, `currency`, `refund_date`, `processed`, `processed_by`, `transaction_id`) VALUES
(5, 1, 54, 'full', 2500.00, 'test', 'AFS', '2025-09-25 16:09:24', 0, 1, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `activity_log_user_id_index` (`user_id`),
  ADD KEY `activity_log_table_name_index` (`table_name`),
  ADD KEY `activity_log_action_index` (`action`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `additional_payments`
--
ALTER TABLE `additional_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Indexes for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_slug` (`tenant_id`,`slug`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `budget_allocations`
--
ALTER TABLE `budget_allocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `main_account_id` (`main_account_id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_time` (`room_id`,`created_at`),
  ADD KEY `idx_to_user` (`to_user_id`),
  ADD KEY `fk_cm_from_user` (`from_user_id`);

--
-- Indexes for table `clients`
--
ALTER TABLE `clients`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_email` (`tenant_id`,`email`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `client_transactions`
--
ALTER TABLE `client_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`client_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `contact_messages`
--
ALTER TABLE `contact_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_status_created` (`status`,`created_at`);

--
-- Indexes for table `creditors`
--
ALTER TABLE `creditors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `creditor_transactions`
--
ALTER TABLE `creditor_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `creditor_id` (`creditor_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `customer_wallets`
--
ALTER TABLE `customer_wallets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_unique_customer_currency` (`tenant_id`,`customer_id`,`currency`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `date_change_tickets`
--
ALTER TABLE `date_change_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `date_change_umrah`
--
ALTER TABLE `date_change_umrah`
  ADD PRIMARY KEY (`id`),
  ADD KEY `umrah_booking_id` (`umrah_booking_id`),
  ADD KEY `family_id` (`family_id`),
  ADD KEY `supplier` (`supplier`),
  ADD KEY `sold_to` (`sold_to`),
  ADD KEY `paid_to` (`paid_to`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_status_created` (`status`,`created_at`);

--
-- Indexes for table `deals`
--
ALTER TABLE `deals`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `debtors`
--
ALTER TABLE `debtors`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_debtors_main_account` (`main_account_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `debtor_transactions`
--
ALTER TABLE `debtor_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `debtor_id` (`debtor_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `debt_records`
--
ALTER TABLE `debt_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `demo_requests`
--
ALTER TABLE `demo_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email` (`email`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Indexes for table `destinations`
--
ALTER TABLE `destinations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `exchange_rates`
--
ALTER TABLE `exchange_rates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_currency_pair` (`from_currency`,`to_currency`);

--
-- Indexes for table `exchange_transactions`
--
ALTER TABLE `exchange_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`);

--
-- Indexes for table `expenses`
--
ALTER TABLE `expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `families`
--
ALTER TABLE `families`
  ADD PRIMARY KEY (`family_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `family_cancellations`
--
ALTER TABLE `family_cancellations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `funding_transactions`
--
ALTER TABLE `funding_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `general_ledger`
--
ALTER TABLE `general_ledger`
  ADD PRIMARY KEY (`id`),
  ADD KEY `transaction_id` (`transaction_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `hawala_transfers`
--
ALTER TABLE `hawala_transfers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_transaction_id` (`sender_transaction_id`),
  ADD KEY `receiver_transaction_id` (`receiver_transaction_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `hotel_bookings`
--
ALTER TABLE `hotel_bookings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_order_id` (`tenant_id`,`order_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `hotel_refunds`
--
ALTER TABLE `hotel_refunds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `invoices`
--
ALTER TABLE `invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_invoice_number` (`tenant_id`,`invoice_number`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `jv_payments`
--
ALTER TABLE `jv_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `jv_transactions`
--
ALTER TABLE `jv_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `jv_transactions_ibfk_1` (`jv_payment_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `email` (`email`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `login_history`
--
ALTER TABLE `login_history`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `main_account`
--
ALTER TABLE `main_account`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `main_account_transactions`
--
ALTER TABLE `main_account_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `client_id` (`main_account_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `maktobs`
--
ALTER TABLE `maktobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `messages`
--
ALTER TABLE `messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `payroll_details`
--
ALTER TABLE `payroll_details`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `payroll_user` (`payroll_id`,`user_id`),
  ADD UNIQUE KEY `tenant_payroll_user` (`tenant_id`,`payroll_id`,`user_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `payroll_records`
--
ALTER TABLE `payroll_records`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_pay_period` (`tenant_id`,`pay_period`,`currency`),
  ADD KEY `generated_by` (`generated_by`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `plans`
--
ALTER TABLE `plans`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `platform_settings`
--
ALTER TABLE `platform_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `key` (`key`);

--
-- Indexes for table `refunded_tickets`
--
ALTER TABLE `refunded_tickets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `salary_adjustments`
--
ALTER TABLE `salary_adjustments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `salary_advances`
--
ALTER TABLE `salary_advances`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `main_account_id` (`main_account_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `salary_bonuses`
--
ALTER TABLE `salary_bonuses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `salary_deductions`
--
ALTER TABLE `salary_deductions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `salary_management`
--
ALTER TABLE `salary_management`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_user_id` (`tenant_id`,`user_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `salary_payments`
--
ALTER TABLE `salary_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `main_account_id` (`main_account_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `sarafi_transactions`
--
ALTER TABLE `sarafi_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `subscription_id` (`subscription_id`),
  ADD KEY `processed_by` (`processed_by`);

--
-- Indexes for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `supplier_transactions`
--
ALTER TABLE `supplier_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `identifier` (`identifier`);

--
-- Indexes for table `tenant_peering`
--
ALTER TABLE `tenant_peering`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_peer_unique` (`tenant_id`,`peer_tenant_id`),
  ADD KEY `fk_tp_tenant` (`tenant_id`),
  ADD KEY `fk_tp_peer` (`peer_tenant_id`);

--
-- Indexes for table `tenant_subscriptions`
--
ALTER TABLE `tenant_subscriptions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_plan_unique` (`tenant_id`,`plan_id`,`start_date`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `ticket_bookings`
--
ALTER TABLE `ticket_bookings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `ticket_reservations`
--
ALTER TABLE `ticket_reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `ticket_weights`
--
ALTER TABLE `ticket_weights`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `totp_recovery_codes`
--
ALTER TABLE `totp_recovery_codes`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`,`user_type`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `totp_secrets`
--
ALTER TABLE `totp_secrets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_user_unique` (`tenant_id`,`user_id`,`user_type`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `umrah_agreements`
--
ALTER TABLE `umrah_agreements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `umrah_bookings`
--
ALTER TABLE `umrah_bookings`
  ADD PRIMARY KEY (`booking_id`),
  ADD KEY `family_id` (`family_id`),
  ADD KEY `idx_passport_expiry` (`passport_expiry`),
  ADD KEY `idx_gender` (`gender`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `umrah_booking_services`
--
ALTER TABLE `umrah_booking_services`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `service_type` (`service_type`);

--
-- Indexes for table `umrah_refunds`
--
ALTER TABLE `umrah_refunds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `booking_id` (`booking_id`),
  ADD KEY `processed_by` (`processed_by`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `umrah_transactions`
--
ALTER TABLE `umrah_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `visa_id` (`umrah_booking_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_email` (`tenant_id`,`email`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `user_agreements`
--
ALTER TABLE `user_agreements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_agreements_user_id` (`user_id`),
  ADD KEY `idx_user_agreements_created_by` (`created_by`),
  ADD KEY `idx_user_agreements_created_at` (`created_at`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `user_blocks`
--
ALTER TABLE `user_blocks`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_block` (`tenant_id`,`user_id`,`blocked_user_id`),
  ADD KEY `fk_ub_user` (`user_id`),
  ADD KEY `fk_ub_blocked` (`blocked_user_id`);

--
-- Indexes for table `user_documents`
--
ALTER TABLE `user_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `user_mutes`
--
ALTER TABLE `user_mutes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_mute` (`tenant_id`,`user_id`,`muted_user_id`),
  ADD KEY `fk_um_user` (`user_id`),
  ADD KEY `fk_um_muted` (`muted_user_id`);

--
-- Indexes for table `visa_applications`
--
ALTER TABLE `visa_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `visa_refunds`
--
ALTER TABLE `visa_refunds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `visa_id` (`visa_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1750;

--
-- AUTO_INCREMENT for table `additional_payments`
--
ALTER TABLE `additional_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=50;

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `blog_posts`
--
ALTER TABLE `blog_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `budget_allocations`
--
ALTER TABLE `budget_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `client_transactions`
--
ALTER TABLE `client_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=530;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `creditors`
--
ALTER TABLE `creditors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `creditor_transactions`
--
ALTER TABLE `creditor_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `customer_wallets`
--
ALTER TABLE `customer_wallets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `date_change_tickets`
--
ALTER TABLE `date_change_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `date_change_umrah`
--
ALTER TABLE `date_change_umrah`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `deals`
--
ALTER TABLE `deals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `debtors`
--
ALTER TABLE `debtors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `debtor_transactions`
--
ALTER TABLE `debtor_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `debt_records`
--
ALTER TABLE `debt_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `demo_requests`
--
ALTER TABLE `demo_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `destinations`
--
ALTER TABLE `destinations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `exchange_rates`
--
ALTER TABLE `exchange_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exchange_transactions`
--
ALTER TABLE `exchange_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=214;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `families`
--
ALTER TABLE `families`
  MODIFY `family_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=14;

--
-- AUTO_INCREMENT for table `family_cancellations`
--
ALTER TABLE `family_cancellations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `funding_transactions`
--
ALTER TABLE `funding_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `general_ledger`
--
ALTER TABLE `general_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `hawala_transfers`
--
ALTER TABLE `hawala_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `hotel_bookings`
--
ALTER TABLE `hotel_bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `hotel_refunds`
--
ALTER TABLE `hotel_refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=239;

--
-- AUTO_INCREMENT for table `jv_payments`
--
ALTER TABLE `jv_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `jv_transactions`
--
ALTER TABLE `jv_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=116;

--
-- AUTO_INCREMENT for table `main_account`
--
ALTER TABLE `main_account`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `main_account_transactions`
--
ALTER TABLE `main_account_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1130;

--
-- AUTO_INCREMENT for table `maktobs`
--
ALTER TABLE `maktobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `messages`
--
ALTER TABLE `messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=945;

--
-- AUTO_INCREMENT for table `payroll_details`
--
ALTER TABLE `payroll_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `payroll_records`
--
ALTER TABLE `payroll_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `plans`
--
ALTER TABLE `plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `platform_settings`
--
ALTER TABLE `platform_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=200;

--
-- AUTO_INCREMENT for table `refunded_tickets`
--
ALTER TABLE `refunded_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT for table `salary_adjustments`
--
ALTER TABLE `salary_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `salary_advances`
--
ALTER TABLE `salary_advances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `salary_bonuses`
--
ALTER TABLE `salary_bonuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `salary_deductions`
--
ALTER TABLE `salary_deductions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `salary_management`
--
ALTER TABLE `salary_management`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `salary_payments`
--
ALTER TABLE `salary_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `sarafi_transactions`
--
ALTER TABLE `sarafi_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `supplier_transactions`
--
ALTER TABLE `supplier_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=866;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `tenant_peering`
--
ALTER TABLE `tenant_peering`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `tenant_subscriptions`
--
ALTER TABLE `tenant_subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `ticket_bookings`
--
ALTER TABLE `ticket_bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=330;

--
-- AUTO_INCREMENT for table `ticket_reservations`
--
ALTER TABLE `ticket_reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `ticket_weights`
--
ALTER TABLE `ticket_weights`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `totp_recovery_codes`
--
ALTER TABLE `totp_recovery_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `totp_secrets`
--
ALTER TABLE `totp_secrets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `umrah_agreements`
--
ALTER TABLE `umrah_agreements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `umrah_bookings`
--
ALTER TABLE `umrah_bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=62;

--
-- AUTO_INCREMENT for table `umrah_booking_services`
--
ALTER TABLE `umrah_booking_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `umrah_refunds`
--
ALTER TABLE `umrah_refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=22;

--
-- AUTO_INCREMENT for table `umrah_transactions`
--
ALTER TABLE `umrah_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=92;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `user_agreements`
--
ALTER TABLE `user_agreements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `user_blocks`
--
ALTER TABLE `user_blocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `user_documents`
--
ALTER TABLE `user_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_mutes`
--
ALTER TABLE `user_mutes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `visa_applications`
--
ALTER TABLE `visa_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

--
-- AUTO_INCREMENT for table `visa_refunds`
--
ALTER TABLE `visa_refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `fk_activity_log_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `additional_payments`
--
ALTER TABLE `additional_payments`
  ADD CONSTRAINT `additional_payments_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `fk_additional_payments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `assets`
--
ALTER TABLE `assets`
  ADD CONSTRAINT `fk_assets_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `audit_logs`
--
ALTER TABLE `audit_logs`
  ADD CONSTRAINT `fk_audit_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `blog_posts`
--
ALTER TABLE `blog_posts`
  ADD CONSTRAINT `fk_blog_posts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `budget_allocations`
--
ALTER TABLE `budget_allocations`
  ADD CONSTRAINT `budget_allocations_ibfk_1` FOREIGN KEY (`main_account_id`) REFERENCES `main_account` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `budget_allocations_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_budget_allocations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `fk_cm_from_user` FOREIGN KEY (`from_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_cm_to_user` FOREIGN KEY (`to_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `clients`
--
ALTER TABLE `clients`
  ADD CONSTRAINT `fk_clients_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `client_transactions`
--
ALTER TABLE `client_transactions`
  ADD CONSTRAINT `client_transactions_ibfk_1` FOREIGN KEY (`client_id`) REFERENCES `clients` (`id`),
  ADD CONSTRAINT `fk_client_transactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `creditors`
--
ALTER TABLE `creditors`
  ADD CONSTRAINT `fk_creditors_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `creditor_transactions`
--
ALTER TABLE `creditor_transactions`
  ADD CONSTRAINT `creditor_transactions_ibfk_1` FOREIGN KEY (`creditor_id`) REFERENCES `creditors` (`id`),
  ADD CONSTRAINT `fk_creditor_transactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `fk_customers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `customer_wallets`
--
ALTER TABLE `customer_wallets`
  ADD CONSTRAINT `customer_wallets_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `fk_customer_wallets_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `date_change_tickets`
--
ALTER TABLE `date_change_tickets`
  ADD CONSTRAINT `fk_date_change_tickets_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `date_change_umrah`
--
ALTER TABLE `date_change_umrah`
  ADD CONSTRAINT `fk_date_change_umrah_booking` FOREIGN KEY (`umrah_booking_id`) REFERENCES `umrah_bookings` (`booking_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_date_change_umrah_family` FOREIGN KEY (`family_id`) REFERENCES `families` (`family_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_date_change_umrah_paid_to` FOREIGN KEY (`paid_to`) REFERENCES `main_account` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_date_change_umrah_sold_to` FOREIGN KEY (`sold_to`) REFERENCES `clients` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_date_change_umrah_supplier` FOREIGN KEY (`supplier`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_date_change_umrah_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `deals`
--
ALTER TABLE `deals`
  ADD CONSTRAINT `fk_deals_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `debtors`
--
ALTER TABLE `debtors`
  ADD CONSTRAINT `fk_debtors_main_account` FOREIGN KEY (`main_account_id`) REFERENCES `main_account` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_debtors_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `debtor_transactions`
--
ALTER TABLE `debtor_transactions`
  ADD CONSTRAINT `debtor_transactions_ibfk_1` FOREIGN KEY (`debtor_id`) REFERENCES `debtors` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_debtor_transactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `debt_records`
--
ALTER TABLE `debt_records`
  ADD CONSTRAINT `debt_records_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `fk_debt_records_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `destinations`
--
ALTER TABLE `destinations`
  ADD CONSTRAINT `fk_destinations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `exchange_transactions`
--
ALTER TABLE `exchange_transactions`
  ADD CONSTRAINT `exchange_transactions_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `sarafi_transactions` (`id`);

--
-- Constraints for table `expenses`
--
ALTER TABLE `expenses`
  ADD CONSTRAINT `expenses_ibfk_1` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`),
  ADD CONSTRAINT `fk_expenses_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD CONSTRAINT `fk_expense_categories_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `families`
--
ALTER TABLE `families`
  ADD CONSTRAINT `fk_families_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `family_cancellations`
--
ALTER TABLE `family_cancellations`
  ADD CONSTRAINT `fk_family_cancellations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `funding_transactions`
--
ALTER TABLE `funding_transactions`
  ADD CONSTRAINT `fk_funding_transactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `funding_transactions_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `general_ledger`
--
ALTER TABLE `general_ledger`
  ADD CONSTRAINT `fk_general_ledger_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `general_ledger_ibfk_1` FOREIGN KEY (`transaction_id`) REFERENCES `sarafi_transactions` (`id`);

--
-- Constraints for table `hawala_transfers`
--
ALTER TABLE `hawala_transfers`
  ADD CONSTRAINT `fk_hawala_transfers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `hawala_transfers_ibfk_1` FOREIGN KEY (`sender_transaction_id`) REFERENCES `sarafi_transactions` (`id`),
  ADD CONSTRAINT `hawala_transfers_ibfk_2` FOREIGN KEY (`receiver_transaction_id`) REFERENCES `sarafi_transactions` (`id`);

--
-- Constraints for table `hotel_bookings`
--
ALTER TABLE `hotel_bookings`
  ADD CONSTRAINT `fk_hotel_bookings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `hotel_refunds`
--
ALTER TABLE `hotel_refunds`
  ADD CONSTRAINT `fk_hotel_refunds_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `hotel_refunds_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `hotel_bookings` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `hotel_refunds_ibfk_2` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `invoices`
--
ALTER TABLE `invoices`
  ADD CONSTRAINT `fk_invoices_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `jv_payments`
--
ALTER TABLE `jv_payments`
  ADD CONSTRAINT `fk_jv_payments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jv_payments_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `jv_transactions`
--
ALTER TABLE `jv_transactions`
  ADD CONSTRAINT `fk_jv_transactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `jv_transactions_ibfk_1` FOREIGN KEY (`jv_payment_id`) REFERENCES `jv_payments` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD CONSTRAINT `fk_login_attempts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `login_history`
--
ALTER TABLE `login_history`
  ADD CONSTRAINT `fk_login_history_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `login_history_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `main_account`
--
ALTER TABLE `main_account`
  ADD CONSTRAINT `fk_main_account_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `main_account_transactions`
--
ALTER TABLE `main_account_transactions`
  ADD CONSTRAINT `fk_main_account_transactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `maktobs`
--
ALTER TABLE `maktobs`
  ADD CONSTRAINT `fk_maktobs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maktobs_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `messages`
--
ALTER TABLE `messages`
  ADD CONSTRAINT `fk_messages_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `messages_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`);

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_details`
--
ALTER TABLE `payroll_details`
  ADD CONSTRAINT `fk_payroll_details_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_details_ibfk_1` FOREIGN KEY (`payroll_id`) REFERENCES `payroll_records` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_details_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `payroll_records`
--
ALTER TABLE `payroll_records`
  ADD CONSTRAINT `fk_payroll_records_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `payroll_records_ibfk_1` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `refunded_tickets`
--
ALTER TABLE `refunded_tickets`
  ADD CONSTRAINT `fk_refunded_tickets_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `salary_adjustments`
--
ALTER TABLE `salary_adjustments`
  ADD CONSTRAINT `fk_salary_adjustments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `salary_adjustments_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `salary_adjustments_ibfk_2` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `salary_advances`
--
ALTER TABLE `salary_advances`
  ADD CONSTRAINT `fk_salary_advances_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `salary_advances_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `salary_advances_ibfk_2` FOREIGN KEY (`main_account_id`) REFERENCES `main_account` (`id`);

--
-- Constraints for table `salary_bonuses`
--
ALTER TABLE `salary_bonuses`
  ADD CONSTRAINT `fk_salary_bonuses_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `salary_bonuses_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `salary_deductions`
--
ALTER TABLE `salary_deductions`
  ADD CONSTRAINT `fk_salary_deductions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `salary_deductions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `salary_management`
--
ALTER TABLE `salary_management`
  ADD CONSTRAINT `fk_salary_management_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `salary_management_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `salary_payments`
--
ALTER TABLE `salary_payments`
  ADD CONSTRAINT `fk_salary_payments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `salary_payments_ibfk_2` FOREIGN KEY (`main_account_id`) REFERENCES `main_account` (`id`);

--
-- Constraints for table `sarafi_transactions`
--
ALTER TABLE `sarafi_transactions`
  ADD CONSTRAINT `fk_sarafi_transactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sarafi_transactions_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `settings`
--
ALTER TABLE `settings`
  ADD CONSTRAINT `fk_settings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  ADD CONSTRAINT `fk_subscription_payments_subscription` FOREIGN KEY (`subscription_id`) REFERENCES `tenant_subscriptions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_subscription_payments_user` FOREIGN KEY (`processed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `suppliers`
--
ALTER TABLE `suppliers`
  ADD CONSTRAINT `fk_suppliers_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `supplier_transactions`
--
ALTER TABLE `supplier_transactions`
  ADD CONSTRAINT `fk_supplier_transactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tenant_peering`
--
ALTER TABLE `tenant_peering`
  ADD CONSTRAINT `fk_tp_peer` FOREIGN KEY (`peer_tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_tp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tenant_subscriptions`
--
ALTER TABLE `tenant_subscriptions`
  ADD CONSTRAINT `fk_tenant_subscriptions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `testimonials`
--
ALTER TABLE `testimonials`
  ADD CONSTRAINT `fk_testimonials_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_bookings`
--
ALTER TABLE `ticket_bookings`
  ADD CONSTRAINT `fk_ticket_bookings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_reservations`
--
ALTER TABLE `ticket_reservations`
  ADD CONSTRAINT `fk_ticket_reservations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `ticket_weights`
--
ALTER TABLE `ticket_weights`
  ADD CONSTRAINT `fk_ticket_weights_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `totp_recovery_codes`
--
ALTER TABLE `totp_recovery_codes`
  ADD CONSTRAINT `fk_totp_recovery_codes_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `totp_secrets`
--
ALTER TABLE `totp_secrets`
  ADD CONSTRAINT `fk_totp_secrets_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `umrah_agreements`
--
ALTER TABLE `umrah_agreements`
  ADD CONSTRAINT `fk_umrah_agreements_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `umrah_bookings`
--
ALTER TABLE `umrah_bookings`
  ADD CONSTRAINT `fk_umrah_bookings_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `umrah_bookings_ibfk_1` FOREIGN KEY (`family_id`) REFERENCES `families` (`family_id`) ON DELETE SET NULL;

--
-- Constraints for table `umrah_booking_services`
--
ALTER TABLE `umrah_booking_services`
  ADD CONSTRAINT `fk_ub_services_booking` FOREIGN KEY (`booking_id`) REFERENCES `umrah_bookings` (`booking_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ub_services_supplier` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `fk_ub_services_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `umrah_refunds`
--
ALTER TABLE `umrah_refunds`
  ADD CONSTRAINT `fk_umrah_refunds_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `umrah_transactions`
--
ALTER TABLE `umrah_transactions`
  ADD CONSTRAINT `fk_umrah_transactions_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `users`
--
ALTER TABLE `users`
  ADD CONSTRAINT `fk_users_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_agreements`
--
ALTER TABLE `user_agreements`
  ADD CONSTRAINT `fk_user_agreements_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_blocks`
--
ALTER TABLE `user_blocks`
  ADD CONSTRAINT `fk_ub_blocked` FOREIGN KEY (`blocked_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ub_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ub_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_documents`
--
ALTER TABLE `user_documents`
  ADD CONSTRAINT `fk_user_documents_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `user_documents_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `user_mutes`
--
ALTER TABLE `user_mutes`
  ADD CONSTRAINT `fk_um_muted` FOREIGN KEY (`muted_user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_um_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_um_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `visa_applications`
--
ALTER TABLE `visa_applications`
  ADD CONSTRAINT `fk_visa_applications_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `visa_refunds`
--
ALTER TABLE `visa_refunds`
  ADD CONSTRAINT `fk_visa_refunds_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `visa_refunds_ibfk_1` FOREIGN KEY (`visa_id`) REFERENCES `visa_applications` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
