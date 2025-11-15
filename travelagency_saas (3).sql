-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 15, 2025 at 07:01 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.3.16

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
(1925, 1, 1, 'add', 'main_account', 13, '[]', '{\"name\":\"SELF BANK (SAFE)\",\"account_type\":\"internal\",\"bank_account_number\":null,\"bank_name\":null,\"usd_balance\":\"0\",\"afs_balance\":\"0\",\"status\":\"active\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-15 10:43:51'),
(1926, 1, 1, 'add', 'main_account', 14, '[]', '{\"name\":\"AZIZI BANK\",\"account_type\":\"bank\",\"bank_account_number\":null,\"bank_name\":\"AZIZI\",\"usd_balance\":\"0\",\"afs_balance\":\"0\",\"status\":\"active\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-15 10:45:17'),
(1927, 1, 1, 'add', 'main_account', 15, '[]', '{\"name\":\"AZIZI BANK\",\"account_type\":\"bank\",\"bank_account_number\":null,\"bank_name\":\"AZIZI\",\"usd_balance\":\"0\",\"afs_balance\":\"0\",\"status\":\"active\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-15 10:45:32'),
(1928, 1, 1, 'add', 'main_account', 16, '[]', '{\"name\":\"AFGHAN UNITED BANK\",\"account_type\":\"bank\",\"bank_account_number\":\"23451534\",\"bank_name\":\"AUB\",\"usd_balance\":\"0\",\"afs_balance\":\"0\",\"status\":\"active\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-15 10:51:17'),
(1929, 1, 1, 'fund', 'main_account', 13, '{\"account_id\":\"13\",\"usd_balance\":\"0.000\"}', '{\"account_id\":\"13\",\"usd_balance\":4008,\"amount\":4008,\"currency\":\"USD\",\"description\":\"Account funded by Sabaoon. Remarks: test. Receipt: 1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-15 10:52:26'),
(1930, 1, 1, 'fund', 'main_account', 13, '{\"account_id\":\"13\",\"afs_balance\":\"0.000\"}', '{\"account_id\":\"13\",\"afs_balance\":202360,\"amount\":202360,\"currency\":\"AFS\",\"description\":\"Account funded by Sabaoon. Remarks: test. Receipt: 2\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-15 10:52:51'),
(1931, 1, 1, 'fund', 'main_account', 13, '{\"account_id\":\"13\",\"euro_balance\":\"0.000\"}', '{\"account_id\":\"13\",\"euro_balance\":680,\"amount\":680,\"currency\":\"EUR\",\"description\":\"Account funded by Sabaoon. Remarks: test. Receipt: 3\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-15 10:53:12'),
(1932, 1, 1, 'fund', 'main_account', 13, '{\"account_id\":\"13\",\"darham_balance\":\"1010.000\"}', '{\"account_id\":\"13\",\"darham_balance\":1515,\"amount\":505,\"currency\":\"DARHAM\",\"description\":\"Account funded by Sabaoon. Remarks: test. Receipt: 4\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-15 10:56:39'),
(1933, 1, 1, 'delete', 'main_account_transactions', 1195, '{\"main_account_id\":13,\"transaction_id\":1195,\"amount\":\"505.000\",\"currency\":\"DARHAM\",\"type\":\"credit\",\"created_at\":\"2025-10-15 15:26:39\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-15 10:56:52'),
(1934, 1, 1, 'delete', 'main_account_transactions', 1194, '{\"main_account_id\":13,\"transaction_id\":1194,\"amount\":\"505.000\",\"currency\":\"DARHAM\",\"type\":\"credit\",\"created_at\":\"2025-10-15 15:23:40\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-15 10:56:59'),
(1935, 1, 1, 'add', 'suppliers', 31, '[]', '{\"name\":\"KamAir\",\"contact_person\":\"Wali\",\"phone\":\"0777305730\",\"email\":\"admin@abc-construction.com\",\"address\":\"wazir\",\"currency\":\"USD\",\"balance\":421.49,\"supplier_type\":\"External\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-15 11:11:34'),
(1936, 1, 1, 'add', 'suppliers', 32, '[]', '{\"name\":\"NSTTRIP\",\"contact_person\":\"SABAOON\",\"phone\":\"0777305730\",\"email\":\"admin@abc-construction.com\",\"address\":\"nsst\",\"currency\":\"USD\",\"balance\":446.62,\"supplier_type\":\"External\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-15 11:57:18'),
(1937, 1, 1, 'add', 'suppliers', 33, '[]', '{\"name\":\"Ariana\",\"contact_person\":\"SABAOON\",\"phone\":\"0777305730\",\"email\":\"admin@abc-construction.com\",\"address\":\"nsst\",\"currency\":\"AFS\",\"balance\":47468,\"supplier_type\":\"External\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-15 11:59:01'),
(1938, 1, 1, 'add', 'suppliers', 34, '[]', '{\"name\":\"Yasin\",\"contact_person\":\"Wali\",\"phone\":\"0777305730\",\"email\":\"admin@abc-construction.com\",\"address\":\"yasin\",\"currency\":\"USD\",\"balance\":406,\"supplier_type\":\"External\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-15 12:05:10'),
(1939, 1, 1, 'add', 'ticket_bookings', 333, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"5DYFCD\",\"origin\":\" KBL\",\"destination\":\"HAS\",\"airline\":\"FZ\",\"departure_date\":\"2025-10-23\",\"total_base\":357.03,\"total_sold\":375,\"total_discount\":0,\"total_profit\":17.970000000000027,\"currency\":\"USD\",\"supplier_id\":32,\"supplier_name\":\"NSTTRIP\",\"client_id\":19,\"client_name\":\"Walking Customers\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-15 12:09:28'),
(1940, 1, 1, 'add', 'ticket_bookings', 334, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"86S99L\",\"origin\":\" KBL\",\"destination\":\"ELQ\",\"airline\":\"FZ\",\"departure_date\":\"2025-10-23\",\"total_base\":320.03,\"total_sold\":330,\"total_discount\":0,\"total_profit\":9.970000000000027,\"currency\":\"USD\",\"supplier_id\":32,\"supplier_name\":\"NSTTRIP\",\"client_id\":19,\"client_name\":\"Walking Customers\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-15 12:12:13'),
(1941, 1, 1, 'add', 'main_account_transactions', 1196, '[]', '{\"booking_id\":334,\"payment_date\":\"2025-10-15 16:42:13\",\"description\":\"PAID BY MR MATIULLAH 330*72.12=23800\",\"amount\":23800,\"currency\":\"AFS\",\"exchange_rate\":72.12,\"main_account_id\":13}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-15 12:13:22'),
(1942, 1, 1, 'add', 'ticket_bookings', 335, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"T6EXHA\",\"origin\":\" KBL\",\"destination\":\"ELQ\",\"airline\":\"FZ\",\"departure_date\":\"2025-10-18\",\"total_base\":320.03,\"total_sold\":330,\"total_discount\":0,\"total_profit\":9.970000000000027,\"currency\":\"USD\",\"supplier_id\":32,\"supplier_name\":\"NSTTRIP\",\"client_id\":19,\"client_name\":\"Walking Customers\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-16 06:52:03'),
(1943, 1, 1, 'delete', 'ticket_bookings', 335, '{\"ticket_id\":335,\"client_id\":19,\"supplier_id\":32,\"paid_to_id\":13,\"ticket_currency\":\"USD\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-16 06:59:19'),
(1944, 1, 1, 'delete', 'ticket_bookings', 334, '{\"ticket_id\":334,\"client_id\":19,\"supplier_id\":32,\"paid_to_id\":13,\"ticket_currency\":\"USD\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-16 06:59:22'),
(1945, 1, 1, 'delete', 'ticket_bookings', 333, '{\"ticket_id\":333,\"client_id\":19,\"supplier_id\":32,\"paid_to_id\":13,\"ticket_currency\":\"USD\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-16 06:59:25'),
(1946, 1, 1, 'add', 'ticket_bookings', 336, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"5DYFCD\",\"origin\":\" KBL\",\"destination\":\"HAS\",\"airline\":\"FZ\",\"departure_date\":\"2025-10-18\",\"total_base\":357.03,\"total_sold\":375,\"total_discount\":0,\"total_profit\":17.970000000000027,\"currency\":\"USD\",\"supplier_id\":32,\"supplier_name\":\"NSTTRIP\",\"client_id\":19,\"client_name\":\"Walking Customers\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-16 07:01:45'),
(1947, 1, 1, 'add', 'ticket_bookings', 337, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"86S99L\",\"origin\":\" KBL\",\"destination\":\"ELQ\",\"airline\":\"FZ\",\"departure_date\":\"2025-10-25\",\"total_base\":320.03,\"total_sold\":330,\"total_discount\":0,\"total_profit\":9.970000000000027,\"currency\":\"USD\",\"supplier_id\":32,\"supplier_name\":\"NSTTRIP\",\"client_id\":19,\"client_name\":\"Walking Customers\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-16 07:03:05'),
(1948, 1, 1, 'add', 'ticket_bookings', 338, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"T6EXHA\",\"origin\":\" KBL\",\"destination\":\"ELQ\",\"airline\":\"FZ\",\"departure_date\":\"2025-10-25\",\"total_base\":320.03,\"total_sold\":330,\"total_discount\":0,\"total_profit\":9.970000000000027,\"currency\":\"USD\",\"supplier_id\":32,\"supplier_name\":\"NSTTRIP\",\"client_id\":19,\"client_name\":\"Walking Customers\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-16 07:04:32'),
(1949, 1, 1, 'add', 'main_account_transactions', 1197, '[]', '{\"booking_id\":337,\"payment_date\":\"2025-10-16 11:34:32\",\"description\":\"PAID BY MR MATIULLAH 330*72.12=23800\",\"amount\":23800,\"currency\":\"AFS\",\"exchange_rate\":72.12,\"main_account_id\":13}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-16 07:06:02'),
(1950, 1, 1, 'add', 'expense_categories', 21, '[]', '{\"name\":\"OFFICE EXPS\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-16 07:09:45'),
(1951, 1, 1, 'delete', 'expense_categories', 21, '{\"category_id\":\"21\",\"name\":\"OFFICE EXPS\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-16 07:09:50'),
(1952, 1, 1, 'add', 'expense_categories', 22, '[]', '{\"name\":\"self expenses\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-16 07:10:13'),
(1953, 1, 1, 'add', 'expenses', 214, '[]', '{\"category_id\":\"22\",\"date\":\"2025-10-16\",\"description\":\"HOME EPENSES CAR OIL AND GYM FEESS\",\"amount\":\"7300.00000\",\"currency\":\"AFS\",\"main_account_id\":\"13\",\"allocation_id\":null,\"receipt_number\":\"\",\"receipt_file\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-16 07:12:29'),
(1954, 1, 1, 'add', 'expenses', 215, '[]', '{\"category_id\":\"19\",\"date\":\"2025-10-16\",\"description\":\"FOR KITCHEN CREATING AND PARTITION IN OFFICE\",\"amount\":\"5050\",\"currency\":\"AFS\",\"main_account_id\":\"13\",\"allocation_id\":null,\"receipt_number\":\"\",\"receipt_file\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-16 07:15:00'),
(1955, 1, 1, 'fund', 'suppliers', 32, '{\"supplier_id\":32,\"supplier_balance\":\"163.590\",\"main_account_id\":13,\"main_account_balance\":4008}', '{\"supplier_id\":32,\"supplier_balance\":1163.59,\"main_account_id\":13,\"main_account_balance\":3008,\"amount\":1000,\"currency\":\"USD\",\"remarks\":\"\",\"receipt_number\":\"101\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-16 07:20:03'),
(1956, 1, 1, 'add', 'main_account_transactions', 1201, '[]', '{\"booking_id\":338,\"payment_date\":\"2025-10-16 11:54:49\",\"description\":\"CASH paid by mr matiullah\",\"amount\":20550,\"currency\":\"AFS\",\"exchange_rate\":72.8,\"main_account_id\":13}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/140.0.0.0 Safari/537.36', '2025-10-16 07:25:23'),
(1957, 1, 1, 'add', 'clients', 22, '[]', '{\"name\":\"MINA Khan\",\"email\":\"mina@yahoo.com\",\"client_type\":\"regular\",\"phone\":\"0777305730\",\"usd_balance\":\"0.00\",\"afs_balance\":\"0.00\",\"address\":\"adfads\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-16 11:29:38'),
(1958, 1, 1, 'add', 'suppliers', 35, '[]', '{\"name\":\"ALAIN T\",\"contact_person\":\"Wali\",\"phone\":\"0777305730\",\"email\":\"admin@abc-construction.com\",\"address\":\"adfa\",\"currency\":\"USD\",\"balance\":-5622,\"supplier_type\":\"External\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-16 11:31:23'),
(1959, 1, 1, 'add', 'ticket_bookings', 339, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"181QHU\",\"origin\":\" KBL\",\"destination\":\"JED\",\"airline\":\"KM\",\"departure_date\":\"2025-10-17\",\"total_base\":403,\"total_sold\":431,\"total_discount\":0,\"total_profit\":28,\"currency\":\"USD\",\"supplier_id\":35,\"supplier_name\":\"ALAIN T\",\"client_id\":22,\"client_name\":\"MINA Khan\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-16 11:35:11'),
(1960, 1, 1, 'update', 'client_transactions', 568, '{\"transaction_id\":\"568\",\"transaction_type\":\"client\",\"amount\":431,\"type\":\"credit\",\"date\":\"2025-10-16 16:17:31\"}', '{\"amount\":431,\"type\":\"credit\",\"date\":\"2025-10-16T11:47\",\"receipt\":\"217687\",\"description\":\"Client: MINA Khan, Account funded by Sabaoon. Remarks: test\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-16 11:52:19'),
(1961, 1, 1, 'update', 'supplier_transactions', 911, '{\"transaction_id\":\"911\",\"transaction_type\":\"supplier\",\"amount\":1000,\"type\":\"Credit\",\"date\":\"2025-10-16 11:50:03\"}', '{\"amount\":1000,\"type\":\"credit\",\"date\":\"2025-10-16T07:20\",\"receipt\":\"101\",\"description\":\"Supplier: NSTTRIP, Funded by main account: SELF BANK (SAFE), processed by: Sabaoon, Remarks: \"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-16 11:52:50'),
(1962, 1, 1, 'update', 'supplier_transactions', 911, '{\"transaction_id\":\"911\",\"transaction_type\":\"supplier\",\"amount\":1000,\"type\":\"Credit\",\"date\":\"2025-10-16 11:50:03\"}', '{\"amount\":1000,\"type\":\"credit\",\"date\":\"2025-10-16T07:20\",\"receipt\":\"101\",\"description\":\"Supplier: NSTTRIP, Funded by main account: SELF BANK (SAFE), processed by: Sabaoon, Remarks: \"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-16 11:53:50'),
(1963, 1, 1, 'delete', 'supplier_transactions', 911, '{\"supplier_id\":32,\"transaction_id\":911,\"amount\":\"1000.000\",\"type\":\"credit\",\"transaction_date\":\"2025-10-16 11:50:03\",\"balance\":\"-2836.410\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-16 11:54:03'),
(1964, 1, 1, 'fund', 'suppliers', 32, '{\"supplier_id\":32,\"supplier_balance\":\"163.590\",\"main_account_id\":13,\"main_account_balance\":4439}', '{\"supplier_id\":32,\"supplier_balance\":1163.59,\"main_account_id\":13,\"main_account_balance\":3439,\"amount\":1000,\"currency\":\"USD\",\"remarks\":\"fafsd\",\"receipt_number\":\"2452345\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-16 11:57:05'),
(1965, 1, 1, 'delete', 'supplier_transactions', 913, '{\"supplier_id\":32,\"transaction_id\":913,\"amount\":\"1000.000\",\"type\":\"credit\",\"transaction_date\":\"2025-10-16 16:27:05\",\"balance\":\"1163.590\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-16 11:57:30'),
(1966, 1, 1, 'delete', 'main_account_transactions', 1202, '{\"main_account_id\":13,\"transaction_id\":1202,\"amount\":\"431.000\",\"currency\":\"USD\",\"type\":\"credit\",\"created_at\":\"2025-10-16 16:17:31\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-16 12:05:26'),
(1967, 1, 1, 'fund', 'suppliers', 32, '{\"supplier_id\":32,\"supplier_balance\":\"163.590\",\"main_account_id\":13,\"main_account_balance\":4008}', '{\"supplier_id\":32,\"supplier_balance\":1163.59,\"main_account_id\":13,\"main_account_balance\":3008,\"amount\":1000,\"currency\":\"USD\",\"remarks\":\"asdf\",\"receipt_number\":\"2452345\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-16 12:08:19'),
(1968, 1, 1, 'delete', 'supplier_transactions', 914, '{\"supplier_id\":32,\"transaction_id\":914,\"amount\":\"1000.000\",\"type\":\"credit\",\"transaction_date\":\"2025-10-16 16:38:19\",\"balance\":\"1163.590\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-16 12:08:47'),
(1969, 1, 1, 'bonus', 'suppliers', 31, '{\"supplier_id\":\"31\",\"supplier_balance\":\"421.490\"}', '{\"supplier_id\":\"31\",\"supplier_balance\":521.49,\"amount\":\"100\",\"currency\":\"USD\",\"remarks\":\"adfasd\",\"receipt_number\":\"2452345\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-16 12:14:27'),
(1970, 1, 1, 'delete', 'supplier_transactions', 915, '{\"supplier_id\":31,\"transaction_id\":915,\"amount\":\"100.000\",\"type\":\"credit\",\"transaction_date\":\"2025-10-16 16:44:27\",\"balance\":\"521.490\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-16 12:14:48'),
(1971, 1, 1, 'fund', 'suppliers', 31, '{\"supplier_id\":31,\"supplier_balance\":\"421.490\",\"main_account_id\":13,\"main_account_balance\":4008}', '{\"supplier_id\":31,\"supplier_balance\":321.49,\"main_account_id\":13,\"main_account_balance\":3908,\"amount\":100,\"currency\":\"USD\",\"remarks\":\"tet\",\"receipt_number\":\"2452345\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-16 12:17:17'),
(1972, 1, 1, 'delete', 'supplier_transactions', 916, '{\"supplier_id\":31,\"transaction_id\":916,\"amount\":\"100.000\",\"type\":\"debit\",\"transaction_date\":\"2025-10-16 16:47:17\",\"balance\":\"321.490\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-16 12:28:40'),
(1973, 1, 1, 'fund', 'suppliers', 31, '{\"supplier_id\":31,\"supplier_balance\":\"421.490\",\"main_account_id\":13,\"main_account_balance\":4108}', '{\"supplier_id\":31,\"supplier_balance\":321.49,\"main_account_id\":13,\"main_account_balance\":4008,\"amount\":100,\"currency\":\"USD\",\"remarks\":\"dsafasdf\",\"receipt_number\":\"217687\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-16 12:34:22'),
(1974, 1, 1, 'delete', 'supplier_transactions', 917, '{\"supplier_id\":31,\"transaction_id\":917,\"amount\":\"100.000\",\"type\":\"debit\",\"transaction_date\":\"2025-10-16 17:04:22\",\"balance\":\"321.490\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-16 12:34:46'),
(1975, 1, 1, 'update', 'main_account_transactions', 1190, '{\"transaction_id\":\"1190\",\"transaction_type\":\"main\",\"amount\":4008,\"type\":\"credit\",\"date\":\"2025-10-15 15:22:26\"}', '{\"amount\":4008,\"type\":\"credit\",\"date\":\"2025-10-15T10:52\",\"receipt\":\"1\",\"description\":\"Account funded by Sabaoon. Remarks: test. Receipt: 1\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 04:20:58'),
(1976, 1, 1, 'update', '0', 336, '{\"ticket_id\":336,\"supplier\":\"32\",\"sold_to\":19,\"price\":\"357.030\",\"sold\":\"375.000\",\"currency\":\"USD\"}', '{\"supplier\":31,\"sold_to\":19,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"MUHAMMAD RAUF\",\"pnr\":\"5DYFCD\",\"phone\":\"0788808299\",\"origin\":\" KBL\",\"destination\":\"HAS\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"FZ\",\"issue_date\":\"2025-10-16\",\"departure_date\":\"2025-10-18\",\"return_date\":\"\",\"price\":357.03,\"sold\":375,\"profit\":17.97,\"currency\":\"USD\",\"description\":\"test\",\"paid_to\":13,\"market_exchange_rate\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 04:30:02'),
(1977, 1, 1, 'update', '0', 337, '{\"ticket_id\":337,\"supplier\":\"32\",\"sold_to\":19,\"price\":\"320.030\",\"sold\":\"330.000\",\"currency\":\"USD\"}', '{\"supplier\":32,\"sold_to\":18,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"KHASTA GUL\",\"pnr\":\"86S99L\",\"phone\":\"0771781576\",\"origin\":\" KBL\",\"destination\":\"ELQ\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"FZ\",\"issue_date\":\"2025-10-16\",\"departure_date\":\"2025-10-25\",\"return_date\":\"\",\"price\":320.03,\"sold\":330,\"profit\":9.97,\"currency\":\"USD\",\"description\":\"Sale for ticket: KHASTA GUL ( KBL to ELQ)\",\"paid_to\":13,\"market_exchange_rate\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 04:32:06'),
(1978, 1, 1, 'update', '0', 337, '{\"ticket_id\":337,\"supplier\":\"32\",\"sold_to\":18,\"price\":\"320.030\",\"sold\":\"330.000\",\"currency\":\"USD\"}', '{\"supplier\":32,\"sold_to\":19,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"KHASTA GUL\",\"pnr\":\"86S99L\",\"phone\":\"0771781576\",\"origin\":\" KBL\",\"destination\":\"ELQ\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"FZ\",\"issue_date\":\"2025-10-16\",\"departure_date\":\"2025-10-25\",\"return_date\":\"\",\"price\":320.03,\"sold\":330,\"profit\":9.97,\"currency\":\"USD\",\"description\":\"Sale for ticket: KHASTA GUL ( KBL to ELQ)\",\"paid_to\":13,\"market_exchange_rate\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 04:33:16'),
(1979, 1, 1, 'update', '0', 1197, '{\"transaction_id\":\"1197\",\"ticket_id\":\"337\",\"amount\":23800,\"description\":\"PAID BY MR MATIULLAH 330*72.12=23800\",\"created_at\":\"2025-10-16 11:34:32\",\"type\":\"credit\",\"currency\":\"AFS\",\"exchange_rate\":null}', '{\"amount\":23800,\"description\":\"PAID BY MR MATIULLAH 330*72.12=23800\",\"created_at\":\"2025-10-16 11:34:32\",\"exchange_rate\":72.11}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 04:45:38'),
(1980, 1, 1, 'add', 'ticket_reservations', 18, '{}', '{\"passenger_name\":\"BAKHTIAR STANIKZAI\",\"pnr\":\"188JZ0\",\"origin\":\"MZR\",\"destination\":\"FRA\",\"airline\":\"EI\",\"departure_date\":\"2025-10-23\",\"base\":100,\"sold\":120,\"profit\":20,\"currency\":\"USD\",\"supplier\":32,\"supplier_name\":\"NSTTRIP\",\"sold_to\":19,\"client_name\":\"Walking Customers\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 05:23:08'),
(1981, 1, 1, 'update', '0', 18, '{\"ticket_id\":18,\"supplier\":\"32\",\"sold_to\":19,\"price\":\"100.000\",\"sold\":\"120.000\",\"currency\":\"USD\"}', '{\"supplier\":35,\"sold_to\":19,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"BAKHTIAR STANIKZAI\",\"pnr\":\"188JZ0\",\"phone\":\"0777305730\",\"origin\":\"MZR\",\"destination\":\"FRA\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"EI\",\"issue_date\":\"2025-10-18\",\"departure_date\":\"2025-10-23\",\"return_date\":\"\",\"price\":100,\"sold\":120,\"profit\":20,\"currency\":\"USD\",\"description\":\"Purchase for ticket: BAKHTIAR STANIKZAI (MZR to FRA)\",\"paid_to\":13}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 05:27:14'),
(1982, 1, 1, 'update', '0', 18, '{\"ticket_id\":18,\"supplier\":\"35\",\"sold_to\":19,\"price\":\"100.000\",\"sold\":\"120.000\",\"currency\":\"USD\"}', '{\"supplier\":35,\"sold_to\":18,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"BAKHTIAR STANIKZAI\",\"pnr\":\"188JZ0\",\"phone\":\"0777305730\",\"origin\":\"MZR\",\"destination\":\"FRA\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"EI\",\"issue_date\":\"2025-10-18\",\"departure_date\":\"2025-10-23\",\"return_date\":\"\",\"price\":100,\"sold\":120,\"profit\":20,\"currency\":\"USD\",\"description\":\"Sale for ticket: BAKHTIAR STANIKZAI (MZR to FRA)\",\"paid_to\":13}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 05:28:44'),
(1983, 1, 1, 'update', '0', 18, '{\"ticket_id\":18,\"supplier\":\"35\",\"sold_to\":18,\"price\":\"100.000\",\"sold\":\"120.000\",\"currency\":\"USD\"}', '{\"supplier\":35,\"sold_to\":19,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"BAKHTIAR STANIKZAI\",\"pnr\":\"188JZ0\",\"phone\":\"0777305730\",\"origin\":\"MZR\",\"destination\":\"FRA\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"EI\",\"issue_date\":\"2025-10-18\",\"departure_date\":\"2025-10-23\",\"return_date\":\"\",\"price\":100,\"sold\":120,\"profit\":20,\"currency\":\"USD\",\"description\":\"Sale for ticket: BAKHTIAR STANIKZAI (MZR to FRA)\",\"paid_to\":13}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 05:34:35'),
(1984, 1, 1, 'update', '0', 18, '{\"ticket_id\":18,\"supplier\":\"35\",\"sold_to\":19,\"price\":\"100.000\",\"sold\":\"120.000\",\"currency\":\"USD\"}', '{\"supplier\":35,\"sold_to\":18,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"BAKHTIAR STANIKZAI\",\"pnr\":\"188JZ0\",\"phone\":\"0777305730\",\"origin\":\"MZR\",\"destination\":\"FRA\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"EI\",\"issue_date\":\"2025-10-18\",\"departure_date\":\"2025-10-23\",\"return_date\":\"\",\"price\":100,\"sold\":120,\"profit\":20,\"currency\":\"USD\",\"description\":\"Sale for ticket: BAKHTIAR STANIKZAI (MZR to FRA)\",\"paid_to\":13}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 05:34:55'),
(1985, 1, 1, 'update', '0', 337, '{\"ticket_id\":337,\"supplier\":\"32\",\"sold_to\":19,\"price\":\"320.030\",\"sold\":\"330.000\",\"currency\":\"USD\"}', '{\"supplier\":32,\"sold_to\":19,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"KHASTA GUL\",\"pnr\":\"86S99L\",\"phone\":\"0771781576\",\"origin\":\" KBL\",\"destination\":\"ELQ\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"FZ\",\"issue_date\":\"2025-10-16\",\"departure_date\":\"2025-10-25\",\"return_date\":\"\",\"price\":330.03,\"sold\":340,\"profit\":9.97,\"currency\":\"USD\",\"description\":\"Sale for ticket: KHASTA GUL ( KBL to ELQ)\",\"paid_to\":13,\"market_exchange_rate\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 05:44:03'),
(1986, 1, 1, 'update', '0', 18, '{\"ticket_id\":18,\"supplier\":\"35\",\"sold_to\":18,\"price\":\"100.000\",\"sold\":\"120.000\",\"currency\":\"USD\"}', '{\"supplier\":35,\"sold_to\":18,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"BAKHTIAR STANIKZAI\",\"pnr\":\"188JZ0\",\"phone\":\"0777305730\",\"origin\":\"MZR\",\"destination\":\"FRA\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"EI\",\"issue_date\":\"2025-10-18\",\"departure_date\":\"2025-10-23\",\"return_date\":\"\",\"price\":110,\"sold\":120,\"profit\":10,\"currency\":\"USD\",\"description\":\"Sale for ticket: BAKHTIAR STANIKZAI (MZR to FRA)\",\"paid_to\":13}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 05:46:57'),
(1987, 1, 1, 'update', '0', 18, '{\"ticket_id\":18,\"supplier\":\"35\",\"sold_to\":18,\"price\":\"110.000\",\"sold\":\"120.000\",\"currency\":\"USD\"}', '{\"supplier\":35,\"sold_to\":18,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"BAKHTIAR STANIKZAI\",\"pnr\":\"188JZ0\",\"phone\":\"0777305730\",\"origin\":\"MZR\",\"destination\":\"FRA\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"EI\",\"issue_date\":\"2025-10-18\",\"departure_date\":\"2025-10-23\",\"return_date\":\"\",\"price\":110,\"sold\":130,\"profit\":20,\"currency\":\"USD\",\"description\":\"Sale for ticket: BAKHTIAR STANIKZAI (MZR to FRA)\",\"paid_to\":13}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 05:47:40'),
(1988, 1, 1, 'add', 'refunded_tickets', 99, '{\"ticket_id\":339,\"passenger_name\":\"HAJI MUSA GUL MINAH KHAN\",\"pnr\":\"181QHU\",\"base\":403,\"sold\":431,\"supplier_penalty\":20,\"service_penalty\":30,\"currency\":\"USD\",\"status\":\"Refunded\",\"description\":\"test\",\"calculation_method\":\"base\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 05:49:21'),
(1989, 1, 1, 'add', 'refunded_tickets', 100, '{\"ticket_id\":338,\"passenger_name\":\"BAIKHT BAIDAR BAKHT JEHAN\",\"pnr\":\"T6EXHA\",\"base\":320.03,\"sold\":330,\"supplier_penalty\":20,\"service_penalty\":30,\"currency\":\"USD\",\"status\":\"Refunded\",\"description\":\"test\",\"calculation_method\":\"base\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 05:50:52'),
(1990, 1, 1, 'add', 'date_change_tickets', 49, '{\"ticket_id\":\"337\",\"passenger_name\":\"KHASTA GUL\",\"pnr\":\"86S99L\",\"base\":330.03,\"sold\":340,\"supplier_penalty\":20,\"service_penalty\":30,\"currency\":\"USD\",\"status\":\"Date Changed\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 05:51:55'),
(1991, 1, 1, 'add', 'refunded_tickets', 101, '{\"ticket_id\":336,\"passenger_name\":\"MUHAMMAD RAUF\",\"pnr\":\"5DYFCD\",\"base\":357.03,\"sold\":375,\"supplier_penalty\":20,\"service_penalty\":30,\"currency\":\"USD\",\"status\":\"pending\",\"description\":\"test\",\"calculation_method\":\"sold\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 05:53:58'),
(1992, 1, 1, 'add', 'date_change_tickets', 50, '{\"ticket_id\":\"336\",\"passenger_name\":\"MUHAMMAD RAUF\",\"pnr\":\"5DYFCD\",\"base\":357.03,\"sold\":375,\"supplier_penalty\":10,\"service_penalty\":20,\"currency\":\"USD\",\"status\":\"Date Changed\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 05:54:26'),
(1993, 1, 1, 'create', 'ticket_weights', 16, NULL, '{\"ticket_id\":337,\"weight\":20,\"base_price\":30,\"sold_price\":40,\"profit\":10,\"remarks\":\"fasdf\",\"supplier_name\":\"NSTTRIP\",\"client_name\":\"Walking Customers\",\"currency\":\"USD\"}', '::1', '0', '2025-10-18 05:54:51'),
(1994, 1, 1, 'create', 'ticket_weights', 17, NULL, '{\"ticket_id\":336,\"weight\":20,\"base_price\":10,\"sold_price\":20,\"profit\":10,\"remarks\":\"sdfas\",\"supplier_name\":\"KamAir\",\"client_name\":\"Walking Customers\",\"currency\":\"USD\"}', '::1', '0', '2025-10-18 05:55:22'),
(1995, 1, 1, 'add', 'hotel_bookings', 47, '[]', '{\"title\":\"Mr\",\"first_name\":\"EDRISS \",\"last_name\":\"NOOR\",\"gender\":\"Male\",\"check_in_date\":\"2025-10-18\",\"check_out_date\":\"2025-10-24\",\"accommodation_details\":\"sfdsa\",\"supplier_id\":32,\"sold_to\":\"19\",\"base_amount\":10,\"sold_amount\":20,\"profit\":10,\"currency\":\"USD\",\"paid_to\":\"13\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 05:56:12'),
(1996, 1, 1, 'update', 'hotel_bookings', 47, '{\"booking_id\":47,\"title\":\"\",\"first_name\":\"\",\"last_name\":\"\",\"base_amount\":\"10.000\",\"sold_amount\":\"20.000\",\"currency\":\"USD\",\"supplier_id\":32,\"sold_to\":\"19\"}', '{\"title\":\"Mr\",\"first_name\":\"EDRISS \",\"last_name\":\"NOOR\",\"gender\":\"Male\",\"contact_no\":\"766666232\",\"check_in_date\":\"2025-10-18\",\"check_out_date\":\"2025-10-24\",\"accommodation_details\":\"sfdsa\",\"base_amount\":15,\"sold_amount\":20,\"profit\":5,\"currency\":\"USD\",\"supplier_id\":\"32\",\"sold_to\":\"19\",\"paid_to\":\"13\",\"remarks\":\"sadf\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 05:59:32'),
(1997, 1, 1, 'update', 'hotel_bookings', 47, '{\"booking_id\":47,\"title\":\"\",\"first_name\":\"\",\"last_name\":\"\",\"base_amount\":\"15.000\",\"sold_amount\":\"20.000\",\"currency\":\"USD\",\"supplier_id\":32,\"sold_to\":\"19\"}', '{\"title\":\"Mr\",\"first_name\":\"EDRISS \",\"last_name\":\"NOOR\",\"gender\":\"Male\",\"contact_no\":\"766666232\",\"check_in_date\":\"2025-10-18\",\"check_out_date\":\"2025-10-24\",\"accommodation_details\":\"sfdsa\",\"base_amount\":15,\"sold_amount\":20,\"profit\":5,\"currency\":\"USD\",\"supplier_id\":\"35\",\"sold_to\":\"19\",\"paid_to\":\"13\",\"remarks\":\"sadf\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 06:11:59'),
(1998, 1, 1, 'update', 'hotel_bookings', 47, '{\"booking_id\":47,\"title\":\"\",\"first_name\":\"\",\"last_name\":\"\",\"base_amount\":\"15.000\",\"sold_amount\":\"20.000\",\"currency\":\"USD\",\"supplier_id\":35,\"sold_to\":\"19\",\"receipt\":\"\"}', '{\"title\":\"Mr\",\"first_name\":\"EDRISS \",\"last_name\":\"NOOR\",\"gender\":\"Male\",\"contact_no\":\"766666232\",\"check_in_date\":\"2025-10-18\",\"check_out_date\":\"2025-10-24\",\"accommodation_details\":\"sfdsa\",\"base_amount\":15,\"sold_amount\":20,\"profit\":5,\"currency\":\"USD\",\"supplier_id\":\"35\",\"sold_to\":\"19\",\"paid_to\":\"13\",\"remarks\":\"sadf\",\"receipt\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 06:28:03'),
(1999, 1, 1, 'update', 'hotel_bookings', 47, '{\"booking_id\":47,\"title\":\"\",\"first_name\":\"\",\"last_name\":\"\",\"base_amount\":\"15.000\",\"sold_amount\":\"20.000\",\"currency\":\"USD\",\"supplier_id\":35,\"sold_to\":\"19\",\"receipt\":\"\"}', '{\"title\":\"Mr\",\"first_name\":\"EDRISS \",\"last_name\":\"NOOR\",\"gender\":\"Male\",\"contact_no\":\"766666232\",\"check_in_date\":\"2025-10-18\",\"check_out_date\":\"2025-10-24\",\"accommodation_details\":\"sfdsa\",\"base_amount\":15,\"sold_amount\":20,\"profit\":5,\"currency\":\"USD\",\"supplier_id\":\"32\",\"sold_to\":\"19\",\"paid_to\":\"13\",\"remarks\":\"sadf\",\"receipt\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 06:32:11'),
(2000, 1, 1, 'update', 'hotel_bookings', 47, '{\"booking_id\":47,\"title\":\"\",\"first_name\":\"\",\"last_name\":\"\",\"base_amount\":\"15.000\",\"sold_amount\":\"20.000\",\"currency\":\"USD\",\"supplier_id\":32,\"sold_to\":\"19\",\"receipt\":\"\"}', '{\"title\":\"Mr\",\"first_name\":\"EDRISS \",\"last_name\":\"NOOR\",\"gender\":\"Male\",\"contact_no\":\"766666232\",\"check_in_date\":\"2025-10-18\",\"check_out_date\":\"2025-10-24\",\"accommodation_details\":\"sfdsa\",\"base_amount\":15,\"sold_amount\":20,\"profit\":5,\"currency\":\"USD\",\"supplier_id\":\"32\",\"sold_to\":\"18\",\"paid_to\":\"13\",\"remarks\":\"sadf\",\"receipt\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 06:35:52'),
(2001, 1, 1, 'transfer', 'main_account_transactions', NULL, '{}', '{\"from_account_id\":\"13\",\"from_account_name\":\"SELF BANK (SAFE)\",\"from_currency\":\"USD\",\"to_account_id\":\"13\",\"to_account_name\":\"SELF BANK (SAFE)\",\"to_currency\":\"AFS\",\"amount\":20,\"converted_amount\":1400,\"exchange_rate\":70,\"description\":\"trdt\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 08:43:04'),
(2002, 1, 1, 'transfer', 'main_account_transactions', NULL, '{}', '{\"from_account_id\":\"13\",\"from_account_name\":\"SELF BANK (SAFE)\",\"from_currency\":\"USD\",\"to_account_id\":\"14\",\"to_account_name\":\"AZIZI BANK\",\"to_currency\":\"USD\",\"amount\":100,\"converted_amount\":100,\"exchange_rate\":1,\"description\":\"teatr\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 08:43:54'),
(2003, 1, 1, 'add', 'visa_applications', 60, '[]', '{\"supplier\":32,\"sold_to\":\"19\",\"paid_to\":\"13\",\"applicant_name\":\"BAKHTIAR STANIKZAI\",\"passport_number\":\"P8798765\",\"visa_type\":\"Tourist\",\"base\":100,\"sold\":120,\"profit\":20,\"currency\":\"USD\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 09:19:18'),
(2004, 1, 1, 'update', 'visa_applications', 60, '{\"id\":60,\"supplier\":32,\"sold_to\":19,\"base\":\"100.000\",\"sold\":\"120.000\",\"currency\":\"USD\"}', '{\"id\":60,\"supplier\":32,\"sold_to\":19,\"title\":\"Mr\",\"gender\":\"Male\",\"applicant_name\":\"BAKHTIAR STANIKZAI\",\"passport_number\":\"P8798765\",\"country\":\"Pakistan\",\"visa_type\":\"Tourist\",\"receive_date\":\"2025-10-18\",\"applied_date\":\"2025-10-25\",\"issued_date\":\"\",\"base\":110,\"sold\":120,\"profit\":10,\"currency\":\"USD\",\"status\":\"Pending\",\"remarks\":\"adf\",\"phone\":\"0777555594\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 09:20:48'),
(2005, 1, 1, 'update', 'visa_applications', 60, '{\"id\":60,\"supplier\":32,\"sold_to\":19,\"base\":\"110.000\",\"sold\":\"120.000\",\"currency\":\"USD\"}', '{\"id\":60,\"supplier\":35,\"sold_to\":19,\"title\":\"Mr\",\"gender\":\"Male\",\"applicant_name\":\"BAKHTIAR STANIKZAI\",\"passport_number\":\"P8798765\",\"country\":\"Pakistan\",\"visa_type\":\"Tourist\",\"receive_date\":\"2025-10-18\",\"applied_date\":\"2025-10-25\",\"issued_date\":\"\",\"base\":110,\"sold\":120,\"profit\":10,\"currency\":\"USD\",\"status\":\"Pending\",\"remarks\":\"adf\",\"phone\":\"0777555594\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 09:22:00'),
(2006, 1, 1, 'update', 'visa_applications', 60, '{\"id\":60,\"supplier\":35,\"sold_to\":19,\"base\":\"110.000\",\"sold\":\"120.000\",\"currency\":\"USD\"}', '{\"id\":60,\"supplier\":35,\"sold_to\":18,\"title\":\"Mr\",\"gender\":\"Male\",\"applicant_name\":\"BAKHTIAR STANIKZAI\",\"passport_number\":\"P8798765\",\"country\":\"Pakistan\",\"visa_type\":\"Tourist\",\"receive_date\":\"2025-10-18\",\"applied_date\":\"2025-10-25\",\"issued_date\":\"\",\"base\":110,\"sold\":120,\"profit\":10,\"currency\":\"USD\",\"status\":\"Pending\",\"remarks\":\"adf\",\"phone\":\"0777555594\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 09:23:33'),
(2007, 1, 1, 'update', 'visa_applications', 60, '{\"id\":60,\"supplier\":35,\"sold_to\":18,\"base\":\"110.000\",\"sold\":\"120.000\",\"currency\":\"USD\"}', '{\"id\":60,\"supplier\":35,\"sold_to\":18,\"title\":\"Mr\",\"gender\":\"Male\",\"applicant_name\":\"BAKHTIAR STANIKZAI\",\"passport_number\":\"P8798765\",\"country\":\"Pakistan\",\"visa_type\":\"Tourist\",\"receive_date\":\"2025-10-18\",\"applied_date\":\"2025-10-25\",\"issued_date\":\"\",\"base\":110,\"sold\":130,\"profit\":20,\"currency\":\"USD\",\"status\":\"Pending\",\"remarks\":\"adf\",\"phone\":\"0777555594\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 09:25:17'),
(2008, 1, 1, 'update', 'hotel_bookings', 47, '{\"booking_id\":47,\"title\":\"\",\"first_name\":\"\",\"last_name\":\"\",\"base_amount\":\"15.000\",\"sold_amount\":\"20.000\",\"currency\":\"USD\",\"supplier_id\":32,\"sold_to\":\"18\",\"receipt\":\"\"}', '{\"title\":\"Mr\",\"first_name\":\"EDRISS \",\"last_name\":\"NOOR\",\"gender\":\"Male\",\"contact_no\":\"766666232\",\"check_in_date\":\"2025-10-18\",\"check_out_date\":\"2025-10-24\",\"accommodation_details\":\"sfdsa\",\"base_amount\":15,\"sold_amount\":25,\"profit\":10,\"currency\":\"USD\",\"supplier_id\":\"32\",\"sold_to\":\"18\",\"paid_to\":\"13\",\"remarks\":\"sadf\",\"receipt\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 09:25:52'),
(2009, 1, 1, 'add', 'umrah_bookings', 65, '[]', '{\"family_id\":\"15\",\"sold_to\":\"19\",\"paid_to\":\"13\",\"name\":\"Matiullah\",\"passport_number\":\"P07592390\",\"flight_date\":\"\",\"return_date\":\"\",\"room_type\":\"Shared\",\"total_base_price\":100,\"total_sold_price\":120,\"total_profit\":20,\"services\":[{\"service_type\":\"all\",\"supplier_id\":\"32\",\"currency\":\"USD\",\"base_price\":\"100\",\"sold_price\":\"120\",\"profit\":20}],\"remarks\":\"Base amount of 100 USD deducted for umrah all.\",\"discount\":\"0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 09:33:41'),
(2010, 1, 1, 'delete', 'umrah_bookings', 65, '{\"booking_id\":65,\"client_id\":19,\"services\":[{\"service_id\":25,\"supplier_id\":32,\"service_type\":\"all\",\"base_price\":\"100.000\",\"sold_price\":\"120.000\",\"profit\":\"20.000\",\"currency\":\"USD\",\"supplier_type\":\"External\"}],\"paid_to\":13,\"currency\":\"USD\",\"client_type\":\"agency\",\"total_base_price\":\"100.000\",\"total_sold_price\":\"120.000\",\"total_profit\":\"20.000\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 09:36:21'),
(2011, 1, 1, 'add', 'umrah_bookings', 66, '[]', '{\"family_id\":\"15\",\"sold_to\":\"19\",\"paid_to\":\"13\",\"name\":\"Matiullah\",\"passport_number\":\"P03241263\",\"flight_date\":\"\",\"return_date\":\"\",\"room_type\":\"Shared\",\"total_base_price\":100,\"total_sold_price\":120,\"total_profit\":20,\"services\":[{\"service_type\":\"all\",\"supplier_id\":\"32\",\"currency\":\"USD\",\"base_price\":\"100\",\"sold_price\":\"120\",\"profit\":20}],\"remarks\":\"Base amount of 100 USD deducted for umrah all.\",\"discount\":\"0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 09:38:20'),
(2012, 1, 1, 'update_umrah_member', 'umrah_bookings', 66, '{\"sold_to\":19,\"family_id\":15,\"paid_to\":13,\"entry_date\":\"2025-10-18\",\"name\":\"Matiullah\",\"dob\":\"2025-10-25\",\"passport_number\":\"P03241263\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":\"0000-00-00\",\"return_date\":\"0000-00-00\",\"duration\":\"21 Days\",\"room_type\":\"Shared\",\"price\":\"100.000\",\"sold_price\":\"120.000\",\"profit\":\"20.000\",\"received_bank_payment\":\"0.000\",\"bank_receipt_number\":\"\",\"paid\":\"0.000\",\"due\":\"120.000\",\"discount\":\"0.000\"}', '{\"booking_id\":66,\"family_id\":15,\"suppliers\":{\"1\":{\"service_type\":\"all\",\"supplier_id\":\"35\",\"currency\":\"USD\",\"base_price\":\"100.000\",\"sold_price\":\"120.000\",\"profit\":\"20.00\"}},\"sold_to\":19,\"paid_to\":13,\"entry_date\":\"2025-10-18\",\"name\":\"Matiullah\",\"dob\":\"2025-10-25\",\"passport_number\":\"P03241263\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":null,\"return_date\":null,\"duration\":\"21 Days\",\"room_type\":\"Shared\",\"total_base_price\":100,\"total_sold_price\":120,\"total_profit\":20,\"received_bank_payment\":null,\"bank_receipt_number\":null,\"paid\":null,\"due\":120,\"gender\":\"Male\",\"passport_expiry\":\"2026-04-25\",\"remarks\":\"yrsd\",\"relation\":\"Uncle\",\"g_name\":\"ESMAT ULLAH\",\"father_name\":\"FAIZ MOHAMMAD\",\"discount\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 09:48:40'),
(2013, 1, 1, 'update_umrah_member', 'umrah_bookings', 66, '{\"sold_to\":19,\"family_id\":15,\"paid_to\":13,\"entry_date\":\"2025-10-18\",\"name\":\"Matiullah\",\"dob\":\"2025-10-25\",\"passport_number\":\"P03241263\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":null,\"return_date\":null,\"duration\":\"21 Days\",\"room_type\":\"Shared\",\"price\":\"100.000\",\"sold_price\":\"120.000\",\"profit\":\"20.000\",\"received_bank_payment\":\"0.000\",\"bank_receipt_number\":\"\",\"paid\":\"0.000\",\"due\":\"120.000\",\"discount\":\"0.000\"}', '{\"booking_id\":66,\"family_id\":15,\"suppliers\":{\"1\":{\"service_type\":\"all\",\"supplier_id\":\"35\",\"currency\":\"USD\",\"base_price\":\"100.000\",\"sold_price\":\"120.000\",\"profit\":\"20.00\"}},\"sold_to\":18,\"paid_to\":13,\"entry_date\":\"2025-10-18\",\"name\":\"Matiullah\",\"dob\":\"2025-10-25\",\"passport_number\":\"P03241263\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":null,\"return_date\":null,\"duration\":\"21 Days\",\"room_type\":\"Shared\",\"total_base_price\":100,\"total_sold_price\":120,\"total_profit\":20,\"received_bank_payment\":null,\"bank_receipt_number\":null,\"paid\":null,\"due\":120,\"gender\":\"Male\",\"passport_expiry\":\"2026-04-25\",\"remarks\":\"yrsd\",\"relation\":\"Uncle\",\"g_name\":\"ESMAT ULLAH\",\"father_name\":\"FAIZ MOHAMMAD\",\"discount\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 10:03:15'),
(2014, 1, 1, 'update_umrah_member', 'umrah_bookings', 66, '{\"sold_to\":18,\"family_id\":15,\"paid_to\":13,\"entry_date\":\"2025-10-18\",\"name\":\"Matiullah\",\"dob\":\"2025-10-25\",\"passport_number\":\"P03241263\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":null,\"return_date\":null,\"duration\":\"21 Days\",\"room_type\":\"Shared\",\"price\":\"100.000\",\"sold_price\":\"120.000\",\"profit\":\"20.000\",\"received_bank_payment\":\"0.000\",\"bank_receipt_number\":\"\",\"paid\":\"0.000\",\"due\":\"120.000\",\"discount\":\"0.000\"}', '{\"booking_id\":66,\"family_id\":15,\"suppliers\":{\"1\":{\"service_type\":\"all\",\"supplier_id\":\"35\",\"currency\":\"USD\",\"base_price\":\"100.000\",\"sold_price\":\"130.000\",\"profit\":\"30.00\"}},\"sold_to\":18,\"paid_to\":13,\"entry_date\":\"2025-10-18\",\"name\":\"Matiullah\",\"dob\":\"2025-10-25\",\"passport_number\":\"P03241263\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":null,\"return_date\":null,\"duration\":\"21 Days\",\"room_type\":\"Shared\",\"total_base_price\":100,\"total_sold_price\":130,\"total_profit\":30,\"received_bank_payment\":null,\"bank_receipt_number\":null,\"paid\":null,\"due\":130,\"gender\":\"Male\",\"passport_expiry\":\"2026-04-25\",\"remarks\":\"yrsd\",\"relation\":\"Uncle\",\"g_name\":\"ESMAT ULLAH\",\"father_name\":\"FAIZ MOHAMMAD\",\"discount\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 10:03:59');
INSERT INTO `activity_log` (`id`, `tenant_id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(2015, 1, 1, 'update', '0', 15, '[]', '{\"family_id\":\"15\",\"head_of_family\":\"HAMEED\",\"contact\":\"0786011115\",\"address\":\"Jada-e-Maiwand\",\"package_type\":\"Full Package\",\"location\":\"Madina and Makkah\",\"tazmin\":\"Done\",\"visa_status\":\"Applied\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 10:11:52'),
(2016, 1, 1, 'add', 'additional_payments', 50, NULL, '{\"id\":50,\"payment_type\":\"Vacine\",\"description\":\"test\",\"base_amount\":10,\"profit\":5,\"sold_amount\":15,\"currency\":\"USD\",\"main_account_id\":13,\"supplier_id\":null,\"is_from_supplier\":1,\"client_id\":null,\"is_for_client\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 10:29:18'),
(2017, 1, 1, 'add', 'additional_payments', 51, NULL, '{\"id\":51,\"payment_type\":\"Vacine\",\"description\":\"test\",\"base_amount\":10,\"profit\":5,\"sold_amount\":15,\"currency\":\"USD\",\"main_account_id\":13,\"supplier_id\":32,\"is_from_supplier\":1,\"client_id\":19,\"is_for_client\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 10:29:46'),
(2018, 1, 1, 'add', 'main_account_transactions', 1211, '[]', '{\"main_account_id\":13,\"amount\":10,\"currency\":\"USD\",\"description\":\"test\",\"payment_id\":50,\"balance\":3998,\"exchange_rate\":0,\"payment_datetime\":\"2025-10-18 15:24:32\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 10:54:44'),
(2019, 1, 1, 'update', 'main_account_transactions', 1211, '{\"transaction_id\":1211,\"payment_id\":50,\"amount\":\"10.000\",\"description\":\"test\",\"created_at\":\"2025-10-18 15:24:32\",\"receipt\":\"\"}', '{\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-10-18 15:24:32\",\"receipt\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 11:01:58'),
(2020, 1, 1, 'update', 'main_account_transactions', 1211, '{\"transaction_id\":1211,\"payment_id\":50,\"amount\":\"10.000\",\"description\":\"test\",\"created_at\":\"2025-10-18 15:24:32\",\"receipt\":\"\"}', '{\"amount\":12,\"description\":\"test\",\"created_at\":\"2025-10-18 15:24:32\",\"receipt\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 11:02:07'),
(2021, 1, 1, 'update', '0', 35, '{\"transaction_id\":\"35\",\"debtor_id\":\"34\",\"amount\":200,\"description\":\"test\",\"created_at\":\"2025-10-18 16:25:36\",\"transaction_type\":\"credit\",\"currency\":\"USD\"}', '{\"amount\":300,\"description\":\"test\",\"created_at\":\"2025-10-18 16:25:00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-18 11:56:25'),
(2022, 1, 1, '0', 'creditor_transactions', 16, '{\"transaction_id\":16,\"creditor_id\":12,\"amount\":100,\"description\":\"tet\",\"created_at\":\"2025-10-18 16:37:48\",\"reference_number\":\"1212121\",\"currency\":\"USD\"}', '{\"amount\":200,\"description\":\"tet\",\"created_at\":\"2025-10-18 16:37:48\",\"reference_number\":\"1212121\"}', '::1', '0', '2025-10-18 12:10:44'),
(2023, 1, 1, 'update', '0', 1218, '{\"transaction_id\":1218,\"customer_id\":5,\"amount\":100,\"description\":\"\",\"created_at\":\"2025-10-20 08:46:18\",\"type\":\"credit\",\"currency\":\"USD\"}', '{\"amount\":200,\"description\":\"trt\",\"created_at\":\"2025-10-20 04:22:57\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 04:22:57'),
(2024, 1, 1, 'delete', 'sarafi_transactions', 39, '{\"transaction_id\":39,\"amount\":200,\"currency\":\"USD\",\"customer_id\":5,\"customer_name\":\"Matiullah\",\"main_account_id\":13,\"created_at\":\"2025-10-20 08:46:18\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 04:41:11'),
(2025, 1, 1, 'delete', 'main_account_transactions', 1211, '{\"main_account_id\":13,\"transaction_id\":1211,\"payment_id\":50,\"amount\":\"12.000\",\"currency\":\"USD\",\"type\":\"credit\",\"created_at\":\"2025-10-18 15:24:32\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 04:44:48'),
(2026, 1, 1, 'delete', 'main_account_transactions', 1209, '{\"main_account_id\":13,\"transaction_id\":1209,\"amount\":\"100.000\",\"currency\":\"USD\",\"type\":\"debit\",\"created_at\":\"2025-10-18 13:13:54\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 04:46:21'),
(2027, 1, 1, 'delete', 'main_account_transactions', 1208, '{\"main_account_id\":13,\"transaction_id\":1208,\"amount\":\"1400.000\",\"currency\":\"AFS\",\"type\":\"credit\",\"created_at\":\"2025-10-18 13:13:04\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 04:46:25'),
(2028, 1, 1, 'delete', 'main_account_transactions', 1207, '{\"main_account_id\":13,\"transaction_id\":1207,\"amount\":\"20.000\",\"currency\":\"USD\",\"type\":\"debit\",\"created_at\":\"2025-10-18 13:13:04\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 04:46:33'),
(2029, 1, 1, 'add', 'main_account_transactions', 1219, '[]', '{\"main_account_id\":13,\"amount\":10,\"currency\":\"USD\",\"description\":\"fdsaf\",\"payment_id\":50,\"balance\":1930,\"exchange_rate\":0,\"payment_datetime\":\"2025-10-20 09:16:56\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 04:47:03'),
(2030, 1, 1, 'delete', 'main_account_transactions', 1219, '{\"main_account_id\":13,\"transaction_id\":1219,\"payment_id\":50,\"amount\":\"10.000\",\"currency\":\"USD\",\"type\":\"credit\",\"created_at\":\"2025-10-20 09:16:56\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 04:47:25'),
(2031, 1, 1, 'add', 'main_account_transactions', 1220, '[]', '{\"main_account_id\":13,\"amount\":10,\"currency\":\"USD\",\"description\":\"test\",\"payment_id\":50,\"balance\":1940,\"exchange_rate\":0,\"payment_datetime\":\"2025-10-20 09:18:50\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 04:48:56'),
(2032, 1, 1, 'delete', 'main_account_transactions', 1220, '{\"main_account_id\":13,\"transaction_id\":1220,\"payment_id\":50,\"amount\":\"10.000\",\"currency\":\"USD\",\"type\":\"credit\",\"created_at\":\"2025-10-20 09:18:50\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 04:49:12'),
(2033, 1, 1, 'add', 'main_account_transactions', 1221, '[]', '{\"main_account_id\":13,\"amount\":10,\"currency\":\"USD\",\"description\":\"test\",\"payment_id\":50,\"balance\":4118,\"exchange_rate\":0,\"payment_datetime\":\"2025-10-20 09:47:55\",\"tenant_id\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 05:18:00'),
(2034, 1, 1, 'update', '0', 39, '{\"transaction_id\":\"39\",\"debtor_id\":\"36\",\"amount\":100,\"description\":\"test\",\"created_at\":\"2025-10-20 09:49:40\",\"transaction_type\":\"credit\",\"currency\":\"USD\"}', '{\"amount\":200,\"description\":\"test\",\"created_at\":\"2025-10-20 09:49:00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 05:20:04'),
(2035, 1, 1, '0', 'creditor_transactions', 17, '{\"transaction_id\":17,\"creditor_id\":13,\"amount\":100,\"description\":\"tert\",\"created_at\":\"2025-10-20 09:51:22\",\"reference_number\":\"1212121\",\"currency\":\"USD\"}', '{\"amount\":200,\"description\":\"tert\",\"created_at\":\"2025-10-20 09:51:22\",\"reference_number\":\"1212121\"}', '::1', '0', '2025-10-20 05:21:43'),
(2036, 1, 1, '0', 'creditor_transactions', 17, '{\"transaction_id\":17,\"creditor_id\":13,\"amount\":200,\"description\":\"tert\",\"created_at\":\"2025-10-20 09:51:22\",\"reference_number\":\"1212121\",\"currency\":\"USD\"}', '{\"amount\":300,\"description\":\"tert\",\"created_at\":\"2025-10-20 09:51:22\",\"reference_number\":\"1212121\"}', '::1', '0', '2025-10-20 05:32:25'),
(2037, 1, 1, 'update', '0', 39, '{\"transaction_id\":\"39\",\"debtor_id\":\"36\",\"amount\":200,\"description\":\"test\",\"created_at\":\"2025-10-20 09:49:00\",\"transaction_type\":\"credit\",\"currency\":\"USD\"}', '{\"amount\":300,\"description\":\"test\",\"created_at\":\"2025-10-20 09:49:00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 05:34:37'),
(2038, 1, 1, 'delete', 'sarafi_transactions', 41, '{\"transaction_id\":41,\"amount\":50,\"currency\":\"USD\",\"customer_id\":5,\"customer_name\":\"Matiullah\",\"main_account_id\":13,\"created_at\":\"2025-10-20 09:52:36\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 05:59:24'),
(2039, 1, 1, 'delete', 'sarafi_transactions', 43, '{\"transaction_id\":43,\"amount\":50,\"currency\":\"USD\",\"customer_id\":5,\"customer_name\":\"Matiullah\",\"main_account_id\":13,\"created_at\":\"2025-10-20 10:12:45\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 05:59:57'),
(2040, 1, 1, 'delete', 'sarafi_transactions', 45, '{\"transaction_id\":45,\"amount\":50,\"currency\":\"USD\",\"customer_id\":5,\"customer_name\":\"Matiullah\",\"main_account_id\":13,\"created_at\":\"2025-10-20 10:23:19\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 06:00:25'),
(2041, 1, 1, 'delete', 'sarafi_transactions', 40, '{\"transaction_id\":40,\"amount\":100,\"currency\":\"USD\",\"customer_id\":5,\"customer_name\":\"Matiullah\",\"main_account_id\":13,\"created_at\":\"2025-10-20 09:52:08\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 06:00:52'),
(2042, 1, 1, 'add', 'jv_payments', 5, '{}', '{\"jv_payment_id\":\"5\",\"jv_name\":\"Client-Supplier Payment\",\"client_id\":18,\"client_name\":\"DR SAHIBs\",\"supplier_id\":35,\"supplier_name\":\"ALAIN T\",\"amount\":1000,\"supplier_amount\":1000,\"currency\":\"USD\",\"supplier_currency\":\"USD\",\"exchange_rate\":1,\"receipt\":\"1212121\",\"remarks\":\"test\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 09:35:33'),
(2043, 1, 1, 'update', 'jv_payments', 5, '{\"id\":5,\"tenant_id\":1,\"jv_name\":\"Client-Supplier Payment\",\"exchange_rate\":\"1.00000\",\"total_amount\":\"1000.000\",\"currency\":\"USD\",\"receipt\":\"1212121\",\"remarks\":\"test\",\"client_id\":18,\"supplier_id\":35,\"created_by\":1,\"created_at\":\"2025-10-20 14:05:33\",\"updated_at\":\"2025-10-20 14:05:33\"}', '{\"jv_name\":\"Client-Supplier Payment\",\"currency\":\"USD\",\"total_amount\":\"1100.000\",\"exchange_rate\":\"1.00000\",\"receipt\":\"1212121\",\"remarks\":\"test\",\"client_id\":18,\"supplier_id\":35,\"client_name\":\"DR SAHIBs\",\"supplier_name\":\"ALAIN T\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 10:13:06'),
(2044, 1, 1, 'fund', 'suppliers', 35, '{\"supplier_id\":35,\"supplier_balance\":\"-4056.000\",\"main_account_id\":13,\"main_account_balance\":4518}', '{\"supplier_id\":35,\"supplier_balance\":-3956,\"main_account_id\":13,\"main_account_balance\":4418,\"amount\":100,\"currency\":\"USD\",\"remarks\":\"test\",\"receipt_number\":\"2452345\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 10:14:17'),
(2045, 1, 1, 'update', 'jv_payments', 5, '{\"id\":5,\"tenant_id\":1,\"jv_name\":\"Client-Supplier Payment\",\"exchange_rate\":\"1.00000\",\"total_amount\":\"1100.000\",\"currency\":\"USD\",\"receipt\":\"1212121\",\"remarks\":\"test\",\"client_id\":18,\"supplier_id\":35,\"created_by\":1,\"created_at\":\"2025-10-20 14:05:33\",\"updated_at\":\"2025-10-20 14:43:06\"}', '{\"jv_name\":\"Client-Supplier Payment\",\"currency\":\"USD\",\"total_amount\":\"1200.000\",\"exchange_rate\":\"1.00000\",\"receipt\":\"1212121\",\"remarks\":\"test\",\"client_id\":18,\"supplier_id\":35,\"client_name\":\"DR SAHIBs\",\"supplier_name\":\"ALAIN T\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 10:15:12'),
(2046, 1, 1, 'delete', 'jv_payments', 5, '{\"jv_payment_id\":5,\"client_id\":18,\"client_name\":\"DR SAHIBs\",\"supplier_id\":35,\"supplier_name\":\"ALAIN T\",\"currency\":\"USD\",\"total_amount\":\"1200.000\",\"exchange_rate\":\"1.00000\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-20 10:17:38'),
(2047, 1, 1, 'update', 'users', 1, '[]', '{\"name\":\"Sabaoon\",\"email\":\"almuqadas_travel@yahoo.com\",\"phone\":\"0786011115\",\"address\":\"kabul, jada-ee-mewand\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-21 08:23:48'),
(2048, 1, 1, 'update', 'users', 1, '[]', '{\"name\":\"Sabaoon\",\"email\":\"almuqadas_travel@yahoo.com\",\"phone\":\"0786011115\",\"address\":\"kabul, jada-ee-mewand\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-21 08:50:50'),
(2049, 1, 1, 'update', 'users', 1, '[]', '{\"name\":\"Sabaoon\",\"email\":\"almuqadas_travel@yahoo.com\",\"phone\":\"0786011115\",\"address\":\"kabul, jada-ee-mewand\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-21 08:53:02'),
(2050, 1, 1, 'update', 'users', 1, '[]', '{\"name\":\"Sabaoon\",\"email\":\"almuqadas_travel@yahoo.com\",\"phone\":\"0786011115\",\"address\":\"kabul, jada-ee-mewand\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-21 08:55:21'),
(2051, 1, 1, 'update', 'users', 1, '[]', '{\"name\":\"Sabaoon\",\"email\":\"almuqadas_travel@yahoo.com\",\"phone\":\"0786011115\",\"address\":\"kabul, jada-ee-mewand\",\"profile_pic\":\"68f74bc0ade30_White and Blue Modern Travel Poster.png\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-21 09:00:48'),
(2052, 1, 1, 'update', 'users', 1, '[]', '{\"name\":\"SABAOON CAR REPAIR\",\"email\":\"almuqadas_travel@yahoo.com\",\"phone\":\"0786011115\",\"address\":\"kabul, jada-ee-mewand\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-21 09:21:33'),
(2053, 1, 1, 'password_change', 'users', 1, '{\"password\":\"(old password)\"}', '{\"password\":\"(new password)\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-21 09:28:23'),
(2054, 1, 1, 'update', '0', 337, '{\"ticket_id\":337,\"supplier\":\"32\",\"sold_to\":19,\"price\":\"330.030\",\"sold\":\"340.000\",\"currency\":\"USD\"}', '{\"supplier\":32,\"sold_to\":19,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"KHASTA GUL\",\"pnr\":\"86S99L\",\"phone\":\"0771781576\",\"origin\":\" KBL\",\"destination\":\"ELQ\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"FZ\",\"issue_date\":\"2025-10-16\",\"departure_date\":\"2025-10-25\",\"return_date\":\"\",\"price\":335.03,\"sold\":340,\"profit\":4.97,\"currency\":\"USD\",\"description\":\"Sale for ticket: KHASTA GUL ( KBL to ELQ)\",\"paid_to\":13,\"market_exchange_rate\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 04:28:04'),
(2055, 1, 1, 'update', '0', 337, '{\"ticket_id\":337,\"supplier\":\"32\",\"sold_to\":19,\"price\":\"335.030\",\"sold\":\"340.000\",\"currency\":\"USD\"}', '{\"supplier\":32,\"sold_to\":19,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"KHASTA GUL\",\"pnr\":\"86S99L\",\"phone\":\"0771781576\",\"origin\":\" KBL\",\"destination\":\"ELQ\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"FZ\",\"issue_date\":\"2025-10-16\",\"departure_date\":\"2025-10-25\",\"return_date\":\"\",\"price\":335.03,\"sold\":350,\"profit\":14.97,\"currency\":\"USD\",\"description\":\"Sale for ticket: KHASTA GUL ( KBL to ELQ)\",\"paid_to\":13,\"market_exchange_rate\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 04:29:16'),
(2056, 1, 1, 'update', '0', 337, '{\"ticket_id\":337,\"supplier\":\"32\",\"sold_to\":19,\"price\":\"335.030\",\"sold\":\"350.000\",\"currency\":\"USD\"}', '{\"supplier\":32,\"sold_to\":18,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"KHASTA GUL\",\"pnr\":\"86S99L\",\"phone\":\"0771781576\",\"origin\":\" KBL\",\"destination\":\"ELQ\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"FZ\",\"issue_date\":\"2025-10-16\",\"departure_date\":\"2025-10-25\",\"return_date\":\"\",\"price\":335.03,\"sold\":350,\"profit\":14.97,\"currency\":\"USD\",\"description\":\"Sale for ticket: KHASTA GUL ( KBL to ELQ)\",\"paid_to\":13,\"market_exchange_rate\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-25 04:30:33'),
(2057, 1, 1, 'add', 'main_account_transactions', 1243, '[]', '{\"booking_id\":101,\"payment_date\":\"2025-10-27 15:37:25\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"client_type\":\"agency\",\"main_account_id\":13}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 11:07:56'),
(2058, 1, 1, 'add', 'main_account_transactions', 1244, '[]', '{\"booking_id\":49,\"payment_date\":\"2025-10-27 15:38:18\",\"description\":\"tets\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"main_account_id\":13}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 11:08:26'),
(2059, 1, 1, 'create', 'main_account_transactions', 1245, NULL, '{\"weight_id\":17,\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"transaction_date\":\"2025-10-27 15:40\",\"remarks\":\"test\",\"main_account_id\":13,\"balance\":4528}', '::1', '0', '2025-10-27 11:10:15'),
(2060, 1, 1, 'update', 'hotel_bookings', 47, '{\"booking_id\":47,\"title\":\"\",\"first_name\":\"\",\"last_name\":\"\",\"base_amount\":\"15.000\",\"sold_amount\":\"25.000\",\"currency\":\"USD\",\"supplier_id\":32,\"sold_to\":\"18\",\"receipt\":\"\"}', '{\"title\":\"Mr\",\"first_name\":\"EDRISS \",\"last_name\":\"NOOR\",\"gender\":\"Male\",\"contact_no\":\"766666232\",\"check_in_date\":\"2025-10-18\",\"check_out_date\":\"2025-10-24\",\"accommodation_details\":\"sfdsa\",\"base_amount\":15,\"sold_amount\":25,\"profit\":10,\"currency\":\"USD\",\"supplier_id\":\"32\",\"sold_to\":\"19\",\"paid_to\":\"13\",\"remarks\":\"sadf\",\"receipt\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 11:14:07'),
(2061, 1, 1, 'add', 'main_account_transactions', 1246, '[]', '{\"booking_id\":47,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 11:14:18'),
(2062, 1, 1, 'add', 'main_account_transactions', 1247, '[]', '{\"refund_id\":15,\"payment_date\":\"2025-10-27 15:46:36\",\"description\":\"Refund payment for Hotel Booking #47 - Mr EDRISS  NOOR\",\"amount\":25,\"currency\":\"USD\",\"client_type\":\"agency\",\"main_account_id\":13,\"first_name\":\"EDRISS \",\"last_name\":\"NOOR\",\"order_id\":\"1089734\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 11:16:48'),
(2063, 1, 1, 'add', 'umrah_transactions', 96, '[]', '{\"umrah_booking_id\":66,\"transaction_to\":\"Internal Account\",\"payment_amount\":10,\"payment_currency\":\"USD\",\"payment_description\":\"test\",\"payment_date\":\"2025-10-27\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 11:17:28'),
(2064, 1, 1, 'update', 'visa_applications', 60, '{\"id\":60,\"supplier\":35,\"sold_to\":18,\"base\":\"110.000\",\"sold\":\"130.000\",\"currency\":\"USD\"}', '{\"id\":60,\"supplier\":35,\"sold_to\":19,\"title\":\"Mr\",\"gender\":\"Male\",\"applicant_name\":\"BAKHTIAR STANIKZAI\",\"passport_number\":\"P8798765\",\"country\":\"Pakistan\",\"visa_type\":\"Tourist\",\"receive_date\":\"2025-10-18\",\"applied_date\":\"2025-10-25\",\"issued_date\":\"\",\"base\":110,\"sold\":130,\"profit\":20,\"currency\":\"USD\",\"status\":\"Pending\",\"remarks\":\"adf\",\"phone\":\"0777555594\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-27 11:21:27'),
(2065, 1, 1, 'add', 'suppliers', 36, '[]', '{\"name\":\"Matiullah\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"admin@abc-construction.com\",\"address\":\"test\",\"currency\":\"USD\",\"balance\":0,\"supplier_type\":\"Internal\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-28 04:53:28'),
(2066, 1, 1, 'add', 'ticket_bookings', 340, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"86S99L\",\"origin\":\"Kabul\",\"destination\":\"JED\",\"airline\":\"A3\",\"departure_date\":\"2025-10-31\",\"total_base\":100,\"total_sold\":120,\"total_discount\":0,\"total_profit\":20,\"currency\":\"USD\",\"supplier_id\":36,\"supplier_name\":\"Matiullah\",\"client_id\":19,\"client_name\":\"Walking Customers\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-28 04:55:33'),
(2067, 1, 1, 'update', '0', 340, '{\"ticket_id\":340,\"supplier\":\"36\",\"sold_to\":19,\"price\":\"100.000\",\"sold\":\"120.000\",\"currency\":\"USD\"}', '{\"supplier\":32,\"sold_to\":19,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"guli\",\"pnr\":\"86S99L\",\"phone\":\"0771781576\",\"origin\":\"Kabul\",\"destination\":\"JED\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"A3\",\"issue_date\":\"2025-10-28\",\"departure_date\":\"2025-10-31\",\"return_date\":\"\",\"price\":100,\"sold\":120,\"profit\":20,\"currency\":\"USD\",\"description\":\"test\",\"paid_to\":13,\"market_exchange_rate\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-28 04:56:10'),
(2068, 1, 1, 'update', '0', 340, '{\"ticket_id\":340,\"supplier\":\"32\",\"sold_to\":19,\"price\":\"100.000\",\"sold\":\"120.000\",\"currency\":\"USD\"}', '{\"supplier\":32,\"sold_to\":19,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"guli\",\"pnr\":\"86S99L\",\"phone\":\"0771781576\",\"origin\":\"Kabul\",\"destination\":\"JED\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"A3\",\"issue_date\":\"2025-10-28\",\"departure_date\":\"2025-10-29\",\"return_date\":\"\",\"price\":100,\"sold\":120,\"profit\":20,\"currency\":\"USD\",\"description\":\"test\",\"paid_to\":13,\"market_exchange_rate\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-29 10:53:46'),
(2069, 1, 1, 'add_employee', 'users', 18, 'null', '{\"name\":\"Matiullah\",\"email\":\"matiullahr970@gmail.com\",\"phone\":\"0777305730\",\"role\":\"finance\",\"hire_date\":\"2025-10-29\",\"address\":\"test\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-29 11:58:44'),
(2070, 1, 18, 'update', 'users', 18, '[]', '{\"name\":\"Matiullah\",\"email\":\"matiullahr970@gmail.com\",\"phone\":\"0777305730\",\"address\":\"test\",\"profile_pic\":\"690307f9d794a_umrah.png\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-10-30 06:38:49'),
(2071, 1, 1, 'add', 'expenses', 216, '[]', '{\"category_id\":\"19\",\"date\":\"2025-11-01\",\"description\":\"this is for test\",\"amount\":\"100\",\"currency\":\"AFS\",\"main_account_id\":\"13\",\"allocation_id\":null,\"receipt_number\":\"31531\",\"receipt_file\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-01 06:33:34'),
(2072, 1, 1, 'update', 'expenses', 216, '{\"expense_id\":\"216\",\"previous_values\":{\"amount\":\"100.000\",\"currency\":\"AFS\",\"main_account_id\":13,\"allocation_id\":null,\"receipt_file\":null}}', '{\"category_id\":\"19\",\"date\":\"2025-11-01\",\"description\":\"this is for test\",\"amount\":\"200.000\",\"currency\":\"AFS\",\"main_account_id\":\"13\",\"allocation_id\":null,\"receipt_number\":\"31531\",\"receipt_file\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-01 06:34:11'),
(2073, 1, 1, 'update', '0', 1046, '{\"notification_id\":1046,\"previous_status\":\"Read\"}', '{\"status\":\"read\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-01 06:40:40'),
(2074, 1, 1, 'update', '0', 1047, '{\"notification_id\":1047,\"previous_status\":\"Read\"}', '{\"status\":\"read\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-01 06:40:42'),
(2075, 1, 1, 'update', 'expenses', 216, '{\"expense_id\":\"216\",\"previous_values\":{\"amount\":\"200.000\",\"currency\":\"AFS\",\"main_account_id\":13,\"allocation_id\":null,\"receipt_file\":null}}', '{\"category_id\":\"19\",\"date\":\"2025-11-01\",\"description\":\"this is for test\",\"amount\":\"300.000\",\"currency\":\"AFS\",\"main_account_id\":\"13\",\"allocation_id\":null,\"receipt_number\":\"31531\",\"receipt_file\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-01 06:40:58'),
(2076, 1, 1, 'update', '0', 1048, '{\"notification_id\":1048,\"previous_status\":\"Read\"}', '{\"status\":\"read\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-01 06:43:43'),
(2077, 1, 1, 'update', 'expenses', 216, '{\"expense_id\":\"216\",\"previous_values\":{\"amount\":\"300.000\",\"currency\":\"AFS\",\"main_account_id\":13,\"allocation_id\":null,\"receipt_file\":null}}', '{\"category_id\":\"19\",\"date\":\"2025-11-01\",\"description\":\"this is for test\",\"amount\":\"400.000\",\"currency\":\"AFS\",\"main_account_id\":\"13\",\"allocation_id\":null,\"receipt_number\":\"31531\",\"receipt_file\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-01 06:43:56'),
(2078, 1, 1, 'add', 'expenses', 217, '[]', '{\"category_id\":\"19\",\"date\":\"2025-11-01\",\"description\":\"this is for test\",\"amount\":\"100\",\"currency\":\"AFS\",\"main_account_id\":\"13\",\"allocation_id\":null,\"receipt_number\":\"adfasdf\",\"receipt_file\":\"receipt_6905ac54c7348.png\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-01 06:44:36'),
(2079, 1, 1, 'update_employee', 'users', 6, '{\"name\":\"Idrees\",\"email\":\"idress@gmail.com\",\"phone\":\"0777555594\",\"role\":\"umrah\",\"hire_date\":\"2025-04-09\",\"address\":\"Jada-e-Maiwand\",\"profile_pic\":\"67f60cc0da261.jpg\"}', '{\"name\":\"Idrees\",\"email\":\"idress@gmail.com\",\"phone\":\"0777555594\",\"role\":\"sales\",\"hire_date\":\"2025-04-09\",\"address\":\"Jada-e-Maiwand\",\"profile_pic\":\"67f60cc0da261.jpg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-01 09:53:56'),
(2080, 1, 6, 'update', 'users', 6, '[]', '{\"name\":\"Idrees\",\"email\":\"idress@gmail.com\",\"phone\":\"0777555594\",\"address\":\"Jada-e-Maiwand\",\"profile_pic\":\"6905da1a0e1d6_WhatsApp Image 2025-10-27 at 11.31.26 AM.jpeg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36', '2025-11-01 09:59:54');

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
(50, 1, 'Vacine', 'test', 10.00, 15.00, 5.00, 'USD', 13, 1, '2025-10-18 10:29:18', '2025-10-18 10:29:18', NULL, NULL, 1, NULL, 1),
(51, 1, 'Vacine', 'test', 10.00, 15.00, 5.00, 'USD', 13, 1, '2025-10-18 10:29:46', '2025-10-18 10:29:46', NULL, 32, 1, 19, 1);

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
(38, 14, 'update_platform_settings', 'platform_setting', 0, '{\"platform_name\":\"MTravels\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"contact_email\":\"allahdadmuhammadi01@gmail.com\",\"website_url\":\"https:\\/\\/mtravels.com\",\"contact_phone\":\"0780310431\",\"support_phone\":\"+93780310431\",\"contact_address\":\"Kabul,Afghanistan\",\"contact_facebook\":\"https:\\/\\/mtravels.com\",\"contact_twitter\":\"https:\\/\\/mtravels.com\",\"contact_linkedin\":\"https:\\/\\/mtravels.com\",\"contact_instagram\":\"https:\\/\\/mtravels.com\",\"default_currency\":\"AFN\",\"max_users_per_tenant\":\"20\",\"api_enabled\":\"false\",\"logo_updated\":true,\"favicon_updated\":false}', '::1', '2025-10-02 11:52:07'),
(39, 14, 'update_demo_request_status', 'demo_request', 1, '{\"new_status\":\"contacted\"}', '::1', '2025-10-04 06:06:40'),
(40, 14, 'update_subscription', 'subscription', 1, '{\"subscription_id\":1,\"plan_id\":\"3\",\"status\":\"active\",\"billing_cycle\":\"monthly\"}', '::1', '2025-10-06 06:29:38'),
(41, 14, 'update_testimonial', 'testimonials', 4, 'Updated testimonial for Ahmad Rahimi', '', '2025-10-13 10:16:57'),
(42, 14, 'update_plan', 'plan', 0, '{\"old_name\":\"basic\",\"new_name\":\"basic\",\"description\":\"Basic plan with access to ticket-related tasks only\",\"price\":\"1500\",\"max_users\":\"10\",\"trial_days\":\"10\",\"status\":\"active\"}', '::1', '2025-11-05 05:45:05'),
(43, 14, 'update_plan', 'plan', 0, '{\"old_name\":\"pro\",\"new_name\":\"pro\",\"description\":\"Pro plan with ticket-related tasks, visa-related tasks, and inter-tenant chat\",\"price\":\"2800\",\"max_users\":\"15\",\"trial_days\":\"30\",\"status\":\"active\"}', '::1', '2025-11-05 05:45:47'),
(44, 14, 'update_plan', 'plan', 0, '{\"old_name\":\"enterprise\",\"new_name\":\"enterprise\",\"description\":\"Enterprise plan with all Pro features plus Umrah management\",\"price\":\"5000\",\"max_users\":\"30\",\"trial_days\":\"30\",\"status\":\"active\"}', '::1', '2025-11-05 05:46:16'),
(45, 14, 'update_subscription', 'subscription', 1, '{\"subscription_id\":1,\"plan_id\":\"1\",\"status\":\"active\",\"billing_cycle\":\"monthly\"}', '::1', '2025-11-15 03:43:24'),
(46, 14, 'update_subscription', 'subscription', 2, '{\"subscription_id\":2,\"plan_id\":\"1\",\"status\":\"active\",\"billing_cycle\":\"yearly\"}', '::1', '2025-11-15 03:53:42'),
(47, 14, 'update_tenant', 'tenant', 2, '{\"tenant_id\":2,\"name\":\"Al Wali\",\"subdomain\":\"beta\",\"status\":\"active\"}', '::1', '2025-11-15 03:53:54'),
(48, 14, 'update_tenant', 'tenant', 1, '{\"tenant_id\":1,\"name\":\"Al Moqadas Travel Agency\",\"subdomain\":\"alpha\",\"status\":\"active\"}', '::1', '2025-11-15 03:54:05');

-- --------------------------------------------------------

--
-- Table structure for table `blog_posts`
--

CREATE TABLE `blog_posts` (
  `id` int(11) NOT NULL,
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

--
-- Dumping data for table `blog_posts`
--

INSERT INTO `blog_posts` (`id`, `title`, `slug`, `content`, `excerpt`, `featured_image`, `author`, `category`, `status`, `created_at`, `updated_at`) VALUES
(4, 'Test', 'test', 'Mtravels offer variaty of services such us ticket management visa mamangent and so on', 'Today we want to make our first post or mtravels', '/uploads/blog/blog_69100d0eca8d3.png', 'MTravels Team', 'test', 'published', '2025-11-09 03:39:58', '2025-11-09 03:39:58');

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
(38, 'u-1-7', 1, 7, 1, 'helo', '2025-09-08 11:00:34', '2025-09-09 07:09:30'),
(39, 'u-1-6', 1, 6, 1, 'hi', '2025-10-28 04:34:25', '2025-11-01 09:56:32'),
(40, 'u-1-7', 1, 7, 1, 'hi', '2025-10-29 10:16:34', NULL),
(41, 'u-1-18', 18, 1, 1, 'hi', '2025-10-30 06:32:06', '2025-11-01 06:32:40');

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
(18, 1, '', 'DR SAHIBs', 'admin@abc-construction.com', '$2y$10$wNU7te9YSBzmM86Q3Z9D6eLp2SloDOyIz29TUmz/JDc9ZJMwT2EG.', '0777305730', -5326.300, 0.000, 'Jada-e-Maiwand', '2025-09-01 10:44:22', '2025-10-27 11:21:27', 'active', 'regular', 0),
(19, 1, '690854d3a4ecb_Blue and White Simple Furniture Doubled Sided Poster.png', 'Walking Customer', 'almuqadas_travel@yahoo.com', '$2y$10$RCql5Ieq91Jkd8FaykSbKOxX/QNNJ9LD4jKJORz6Zkw9rlalh0qVS', '0777305730', 660.000, 0.000, 'Jada-e-Maiwand', '2025-09-07 08:42:27', '2025-11-03 07:08:03', 'active', 'agency', 0),
(20, 2, '', 'DR SAHIB', 'DRal@GMAIL.COM', '$2y$10$AgeoMovkpOzvOgWKT7b5tegnAHDaUcLYwr3SJ80aDsw0o20llzJrm', '0777305730', 0.000, 0.000, 'Jada-e-Maiwand', '2025-09-10 11:48:39', '2025-09-10 13:11:15', 'active', 'regular', 0),
(21, 2, '', 'walkings', 'esmati@gmail.com', '$2y$10$JWMeEbMm9oA4FxG9DhK6.uMNh.NLazftrApSj/oG3KPJcM1MikAJK', '0777305730', 0.000, 0.000, 'Jada-e-Maiwand', '2025-09-10 12:31:12', '2025-09-10 12:31:12', 'active', 'agency', 0),
(22, 1, '', 'MINA Khan', 'mina@yahoo.com', '$2y$10$E70pRj3P2NYVedD5bA1UgeOB0srLqm.0nFlsDsGmhIB5BsKDl/qha', '0777305730', 353.000, 0.000, 'adfads', '2025-10-16 11:29:38', '2025-10-18 05:49:21', 'active', 'regular', 0);

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
  `receipt` varchar(100) DEFAULT NULL,
  `exchange_rate` decimal(10,5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `client_transactions`
--

INSERT INTO `client_transactions` (`id`, `tenant_id`, `client_id`, `type`, `amount`, `balance`, `currency`, `description`, `created_at`, `transaction_of`, `reference_id`, `receipt`, `exchange_rate`) VALUES
(561, 1, 19, 'debit', 375.000, -375.000, 'USD', 'Ticket booked for Mr MUHAMMAD RAUF with PNR: 5DYFCD from  KBL to HAS.', '2025-10-16 07:01:45', 'ticket_sale', 336, '0', NULL),
(562, 1, 18, 'debit', 330.000, -330.000, 'USD', 'Ticket booked for Mr KHASTA GUL with PNR: 86S99L from  KBL to ELQ. (Transferred from client 19)', '2025-10-16 07:03:05', 'ticket_sale', 337, '0', NULL),
(563, 1, 19, 'debit', 330.000, 330.000, 'USD', 'Ticket booked for Mr BAIKHT BAIDAR BAKHT JEHAN with PNR: T6EXHA from  KBL to ELQ.', '2025-10-16 07:04:32', 'ticket_sale', 338, '0', NULL),
(564, 1, 22, 'debit', 431.000, -431.000, 'USD', 'Ticket booked for Mr HAJI MUSA GUL MINAH KHAN with PNR: 181QHU from  KBL to JED.', '2025-10-16 11:35:11', 'ticket_sale', 339, '0', NULL),
(568, 1, 22, 'credit', 431.000, 0.000, 'USD', 'Client: MINA Khan, Account funded by Sabaoon. Remarks: test', '2025-10-16 11:47:31', 'fund', 1, '217687', 1.00000),
(569, 1, 18, 'debit', 330.000, -3826.300, 'USD', 'Sale for ticket: KHASTA GUL ( KBL to ELQ) (Transferred from client 18) (Transferred from client 19)', '2025-10-18 04:32:06', 'ticket_sale', 337, '0', NULL),
(570, 1, 19, 'debit', 120.000, 540.000, 'USD', 'Ticket reservation for passenger BAKHTIAR STANIKZAI', '2025-10-18 05:23:08', 'ticket_reserve', 18, '0', NULL),
(571, 1, 19, 'debit', 120.000, -3616.300, 'USD', 'Sale for ticket: BAKHTIAR STANIKZAI (MZR to FRA) (Transferred from client 18)', '2025-10-18 05:28:44', 'ticket_reserve', 18, '0', NULL),
(572, 1, 18, 'debit', 130.000, -4286.300, 'USD', 'Updated: Sale for ticket: BAKHTIAR STANIKZAI (MZR to FRA)', '2025-10-18 05:34:55', 'ticket_reserve', 18, '0', NULL),
(573, 1, 22, 'credit', 353.000, 353.000, 'USD', 'Refund for ticket HAJI MUSA GUL MINAH KHAN.', '2025-10-18 05:49:21', 'ticket_refund', 99, '0', NULL),
(574, 1, 19, 'debit', 40.000, 620.000, 'USD', 'Weight transaction: 20kg at 40 USD.', '2025-10-18 05:54:51', 'weight_sale', 16, '0', NULL),
(575, 1, 19, 'debit', 20.000, 640.000, 'USD', 'Weight transaction: 20kg at 20 USD.', '2025-10-18 05:55:22', 'weight_sale', 17, '0', NULL),
(576, 1, 19, 'debit', 25.000, -4311.300, 'USD', 'Updated: Sale for hotel booking: EDRISS  NOOR (Check-in: 2025-10-18) (Transferred from client 18)', '2025-10-18 06:35:52', 'hotel', 47, '0', NULL),
(577, 1, 19, 'debit', 120.000, 660.000, 'USD', 'Visa booking for BAKHTIAR STANIKZAI', '2025-10-18 09:19:18', 'visa_sale', 60, '0', NULL),
(580, 1, 19, 'debit', 0.000, 660.000, 'USD', 'Client was debited 0 for umrah booking for Matiullah', '2025-10-18 09:38:20', 'umrah', 66, '0', NULL),
(581, 1, 18, 'debit', -130.000, -4416.300, 'USD', 'Updated: Sale for member: Matiullah (Passport: P03241263)', '2025-10-18 10:03:15', 'umrah', 66, NULL, NULL),
(582, 1, 19, 'debit', 15.000, 645.000, 'USD', 'Additional payment: Vacine - test', '2025-10-18 10:29:46', 'additional_payment', 51, NULL, NULL),
(584, 1, 18, 'credit', 100.000, -4316.300, 'USD', 'Client: DR SAHIBs, Account funded by Sabaoon. Remarks: ', '2025-10-20 10:14:54', 'fund', 1, '2452345', 1.00000),
(585, 1, 18, 'debit', 350.000, -5326.300, 'USD', 'Sale for ticket: KHASTA GUL ( KBL to ELQ)', '2025-10-25 04:30:33', 'ticket_sale', 337, NULL, NULL),
(586, 1, 19, 'credit', 25.000, 0.000, 'USD', 'Refund for hotel booking #47 - test', '2025-10-27 11:16:33', 'hotel_refund', 15, NULL, NULL),
(587, 1, 19, 'debit', 120.000, 540.000, 'USD', 'Ticket booked for Mr guli with PNR: 86S99L from Kabul to JED.', '2025-10-28 04:55:33', 'ticket_sale', 340, NULL, NULL);

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
(15, 1, 'Matiullah', 'admin@abc-construction.com', '0777305730', 'test', 80.00, 'USD', 'active', '2025-10-27 11:59:51');

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

--
-- Dumping data for table `creditor_transactions`
--

INSERT INTO `creditor_transactions` (`id`, `tenant_id`, `creditor_id`, `amount`, `currency`, `transaction_type`, `description`, `payment_date`, `reference_number`, `created_at`) VALUES
(19, 1, 15, 20.00, 'USD', 'debit', 'tete', '2025-10-27', '1212121', '2025-10-27 12:00:06');

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
(5, 1, 'Matiullah', 'admin@abc-construction.com', '0777305730', '6345', 'active', '2025-10-20 04:15:45', '2025-10-20 04:15:45');

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
(12, 1, 5, 'USD', 400.00, '2025-10-20 04:16:18', '2025-10-20 06:02:22');

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
(49, 1, 337, '32', 19, 13, 'Mr', 'KHASTA GUL', '86S99L', ' KBL', 'ELQ', '0771781576', 'FZ', 'Male', '2025-10-16', '2025-10-18', 'USD', 340.000, 330.030, 20.000, 30.000, '', 0, 'Sale for ticket: KHASTA GUL ( KBL to ELQ)', 1, '2025-10-18 10:21:55', '2025-10-18 10:21:55'),
(50, 1, 336, '31', 19, 13, 'Mr', 'MUHAMMAD RAUF', '5DYFCD', ' KBL', 'HAS', '0788808299', 'FZ', 'Male', '2025-10-16', '2025-10-18', 'USD', 375.000, 357.030, 10.000, 20.000, '', 0, 'asf', 1, '2025-10-18 10:24:26', '2025-10-18 10:24:26');

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
(40, 1, 'Matiullah', 'admin@abc-construction.com', '0777305730', 'test', 100.00, 'USD', 'active', '2025-10-27 11:52:04', '2025-10-27 11:52:04', 13, '');

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
(45, 1, 40, 100.00, 'USD', 'debit', 'Initial debt balance for Matiullah', 'DEBT-20251027115204-40', '2025-10-27', '2025-10-27 11:52:04');

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

--
-- Dumping data for table `demo_requests`
--

INSERT INTO `demo_requests` (`id`, `name`, `email`, `company`, `phone`, `company_size`, `preferred_date`, `preferred_time`, `message`, `status`, `created_at`, `updated_at`) VALUES
(1, 'KamAir', 'almuqadas_travel@yahoo.com', 'MTech', '0777305730', '11-50', '2025-10-04', '14:00:00', 'gsfdgsd', 'contacted', '2025-10-04 05:45:57', '2025-10-04 06:06:40');

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
-- Table structure for table `employee_terminations`
--

CREATE TABLE `employee_terminations` (
  `id` int(11) NOT NULL,
  `employee_id` int(11) NOT NULL,
  `terminated_by` int(11) NOT NULL,
  `termination_reason` text NOT NULL,
  `termination_date` datetime NOT NULL,
  `tenant_id` int(11) NOT NULL,
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

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `tenant_id`, `category_id`, `main_account_id`, `date`, `description`, `amount`, `currency`, `created_at`, `allocation_id`, `receipt_file`) VALUES
(214, 1, 22, 13, '2025-10-16', 'HOME EPENSES CAR OIL AND GYM FEESS', 7300.000, 'AFS', '2025-10-16 07:12:29', NULL, NULL),
(215, 1, 19, 13, '2025-10-16', 'FOR KITCHEN CREATING AND PARTITION IN OFFICE', 5050.000, 'AFS', '2025-10-16 07:15:00', NULL, NULL),
(216, 1, 19, 13, '2025-11-01', 'this is for test', 400.000, 'AFS', '2025-11-01 06:33:34', NULL, NULL),
(217, 1, 19, 13, '2025-11-01', 'this is for test', 100.000, 'AFS', '2025-11-01 06:44:36', NULL, 'receipt_6905ac54c7348.png');

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
(19, 1, 'OFFICE EXPS', '2025-09-01 11:17:05'),
(22, 1, 'self expenses', '2025-10-16 07:10:13');

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
(15, 1, 'HAMEED', '0786011115', 'Jada-e-Maiwand', 'Kabul', 'dehsabz', 1, 'Full Package', 'Madina and Makkah', 'Done', 'Applied', 130.00, 10.00, 0.00, 120.00, 0, '2025-10-18 09:32:44', '2025-10-27 11:17:28');

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
(47, 1, 'Mr', 'EDRISS ', 'NOOR', 'Male', '1089734', '2025-10-18', '2025-10-24', 'sfdsa', '2025-10-18', 32, '19', 13, '766666232', 15.000, 25.000, 0.000, 'USD', 'sadf', '', 1, '2025-10-18 05:56:12', '2025-10-27 11:16:33', 'refunded');

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
(15, 1, 47, 'full', 25.00, 'test', 'USD', 1.0000, 0, NULL, NULL, '2025-10-27 11:16:33', '2025-10-27 11:16:33');

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
  `transaction_type` varchar(100) NOT NULL,
  `amount` decimal(15,3) NOT NULL,
  `balance` decimal(15,3) NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `description` text DEFAULT NULL,
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
(116, 1, 1, 'logout', '2025-10-21 09:28:31'),
(117, 1, 1, 'logout', '2025-10-29 11:59:03'),
(118, 1, 1, 'logout', '2025-11-01 09:54:02');

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
(13, 1, 'SELF BANK (SAFE)', 'internal', NULL, NULL, NULL, 4513.000, 233838.000, 680.000, 505.000, '2025-11-01 11:14:36', 'active'),
(14, 1, 'AZIZI BANK', 'bank', NULL, '143241234', 'AZIZI', 100.000, 0.000, 0.000, 0.000, '2025-10-18 13:13:54', 'active'),
(16, 1, 'AFGHAN UNITED BANK', 'bank', '23451534', '3254524afs453', 'AUB', 0.000, 0.000, 0.000, 0.000, '2025-10-15 15:21:17', 'active');

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
  `receipt` varchar(100) DEFAULT NULL,
  `exchange_rate` decimal(10,5) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `main_account_transactions`
--

INSERT INTO `main_account_transactions` (`id`, `tenant_id`, `main_account_id`, `type`, `amount`, `balance`, `currency`, `description`, `created_at`, `transaction_of`, `reference_id`, `receipt`, `exchange_rate`) VALUES
(1190, 1, 13, 'credit', 4008.000, 4008.000, 'USD', 'Account funded by Sabaoon. Remarks: test. Receipt: 1', '2025-10-15 10:52:26', 'fund', 1, '1', NULL),
(1191, 1, 13, 'credit', 202360.000, 202360.000, 'AFS', 'Account funded by Sabaoon. Remarks: test. Receipt: 2', '2025-10-15 10:52:51', 'fund', 1, '2', NULL),
(1192, 1, 13, 'credit', 680.000, 680.000, 'EUR', 'Account funded by Sabaoon. Remarks: test. Receipt: 3', '2025-10-15 10:53:12', 'fund', 1, '3', NULL),
(1193, 1, 13, 'credit', 505.000, 505.000, 'DARHAM', 'Account funded by Sabaoon. Remarks: test. Receipt: 4', '2025-10-15 10:53:29', 'fund', 1, '4', NULL),
(1197, 1, 13, 'credit', 23800.000, 226160.000, 'AFS', 'PAID BY MR MATIULLAH 330*72.12=23800', '2025-10-16 07:04:32', 'ticket_sale', 337, NULL, 72.11000),
(1198, 1, 13, 'debit', 7300.000, 218860.000, 'AFS', 'HOME EPENSES CAR OIL AND GYM FEESS', '2025-10-16 07:12:29', 'expense', 214, '', NULL),
(1199, 1, 13, 'debit', 5050.000, 213810.000, 'AFS', 'FOR KITCHEN CREATING AND PARTITION IN OFFICE', '2025-10-16 07:15:00', 'expense', 215, '', NULL),
(1201, 1, 13, 'credit', 20550.000, 234360.000, 'AFS', 'CASH paid by mr matiullah', '2025-10-16 07:24:49', 'ticket_sale', 338, NULL, 72.80000),
(1205, 1, 13, 'credit', 100.000, 4108.000, 'USD', 'Supplier: KamAir, Withdrawn to main account: SELF BANK (SAFE), processed by: Sabaoon, Remarks: tet', '2025-10-16 12:17:17', 'supplier_fund_withdrawal', 916, '2452345', NULL),
(1210, 1, 14, 'credit', 100.000, 100.000, 'USD', 'teatr', '2025-10-18 08:43:54', 'transfer', 13, NULL, NULL),
(1221, 1, 13, 'credit', 10.000, 4118.000, 'USD', 'test', '2025-10-20 05:17:55', 'additional_payment', 50, NULL, 0.00000),
(1230, 1, 13, 'credit', 100.000, 4218.000, 'USD', '', '2025-10-20 05:42:33', 'deposit_sarafi', 42, 'DEP68f5cbbe4c5e7', NULL),
(1234, 1, 13, 'credit', 100.000, 4318.000, 'USD', '', '2025-10-20 05:53:07', 'deposit_sarafi', 44, 'DEP68f5ce3620d86', NULL),
(1237, 1, 13, 'credit', 100.000, 4418.000, 'USD', '', '2025-10-20 05:57:43', 'deposit_sarafi', 46, 'DEP68f5cf4cee5da', NULL),
(1240, 1, 13, 'credit', 100.000, 4518.000, 'USD', '', '2025-10-20 06:02:22', 'deposit_sarafi', 47, 'DEP68f5d063b5ce6', NULL),
(1241, 1, 13, 'debit', 100.000, 4418.000, 'USD', 'Supplier: ALAIN T, Funded by main account: SELF BANK (SAFE), processed by: Sabaoon, Remarks: test', '2025-10-20 10:14:17', 'supplier_fund', 936, '2452345', NULL),
(1242, 1, 13, 'credit', 100.000, 4518.000, 'USD', 'Client: DR SAHIBs, Received 100 USD for client account funding, processed by: Sabaoon, Remarks: ', '2025-10-20 10:14:54', 'client_fund', 584, '2452345', NULL),
(1243, 1, 13, 'debit', 10.000, 4508.000, 'USD', 'test', '2025-10-27 11:07:25', 'ticket_refund', 101, NULL, 0.00000),
(1244, 1, 13, 'credit', 10.000, 4518.000, 'USD', 'tets', '2025-10-27 11:08:18', 'date_change', 49, NULL, 0.00000),
(1245, 1, 13, 'credit', 10.000, 4528.000, 'USD', 'test', '2025-10-27 11:10:00', 'weight', 17, NULL, NULL),
(1246, 1, 13, 'credit', 10.000, 4538.000, 'USD', 'test', '2025-10-27 11:14:18', 'hotel', 47, NULL, NULL),
(1247, 1, 13, 'debit', 25.000, 4513.000, 'USD', 'Refund payment for Hotel Booking #47 - Mr EDRISS  NOOR', '2025-10-27 11:16:36', 'hotel_refund', 15, NULL, NULL),
(1248, 1, 13, 'credit', 10.000, 4523.000, 'USD', 'test', '2025-10-27 11:17:28', 'umrah', 96, '', NULL),
(1249, 1, 13, 'credit', 10.000, 4533.000, 'USD', 'test', '2025-10-27 11:21:27', 'visa_sale', 60, NULL, 0.00000),
(1250, 1, 13, 'debit', 100.000, 4433.000, 'USD', 'Initial debt balance for Matiullah', '2025-10-27 11:52:04', 'debtor', 45, 'DEBT-20251027115204-40', NULL),
(1251, 1, 13, 'credit', 100.000, 4533.000, 'USD', 'Initial credit balance for creditor: Matiullah', '2025-10-27 11:59:51', 'creditor', 15, NULL, NULL),
(1252, 1, 13, 'debit', 20.000, 4513.000, 'USD', 'tete', '2025-10-27 12:00:06', 'creditor', 19, NULL, NULL),
(1253, 1, 13, 'debit', 400.000, 233938.000, 'AFS', 'this is for test', '2025-11-01 06:33:34', 'expense', 216, '31531', NULL),
(1254, 1, 13, 'debit', 100.000, 233838.000, 'AFS', 'this is for test', '2025-11-01 06:44:36', 'expense', 217, 'adfasdf', NULL);

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
-- Table structure for table `message_reactions`
--

CREATE TABLE `message_reactions` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `emoji` varchar(10) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `message_reactions`
--

INSERT INTO `message_reactions` (`id`, `message_id`, `user_id`, `emoji`, `tenant_id`, `created_at`) VALUES
(1, 28, 1, '👍', 1, '2025-10-28 12:18:22'),
(4, 26, 1, '❤️', 1, '2025-10-28 12:30:02'),
(6, 26, 1, '😂', 1, '2025-10-29 10:23:02'),
(7, 25, 1, '👍', 1, '2025-10-29 10:23:06');

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
(1002, 1, 1196, 'ticket_sale', 'New payment received for ticket booking #86S99L - Mr KHASTA GUL: Amount AFS 23800.00', 'Admin', 'Unread', '2025-10-15 12:13:22'),
(1003, 1, 1197, 'ticket_sale', 'New payment received for ticket booking #86S99L - Mr KHASTA GUL: Amount AFS 23800.00', 'Admin', 'Unread', '2025-10-16 07:06:02'),
(1004, 1, 1198, 'expense', 'New expense added for category self expenses: Amount AFS 7300.00 - HOME EPENSES CAR OIL AND GYM FEESS', 'Admin', 'Unread', '2025-10-16 07:12:29'),
(1005, 1, 1199, 'expense', 'New expense added for category OFFICE EXPS: Amount AFS 5050.00 - FOR KITCHEN CREATING AND PARTITION IN OFFICE', 'Admin', 'Unread', '2025-10-16 07:15:00'),
(1006, 1, 911, 'supplier_fund', 'Supplier: NSTTRIP, Funded 1000 USD by main account: SELF BANK (SAFE), processed by: Sabaoon, Remarks: ', 'Admin', 'Unread', '2025-10-16 07:20:03'),
(1007, 1, 1201, 'ticket_sale', 'New payment received for ticket booking #T6EXHA - Mr BAIKHT BAIDAR BAKHT JEHAN: Amount AFS 20550.00', 'Admin', 'Unread', '2025-10-16 07:25:23'),
(1008, 1, 568, 'client_fund', 'Client: MINA Khan, Paid 431 USD for client account funding, processed by: Sabaoon, Remarks: test', 'Admin', 'Unread', '2025-10-16 11:47:31'),
(1009, 1, 913, 'supplier_fund', 'Supplier: NSTTRIP, Funded 1000 USD by main account: SELF BANK (SAFE), processed by: Sabaoon, Remarks: fafsd', 'Admin', 'Unread', '2025-10-16 11:57:05'),
(1010, 1, 914, 'supplier_fund', 'Supplier: NSTTRIP, Funded 1000 USD by main account: SELF BANK (SAFE), processed by: Sabaoon, Remarks: asdf', 'Admin', 'Unread', '2025-10-16 12:08:19'),
(1011, 1, 915, 'supplier_bonus', 'Bonus of 100 USD added to supplier: KamAir, processed by: Sabaoon, Remarks: adfasd', 'Admin', '', '2025-10-16 12:14:27'),
(1012, 1, 916, 'supplier_fund_withdrawal', 'Supplier: KamAir, Withdrawn 100 USD to main account: SELF BANK (SAFE), processed by: Sabaoon, Remarks: tet', 'Admin', 'Unread', '2025-10-16 12:17:17'),
(1013, 1, 917, 'supplier_fund_withdrawal', 'Supplier: KamAir, Withdrawn 100 USD to main account: SELF BANK (SAFE), processed by: Sabaoon, Remarks: dsafasdf', 'Admin', 'Unread', '2025-10-16 12:34:22'),
(1014, 1, 1211, 'additional_payment', 'New additional payment received: Amount USD 10.00 - test', 'Admin', 'Unread', '2025-10-18 10:54:44'),
(1015, 1, 1213, 'debtor', 'Payment received from debtor: Amount USD 200.00 - test', 'Admin', 'Unread', '2025-10-18 11:55:36'),
(1016, 1, 1215, 'creditor', 'Payment made to creditor:  - Amount USD 100.00', 'Admin', 'Unread', '2025-10-18 12:07:48'),
(1017, 1, 1217, 'debtor', 'Payment received from debtor: Amount USD 200.00 - adfs', 'Admin', 'Unread', '2025-10-20 03:56:04'),
(1018, 1, 1218, 'deposit_sarafi', 'New deposit from Matiullah: USD 100 - Reference: DEP68f5b77209901', 'Admin', 'Unread', '2025-10-20 04:16:18'),
(1019, 1, 1219, 'additional_payment', 'New additional payment received: Amount USD 10.00 - fdsaf', 'Admin', 'Unread', '2025-10-20 04:47:03'),
(1020, 1, 1220, 'additional_payment', 'New additional payment received: Amount USD 10.00 - test', 'Admin', 'Unread', '2025-10-20 04:48:56'),
(1021, 1, 1221, 'additional_payment', 'New additional payment received: Amount USD 10.00 - test', 'Admin', 'Unread', '2025-10-20 05:18:00'),
(1022, 1, 1223, 'debtor', 'Payment received from debtor: Amount USD 100.00 - test', 'Admin', 'Unread', '2025-10-20 05:19:40'),
(1023, 1, 1225, 'creditor', 'Payment made to creditor:  - Amount USD 100.00', 'Admin', 'Unread', '2025-10-20 05:21:22'),
(1024, 1, 1226, 'deposit_sarafi', 'New deposit from Matiullah: USD 100 - Reference: DEP68f5c6f3bbf75', 'Admin', 'Unread', '2025-10-20 05:22:08'),
(1025, 1, 1227, 'withdrawal_sarafi', 'New withdrawal by Matiullah: USD 50 - Reference: WDR68f5c701054ce', 'Admin', 'Unread', '2025-10-20 05:22:36'),
(1026, 1, 1229, 'debtor', 'Payment received from debtor: Amount USD 100.00 - test', 'Admin', 'Unread', '2025-10-20 05:42:02'),
(1027, 1, 1230, 'deposit_sarafi', 'New deposit from Matiullah: USD 100 - Reference: DEP68f5cbbe4c5e7', 'Admin', 'Unread', '2025-10-20 05:42:33'),
(1028, 1, 1231, 'withdrawal_sarafi', 'New withdrawal by Matiullah: USD 50 - Reference: WDR68f5cbca1089e', 'Admin', 'Unread', '2025-10-20 05:42:45'),
(1029, 1, 1233, 'debtor', 'Payment received from debtor: Amount USD 100.00 - test', 'Admin', 'Unread', '2025-10-20 05:52:39'),
(1030, 1, 1234, 'deposit_sarafi', 'New deposit from Matiullah: USD 100 - Reference: DEP68f5ce3620d86', 'Admin', 'Unread', '2025-10-20 05:53:07'),
(1031, 1, 1235, 'withdrawal_sarafi', 'New withdrawal by Matiullah: USD 50 - Reference: WDR68f5ce43b09bc', 'Admin', 'Unread', '2025-10-20 05:53:19'),
(1032, 1, 1237, 'deposit_sarafi', 'New deposit from Matiullah: USD 100 - Reference: DEP68f5cf4cee5da', 'Admin', 'Unread', '2025-10-20 05:57:43'),
(1033, 1, 1239, 'creditor', 'Payment made to creditor:  - Amount USD 100.00', 'Admin', 'Unread', '2025-10-20 06:02:08'),
(1034, 1, 1240, 'deposit_sarafi', 'New deposit from Matiullah: USD 100 - Reference: DEP68f5d063b5ce6', 'Admin', 'Unread', '2025-10-20 06:02:22'),
(1035, 1, 936, 'supplier_fund', 'Supplier: ALAIN T, Funded 100 USD by main account: SELF BANK (SAFE), processed by: Sabaoon, Remarks: test', 'Admin', 'Unread', '2025-10-20 10:14:17'),
(1036, 1, 584, 'client_fund', 'Client: DR SAHIBs, Paid 100 USD for client account funding, processed by: Sabaoon, Remarks: ', 'Admin', 'Unread', '2025-10-20 10:14:54'),
(1037, 1, 1243, 'ticket_refund', 'Refund payment for Agency client ticket #5DYFCD - Mr MUHAMMAD RAUF: Amount USD 10.00', 'Admin', 'Unread', '2025-10-27 11:07:56'),
(1038, 1, 1244, 'ticket_date_change', 'New payment received for date change #86S99L - Mr KHASTA GUL: Amount USD 10.00', 'Admin', 'Unread', '2025-10-27 11:08:26'),
(1039, 1, 1245, 'weight', 'New payment received for weight charge #5DYFCD - Mr MUHAMMAD RAUF: Amount USD 10.00', 'Admin', 'Unread', '2025-10-27 11:10:15'),
(1040, 1, 1246, 'hotel', 'New payment received for hotel booking #1089734 - Mr EDRISS  NOOR: Amount USD 10.00', 'Admin', 'Unread', '2025-10-27 11:14:18'),
(1041, 1, 1247, 'hotel_refund', 'Hotel refund payment for Agency client - EDRISS  (NOOR) Amount USD 25.00', 'Admin', 'Unread', '2025-10-27 11:16:48'),
(1042, 1, 96, 'umrah', 'Customer: Matiullah has paid: 10 USD to Internal Account processed by SABAOON CAR REPAIR for the Umrah booking.', 'Admin', 'Unread', '2025-10-27 11:17:28'),
(1043, 1, 1249, 'visa', 'New payment received for visa application #60 - BAKHTIAR STANIKZAI: Amount USD 10.00', 'Admin', 'Unread', '2025-10-27 11:21:37'),
(1044, 1, 1252, 'creditor', 'Payment made to creditor:  - Amount USD 20.00', 'Admin', 'Unread', '2025-10-27 12:00:06'),
(1045, 1, 1253, 'expense', 'New expense added for category OFFICE EXPS: Amount AFS 100.00 - this is for test', 'Admin', 'Unread', '2025-11-01 06:33:34'),
(1046, 1, 1253, 'expense_update', 'Expense updated for category OFFICE EXPS: Amount AFS 200.00 - this is for test', 'Admin', 'Read', '2025-11-01 06:34:11'),
(1047, 1, 1253, 'expense', 'New expense added for category OFFICE EXPS: Amount AFS 200.00 - this is for test', 'Admin', 'Read', '2025-11-01 06:34:11'),
(1048, 1, 1253, 'expense', 'New expense added for category OFFICE EXPS: Amount AFS 300.00 - this is for test', 'Admin', 'Read', '2025-11-01 06:40:58'),
(1049, 1, 1254, 'expense', 'New expense added for category OFFICE EXPS: Amount AFS 100.00 - this is for test', 'Admin', 'Unread', '2025-11-01 06:44:36');

-- --------------------------------------------------------

--
-- Table structure for table `payment_sessions`
--

CREATE TABLE `payment_sessions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(255) NOT NULL,
  `subscription_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) DEFAULT 'AFN',
  `status` enum('pending','completed','failed') DEFAULT 'pending',
  `transaction_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

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
-- Table structure for table `performance_reviews`
--

CREATE TABLE `performance_reviews` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `review_date` date NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `overall_rating` decimal(3,2) DEFAULT NULL COMMENT 'Rating from 1.00 to 5.00',
  `comments` text DEFAULT NULL,
  `achievements` text DEFAULT NULL,
  `areas_for_improvement` text DEFAULT NULL,
  `goals` text DEFAULT NULL,
  `recommendations` text DEFAULT NULL,
  `status` enum('draft','submitted','approved','rejected') DEFAULT 'draft',
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
(1, 'basic', 'Basic plan: Streamline your agency’s ticket operations with this plan. Manage bookings, reservations, refunds, and date changes effortlessly. Keep track of ticket profits, internal expenses, and generate basic financial statements — all automated to save time and reduce errors. Includes internal staff chat for smooth communication.', '[\n    \"ticket_bookings\",\n    \"ticket_reservations\",\n    \"refunded_tickets\",\n    \"date_change_tickets\",\n    \"ticket_weights\",\n    \"financial_statements\",\n    \"inter_tenant_chat\",\n    \"expense_management\"\n  ]\n', 1000.00, 10, 14, 'active', '2025-08-23 11:28:15', '2025-11-13 08:03:57'),
(2, 'pro', 'Pro plan: Take your agency operations to the next level. Includes all Basic ticket management features, plus full visa and hotel management. Add extra services with automatic profit tracking. Collaborate with other agencies using limited inter-tenant chat, allowing secure trading of tickets, visas, and Umrah services. Advanced financial statements help you monitor profits and expenses easily.', '[\n    \"ticket_bookings\",\n    \"ticket_reservations\",\n    \"refunded_tickets\",\n    \"date_change_tickets\",\n    \"ticket_weights\",\n    \"visa_applications\",\n    \"visa_refunds\",\n    \"visa_transactions\",\n    \"hotel_bookings\",\n    \"hotel_refunds\",\n    \"additional_payments\",\n    \"expense_management\",\n    \"inter_tenant_chat\"\n  ]\n', 2500.00, 15, 14, 'active', '2025-08-23 11:28:15', '2025-11-13 08:04:10'),
(3, 'enterprise', 'Enterprise plan: The complete solution for large or multi-branch agencies. Includes all Pro features, plus full Umrah management, HR & payroll automation (salaries, JV payments, additional staff payments), debtor/creditor tracking, Sarafi/currency management, assets management, and manage maktobs. Unlimited inter-tenant collaboration allows multiple agencies to trade services seamlessly, while real-time financial statements give you complete control over your agency’s operations.', '[\n    \"ticket_bookings\",\n    \"ticket_reservations\", \n    \"refunded_tickets\",\n    \"date_change_tickets\",\n    \"ticket_weights\",\n    \"hotel_bookings\",\n    \"hotel_refunds\",\n    \"visa_applications\",\n    \"visa_refunds\",\n    \"visa_transactions\", \n    \"inter_tenant_chat\",\n    \"umrah_bookings\",\n    \"umrah_refunds\",\n    \"debtors\",\n    \"creditors\",\n    \"sarafi\",\n    \"salary\",\n    \"additional_payments\",\n    \"jv_payments\",\n    \"manage_maktobs\",\n    \"assets\",\n    \"financial_statements\",\n    \"expense_management\"\n]', 4500.00, 30, 14, 'active', '2025-08-23 11:28:15', '2025-11-13 08:04:36'),
(4, 'Umrah', 'Features: All the Umrah-specific features (family management, member management, ID card generation, agreement generation, cancellation generation, refund processing, payment processing, multi-currency)', '[\n    \"inter_tenant_chat\",\n    \"umrah_bookings\",\n    \"umrah_refunds\",\n    \"salary\",\n    \"financial_statements\",\n    \"expense_management\"\n]', 2000.00, 10, 14, 'active', '2025-11-15 03:41:26', '2025-11-15 05:50:09');

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
(22, 'platform_description', 'Professional travel agency management platform designed to optimize workflows, enhance customer service, and drive business growth through comprehensive automation and intelligent insights.', 'string', NULL, '2025-08-12 07:04:53', '2025-10-29 08:31:35'),
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
(99, 1, 339, 13, 22, '35', 'Mr', 'HAJI MUSA GUL MINAH KHAN', '181QHU', ' KBL', 'JED', '0773513830', 'KM', 'Male', '2025-10-16', '2025-10-17', 'USD', 431.000, 403.000, 20.000, 30.000, 353.000, 'Refunded', '', 'test', 1, '2025-10-18 10:19:21', '2025-10-18 10:19:21', 'base'),
(100, 1, 338, 13, 19, '32', 'Mr', 'BAIKHT BAIDAR BAKHT JEHAN', 'T6EXHA', ' KBL', 'ELQ', '0766202122', 'FZ', 'Male', '2025-10-16', '2025-10-25', 'USD', 330.000, 320.030, 20.000, 30.000, 270.030, 'Refunded', '', 'test', 1, '2025-10-18 10:20:52', '2025-10-18 10:20:52', 'base'),
(101, 1, 336, 13, 19, '31', 'Mr', 'MUHAMMAD RAUF', '5DYFCD', ' KBL', 'HAS', '0788808299', 'FZ', 'Male', '2025-10-16', '2025-10-18', 'USD', 375.000, 357.030, 20.000, 30.000, 325.000, '', '', 'test', 1, '2025-10-18 10:23:58', '2025-10-18 10:23:58', 'sold');

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

--
-- Dumping data for table `salary_adjustments`
--

INSERT INTO `salary_adjustments` (`id`, `tenant_id`, `user_id`, `adjustment_type`, `amount`, `percentage`, `effective_date`, `previous_salary`, `new_salary`, `reason`, `approved_by`, `created_at`) VALUES
(2, 1, 6, 'increment', 1000.00, 0.00, '2025-10-20', 10000.00, 11000.00, 'test', 1, '2025-10-20 04:43:01');

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

--
-- Dumping data for table `salary_bonuses`
--

INSERT INTO `salary_bonuses` (`id`, `tenant_id`, `user_id`, `amount`, `description`, `bonus_date`, `type`, `created_by`, `created_at`) VALUES
(2, 1, 6, 1000.00, 'test', '2025-10-20', 'performance', 1, '2025-10-20 04:43:46');

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
(9, 1, 6, 11000.00, 'AFS', '2025-10-20', 28, 'active', '2025-10-20 04:42:34', '2025-10-20 04:43:01');

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
(42, 1, 5, 100.00, 'USD', 'deposit', 'completed', '', 'DEP68f5cbbe4c5e7', NULL, '2025-10-20 05:42:33', '2025-10-20 05:42:33'),
(44, 1, 5, 100.00, 'USD', 'deposit', 'completed', '', 'DEP68f5ce3620d86', NULL, '2025-10-20 05:53:07', '2025-10-20 05:53:07'),
(46, 1, 5, 100.00, 'USD', 'deposit', 'completed', '', 'DEP68f5cf4cee5da', NULL, '2025-10-20 05:57:43', '2025-10-20 05:57:43'),
(47, 1, 5, 100.00, 'USD', 'deposit', 'completed', '', 'DEP68f5d063b5ce6', NULL, '2025-10-20 06:02:22', '2025-10-20 06:02:22');

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
(6, 1, 50.00, 'AFN', '2025-09-18', 'Hesabpay', NULL, 'hesabpay_68cbd15d22905', NULL, 1, '2025-09-18 09:31:09', '2025-09-18 09:31:09'),
(7, 1, 10.00, 'AFN', '2025-10-20', 'Hesabpay', NULL, NULL, NULL, 1, '2025-10-20 06:31:16', '2025-10-20 06:31:16'),
(8, 1, 10.00, 'AFN', '2025-10-20', 'Hesabpay', NULL, NULL, NULL, 1, '2025-10-20 07:05:01', '2025-10-20 07:05:01'),
(9, 1, 10.00, 'AFN', '2025-10-20', 'Hesabpay', NULL, NULL, NULL, 1, '2025-10-20 07:08:18', '2025-10-20 07:08:18'),
(10, 1, 10.00, 'AFN', '2025-10-20', 'Hesabpay', NULL, NULL, NULL, 1, '2025-10-20 07:15:26', '2025-10-20 07:15:26'),
(11, 1, 10.00, 'AFN', '2025-10-20', 'HesabPay', NULL, NULL, NULL, 1, '2025-10-20 07:45:49', '2025-10-20 07:45:49'),
(12, 1, 10.00, 'AFN', '2025-10-20', 'HesabPay', NULL, NULL, NULL, 1, '2025-10-20 07:56:50', '2025-10-20 07:56:50'),
(13, 1, 10.00, 'AFN', '2025-10-23', 'HesabPay', NULL, NULL, NULL, 1, '2025-10-23 06:46:01', '2025-10-23 06:46:01'),
(14, 1, 10.00, 'AFN', '2025-10-23', 'HesabPay', '0313220001761203998', NULL, NULL, 1, '2025-10-23 07:20:11', '2025-10-23 07:20:11'),
(15, 1, 10.00, 'AFN', '2025-10-23', 'HesabPay', '0078794001761204236', '0078794001761204236', NULL, 1, '2025-10-23 07:24:07', '2025-10-23 07:24:07'),
(16, 1, 10.00, 'AFN', '2025-10-29', 'HesabPay', '0184819001761720728', '0184819001761720728', NULL, 1, '2025-10-29 06:52:21', '2025-10-29 06:52:21');

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
(31, 1, 'KamAir', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 381.490, '2025-10-15 11:11:34', '2025-10-18 05:55:22', 'active'),
(32, 1, 'NSTTRIP', 'SABAOON', 'External', '0777305730', 'admin@abc-construction.com', 'nsst', 'USD', 600.650, '2025-10-15 11:57:18', '2025-10-28 04:56:10', 'active'),
(33, 1, 'Ariana', 'SABAOON', 'External', '0777305730', 'admin@abc-construction.com', 'nsst', 'AFS', 47468.000, '2025-10-15 11:59:01', '2025-10-15 11:59:01', 'active'),
(34, 1, 'Yasin', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'yasin', 'USD', 406.000, '2025-10-15 12:05:10', '2025-10-15 12:05:10', 'active'),
(35, 1, 'ALAIN T', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'adfa', 'USD', -5056.000, '2025-10-16 11:31:23', '2025-10-20 10:17:38', 'active'),
(36, 1, 'Matiullah', 'NAVEED RASHIQ', 'Internal', '0777305730', 'admin@abc-construction.com', 'test', 'USD', 0.000, '2025-10-28 04:53:28', '2025-10-28 04:53:28', 'active');

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
(908, 1, 31, 336, 'Debit', 'ticket_sale', 357.030, 64.460, 'Base amount of 357.03 USD deducted for ticket booking for Mr MUHAMMAD RAUF with PNR: 5DYFCD. (Transferred from supplier 32)', '2025-10-18 04:30:02', '2025-10-18 04:30:02', ''),
(909, 1, 32, 337, 'Debit', 'ticket_sale', 335.030, 825.650, 'Updated: Updated: Base amount of 320.03 USD deducted for ticket booking for Mr KHASTA GUL with PNR: 86S99L.', '2025-10-16 07:03:05', '2025-10-25 04:28:04', ''),
(910, 1, 32, 338, 'Debit', 'ticket_sale', 320.030, 505.620, 'Base amount of 320.03 USD deducted for ticket booking for Mr BAIKHT BAIDAR BAKHT JEHAN with PNR: T6EXHA.', '2025-10-16 07:04:32', '2025-10-25 04:28:04', ''),
(912, 1, 35, 339, 'Debit', 'ticket_sale', 403.000, -5219.000, 'Base amount of 403 USD deducted for ticket booking for Mr HAJI MUSA GUL MINAH KHAN with PNR: 181QHU.', '2025-10-16 11:35:11', '2025-10-16 11:35:11', ''),
(918, 1, 32, 18, '', 'ticket_reserve', 100.000, 405.620, 'Base amount of 100 USD deducted for ticket reservation. (Supplier changed)', '2025-10-18 05:23:08', '2025-10-25 04:28:04', ''),
(919, 1, 35, 18, 'Debit', 'ticket_reserve', 110.000, -5329.000, 'Updated: Purchase for ticket: BAKHTIAR STANIKZAI (MZR to FRA)', '2025-10-18 05:27:14', '2025-10-18 05:46:57', ''),
(920, 1, 35, 99, 'Credit', 'ticket_refund', 383.000, -4946.000, 'Refund for ticket HAJI MUSA GUL MINAH KHAN added to account.', '2025-10-18 05:49:21', '2025-10-18 05:49:21', ''),
(921, 1, 32, 100, 'Credit', 'ticket_refund', 300.030, 805.650, 'Refund for ticket BAIKHT BAIDAR BAKHT JEHAN added to account.', '2025-10-18 05:50:52', '2025-10-25 04:28:04', ''),
(922, 1, 32, 49, 'Debit', 'date_change', 20.000, 785.650, 'Penalty for ticket Name KHASTA GUL date change deducted from account', '2025-10-18 05:51:55', '2025-10-25 04:28:04', ''),
(923, 1, 31, 101, 'Credit', 'ticket_refund', 337.030, 401.490, 'Refund for ticket MUHAMMAD RAUF added to account.', '2025-10-18 05:53:58', '2025-10-18 05:53:58', ''),
(924, 1, 31, 50, 'Debit', 'date_change', 10.000, 391.490, 'Penalty for ticket Name MUHAMMAD RAUF date change deducted from account', '2025-10-18 05:54:26', '2025-10-18 05:54:26', ''),
(925, 1, 32, 16, 'Debit', 'weight_sale', 30.000, 755.650, 'Base amount of 30 USD deducted for weight transaction.', '2025-10-18 05:54:51', '2025-10-25 04:28:04', ''),
(926, 1, 31, 17, 'Debit', 'weight_sale', 10.000, 381.490, 'Base amount of 10 USD deducted for weight transaction.', '2025-10-18 05:55:22', '2025-10-18 05:55:22', ''),
(927, 1, 32, 47, 'Debit', 'hotel', 15.000, 740.650, 'Updated: Hotel booking for Mr EDRISS  NOOR', '2025-10-18 05:56:12', '2025-10-25 04:28:04', ''),
(928, 1, 32, 47, 'Debit', 'hotel', 15.000, 695.650, 'Purchase for hotel booking: EDRISS  NOOR (Check-in: 2025-10-18)', '2025-10-18 06:32:11', '2025-10-25 04:28:04', ''),
(930, 1, 35, 60, 'Debit', 'visa_sale', 110.000, -5056.000, 'Purchase for visa: BAKHTIAR STANIKZAI (Passport: P8798765)', '2025-10-18 09:22:00', '2025-10-18 09:22:00', ''),
(933, 1, 35, 66, 'Debit', 'umrah', 100.000, -5156.000, 'Purchase for all: Matiullah (Passport: P03241263)', '2025-10-18 09:48:40', '2025-10-18 09:48:40', ''),
(934, 1, 32, 51, 'Debit', 'additional_payment', 10.000, 685.650, 'test', '2025-10-18 10:29:46', '2025-10-25 04:28:04', ''),
(936, 1, 35, 1, 'Credit', 'fund', 100.000, -5056.000, 'Supplier: ALAIN T, Funded by main account: SELF BANK (SAFE), processed by: Sabaoon, Remarks: test', '2025-10-20 10:14:17', '2025-10-20 10:17:38', '2452345'),
(937, 1, 32, 15, 'Credit', 'hotel_refund', 15.000, 700.650, 'Refund for hotel booking #47 - test', '2025-10-27 11:16:33', '2025-10-27 11:16:33', ''),
(938, 1, 32, 340, 'Debit', 'ticket_sale', 100.000, 600.650, 'Base amount of 100 USD deducted for ticket booking for Mr guli with PNR: 86S99L. (Transferred from supplier 36)', '2025-10-28 04:56:10', '2025-10-28 04:56:10', '');

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
(1, 'Al Moqadas Travel Agency', 'alpha', 'tenant-alpha-001', 'active', 'basic', 'billing@alpha.com', 26214400, 'image/,video/,audio/,application/pdf,text/', 0, 'current', '2025-10-17', '2025-09-17', 0, NULL, '2025-06-30 19:30:00', '2025-11-15 03:54:05', NULL),
(2, 'Al Wali', 'beta', 'tenant-beta-002', 'active', 'basic', 'billing@beta.com', 26214400, 'image/,video/,audio/,application/pdf,text/', 0, 'current', '2026-09-17', '2025-09-17', 0, '2025-09-17 11:09:30', '2025-07-14 19:30:00', '2025-11-15 03:53:54', NULL),
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
(1, 1, '1', 'active', 'monthly', '2025-07-23 19:30:00', NULL, 1000.00, 'AFN', 'stripe', '2025-10-28 19:30:00', '0000-00-00 00:00:00', 'txn_123456789', '2025-07-23 19:30:00', '2025-11-15 03:43:24'),
(2, 2, '1', 'active', 'yearly', '2025-07-31 19:30:00', NULL, 1000.00, 'USD', 'paypal', '2025-09-16 19:30:00', '0000-00-00 00:00:00', 'txn_987654321', '2025-07-31 19:30:00', '2025-11-15 03:53:42'),
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
(4, 1, 'Ahmad Rahimi', 'uploads/testimonials/testimonial_68ecd199e9b8c.png', 'MTravels has completely transformed our travel agency operations. The flight booking system is incredibly efficient and our customer satisfaction has increased by 40%.', 'Dubai', 5, 1, '2025-09-09 04:04:20', '2025-10-13 10:16:57'),
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
(336, 1, NULL, '31', 19, 13, 'Mr', 'MUHAMMAD RAUF', '5DYFCD', ' KBL', 'HAS', '0788808299', 'FZ', 'Male', '2025-10-16', '2025-10-18', 'USD', 357.030, 375.000, 0.000, 17.970, 'Date Changed', '', 'test', '2025-10-16 07:01:45', '2025-10-18 05:54:26', 'one_way', '0000-00-00', '', '', 1),
(337, 1, NULL, '32', 18, 13, 'Mr', 'KHASTA GUL', '86S99L', ' KBL', 'ELQ', '0771781576', 'FZ', 'Male', '2025-10-16', '2025-10-25', 'USD', 335.030, 350.000, 0.000, 14.970, 'Date Changed', '', 'Sale for ticket: KHASTA GUL ( KBL to ELQ)', '2025-10-16 07:03:05', '2025-10-25 04:30:33', 'one_way', '0000-00-00', '', '', 1),
(338, 1, NULL, '32', 19, 13, 'Mr', 'BAIKHT BAIDAR BAKHT JEHAN', 'T6EXHA', ' KBL', 'ELQ', '0766202122', 'FZ', 'Male', '2025-10-16', '2025-10-25', 'USD', 320.030, 330.000, 0.000, 9.970, 'Refunded', '', 'test', '2025-10-16 07:04:32', '2025-10-18 05:50:52', 'one_way', '0000-00-00', NULL, '', 1),
(339, 1, NULL, '35', 22, 13, 'Mr', 'HAJI MUSA GUL MINAH KHAN', '181QHU', ' KBL', 'JED', '0773513830', 'KM', 'Male', '2025-10-16', '2025-10-17', 'USD', 403.000, 431.000, 0.000, 28.000, 'Refunded', '', 'test', '2025-10-16 11:35:11', '2025-10-18 05:49:21', 'one_way', '0000-00-00', NULL, '', 1),
(340, 1, NULL, '32', 19, 13, 'Mr', 'guli', '86S99L', 'Kabul', 'JED', '0771781576', 'A3', 'Male', '2025-10-28', '2025-10-29', 'USD', 100.000, 120.000, 0.000, 20.000, 'Booked', '', 'test', '2025-10-28 04:55:33', '2025-10-29 10:53:46', 'one_way', '0000-00-00', '', '', 1);

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
(18, 1, '35', 18, 13, 'Mr', 'BAKHTIAR STANIKZAI', '188JZ0', 'MZR', 'FRA', '0777305730', 'EI', 'Male', '2025-10-18', '2025-10-23', 'USD', 110.000, 130.000, 20.000, 'Reserved', '', 'Sale for ticket: BAKHTIAR STANIKZAI (MZR to FRA)', '2025-10-18 05:23:08', '2025-10-18 05:47:40', 'one_way', '0000-00-00', '', '', 1);

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
(16, 1, 337, 20.00, 30.00, 40.00, 10.00, 'fasdf', 0, '2025-10-18 10:24:51', '2025-10-18 10:24:51'),
(17, 1, 336, 20.00, 10.00, 20.00, 10.00, 'sdfas', 0, '2025-10-18 10:25:22', '2025-10-18 10:25:22');

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
(49, 1, 1, 'staff', 'DTCY-YDG6-DGSM-AGGP', 0, '2025-11-01 11:58:54', NULL),
(50, 1, 1, 'staff', 'M79U-Q12B-IZ54-PZGI', 0, '2025-11-01 11:58:54', NULL),
(51, 1, 1, 'staff', 'SPNY-YYX8-69Q1-RTRQ', 0, '2025-11-01 11:58:54', NULL),
(52, 1, 1, 'staff', '6DSM-M6TI-R9I6-N30U', 0, '2025-11-01 11:58:54', NULL),
(53, 1, 1, 'staff', 'MFKH-PFN9-XVZR-MQ1E', 0, '2025-11-01 11:58:54', NULL),
(54, 1, 1, 'staff', 'HE8P-ZN3E-UBYN-N8ZC', 0, '2025-11-01 11:58:54', NULL),
(55, 1, 1, 'staff', 'WLPA-GMV5-K3PR-LYO3', 0, '2025-11-01 11:58:54', NULL),
(56, 1, 1, 'staff', 'HM4G-NPIV-B28F-8DFW', 0, '2025-11-01 11:58:54', NULL);

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
(11, 1, 1, 'staff', 'F4P6P43CGPWGHI34YM6BTKDKE56HFS45XEE2PSZNDLT65E33XEQS6GUVSTZZLHOPVTMNAKN3FQHOS56GMOUM4M5QQFRCI23DXIV3KWI', 0, '2025-11-01 11:58:54', NULL);

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
(66, 1, 15, 18, 13, '2025-10-18', 'Matiullah', 'FAIZ MOHAMMAD', 'ESMAT ULLAH', 'Uncle', '2025-10-25', 'Male', 'P03241263', '2026-04-25', 'ID Original + Passport Original', NULL, NULL, '21 Days', 'Shared', 100.000, 130.000, 0.000, 30.000, 0.000, '', 10.000, 120.000, 'USD', 1, '2025-10-18 09:38:20', '2025-10-27 11:17:28', 'yrsd', 'active');

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
(34, 1, 66, 'all', 35, 100.000, 130.000, 30.000, 'USD', '2025-10-18 10:03:59', '2025-10-18 10:03:59');

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
(96, 1, 66, 'Credit', 'Internal Account', '2025-10-27', 'test', 10.000, '', 'USD', NULL, '2025-10-27 11:17:28');

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
  `updated_at` datetime DEFAULT NULL,
  `deleted_at` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `tenant_id`, `name`, `email`, `password`, `created_at`, `role`, `phone`, `address`, `hire_date`, `profile_pic`, `totp_enabled`, `fired`, `fired_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 'SABAOON CAR REPAIR', 'almuqadas_travel@yahoo.com', '$2y$10$McogSKPqbHafcuzb.fOiLexQrCt2CR.EUCDNxT3vD/SNDC.P2fWA2', '2024-12-24 13:11:23', 'admin', '0786011115', 'kabul, jada-ee-mewand', '2024-12-04', '68f74bc0ade30_White and Blue Modern Travel Poster.png', 0, 0, NULL, NULL, NULL),
(6, 1, 'Idrees', 'idress@gmail.com', '$2y$10$LJc9TC9ekIpXNSag3kIWu.d45oVeJLbp9zP0a1a04b6lZOo/CltH6', '2025-04-09 10:29:29', 'sales', '0777555594', 'Jada-e-Maiwand', '2025-04-09', '6905da1a0e1d6_WhatsApp Image 2025-10-27 at 11.31.26 AM.jpeg', 0, 0, '2025-10-20 09:12:42', NULL, NULL),
(7, 2, 'Matiullah Rahimi', 'mati@gmail.com', '$2y$10$Yr61g65gW/w8UjFfG3kLmephVrFKf2m6zGqMrTdgvmPcy8pg/Bv/e', '2025-04-09 10:30:25', 'admin', '0777555594', 'Jada-e-Maiwand', '2025-04-09', '68bd507105c6e_Blue and White Grunge Travel and Tourism Instagram Post.png', 0, 0, '2025-09-01 13:22:36', NULL, NULL),
(14, NULL, 'ALLAH DAD MUHAMMADI', 'allahdadmuhammadi01@gmail.com', '$2y$10$5BbWc37e43gokcY5etVUauiZZFP/uLeYQrGFJaUkrEGSxvvXnsQnS', '2025-05-19 08:31:47', 'super_admin', '0780310431', 'KABUL AFGHANISTAN', '2025-05-19', '682aadaaef90b.jpg', 0, 0, NULL, NULL, NULL),
(18, 1, 'Matiullah', 'matiullahr970@gmail.com', '$2y$10$fh.T7oUwq/iPapaztUlf6.XSUBsCbbn2bRtEeHGiPY3Yd.chZ1iEa', '2025-10-29 16:28:44', 'finance', '0777305730', 'test', '2025-10-29', '690307f9d794a_umrah.png', 0, 0, NULL, '2025-10-29 16:28:44', NULL);

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
(60, 1, 35, 19, 13, '0777555594', 'Mr', 'Male', 'BAKHTIAR STANIKZAI', 'P8798765', 'Pakistan', 'Tourist', '2025-10-18', '2025-10-25', '0000-00-00', 110.000, 130.000, 20.000, 'USD', 'Pending', 'adf', '2025-10-18 09:19:18', '2025-10-27 11:21:27', 1);

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
  ADD PRIMARY KEY (`id`);

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
-- Indexes for table `employee_terminations`
--
ALTER TABLE `employee_terminations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `employee_id` (`employee_id`),
  ADD KEY `terminated_by` (`terminated_by`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `idx_termination_date` (`termination_date`);

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
  ADD KEY `email` (`email`);

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
-- Indexes for table `message_reactions`
--
ALTER TABLE `message_reactions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_reaction` (`message_id`,`user_id`,`emoji`),
  ADD KEY `idx_message_id` (`message_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_tenant_id` (`tenant_id`);

--
-- Indexes for table `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `payment_sessions`
--
ALTER TABLE `payment_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `session_id` (`session_id`),
  ADD KEY `idx_session_id` (`session_id`),
  ADD KEY `idx_subscription_tenant` (`subscription_id`,`tenant_id`);

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
-- Indexes for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_tenant` (`user_id`,`tenant_id`),
  ADD KEY `idx_reviewer` (`reviewer_id`),
  ADD KEY `idx_period` (`period_start`,`period_end`),
  ADD KEY `fk_performance_reviews_tenant` (`tenant_id`);

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2081;

--
-- AUTO_INCREMENT for table `additional_payments`
--
ALTER TABLE `additional_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=52;

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=49;

--
-- AUTO_INCREMENT for table `blog_posts`
--
ALTER TABLE `blog_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `budget_allocations`
--
ALTER TABLE `budget_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `client_transactions`
--
ALTER TABLE `client_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=588;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `creditors`
--
ALTER TABLE `creditors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `creditor_transactions`
--
ALTER TABLE `creditor_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `customer_wallets`
--
ALTER TABLE `customer_wallets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- AUTO_INCREMENT for table `date_change_tickets`
--
ALTER TABLE `date_change_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=51;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=41;

--
-- AUTO_INCREMENT for table `debtor_transactions`
--
ALTER TABLE `debtor_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=46;

--
-- AUTO_INCREMENT for table `debt_records`
--
ALTER TABLE `debt_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `demo_requests`
--
ALTER TABLE `demo_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `destinations`
--
ALTER TABLE `destinations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `employee_terminations`
--
ALTER TABLE `employee_terminations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=218;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `families`
--
ALTER TABLE `families`
  MODIFY `family_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `hotel_refunds`
--
ALTER TABLE `hotel_refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=239;

--
-- AUTO_INCREMENT for table `jv_payments`
--
ALTER TABLE `jv_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `jv_transactions`
--
ALTER TABLE `jv_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=60;

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=119;

--
-- AUTO_INCREMENT for table `main_account`
--
ALTER TABLE `main_account`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `main_account_transactions`
--
ALTER TABLE `main_account_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1255;

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
-- AUTO_INCREMENT for table `message_reactions`
--
ALTER TABLE `message_reactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1050;

--
-- AUTO_INCREMENT for table `payment_sessions`
--
ALTER TABLE `payment_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- AUTO_INCREMENT for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `plans`
--
ALTER TABLE `plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `platform_settings`
--
ALTER TABLE `platform_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=200;

--
-- AUTO_INCREMENT for table `refunded_tickets`
--
ALTER TABLE `refunded_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=102;

--
-- AUTO_INCREMENT for table `salary_adjustments`
--
ALTER TABLE `salary_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `salary_advances`
--
ALTER TABLE `salary_advances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `salary_bonuses`
--
ALTER TABLE `salary_bonuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `salary_deductions`
--
ALTER TABLE `salary_deductions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `salary_management`
--
ALTER TABLE `salary_management`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `salary_payments`
--
ALTER TABLE `salary_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `sarafi_transactions`
--
ALTER TABLE `sarafi_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=48;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=37;

--
-- AUTO_INCREMENT for table `supplier_transactions`
--
ALTER TABLE `supplier_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=939;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=341;

--
-- AUTO_INCREMENT for table `ticket_reservations`
--
ALTER TABLE `ticket_reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `ticket_weights`
--
ALTER TABLE `ticket_weights`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `totp_recovery_codes`
--
ALTER TABLE `totp_recovery_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `totp_secrets`
--
ALTER TABLE `totp_secrets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `umrah_agreements`
--
ALTER TABLE `umrah_agreements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `umrah_bookings`
--
ALTER TABLE `umrah_bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=67;

--
-- AUTO_INCREMENT for table `umrah_booking_services`
--
ALTER TABLE `umrah_booking_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=35;

--
-- AUTO_INCREMENT for table `umrah_refunds`
--
ALTER TABLE `umrah_refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=26;

--
-- AUTO_INCREMENT for table `umrah_transactions`
--
ALTER TABLE `umrah_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=97;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=61;

--
-- AUTO_INCREMENT for table `visa_refunds`
--
ALTER TABLE `visa_refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

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
-- Constraints for table `employee_terminations`
--
ALTER TABLE `employee_terminations`
  ADD CONSTRAINT `fk_employee_terminations_employee` FOREIGN KEY (`employee_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_employee_terminations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_employee_terminations_terminator` FOREIGN KEY (`terminated_by`) REFERENCES `users` (`id`);

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
-- Constraints for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  ADD CONSTRAINT `fk_performance_reviews_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_performance_reviews_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_performance_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
