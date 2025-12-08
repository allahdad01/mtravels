-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Dec 07, 2025 at 08:03 AM
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
  `branch_id` int(11) DEFAULT NULL,
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

INSERT INTO `activity_log` (`id`, `tenant_id`, `branch_id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(2082, 2, NULL, 7, 'add', 'suppliers', 57, '[]', '{\"name\":\"KamAir\",\"contact_person\":\"NAVEED RASHIQ\",\"phone\":\"0777305730\",\"email\":\"admin@abc-construction.com\",\"address\":\"test\",\"currency\":\"USD\",\"balance\":0,\"supplier_type\":\"External\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 09:22:44'),
(2083, 2, NULL, 7, 'add', 'main_account', 17, '[]', '{\"name\":\"SELF BANK (SAFE)\",\"account_type\":\"internal\",\"bank_account_number\":null,\"bank_name\":null,\"usd_balance\":\"0\",\"afs_balance\":\"0\",\"status\":\"active\",\"tenant_id\":2}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 09:23:53'),
(2084, 2, NULL, 7, 'add', 'ticket_bookings', 398, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"SZQXJU\",\"origin\":\" KBL\",\"destination\":\"FRA\",\"airline\":\"AR\",\"departure_date\":\"2025-11-28\",\"total_base\":100,\"total_sold\":120,\"total_discount\":0,\"total_profit\":20,\"currency\":\"USD\",\"supplier_id\":57,\"supplier_name\":\"KamAir\",\"client_id\":20,\"client_name\":\"DR SAHIB\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 09:24:34'),
(2085, 2, NULL, 7, 'update', 'settings', 2, '{\"id\":2,\"agency_name\":\"Al Wali\",\"title\":\"Al Wali Travel\",\"phone\":\"0786011115\",\"email\":\"alwali@gmail.com\",\"address\":\"kabul\",\"logo\":\"file_68b9695fea986_Blue_and_White_Modern_Travel_Instagram_Post (1).png\",\"smtp_host\":\"\",\"smtp_port\":\"\",\"smtp_encryption\":\"\",\"smtp_username\":\"\",\"smtp_from_email\":\"\",\"smtp_from_name\":\"\"}', '{\"id\":2,\"agency_name\":\"Al Wali\",\"title\":\"Al Wali Travel\",\"phone\":\"0786011115\",\"email\":\"alwali@gmail.com\",\"address\":\"kabul\",\"logo\":\"file_68b9695fea986_Blue_and_White_Modern_Travel_Instagram_Post (1).png\",\"smtp_host\":\"smtp.gmail.com\",\"smtp_port\":\"587\",\"smtp_encryption\":\"tls\",\"smtp_username\":\"allahdadmuhammadi01@gmail.com\",\"smtp_from_email\":\"allahdadmuhammadi01@gmail.com\",\"smtp_from_name\":\"Al Wali Travel Agency\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 09:50:39'),
(2086, 2, NULL, 7, 'delete', 'ticket_bookings', 398, '{\"ticket_id\":398,\"client_id\":20,\"supplier_id\":57,\"paid_to_id\":17,\"ticket_currency\":\"USD\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 10:55:08'),
(2087, 2, NULL, 7, 'add', 'ticket_bookings', 399, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"HAUPSE\",\"origin\":\" KBL\",\"destination\":\"FRA\",\"airline\":\"A3\",\"departure_date\":\"2025-11-28\",\"total_base\":100,\"total_sold\":120,\"total_discount\":0,\"total_profit\":20,\"currency\":\"USD\",\"supplier_id\":57,\"supplier_name\":\"KamAir\",\"client_id\":20,\"client_name\":\"DR SAHIB\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 10:55:41'),
(2088, 2, NULL, 7, 'delete', 'ticket_bookings', 399, '{\"ticket_id\":399,\"client_id\":20,\"supplier_id\":57,\"paid_to_id\":17,\"ticket_currency\":\"USD\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 10:58:32'),
(2089, 2, NULL, 7, 'add', 'ticket_bookings', 400, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"HAUPSE\",\"origin\":\" KBL\",\"destination\":\"FRA\",\"airline\":\"EI\",\"departure_date\":\"2025-12-04\",\"total_base\":100,\"total_sold\":120,\"total_discount\":0,\"total_profit\":20,\"currency\":\"USD\",\"supplier_id\":57,\"supplier_name\":\"KamAir\",\"client_id\":20,\"client_name\":\"DR SAHIB\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-18 10:59:21'),
(2092, 2, NULL, 7, 'add', 'ticket_bookings', 403, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"WKEPD1\",\"origin\":\"KBL \",\"destination\":\"DXB\",\"airline\":\"EI\",\"departure_date\":\"2025-11-28\",\"total_base\":120,\"total_sold\":150,\"total_discount\":0,\"total_profit\":30,\"currency\":\"USD\",\"supplier_id\":57,\"supplier_name\":\"KamAir\",\"client_id\":20,\"client_name\":\"DR SAHIB\",\"trip_type\":\"round_trip\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 04:01:51'),
(2093, 2, NULL, 7, 'add', 'ticket_bookings', 404, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"SZQXJU\",\"origin\":\" KBL\",\"destination\":\"ELQ\",\"airline\":\"KM\",\"departure_date\":\"2025-12-05\",\"total_base\":120,\"total_sold\":130,\"total_discount\":0,\"total_profit\":10,\"currency\":\"USD\",\"supplier_id\":57,\"supplier_name\":\"KamAir\",\"client_id\":20,\"client_name\":\"DR SAHIB\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 04:15:31'),
(2094, 2, NULL, 7, 'add', 'hotel_bookings', 54, '[]', '{\"title\":\"Mr\",\"first_name\":\"ROHULLAH\",\"last_name\":\"SAFI\",\"gender\":\"Male\",\"check_in_date\":\"2025-11-19\",\"check_out_date\":\"2025-11-28\",\"accommodation_details\":\"test\",\"supplier_id\":57,\"sold_to\":\"20\",\"base_amount\":10,\"sold_amount\":20,\"profit\":10,\"currency\":\"USD\",\"paid_to\":\"17\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 05:22:13'),
(2095, 2, NULL, 7, 'add', 'hotel_bookings', 55, '[]', '{\"title\":\"Mr\",\"first_name\":\"ROHULLAH\",\"last_name\":\"SAFI\",\"gender\":\"Male\",\"check_in_date\":\"2025-11-19\",\"check_out_date\":\"2025-11-22\",\"accommodation_details\":\"test\",\"supplier_id\":57,\"sold_to\":\"20\",\"base_amount\":20,\"sold_amount\":30,\"profit\":10,\"currency\":\"USD\",\"paid_to\":\"17\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 05:26:52'),
(2096, 1, NULL, 1, 'add', 'hotel_bookings', 56, '[]', '{\"title\":\"Mr\",\"first_name\":\"ROHULLAH\",\"last_name\":\"SAFI\",\"gender\":\"Male\",\"check_in_date\":\"2025-11-19\",\"check_out_date\":\"2025-11-28\",\"accommodation_details\":\"ytest\",\"supplier_id\":37,\"sold_to\":\"18\",\"base_amount\":40,\"sold_amount\":50,\"profit\":10,\"currency\":\"USD\",\"paid_to\":\"13\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 06:04:03'),
(2097, 2, NULL, 7, 'add', 'hotel_bookings', 57, '[]', '{\"title\":\"Mr\",\"first_name\":\"ROHULLAH\",\"last_name\":\"SAFI\",\"gender\":\"Male\",\"check_in_date\":\"2025-11-19\",\"check_out_date\":\"2025-11-21\",\"accommodation_details\":\"test\",\"supplier_id\":57,\"sold_to\":\"21\",\"base_amount\":15,\"sold_amount\":30,\"profit\":15,\"currency\":\"USD\",\"paid_to\":\"17\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 06:22:05'),
(2098, 2, NULL, 7, 'add', 'umrah_bookings', 68, '[]', '{\"family_id\":\"17\",\"sold_to\":\"21\",\"paid_to\":\"17\",\"name\":\"KamAir\",\"passport_number\":\"P07592390\",\"flight_date\":\"\",\"return_date\":\"\",\"room_type\":\"1 Bed\",\"total_base_price\":10,\"total_sold_price\":20,\"total_profit\":10,\"services\":[{\"service_type\":\"all\",\"supplier_id\":\"57\",\"currency\":\"USD\",\"base_price\":\"10\",\"sold_price\":\"20\",\"profit\":10}],\"remarks\":\"Base amount of 10 USD deducted for umrah all.\",\"discount\":\"0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 06:28:16'),
(2099, 2, NULL, 7, 'add', 'umrah_bookings', 69, '[]', '{\"family_id\":\"17\",\"sold_to\":\"21\",\"paid_to\":\"17\",\"name\":\"KamAir\",\"passport_number\":\"P07592390\",\"flight_date\":\"\",\"return_date\":\"\",\"room_type\":\"1 Bed\",\"total_base_price\":10,\"total_sold_price\":20,\"total_profit\":10,\"services\":[{\"service_type\":\"all\",\"supplier_id\":\"57\",\"currency\":\"USD\",\"base_price\":\"10\",\"sold_price\":\"20\",\"profit\":10}],\"remarks\":\"Base amount of 10 USD deducted for umrah all.\",\"discount\":\"0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 06:28:58'),
(2100, 2, NULL, 7, 'add', 'main_account_transactions', 1255, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"tes\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 06:32:23'),
(2101, 2, NULL, 7, 'add', 'main_account_transactions', 1256, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 06:32:54'),
(2102, 2, NULL, 7, 'add', 'hotel_bookings', 58, '[]', '{\"title\":\"Mr\",\"first_name\":\"ROHULLAH\",\"last_name\":\"SAFI\",\"gender\":\"Male\",\"check_in_date\":\"2025-11-20\",\"check_out_date\":\"2025-11-27\",\"accommodation_details\":\"test\",\"supplier_id\":57,\"sold_to\":\"21\",\"base_amount\":40,\"sold_amount\":50,\"profit\":10,\"currency\":\"USD\",\"paid_to\":\"17\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 06:33:32'),
(2103, 2, NULL, 7, 'add', 'ticket_reservations', 29, '{}', '{\"passenger_name\":\"BAKHTIAR STANIKZAI\",\"pnr\":\"188JZ0\",\"origin\":\" KBL\",\"destination\":\"FRA\",\"airline\":\"AR\",\"departure_date\":\"2025-11-27\",\"base\":10,\"sold\":20,\"profit\":10,\"currency\":\"USD\",\"supplier\":57,\"supplier_name\":\"KamAir\",\"sold_to\":21,\"client_name\":\"walkings\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 06:51:41'),
(2104, 2, NULL, 7, 'add', 'main_account_transactions', 1257, '[]', '{\"booking_id\":29,\"payment_date\":\"2025-11-19 11:21:42\",\"description\":\"tets\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 06:51:53'),
(2105, 2, NULL, 7, 'add', 'date_change_tickets', 94, '{\"ticket_id\":\"404\",\"passenger_name\":\"wali\",\"pnr\":\"SZQXJU\",\"base\":120,\"sold\":130,\"supplier_penalty\":0,\"service_penalty\":0,\"currency\":\"USD\",\"status\":\"Date Changed\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 06:52:40'),
(2106, 2, NULL, 7, 'delete', 'date_change_tickets', 94, '{\"date_change_id\":94,\"client_id\":20,\"supplier_id\":\"57\",\"main_account_id\":17,\"currency\":\"USD\",\"client_type\":\"regular\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 06:52:54'),
(2107, 2, NULL, 7, 'create', 'ticket_weights', 69, NULL, '{\"ticket_id\":404,\"weight\":20,\"base_price\":20,\"sold_price\":30,\"profit\":10,\"remarks\":\"test\",\"supplier_name\":\"KamAir\",\"client_name\":\"DR SAHIB\",\"currency\":\"USD\"}', '::1', '0', '2025-11-19 06:57:37'),
(2108, 2, NULL, 7, 'update', 'ticket_weights', 69, '{\"weight_id\":69,\"weight\":\"20.00\",\"base_price\":\"20.00\",\"sold_price\":\"30.00\",\"profit\":\"10.00\",\"remarks\":\"test\"}', '{\"weight_id\":69,\"weight\":20,\"base_price\":20,\"sold_price\":30,\"profit\":10,\"remarks\":\"test\",\"supplier_name\":\"KamAir\",\"client_name\":\"DR SAHIB\",\"base_price_difference\":0,\"sold_price_difference\":0}', '::1', '0', '2025-11-19 06:57:47'),
(2109, 2, NULL, 7, 'add', 'refunded_tickets', 141, '{\"ticket_id\":404,\"passenger_name\":\"wali\",\"pnr\":\"SZQXJU\",\"base\":120,\"sold\":130,\"supplier_penalty\":0,\"service_penalty\":0,\"currency\":\"USD\",\"status\":\"pending\",\"description\":\"test\",\"calculation_method\":\"sold\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 06:58:11'),
(2110, 2, NULL, 7, 'add', 'visa_applications', 69, '[]', '{\"supplier\":57,\"sold_to\":\"20\",\"paid_to\":\"17\",\"applicant_name\":\"guli\",\"passport_number\":\"P8798765\",\"visa_type\":\"Medical\",\"base\":10,\"sold\":20,\"profit\":10,\"currency\":\"USD\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:03:56'),
(2111, 2, NULL, 7, 'update', 'visa_applications', 69, '{\"id\":69,\"supplier\":57,\"sold_to\":20,\"base\":\"10.000\",\"sold\":\"20.000\",\"currency\":\"USD\"}', '{\"id\":69,\"supplier\":57,\"sold_to\":20,\"title\":\"Mr\",\"gender\":\"Male\",\"applicant_name\":\"guli\",\"passport_number\":\"P8798765\",\"country\":\"Qatar\",\"visa_type\":\"Medical\",\"receive_date\":\"2025-11-19\",\"applied_date\":\"2025-11-21\",\"issued_date\":\"\",\"base\":10,\"sold\":20,\"profit\":10,\"currency\":\"USD\",\"status\":\"Pending\",\"remarks\":\"test\",\"phone\":\"0771781576\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:09:55'),
(2112, 2, NULL, 7, 'update', '0', 29, '{\"ticket_id\":29,\"supplier\":\"57\",\"sold_to\":21,\"price\":\"10.000\",\"sold\":\"20.000\",\"currency\":\"USD\"}', '{\"supplier\":57,\"sold_to\":21,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"BAKHTIAR STANIKZAI\",\"pnr\":\"188JZ0\",\"phone\":\"0775172181\",\"origin\":\" KBL\",\"destination\":\"FRA\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"AR\",\"issue_date\":\"2025-11-19\",\"departure_date\":\"2025-11-27\",\"return_date\":\"\",\"price\":10,\"sold\":20,\"profit\":10,\"currency\":\"USD\",\"description\":\"test\",\"paid_to\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:12:01'),
(2113, 2, NULL, 7, 'update', '0', 29, '{\"ticket_id\":29,\"supplier\":\"57\",\"sold_to\":21,\"price\":\"10.000\",\"sold\":\"20.000\",\"currency\":\"USD\"}', '{\"supplier\":57,\"sold_to\":21,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"BAKHTIAR STANIKZAI\",\"pnr\":\"188JZ0\",\"phone\":\"0775172181\",\"origin\":\" KBL\",\"destination\":\"FRA\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"AR\",\"issue_date\":\"2025-11-19\",\"departure_date\":\"2025-11-27\",\"return_date\":\"\",\"price\":10,\"sold\":20,\"profit\":10,\"currency\":\"USD\",\"description\":\"test\",\"paid_to\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:16:51'),
(2114, 2, NULL, 7, 'add', 'ticket_reservations', 30, '{}', '{\"passenger_name\":\"BAKHTIAR STANIKZAI\",\"pnr\":\"HAUPSE\",\"origin\":\"KBL \",\"destination\":\"HAS\",\"airline\":\"A3\",\"departure_date\":\"2025-12-05\",\"base\":10,\"sold\":20,\"profit\":10,\"currency\":\"USD\",\"supplier\":57,\"supplier_name\":\"KamAir\",\"sold_to\":20,\"client_name\":\"DR SAHIB\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:17:37'),
(2115, 2, NULL, 7, 'add', 'main_account_transactions', 1258, '[]', '{\"booking_id\":29,\"payment_date\":\"2025-11-19 11:47:37\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:17:58'),
(2116, 2, NULL, 7, 'delete', 'ticket_reservations', 29, '{\"ticket_id\":29,\"client_id\":21,\"supplier_id\":57,\"paid_to_id\":17,\"ticket_currency\":\"USD\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:18:16'),
(2117, 2, NULL, 7, 'update', '0', 404, '{\"ticket_id\":404,\"supplier\":\"57\",\"sold_to\":20,\"price\":\"120.000\",\"sold\":\"130.000\",\"currency\":\"USD\"}', '{\"supplier\":57,\"sold_to\":21,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"wali\",\"pnr\":\"SZQXJU\",\"phone\":\"0700907993\",\"origin\":\" KBL\",\"destination\":\"ELQ\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"KM\",\"issue_date\":\"2025-11-19\",\"departure_date\":\"2025-12-05\",\"return_date\":\"\",\"price\":120,\"sold\":130,\"profit\":10,\"currency\":\"USD\",\"description\":\"test\",\"paid_to\":17,\"market_exchange_rate\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:19:27'),
(2118, 2, NULL, 7, 'add', 'main_account_transactions', 1259, '[]', '{\"booking_id\":404,\"payment_date\":\"2025-11-19 11:49:28\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:19:41'),
(2119, 2, NULL, 7, 'add', 'main_account_transactions', 1260, '[]', '{\"booking_id\":404,\"payment_date\":\"2025-11-19 11:49:41\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:20:41'),
(2120, 2, NULL, 7, 'add', 'main_account_transactions', 1261, '[]', '{\"booking_id\":404,\"payment_date\":\"2025-11-19 11:52:38\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:22:59'),
(2121, 2, NULL, 7, 'add', 'main_account_transactions', 1262, '[]', '{\"booking_id\":404,\"payment_date\":\"2025-11-19 11:57:21\",\"description\":\"tets\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:27:42'),
(2122, 2, NULL, 7, 'add', 'main_account_transactions', 1263, '[]', '{\"booking_id\":404,\"payment_date\":\"2025-11-19 12:02:12\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:32:29'),
(2123, 2, NULL, 7, 'add', 'main_account_transactions', 1264, '[]', '{\"booking_id\":404,\"payment_date\":\"2025-11-19 12:03:28\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:34:06'),
(2124, 2, NULL, 7, 'add', 'main_account_transactions', 1265, '[]', '{\"booking_id\":404,\"payment_date\":\"2025-11-19 12:05:54\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:36:11'),
(2125, 2, NULL, 7, 'add', 'main_account_transactions', 1266, '[]', '{\"booking_id\":404,\"payment_date\":\"2025-11-19 12:05:54\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:36:13'),
(2126, 2, NULL, 7, 'add', 'main_account_transactions', 1267, '[]', '{\"booking_id\":404,\"payment_date\":\"2025-11-19 12:07:47\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:38:08'),
(2127, 2, NULL, 7, 'add', 'main_account_transactions', 1268, '[]', '{\"booking_id\":404,\"payment_date\":\"2025-11-19 12:07:47\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:38:09'),
(2128, 2, NULL, 7, 'add', 'main_account_transactions', 1269, '[]', '{\"booking_id\":404,\"payment_date\":\"2025-11-19 12:07:47\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:38:10'),
(2129, 2, NULL, 7, 'add', 'main_account_transactions', 1270, '[]', '{\"booking_id\":404,\"payment_date\":\"2025-11-19 12:07:47\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:38:10'),
(2130, 2, NULL, 7, 'add', 'main_account_transactions', 1271, '[]', '{\"booking_id\":404,\"payment_date\":\"2025-11-19 12:10:38\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:40:59'),
(2131, 2, NULL, 7, 'add', 'main_account_transactions', 1272, '[]', '{\"booking_id\":404,\"payment_date\":\"2025-11-19 12:10:38\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:41:01'),
(2132, 2, NULL, 7, 'add', 'main_account_transactions', 1273, '[]', '{\"booking_id\":404,\"payment_date\":\"2025-11-19 12:10:38\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:41:01'),
(2133, 2, NULL, 7, 'add', 'main_account_transactions', 1274, '[]', '{\"booking_id\":404,\"payment_date\":\"2025-11-19 12:10:38\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:41:01'),
(2134, 2, NULL, 7, 'add', 'main_account_transactions', 1275, '[]', '{\"booking_id\":404,\"payment_date\":\"2025-11-19 12:16:29\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:46:46'),
(2135, 2, NULL, 7, 'update', '0', 1271, '{\"transaction_id\":\"1271\",\"ticket_id\":\"404\",\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-11-19 12:10:38\",\"type\":\"credit\",\"currency\":\"USD\",\"exchange_rate\":null}', '{\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-11-19 12:10:38\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:59:12'),
(2136, 2, NULL, 7, 'add', 'main_account_transactions', 1276, '[]', '{\"booking_id\":404,\"payment_date\":\"2025-11-19 12:29:07\",\"description\":\"tets\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 07:59:54'),
(2137, 2, NULL, 7, 'add', 'main_account_transactions', 1277, '[]', '{\"booking_id\":404,\"payment_date\":\"2025-11-19 12:29:54\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:00:10'),
(2138, 2, NULL, 7, 'delete', 'main_account_transactions', 1277, '{\"transaction_id\":1277,\"ticket_id\":404,\"amount\":\"10.000\",\"currency\":\"USD\",\"main_account_id\":17,\"created_at\":\"2025-11-19 12:29:54\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:00:26'),
(2139, 2, NULL, 7, 'update', '0', 400, '{\"ticket_id\":400,\"supplier\":\"57\",\"sold_to\":20,\"price\":\"100.000\",\"sold\":\"120.000\",\"currency\":\"USD\"}', '{\"supplier\":57,\"sold_to\":21,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"FAZALHAQ PARDES\",\"pnr\":\"HAUPSE\",\"phone\":\"0766202122\",\"origin\":\" KBL\",\"destination\":\"FRA\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"EI\",\"issue_date\":\"2025-11-18\",\"departure_date\":\"2025-12-04\",\"return_date\":\"\",\"price\":100,\"sold\":120,\"profit\":20,\"currency\":\"USD\",\"description\":\"test\",\"paid_to\":17,\"market_exchange_rate\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:03:23'),
(2140, 2, NULL, 7, 'add', 'refunded_tickets', 142, '{\"ticket_id\":400,\"passenger_name\":\"FAZALHAQ PARDES\",\"pnr\":\"HAUPSE\",\"base\":100,\"sold\":120,\"supplier_penalty\":0,\"service_penalty\":0,\"currency\":\"USD\",\"status\":\"Refunded\",\"description\":\"test\",\"calculation_method\":\"base\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:03:33'),
(2141, 2, NULL, 7, 'add', 'main_account_transactions', 1278, '[]', '{\"booking_id\":142,\"payment_date\":\"2025-11-19 12:33:43\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"client_type\":\"agency\",\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:03:58'),
(2142, 2, NULL, 7, 'add', 'main_account_transactions', 1279, '[]', '{\"booking_id\":142,\"payment_date\":\"2025-11-19 12:33:43\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"client_type\":\"agency\",\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:03:58'),
(2143, 2, NULL, 7, 'add', 'main_account_transactions', 1280, '[]', '{\"booking_id\":142,\"payment_date\":\"2025-11-19 12:33:43\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"client_type\":\"agency\",\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:03:58'),
(2144, 2, NULL, 7, 'add', 'main_account_transactions', 1281, '[]', '{\"booking_id\":142,\"payment_date\":\"2025-11-19 12:33:43\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"client_type\":\"agency\",\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:03:58'),
(2145, 2, NULL, 7, 'add', 'main_account_transactions', 1282, '[]', '{\"booking_id\":142,\"payment_date\":\"2025-11-19 12:33:43\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"client_type\":\"agency\",\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:03:59'),
(2146, 2, NULL, 7, 'add', 'main_account_transactions', 1283, '[]', '{\"booking_id\":142,\"payment_date\":\"2025-11-19 12:33:43\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"client_type\":\"agency\",\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:03:59'),
(2147, 2, NULL, 7, 'add', 'main_account_transactions', 1284, '[]', '{\"booking_id\":142,\"payment_date\":\"2025-11-19 12:33:43\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"client_type\":\"agency\",\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:04:00'),
(2148, 2, NULL, 7, 'add', 'main_account_transactions', 1285, '[]', '{\"booking_id\":142,\"payment_date\":\"2025-11-19 12:33:43\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"client_type\":\"agency\",\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:04:00'),
(2149, 2, NULL, 7, 'add', 'main_account_transactions', 1286, '[]', '{\"booking_id\":142,\"payment_date\":\"2025-11-19 12:33:43\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"client_type\":\"agency\",\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:04:00'),
(2150, 2, NULL, 7, 'add', 'main_account_transactions', 1287, '[]', '{\"booking_id\":142,\"payment_date\":\"2025-11-19 12:33:43\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"client_type\":\"agency\",\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:04:01'),
(2151, 2, NULL, 7, 'add', 'main_account_transactions', 1288, '[]', '{\"booking_id\":142,\"payment_date\":\"2025-11-19 12:33:43\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"client_type\":\"agency\",\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:04:01'),
(2152, 2, NULL, 7, 'add', 'main_account_transactions', 1289, '[]', '{\"booking_id\":142,\"payment_date\":\"2025-11-19 12:36:16\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"client_type\":\"agency\",\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:06:36'),
(2153, 2, NULL, 7, 'update', '0', 1289, '{\"transaction_id\":\"1289\",\"ticket_id\":\"142\",\"amount\":10,\"description\":\"\",\"created_at\":\"2025-11-19 12:36:16\",\"currency\":\"USD\",\"type\":\"debit\",\"exchange_rate\":\"0.00000\"}', '{\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-11-19 12:36:16\",\"exchange_rate\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:10:51'),
(2154, 2, NULL, 7, 'delete', 'main_account_transactions', 1289, '{\"transaction_id\":1289,\"ticket_id\":142,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":17,\"created_at\":\"2025-11-19 12:36:16\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:11:04'),
(2155, 2, NULL, 7, 'add', 'date_change_tickets', 95, '{\"ticket_id\":\"400\",\"passenger_name\":\"FAZALHAQ PARDES\",\"pnr\":\"HAUPSE\",\"base\":100,\"sold\":120,\"supplier_penalty\":0,\"service_penalty\":0,\"currency\":\"USD\",\"status\":\"Date Changed\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:11:31'),
(2156, 2, NULL, 7, 'add', 'main_account_transactions', 1290, '[]', '{\"booking_id\":95,\"payment_date\":\"2025-11-19 12:41:44\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:12:12'),
(2157, 2, NULL, 7, 'add', 'main_account_transactions', 1291, '[]', '{\"booking_id\":95,\"payment_date\":\"2025-11-19 12:41:44\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:12:13'),
(2158, 2, NULL, 7, 'add', 'main_account_transactions', 1292, '[]', '{\"booking_id\":95,\"payment_date\":\"2025-11-19 12:41:44\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:12:13'),
(2159, 2, NULL, 7, 'update', 'main_account_transactions', 1290, '{\"transaction_id\":\"1290\",\"booking_id\":\"95\",\"amount\":10,\"description\":\"\",\"created_at\":\"2025-11-19 12:41:44\"}', '{\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-11-19 12:41:44\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:12:24'),
(2160, 2, NULL, 7, 'add', 'main_account_transactions', 1293, '[]', '{\"booking_id\":95,\"payment_date\":\"2025-11-19 12:45:01\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:15:19'),
(2161, 2, NULL, 7, 'add', 'main_account_transactions', 1294, '[]', '{\"booking_id\":95,\"payment_date\":\"2025-11-19 12:45:01\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:15:19'),
(2162, 2, NULL, 7, 'add', 'main_account_transactions', 1295, '[]', '{\"booking_id\":95,\"payment_date\":\"2025-11-19 12:45:01\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:15:20'),
(2163, 2, NULL, 7, 'add', 'main_account_transactions', 1296, '[]', '{\"booking_id\":95,\"payment_date\":\"2025-11-19 12:45:01\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:15:21'),
(2164, 2, NULL, 7, 'add', 'main_account_transactions', 1297, '[]', '{\"booking_id\":95,\"payment_date\":\"2025-11-19 12:45:01\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:15:21'),
(2165, 2, NULL, 7, 'add', 'main_account_transactions', 1298, '[]', '{\"booking_id\":95,\"payment_date\":\"2025-11-19 13:08:06\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:38:32'),
(2166, 2, NULL, 7, 'add', 'main_account_transactions', 1299, '[]', '{\"booking_id\":95,\"payment_date\":\"2025-11-19 13:08:32\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:38:52'),
(2167, 2, NULL, 7, 'update', 'main_account_transactions', 1290, '{\"transaction_id\":\"1290\",\"booking_id\":\"95\",\"amount\":10,\"description\":\"\",\"created_at\":\"2025-11-19 12:41:44\"}', '{\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-11-19 12:41:44\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:41:08'),
(2168, 2, NULL, 7, 'create', 'main_account_transactions', 1300, NULL, '{\"weight_id\":69,\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"transaction_date\":\"2025-11-19 13:11\",\"remarks\":\"test\",\"main_account_id\":17,\"balance\":200}', '::1', '0', '2025-11-19 08:41:45'),
(2169, 2, NULL, 7, 'update', '0', 1300, '{\"transaction_id\":\"1300\",\"weight_id\":\"69\",\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-11-19 13:11:00\",\"type\":\"credit\",\"currency\":\"USD\",\"exchange_rate\":null}', '{\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-11-19 04:30:00\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:42:13'),
(2170, 2, NULL, 7, 'add', 'main_account_transactions', 1301, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:43:24'),
(2171, 2, NULL, 7, 'add', 'main_account_transactions', 1302, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:43:24'),
(2172, 2, NULL, 7, 'add', 'main_account_transactions', 1303, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:43:25'),
(2173, 2, NULL, 7, 'add', 'main_account_transactions', 1304, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:43:25'),
(2174, 2, NULL, 7, 'add', 'main_account_transactions', 1305, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:47:48'),
(2175, 2, NULL, 7, 'add', 'main_account_transactions', 1306, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:47:48'),
(2176, 2, NULL, 7, 'add', 'main_account_transactions', 1307, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:47:48'),
(2177, 2, NULL, 7, 'add', 'main_account_transactions', 1308, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:47:49'),
(2178, 2, NULL, 7, 'add', 'main_account_transactions', 1309, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:47:49'),
(2179, 2, NULL, 7, 'add', 'main_account_transactions', 1310, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:47:49'),
(2180, 2, NULL, 7, 'add', 'main_account_transactions', 1311, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:49:43'),
(2181, 2, NULL, 7, 'add', 'main_account_transactions', 1312, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:50:00'),
(2182, 2, NULL, 7, 'add', 'main_account_transactions', 1313, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:50:00'),
(2183, 2, NULL, 7, 'add', 'main_account_transactions', 1314, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:50:00'),
(2184, 2, NULL, 7, 'add', 'main_account_transactions', 1315, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:50:00'),
(2185, 2, NULL, 7, 'add', 'main_account_transactions', 1316, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:50:00'),
(2186, 2, NULL, 7, 'add', 'main_account_transactions', 1317, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:50:01'),
(2187, 2, NULL, 7, 'add', 'main_account_transactions', 1318, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:50:02'),
(2188, 2, NULL, 7, 'add', 'main_account_transactions', 1319, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:50:02'),
(2189, 2, NULL, 7, 'add', 'main_account_transactions', 1320, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:50:02'),
(2190, 2, NULL, 7, 'add', 'main_account_transactions', 1321, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:50:02'),
(2191, 2, NULL, 7, 'add', 'main_account_transactions', 1322, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:50:02'),
(2192, 2, NULL, 7, 'add', 'main_account_transactions', 1323, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:51:25'),
(2193, 2, NULL, 7, 'add', 'main_account_transactions', 1324, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:51:25'),
(2194, 2, NULL, 7, 'add', 'main_account_transactions', 1325, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:51:25'),
(2195, 2, NULL, 7, 'add', 'main_account_transactions', 1326, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:51:25'),
(2196, 2, NULL, 7, 'add', 'main_account_transactions', 1327, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:51:25'),
(2197, 2, NULL, 7, 'add', 'main_account_transactions', 1328, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:51:26'),
(2198, 2, NULL, 7, 'add', 'main_account_transactions', 1329, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:51:27'),
(2199, 2, NULL, 7, 'add', 'main_account_transactions', 1330, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:51:27'),
(2200, 2, NULL, 7, 'add', 'main_account_transactions', 1331, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:51:27'),
(2201, 2, NULL, 7, 'add', 'main_account_transactions', 1332, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:51:27'),
(2202, 2, NULL, 7, 'add', 'main_account_transactions', 1333, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:53:24');
INSERT INTO `activity_log` (`id`, `tenant_id`, `branch_id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(2203, 2, NULL, 7, 'add', 'main_account_transactions', 1334, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:53:43'),
(2204, 2, NULL, 7, 'add', 'main_account_transactions', 1335, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:53:43'),
(2205, 2, NULL, 7, 'add', 'main_account_transactions', 1336, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:53:43'),
(2206, 2, NULL, 7, 'add', 'main_account_transactions', 1337, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:53:44'),
(2207, 2, NULL, 7, 'add', 'main_account_transactions', 1338, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:53:44'),
(2208, 2, NULL, 7, 'add', 'main_account_transactions', 1339, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:53:44'),
(2209, 2, NULL, 7, 'add', 'main_account_transactions', 1340, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:53:45'),
(2210, 2, NULL, 7, 'add', 'main_account_transactions', 1341, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:53:45'),
(2211, 2, NULL, 7, 'add', 'main_account_transactions', 1342, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:53:45'),
(2212, 2, NULL, 7, 'add', 'main_account_transactions', 1343, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:53:46'),
(2213, 2, NULL, 7, 'add', 'main_account_transactions', 1344, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:55:27'),
(2214, 2, NULL, 7, 'add', 'main_account_transactions', 1345, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:55:28'),
(2215, 2, NULL, 7, 'add', 'main_account_transactions', 1346, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:55:28'),
(2216, 2, NULL, 7, 'add', 'main_account_transactions', 1347, '[]', '{\"booking_id\":58,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:55:28'),
(2217, 2, NULL, 7, 'add', 'main_account_transactions', 1348, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:58:00'),
(2218, 2, NULL, 7, 'add', 'main_account_transactions', 1349, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:58:00'),
(2219, 2, NULL, 7, 'add', 'main_account_transactions', 1350, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:59:45'),
(2220, 2, NULL, 7, 'add', 'main_account_transactions', 1351, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:59:57'),
(2221, 2, NULL, 7, 'add', 'main_account_transactions', 1352, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:59:57'),
(2222, 2, NULL, 7, 'add', 'main_account_transactions', 1353, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:59:58'),
(2223, 2, NULL, 7, 'add', 'main_account_transactions', 1354, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 08:59:59'),
(2224, 2, NULL, 7, 'add', 'main_account_transactions', 1355, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:04:19'),
(2225, 2, NULL, 7, 'add', 'main_account_transactions', 1356, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:04:19'),
(2226, 2, NULL, 7, 'add', 'main_account_transactions', 1357, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:04:20'),
(2227, 2, NULL, 7, 'add', 'main_account_transactions', 1358, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:08:12'),
(2228, 2, NULL, 7, 'add', 'main_account_transactions', 1359, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:08:29'),
(2229, 2, NULL, 7, 'add', 'main_account_transactions', 1360, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:08:29'),
(2230, 2, NULL, 7, 'add', 'main_account_transactions', 1361, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:08:30'),
(2231, 2, NULL, 7, 'add', 'main_account_transactions', 1362, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:10:59'),
(2232, 2, NULL, 7, 'add', 'main_account_transactions', 1363, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:11:00'),
(2233, 2, NULL, 7, 'add', 'main_account_transactions', 1364, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:11:00'),
(2234, 2, NULL, 7, 'add', 'main_account_transactions', 1365, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:12:33'),
(2235, 2, NULL, 7, 'add', 'main_account_transactions', 1366, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:12:33'),
(2236, 2, NULL, 7, 'add', 'main_account_transactions', 1367, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:12:34'),
(2237, 2, NULL, 7, 'add', 'main_account_transactions', 1368, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:16:17'),
(2238, 2, NULL, 7, 'add', 'main_account_transactions', 1369, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:16:17'),
(2239, 2, NULL, 7, 'add', 'main_account_transactions', 1370, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:16:18'),
(2240, 2, NULL, 7, 'add', 'main_account_transactions', 1371, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:21:31'),
(2241, 2, NULL, 7, 'add', 'main_account_transactions', 1372, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:21:32'),
(2242, 2, NULL, 7, 'add', 'main_account_transactions', 1373, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:21:32'),
(2243, 2, NULL, 7, 'add', 'main_account_transactions', 1374, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:21:33'),
(2244, 2, NULL, 7, 'add', 'main_account_transactions', 1375, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:24:15'),
(2245, 2, NULL, 7, 'add', 'main_account_transactions', 1376, '[]', '{\"booking_id\":57,\"payment_date\":null,\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:24:36'),
(2246, 2, NULL, 7, 'update', '0', 1376, '{\"transaction_id\":\"1376\",\"booking_id\":\"57\",\"amount\":10,\"type\":\"credit\",\"created_at\":\"2025-11-19 13:54:36\",\"description\":\"\",\"currency\":\"USD\"}', '{\"amount\":10,\"type\":\"credit\",\"description\":\"test\",\"created_at\":\"2025-11-19 13:54:36\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:24:45'),
(2247, 2, NULL, 7, 'update', '0', 1376, '{\"transaction_id\":\"1376\",\"booking_id\":\"57\",\"amount\":10,\"type\":\"credit\",\"created_at\":\"2025-11-19 13:54:36\",\"description\":\"\",\"currency\":\"USD\"}', '{\"amount\":10,\"type\":\"credit\",\"description\":\"test\",\"created_at\":\"2025-11-19 13:54:36\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:24:47'),
(2248, 2, NULL, 7, 'update', 'hotel_bookings', 55, '{\"booking_id\":55,\"title\":\"\",\"first_name\":\"\",\"last_name\":\"\",\"base_amount\":\"20.000\",\"sold_amount\":\"30.000\",\"currency\":\"USD\",\"supplier_id\":57,\"sold_to\":\"20\"}', '{\"title\":\"Mr\",\"first_name\":\"ROHULLAH\",\"last_name\":\"SAFI\",\"gender\":\"Male\",\"contact_no\":\"786011115\",\"check_in_date\":\"2025-11-19\",\"check_out_date\":\"2025-11-22\",\"accommodation_details\":\"test\",\"base_amount\":20,\"sold_amount\":30,\"profit\":10,\"currency\":\"USD\",\"supplier_id\":\"57\",\"sold_to\":\"20\",\"paid_to\":\"17\",\"remarks\":\"test\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:26:22'),
(2249, 2, NULL, 7, 'add', 'main_account_transactions', 1377, '[]', '{\"refund_id\":16,\"payment_date\":\"2025-11-19 14:00:21\",\"description\":\"Refund payment for Hotel Booking #58 - Mr ROHULLAH SAFI\",\"amount\":50,\"currency\":\"USD\",\"client_type\":\"agency\",\"main_account_id\":17,\"first_name\":\"ROHULLAH\",\"last_name\":\"SAFI\",\"order_id\":\"34212\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:30:37'),
(2250, 2, NULL, 7, 'add', 'umrah_transactions', 97, '[]', '{\"umrah_booking_id\":68,\"transaction_to\":\"Internal Account\",\"payment_amount\":10,\"payment_currency\":\"USD\",\"payment_description\":\"test\",\"payment_date\":\"2025-11-19\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:31:12'),
(2251, 2, NULL, 7, 'add', 'main_account_transactions', 1379, '[]', '{\"refund_id\":26,\"payment_date\":\"2025-11-19 14:02:05\",\"description\":\"Refund payment for Umrah Booking #68 - KamAir\",\"amount\":20,\"currency\":\"USD\",\"client_type\":\"agency\",\"main_account_id\":17,\"name\":\"KamAir\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:32:13'),
(2252, 2, NULL, 7, 'update', 'visa_applications', 69, '{\"id\":69,\"supplier\":57,\"sold_to\":20,\"base\":\"10.000\",\"sold\":\"20.000\",\"currency\":\"USD\"}', '{\"id\":69,\"supplier\":57,\"sold_to\":21,\"title\":\"Mr\",\"gender\":\"Male\",\"applicant_name\":\"guli\",\"passport_number\":\"P8798765\",\"country\":\"Qatar\",\"visa_type\":\"Medical\",\"receive_date\":\"2025-11-19\",\"applied_date\":\"2025-11-21\",\"issued_date\":\"\",\"base\":10,\"sold\":20,\"profit\":10,\"currency\":\"USD\",\"status\":\"Pending\",\"remarks\":\"test\",\"phone\":\"0771781576\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:32:57'),
(2253, 2, NULL, 7, 'update', 'main_account_transactions', 1380, '{\"transaction_id\":\"1380\",\"visa_id\":\"69\",\"amount\":\"10.000\",\"currency\":\"USD\",\"type\":\"credit\",\"created_at\":\"2025-11-19 14:02:58\"}', '{\"transaction_id\":\"1380\",\"visa_id\":\"69\",\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-11-19 14:02:58\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:33:29'),
(2254, 2, NULL, 7, 'add', 'main_account_transactions', 1381, '[]', '{\"refund_id\":10,\"payment_date\":\"2025-11-19 14:07:42\",\"description\":\"Refund payment for Visa Application #69 - guli\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"client_type\":\"agency\",\"main_account_id\":17,\"applicant_name\":\"guli\",\"passport_number\":\"P8798765\",\"country\":\"Qatar\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:37:54'),
(2255, 2, NULL, 7, 'update', '0', 1381, '{\"transaction_id\":\"1381\",\"visa_id\":\"10\",\"amount\":10,\"description\":\"\",\"created_at\":\"2025-11-19 14:07:42\",\"currency\":\"USD\",\"type\":\"debit\"}', '{\"amount\":10,\"description\":\"Refund payment for Visa Application #69 - guli\",\"created_at\":\"2025-11-19 14:07:42\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:39:29'),
(2256, 2, NULL, 7, 'delete', 'main_account_transactions', 1381, '{\"transaction_id\":1381,\"refund_id\":10,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":17,\"created_at\":\"2025-11-19 14:07:42\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:39:35'),
(2257, 2, NULL, 7, 'add', 'additional_payments', 52, NULL, '{\"id\":52,\"payment_type\":\"Vacine\",\"description\":\"test\",\"base_amount\":10,\"profit\":10,\"sold_amount\":20,\"currency\":\"USD\",\"main_account_id\":17,\"supplier_id\":null,\"is_from_supplier\":1,\"client_id\":null,\"is_for_client\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:59:31'),
(2258, 2, NULL, 7, 'add', 'main_account_transactions', 1382, '[]', '{\"main_account_id\":17,\"amount\":10,\"currency\":\"USD\",\"description\":\"test\",\"payment_id\":52,\"balance\":920,\"exchange_rate\":0,\"payment_datetime\":\"2025-11-19 14:29:33\",\"tenant_id\":2}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 09:59:43'),
(2259, 2, NULL, 7, 'update', 'main_account_transactions', 1382, '{\"transaction_id\":1382,\"payment_id\":52,\"amount\":\"10.000\",\"description\":\"test\",\"created_at\":\"2025-11-19 14:29:33\",\"receipt\":\"\"}', '{\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-11-19 14:29:33\",\"receipt\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 10:00:02'),
(2260, 2, NULL, 7, 'delete', 'main_account_transactions', 1382, '{\"main_account_id\":17,\"transaction_id\":1382,\"payment_id\":52,\"amount\":\"10.000\",\"currency\":\"USD\",\"type\":\"credit\",\"created_at\":\"2025-11-19 14:29:33\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 10:00:13'),
(2261, 2, NULL, 7, 'add', 'expense_categories', 23, '[]', '{\"name\":\"Office\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 10:01:01'),
(2262, 2, NULL, 7, 'add', 'expenses', 218, '[]', '{\"category_id\":\"23\",\"date\":\"2025-11-19\",\"description\":\"test\",\"amount\":\"10\",\"currency\":\"USD\",\"main_account_id\":\"17\",\"allocation_id\":null,\"receipt_number\":\"\",\"receipt_file\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 10:01:20'),
(2263, 2, NULL, 7, 'update', 'expense_categories', 23, '{\"category_id\":\"23\"}', '{\"name\":\"Office\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 10:01:37'),
(2264, 2, NULL, 7, 'update', 'expenses', 218, '{\"expense_id\":\"218\",\"previous_values\":{\"amount\":\"10.000\",\"currency\":\"USD\",\"main_account_id\":17,\"allocation_id\":null,\"receipt_file\":null}}', '{\"category_id\":\"23\",\"date\":\"2025-11-19\",\"description\":\"test\",\"amount\":\"10.000\",\"currency\":\"USD\",\"main_account_id\":\"17\",\"allocation_id\":null,\"receipt_number\":\"\",\"receipt_file\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 10:01:45'),
(2265, 2, NULL, 7, 'add', 'jv_payments', 6, '{}', '{\"jv_payment_id\":\"6\",\"jv_name\":\"Client-Supplier Payment\",\"client_id\":20,\"client_name\":\"DR SAHIB\",\"supplier_id\":57,\"supplier_name\":\"KamAir\",\"amount\":100,\"supplier_amount\":100,\"currency\":\"USD\",\"supplier_currency\":\"USD\",\"exchange_rate\":1,\"receipt\":\"1213231\",\"remarks\":\"test\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 10:02:41'),
(2266, 2, NULL, 7, 'update', 'jv_payments', 6, '{\"id\":6,\"tenant_id\":2,\"jv_name\":\"Client-Supplier Payment\",\"exchange_rate\":\"1.00000\",\"total_amount\":\"100.000\",\"currency\":\"USD\",\"receipt\":\"1213231\",\"remarks\":\"test\",\"client_id\":20,\"supplier_id\":57,\"created_by\":7,\"created_at\":\"2025-11-19 14:32:41\",\"updated_at\":\"2025-11-19 14:32:41\"}', '{\"jv_name\":\"Client-Supplier Payment\",\"currency\":\"USD\",\"total_amount\":\"100.000\",\"exchange_rate\":\"1.00000\",\"receipt\":\"1213231\",\"remarks\":\"test\",\"client_id\":20,\"supplier_id\":57,\"client_name\":\"DR SAHIB\",\"supplier_name\":\"KamAir\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 10:02:56'),
(2267, 2, NULL, 7, 'fund', 'main_account', 17, '{\"account_id\":\"17\",\"usd_balance\":\"5320.000\"}', '{\"account_id\":\"17\",\"usd_balance\":5330,\"amount\":10,\"currency\":\"USD\",\"description\":\"Account funded by Matiullah Rahimi. Remarks: 0. Receipt: 0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 10:51:31'),
(2268, 2, NULL, 7, 'fund', 'main_account', 17, '{\"account_id\":\"17\",\"usd_balance\":\"5330.000\"}', '{\"account_id\":\"17\",\"usd_balance\":5340,\"amount\":10,\"currency\":\"USD\",\"description\":\"Account funded by Matiullah Rahimi. Remarks: 0. Receipt: 0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 10:53:26'),
(2269, 2, NULL, 7, 'update', 'main_account', 17, '{\"name\":\"SELF BANK (SAFE)\",\"account_type\":\"internal\",\"bank_account_number\":null,\"status\":\"active\"}', '{\"name\":\"SELF BANK (SAFE)\",\"account_type\":\"internal\",\"bank_account_number\":null,\"status\":\"active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-19 10:53:43'),
(2270, 2, NULL, 7, 'add', 'ticket_bookings', 405, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"WKEPD1\",\"origin\":\"VKO\",\"destination\":\"JED\",\"airline\":\"AR\",\"departure_date\":\"2025-11-29\",\"total_base\":100,\"total_sold\":150,\"total_discount\":0,\"total_profit\":50,\"currency\":\"USD\",\"supplier_id\":57,\"supplier_name\":\"KamAir\",\"client_id\":20,\"client_name\":\"DR SAHIB\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-20 04:10:46'),
(2271, 2, NULL, 7, 'add', 'ticket_bookings', 407, '{}', '{\"multiple_passengers\":true,\"passenger_count\":2,\"pnr\":\"SZQXJU\",\"origin\":\"KBL \",\"destination\":\"ELQ\",\"airline\":\"EI\",\"departure_date\":\"2025-11-28\",\"total_base\":200,\"total_sold\":220,\"total_discount\":0,\"total_profit\":20,\"currency\":\"USD\",\"supplier_id\":57,\"supplier_name\":\"KamAir\",\"client_id\":20,\"client_name\":\"DR SAHIB\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-20 05:25:58'),
(2272, 2, NULL, 7, 'update_umrah_member', 'umrah_bookings', 69, '{\"sold_to\":21,\"family_id\":17,\"paid_to\":17,\"entry_date\":\"2025-11-19\",\"name\":\"KamAir\",\"dob\":\"2025-11-19\",\"passport_number\":\"P07592390\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":\"0000-00-00\",\"return_date\":\"0000-00-00\",\"duration\":\"5 Days\",\"room_type\":\"1 Bed\",\"price\":\"10.000\",\"sold_price\":\"20.000\",\"profit\":\"10.000\",\"received_bank_payment\":\"0.000\",\"bank_receipt_number\":\"\",\"paid\":\"0.000\",\"due\":\"20.000\",\"discount\":\"0.000\"}', '{\"booking_id\":69,\"family_id\":17,\"suppliers\":{\"1\":{\"service_type\":\"all\",\"supplier_id\":\"57\",\"currency\":\"USD\",\"base_price\":\"10.000\",\"sold_price\":\"20.000\",\"profit\":\"10.00\"}},\"sold_to\":21,\"paid_to\":17,\"entry_date\":\"2025-11-19\",\"name\":\"KamAir\",\"dob\":\"2025-11-19\",\"passport_number\":\"P07592390\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":null,\"return_date\":null,\"duration\":\"5 Days\",\"room_type\":\"1 Bed\",\"total_base_price\":10,\"total_sold_price\":20,\"total_profit\":10,\"received_bank_payment\":null,\"bank_receipt_number\":null,\"paid\":null,\"due\":20,\"gender\":\"Male\",\"passport_expiry\":\"2026-06-05\",\"remarks\":\"test\",\"relation\":\"Friend\",\"g_name\":\"ESMAT ULLAH\",\"father_name\":\"MOHAMMAD SIDDIQ AHMADZAI\",\"discount\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-20 10:17:41'),
(2273, 2, NULL, 7, 'add', 'umrah_bookings', 70, '[]', '{\"family_id\":\"17\",\"sold_to\":\"20\",\"paid_to\":\"17\",\"name\":\"Matiullah\",\"passport_number\":\"P07592390\",\"flight_date\":\"\",\"return_date\":\"\",\"room_type\":\"3 Beds\",\"total_base_price\":100,\"total_sold_price\":150,\"total_profit\":50,\"services\":[{\"service_type\":\"all\",\"supplier_id\":\"57\",\"currency\":\"USD\",\"base_price\":\"100\",\"sold_price\":\"150\",\"profit\":50}],\"remarks\":\"Base amount of 100 USD deducted for umrah all.\",\"discount\":\"0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-20 10:18:46'),
(2274, 2, NULL, 7, 'update_employee', 'users', 7, '{\"name\":\"Matiullah Rahimi\",\"email\":\"mati@gmail.com\",\"phone\":\"0777555594\",\"role\":\"admin\",\"hire_date\":\"2025-04-09\",\"address\":\"Jada-e-Maiwand\",\"profile_pic\":\"68bd507105c6e_Blue and White Grunge Travel and Tourism Instagram Post.png\"}', '{\"name\":\"Matiullah Rahimi\",\"email\":\"fluentcenter01@gmail.com\",\"phone\":\"0777555594\",\"role\":\"admin\",\"hire_date\":\"2025-04-09\",\"address\":\"Jada-e-Maiwand\",\"profile_pic\":\"68bd507105c6e_Blue and White Grunge Travel and Tourism Instagram Post.png\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-20 11:28:43'),
(2275, 2, NULL, 7, 'delete', 'salary_payments', 23, '{\"payment_id\":23,\"amount\":10000,\"currency\":\"AFS\",\"main_account_id\":17,\"payment_date\":\"2025-11-20 16:00:07\",\"payment_type\":\"regular\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-20 11:32:21'),
(2276, 2, NULL, 7, 'delete', 'salary_payments', 24, '{\"payment_id\":24,\"amount\":10000,\"currency\":\"AFS\",\"main_account_id\":17,\"payment_date\":\"2025-11-20 16:02:34\",\"payment_type\":\"regular\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-20 11:34:55'),
(2277, 2, NULL, 7, 'delete', 'salary_payments', 25, '{\"payment_id\":25,\"amount\":10000,\"currency\":\"AFS\",\"main_account_id\":17,\"payment_date\":\"2025-11-20 16:05:06\",\"payment_type\":\"regular\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-20 11:39:54'),
(2278, 2, NULL, 7, 'fund', 'main_account', 17, '{\"account_id\":\"17\",\"afs_balance\":\"0.000\"}', '{\"account_id\":\"17\",\"afs_balance\":100000,\"amount\":100000,\"currency\":\"AFS\",\"description\":\"Account funded by Matiullah Rahimi. Remarks: test. Receipt: t43t4tw\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-20 11:40:53'),
(2279, 2, NULL, 7, 'add', 'umrah_transactions', 98, '[]', '{\"umrah_booking_id\":70,\"transaction_to\":\"Internal Account\",\"payment_amount\":10,\"payment_currency\":\"USD\",\"payment_description\":\"\",\"payment_date\":\"2025-11-24\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-24 05:11:58'),
(2280, 2, NULL, 7, 'add', 'umrah_transactions', 99, '[]', '{\"umrah_booking_id\":69,\"transaction_to\":\"Internal Account\",\"payment_amount\":10,\"payment_currency\":\"USD\",\"payment_description\":\"\",\"payment_date\":\"2025-11-24\",\"receipt_number\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-24 05:12:22'),
(2281, 2, NULL, 7, 'delete', 'umrah_bookings', 70, '{\"booking_id\":70,\"client_id\":20,\"services\":[{\"service_id\":39,\"supplier_id\":57,\"service_type\":\"all\",\"base_price\":\"100.000\",\"sold_price\":\"150.000\",\"profit\":\"50.000\",\"currency\":\"USD\",\"supplier_type\":\"External\"}],\"paid_to\":17,\"currency\":\"USD\",\"client_type\":\"regular\",\"total_base_price\":\"100.000\",\"total_sold_price\":\"150.000\",\"total_profit\":\"50.000\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-24 05:14:09'),
(2282, 2, NULL, 7, 'add', 'umrah_bookings', 71, '[]', '{\"family_id\":\"17\",\"sold_to\":\"20\",\"paid_to\":\"17\",\"name\":\"Idrees\",\"passport_number\":\"P09696377\",\"flight_date\":\"\",\"return_date\":\"\",\"room_type\":\"1 Bed\",\"total_base_price\":100,\"total_sold_price\":150,\"total_profit\":50,\"services\":[{\"service_type\":\"all\",\"supplier_id\":\"57\",\"currency\":\"USD\",\"base_price\":\"100\",\"sold_price\":\"150\",\"profit\":50}],\"remarks\":\"Base amount of 100 USD deducted for umrah all.\",\"discount\":\"0\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-24 05:18:30'),
(2283, 2, NULL, 7, 'update_umrah_member', 'umrah_bookings', 71, '{\"sold_to\":20,\"family_id\":17,\"paid_to\":17,\"entry_date\":\"2025-11-24\",\"name\":\"Idrees\",\"dob\":\"2025-11-24\",\"passport_number\":\"P09696377\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":\"0000-00-00\",\"return_date\":\"0000-00-00\",\"duration\":\"18 Days\",\"room_type\":\"1 Bed\",\"price\":\"100.000\",\"sold_price\":\"150.000\",\"profit\":\"50.000\",\"received_bank_payment\":\"0.000\",\"bank_receipt_number\":\"\",\"paid\":\"0.000\",\"due\":\"150.000\",\"discount\":\"0.000\"}', '{\"booking_id\":71,\"family_id\":17,\"suppliers\":{\"1\":{\"service_type\":\"all\",\"supplier_id\":\"57\",\"currency\":\"USD\",\"base_price\":\"100.000\",\"sold_price\":\"150.000\",\"profit\":\"50.00\"}},\"sold_to\":21,\"paid_to\":17,\"entry_date\":\"2025-11-24\",\"name\":\"Idrees\",\"dob\":\"2025-11-24\",\"passport_number\":\"P09696377\",\"id_type\":\"ID Original + Passport Original\",\"flight_date\":null,\"return_date\":null,\"duration\":\"18 Days\",\"room_type\":\"1 Bed\",\"total_base_price\":100,\"total_sold_price\":150,\"total_profit\":50,\"received_bank_payment\":null,\"bank_receipt_number\":null,\"paid\":null,\"due\":150,\"gender\":\"Male\",\"passport_expiry\":\"2026-06-04\",\"remarks\":\"test\",\"relation\":\"Uncle\",\"g_name\":\"ESMAT ULLAH\",\"father_name\":\"RAHIMI\",\"discount\":0}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-24 05:19:53'),
(2284, 2, NULL, 7, 'visa_cancellation', 'visa_applications', 69, '{\"status\":\"refunded\"}', '{\"status\":\"Cancelled\",\"cancellation_reason\":\"test\",\"cancelled_by\":7,\"cancelled_at\":\"2025-11-24 05:50:56\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-24 05:50:56'),
(2285, 2, NULL, 7, 'visa_reapply', 'visa_applications', 69, '{\"status\":\"Cancelled\",\"profit\":\"0.000\",\"remarks\":\"test\\n[CANCELLED] Cancelled: test | 2025-11-24 10:20:56\"}', '{\"status\":\"Pending\",\"profit\":0,\"remarks\":\"Re-applied: test\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-24 06:01:49'),
(2286, 2, NULL, 7, 'visa_cancellation', 'visa_applications', 69, '{\"status\":\"Pending\"}', '{\"status\":\"Cancelled\",\"cancellation_reason\":\"test\",\"cancelled_by\":7,\"cancelled_at\":\"2025-11-24 06:07:56\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-24 06:07:56'),
(2287, 2, NULL, 7, 'visa_reapply', 'visa_applications', 69, '{\"status\":\"Cancelled\",\"profit\":\"0.000\",\"remarks\":\"test\\n[CANCELLED] Cancelled: test | 2025-11-24 10:20:56\\n[RE-APPLIED] Re-applied: test | 2025-11-24 10:31:49\\n[CANCELLED] Cancelled: test | 2025-11-24 10:37:56\"}', '{\"status\":\"Pending\",\"profit\":-10,\"remarks\":\"Re-applied: test\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-24 06:09:34'),
(2288, 2, NULL, 7, 'visa_cancellation', 'visa_applications', 69, '{\"status\":\"Pending\"}', '{\"status\":\"Cancelled\",\"cancellation_reason\":\"test\",\"cancelled_by\":7,\"cancelled_at\":\"2025-11-24 06:10:29\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-24 06:10:29'),
(2289, 2, NULL, 7, 'visa_reapply', 'visa_applications', 69, '{\"status\":\"Cancelled\",\"profit\":\"0.000\",\"remarks\":\"test\\n[CANCELLED] Cancelled: test | 2025-11-24 10:20:56\\n[RE-APPLIED] Re-applied: test | 2025-11-24 10:31:49\\n[CANCELLED] Cancelled: test | 2025-11-24 10:37:56\\n[RE-APPLIED] Re-applied: test | 2025-11-24 10:39:34\\n[CANCELLED] Cancelled: test | 2025-11-24 10:40:29\"}', '{\"status\":\"Pending\",\"profit\":-10,\"remarks\":\"Re-applied: test\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-24 06:12:26'),
(2290, 2, NULL, 7, 'visa_cancellation', 'visa_applications', 69, '{\"status\":\"Pending\"}', '{\"status\":\"Cancelled\",\"cancellation_reason\":\"test\",\"cancelled_by\":7,\"cancelled_at\":\"2025-11-24 06:13:07\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-24 06:13:07'),
(2291, 2, NULL, 7, 'visa_reapply', 'visa_applications', 69, '{\"status\":\"Cancelled\",\"profit\":\"0.000\",\"remarks\":\"test\\n[CANCELLED] Cancelled: test | 2025-11-24 10:20:56\\n[RE-APPLIED] Re-applied: test | 2025-11-24 10:31:49\\n[CANCELLED] Cancelled: test | 2025-11-24 10:37:56\\n[RE-APPLIED] Re-applied: test | 2025-11-24 10:39:34\\n[CANCELLED] Cancelled: test | 2025-11-24 10:40:29\\n[RE-APPLIED] Re-applied: test | 2025-11-24 10:42:26\\n[CANCELLED] Cancelled: test | 2025-11-24 10:43:07\"}', '{\"status\":\"Pending\",\"profit\":-10,\"remarks\":\"Re-applied: test\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-24 06:14:07'),
(2292, 2, NULL, 7, 'visa_cancellation', 'visa_applications', 69, '{\"status\":\"Pending\"}', '{\"status\":\"Cancelled\",\"cancellation_reason\":\"test1\",\"cancelled_by\":7,\"cancelled_at\":\"2025-11-24 06:17:35\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-24 06:17:35'),
(2293, 2, NULL, 7, 'update', '0', 1201, '{\"notification_id\":1201,\"previous_status\":\"Read\"}', '{\"status\":\"read\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-24 07:56:38'),
(2294, 2, NULL, 7, 'update', '0', 1200, '{\"notification_id\":1200,\"previous_status\":\"Read\"}', '{\"status\":\"read\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-24 07:57:37'),
(2295, 2, NULL, 7, 'update', '0', 1199, '{\"notification_id\":1199,\"previous_status\":\"Read\"}', '{\"status\":\"read\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-24 07:57:41'),
(2296, 2, NULL, 7, 'create', 'users', 19, NULL, '{\"name\":\"ROHULLAH SAFI\",\"email\":\"fluentcenter001@gmail.com\",\"role\":\"admin\",\"branch_id\":\"2\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-24 10:14:55'),
(2297, 2, NULL, 7, 'create', 'branches', 4, NULL, '{\"name\":\"Heart\",\"code\":\"H1\",\"address\":\"Jada-e-Maiwand\",\"phone\":\"0786011115\",\"email\":\"halmuqadas_travel@yahoo.com\",\"manager_id\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-24 10:18:05'),
(2298, 2, NULL, 7, 'switch_branch', 'branches', 2, NULL, '{\"branch_name\":\"Al Wali - Main Branch\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-24 10:32:07'),
(2299, 2, NULL, 7, 'switch_branch', 'branches', 4, NULL, '{\"branch_name\":\"Heart\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-25 05:05:31'),
(2300, 2, NULL, 7, 'switch_branch', 'branches', 2, NULL, '{\"branch_name\":\"Al Wali - Main Branch\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-25 06:01:30'),
(2301, 2, NULL, 7, 'update', 'settings', 2, '{\"id\":2,\"agency_name\":\"Al Wali\",\"title\":\"Al Wali Travel\",\"phone\":\"0786011115\",\"email\":\"alwali@gmail.com\",\"address\":\"kabul\",\"logo\":\"file_68b9695fea986_Blue_and_White_Modern_Travel_Instagram_Post (1).png\",\"smtp_host\":\"smtp.gmail.com\",\"smtp_port\":587,\"smtp_encryption\":\"tls\",\"smtp_username\":\"allahdadmuhammadi01@gmail.com\",\"smtp_from_email\":\"allahdadmuhammadi01@gmail.com\",\"smtp_from_name\":\"Al Wali Travel Agency\"}', '{\"id\":2,\"agency_name\":\"Al Walis\",\"title\":\"Al Wali Travel\",\"phone\":\"0786011115\",\"email\":\"alwali@gmail.com\",\"address\":\"kabul\",\"logo\":\"file_68b9695fea986_Blue_and_White_Modern_Travel_Instagram_Post (1).png\",\"smtp_host\":\"smtp.gmail.com\",\"smtp_port\":\"587\",\"smtp_encryption\":\"tls\",\"smtp_username\":\"allahdadmuhammadi01@gmail.com\",\"smtp_from_email\":\"allahdadmuhammadi01@gmail.com\",\"smtp_from_name\":\"Al Wali Travel Agency\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 06:51:04'),
(2302, 2, NULL, 7, 'update', 'settings', 2, '{\"id\":2,\"agency_name\":\"Al Walis\",\"title\":\"Al Wali Travel\",\"phone\":\"0786011115\",\"email\":\"alwali@gmail.com\",\"address\":\"kabul\",\"logo\":\"file_68b9695fea986_Blue_and_White_Modern_Travel_Instagram_Post (1).png\",\"smtp_host\":\"smtp.gmail.com\",\"smtp_port\":587,\"smtp_encryption\":\"tls\",\"smtp_username\":\"allahdadmuhammadi01@gmail.com\",\"smtp_from_email\":\"allahdadmuhammadi01@gmail.com\",\"smtp_from_name\":\"Al Wali Travel Agency\"}', '{\"id\":2,\"agency_name\":\"Al Wali\",\"title\":\"Al Wali Travel\",\"phone\":\"0786011115\",\"email\":\"alwali@gmail.com\",\"address\":\"kabul\",\"logo\":\"file_68b9695fea986_Blue_and_White_Modern_Travel_Instagram_Post (1).png\",\"smtp_host\":\"smtp.gmail.com\",\"smtp_port\":\"587\",\"smtp_encryption\":\"tls\",\"smtp_username\":\"allahdadmuhammadi01@gmail.com\",\"smtp_from_email\":\"allahdadmuhammadi01@gmail.com\",\"smtp_from_name\":\"Al Wali Travel Agency\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-11-26 06:52:10'),
(2303, 2, 2, 19, 'add', 'additional_payments', 53, NULL, '{\"id\":53,\"payment_type\":\"Vacine\",\"description\":\"test\",\"base_amount\":10,\"profit\":10,\"sold_amount\":20,\"currency\":\"USD\",\"main_account_id\":17,\"supplier_id\":null,\"is_from_supplier\":1,\"client_id\":null,\"is_for_client\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 09:02:17'),
(2304, 2, 2, 19, 'add', 'main_account_transactions', 1416, '[]', '{\"main_account_id\":17,\"amount\":10,\"currency\":\"USD\",\"description\":\"test\",\"payment_id\":53,\"balance\":5370,\"exchange_rate\":0,\"payment_datetime\":\"2025-12-02 13:40:10\",\"tenant_id\":2}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 09:10:15'),
(2305, 2, 2, 19, 'add', 'main_account_transactions', 1417, '[]', '{\"main_account_id\":17,\"amount\":10,\"currency\":\"USD\",\"description\":\"test\",\"payment_id\":53,\"balance\":5380,\"exchange_rate\":0,\"payment_datetime\":\"2025-12-02 13:42:21\",\"tenant_id\":2}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 09:12:27'),
(2306, 2, 2, 19, 'update', 'main_account_transactions', 1417, '{\"transaction_id\":1417,\"payment_id\":53,\"amount\":\"10.000\",\"description\":\"test\",\"created_at\":\"2025-12-02 13:42:21\",\"receipt\":\"\"}', '{\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-12-02 13:42:21\",\"receipt\":\"\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 09:12:33'),
(2307, 2, 2, 19, 'delete', 'main_account_transactions', 1416, '{\"main_account_id\":17,\"transaction_id\":1416,\"payment_id\":53,\"amount\":\"10.000\",\"currency\":\"USD\",\"type\":\"credit\",\"created_at\":\"2025-12-02 13:40:10\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-02 09:53:47'),
(2308, 2, 2, 19, 'update', '0', 1203, '{\"notification_id\":1203,\"previous_status\":\"Read\"}', '{\"status\":\"read\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 05:14:37'),
(2309, 2, 2, 19, 'fund', 'main_account', 17, '{\"account_id\":\"17\",\"usd_balance\":\"5370.000\"}', '{\"account_id\":\"17\",\"usd_balance\":5380,\"amount\":10,\"currency\":\"USD\",\"description\":\"Account funded by ROHULLAH SAFI. Remarks: test. Receipt: sdg\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 06:38:35'),
(2310, 2, 2, 19, 'update', 'main_account', 17, '{\"name\":\"SELF BANK (SAFE)\",\"account_type\":\"internal\",\"bank_account_number\":null,\"status\":\"active\"}', '{\"name\":\"SELF BANK (SAFE)\",\"account_type\":\"internal\",\"bank_account_number\":null,\"status\":\"active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-03 06:40:12'),
(2311, 2, 2, 19, 'add', 'expense_categories', 24, '[]', '{\"name\":\"OFFICE EXPS\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 08:36:14'),
(2312, 2, 2, 19, 'add', 'expenses', 219, '[]', '{\"category_id\":\"24\",\"date\":\"2025-12-04\",\"description\":\"this is for test\",\"amount\":\"100\",\"currency\":\"USD\",\"main_account_id\":\"17\",\"allocation_id\":null,\"receipt_number\":\"\",\"receipt_file\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-04 08:36:47'),
(2313, 2, 2, 19, 'add', 'main_account', 18, '[]', '{\"name\":\"AZIZI BANK\",\"account_type\":\"bank\",\"bank_account_number\":\"23451534\",\"bank_name\":\"AZIZI\",\"usd_balance\":\"0\",\"afs_balance\":\"0\",\"status\":\"active\",\"tenant_id\":2,\"branch_id\":2}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 06:29:47'),
(2314, 2, 2, 19, 'update', 'main_account', 18, '{\"name\":\"AZIZI BANK\",\"account_type\":\"bank\",\"bank_account_number\":\"23451534\",\"status\":\"active\"}', '{\"name\":\"AZIZI BANK\",\"account_type\":\"bank\",\"bank_account_number\":\"23451534\",\"status\":\"active\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 06:31:08'),
(2315, 2, 2, 19, 'fund', 'main_account', 18, '{\"account_id\":\"18\",\"usd_balance\":\"0.000\"}', '{\"account_id\":\"18\",\"usd_balance\":100,\"amount\":100,\"currency\":\"USD\",\"description\":\"Account funded by ROHULLAH SAFI. Remarks: test. Receipt: afdsa\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 06:31:20'),
(2316, 2, 2, 19, 'fund', 'suppliers', 57, '{\"supplier_id\":57,\"supplier_balance\":\"-385.000\",\"main_account_id\":17,\"main_account_balance\":5280}', '{\"supplier_id\":57,\"supplier_balance\":-285,\"main_account_id\":17,\"main_account_balance\":5180,\"amount\":100,\"currency\":\"USD\",\"remarks\":\"test\",\"receipt_number\":\"2452345\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 06:31:44'),
(2317, 2, 2, 19, 'bonus', 'suppliers', 57, '{\"supplier_id\":\"57\",\"supplier_balance\":\"-285.000\"}', '{\"supplier_id\":\"57\",\"supplier_balance\":-185,\"amount\":\"100\",\"currency\":\"USD\",\"remarks\":\"test\",\"receipt_number\":\"6524652\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 06:34:07');
INSERT INTO `activity_log` (`id`, `tenant_id`, `branch_id`, `user_id`, `action`, `table_name`, `record_id`, `old_values`, `new_values`, `ip_address`, `user_agent`, `created_at`) VALUES
(2318, 2, 2, 19, 'fund', 'suppliers', 57, '{\"supplier_id\":57,\"supplier_balance\":\"-185.000\",\"main_account_id\":17,\"main_account_balance\":5180}', '{\"supplier_id\":57,\"supplier_balance\":-285,\"main_account_id\":17,\"main_account_balance\":5080,\"amount\":100,\"currency\":\"USD\",\"remarks\":\"test\",\"receipt_number\":\"245234\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 06:34:32'),
(2319, 2, 2, 19, 'transfer', 'main_account_transactions', 17, '{}', '{\"from_account_id\":\"17\",\"from_account_name\":\"SELF BANK (SAFE)\",\"from_currency\":\"USD\",\"to_account_id\":\"18\",\"to_account_name\":\"AZIZI BANK\",\"to_currency\":\"USD\",\"amount\":100,\"converted_amount\":100,\"exchange_rate\":1,\"description\":\"test\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 06:35:10'),
(2320, 2, 2, 19, 'transfer', 'main_account_transactions', 17, '{}', '{\"from_account_id\":\"17\",\"from_account_name\":\"SELF BANK (SAFE)\",\"from_currency\":\"USD\",\"to_account_id\":\"18\",\"to_account_name\":\"AZIZI BANK\",\"to_currency\":\"USD\",\"amount\":100,\"converted_amount\":100,\"exchange_rate\":1,\"description\":\"test\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 06:35:46'),
(2321, 2, 2, 19, 'fund', 'suppliers', 57, '{\"supplier_id\":57,\"supplier_balance\":\"-285.000\",\"main_account_id\":17,\"main_account_balance\":5080}', '{\"supplier_id\":57,\"supplier_balance\":-385,\"main_account_id\":17,\"main_account_balance\":4980,\"amount\":100,\"currency\":\"USD\",\"remarks\":\"test\",\"receipt_number\":\"245234\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 06:36:46'),
(2322, 2, 2, 19, 'fund', 'suppliers', 57, '{\"supplier_id\":57,\"supplier_balance\":\"-385.000\",\"main_account_id\":17,\"main_account_balance\":5180}', '{\"supplier_id\":57,\"supplier_balance\":-485,\"main_account_id\":17,\"main_account_balance\":5280,\"amount\":100,\"currency\":\"USD\",\"remarks\":\"test\",\"receipt_number\":\"2452345\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 06:37:58'),
(2323, 2, 2, 19, 'update', '0', 49, '{\"transaction_id\":\"49\",\"debtor_id\":\"43\",\"amount\":10,\"description\":\"Initial debt balance for ROHULLAH SAFI\",\"created_at\":\"2025-12-06 11:16:37\",\"transaction_type\":\"debit\",\"currency\":\"USD\"}', '{\"amount\":10,\"description\":\"Initial debt balance for ROHULLAH SAFI\",\"created_at\":\"2025-12-06 11:16:00\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 06:46:45'),
(2324, 2, 2, 19, '0', 'creditor_transactions', 38, '{\"transaction_id\":38,\"creditor_id\":17,\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-12-06 11:57:59\",\"reference_number\":\"1212121\",\"currency\":\"USD\"}', '{\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-12-06 11:57:59\",\"reference_number\":\"1212121\"}', '::1', '0', '2025-12-06 07:29:22'),
(2325, 2, 2, 19, 'update', '0', 407, '{\"ticket_id\":407,\"supplier\":\"57\",\"sold_to\":20,\"price\":\"100.000\",\"sold\":\"110.000\",\"currency\":\"USD\"}', '{\"supplier\":57,\"sold_to\":20,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"IHSAMUDDIN ALIKHEL\",\"pnr\":\"SZQXJU\",\"phone\":\"0776564368\",\"origin\":\"KBL \",\"destination\":\"ELQ\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"EI\",\"issue_date\":\"2025-11-20\",\"departure_date\":\"2025-11-28\",\"return_date\":\"\",\"price\":100,\"sold\":110,\"profit\":10,\"currency\":\"USD\",\"description\":\"Ticket booked for Mr ATIQ ULLAH ZAMAN with PNR: SZQXJU from KBL  to ELQ.\",\"paid_to\":17,\"market_exchange_rate\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 08:07:35'),
(2326, 2, 2, 19, 'add', 'main_account_transactions', 1442, '[]', '{\"booking_id\":404,\"payment_date\":\"2025-12-06 12:37:35\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 08:07:52'),
(2327, 2, 2, 19, 'update', '0', 1442, '{\"transaction_id\":\"1442\",\"ticket_id\":\"404\",\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-12-06 12:37:35\",\"type\":\"credit\",\"currency\":\"USD\",\"exchange_rate\":null}', '{\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-12-06 12:37:35\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 08:08:00'),
(2328, 2, 2, 19, 'update', '0', 1442, '{\"transaction_id\":\"1442\",\"ticket_id\":\"404\",\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-12-06 12:37:35\",\"type\":\"credit\",\"currency\":\"USD\",\"exchange_rate\":null}', '{\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-12-06 12:37:35\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 08:17:28'),
(2329, 2, 2, 19, 'delete', 'main_account_transactions', 1442, '{\"transaction_id\":1442,\"ticket_id\":404,\"amount\":\"10.000\",\"currency\":\"USD\",\"main_account_id\":17,\"created_at\":\"2025-12-06 12:37:35\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 08:17:34'),
(2330, 2, 2, 19, 'delete', 'ticket_bookings', 405, '{\"ticket_id\":405,\"client_id\":null,\"supplier_id\":null,\"paid_to_id\":null,\"ticket_currency\":null}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 08:17:44'),
(2331, 2, 2, 19, 'add', 'ticket_bookings', 408, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"SZQXJU\",\"origin\":\"KBL \",\"destination\":\"FRA\",\"airline\":\"A3\",\"departure_date\":\"2025-12-08\",\"total_base\":100,\"total_sold\":120,\"total_discount\":0,\"total_profit\":20,\"currency\":\"USD\",\"supplier_id\":57,\"supplier_name\":\"KamAir\",\"client_id\":21,\"client_name\":\"walkings\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 08:23:48'),
(2332, 2, 2, 19, 'add', 'ticket_bookings', 409, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"SZQXJU\",\"origin\":\"KBL \",\"destination\":\"FRA\",\"airline\":\"A3\",\"departure_date\":\"2025-12-08\",\"total_base\":100,\"total_sold\":120,\"total_discount\":0,\"total_profit\":20,\"currency\":\"USD\",\"supplier_id\":57,\"supplier_name\":\"KamAir\",\"client_id\":21,\"client_name\":\"walkings\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 08:24:29'),
(2333, 2, 2, 19, 'add', 'ticket_bookings', 410, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"SZQXJU\",\"origin\":\"KBL \",\"destination\":\"FRA\",\"airline\":\"A3\",\"departure_date\":\"2025-12-08\",\"total_base\":100,\"total_sold\":120,\"total_discount\":0,\"total_profit\":20,\"currency\":\"USD\",\"supplier_id\":57,\"supplier_name\":\"KamAir\",\"client_id\":21,\"client_name\":\"walkings\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 08:26:44'),
(2334, 2, 2, 19, 'add', 'ticket_bookings', 411, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"SZQXJU\",\"origin\":\"KBL \",\"destination\":\"FRA\",\"airline\":\"A3\",\"departure_date\":\"2025-12-08\",\"total_base\":100,\"total_sold\":120,\"total_discount\":0,\"total_profit\":20,\"currency\":\"USD\",\"supplier_id\":57,\"supplier_name\":\"KamAir\",\"client_id\":21,\"client_name\":\"walkings\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 08:28:48'),
(2335, 2, 2, 19, 'add', 'ticket_bookings', 412, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"SZQXJU\",\"origin\":\"KBL \",\"destination\":\"FRA\",\"airline\":\"A3\",\"departure_date\":\"2025-12-08\",\"total_base\":100,\"total_sold\":120,\"total_discount\":0,\"total_profit\":20,\"currency\":\"USD\",\"supplier_id\":57,\"supplier_name\":\"KamAir\",\"client_id\":21,\"client_name\":\"walkings\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 08:29:20'),
(2336, 2, 2, 19, 'add', 'ticket_bookings', 413, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"SZQXJU\",\"origin\":\"KBL \",\"destination\":\"FRA\",\"airline\":\"A3\",\"departure_date\":\"2025-12-08\",\"total_base\":100,\"total_sold\":120,\"total_discount\":0,\"total_profit\":20,\"currency\":\"USD\",\"supplier_id\":57,\"supplier_name\":\"KamAir\",\"client_id\":21,\"client_name\":\"walkings\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 08:29:46'),
(2337, 2, 2, 19, 'add', 'refunded_tickets', 143, '{\"ticket_id\":413,\"passenger_name\":\"guli\",\"pnr\":\"SZQXJU\",\"base\":100,\"sold\":120,\"supplier_penalty\":10,\"service_penalty\":20,\"currency\":\"USD\",\"status\":\"Refunded\",\"description\":\"test\",\"calculation_method\":\"sold\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 08:33:36'),
(2338, 2, 2, 19, 'add', 'date_change_tickets', 97, '{\"ticket_id\":\"413\",\"passenger_name\":\"guli\",\"pnr\":\"SZQXJU\",\"base\":100,\"sold\":120,\"supplier_penalty\":20,\"service_penalty\":10,\"currency\":\"USD\",\"status\":\"Date Changed\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 08:35:45'),
(2339, 2, 2, 19, 'create', 'ticket_weights', 70, NULL, '{\"ticket_id\":413,\"weight\":10,\"base_price\":10,\"sold_price\":20,\"profit\":10,\"remarks\":\"test\",\"supplier_name\":\"KamAir\",\"client_name\":\"walkings\",\"currency\":\"USD\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 08:36:31'),
(2340, 2, 2, 19, 'add', 'ticket_bookings', 416, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"WKEPD1\",\"origin\":\"VKO\",\"destination\":\"LED\",\"airline\":\"A3\",\"departure_date\":\"2025-12-10\",\"total_base\":110,\"total_sold\":120,\"total_discount\":0,\"total_profit\":10,\"currency\":\"USD\",\"supplier_id\":57,\"supplier_name\":\"KamAir\",\"client_id\":21,\"client_name\":\"walkings\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 09:06:24'),
(2341, 2, 2, 19, 'add', 'ticket_bookings', 417, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"188JZ0\",\"origin\":\"KBL \",\"destination\":\"FRA\",\"airline\":\"A3\",\"departure_date\":\"2025-12-07\",\"total_base\":100,\"total_sold\":120,\"total_discount\":0,\"total_profit\":20,\"currency\":\"USD\",\"supplier_id\":57,\"supplier_name\":\"KamAir\",\"client_id\":21,\"client_name\":\"walkings\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 09:12:15'),
(2342, 2, 2, 19, 'add', 'ticket_bookings', 418, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"WKEPD1\",\"origin\":\"VKO\",\"destination\":\"LED\",\"airline\":\"A3\",\"departure_date\":\"2025-12-06\",\"total_base\":100,\"total_sold\":120,\"total_discount\":0,\"total_profit\":20,\"currency\":\"USD\",\"supplier_id\":57,\"supplier_name\":\"KamAir\",\"client_id\":21,\"client_name\":\"walkings\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 09:45:15'),
(2343, 2, 2, 19, 'delete', 'ticket_bookings', 418, '{\"ticket_id\":418,\"client_id\":21,\"supplier_id\":57,\"paid_to_id\":17,\"ticket_currency\":\"USD\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 09:49:14'),
(2344, 2, 2, 19, 'delete', 'ticket_bookings', 417, '{\"ticket_id\":417,\"client_id\":21,\"supplier_id\":57,\"paid_to_id\":17,\"ticket_currency\":\"USD\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 09:49:17'),
(2345, 2, 2, 19, 'delete', 'ticket_bookings', 416, '{\"ticket_id\":416,\"client_id\":21,\"supplier_id\":57,\"paid_to_id\":17,\"ticket_currency\":\"USD\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 09:49:21'),
(2346, 2, 2, 19, 'add', 'ticket_bookings', 420, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"RACSXS\",\"origin\":\"VKO\",\"destination\":\"LED\",\"airline\":\"A3\",\"departure_date\":\"2025-12-07\",\"total_base\":100,\"total_sold\":120,\"total_discount\":0,\"total_profit\":20,\"currency\":\"USD\",\"supplier_id\":57,\"supplier_name\":\"KamAir\",\"client_id\":21,\"client_name\":\"walkings\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 09:57:22'),
(2347, 2, 2, 19, 'update', '0', 420, '{\"ticket_id\":420,\"supplier\":\"57\",\"sold_to\":21,\"price\":\"100.000\",\"sold\":\"120.000\",\"currency\":\"USD\"}', '{\"supplier\":57,\"sold_to\":21,\"trip_type\":\"one_way\",\"title\":\"Mr\",\"gender\":\"Male\",\"passenger_name\":\"FAZALHAQ PARDES\",\"pnr\":\"RACSXS\",\"phone\":\"0771781576\",\"origin\":\"VKO\",\"destination\":\"LED\",\"return_origin\":\"\",\"return_destination\":\"\",\"airline\":\"A3\",\"issue_date\":\"2025-12-06\",\"departure_date\":\"2025-12-07\",\"return_date\":\"\",\"price\":100,\"sold\":120,\"profit\":20,\"currency\":\"USD\",\"description\":\"test\",\"paid_to\":17,\"market_exchange_rate\":1}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 10:20:51'),
(2348, 2, 2, 19, 'add', 'ticket_bookings', 421, '{}', '{\"multiple_passengers\":true,\"passenger_count\":1,\"pnr\":\"188JZ0\",\"origin\":\" KBL\",\"destination\":\"FRA\",\"airline\":\"A3\",\"departure_date\":\"2025-12-14\",\"departure_time\":\"15:16\",\"return_departure_time\":\"\",\"total_base\":110,\"total_sold\":120,\"total_discount\":0,\"total_profit\":10,\"currency\":\"USD\",\"supplier_id\":57,\"supplier_name\":\"KamAir\",\"client_id\":21,\"client_name\":\"walkings\",\"trip_type\":\"one_way\"}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 10:47:52'),
(2349, 2, 2, 19, 'add', 'main_account_transactions', 1443, '[]', '{\"booking_id\":143,\"payment_date\":\"2025-12-06 15:26:14\",\"description\":\"test\",\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":0,\"client_type\":\"agency\",\"main_account_id\":17}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 11:02:02'),
(2350, 2, 2, 19, 'delete', 'main_account_transactions', 1443, '{\"transaction_id\":1443,\"ticket_id\":143,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":17,\"created_at\":\"2025-12-06 15:26:14\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 11:03:16'),
(2351, 2, 2, 19, 'delete', 'refunded_tickets', 143, '{\"refund_id\":143,\"client_id\":21,\"supplier_id\":\"57\",\"main_account_id\":17,\"currency\":\"USD\",\"client_type\":\"agency\",\"pnr\":\"SZQXJU\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 11:03:38'),
(2352, 2, 2, 19, 'add', 'refunded_tickets', 144, '{\"ticket_id\":421,\"passenger_name\":\"FAZALHAQ PARDES\",\"pnr\":\"188JZ0\",\"base\":110,\"sold\":120,\"supplier_penalty\":10,\"service_penalty\":20,\"currency\":\"USD\",\"status\":\"pending\",\"description\":\"test\",\"calculation_method\":\"sold\"}', '{}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 11:07:47'),
(2353, 2, 2, 19, 'delete', 'date_change_tickets', 95, '{\"date_change_id\":95,\"client_id\":21,\"supplier_id\":\"57\",\"main_account_id\":17,\"currency\":\"USD\",\"client_type\":\"agency\"}', '[]', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 11:32:31'),
(2354, 2, 2, 19, 'create', 'main_account_transactions', 1444, NULL, '{\"weight_id\":69,\"amount\":10,\"currency\":\"USD\",\"exchange_rate\":null,\"transaction_date\":\"2025-12-06 16:08\",\"remarks\":\"test\",\"main_account_id\":17,\"balance\":5410}', '::1', '0', '2025-12-06 11:40:16'),
(2355, 2, 2, 19, 'update', '0', 1444, '{\"transaction_id\":\"1444\",\"weight_id\":\"69\",\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-12-06 16:08:00\",\"type\":\"credit\",\"currency\":\"USD\",\"exchange_rate\":null}', '{\"amount\":10,\"description\":\"test\",\"created_at\":\"2025-12-06 04:30:00\",\"exchange_rate\":null}', '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 11:40:20'),
(2356, 2, 2, 19, 'delete', 'main_account_transactions', 1444, '{\"transaction_id\":1444,\"weight_id\":69,\"amount\":10,\"currency\":\"USD\",\"main_account_id\":17,\"created_at\":\"2025-12-06 04:30:00\"}', NULL, '::1', 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/142.0.0.0 Safari/537.36', '2025-12-06 11:40:29'),
(2357, 2, 2, 19, 'delete', 'ticket_weights', 69, '{\"weight_id\":69,\"transactions\":[]}', NULL, '::1', '0', '2025-12-06 11:41:30');

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
  `is_for_client` tinyint(1) DEFAULT 0,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `additional_payments`
--

INSERT INTO `additional_payments` (`id`, `tenant_id`, `payment_type`, `description`, `base_amount`, `sold_amount`, `profit`, `currency`, `main_account_id`, `created_by`, `created_at`, `updated_at`, `receipt`, `supplier_id`, `is_from_supplier`, `client_id`, `is_for_client`, `branch_id`) VALUES
(52, 2, 'Vacine', 'test', 10.00, 20.00, 10.00, 'USD', 17, 19, '2025-11-19 09:59:31', '2025-11-25 06:59:59', NULL, NULL, 1, NULL, 1, NULL),
(53, 2, 'Vacine', 'test', 10.00, 20.00, 10.00, 'USD', 17, 19, '2025-12-02 09:02:17', '2025-12-02 09:02:17', NULL, NULL, 1, NULL, 1, 2);

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `audit_logs`
--

INSERT INTO `audit_logs` (`id`, `user_id`, `action`, `entity_type`, `entity_id`, `details`, `ip_address`, `created_at`, `branch_id`) VALUES
(1, 1, 'create_tenant', 'tenant', 1, '{\"tenant_name\": \"Travel Agency Alpha\"}', '192.168.1.1', '2025-08-23 19:30:01', NULL),
(2, 1, 'update_subscription', 'subscription', 1, '{\"plan_id\": \"basic\", \"status\": \"active\"}', '192.168.1.1', '2025-08-23 19:30:02', NULL),
(3, 1, 'update_platform_setting', 'platform_setting', 1, '{\"key\": \"default_currency\", \"value\": \"USD\"}', '192.168.1.1', '2025-08-23 19:30:03', NULL),
(4, 2, 'view_usage_report', 'tenant', 2, '{\"metric_type\": \"api_calls\", \"date\": \"2025-08-23\"}', '192.168.1.2', '2025-08-23 19:30:04', NULL),
(5, 14, 'delete_tenant', 'tenant', 4, '{\"name\":\"Suspended Tours Delta\"}', '::1', '2025-08-26 11:12:34', NULL),
(6, 14, 'create_tenant', 'tenant', 6, '{\"name\":\"KamAir\",\"subdomain\":\"mtravels\",\"identifier\":\"travelalmuqadas\",\"plan\":\"basic\"}', '::1', '2025-08-26 11:39:54', NULL),
(7, 14, 'delete_tenant', 'tenant', 6, '{\"name\":\"KamAir\"}', '::1', '2025-08-26 11:41:22', NULL),
(8, 14, 'update_platform_settings', 'platform_setting', 0, '{\"agency_name\":\"MTravels\",\"default_currency\":\"AFN\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"api_enabled\":\"true\",\"max_users_per_tenant\":\"20\",\"logo_updated\":true}', '::1', '2025-08-26 12:32:02', NULL),
(9, 14, 'update_platform_settings', 'platform_setting', 0, '{\"agency_name\":\"MTravels\",\"default_currency\":\"AFN\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"api_enabled\":\"true\",\"max_users_per_tenant\":\"20\",\"logo_updated\":true}', '::1', '2025-08-26 12:37:54', NULL),
(10, 14, 'update_platform_settings', 'platform_setting', 0, '{\"agency_name\":\"MTravels\",\"default_currency\":\"AFN\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"api_enabled\":\"true\",\"max_users_per_tenant\":\"20\",\"logo_updated\":true}', '::1', '2025-08-26 12:40:47', NULL),
(11, 14, 'extend_subscription', 'subscription', 1, 'Extended subscription by 1 months', '', '2025-08-27 12:46:29', NULL),
(12, 14, 'extend_subscription', 'subscription', 1, 'Extended subscription by 3 months', '', '2025-08-27 12:48:27', NULL),
(13, 14, 'update_plan', 'plan', 0, '{\"old_name\":\"basic\",\"new_name\":\"basic\",\"description\":\"Basic plan with access to ticket-related tasks only\",\"price\":\"200\",\"max_users\":\"20\",\"trial_days\":\"10\",\"status\":\"active\"}', '::1', '2025-08-28 07:26:25', NULL),
(14, 14, 'update_plan', 'plan', 0, '{\"old_name\":\"basic\",\"new_name\":\"basic\",\"description\":\"Basic plan with access to ticket-related tasks only\",\"price\":\"200.00\",\"max_users\":\"20\",\"trial_days\":\"10\",\"status\":\"active\"}', '::1', '2025-08-28 10:20:33', NULL),
(15, 14, 'update_subscription', 'subscription', 5, '{\"tenant_id\":5,\"plan_id\":\"2\",\"status\":\"pending\",\"billing_cycle\":\"monthly\"}', '::1', '2025-08-30 07:39:31', NULL),
(16, 14, 'update_plan', 'plan', 0, '{\"old_name\":\"basic\",\"new_name\":\"basic\",\"description\":\"Basic plan with access to ticket-related tasks only\",\"price\":\"200.00\",\"max_users\":\"20\",\"trial_days\":\"10\",\"status\":\"active\"}', '::1', '2025-08-30 07:40:19', NULL),
(17, 14, 'update_tenant', 'tenant', 5, '{\"tenant_id\":5,\"name\":\"New Ventures Epsilon\",\"subdomain\":\"epsilon\",\"status\":\"active\"}', '::1', '2025-08-30 07:43:52', NULL),
(18, 14, 'update_platform_settings', 'platform_setting', 0, '{\"agency_name\":\"MTravels\",\"default_currency\":\"AFN\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"api_enabled\":\"false\",\"max_users_per_tenant\":\"20\",\"logo_updated\":false}', '::1', '2025-08-30 07:56:11', NULL),
(19, 14, 'update_tenant', 'tenant', 1, '{\"tenant_id\":1,\"name\":\"Travel Agency Alpha\",\"subdomain\":\"alpha\",\"status\":\"active\"}', '::1', '2025-09-01 12:25:47', NULL),
(20, 14, 'update_tenant', 'tenant', 2, '{\"tenant_id\":2,\"name\":\"Global Tours Beta\",\"subdomain\":\"beta\",\"status\":\"active\"}', '::1', '2025-09-01 12:25:54', NULL),
(21, 14, 'update_tenant', 'tenant', 5, '{\"tenant_id\":5,\"name\":\"New Ventures Epsilon\",\"subdomain\":\"epsilon\",\"status\":\"active\"}', '::1', '2025-09-01 12:26:00', NULL),
(22, 14, 'update_subscription', 'subscription', 1, '{\"tenant_id\":1,\"plan_id\":\"2\",\"status\":\"active\",\"billing_cycle\":\"monthly\"}', '::1', '2025-09-01 12:46:02', NULL),
(23, 14, 'update_subscription', 'subscription', 1, '{\"tenant_id\":1,\"plan_id\":\"3\",\"status\":\"active\",\"billing_cycle\":\"monthly\"}', '::1', '2025-09-01 12:46:15', NULL),
(24, 14, 'update_plan', 'plan', 0, '{\"old_name\":\"enterprise\",\"new_name\":\"enterprise\",\"description\":\"Enterprise plan with all Pro features plus Umrah management\",\"price\":\"0.00\",\"max_users\":\"0\",\"trial_days\":\"0\",\"status\":\"active\"}', '::1', '2025-09-03 06:15:43', NULL),
(25, 14, 'update_subscription', 'subscription', 2, '{\"tenant_id\":2,\"plan_id\":\"3\",\"status\":\"active\",\"billing_cycle\":\"yearly\"}', '::1', '2025-09-04 12:32:25', NULL),
(26, 14, 'update_platform_settings', 'platform_setting', 0, '{\"platform_name\":\"MTravels\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"contact_email\":\"allahdadmuhammadi01@gmail.com\",\"website_url\":\"https:\\/\\/construct360.com\",\"contact_phone\":\"0780310431\",\"support_phone\":\"+93780310431\",\"contact_address\":\"Kabul,Afghanistan\",\"contact_facebook\":\"https:\\/\\/mtravels.com\",\"contact_twitter\":\"https:\\/\\/mtravels.com\",\"contact_linkedin\":\"https:\\/\\/mtravels.com\",\"contact_instagram\":\"https:\\/\\/mtravels.com\",\"default_currency\":\"AFN\",\"max_users_per_tenant\":\"20\",\"api_enabled\":\"false\",\"logo_updated\":false,\"favicon_updated\":false}', '::1', '2025-09-09 06:01:54', NULL),
(27, 14, 'update_platform_settings', 'platform_setting', 0, '{\"platform_name\":\"MTravels\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"contact_email\":\"allahdadmuhammadi01@gmail.com\",\"website_url\":\"https:\\/\\/mtravels.com\",\"contact_phone\":\"0780310431\",\"support_phone\":\"+93780310431\",\"contact_address\":\"Kabul,Afghanistan\",\"contact_facebook\":\"https:\\/\\/mtravels.com\",\"contact_twitter\":\"https:\\/\\/mtravels.com\",\"contact_linkedin\":\"https:\\/\\/mtravels.com\",\"contact_instagram\":\"https:\\/\\/mtravels.com\",\"default_currency\":\"AFN\",\"max_users_per_tenant\":\"20\",\"api_enabled\":\"false\",\"logo_updated\":false,\"favicon_updated\":false}', '::1', '2025-09-09 06:02:09', NULL),
(28, 14, 'update_platform_settings', 'platform_setting', 0, '{\"platform_name\":\"MTravels\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"contact_email\":\"allahdadmuhammadi01@gmail.com\",\"website_url\":\"https:\\/\\/mtravels.com\",\"contact_phone\":\"0780310431\",\"support_phone\":\"+93780310431\",\"contact_address\":\"Kabul,Afghanistan\",\"contact_facebook\":\"https:\\/\\/mtravels.com\",\"contact_twitter\":\"https:\\/\\/mtravels.com\",\"contact_linkedin\":\"https:\\/\\/mtravels.com\",\"contact_instagram\":\"https:\\/\\/mtravels.com\",\"default_currency\":\"AFN\",\"max_users_per_tenant\":\"20\",\"api_enabled\":\"false\",\"logo_updated\":false,\"favicon_updated\":false}', '::1', '2025-09-09 06:02:23', NULL),
(29, 14, 'update_platform_settings', 'platform_setting', 0, '{\"platform_name\":\"MTravels\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"contact_email\":\"allahdadmuhammadi01@gmail.com\",\"website_url\":\"https:\\/\\/mtravels.com\",\"contact_phone\":\"0780310431\",\"support_phone\":\"+93780310431\",\"contact_address\":\"Kabul,Afghanistan\",\"contact_facebook\":\"https:\\/\\/mtravels.com\",\"contact_twitter\":\"https:\\/\\/mtravels.com\",\"contact_linkedin\":\"https:\\/\\/mtravels.com\",\"contact_instagram\":\"https:\\/\\/mtravels.com\",\"default_currency\":\"AFN\",\"max_users_per_tenant\":\"20\",\"api_enabled\":\"false\",\"logo_updated\":false,\"favicon_updated\":false}', '::1', '2025-09-09 06:05:17', NULL),
(30, 14, 'update_platform_settings', 'platform_setting', 0, '{\"platform_name\":\"MTravels\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"contact_email\":\"allahdadmuhammadi01@gmail.com\",\"website_url\":\"https:\\/\\/mtravels.com\",\"contact_phone\":\"0780310431\",\"support_phone\":\"+93780310431\",\"contact_address\":\"Kabul,Afghanistan\",\"contact_facebook\":\"https:\\/\\/mtravels.com\",\"contact_twitter\":\"https:\\/\\/mtravels.com\",\"contact_linkedin\":\"https:\\/\\/mtravels.com\",\"contact_instagram\":\"https:\\/\\/mtravels.com\",\"default_currency\":\"AFN\",\"max_users_per_tenant\":\"20\",\"api_enabled\":\"false\",\"logo_updated\":false,\"favicon_updated\":false}', '::1', '2025-09-09 06:07:01', NULL),
(31, 14, 'delete_tenant', 'tenant', 3, '{\"name\":\"Elite Pilgrimages Gamma\"}', '::1', '2025-09-09 06:52:26', NULL),
(32, 14, 'delete_tenant', 'tenant', 5, '{\"name\":\"New Ventures Epsilon\"}', '::1', '2025-09-09 06:52:30', NULL),
(33, 14, 'update_subscription', 'subscription', 1, '{\"tenant_id\":1,\"plan_id\":\"3\",\"status\":\"active\",\"billing_cycle\":\"monthly\"}', '::1', '2025-09-17 04:30:47', NULL),
(34, 14, 'update_subscription', 'subscription', 2, '{\"tenant_id\":2,\"plan_id\":\"3\",\"status\":\"active\",\"billing_cycle\":\"yearly\"}', '::1', '2025-09-17 05:42:36', NULL),
(35, 14, 'update_subscription', 'subscription', 2, '{\"tenant_id\":2,\"plan_id\":\"3\",\"status\":\"active\",\"billing_cycle\":\"yearly\"}', '::1', '2025-09-17 06:40:29', NULL),
(36, 14, 'update_subscription', 'subscription', 1, '{\"subscription_id\":1,\"plan_id\":\"3\",\"status\":\"active\",\"billing_cycle\":\"monthly\"}', '::1', '2025-09-18 09:19:08', NULL),
(37, 14, 'update_subscription', 'subscription', 1, '{\"subscription_id\":1,\"plan_id\":\"3\",\"status\":\"active\",\"billing_cycle\":\"monthly\"}', '::1', '2025-09-18 10:04:53', NULL),
(38, 14, 'update_platform_settings', 'platform_setting', 0, '{\"platform_name\":\"MTravels\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"contact_email\":\"allahdadmuhammadi01@gmail.com\",\"website_url\":\"https:\\/\\/mtravels.com\",\"contact_phone\":\"0780310431\",\"support_phone\":\"+93780310431\",\"contact_address\":\"Kabul,Afghanistan\",\"contact_facebook\":\"https:\\/\\/mtravels.com\",\"contact_twitter\":\"https:\\/\\/mtravels.com\",\"contact_linkedin\":\"https:\\/\\/mtravels.com\",\"contact_instagram\":\"https:\\/\\/mtravels.com\",\"default_currency\":\"AFN\",\"max_users_per_tenant\":\"20\",\"api_enabled\":\"false\",\"logo_updated\":true,\"favicon_updated\":false}', '::1', '2025-10-02 11:52:07', NULL),
(39, 14, 'update_demo_request_status', 'demo_request', 1, '{\"new_status\":\"contacted\"}', '::1', '2025-10-04 06:06:40', NULL),
(40, 14, 'update_subscription', 'subscription', 1, '{\"subscription_id\":1,\"plan_id\":\"3\",\"status\":\"active\",\"billing_cycle\":\"monthly\"}', '::1', '2025-10-06 06:29:38', NULL),
(41, 14, 'update_testimonial', 'testimonials', 4, 'Updated testimonial for Ahmad Rahimi', '', '2025-10-13 10:16:57', NULL),
(42, 14, 'update_plan', 'plan', 0, '{\"old_name\":\"basic\",\"new_name\":\"basic\",\"description\":\"Basic plan with access to ticket-related tasks only\",\"price\":\"1500\",\"max_users\":\"10\",\"trial_days\":\"10\",\"status\":\"active\"}', '::1', '2025-11-05 05:45:05', NULL),
(43, 14, 'update_plan', 'plan', 0, '{\"old_name\":\"pro\",\"new_name\":\"pro\",\"description\":\"Pro plan with ticket-related tasks, visa-related tasks, and inter-tenant chat\",\"price\":\"2800\",\"max_users\":\"15\",\"trial_days\":\"30\",\"status\":\"active\"}', '::1', '2025-11-05 05:45:47', NULL),
(44, 14, 'update_plan', 'plan', 0, '{\"old_name\":\"enterprise\",\"new_name\":\"enterprise\",\"description\":\"Enterprise plan with all Pro features plus Umrah management\",\"price\":\"5000\",\"max_users\":\"30\",\"trial_days\":\"30\",\"status\":\"active\"}', '::1', '2025-11-05 05:46:16', NULL),
(45, 14, 'update_subscription', 'subscription', 1, '{\"subscription_id\":1,\"plan_id\":\"1\",\"status\":\"active\",\"billing_cycle\":\"monthly\"}', '::1', '2025-11-15 03:43:24', NULL),
(46, 14, 'update_subscription', 'subscription', 2, '{\"subscription_id\":2,\"plan_id\":\"1\",\"status\":\"active\",\"billing_cycle\":\"yearly\"}', '::1', '2025-11-15 03:53:42', NULL),
(47, 14, 'update_tenant', 'tenant', 2, '{\"tenant_id\":2,\"name\":\"Al Wali\",\"subdomain\":\"beta\",\"status\":\"active\"}', '::1', '2025-11-15 03:53:54', NULL),
(48, 14, 'update_tenant', 'tenant', 1, '{\"tenant_id\":1,\"name\":\"Al Moqadas Travel Agency\",\"subdomain\":\"alpha\",\"status\":\"active\"}', '::1', '2025-11-15 03:54:05', NULL),
(49, 14, 'update_tenant', 'tenant', 1, '{\"tenant_id\":1,\"name\":\"Al Moqadas Travel Agency\",\"subdomain\":\"alpha\",\"status\":\"active\"}', '::1', '2025-11-16 04:39:09', NULL),
(50, 14, 'update_subscription', 'subscription', 1, '{\"subscription_id\":1,\"plan_id\":\"3\",\"status\":\"active\",\"billing_cycle\":\"monthly\"}', '::1', '2025-11-16 05:54:03', NULL),
(51, 14, 'update_subscription', 'subscription', 2, '{\"subscription_id\":2,\"plan_id\":\"4\",\"status\":\"active\",\"billing_cycle\":\"yearly\"}', '::1', '2025-11-16 10:28:12', NULL),
(52, 14, 'update_subscription', 'subscription', 2, '{\"subscription_id\":2,\"plan_id\":\"1\",\"status\":\"active\",\"billing_cycle\":\"yearly\"}', '::1', '2025-11-16 11:06:25', NULL),
(53, 14, 'update_subscription', 'subscription', 2, '{\"subscription_id\":2,\"plan_id\":\"2\",\"status\":\"active\",\"billing_cycle\":\"yearly\"}', '::1', '2025-11-16 11:07:54', NULL),
(54, 14, 'update_subscription', 'subscription', 2, '{\"subscription_id\":2,\"plan_id\":\"3\",\"status\":\"active\",\"billing_cycle\":\"yearly\"}', '::1', '2025-11-16 11:08:54', NULL),
(55, 14, 'update_platform_settings', 'platform_setting', 0, '{\"platform_name\":\"MTravels\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"contact_email\":\"allahdadmuhammadi01@gmail.com\",\"website_url\":\"https:\\/\\/mtravels.com\",\"contact_phone\":\"0780310431\",\"support_phone\":\"+93780310431\",\"contact_address\":\"Kabul,Afghanistan\",\"contact_facebook\":\"https:\\/\\/mtravels.com\",\"contact_twitter\":\"https:\\/\\/mtravels.com\",\"contact_linkedin\":\"https:\\/\\/mtravels.com\",\"contact_instagram\":\"https:\\/\\/mtravels.com\",\"default_currency\":\"AFN\",\"max_users_per_tenant\":\"20\",\"api_enabled\":\"false\",\"logo_updated\":false,\"favicon_updated\":false,\"smtp_host\":\"smtp.gmail.com\",\"smtp_port\":\"587\",\"smtp_encryption\":\"tls\",\"smtp_username\":\"allahdadmuhammadi01@gmail.com\",\"smtp_from_email\":\"allahdadmuhammadi01@gmail.com\",\"smtp_from_name\":\"MTravels\"}', '::1', '2025-11-18 06:32:20', NULL),
(56, 14, 'update_platform_settings', 'platform_setting', 0, '{\"platform_name\":\"MTravels\",\"support_email\":\"allahdadmuhammadi01@gmail.com\",\"contact_email\":\"allahdadmuhammadi01@gmail.com\",\"website_url\":\"https:\\/\\/mtravels.com\",\"contact_phone\":\"0780310431\",\"support_phone\":\"+93780310431\",\"contact_address\":\"Kabul,Afghanistan\",\"contact_facebook\":\"https:\\/\\/mtravels.com\",\"contact_twitter\":\"https:\\/\\/mtravels.com\",\"contact_linkedin\":\"https:\\/\\/mtravels.com\",\"contact_instagram\":\"https:\\/\\/mtravels.com\",\"default_currency\":\"AFN\",\"max_users_per_tenant\":\"20\",\"api_enabled\":\"false\",\"logo_updated\":false,\"favicon_updated\":false,\"smtp_host\":\"smtp.gmail.com\",\"smtp_port\":\"587\",\"smtp_encryption\":\"tls\",\"smtp_username\":\"allahdadmuhammadi01@gmail.com\",\"smtp_from_email\":\"allahdadmuhammadi01@gmail.com\",\"smtp_from_name\":\"MTravels\"}', '::1', '2025-11-18 09:09:33', NULL);

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `blog_posts`
--

INSERT INTO `blog_posts` (`id`, `title`, `slug`, `content`, `excerpt`, `featured_image`, `author`, `category`, `status`, `created_at`, `updated_at`, `branch_id`) VALUES
(4, 'Test', 'test', 'Mtravels offer variaty of services such us ticket management visa mamangent and so on', 'Today we want to make our first post or mtravels', '/uploads/blog/blog_69100d0eca8d3.png', 'MTravels Team', 'test', 'published', '2025-11-09 03:39:58', '2025-11-09 03:39:58', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `code` varchar(50) NOT NULL,
  `address` text DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `email` varchar(255) DEFAULT NULL,
  `manager_id` int(11) DEFAULT NULL,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `tenant_id`, `name`, `code`, `address`, `phone`, `email`, `manager_id`, `status`, `created_by`, `created_at`, `updated_at`, `branch_id`) VALUES
(1, 1, 'Al Moqadas Travel Agency - Main Branch', 'MAIN-1', NULL, NULL, NULL, NULL, 'active', 1, '2025-11-24 10:02:34', '2025-11-24 10:02:34', NULL),
(2, 2, 'Al Wali - Main Branch', 'MAIN-2', NULL, NULL, NULL, NULL, 'active', 7, '2025-11-24 10:02:34', '2025-11-24 10:02:34', NULL),
(4, 2, 'Heart', 'H1', 'Jada-e-Maiwand', '0786011115', 'halmuqadas_travel@yahoo.com', NULL, 'active', 7, '2025-11-24 10:18:05', '2025-11-24 10:18:05', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `branch_audit_log`
--

CREATE TABLE `branch_audit_log` (
  `id` bigint(20) UNSIGNED NOT NULL,
  `branch_id` int(11) NOT NULL,
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cancellation_reapply_log`
--

CREATE TABLE `cancellation_reapply_log` (
  `id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `action` enum('cancellation','reapplication') NOT NULL,
  `base_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `sold_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `previous_profit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `new_profit` decimal(10,2) NOT NULL DEFAULT 0.00,
  `reason` text NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `cancellation_reapply_log`
--

INSERT INTO `cancellation_reapply_log` (`id`, `booking_id`, `action`, `base_price`, `sold_price`, `previous_profit`, `new_profit`, `reason`, `tenant_id`, `created_by`, `created_at`, `branch_id`) VALUES
(3, 69, 'cancellation', 10.00, 20.00, 10.00, 0.00, '0', 2, 7, '2025-11-22 07:14:55', NULL),
(5, 69, 'reapplication', 10.00, 20.00, 0.00, 10.00, '0', 2, 7, '2025-11-22 07:15:16', NULL),
(7, 69, 'cancellation', 10.00, 20.00, 10.00, 0.00, '0', 2, 7, '2025-11-22 07:25:31', NULL),
(9, 69, 'reapplication', 10.00, 20.00, 0.00, 10.00, '0', 2, 7, '2025-11-22 07:26:12', NULL),
(12, 69, 'cancellation', 10.00, 20.00, 10.00, 0.00, '0', 2, 7, '2025-11-22 07:34:18', NULL),
(14, 69, 'reapplication', 10.00, 20.00, 0.00, 10.00, '0', 2, 7, '2025-11-22 07:34:38', NULL);

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
  `seen_at` timestamp NULL DEFAULT NULL,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `chat_messages`
--

INSERT INTO `chat_messages` (`id`, `room_id`, `from_user_id`, `to_user_id`, `tenant_id_from`, `content`, `created_at`, `seen_at`, `branch_id`) VALUES
(1, 'u-1-6', 1, 6, 1, 'hi', '2025-09-03 10:57:21', '2025-09-04 10:38:32', NULL),
(2, 'u-1-6', 6, 1, 1, 'heloo', '2025-09-03 10:59:46', '2025-09-04 12:01:07', NULL),
(3, 'u-1-6', 1, 6, 1, 'hiy', '2025-09-03 11:11:09', '2025-09-04 10:38:32', NULL),
(4, 'u-1-6', 1, 6, 1, 'hi', '2025-09-03 12:22:06', '2025-09-04 10:38:32', NULL),
(5, 'u-1-6', 6, 1, 1, 'hi', '2025-09-03 12:42:15', '2025-09-04 12:01:07', NULL),
(6, 'u-1-6', 1, 6, 1, '{\"type\":\"file\",\"name\":\"01.pdf\",\"size\":368254,\"mimeType\":\"application\\/pdf\",\"filePath\":\"file_68b83d78d693d_01.pdf\"}', '2025-09-03 13:07:04', '2025-09-04 10:38:32', NULL),
(7, 'u-1-6', 1, 6, 1, 'adf', '2025-09-03 13:11:37', '2025-09-04 10:38:32', NULL),
(8, 'u-1-6', 1, 6, 1, '{\"type\":\"file\",\"name\":\"01.pdf\",\"size\":368254,\"mimeType\":\"application\\/pdf\",\"filePath\":\"file_68b83e8e935f5_01.pdf\"}', '2025-09-03 13:11:42', '2025-09-04 10:38:32', NULL),
(9, 'u-1-6', 1, 6, 1, 'adf', '2025-09-03 13:12:17', '2025-09-04 10:38:32', NULL),
(10, 'u-1-6', 1, 6, 1, '{\"type\":\"file\",\"name\":\"Blue Modern Travel Poster Portrait.png\",\"size\":13431638,\"mimeType\":\"image\\/png\",\"filePath\":\"file_68b83eb814e5a_Blue_Modern_Travel_Poster_Portrait.png\"}', '2025-09-03 13:12:24', '2025-09-04 10:38:32', NULL),
(11, 'u-1-6', 1, 6, 1, 'adf', '2025-09-04 03:42:12', '2025-09-04 10:38:32', NULL),
(12, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"01.pdf\",\"size\":368254,\"mimeType\":\"application\\/pdf\",\"filePath\":\"file_68b90ab369be6_01.pdf\"}', '2025-09-04 03:42:43', '2025-09-04 12:01:07', NULL),
(13, 'u-1-6', 1, 6, 1, '{\"type\":\"file\",\"name\":\"Blue and White Grunge Travel and Tourism Instagram Post.png\",\"size\":1030459,\"mimeType\":\"image\\/png\",\"filePath\":\"file_68b967379e0fe_Blue_and_White_Grunge_Travel_and_Tourism_Instagram_Post.png\"}', '2025-09-04 10:17:27', '2025-09-04 10:38:32', NULL),
(14, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"Blue and White Modern Travel Instagram Post.png\",\"size\":1363535,\"mimeType\":\"image\\/png\",\"filePath\":\"file_68b9695fea986_Blue_and_White_Modern_Travel_Instagram_Post.png\"}', '2025-09-04 10:26:39', '2025-09-04 12:01:07', NULL),
(15, 'u-1-6', 6, 1, 1, 'hi', '2025-09-04 11:21:34', '2025-09-04 12:01:07', NULL),
(16, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"01.pdf\",\"size\":368254,\"mimeType\":\"application\\/pdf\",\"filePath\":\"file_68b97885ca8a6_01.pdf\"}', '2025-09-04 11:31:17', '2025-09-04 12:01:07', NULL),
(17, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"01.pdf\",\"size\":368254,\"mimeType\":\"application\\/pdf\",\"filePath\":\"file_68b97b27811c2_01.pdf\"}', '2025-09-04 11:42:31', '2025-09-04 12:01:07', NULL),
(18, 'u-1-6', 6, 1, 1, 'fa', '2025-09-04 11:42:42', '2025-09-04 12:01:07', NULL),
(19, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"03.pdf\",\"size\":356854,\"mimeType\":\"application\\/pdf\",\"filePath\":\"file_68b97b39d3e1d_03.pdf\"}', '2025-09-04 11:42:49', '2025-09-04 12:01:07', NULL),
(20, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"\\u0631\\u0633\\u06cc\\u062f \\u0628\\u0627\\u0646\\u06a9\\u06cc - Al Moqadas.pdf\",\"size\":196990,\"mimeType\":\"application\\/pdf\",\"filePath\":\"file_68b97b6e505d6_____________________-_Al_Moqadas.pdf\"}', '2025-09-04 11:43:42', '2025-09-04 12:01:07', NULL),
(21, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"Blue Modern Travel Poster Portrait.png\",\"size\":13431638,\"mimeType\":\"image\\/png\",\"filePath\":\"file_68b97b73d9136_Blue_Modern_Travel_Poster_Portrait.png\"}', '2025-09-04 11:43:47', '2025-09-04 12:01:07', NULL),
(22, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"03.pdf\",\"size\":356854,\"mimeType\":\"application\\/pdf\",\"filePath\":\"file_68b97c5c366e6_03.pdf\"}', '2025-09-04 11:47:40', '2025-09-04 12:01:07', NULL),
(23, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"Yellow And Blue Modern Open Trip To England Instagram Post.png\",\"size\":722868,\"mimeType\":\"image\\/png\",\"filePath\":\"file_68b97fad61f31_Yellow_And_Blue_Modern_Open_Trip_To_England_Instagram_Post.png\"}', '2025-09-04 12:01:49', '2025-09-04 12:02:45', NULL),
(24, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"aisalmot_travelagency (14).sql\",\"size\":2133029,\"mimeType\":\"text\\/plain\",\"filePath\":\"file_68b97fcfb0ed6_aisalmot_travelagency__14_.sql\"}', '2025-09-04 12:02:23', '2025-09-04 12:02:45', NULL),
(25, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"03.pdf\",\"size\":356854,\"mimeType\":\"application\\/pdf\",\"filePath\":\"file_68b98187cb4ec_03.pdf\"}', '2025-09-04 12:09:43', '2025-09-04 12:17:06', NULL),
(26, 'u-1-6', 6, 1, 1, '{\"type\":\"file\",\"name\":\"voice-1756988605345.webm\",\"size\":122843,\"mimeType\":\"video\\/webm\",\"filePath\":\"file_68b984bd583ae_voice-1756988605345.webm\"}', '2025-09-04 12:23:25', '2025-09-04 12:28:57', NULL),
(27, 'u-1-7', 1, 7, 1, 'hello', '2025-09-04 12:58:00', '2025-09-04 12:58:13', NULL),
(28, 'u-1-6', 1, 6, 1, 'heloo', '2025-09-08 08:27:42', '2025-09-08 08:29:06', NULL),
(30, 'u-1-7', 1, 7, 1, '{\"type\":\"reply\",\"replyTo\":\"27\",\"replyText\":\"hello\",\"content\":\"fadf\"}', '2025-09-08 10:19:26', '2025-09-09 07:09:30', NULL),
(31, 'u-1-6', 1, 6, 1, 'hello', '2025-09-08 10:23:11', '2025-09-08 11:52:31', NULL),
(32, 'u-1-7', 1, 7, 1, 'hi', '2025-09-08 10:29:45', '2025-09-09 07:09:30', NULL),
(34, 'u-1-6', 1, 6, 1, '{\"type\":\"reply\",\"replyTo\":\"18\",\"replyText\":\"fa\",\"content\":\"hi\"}', '2025-09-08 10:36:34', '2025-09-08 11:52:31', NULL),
(35, 'u-1-7', 1, 7, 1, '{\"type\":\"file\",\"name\":\"Yellow And Blue Modern Open Trip To England Instagram Post.png\",\"size\":722868,\"mimeType\":\"image\\/png\",\"filePath\":\"file_68beb2d9b6d9b_Yellow_And_Blue_Modern_Open_Trip_To_England_Instagram_Post.png\"}', '2025-09-08 10:41:29', '2025-09-09 07:09:30', NULL),
(36, 'u-1-6', 1, 6, 1, '{\"type\":\"reply\",\"replyTo\":\"23\",\"replyText\":\"📷 Photo\",\"content\":\"good\"}', '2025-09-08 10:41:54', '2025-09-08 11:52:31', NULL),
(37, 'u-1-6', 1, 6, 1, '{\"type\":\"reply\",\"replyTo\":\"34\",\"replyText\":\"hi\",\"content\":\"helo\"}', '2025-09-08 10:53:42', '2025-09-08 11:52:31', NULL),
(38, 'u-1-7', 1, 7, 1, 'helo', '2025-09-08 11:00:34', '2025-09-09 07:09:30', NULL),
(39, 'u-1-6', 1, 6, 1, 'hi', '2025-10-28 04:34:25', '2025-11-01 09:56:32', NULL),
(40, 'u-1-7', 1, 7, 1, 'hi', '2025-10-29 10:16:34', '2025-11-16 10:31:29', NULL),
(41, 'u-1-18', 18, 1, 1, 'hi', '2025-10-30 06:32:06', '2025-11-01 06:32:40', NULL);

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
  `totp_enabled` tinyint(1) NOT NULL DEFAULT 0,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `clients`
--

INSERT INTO `clients` (`id`, `tenant_id`, `image`, `name`, `email`, `password_hash`, `phone`, `usd_balance`, `afs_balance`, `address`, `created_at`, `updated_at`, `status`, `client_type`, `totp_enabled`, `branch_id`) VALUES
(18, 1, '', 'DR SAHIBs', 'admin@abc-construction.com', '$2y$10$wNU7te9YSBzmM86Q3Z9D6eLp2SloDOyIz29TUmz/JDc9ZJMwT2EG.', '0777305730', -4314.190, 0.000, 'Jada-e-Maiwand', '2025-09-01 10:44:22', '2025-11-19 06:04:03', 'active', 'regular', 0, NULL),
(19, 1, '690854d3a4ecb_Blue and White Simple Furniture Doubled Sided Poster.png', 'Walking Customer', 'almuqadas_travel@yahoo.com', '$2y$10$RCql5Ieq91Jkd8FaykSbKOxX/QNNJ9LD4jKJORz6Zkw9rlalh0qVS', '0777305730', 0.000, 0.000, 'Jada-e-Maiwand', '2025-09-07 08:42:27', '2025-11-16 09:50:27', 'active', 'agency', 0, NULL),
(20, 2, '', 'DR SAHIB', 'fluentcenter01@gmail.com', '$2y$10$AgeoMovkpOzvOgWKT7b5tegnAHDaUcLYwr3SJ80aDsw0o20llzJrm', '0777305730', 0.000, 0.000, 'Jada-e-Maiwand', '2025-09-10 11:48:39', '2025-12-06 06:38:52', 'active', 'regular', 0, 2),
(21, 2, '', 'walkings', 'esmati@gmail.com', '$2y$10$JWMeEbMm9oA4FxG9DhK6.uMNh.NLazftrApSj/oG3KPJcM1MikAJK', '0777305730', 0.000, 0.000, 'Jada-e-Maiwand', '2025-09-10 12:31:12', '2025-11-25 05:46:06', 'active', 'agency', 0, 2),
(22, 1, '', 'MINA Khan', 'mina@yahoo.com', '$2y$10$E70pRj3P2NYVedD5bA1UgeOB0srLqm.0nFlsDsGmhIB5BsKDl/qha', '0777305730', 0.000, 0.000, 'adfads', '2025-10-16 11:29:38', '2025-11-16 09:50:39', 'active', 'regular', 0, NULL);

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
  `transaction_of` enum('ticket_sale','visa_sale','ticket_refund','date_change','fund','umrah','hotel','hotel_refund','ticket_reserve','jv_payment','additional_payment','visa_refund','hotel_refund','umrah_refund','additional_payment','weight_sale','umrah_date_change','umrah_cancellation','visa_cancellation') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `receipt` varchar(100) DEFAULT NULL,
  `exchange_rate` decimal(10,5) DEFAULT NULL,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `client_transactions`
--

INSERT INTO `client_transactions` (`id`, `tenant_id`, `client_id`, `type`, `amount`, `balance`, `currency`, `description`, `created_at`, `transaction_of`, `reference_id`, `receipt`, `exchange_rate`, `branch_id`) VALUES
(590, 2, 21, 'debit', 120.000, -120.000, 'USD', 'Ticket booked for Mr FAZALHAQ PARDES with PNR: HAUPSE from  KBL to FRA. (Transferred from client 20)', '2025-11-18 10:59:21', 'ticket_sale', 400, NULL, NULL, NULL),
(593, 2, 20, 'debit', 150.000, -150.000, 'USD', 'Ticket booked for Mr SHIRIN AGHA MUTAWAKIL with PNR: WKEPD1 from KBL  to DXB.', '2025-11-19 04:01:51', 'ticket_sale', 403, NULL, NULL, NULL),
(594, 2, 21, 'debit', 130.000, -400.000, 'USD', 'Ticket booked for Mr wali with PNR: SZQXJU from  KBL to ELQ. (Transferred from client 20)', '2025-11-19 04:15:31', 'ticket_sale', 404, NULL, NULL, NULL),
(595, 2, 20, 'debit', 20.000, -170.000, 'USD', 'Hotel booking for Mr ROHULLAH SAFI', '2025-11-19 05:22:13', 'hotel', 54, NULL, NULL, NULL),
(596, 2, 20, 'debit', 30.000, -200.000, 'USD', 'Hotel booking for Mr ROHULLAH SAFI', '2025-11-19 05:26:52', 'hotel', 55, NULL, NULL, NULL),
(597, 1, 18, 'debit', 50.000, -4314.190, 'USD', 'Hotel booking for Mr ROHULLAH SAFI', '2025-11-19 06:04:03', 'hotel', 56, NULL, NULL, NULL),
(598, 2, 21, 'debit', 0.000, 0.000, 'USD', 'Client was debited 0 for umrah booking for KamAir', '2025-11-19 06:28:16', 'umrah', 68, NULL, NULL, NULL),
(599, 2, 21, 'debit', 0.000, 0.000, 'USD', 'Client was debited 0 for umrah booking for KamAir', '2025-11-19 06:28:58', 'umrah', 69, NULL, NULL, NULL),
(602, 2, 20, 'debit', 30.000, -230.000, 'USD', 'Weight transaction: 20kg at 30 USD.', '2025-11-19 06:57:37', 'weight_sale', 69, NULL, NULL, NULL),
(603, 2, 20, 'credit', 130.000, -100.000, 'USD', 'Refund for ticket wali.', '2025-11-19 06:58:11', 'ticket_refund', 141, NULL, NULL, NULL),
(605, 2, 20, 'debit', 20.000, -120.000, 'USD', 'Ticket reservation for passenger BAKHTIAR STANIKZAI', '2025-11-19 07:17:37', 'ticket_reserve', 30, NULL, NULL, NULL),
(606, 2, 21, 'credit', 50.000, 0.000, 'USD', 'Refund for hotel booking #58 - test', '2025-11-19 09:30:00', 'hotel_refund', 16, NULL, NULL, NULL),
(607, 2, 21, 'credit', 20.000, 0.000, 'USD', 'Refund for umrah booking #68 - tet', '2025-11-19 09:31:42', 'umrah_refund', 26, NULL, NULL, NULL),
(608, 2, 21, 'credit', 20.000, 0.000, 'USD', 'Refund for visa application #69 - tes', '2025-11-19 09:33:48', 'visa_refund', 10, NULL, NULL, NULL),
(609, 2, 20, 'credit', 100.000, -20.000, 'USD', 'Updated JV Payment: Client DR SAHIB paid 100.000 USD to supplier KamAir. Updated by: Matiullah Rahimi. test', '2025-11-19 10:02:41', 'jv_payment', 4, '1213231', NULL, NULL),
(610, 2, 20, 'credit', 10.000, -10.000, 'USD', 'Client: DR SAHIB, Account funded by Matiullah Rahimi. Remarks: 0', '2025-11-19 10:54:32', 'fund', 7, '073126', 1.00000, NULL),
(611, 2, 20, 'debit', 150.000, -160.000, 'USD', 'Ticket booked for Mr AHMED ALI MUBARAK JAN with PNR: WKEPD1 from VKO to JED.', '2025-11-20 04:10:46', 'ticket_sale', 405, NULL, NULL, NULL),
(612, 2, 20, 'debit', 110.000, -270.000, 'USD', 'Ticket booked for Mr ATIQ ULLAH ZAMAN with PNR: SZQXJU from KBL  to ELQ.', '2025-11-20 05:25:58', 'ticket_sale', 406, NULL, NULL, NULL),
(613, 2, 20, 'debit', 110.000, -380.000, 'USD', 'Ticket booked for Mr IHSAMUDDIN ALIKHEL with PNR: SZQXJU from KBL  to ELQ.', '2025-11-20 05:25:58', 'ticket_sale', 407, NULL, NULL, NULL),
(615, 2, 21, 'credit', 20.000, 0.000, 'USD', 'Bulk cancellation for umrah booking #69 - Bulk cancel - processed 2 members', '2025-11-22 07:25:31', '', 69, NULL, NULL, NULL),
(616, 2, 20, 'credit', 150.000, -230.000, 'USD', 'Bulk cancellation for umrah booking #70 - Bulk cancel - processed 2 members', '2025-11-22 07:25:31', '', 70, NULL, NULL, NULL),
(618, 2, 21, '', 20.000, 0.000, 'USD', 'Cancel for umrah booking #69 - Bulk cancel - processed 2 members', '2025-11-22 07:34:18', '', 69, NULL, NULL, NULL),
(619, 2, 20, '', 150.000, -80.000, 'USD', 'Cancel for umrah booking #70 - Bulk cancel - processed 2 members', '2025-11-22 07:34:18', '', 70, NULL, NULL, NULL),
(620, 2, 21, '', 20.000, 0.000, 'USD', 'Reapply for umrah booking #69 - Bulk reapply - processed 2 members', '2025-11-22 07:34:38', '', 69, NULL, NULL, NULL),
(621, 2, 20, '', 150.000, -230.000, 'USD', 'Reapply for umrah booking #70 - Bulk reapply - processed 2 members', '2025-11-22 07:34:38', '', 70, NULL, NULL, NULL),
(624, 2, 21, 'debit', 20.000, 0.000, 'USD', 'Re-application reversal for visa #69 - test', '2025-11-24 06:01:49', '', 69, NULL, NULL, NULL),
(625, 2, 21, 'debit', 20.000, 0.000, 'USD', 'Re-application reversal for visa #69 - test', '2025-11-24 06:09:34', '', 69, NULL, NULL, NULL),
(626, 2, 21, 'debit', 20.000, 0.000, 'USD', 'Re-application reversal for visa #69 - test', '2025-11-24 06:12:26', '', 69, NULL, NULL, NULL),
(627, 2, 21, 'debit', 20.000, 0.000, 'USD', 'Cancellation reversal for visa application #69 - test', '2025-11-24 06:13:07', 'visa_cancellation', 69, NULL, NULL, NULL),
(628, 2, 21, 'debit', 20.000, 0.000, 'USD', 'Re-application reversal for visa #69 - test', '2025-11-24 06:14:07', '', 69, NULL, NULL, NULL),
(629, 2, 21, 'debit', 20.000, 0.000, 'USD', 'Cancellation reversal for visa application #69 - test1', '2025-11-24 06:17:35', 'visa_cancellation', 69, NULL, NULL, NULL),
(630, 2, 20, 'credit', 80.000, 0.000, 'USD', 'Client: DR SAHIB, Account funded by ROHULLAH SAFI. Remarks: test', '2025-12-06 06:38:52', 'fund', 19, '2452345', 1.00000, 2),
(631, 2, 21, 'debit', 120.000, -120.000, 'USD', 'Ticket booked for Mr guli with PNR: SZQXJU from KBL  to FRA.', '2025-12-06 08:23:48', 'ticket_sale', 408, NULL, NULL, 2),
(632, 2, 21, 'debit', 120.000, -120.000, 'USD', 'Ticket booked for Mr guli with PNR: SZQXJU from KBL  to FRA.', '2025-12-06 08:24:29', 'ticket_sale', 409, NULL, NULL, 2),
(633, 2, 21, 'debit', 120.000, -120.000, 'USD', 'Ticket booked for Mr guli with PNR: SZQXJU from KBL  to FRA.', '2025-12-06 08:26:44', 'ticket_sale', 410, NULL, NULL, 2),
(634, 2, 21, 'debit', 120.000, -120.000, 'USD', 'Ticket booked for Mr guli with PNR: SZQXJU from KBL  to FRA.', '2025-12-06 08:28:48', 'ticket_sale', 411, NULL, NULL, 2),
(635, 2, 21, 'debit', 120.000, -120.000, 'USD', 'Ticket booked for Mr guli with PNR: SZQXJU from KBL  to FRA.', '2025-12-06 08:29:20', 'ticket_sale', 412, NULL, NULL, 2),
(636, 2, 21, 'debit', 120.000, -120.000, 'USD', 'Ticket booked for Mr guli with PNR: SZQXJU from KBL  to FRA.', '2025-12-06 08:29:46', 'ticket_sale', 413, NULL, NULL, 2),
(637, 2, 21, 'debit', 20.000, -20.000, 'USD', 'Weight transaction: 10kg at 20 USD.', '2025-12-06 08:36:31', 'weight_sale', 70, NULL, NULL, 2),
(644, 2, 21, 'debit', 120.000, -120.000, 'USD', 'Ticket booked for Mr FAZALHAQ PARDES with PNR: RACSXS from VKO to LED.', '2025-12-06 09:57:22', 'ticket_sale', 420, NULL, NULL, 2),
(645, 2, 21, 'debit', 120.000, -120.000, 'USD', 'Ticket booked for Mr FAZALHAQ PARDES with PNR: 188JZ0 from  KBL to FRA.', '2025-12-06 10:47:52', 'ticket_sale', 421, NULL, NULL, 2);

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
  `status` enum('unread','read','replied') NOT NULL DEFAULT 'unread',
  `branch_id` bigint(20) DEFAULT NULL
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `creditors`
--

INSERT INTO `creditors` (`id`, `tenant_id`, `name`, `email`, `phone`, `address`, `balance`, `currency`, `status`, `created_at`, `branch_id`) VALUES
(16, 2, 'ROHULLAH SAFI', 'almuqadas_travel@yahoo.com', '0786011115', 'Jada-e-Maiwand', 4470.00, 'USD', 'active', '2025-11-19 10:03:58', 2),
(17, 2, 'ROHULLAH SAFI', 'almuqadas_travel@yahoo.com', '0786011115', 'Jada-e-Maiwand', 80.00, 'USD', 'active', '2025-12-06 07:14:50', 2);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `creditor_transactions`
--

INSERT INTO `creditor_transactions` (`id`, `tenant_id`, `creditor_id`, `amount`, `currency`, `transaction_type`, `description`, `payment_date`, `reference_number`, `created_at`, `branch_id`) VALUES
(20, 2, 16, 100.00, 'USD', 'debit', 'test', '2025-11-19', '1212121', '2025-11-19 10:04:12', NULL),
(21, 2, 16, 100.00, 'USD', 'debit', 'test', '2025-11-19', '1212121', '2025-11-19 10:04:32', NULL),
(22, 2, 16, 100.00, 'USD', 'debit', 'test', '2025-11-19', '1212121', '2025-11-19 10:04:38', NULL),
(23, 2, 16, 100.00, 'USD', 'debit', 'test', '2025-11-19', '1212121', '2025-11-19 10:04:45', NULL),
(24, 2, 16, 10.00, 'USD', 'debit', 'test', '2025-11-19', '123', '2025-11-19 10:28:02', NULL),
(25, 2, 16, 10.00, 'USD', 'debit', 'test', '2025-11-19', '123', '2025-11-19 10:28:09', NULL),
(26, 2, 16, 10.00, 'USD', 'debit', 'test', '2025-11-19', '123', '2025-11-19 10:28:17', NULL),
(27, 2, 16, 10.00, 'USD', 'debit', 'test', '2025-11-19', '123', '2025-11-19 10:28:26', NULL),
(28, 2, 16, 10.00, 'USD', 'debit', 'test', '2025-11-19', '123', '2025-11-19 10:28:36', NULL),
(29, 2, 16, 10.00, 'USD', 'debit', 'test', '2025-11-19', '1213231t', '2025-11-19 10:41:54', NULL),
(30, 2, 16, 10.00, 'USD', 'debit', 'test', '2025-11-19', '1213231t', '2025-11-19 10:42:02', NULL),
(31, 2, 16, 10.00, 'USD', 'debit', 'test', '2025-11-19', '1213231t', '2025-11-19 10:42:10', NULL),
(32, 2, 16, 10.00, 'USD', 'debit', 'test', '2025-11-19', '1213231t', '2025-11-19 10:42:18', NULL),
(33, 2, 16, 10.00, 'USD', 'debit', 'test', '2025-11-19', '1213231t', '2025-11-19 10:42:27', NULL),
(34, 2, 16, 10.00, 'USD', 'debit', 'test', '2025-11-19', '1212121', '2025-11-19 10:43:17', NULL),
(35, 2, 16, 10.00, 'USD', 'debit', 'test | Additional Remarks: This is a visa', '2025-11-19', '2452345', '2025-11-19 10:43:25', NULL),
(36, 2, 16, 10.00, 'USD', 'debit', 'test', '2025-12-06', '1212121', '2025-12-06 07:15:26', NULL),
(37, 2, 17, 10.00, 'USD', 'debit', 'test', '2025-12-06', '1212121', '2025-12-06 07:16:38', NULL),
(38, 2, 17, 10.00, 'USD', 'debit', 'test', '2025-12-06', '1212121', '2025-12-06 07:27:59', 2);

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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customers`
--

INSERT INTO `customers` (`id`, `tenant_id`, `name`, `email`, `phone`, `address`, `status`, `created_at`, `updated_at`, `branch_id`) VALUES
(6, 2, 'ROHULLAH SAFI', 'almuqadas_travel@yahoo.com', '0786011115', 'Jada-e-Maiwand', 'active', '2025-11-19 10:03:24', '2025-11-19 10:03:24', NULL),
(7, 2, 'ROHULLAH', 'almuqadas_travel@yahoo.com', '0786011115', 'Jada-e-Maiwand', 'active', '2025-11-19 10:11:10', '2025-11-19 10:11:10', NULL),
(8, 2, 'SAFI', 'almuqadas_travel@yahoo.com', '0786011115', 'Jada-e-Maiwand', 'active', '2025-11-19 10:16:49', '2025-11-19 10:16:49', NULL),
(9, 2, 'ROHULLAH SAFI', 'almuqadas_travel@yahoo.com', '0786011115', 'Jada-e-Maiwand', 'active', '2025-12-03 09:34:48', '2025-12-03 09:34:48', 2),
(10, 2, ' SAFI', 'almuqadas_travel@yahoo.com', '0786011115', 'Jada-e-Maiwand', 'active', '2025-12-03 09:45:11', '2025-12-03 09:45:11', 2),
(11, 2, 'ROHULLAH SAFI435', 'almuqadas_travel@yahoo.com', '0786011115', 'Jada-e-Maiwand', 'active', '2025-12-03 09:56:07', '2025-12-03 09:56:07', 2);

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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `customer_wallets`
--

INSERT INTO `customer_wallets` (`id`, `tenant_id`, `customer_id`, `currency`, `balance`, `created_at`, `updated_at`, `branch_id`) VALUES
(13, 2, 6, 'USD', 30.00, '2025-11-19 10:03:37', '2025-11-19 10:11:43', NULL),
(14, 2, 7, 'USD', 10.00, '2025-11-19 10:11:24', '2025-11-19 10:11:24', NULL),
(15, 2, 11, 'USD', 10.00, '2025-12-06 07:30:45', '2025-12-06 07:30:45', 2);

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
  `phone` varchar(50) DEFAULT NULL,
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
  `remarks` mediumtext DEFAULT NULL,
  `created_by` int(50) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `imported` tinyint(1) NOT NULL DEFAULT 0,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `date_change_tickets`
--

INSERT INTO `date_change_tickets` (`id`, `tenant_id`, `ticket_id`, `supplier`, `sold_to`, `paid_to`, `title`, `passenger_name`, `pnr`, `origin`, `destination`, `phone`, `airline`, `gender`, `issue_date`, `departure_date`, `currency`, `sold`, `base`, `supplier_penalty`, `service_penalty`, `status`, `remarks`, `created_by`, `created_at`, `updated_at`, `imported`, `branch_id`) VALUES
(51, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 11:56:08', '2025-11-25 10:16:26', 1, 2),
(52, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 12:00:40', '2025-11-25 10:16:29', 1, 2),
(53, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 12:04:15', '2025-11-25 10:16:31', 1, 2),
(54, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 12:31:28', '2025-11-25 10:16:33', 1, 2),
(55, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 12:52:29', '2025-11-25 10:16:35', 1, 2),
(56, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 12:54:20', '2025-11-25 10:16:38', 1, 2),
(57, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 12:56:40', '2025-11-25 10:16:41', 1, 2),
(58, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 12:57:19', '2025-11-25 10:16:43', 1, 2),
(59, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 13:02:30', '2025-11-25 10:16:45', 1, 2),
(60, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 13:05:44', '2025-11-25 10:16:47', 1, 2),
(61, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 13:06:20', '2025-11-25 10:16:48', 1, 2),
(76, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 13:31:27', '2025-11-25 10:16:50', 0, 2),
(77, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 13:34:22', '2025-11-25 10:16:51', 0, 2),
(78, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 13:35:14', '2025-11-25 10:16:53', 0, 2),
(79, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 13:37:31', '2025-11-25 10:16:55', 0, 2),
(80, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 13:59:16', '2025-11-25 10:16:57', 0, 2),
(81, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 14:05:07', '2025-11-25 10:17:00', 0, 2),
(82, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 14:05:32', '2025-11-25 10:17:01', 0, 2),
(83, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 14:09:22', '2025-11-25 10:17:03', 0, 2),
(90, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 14:43:00', '2025-11-25 10:17:05', 0, 2),
(91, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 14:44:07', '2025-11-25 10:17:07', 0, 2),
(92, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 14:44:40', '2025-11-25 10:17:09', 0, 2),
(93, 1, 341, '32', 19, 13, 'Mr', 'John Doe', 'ABC123', 'New York', 'London', '780310431', 'British Airways', 'Male', '2024-01-20', '2024-01-20', 'USD', 550.000, 500.000, 50.000, 25.000, 'Refunded', NULL, 1, '2025-11-17 14:58:16', '2025-11-25 10:17:11', 0, 2),
(97, 2, 413, '57', 21, 17, 'Mr', 'guli', 'SZQXJU', 'KBL ', 'FRA', '0771781576', 'A3', 'Male', '2025-12-06', '2025-12-06', 'USD', 120.000, 100.000, 20.000, 10.000, '', 'test', 19, '2025-12-06 13:05:45', '2025-12-06 13:05:45', 0, NULL);

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
  `tenant_id` int(11) NOT NULL,
  `branch_id` bigint(20) DEFAULT NULL
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `agreement_terms` mediumtext DEFAULT NULL,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `debtors`
--

INSERT INTO `debtors` (`id`, `tenant_id`, `name`, `email`, `phone`, `address`, `balance`, `currency`, `status`, `created_at`, `updated_at`, `main_account_id`, `agreement_terms`, `branch_id`) VALUES
(42, 2, ' SAFI', 'almuqadas_travel@yahoo.com', '0786011115', 'Jada-e-Maiwand', 0.00, 'USD', 'active', '2025-12-03 07:14:02', '2025-12-06 07:10:40', 17, '', 2),
(43, 2, 'ROHULLAH SAFI', 'almuqadas_travel@yahoo.com', '0786011115', 'Jada-e-Maiwand', 10.00, 'USD', 'active', '2025-12-06 06:46:37', '2025-12-06 07:04:49', 17, 'testttttt', 2),
(44, 2, 'ROHULLAH SAFI', 'almuqadas_travel@yahoo.com', '0786011115', 'Jada-e-Maiwand', 10.00, 'USD', 'active', '2025-12-06 07:00:33', '2025-12-06 07:00:33', 17, 'test', 2),
(45, 2, 'ROHULLAH SAFI', 'almuqadas_travel@yahoo.com', '0786011115', 'Jada-e-Maiwand', 10.00, 'USD', 'active', '2025-12-06 07:02:58', '2025-12-06 07:02:58', 17, 'testt', 2),
(46, 2, 'ROHULLAH SAFI', 'almuqadas_travel@yahoo.com', '0786011115', 'Jada-e-Maiwand', 10.00, 'USD', 'active', '2025-12-06 07:03:20', '2025-12-06 07:03:20', 17, 'testttttt', 2);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `debtor_transactions`
--

INSERT INTO `debtor_transactions` (`id`, `tenant_id`, `debtor_id`, `amount`, `currency`, `transaction_type`, `description`, `reference_number`, `payment_date`, `created_at`, `branch_id`) VALUES
(47, 2, 42, 50.00, 'USD', 'debit', 'Initial debt balance for  SAFI', 'DEBT-20251203071402-42', '2025-12-03', '2025-12-03 07:14:02', 2),
(48, 2, 42, 50.00, 'USD', 'credit', 'test', '', '2025-12-03', '2025-12-03 07:25:33', 2),
(49, 2, 43, 10.00, 'USD', 'debit', 'Initial debt balance for ROHULLAH SAFI', 'DEBT-20251206064637-43', '2025-12-06', '2025-12-06 06:46:00', 2),
(51, 2, 44, 10.00, 'USD', 'debit', 'Initial debt balance for ROHULLAH SAFI', 'DEBT-20251206070033-44', '2025-12-06', '2025-12-06 07:00:33', 2),
(52, 2, 45, 10.00, 'USD', 'debit', 'Initial debt balance for ROHULLAH SAFI', 'DEBT-20251206070258-45', '2025-12-06', '2025-12-06 07:02:58', 2),
(53, 2, 46, 10.00, 'USD', 'debit', 'Initial debt balance for ROHULLAH SAFI', 'DEBT-20251206070320-46', '2025-12-06', '2025-12-06 07:03:20', 2);

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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `demo_requests`
--

INSERT INTO `demo_requests` (`id`, `name`, `email`, `company`, `phone`, `company_size`, `preferred_date`, `preferred_time`, `message`, `status`, `created_at`, `updated_at`, `branch_id`) VALUES
(1, 'KamAir', 'almuqadas_travel@yahoo.com', 'MTech', '0777305730', '11-50', '2025-10-04', '14:00:00', 'gsfdgsd', 'contacted', '2025-10-04 05:45:57', '2025-10-04 06:06:40', NULL);

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_tracking`
--

CREATE TABLE `email_tracking` (
  `id` int(11) NOT NULL,
  `email_id` varchar(255) NOT NULL,
  `recipient_email` varchar(255) NOT NULL,
  `email_type` varchar(100) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `opened_at` timestamp NULL DEFAULT NULL,
  `opened` tinyint(1) DEFAULT 0,
  `user_agent` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `email_tracking`
--

INSERT INTO `email_tracking` (`id`, `email_id`, `recipient_email`, `email_type`, `tenant_id`, `sent_at`, `opened_at`, `opened`, `user_agent`, `ip_address`, `branch_id`) VALUES
(1, 'email_691c518930b8e2.38733259', 'fluentcenter01@gmail.com', 'ticket_notification', 2, '2025-11-18 10:59:26', NULL, 0, NULL, NULL, 2),
(2, 'email_691d4139488b08.28883902', 'fluentcenter01@gmail.com', 'ticket_notification_with_pdf', 2, '2025-11-19 04:02:01', NULL, 0, NULL, NULL, 2),
(3, 'email_691d446b8d1b79.05461753', 'fluentcenter01@gmail.com', 'ticket_notification_with_pdf', 2, '2025-11-19 04:15:39', NULL, 0, NULL, NULL, 2),
(4, 'email_691d5405447248.00105462', 'fluentcenter01@gmail.com', 'ticket_notification', 2, '2025-11-19 05:22:17', NULL, 0, NULL, NULL, 2),
(5, 'email_691d551cb8ae45.28996790', 'fluentcenter01@gmail.com', 'ticket_notification', 2, '2025-11-19 05:26:57', NULL, 0, NULL, NULL, 2),
(6, 'email_691d5dd3b95188.82715818', 'admin@abc-construction.com', 'ticket_notification', 1, '2025-11-19 06:04:12', NULL, 0, NULL, NULL, 2),
(7, 'email_691d620d8c3438.90952898', 'esmati@gmail.com', 'ticket_notification', 2, '2025-11-19 06:22:13', NULL, 0, NULL, NULL, 2),
(8, 'email_691d63789decc5.71905792', 'esmati@gmail.com', 'ticket_notification', 2, '2025-11-19 06:28:16', NULL, 0, NULL, NULL, 2),
(9, 'email_691d63a7ed98c7.43330092', 'esmati@gmail.com', 'ticket_notification', 2, '2025-11-19 06:28:58', NULL, 0, NULL, NULL, 2),
(10, 'email_691d64bcb80cb1.11794620', 'esmati@gmail.com', 'ticket_notification', 2, '2025-11-19 06:33:36', NULL, 0, NULL, NULL, 2),
(11, 'email_691d6bdce86f23.64002463', 'fluentcenter01@gmail.com', 'ticket_notification', 2, '2025-11-19 07:04:06', NULL, 0, NULL, NULL, 2),
(12, 'email_691d961c682cd9.04267805', 'almuqadas_travel@yahoo.com', 'account_notification', 2, '2025-11-19 10:04:16', NULL, 0, NULL, NULL, 2),
(13, 'email_691d9630543db4.46355041', 'almuqadas_travel@yahoo.com', 'account_notification', 2, '2025-11-19 10:04:38', NULL, 0, NULL, NULL, 2),
(14, 'email_691d9636f2f584.46424237', 'almuqadas_travel@yahoo.com', 'account_notification', 2, '2025-11-19 10:04:45', NULL, 0, NULL, NULL, 2),
(15, 'email_691d963d7319b3.15936090', 'almuqadas_travel@yahoo.com', 'account_notification', 2, '2025-11-19 10:04:49', NULL, 0, NULL, NULL, 2),
(16, 'email_691d9bb2086f44.02400007', 'almuqadas_travel@yahoo.com', 'account_notification', 2, '2025-11-19 10:28:09', NULL, 0, NULL, NULL, 2),
(17, 'email_691d9bb9f0eb59.28547604', 'almuqadas_travel@yahoo.com', 'account_notification', 2, '2025-11-19 10:28:17', NULL, 0, NULL, NULL, 2),
(18, 'email_691d9bc1cef2d3.56154666', 'almuqadas_travel@yahoo.com', 'account_notification', 2, '2025-11-19 10:28:26', NULL, 0, NULL, NULL, 2),
(19, 'email_691d9bcadbe3e1.84555494', 'almuqadas_travel@yahoo.com', 'account_notification', 2, '2025-11-19 10:28:36', NULL, 0, NULL, NULL, 2),
(20, 'email_691d9bd4378585.69127782', 'almuqadas_travel@yahoo.com', 'account_notification', 2, '2025-11-19 10:28:44', NULL, 0, NULL, NULL, 2),
(21, 'email_691d9ef2d7d7f5.67891304', 'almuqadas_travel@yahoo.com', 'account_notification', 2, '2025-11-19 10:42:02', NULL, 0, NULL, NULL, 2),
(22, 'email_691d9efaa20c50.27047660', 'almuqadas_travel@yahoo.com', 'account_notification', 2, '2025-11-19 10:42:10', NULL, 0, NULL, NULL, 2),
(23, 'email_691d9f02636718.91406998', 'almuqadas_travel@yahoo.com', 'account_notification', 2, '2025-11-19 10:42:18', NULL, 0, NULL, NULL, 2),
(24, 'email_691d9f0aa629a2.78722786', 'almuqadas_travel@yahoo.com', 'account_notification', 2, '2025-11-19 10:42:26', NULL, 0, NULL, NULL, 2),
(25, 'email_691d9f130ac1b9.28702407', 'almuqadas_travel@yahoo.com', 'account_notification', 2, '2025-11-19 10:42:36', NULL, 0, NULL, NULL, 2),
(26, 'email_691d9f45d19579.96330500', 'almuqadas_travel@yahoo.com', 'account_notification', 2, '2025-11-19 10:43:25', NULL, 0, NULL, NULL, NULL),
(27, 'email_691d9f4d8c79a0.65053931', 'almuqadas_travel@yahoo.com', 'account_notification', 2, '2025-11-19 10:43:33', NULL, 0, NULL, NULL, NULL),
(28, 'email_691e94cfb50780.95833572', 'fluentcenter01@gmail.com', 'ticket_notification_with_pdf', 2, '2025-11-20 04:10:55', NULL, 0, NULL, NULL, NULL),
(29, 'email_691ea66e7dcea7.31914543', 'fluentcenter01@gmail.com', 'ticket_notification_with_pdf', 2, '2025-11-20 05:26:06', NULL, 0, NULL, NULL, NULL),
(30, 'email_691eeafe102922.33136675', 'fluentcenter01@gmail.com', 'umrah_notification', 2, '2025-11-20 10:18:46', NULL, 0, NULL, NULL, NULL),
(31, 'email_691efceb2c77a1.05336628', 'fluentcenter01@gmail.com', 'salary_payment_notification', 2, '2025-11-20 11:35:16', NULL, 0, NULL, NULL, NULL),
(32, 'email_691efe5a859361.24046912', 'fluentcenter01@gmail.com', 'salary_advance_notification', 2, '2025-11-20 11:41:23', NULL, 0, NULL, NULL, NULL),
(33, 'email_6923eaa1bf0bb3.47161554', 'fluentcenter01@gmail.com', 'umrah_notification', 2, '2025-11-24 05:18:30', NULL, 0, NULL, NULL, NULL),
(34, 'email_692fe5ed4e1e06.42190955', 'almuqadas_travel@yahoo.com', 'account_notification', 2, '2025-12-03 07:25:42', NULL, 0, NULL, NULL, NULL),
(35, 'email_6933d3db7d1a27.66350061', 'almuqadas_travel@yahoo.com', 'account_notification', 2, '2025-12-06 06:57:35', NULL, 0, NULL, NULL, NULL),
(36, 'email_6933d80ed16877.14026308', 'almuqadas_travel@yahoo.com', 'account_notification', 2, '2025-12-06 07:15:31', NULL, 0, NULL, NULL, NULL),
(37, 'email_6933d85633b553.08002819', 'almuqadas_travel@yahoo.com', 'account_notification', 2, '2025-12-06 07:16:41', NULL, 0, NULL, NULL, NULL),
(38, 'email_6933daff50a800.69336273', 'almuqadas_travel@yahoo.com', 'account_notification', 2, '2025-12-06 07:28:02', NULL, 0, NULL, NULL, NULL),
(39, 'email_6933e981100317.30963229', 'esmati@gmail.com', 'ticket_notification_with_pdf', 2, '2025-12-06 08:29:53', NULL, 0, NULL, NULL, NULL),
(40, 'email_6933f215b5b511.94672006', 'esmati@gmail.com', 'ticket_notification_with_pdf', 2, '2025-12-06 09:06:29', NULL, 0, NULL, NULL, NULL),
(41, 'email_6933f374918cc1.33144162', 'esmati@gmail.com', 'ticket_notification_with_pdf', 2, '2025-12-06 09:12:20', NULL, 0, NULL, NULL, NULL),
(42, 'email_6933fb2fe7a3d9.59373140', 'esmati@gmail.com', 'ticket_notification_with_pdf', 2, '2025-12-06 09:45:19', NULL, 0, NULL, NULL, NULL),
(43, 'email_6933fe080a1642.17196105', 'esmati@gmail.com', 'ticket_notification_with_pdf', 2, '2025-12-06 09:57:28', NULL, 0, NULL, NULL, NULL),
(44, 'email_693409dd5bcf13.42128357', 'esmati@gmail.com', 'ticket_notification_with_pdf', 2, '2025-12-06 10:47:57', NULL, 0, NULL, NULL, NULL);

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `receipt_file` varchar(50) DEFAULT NULL,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expenses`
--

INSERT INTO `expenses` (`id`, `tenant_id`, `category_id`, `main_account_id`, `date`, `description`, `amount`, `currency`, `created_at`, `allocation_id`, `receipt_file`, `branch_id`) VALUES
(218, 2, 23, 17, '2025-11-19', 'test', 10.000, 'USD', '2025-11-19 10:01:20', NULL, NULL, 2),
(219, 2, 24, 17, '2025-12-04', 'this is for test', 100.000, 'USD', '2025-12-04 08:36:47', NULL, NULL, 2);

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `expense_categories`
--

INSERT INTO `expense_categories` (`id`, `tenant_id`, `name`, `created_at`, `branch_id`) VALUES
(19, 1, 'OFFICE EXPS', '2025-09-01 11:17:05', 2),
(22, 1, 'self expenses', '2025-10-16 07:10:13', 2),
(23, 2, 'Office', '2025-11-19 10:01:01', 2),
(24, 2, 'OFFICE EXPS', '2025-12-04 08:36:14', 2);

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `families`
--

INSERT INTO `families` (`family_id`, `tenant_id`, `head_of_family`, `contact`, `address`, `province`, `district`, `total_members`, `package_type`, `location`, `tazmin`, `visa_status`, `total_price`, `total_paid`, `total_paid_to_bank`, `total_due`, `created_by`, `created_at`, `updated_at`, `branch_id`) VALUES
(16, 1, 'ali', '780310431', 'test', 'test', 'test', 0, 'all', 'tet', 'done', 'Applied', 0.00, 0.00, 0.00, 0.00, 1, '2025-11-17 09:52:48', '2025-11-17 11:18:11', NULL),
(17, 2, 'HAMEED', '0786011115', 'Jada-e-Maiwand', 'Kabul', 'dehsabz', 3, 'Full Package', 'Madina and Makkah', 'Done', 'Not Applied', 190.00, 20.00, 0.00, 160.00, 0, '2025-11-19 06:27:18', '2025-11-25 05:48:58', 2);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `remarks` varchar(255) DEFAULT NULL,
  `branch_id` bigint(20) DEFAULT NULL
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
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `general_ledger`
--

INSERT INTO `general_ledger` (`id`, `tenant_id`, `transaction_id`, `account_type`, `entry_type`, `amount`, `currency`, `balance`, `created_at`, `branch_id`) VALUES
(3, 1, NULL, 'income', 'credit', 5.00, 'USD', 5.00, '2025-09-01 06:51:45', NULL),
(4, 1, NULL, 'income', 'credit', 5.00, 'USD', 10.00, '2025-09-01 08:38:24', NULL),
(5, 2, 34, 'asset', 'credit', 1000.00, 'USD', 1000.00, '2025-09-10 12:02:43', NULL),
(6, 1, NULL, 'income', 'credit', 2.00, 'USD', 12.00, '2025-09-22 11:40:32', NULL);

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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `created_by` int(50) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','refunded') NOT NULL DEFAULT 'active',
  `imported` tinyint(1) NOT NULL DEFAULT 0,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hotel_bookings`
--

INSERT INTO `hotel_bookings` (`id`, `tenant_id`, `title`, `first_name`, `last_name`, `gender`, `order_id`, `check_in_date`, `check_out_date`, `accommodation_details`, `issue_date`, `supplier_id`, `sold_to`, `paid_to`, `contact_no`, `base_amount`, `sold_amount`, `profit`, `currency`, `remarks`, `created_by`, `created_at`, `updated_at`, `status`, `imported`, `branch_id`) VALUES
(54, 2, 'Mr', 'ROHULLAH', 'SAFI', 'Male', '1089734', '2025-11-19', '2025-11-28', 'test', '2025-11-19', 57, '20', 17, '786011115', 10.000, 20.000, 10.000, 'USD', 'test', 19, '2025-11-19 05:22:13', '2025-11-25 07:37:12', 'active', 0, 2),
(55, 2, 'Mr', 'ROHULLAH', 'SAFI', 'Male', '12200', '2025-11-19', '2025-11-22', 'test', '2025-11-19', 57, '20', 17, '786011115', 20.000, 30.000, 10.000, 'USD', 'test', 19, '2025-11-19 05:26:52', '2025-11-25 07:37:16', 'active', 0, 2),
(56, 1, 'Mr', 'ROHULLAH', 'SAFI', 'Male', '45625', '2025-11-19', '2025-11-28', 'ytest', '2025-11-19', 37, '18', 13, '786011115', 40.000, 50.000, 10.000, 'USD', 'test', 1, '2025-11-19 06:04:03', '2025-11-25 05:50:37', 'active', 0, 1),
(57, 2, 'Mr', 'ROHULLAH', 'SAFI', 'Male', 'adfadsf', '2025-11-19', '2025-11-21', 'test', '2025-11-19', 57, '21', 17, '786011115', 15.000, 30.000, 15.000, 'USD', 'tets', 19, '2025-11-19 06:22:05', '2025-11-25 07:37:21', 'active', 0, 2),
(58, 2, 'Mr', 'ROHULLAH', 'SAFI', 'Male', '34212', '2025-11-20', '2025-11-27', 'test', '2025-11-19', 57, '21', 17, '786011115', 40.000, 50.000, 10.000, 'USD', 'test', 19, '2025-11-19 06:33:32', '2025-11-25 07:37:49', 'active', 0, 2);

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `hotel_refunds`
--

INSERT INTO `hotel_refunds` (`id`, `tenant_id`, `booking_id`, `refund_type`, `refund_amount`, `reason`, `currency`, `exchange_rate`, `processed`, `processed_by`, `transaction_id`, `created_at`, `updated_at`, `branch_id`) VALUES
(16, 2, 58, 'full', 50.00, 'test', 'USD', 1.0000, 0, NULL, NULL, '2025-11-19 09:30:00', '2025-11-25 05:50:53', 2);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jv_payments`
--

INSERT INTO `jv_payments` (`id`, `tenant_id`, `jv_name`, `exchange_rate`, `total_amount`, `currency`, `receipt`, `remarks`, `client_id`, `supplier_id`, `created_by`, `created_at`, `updated_at`, `branch_id`) VALUES
(6, 2, 'Client-Supplier Payment', 1.00000, 100.000, 'USD', '1213231', 'test', 20, 57, 7, '2025-11-19 10:02:41', '2025-11-25 05:52:50', 2);

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `jv_transactions`
--

INSERT INTO `jv_transactions` (`id`, `tenant_id`, `jv_payment_id`, `transaction_type`, `amount`, `balance`, `currency`, `description`, `reference_id`, `receipt`, `created_at`, `updated_at`, `branch_id`) VALUES
(4, 2, 6, 'Transfer', 100.000, 100.000, 'USD', NULL, 20, '1213231', '2025-11-19 10:02:41', '2025-11-19 10:02:56', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `email` varchar(255) NOT NULL,
  `time` datetime NOT NULL,
  `ip_address` varchar(50) NOT NULL,
  `branch_id` bigint(20) DEFAULT NULL
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
  `action_time` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `login_history`
--

INSERT INTO `login_history` (`id`, `tenant_id`, `user_id`, `action`, `action_time`, `branch_id`) VALUES
(116, 1, 1, 'logout', '2025-10-21 09:28:31', NULL),
(117, 1, 1, 'logout', '2025-10-29 11:59:03', NULL),
(118, 1, 1, 'logout', '2025-11-01 09:54:02', NULL),
(119, 1, 1, 'logout', '2025-11-16 04:38:53', NULL),
(120, 1, 1, 'logout', '2025-11-16 04:39:36', NULL),
(121, 1, 1, 'logout', '2025-11-16 06:03:28', NULL),
(122, 1, 1, 'logout', '2025-11-16 10:24:44', NULL),
(123, 1, 1, 'logout', '2025-11-16 10:27:58', NULL),
(124, 1, 6, 'logout', '2025-11-16 10:28:33', NULL),
(125, 1, 18, 'logout', '2025-11-16 10:29:07', NULL),
(126, 2, 7, 'logout', '2025-11-16 11:05:06', NULL),
(127, 1, 1, 'logout', '2025-11-16 11:06:10', NULL),
(128, 2, 7, 'logout', '2025-11-16 11:07:40', NULL),
(129, 2, 7, 'logout', '2025-11-16 11:08:41', NULL),
(130, 2, 7, 'logout', '2025-11-16 11:09:36', NULL),
(131, 1, 18, 'logout', '2025-11-16 11:10:24', NULL),
(132, 1, 6, 'logout', '2025-11-16 11:10:41', NULL),
(133, 1, 1, 'logout', '2025-11-18 10:53:58', NULL),
(134, 2, 7, 'logout', '2025-11-19 06:03:11', NULL),
(135, 1, 1, 'logout', '2025-11-19 06:12:56', NULL),
(136, 1, 1, 'logout', '2025-11-22 06:06:26', NULL),
(137, 1, 6, 'logout', '2025-11-22 06:06:54', NULL),
(138, 2, 7, 'logout', '2025-11-24 07:46:06', NULL),
(139, 1, 1, 'logout', '2025-11-24 07:51:49', NULL),
(140, 2, 19, 'logout', '2025-11-24 10:15:59', NULL);

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
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `main_account`
--

INSERT INTO `main_account` (`id`, `tenant_id`, `name`, `account_type`, `bank_account_number`, `bank_account_afs_number`, `bank_name`, `usd_balance`, `afs_balance`, `euro_balance`, `darham_balance`, `last_updated`, `status`, `branch_id`) VALUES
(13, 1, 'SELF BANK (SAFE)', 'internal', NULL, NULL, NULL, 7251.300, 533185.000, 450.000, 505.000, '2025-11-25 10:23:06', 'active', 1),
(14, 1, 'AZIZI BANK', 'bank', NULL, '143241234', 'AZIZI', 123.000, 3375.000, 0.000, 0.000, '2025-11-25 10:23:09', 'active', 1),
(16, 1, 'AFGHAN UNITED BANK', 'bank', '23451534', '3254524afs453', 'AUB', 648.000, 26700.000, 0.000, 0.000, '2025-11-25 10:23:11', 'active', 1),
(17, 2, 'SELF BANK (SAFE)', 'internal', NULL, NULL, NULL, 5400.000, 99000.000, 0.000, 0.000, '2025-12-06 16:10:29', 'active', 2),
(18, 2, 'AZIZI BANK', 'bank', '23451534', '', 'AZIZI', 300.000, 0.000, 0.000, 0.000, '2025-12-06 11:05:46', 'inactive', 2);

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
  `exchange_rate` decimal(10,5) DEFAULT NULL,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `main_account_transactions`
--

INSERT INTO `main_account_transactions` (`id`, `tenant_id`, `main_account_id`, `type`, `amount`, `balance`, `currency`, `description`, `created_at`, `transaction_of`, `reference_id`, `receipt`, `exchange_rate`, `branch_id`) VALUES
(1255, 2, 17, 'credit', 10.000, 20.000, 'USD', 'tes', '2025-11-19 06:32:23', 'hotel', 57, NULL, NULL, NULL),
(1256, 2, 17, 'credit', 10.000, 30.000, 'USD', 'test', '2025-11-19 06:32:54', 'hotel', 57, NULL, NULL, NULL),
(1259, 2, 17, 'credit', 10.000, 40.000, 'USD', 'test', '2025-11-19 07:19:28', 'ticket_sale', 404, NULL, NULL, NULL),
(1260, 2, 17, 'credit', 10.000, 50.000, 'USD', 'test', '2025-11-19 07:19:41', 'ticket_sale', 404, NULL, NULL, NULL),
(1261, 2, 17, 'credit', 10.000, 60.000, 'USD', 'test', '2025-11-19 07:22:38', 'ticket_sale', 404, NULL, NULL, NULL),
(1262, 2, 17, 'credit', 10.000, 70.000, 'USD', 'tets', '2025-11-19 07:27:21', 'ticket_sale', 404, NULL, NULL, NULL),
(1263, 2, 17, 'credit', 10.000, 80.000, 'USD', 'test', '2025-11-19 07:32:12', 'ticket_sale', 404, NULL, NULL, NULL),
(1264, 2, 17, 'credit', 10.000, 90.000, 'USD', 'test', '2025-11-19 07:33:28', 'ticket_sale', 404, NULL, NULL, NULL),
(1265, 2, 17, 'credit', 10.000, 100.000, 'USD', 'test', '2025-11-19 07:35:54', 'ticket_sale', 404, NULL, NULL, NULL),
(1266, 2, 17, 'credit', 10.000, 110.000, 'USD', 'test', '2025-11-19 07:35:54', 'ticket_sale', 404, NULL, NULL, NULL),
(1267, 2, 17, 'credit', 10.000, 120.000, 'USD', 'test', '2025-11-19 07:37:47', 'ticket_sale', 404, NULL, NULL, NULL),
(1268, 2, 17, 'credit', 10.000, 130.000, 'USD', 'test', '2025-11-19 07:37:47', 'ticket_sale', 404, NULL, NULL, NULL),
(1269, 2, 17, 'credit', 10.000, 140.000, 'USD', 'test', '2025-11-19 07:37:47', 'ticket_sale', 404, NULL, NULL, NULL),
(1270, 2, 17, 'credit', 10.000, 150.000, 'USD', 'test', '2025-11-19 07:37:47', 'ticket_sale', 404, NULL, NULL, NULL),
(1271, 2, 17, 'credit', 10.000, 160.000, 'USD', 'test', '2025-11-19 07:40:38', 'ticket_sale', 404, NULL, NULL, NULL),
(1272, 2, 17, 'credit', 10.000, 170.000, 'USD', 'test', '2025-11-19 07:40:38', 'ticket_sale', 404, NULL, NULL, NULL),
(1273, 2, 17, 'credit', 10.000, 180.000, 'USD', 'test', '2025-11-19 07:40:38', 'ticket_sale', 404, NULL, NULL, NULL),
(1274, 2, 17, 'credit', 10.000, 190.000, 'USD', 'test', '2025-11-19 07:40:38', 'ticket_sale', 404, NULL, NULL, NULL),
(1275, 2, 17, 'credit', 10.000, 200.000, 'USD', 'test', '2025-11-19 07:46:29', 'ticket_sale', 404, NULL, NULL, NULL),
(1276, 2, 17, 'credit', 10.000, 210.000, 'USD', 'tets', '2025-11-19 07:59:07', 'ticket_sale', 404, NULL, NULL, NULL),
(1278, 2, 17, 'debit', 10.000, 200.000, 'USD', 'test', '2025-11-19 08:03:43', 'ticket_refund', 142, NULL, 0.00000, NULL),
(1279, 2, 17, 'debit', 10.000, 190.000, 'USD', 'test', '2025-11-19 08:03:43', 'ticket_refund', 142, NULL, 0.00000, NULL),
(1280, 2, 17, 'debit', 10.000, 180.000, 'USD', 'test', '2025-11-19 08:03:43', 'ticket_refund', 142, NULL, 0.00000, NULL),
(1281, 2, 17, 'debit', 10.000, 170.000, 'USD', 'test', '2025-11-19 08:03:43', 'ticket_refund', 142, NULL, 0.00000, NULL),
(1282, 2, 17, 'debit', 10.000, 160.000, 'USD', 'test', '2025-11-19 08:03:43', 'ticket_refund', 142, NULL, 0.00000, NULL),
(1283, 2, 17, 'debit', 10.000, 150.000, 'USD', 'test', '2025-11-19 08:03:43', 'ticket_refund', 142, NULL, 0.00000, NULL),
(1284, 2, 17, 'debit', 10.000, 140.000, 'USD', 'test', '2025-11-19 08:03:43', 'ticket_refund', 142, NULL, 0.00000, NULL),
(1285, 2, 17, 'debit', 10.000, 130.000, 'USD', 'test', '2025-11-19 08:03:43', 'ticket_refund', 142, NULL, 0.00000, NULL),
(1286, 2, 17, 'debit', 10.000, 120.000, 'USD', 'test', '2025-11-19 08:03:43', 'ticket_refund', 142, NULL, 0.00000, NULL),
(1287, 2, 17, 'debit', 10.000, 110.000, 'USD', 'test', '2025-11-19 08:03:43', 'ticket_refund', 142, NULL, 0.00000, NULL),
(1288, 2, 17, 'debit', 10.000, 100.000, 'USD', 'test', '2025-11-19 08:03:43', 'ticket_refund', 142, NULL, 0.00000, NULL),
(1290, 2, 17, 'credit', 10.000, 110.000, 'USD', 'test', '2025-11-19 08:11:44', 'date_change', 95, NULL, NULL, NULL),
(1291, 2, 17, 'credit', 10.000, 120.000, 'USD', 'test', '2025-11-19 08:11:44', 'date_change', 95, NULL, 0.00000, NULL),
(1292, 2, 17, 'credit', 10.000, 130.000, 'USD', 'test', '2025-11-19 08:11:44', 'date_change', 95, NULL, 0.00000, NULL),
(1293, 2, 17, 'credit', 10.000, 140.000, 'USD', 'test', '2025-11-19 08:15:01', 'date_change', 95, NULL, 0.00000, NULL),
(1294, 2, 17, 'credit', 10.000, 150.000, 'USD', 'test', '2025-11-19 08:15:01', 'date_change', 95, NULL, 0.00000, NULL),
(1295, 2, 17, 'credit', 10.000, 160.000, 'USD', 'test', '2025-11-19 08:15:01', 'date_change', 95, NULL, 0.00000, NULL),
(1296, 2, 17, 'credit', 10.000, 170.000, 'USD', 'test', '2025-11-19 08:15:01', 'date_change', 95, NULL, 0.00000, NULL),
(1297, 2, 17, 'credit', 10.000, 180.000, 'USD', 'test', '2025-11-19 08:15:01', 'date_change', 95, NULL, 0.00000, NULL),
(1298, 2, 17, 'credit', 10.000, 190.000, 'USD', 'test', '2025-11-19 08:38:06', 'date_change', 95, NULL, 0.00000, NULL),
(1299, 2, 17, 'credit', 10.000, 200.000, 'USD', 'test', '2025-11-19 08:38:32', 'date_change', 95, NULL, 0.00000, NULL),
(1300, 2, 17, 'credit', 10.000, 10.000, 'USD', 'test', '2025-11-19 00:00:00', 'weight', 69, NULL, NULL, NULL),
(1301, 2, 17, 'credit', 10.000, 210.000, 'USD', 'test', '2025-11-19 08:43:24', 'hotel', 58, NULL, NULL, NULL),
(1302, 2, 17, 'credit', 10.000, 220.000, 'USD', 'test', '2025-11-19 08:43:24', 'hotel', 58, NULL, NULL, NULL),
(1303, 2, 17, 'credit', 10.000, 230.000, 'USD', 'test', '2025-11-19 08:43:25', 'hotel', 58, NULL, NULL, NULL),
(1304, 2, 17, 'credit', 10.000, 240.000, 'USD', 'test', '2025-11-19 08:43:25', 'hotel', 58, NULL, NULL, NULL),
(1305, 2, 17, 'credit', 10.000, 250.000, 'USD', 'test', '2025-11-19 08:47:48', 'hotel', 58, NULL, NULL, NULL),
(1306, 2, 17, 'credit', 10.000, 260.000, 'USD', 'test', '2025-11-19 08:47:48', 'hotel', 58, NULL, NULL, NULL),
(1307, 2, 17, 'credit', 10.000, 270.000, 'USD', 'test', '2025-11-19 08:47:48', 'hotel', 58, NULL, NULL, NULL),
(1308, 2, 17, 'credit', 10.000, 280.000, 'USD', 'test', '2025-11-19 08:47:49', 'hotel', 58, NULL, NULL, NULL),
(1309, 2, 17, 'credit', 10.000, 290.000, 'USD', 'test', '2025-11-19 08:47:49', 'hotel', 58, NULL, NULL, NULL),
(1310, 2, 17, 'credit', 10.000, 300.000, 'USD', 'test', '2025-11-19 08:47:49', 'hotel', 58, NULL, NULL, NULL),
(1311, 2, 17, 'credit', 10.000, 310.000, 'USD', 'test', '2025-11-19 08:49:43', 'hotel', 58, NULL, NULL, NULL),
(1312, 2, 17, 'credit', 10.000, 320.000, 'USD', 'test', '2025-11-19 08:50:00', 'hotel', 58, NULL, NULL, NULL),
(1313, 2, 17, 'credit', 10.000, 330.000, 'USD', 'test', '2025-11-19 08:50:00', 'hotel', 58, NULL, NULL, NULL),
(1314, 2, 17, 'credit', 10.000, 340.000, 'USD', 'test', '2025-11-19 08:50:00', 'hotel', 58, NULL, NULL, NULL),
(1315, 2, 17, 'credit', 10.000, 350.000, 'USD', 'test', '2025-11-19 08:50:00', 'hotel', 58, NULL, NULL, NULL),
(1316, 2, 17, 'credit', 10.000, 360.000, 'USD', 'test', '2025-11-19 08:50:00', 'hotel', 58, NULL, NULL, NULL),
(1317, 2, 17, 'credit', 10.000, 370.000, 'USD', 'test', '2025-11-19 08:50:01', 'hotel', 58, NULL, NULL, NULL),
(1318, 2, 17, 'credit', 10.000, 380.000, 'USD', 'test', '2025-11-19 08:50:02', 'hotel', 58, NULL, NULL, NULL),
(1319, 2, 17, 'credit', 10.000, 390.000, 'USD', 'test', '2025-11-19 08:50:02', 'hotel', 58, NULL, NULL, NULL),
(1320, 2, 17, 'credit', 10.000, 400.000, 'USD', 'test', '2025-11-19 08:50:02', 'hotel', 58, NULL, NULL, NULL),
(1321, 2, 17, 'credit', 10.000, 410.000, 'USD', 'test', '2025-11-19 08:50:02', 'hotel', 58, NULL, NULL, NULL),
(1322, 2, 17, 'credit', 10.000, 420.000, 'USD', 'test', '2025-11-19 08:50:02', 'hotel', 58, NULL, NULL, NULL),
(1323, 2, 17, 'credit', 10.000, 430.000, 'USD', 'test', '2025-11-19 08:51:25', 'hotel', 58, NULL, NULL, NULL),
(1324, 2, 17, 'credit', 10.000, 440.000, 'USD', 'test', '2025-11-19 08:51:25', 'hotel', 58, NULL, NULL, NULL),
(1325, 2, 17, 'credit', 10.000, 450.000, 'USD', 'test', '2025-11-19 08:51:25', 'hotel', 58, NULL, NULL, NULL),
(1326, 2, 17, 'credit', 10.000, 460.000, 'USD', 'test', '2025-11-19 08:51:25', 'hotel', 58, NULL, NULL, NULL),
(1327, 2, 17, 'credit', 10.000, 470.000, 'USD', 'test', '2025-11-19 08:51:25', 'hotel', 58, NULL, NULL, NULL),
(1328, 2, 17, 'credit', 10.000, 480.000, 'USD', 'test', '2025-11-19 08:51:26', 'hotel', 58, NULL, NULL, NULL),
(1329, 2, 17, 'credit', 10.000, 490.000, 'USD', 'test', '2025-11-19 08:51:27', 'hotel', 58, NULL, NULL, NULL),
(1330, 2, 17, 'credit', 10.000, 500.000, 'USD', 'test', '2025-11-19 08:51:27', 'hotel', 58, NULL, NULL, NULL),
(1331, 2, 17, 'credit', 10.000, 510.000, 'USD', 'test', '2025-11-19 08:51:27', 'hotel', 58, NULL, NULL, NULL),
(1332, 2, 17, 'credit', 10.000, 520.000, 'USD', 'test', '2025-11-19 08:51:27', 'hotel', 58, NULL, NULL, NULL),
(1333, 2, 17, 'credit', 10.000, 530.000, 'USD', 'test', '2025-11-19 08:53:24', 'hotel', 58, NULL, NULL, NULL),
(1334, 2, 17, 'credit', 10.000, 540.000, 'USD', 'test', '2025-11-19 08:53:43', 'hotel', 58, NULL, NULL, NULL),
(1335, 2, 17, 'credit', 10.000, 550.000, 'USD', 'test', '2025-11-19 08:53:43', 'hotel', 58, NULL, NULL, NULL),
(1336, 2, 17, 'credit', 10.000, 560.000, 'USD', 'test', '2025-11-19 08:53:43', 'hotel', 58, NULL, NULL, NULL),
(1337, 2, 17, 'credit', 10.000, 570.000, 'USD', 'test', '2025-11-19 08:53:44', 'hotel', 58, NULL, NULL, NULL),
(1338, 2, 17, 'credit', 10.000, 580.000, 'USD', 'test', '2025-11-19 08:53:44', 'hotel', 58, NULL, NULL, NULL),
(1339, 2, 17, 'credit', 10.000, 590.000, 'USD', 'test', '2025-11-19 08:53:44', 'hotel', 58, NULL, NULL, NULL),
(1340, 2, 17, 'credit', 10.000, 600.000, 'USD', 'test', '2025-11-19 08:53:45', 'hotel', 58, NULL, NULL, NULL),
(1341, 2, 17, 'credit', 10.000, 610.000, 'USD', 'test', '2025-11-19 08:53:45', 'hotel', 58, NULL, NULL, NULL),
(1342, 2, 17, 'credit', 10.000, 620.000, 'USD', 'test', '2025-11-19 08:53:45', 'hotel', 58, NULL, NULL, NULL),
(1343, 2, 17, 'credit', 10.000, 630.000, 'USD', 'test', '2025-11-19 08:53:46', 'hotel', 58, NULL, NULL, NULL),
(1344, 2, 17, 'credit', 10.000, 640.000, 'USD', 'test', '2025-11-19 08:55:27', 'hotel', 58, NULL, NULL, NULL),
(1345, 2, 17, 'credit', 10.000, 650.000, 'USD', 'test', '2025-11-19 08:55:28', 'hotel', 58, NULL, NULL, NULL),
(1346, 2, 17, 'credit', 10.000, 660.000, 'USD', 'test', '2025-11-19 08:55:28', 'hotel', 58, NULL, NULL, NULL),
(1347, 2, 17, 'credit', 10.000, 670.000, 'USD', 'test', '2025-11-19 08:55:28', 'hotel', 58, NULL, NULL, NULL),
(1348, 2, 17, 'credit', 10.000, 680.000, 'USD', 'test', '2025-11-19 08:58:00', 'hotel', 57, NULL, NULL, NULL),
(1349, 2, 17, 'credit', 10.000, 690.000, 'USD', 'test', '2025-11-19 08:58:00', 'hotel', 57, NULL, NULL, NULL),
(1350, 2, 17, 'credit', 10.000, 700.000, 'USD', 'test', '2025-11-19 08:59:45', 'hotel', 57, NULL, NULL, NULL),
(1351, 2, 17, 'credit', 10.000, 710.000, 'USD', 'test', '2025-11-19 08:59:57', 'hotel', 57, NULL, NULL, NULL),
(1352, 2, 17, 'credit', 10.000, 720.000, 'USD', 'test', '2025-11-19 08:59:57', 'hotel', 57, NULL, NULL, NULL),
(1353, 2, 17, 'credit', 10.000, 730.000, 'USD', 'test', '2025-11-19 08:59:58', 'hotel', 57, NULL, NULL, NULL),
(1354, 2, 17, 'credit', 10.000, 740.000, 'USD', 'test', '2025-11-19 08:59:59', 'hotel', 57, NULL, NULL, NULL),
(1355, 2, 17, 'credit', 10.000, 750.000, 'USD', 'test', '2025-11-19 09:04:19', 'hotel', 57, NULL, NULL, NULL),
(1356, 2, 17, 'credit', 10.000, 760.000, 'USD', 'test', '2025-11-19 09:04:19', 'hotel', 57, NULL, NULL, NULL),
(1357, 2, 17, 'credit', 10.000, 770.000, 'USD', 'test', '2025-11-19 09:04:20', 'hotel', 57, NULL, NULL, NULL),
(1358, 2, 17, 'credit', 10.000, 780.000, 'USD', 'test', '2025-11-19 09:08:12', 'hotel', 57, NULL, NULL, NULL),
(1359, 2, 17, 'credit', 10.000, 790.000, 'USD', 'test', '2025-11-19 09:08:29', 'hotel', 57, NULL, NULL, NULL),
(1360, 2, 17, 'credit', 10.000, 800.000, 'USD', 'test', '2025-11-19 09:08:29', 'hotel', 57, NULL, NULL, NULL),
(1361, 2, 17, 'credit', 10.000, 810.000, 'USD', 'test', '2025-11-19 09:08:30', 'hotel', 57, NULL, NULL, NULL),
(1362, 2, 17, 'credit', 10.000, 820.000, 'USD', 'test', '2025-11-19 09:10:59', 'hotel', 57, NULL, NULL, NULL),
(1363, 2, 17, 'credit', 10.000, 830.000, 'USD', 'test', '2025-11-19 09:11:00', 'hotel', 57, NULL, NULL, NULL),
(1364, 2, 17, 'credit', 10.000, 840.000, 'USD', 'test', '2025-11-19 09:11:00', 'hotel', 57, NULL, NULL, NULL),
(1365, 2, 17, 'credit', 10.000, 850.000, 'USD', 'test', '2025-11-19 09:12:33', 'hotel', 57, NULL, NULL, NULL),
(1366, 2, 17, 'credit', 10.000, 860.000, 'USD', 'test', '2025-11-19 09:12:33', 'hotel', 57, NULL, NULL, NULL),
(1367, 2, 17, 'credit', 10.000, 870.000, 'USD', 'test', '2025-11-19 09:12:34', 'hotel', 57, NULL, NULL, NULL),
(1368, 2, 17, 'credit', 10.000, 880.000, 'USD', 'test', '2025-11-19 09:16:17', 'hotel', 57, NULL, NULL, NULL),
(1369, 2, 17, 'credit', 10.000, 890.000, 'USD', 'test', '2025-11-19 09:16:17', 'hotel', 57, NULL, NULL, NULL),
(1370, 2, 17, 'credit', 10.000, 900.000, 'USD', 'test', '2025-11-19 09:16:18', 'hotel', 57, NULL, NULL, NULL),
(1371, 2, 17, 'credit', 10.000, 910.000, 'USD', 'test', '2025-11-19 09:21:31', 'hotel', 57, NULL, NULL, NULL),
(1372, 2, 17, 'credit', 10.000, 920.000, 'USD', 'test', '2025-11-19 09:21:32', 'hotel', 57, NULL, NULL, NULL),
(1373, 2, 17, 'credit', 10.000, 930.000, 'USD', 'test', '2025-11-19 09:21:32', 'hotel', 57, NULL, NULL, NULL),
(1374, 2, 17, 'credit', 10.000, 940.000, 'USD', 'test', '2025-11-19 09:21:33', 'hotel', 57, NULL, NULL, NULL),
(1375, 2, 17, 'credit', 10.000, 950.000, 'USD', 'test', '2025-11-19 09:24:15', 'hotel', 57, NULL, NULL, NULL),
(1376, 2, 17, 'credit', 10.000, 960.000, 'USD', 'test', '2025-11-19 09:24:36', 'hotel', 57, NULL, NULL, NULL),
(1377, 2, 17, 'debit', 50.000, 910.000, 'USD', 'Refund payment for Hotel Booking #58 - Mr ROHULLAH SAFI', '2025-11-19 09:30:21', 'hotel_refund', 16, NULL, NULL, NULL),
(1378, 2, 17, 'credit', 10.000, 920.000, 'USD', 'test', '2025-11-19 09:31:12', 'umrah', 97, '', NULL, NULL),
(1379, 2, 17, 'debit', 20.000, 900.000, 'USD', 'Refund payment for Umrah Booking #68 - KamAir', '2025-11-19 09:32:05', 'umrah_refund', 26, NULL, 0.00000, NULL),
(1380, 2, 17, 'credit', 10.000, 910.000, 'USD', 'test', '2025-11-19 09:32:58', 'visa_sale', 69, NULL, NULL, NULL),
(1383, 2, 17, 'debit', 10.000, 900.000, 'USD', 'test', '2025-11-19 10:01:20', 'expense', 218, '', NULL, NULL),
(1384, 2, 17, 'credit', 10.000, 910.000, 'USD', '', '2025-11-19 10:03:37', 'deposit_sarafi', 48, 'DEP691d95ec89124', NULL, NULL),
(1385, 2, 17, 'credit', 5000.000, 5910.000, 'USD', 'Initial credit balance for creditor: ROHULLAH SAFI', '2025-11-19 10:03:58', 'creditor', 16, NULL, NULL, NULL),
(1386, 2, 17, 'debit', 100.000, 5810.000, 'USD', 'test', '2025-11-19 10:04:12', 'creditor', 20, NULL, NULL, NULL),
(1387, 2, 17, 'debit', 100.000, 5710.000, 'USD', 'test', '2025-11-19 10:04:32', 'creditor', 21, NULL, NULL, NULL),
(1388, 2, 17, 'debit', 100.000, 5610.000, 'USD', 'test', '2025-11-19 10:04:38', 'creditor', 22, NULL, NULL, NULL),
(1389, 2, 17, 'debit', 100.000, 5510.000, 'USD', 'test', '2025-11-19 10:04:45', 'creditor', 23, NULL, NULL, NULL),
(1390, 2, 17, 'credit', 10.000, 5520.000, 'USD', '', '2025-11-19 10:11:24', 'deposit_sarafi', 49, 'DEP691d97be54d6c', NULL, NULL),
(1391, 2, 17, 'credit', 10.000, 5530.000, 'USD', '', '2025-11-19 10:11:41', 'deposit_sarafi', 50, 'DEP691d97cc6fe2d', NULL, NULL),
(1392, 2, 17, 'credit', 10.000, 5540.000, 'USD', '', '2025-11-19 10:11:43', 'deposit_sarafi', 51, 'DEP691d97cc6fe2d', NULL, NULL),
(1393, 2, 17, 'debit', 10.000, 5530.000, 'USD', 'test', '2025-11-19 10:28:02', 'creditor', 24, NULL, NULL, NULL),
(1394, 2, 17, 'debit', 10.000, 5520.000, 'USD', 'test', '2025-11-19 10:28:09', 'creditor', 25, NULL, NULL, NULL),
(1395, 2, 17, 'debit', 10.000, 5510.000, 'USD', 'test', '2025-11-19 10:28:17', 'creditor', 26, NULL, NULL, NULL),
(1396, 2, 17, 'debit', 10.000, 5500.000, 'USD', 'test', '2025-11-19 10:28:26', 'creditor', 27, NULL, NULL, NULL),
(1397, 2, 17, 'debit', 10.000, 5490.000, 'USD', 'test', '2025-11-19 10:28:36', 'creditor', 28, NULL, NULL, NULL),
(1398, 2, 17, 'debit', 10.000, 5480.000, 'USD', 'test', '2025-11-19 10:41:54', 'creditor', 29, NULL, NULL, NULL),
(1399, 2, 17, 'debit', 10.000, 5470.000, 'USD', 'test', '2025-11-19 10:42:02', 'creditor', 30, NULL, NULL, NULL),
(1400, 2, 17, 'debit', 10.000, 5460.000, 'USD', 'test', '2025-11-19 10:42:10', 'creditor', 31, NULL, NULL, NULL),
(1401, 2, 17, 'debit', 10.000, 5450.000, 'USD', 'test', '2025-11-19 10:42:18', 'creditor', 32, NULL, NULL, NULL),
(1402, 2, 17, 'debit', 10.000, 5440.000, 'USD', 'test', '2025-11-19 10:42:27', 'creditor', 33, NULL, NULL, NULL),
(1403, 2, 17, 'debit', 10.000, 5430.000, 'USD', 'test', '2025-11-19 10:43:17', 'creditor', 34, NULL, NULL, NULL),
(1404, 2, 17, 'debit', 10.000, 5420.000, 'USD', 'test | Additional Remarks: This is a visa', '2025-11-19 10:43:25', 'creditor', 35, '2452345', NULL, NULL),
(1405, 2, 17, 'debit', 100.000, 5320.000, 'USD', 'Initial debt balance for ROHULLAH SAFI', '2025-11-19 10:44:45', 'debtor', 46, 'DEBT-20251119104445-41', NULL, NULL),
(1406, 2, 17, 'credit', 10.000, 5330.000, 'USD', 'Account funded by Matiullah Rahimi. Remarks: 0. Receipt: 0', '2025-11-19 10:51:31', 'fund', 7, '0', NULL, NULL),
(1407, 2, 17, 'credit', 10.000, 5340.000, 'USD', 'Account funded by Matiullah Rahimi. Remarks: 0. Receipt: 0', '2025-11-19 10:53:26', 'fund', 7, '0', NULL, NULL),
(1408, 2, 17, 'credit', 10.000, 5350.000, 'USD', 'Client: DR SAHIB, Received 10 USD for client account funding, processed by: Matiullah Rahimi, Remarks: 0', '2025-11-19 10:54:32', 'client_fund', 610, '073126', NULL, NULL),
(1412, 2, 17, 'credit', 100000.000, 100000.000, 'AFS', 'Account funded by Matiullah Rahimi. Remarks: test. Receipt: t43t4tw', '2025-11-20 11:40:53', 'fund', 7, 't43t4tw', NULL, NULL),
(1413, 2, 17, 'debit', 1000.000, 99000.000, 'AFS', 'test', '2025-11-20 11:41:14', 'salary_payment', 26, 'SA20251120114114', NULL, NULL),
(1415, 2, 17, 'credit', 10.000, 5360.000, 'USD', '', '2025-11-24 05:12:22', 'umrah', 99, '', NULL, NULL),
(1417, 2, 17, 'credit', 10.000, 0.000, 'USD', 'test', '2025-12-02 09:12:21', 'additional_payment', 53, '', 0.00000, 2),
(1418, 2, 17, 'credit', 10.000, 10.000, 'USD', 'Account funded by ROHULLAH SAFI. Remarks: test. Receipt: sdg', '2025-12-03 06:38:35', 'fund', 19, 'sdg', NULL, 2),
(1419, 2, 17, 'debit', 50.000, -40.000, 'USD', 'Initial debt balance for  SAFI', '2025-12-03 07:14:02', 'debtor', 47, 'DEBT-20251203071402-42', NULL, 2),
(1420, 2, 17, 'credit', 50.000, 10.000, 'USD', 'test', '2025-12-03 07:25:33', 'debtor', 48, NULL, NULL, 2),
(1421, 2, 17, 'debit', 100.000, -90.000, 'USD', 'this is for test', '2025-12-04 08:36:47', 'expense', 219, '', NULL, 2),
(1422, 2, 18, 'credit', 100.000, 100.000, 'USD', 'Account funded by ROHULLAH SAFI. Remarks: test. Receipt: afdsa', '2025-12-06 06:31:20', 'fund', 19, 'afdsa', NULL, 2),
(1423, 2, 17, 'debit', 100.000, -180.000, 'USD', 'Supplier: KamAir, Funded by main account: SELF BANK (SAFE), processed by: ROHULLAH SAFI, Remarks: test', '2025-12-06 06:31:44', 'supplier_fund', 984, '2452345', NULL, 2),
(1424, 2, 17, 'credit', 100.000, -80.000, 'USD', 'Supplier: KamAir, Withdrawn to main account: SELF BANK (SAFE), processed by: ROHULLAH SAFI, Remarks: test', '2025-12-06 06:34:32', 'supplier_fund_withdrawal', 987, '245234', NULL, 2),
(1425, 2, 17, 'debit', 100.000, -180.000, 'USD', 'test', '2025-12-06 06:35:10', 'transfer', 18, NULL, NULL, 2),
(1426, 2, 18, 'credit', 100.000, 200.000, 'USD', 'test', '2025-12-06 06:35:10', 'transfer', 17, NULL, NULL, 2),
(1427, 2, 17, 'debit', 100.000, -280.000, 'USD', 'test', '2025-12-06 06:35:46', 'transfer', 18, NULL, NULL, 2),
(1428, 2, 18, 'credit', 100.000, 300.000, 'USD', 'test', '2025-12-06 06:35:46', 'transfer', 17, NULL, NULL, 2),
(1429, 2, 17, 'credit', 100.000, -180.000, 'USD', 'Supplier: KamAir, Withdrawn to main account: SELF BANK (SAFE), processed by: ROHULLAH SAFI, Remarks: test', '2025-12-06 06:36:46', 'supplier_fund_withdrawal', 988, '245234', NULL, 2),
(1430, 2, 17, 'credit', 100.000, -80.000, 'USD', 'Supplier: KamAir, Withdrawn to main account: SELF BANK (SAFE), processed by: ROHULLAH SAFI, Remarks: test', '2025-12-06 06:37:58', 'supplier_fund_withdrawal', 989, '2452345', NULL, 2),
(1431, 2, 17, 'credit', 80.000, 0.000, 'USD', 'Client: DR SAHIB, Received 80 USD for client account funding, processed by: ROHULLAH SAFI, Remarks: test', '2025-12-06 06:38:52', 'client_fund', 630, '2452345', NULL, 2),
(1432, 2, 17, 'debit', 10.000, -10.000, 'USD', 'Initial debt balance for ROHULLAH SAFI', '2025-12-06 06:46:00', 'debtor', 49, 'DEBT-20251206064637-43', NULL, 2),
(1434, 2, 17, 'debit', 10.000, -20.000, 'USD', 'Initial debt balance for ROHULLAH SAFI', '2025-12-06 07:00:33', 'debtor', 51, 'DEBT-20251206070033-44', NULL, 2),
(1435, 2, 17, 'debit', 10.000, -30.000, 'USD', 'Initial debt balance for ROHULLAH SAFI', '2025-12-06 07:02:58', 'debtor', 52, 'DEBT-20251206070258-45', NULL, 2),
(1436, 2, 17, 'debit', 10.000, -40.000, 'USD', 'Initial debt balance for ROHULLAH SAFI', '2025-12-06 07:03:20', 'debtor', 53, 'DEBT-20251206070320-46', NULL, 2),
(1437, 2, 17, 'credit', 100.000, 5420.000, 'USD', 'Initial credit balance for creditor: ROHULLAH SAFI', '2025-12-06 07:14:50', 'creditor', 17, NULL, NULL, NULL),
(1438, 2, 17, 'debit', 10.000, 5410.000, 'USD', 'test', '2025-12-06 07:15:26', 'creditor', 36, NULL, NULL, NULL),
(1439, 2, 17, 'debit', 10.000, 5400.000, 'USD', 'test', '2025-12-06 07:16:38', 'creditor', 37, NULL, NULL, NULL),
(1440, 2, 17, 'debit', 10.000, -50.000, 'USD', 'test', '2025-12-06 07:27:59', 'creditor', 38, NULL, NULL, 2),
(1441, 2, 17, 'credit', 10.000, -40.000, 'USD', 'test', '2025-12-06 07:30:45', 'deposit_sarafi', 52, 'DEP6933db96c6799', NULL, 2);

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
  `pdf_path` varchar(255) DEFAULT NULL,
  `branch_id` bigint(20) DEFAULT NULL
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `message_reactions`
--

INSERT INTO `message_reactions` (`id`, `message_id`, `user_id`, `emoji`, `tenant_id`, `created_at`, `branch_id`) VALUES
(1, 28, 1, '👍', 1, '2025-10-28 12:18:22', NULL),
(4, 26, 1, '❤️', 1, '2025-10-28 12:30:02', NULL),
(6, 26, 1, '😂', 1, '2025-10-29 10:23:02', NULL),
(7, 25, 1, '👍', 1, '2025-10-29 10:23:06', NULL);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `notifications`
--

INSERT INTO `notifications` (`id`, `tenant_id`, `transaction_id`, `transaction_type`, `message`, `recipient_role`, `status`, `created_at`, `branch_id`) VALUES
(1050, 2, 1255, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 06:32:23', NULL),
(1051, 2, 1256, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 06:32:54', NULL),
(1052, 2, 1257, 'ticket_reserve', 'New payment received for ticket booking #188JZ0 - Mr BAKHTIAR STANIKZAI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 06:51:53', NULL),
(1053, 2, 1258, 'ticket_reserve', 'New payment received for ticket booking #188JZ0 - Mr BAKHTIAR STANIKZAI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 07:17:58', NULL),
(1054, 2, 1259, 'ticket_sale', 'New payment received for ticket booking #SZQXJU - Mr wali: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 07:19:41', NULL),
(1055, 2, 1260, 'ticket_sale', 'New payment received for ticket booking #SZQXJU - Mr wali: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 07:20:41', NULL),
(1056, 2, 1261, 'ticket_sale', 'New payment received for ticket booking #SZQXJU - Mr wali: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 07:22:59', NULL),
(1057, 2, 1262, 'ticket_sale', 'New payment received for ticket booking #SZQXJU - Mr wali: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 07:27:42', NULL),
(1058, 2, 1263, 'ticket_sale', 'New payment received for ticket booking #SZQXJU - Mr wali: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 07:32:29', NULL),
(1059, 2, 1264, 'ticket_sale', 'New payment received for ticket booking #SZQXJU - Mr wali: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 07:34:06', NULL),
(1060, 2, 1265, 'ticket_sale', 'New payment received for ticket booking #SZQXJU - Mr wali: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 07:36:11', NULL),
(1061, 2, 1266, 'ticket_sale', 'New payment received for ticket booking #SZQXJU - Mr wali: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 07:36:13', NULL),
(1062, 2, 1267, 'ticket_sale', 'New payment received for ticket booking #SZQXJU - Mr wali: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 07:38:08', NULL),
(1063, 2, 1268, 'ticket_sale', 'New payment received for ticket booking #SZQXJU - Mr wali: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 07:38:09', NULL),
(1064, 2, 1269, 'ticket_sale', 'New payment received for ticket booking #SZQXJU - Mr wali: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 07:38:10', NULL),
(1065, 2, 1270, 'ticket_sale', 'New payment received for ticket booking #SZQXJU - Mr wali: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 07:38:10', NULL),
(1066, 2, 1271, 'ticket_sale', 'New payment received for ticket booking #SZQXJU - Mr wali: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 07:40:59', NULL),
(1067, 2, 1272, 'ticket_sale', 'New payment received for ticket booking #SZQXJU - Mr wali: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 07:41:01', NULL),
(1068, 2, 1273, 'ticket_sale', 'New payment received for ticket booking #SZQXJU - Mr wali: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 07:41:01', NULL),
(1069, 2, 1274, 'ticket_sale', 'New payment received for ticket booking #SZQXJU - Mr wali: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 07:41:01', NULL),
(1070, 2, 1275, 'ticket_sale', 'New payment received for ticket booking #SZQXJU - Mr wali: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 07:46:46', NULL),
(1071, 2, 1276, 'ticket_sale', 'New payment received for ticket booking #SZQXJU - Mr wali: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 07:59:54', NULL),
(1072, 2, 1277, 'ticket_sale', 'New payment received for ticket booking #SZQXJU - Mr wali: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:00:10', NULL),
(1073, 2, 1278, 'ticket_refund', 'Refund payment for Agency client ticket #HAUPSE - Mr FAZALHAQ PARDES: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:03:58', NULL),
(1074, 2, 1279, 'ticket_refund', 'Refund payment for Agency client ticket #HAUPSE - Mr FAZALHAQ PARDES: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:03:58', NULL),
(1075, 2, 1280, 'ticket_refund', 'Refund payment for Agency client ticket #HAUPSE - Mr FAZALHAQ PARDES: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:03:58', NULL),
(1076, 2, 1281, 'ticket_refund', 'Refund payment for Agency client ticket #HAUPSE - Mr FAZALHAQ PARDES: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:03:58', NULL),
(1077, 2, 1282, 'ticket_refund', 'Refund payment for Agency client ticket #HAUPSE - Mr FAZALHAQ PARDES: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:03:59', NULL),
(1078, 2, 1283, 'ticket_refund', 'Refund payment for Agency client ticket #HAUPSE - Mr FAZALHAQ PARDES: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:03:59', NULL),
(1079, 2, 1284, 'ticket_refund', 'Refund payment for Agency client ticket #HAUPSE - Mr FAZALHAQ PARDES: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:04:00', NULL),
(1080, 2, 1285, 'ticket_refund', 'Refund payment for Agency client ticket #HAUPSE - Mr FAZALHAQ PARDES: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:04:00', NULL),
(1081, 2, 1286, 'ticket_refund', 'Refund payment for Agency client ticket #HAUPSE - Mr FAZALHAQ PARDES: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:04:00', NULL),
(1082, 2, 1287, 'ticket_refund', 'Refund payment for Agency client ticket #HAUPSE - Mr FAZALHAQ PARDES: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:04:01', NULL),
(1083, 2, 1288, 'ticket_refund', 'Refund payment for Agency client ticket #HAUPSE - Mr FAZALHAQ PARDES: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:04:01', NULL),
(1084, 2, 1289, 'ticket_refund', 'Refund payment for Agency client ticket #HAUPSE - Mr FAZALHAQ PARDES: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:06:36', NULL),
(1085, 2, 1290, 'ticket_date_change', 'New payment received for date change #HAUPSE - Mr FAZALHAQ PARDES: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:12:12', NULL),
(1086, 2, 1291, 'ticket_date_change', 'New payment received for date change #HAUPSE - Mr FAZALHAQ PARDES: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:12:13', NULL),
(1087, 2, 1292, 'ticket_date_change', 'New payment received for date change #HAUPSE - Mr FAZALHAQ PARDES: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:12:13', NULL),
(1088, 2, 1293, 'ticket_date_change', 'New payment received for date change #HAUPSE - Mr FAZALHAQ PARDES: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:15:19', NULL),
(1089, 2, 1294, 'ticket_date_change', 'New payment received for date change #HAUPSE - Mr FAZALHAQ PARDES: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:15:19', NULL),
(1090, 2, 1295, 'ticket_date_change', 'New payment received for date change #HAUPSE - Mr FAZALHAQ PARDES: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:15:20', NULL),
(1091, 2, 1296, 'ticket_date_change', 'New payment received for date change #HAUPSE - Mr FAZALHAQ PARDES: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:15:21', NULL),
(1092, 2, 1297, 'ticket_date_change', 'New payment received for date change #HAUPSE - Mr FAZALHAQ PARDES: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:15:21', NULL),
(1093, 2, 1298, 'ticket_date_change', 'New payment received for date change #HAUPSE - Mr FAZALHAQ PARDES: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:38:32', NULL),
(1094, 2, 1299, 'ticket_date_change', 'New payment received for date change #HAUPSE - Mr FAZALHAQ PARDES: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:38:52', NULL),
(1095, 2, 1300, 'weight', 'New payment received for weight charge #SZQXJU - Mr wali: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:41:45', NULL),
(1096, 2, 1301, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:43:24', NULL),
(1097, 2, 1302, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:43:24', NULL),
(1098, 2, 1303, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:43:25', NULL),
(1099, 2, 1304, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:43:25', NULL),
(1100, 2, 1305, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:47:48', NULL),
(1101, 2, 1306, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:47:48', NULL),
(1102, 2, 1307, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:47:48', NULL),
(1103, 2, 1308, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:47:49', NULL),
(1104, 2, 1309, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:47:49', NULL),
(1105, 2, 1310, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:47:49', NULL),
(1106, 2, 1311, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:49:43', NULL),
(1107, 2, 1312, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:50:00', NULL),
(1108, 2, 1313, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:50:00', NULL),
(1109, 2, 1314, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:50:00', NULL),
(1110, 2, 1315, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:50:00', NULL),
(1111, 2, 1316, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:50:00', NULL),
(1112, 2, 1317, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:50:01', NULL),
(1113, 2, 1318, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:50:02', NULL),
(1114, 2, 1319, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:50:02', NULL),
(1115, 2, 1320, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:50:02', NULL),
(1116, 2, 1321, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:50:02', NULL),
(1117, 2, 1322, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:50:02', NULL),
(1118, 2, 1323, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:51:25', NULL),
(1119, 2, 1324, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:51:25', NULL),
(1120, 2, 1325, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:51:25', NULL),
(1121, 2, 1326, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:51:25', NULL),
(1122, 2, 1327, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:51:25', NULL),
(1123, 2, 1328, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:51:26', NULL),
(1124, 2, 1329, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:51:27', NULL),
(1125, 2, 1330, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:51:27', NULL),
(1126, 2, 1331, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:51:27', NULL),
(1127, 2, 1332, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:51:27', NULL),
(1128, 2, 1333, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:53:24', NULL),
(1129, 2, 1334, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:53:43', NULL),
(1130, 2, 1335, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:53:43', NULL),
(1131, 2, 1336, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:53:43', NULL),
(1132, 2, 1337, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:53:44', NULL),
(1133, 2, 1338, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:53:44', NULL),
(1134, 2, 1339, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:53:44', NULL),
(1135, 2, 1340, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:53:45', NULL),
(1136, 2, 1341, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:53:45', NULL),
(1137, 2, 1342, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:53:45', NULL),
(1138, 2, 1343, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:53:46', NULL),
(1139, 2, 1344, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:55:27', NULL),
(1140, 2, 1345, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:55:28', NULL),
(1141, 2, 1346, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:55:28', NULL),
(1142, 2, 1347, 'hotel', 'New payment received for hotel booking #34212 - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:55:28', NULL),
(1143, 2, 1348, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:58:00', NULL),
(1144, 2, 1349, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:58:00', NULL),
(1145, 2, 1350, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:59:45', NULL),
(1146, 2, 1351, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:59:57', NULL),
(1147, 2, 1352, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:59:57', NULL),
(1148, 2, 1353, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:59:58', NULL),
(1149, 2, 1354, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 08:59:59', NULL),
(1150, 2, 1355, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:04:19', NULL),
(1151, 2, 1356, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:04:19', NULL),
(1152, 2, 1357, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:04:20', NULL),
(1153, 2, 1358, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:08:12', NULL),
(1154, 2, 1359, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:08:29', NULL),
(1155, 2, 1360, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:08:29', NULL),
(1156, 2, 1361, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:08:30', NULL),
(1157, 2, 1362, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:10:59', NULL),
(1158, 2, 1363, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:11:00', NULL),
(1159, 2, 1364, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:11:00', NULL),
(1160, 2, 1365, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:12:33', NULL),
(1161, 2, 1366, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:12:33', NULL),
(1162, 2, 1367, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:12:34', NULL),
(1163, 2, 1368, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:16:17', NULL),
(1164, 2, 1369, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:16:17', NULL),
(1165, 2, 1370, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:16:18', NULL),
(1166, 2, 1371, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:21:31', NULL),
(1167, 2, 1372, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:21:32', NULL),
(1168, 2, 1373, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:21:32', NULL),
(1169, 2, 1374, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:21:33', NULL),
(1170, 2, 1375, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:24:15', NULL),
(1171, 2, 1376, 'hotel', 'New payment received for hotel booking #adfadsf - Mr ROHULLAH SAFI: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:24:36', NULL),
(1172, 2, 1377, 'hotel_refund', 'Hotel refund payment for Agency client - ROHULLAH (SAFI) Amount USD 50.00', 'Admin', 'Unread', '2025-11-19 09:30:37', NULL),
(1173, 2, 97, 'umrah', 'Customer: KamAir has paid: 10 USD to Internal Account processed by Matiullah Rahimi for the Umrah booking.', 'Admin', 'Unread', '2025-11-19 09:31:12', NULL),
(1174, 2, 1379, 'umrah_refund', 'Umrah refund payment for Agency client - KamAir Amount USD 20.00', 'Admin', 'Unread', '2025-11-19 09:32:13', NULL),
(1175, 2, 1380, 'visa', 'New payment received for visa application #69 - guli: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:33:16', NULL),
(1176, 2, 1381, 'visa_refund', 'Visa refund payment for Agency client - guli (P8798765) from Qatar: Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 09:37:54', NULL),
(1177, 2, 1382, 'additional_payment', 'New additional payment received: Amount USD 10.00 - test', 'Admin', 'Unread', '2025-11-19 09:59:43', NULL),
(1178, 2, 1383, 'expense', 'New expense added for category Office: Amount USD 10.00 - test', 'Admin', 'Unread', '2025-11-19 10:01:20', NULL),
(1179, 2, 1384, 'deposit_sarafi', 'New deposit from ROHULLAH SAFI: USD 10 - Reference: DEP691d95ec89124', 'Admin', 'Unread', '2025-11-19 10:03:37', NULL),
(1180, 2, 1386, 'creditor', 'Payment made to creditor:  - Amount USD 100.00', 'Admin', 'Unread', '2025-11-19 10:04:12', NULL),
(1181, 2, 1387, 'creditor', 'Payment made to creditor:  - Amount USD 100.00', 'Admin', 'Unread', '2025-11-19 10:04:32', NULL),
(1182, 2, 1388, 'creditor', 'Payment made to creditor:  - Amount USD 100.00', 'Admin', 'Unread', '2025-11-19 10:04:38', NULL),
(1183, 2, 1389, 'creditor', 'Payment made to creditor:  - Amount USD 100.00', 'Admin', 'Unread', '2025-11-19 10:04:45', NULL),
(1184, 2, 1390, 'deposit_sarafi', 'New deposit from ROHULLAH: USD 10 - Reference: DEP691d97be54d6c', 'Admin', 'Unread', '2025-11-19 10:11:24', NULL),
(1185, 2, 1391, 'deposit_sarafi', 'New deposit from ROHULLAH SAFI: USD 10 - Reference: DEP691d97cc6fe2d', 'Admin', 'Unread', '2025-11-19 10:11:41', NULL),
(1186, 2, 1392, 'deposit_sarafi', 'New deposit from ROHULLAH SAFI: USD 10 - Reference: DEP691d97cc6fe2d', 'Admin', 'Unread', '2025-11-19 10:11:43', NULL),
(1187, 2, 1393, 'creditor', 'Payment made to creditor:  - Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 10:28:02', NULL),
(1188, 2, 1394, 'creditor', 'Payment made to creditor:  - Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 10:28:09', NULL),
(1189, 2, 1395, 'creditor', 'Payment made to creditor:  - Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 10:28:17', NULL),
(1190, 2, 1396, 'creditor', 'Payment made to creditor:  - Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 10:28:26', NULL),
(1191, 2, 1397, 'creditor', 'Payment made to creditor:  - Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 10:28:36', NULL),
(1192, 2, 1398, 'creditor', 'Payment made to creditor:  - Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 10:41:54', NULL),
(1193, 2, 1399, 'creditor', 'Payment made to creditor:  - Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 10:42:02', NULL),
(1194, 2, 1400, 'creditor', 'Payment made to creditor:  - Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 10:42:10', NULL),
(1195, 2, 1401, 'creditor', 'Payment made to creditor:  - Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 10:42:18', NULL),
(1196, 2, 1402, 'creditor', 'Payment made to creditor:  - Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 10:42:27', NULL),
(1197, 2, 1403, 'creditor', 'Payment made to creditor:  - Amount USD 10.00', 'Admin', 'Unread', '2025-11-19 10:43:17', NULL),
(1198, 2, 1404, 'creditor', 'Payment made to creditor:  - Amount USD 10.00', 'Admin', 'Read', '2025-11-19 10:43:25', NULL),
(1199, 2, 610, 'client_fund', 'Client: DR SAHIB, Paid 10 USD for client account funding, processed by: Matiullah Rahimi, Remarks: 0', 'Admin', 'Read', '2025-11-19 10:54:32', NULL),
(1200, 2, 98, 'umrah', 'Customer: Matiullah has paid: 10 USD to Internal Account processed by Matiullah Rahimi for the Umrah booking.', 'Admin', 'Read', '2025-11-24 05:11:58', NULL),
(1201, 2, 99, 'umrah', 'Customer: KamAir has paid: 10 USD to Internal Account processed by Matiullah Rahimi for the Umrah booking.', 'Admin', 'Read', '2025-11-24 05:12:22', NULL),
(1202, 2, 1416, 'additional_payment', 'New additional payment received: Amount USD 10.00 - test', 'Admin', 'Unread', '2025-12-02 09:10:15', 2),
(1203, 2, 1417, 'additional_payment', 'New additional payment received: Amount USD 10.00 - test', 'Admin', 'Read', '2025-12-02 09:12:27', 2),
(1204, 2, 1420, 'debtor', 'Payment received from debtor: Amount USD 50.00 - test', 'Admin', 'Unread', '2025-12-03 07:25:33', 2),
(1205, 2, 1421, 'expense', 'New expense added for category OFFICE EXPS: Amount USD 100.00 - this is for test', 'Admin', 'Unread', '2025-12-04 08:36:47', 2),
(1206, 2, 984, 'supplier_fund', 'Supplier: KamAir, Funded 100 USD by main account: SELF BANK (SAFE), processed by: ROHULLAH SAFI, Remarks: test', 'Admin', 'Unread', '2025-12-06 06:31:44', 2),
(1207, 2, 986, 'supplier_bonus', 'Bonus of 100 USD added to supplier: KamAir, processed by: ROHULLAH SAFI, Remarks: test', 'Admin', 'Unread', '2025-12-06 06:34:07', 2),
(1208, 2, 987, 'supplier_fund_withdrawal', 'Supplier: KamAir, Withdrawn 100 USD to main account: SELF BANK (SAFE), processed by: ROHULLAH SAFI, Remarks: test', 'Admin', 'Unread', '2025-12-06 06:34:32', 2),
(1209, 2, 988, 'supplier_fund_withdrawal', 'Supplier: KamAir, Withdrawn 100 USD to main account: SELF BANK (SAFE), processed by: ROHULLAH SAFI, Remarks: test', 'Admin', 'Unread', '2025-12-06 06:36:46', 2),
(1210, 2, 989, 'supplier_fund_withdrawal', 'Supplier: KamAir, Withdrawn 100 USD to main account: SELF BANK (SAFE), processed by: ROHULLAH SAFI, Remarks: test', 'Admin', 'Unread', '2025-12-06 06:37:58', 2),
(1211, 2, 630, 'client_fund', 'Client: DR SAHIB, Paid 80 USD for client account funding, processed by: ROHULLAH SAFI, Remarks: test', 'Admin', 'Unread', '2025-12-06 06:38:52', 2),
(1212, 2, 1433, 'debtor', 'Payment received from debtor: Amount USD 10.00 - test', 'Admin', 'Unread', '2025-12-06 06:57:31', 2),
(1213, 2, 1438, 'creditor', 'Payment made to creditor:  - Amount USD 10.00', 'Admin', 'Unread', '2025-12-06 07:15:26', NULL),
(1214, 2, 1439, 'creditor', 'Payment made to creditor:  - Amount USD 10.00', 'Admin', 'Unread', '2025-12-06 07:16:38', NULL),
(1215, 2, 1440, 'creditor', 'Payment made to creditor:  - Amount USD 10.00', 'Admin', 'Unread', '2025-12-06 07:27:59', NULL),
(1216, 2, 1441, 'deposit_sarafi', 'New deposit from ROHULLAH SAFI435: USD 10 - Reference: DEP6933db96c6799', 'Admin', 'Unread', '2025-12-06 07:30:45', 2),
(1217, 2, 1442, 'ticket_sale', 'New payment received for ticket booking #SZQXJU - Mr wali: Amount USD 10.00', 'Admin', 'Unread', '2025-12-06 08:07:52', 2),
(1218, 2, 1443, 'ticket_refund', 'Refund payment for Agency client ticket #SZQXJU - Mr guli: Amount USD 10.00', 'Admin', 'Unread', '2025-12-06 11:02:02', 2),
(1219, 2, 1444, 'weight', 'New payment received for weight charge #SZQXJU - Mr wali: Amount USD 10.00', 'Admin', 'Unread', '2025-12-06 11:40:16', 2);

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
(21, 'platform_name', 'MTravels', 'string', NULL, '2025-08-12 07:04:53', '2025-11-18 09:09:33'),
(22, 'platform_description', 'Professional travel agency management platform designed to optimize workflows, enhance customer service, and drive business growth through comprehensive automation and intelligent insights.', 'string', NULL, '2025-08-12 07:04:53', '2025-10-29 08:31:35'),
(23, 'contact_email', 'allahdadmuhammadi01@gmail.com', 'string', NULL, '2025-08-12 07:04:53', '2025-11-18 09:09:33'),
(24, 'support_phone', '+93780310431', 'string', NULL, '2025-08-12 07:04:53', '2025-11-18 09:09:33'),
(25, 'website_url', 'https://mtravels.com', 'string', NULL, '2025-08-12 07:04:53', '2025-11-18 09:09:33'),
(31, 'platform_logo', 'logo_1759405927_68de67675a380.png', 'string', NULL, '2025-08-12 07:13:25', '2025-10-02 11:52:07'),
(42, 'platform_favicon', 'logo_1756212047_68adab4f626b7.png', 'string', NULL, '2025-08-12 07:25:25', '2025-08-27 12:01:37'),
(43, 'contact_address', 'Kabul,Afghanistan', 'string', NULL, '2025-08-12 07:31:12', '2025-11-18 09:09:33'),
(44, 'contact_phone', '0780310431', 'string', NULL, '2025-08-12 07:31:12', '2025-11-18 09:09:33'),
(46, 'contact_website', 'https://construct360.com', 'string', NULL, '2025-08-12 07:31:12', '2025-08-12 23:17:29'),
(47, 'contact_facebook', 'https://mtravels.com', 'string', NULL, '2025-08-12 07:31:12', '2025-11-18 09:09:33'),
(48, 'contact_twitter', 'https://mtravels.com', 'string', NULL, '2025-08-12 07:31:12', '2025-11-18 09:09:33'),
(49, 'contact_linkedin', 'https://mtravels.com', 'string', NULL, '2025-08-12 07:31:12', '2025-11-18 09:09:33'),
(50, 'contact_instagram', 'https://mtravels.com', 'string', NULL, '2025-08-12 07:31:12', '2025-11-18 09:09:33'),
(84, 'email_notifications', '1', 'string', NULL, '2025-08-13 01:46:05', '2025-08-13 01:46:05'),
(85, 'sms_notifications', '0', 'string', NULL, '2025-08-13 01:46:05', '2025-08-13 01:46:05'),
(86, 'push_notifications', '1', 'string', NULL, '2025-08-13 01:46:05', '2025-08-13 01:46:05'),
(87, 'notification_sound', '1', 'string', NULL, '2025-08-13 01:46:05', '2025-08-13 01:46:05'),
(88, 'vapid_subject', 'mailto:allahdadmuhammadi01@gmail.com', 'string', NULL, '2025-08-13 05:40:16', '2025-08-13 05:40:16'),
(89, 'vapid_public_key', 'BPrcke06b6zEa_k2loCLVatIG83YOjNByloONBeeFzC4c4tlwW4ww3a9JoX5dp58dLekgAoOn2FtS9sPZKxbjjA', 'string', NULL, '2025-08-13 05:40:16', '2025-08-13 05:40:16'),
(90, 'vapid_private_key', 'XvR9FAF5YCNFbtJcLkJkBkQIYZ6bULJiGoPRsjV56UI', 'string', NULL, '2025-08-13 05:40:16', '2025-08-13 05:40:16'),
(91, 'agency_name', 'MTravels', 'string', 'Platform agency name', '2025-08-26 12:32:02', '2025-08-30 07:56:11'),
(92, 'default_currency', 'AFN', 'string', 'Default currency for new tenants', '2025-08-26 12:32:02', '2025-11-18 09:09:33'),
(93, 'support_email', 'allahdadmuhammadi01@gmail.com', 'string', 'Contact email for platform support', '2025-08-26 12:32:02', '2025-11-18 09:09:33'),
(94, 'api_enabled', 'false', 'boolean', 'Whether API access is enabled globally', '2025-08-26 12:32:02', '2025-11-18 09:09:33'),
(95, 'max_users_per_tenant', '20', 'integer', 'Maximum users allowed per tenant on basic plan', '2025-08-26 12:32:02', '2025-11-18 09:09:33'),
(96, 'logo', 'logo_1756211874_68adaaa2805b8.png', 'string', 'Platform logo file name', '2025-08-26 12:32:02', '2025-08-26 12:37:54'),
(214, 'smtp_host', 'smtp.gmail.com', 'string', 'SMTP server hostname', '2025-11-18 06:32:20', '2025-11-18 09:09:33'),
(215, 'smtp_port', '587', 'integer', 'SMTP server port', '2025-11-18 06:32:20', '2025-11-18 09:09:33'),
(216, 'smtp_encryption', 'tls', 'string', 'SMTP encryption type (tls/ssl)', '2025-11-18 06:32:20', '2025-11-18 09:09:33'),
(217, 'smtp_username', 'allahdadmuhammadi01@gmail.com', 'string', 'SMTP authentication username', '2025-11-18 06:32:20', '2025-11-18 09:09:33'),
(218, 'smtp_password', 'uogn gubu vfdq apjr', 'string', 'SMTP authentication password', '2025-11-18 06:32:20', '2025-11-18 09:09:33'),
(219, 'smtp_from_email', 'allahdadmuhammadi01@gmail.com', 'string', 'Email address to send from', '2025-11-18 06:32:20', '2025-11-18 09:09:33'),
(220, 'smtp_from_name', 'MTravels', 'string', 'Name to display in sent emails', '2025-11-18 06:32:20', '2025-11-18 09:09:33');

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
  `phone` varchar(50) DEFAULT NULL,
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
  `remarks` mediumtext NOT NULL,
  `created_by` int(50) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `calculation_method` varchar(11) NOT NULL,
  `imported` tinyint(1) NOT NULL DEFAULT 0,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `refunded_tickets`
--

INSERT INTO `refunded_tickets` (`id`, `tenant_id`, `ticket_id`, `paid_to`, `sold_to`, `supplier`, `title`, `passenger_name`, `pnr`, `origin`, `destination`, `phone`, `airline`, `gender`, `issue_date`, `departure_date`, `currency`, `sold`, `base`, `supplier_penalty`, `service_penalty`, `refund_to_passenger`, `status`, `remarks`, `created_by`, `created_at`, `updated_at`, `calculation_method`, `imported`, `branch_id`) VALUES
(141, 2, 404, 17, 20, '57', 'Mr', 'wali', 'SZQXJU', ' KBL', 'ELQ', '0700907993', 'KM', 'Male', '2025-11-19', '2025-12-05', 'USD', 130.000, 120.000, 0.000, 0.000, 130.000, '', 'test', 19, '2025-11-19 11:28:11', '2025-11-25 11:10:39', 'sold', 0, 2),
(142, 2, 400, 17, 21, '57', 'Mr', 'FAZALHAQ PARDES', 'HAUPSE', ' KBL', 'FRA', '0766202122', 'EI', 'Male', '2025-11-18', '2025-12-04', 'USD', 120.000, 100.000, 0.000, 0.000, 100.000, 'Refunded', 'test', 19, '2025-11-19 12:33:33', '2025-11-25 11:10:43', 'base', 0, 2),
(144, 2, 421, 17, 21, '57', 'Mr', 'FAZALHAQ PARDES', '188JZ0', ' KBL', 'FRA', '0766202122', 'A3', 'Male', '2025-12-06', '2025-12-14', 'USD', 120.000, 110.000, 10.000, 20.000, 90.000, '', 'test', 19, '2025-12-06 15:37:47', '2025-12-06 15:37:47', 'sold', 0, 2);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `salary_adjustments`
--

INSERT INTO `salary_adjustments` (`id`, `tenant_id`, `user_id`, `adjustment_type`, `amount`, `percentage`, `effective_date`, `previous_salary`, `new_salary`, `reason`, `approved_by`, `created_at`, `branch_id`) VALUES
(2, 1, 6, 'increment', 1000.00, 0.00, '2025-10-20', 10000.00, 11000.00, 'test', 1, '2025-10-20 04:43:01', 1);

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `salary_advances`
--

INSERT INTO `salary_advances` (`id`, `tenant_id`, `user_id`, `main_account_id`, `amount`, `currency`, `advance_date`, `repayment_status`, `amount_paid`, `description`, `receipt`, `created_at`, `updated_at`, `branch_id`) VALUES
(10, 2, 7, 17, 1000.00, 'AFS', '2025-11-20', 'pending', 0.00, 'test', 'SA20251120114114', '2025-11-20 11:41:14', '2025-11-25 05:54:39', 2);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `salary_bonuses`
--

INSERT INTO `salary_bonuses` (`id`, `tenant_id`, `user_id`, `amount`, `description`, `bonus_date`, `type`, `created_by`, `created_at`, `branch_id`) VALUES
(2, 1, 6, 1000.00, 'test', '2025-10-20', 'performance', 1, '2025-10-20 04:43:46', 1);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `salary_management`
--

INSERT INTO `salary_management` (`id`, `tenant_id`, `user_id`, `base_salary`, `currency`, `joining_date`, `payment_day`, `status`, `created_at`, `updated_at`, `branch_id`) VALUES
(10, 2, 7, 10000.00, 'AFS', '2025-11-01', 28, 'active', '2025-11-20 11:29:23', '2025-11-25 05:54:57', 2),
(11, 2, 19, 10000.00, 'AFS', '2025-12-06', 29, 'active', '2025-12-06 07:32:35', '2025-12-06 07:32:35', 2);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `salary_payments`
--

INSERT INTO `salary_payments` (`id`, `tenant_id`, `user_id`, `main_account_id`, `amount`, `currency`, `payment_date`, `payment_for_month`, `payment_type`, `description`, `receipt`, `created_at`, `branch_id`) VALUES
(26, 2, 7, 17, 1000.00, 'AFS', '2025-11-20', '2025-11-01', 'advance', 'test', 'SA20251120114114', '2025-11-20 11:41:14', 2);

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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `sarafi_transactions`
--

INSERT INTO `sarafi_transactions` (`id`, `tenant_id`, `customer_id`, `amount`, `currency`, `type`, `status`, `notes`, `reference_number`, `receipt_path`, `created_at`, `updated_at`, `branch_id`) VALUES
(48, 2, 6, 10.00, 'USD', 'deposit', 'completed', '', 'DEP691d95ec89124', NULL, '2025-11-19 10:03:37', '2025-11-19 10:03:37', NULL),
(49, 2, 7, 10.00, 'USD', 'deposit', 'completed', '', 'DEP691d97be54d6c', NULL, '2025-11-19 10:11:24', '2025-11-19 10:11:24', NULL),
(50, 2, 6, 10.00, 'USD', 'deposit', 'completed', '', 'DEP691d97cc6fe2d', NULL, '2025-11-19 10:11:41', '2025-11-19 10:11:41', NULL),
(51, 2, 6, 10.00, 'USD', 'deposit', 'completed', '', 'DEP691d97cc6fe2d', NULL, '2025-11-19 10:11:43', '2025-11-19 10:11:43', NULL),
(52, 2, 11, 10.00, 'USD', 'deposit', 'completed', 'test', 'DEP6933db96c6799', NULL, '2025-12-06 07:30:45', '2025-12-06 07:30:45', 2);

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `smtp_host` varchar(255) DEFAULT NULL,
  `smtp_port` int(11) DEFAULT NULL,
  `smtp_encryption` varchar(10) DEFAULT NULL,
  `smtp_username` varchar(255) DEFAULT NULL,
  `smtp_password` varchar(255) DEFAULT NULL,
  `smtp_from_email` varchar(255) DEFAULT NULL,
  `smtp_from_name` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `settings`
--

INSERT INTO `settings` (`id`, `tenant_id`, `agency_name`, `title`, `phone`, `email`, `address`, `logo`, `created_at`, `updated_at`, `smtp_host`, `smtp_port`, `smtp_encryption`, `smtp_username`, `smtp_password`, `smtp_from_email`, `smtp_from_name`) VALUES
(1, 1, 'Al Moqadas', 'Al Moqadas Travel Agency', '0786011115', 'almuqadas_travel@yahoo.com', 'Jada-e-Maiwand, KABUL , AFGHANISTAN', 'logo.png', '2025-01-18 04:43:58', '2025-08-26 07:49:36', NULL, NULL, NULL, NULL, NULL, NULL, NULL),
(2, 2, 'Al Wali', 'Al Wali Travel', '0786011115', 'alwali@gmail.com', 'kabul', 'file_68b9695fea986_Blue_and_White_Modern_Travel_Instagram_Post (1).png', '2025-09-04 12:34:02', '2025-11-26 06:52:10', 'smtp.gmail.com', 587, 'tls', 'allahdadmuhammadi01@gmail.com', 'uogn gubu vfdq apjr', 'allahdadmuhammadi01@gmail.com', 'Al Wali Travel Agency');

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
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `suppliers`
--

INSERT INTO `suppliers` (`id`, `tenant_id`, `name`, `contact_person`, `supplier_type`, `phone`, `email`, `address`, `currency`, `balance`, `created_at`, `updated_at`, `status`, `branch_id`) VALUES
(31, 1, 'KamAir', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 651.490, '2025-10-15 11:11:34', '2025-11-25 05:55:25', 'active', 1),
(32, 1, 'NSTTRIP', 'SABAOON', 'External', '0777305730', 'admin@abc-construction.com', 'nsst', 'USD', 699.500, '2025-10-15 11:57:18', '2025-11-25 05:55:27', 'active', 1),
(33, 1, 'Ariana', 'SABAOON', 'External', '0777305730', 'admin@abc-construction.com', 'nsst', 'AFS', 32093.000, '2025-10-15 11:59:01', '2025-11-25 05:55:29', 'active', 1),
(34, 1, 'Yasin', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'yasin', 'USD', 0.000, '2025-10-15 12:05:10', '2025-11-25 05:55:32', 'active', 1),
(35, 1, 'ALAIN T', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'adfa', 'USD', -6618.000, '2025-10-16 11:31:23', '2025-11-25 05:55:34', 'active', 1),
(37, 1, '\r\nTOLO TRIP', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 1206.130, '2025-10-15 11:11:34', '2025-11-25 05:55:35', 'active', 1),
(38, 1, '\r\nFLY DUBAI (FZ)', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 1369.960, '2025-10-15 11:11:34', '2025-11-25 05:55:37', 'active', 1),
(39, 1, '\r\nHADITA', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 56.920, '2025-10-15 11:11:34', '2025-11-25 05:55:41', 'active', 1),
(40, 1, 'SALAM (PAR)', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 692.200, '2025-10-15 11:11:34', '2025-11-25 05:55:42', 'active', 1),
(41, 1, 'MASHALLAH TRAVEL AGENCY', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 0.000, '2025-10-15 11:11:34', '2025-11-25 05:55:44', 'active', 1),
(42, 1, 'NOOR WALI TRAVEL AGENCY', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', -3.000, '2025-10-15 11:11:34', '2025-11-25 05:55:45', 'active', 1),
(43, 1, 'AL MAJLAS TRAVEL AGENCY', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 0.000, '2025-10-15 11:11:34', '2025-11-25 05:55:47', 'active', 1),
(44, 1, 'SAFI ETEMAD TRAVEL PAK VISA PAYMENT', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'AFS', 0.000, '2025-10-15 11:11:34', '2025-11-25 05:55:49', 'active', 1),
(45, 1, 'AL WESAL TRAVEL AGENCY', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', -9.990, '2025-10-15 11:11:34', '2025-11-25 05:55:50', 'active', 1),
(46, 1, 'BAHRIA TRAVEL AGENCY', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 0.000, '2025-10-15 11:11:34', '2025-11-25 05:55:53', 'active', 1),
(47, 1, 'SKY TRAVELER', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 1093.590, '2025-10-15 11:11:34', '2025-11-25 05:55:55', 'active', 1),
(48, 1, 'CITY LAB', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'AFS', 0.000, '2025-10-15 11:11:34', '2025-11-25 05:55:57', 'active', 1),
(49, 1, 'AL MOQADAS TRAVEL UMRA PORTION', 'Wali', 'Internal', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 0.000, '2025-10-15 11:11:34', '2025-11-25 05:55:59', 'active', 1),
(50, 1, 'Shamshad Travel Agency', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 0.000, '2025-10-15 11:11:34', '2025-11-25 05:56:01', 'active', 1),
(51, 1, 'ZIA TRAVEL AGENCY', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', -15.000, '2025-10-15 11:11:34', '2025-11-25 05:56:03', 'active', 1),
(52, 1, 'SHIRZAD TRAVL AGINCY', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 0.000, '2025-10-15 11:11:34', '2025-11-25 05:56:05', 'active', 1),
(53, 1, 'RAHI BARAKAT', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', -6240.000, '2025-10-15 11:11:34', '2025-11-25 05:56:07', 'active', 1),
(54, 1, 'AL EKHLAS TRAVEL AGENCY', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', -1560.000, '2025-10-15 11:11:34', '2025-11-25 05:56:08', 'active', 1),
(55, 1, 'New Mustfa Travel Agency', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', -10.000, '2025-10-15 11:11:34', '2025-11-25 05:56:13', 'active', 1),
(56, 1, 'AIR ARABIA', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 0.000, '2025-10-15 11:11:34', '2025-11-25 05:56:18', 'active', 1),
(57, 2, 'KamAir', 'NAVEED RASHIQ', 'External', '0777305730', 'admin@abc-construction.com', 'test', 'USD', -1225.000, '2025-11-18 09:22:44', '2025-12-06 11:07:47', 'active', 2);

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
  `transaction_of` enum('ticket_sale','visa_sale','ticket_refund','date_change','fund','umrah','hotel','hotel_refund','ticket_reserve','jv_payment','visa_refund','hotel_refund','umrah_refund','additional_payment','weight_sale','supplier_bonus','fund_withdrawal','umrah_date_change','umrah_cancellation','visa_cancellation') NOT NULL,
  `amount` decimal(10,3) NOT NULL,
  `balance` decimal(15,3) NOT NULL,
  `remarks` text DEFAULT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `receipt` varchar(100) NOT NULL,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `supplier_transactions`
--

INSERT INTO `supplier_transactions` (`id`, `tenant_id`, `supplier_id`, `reference_id`, `transaction_type`, `transaction_of`, `amount`, `balance`, `remarks`, `transaction_date`, `updated_at`, `receipt`, `branch_id`) VALUES
(941, 2, 57, 400, 'Debit', 'ticket_sale', 100.000, -100.000, 'Base amount of 100 USD deducted for ticket booking for Mr FAZALHAQ PARDES with PNR: HAUPSE.', '2025-11-18 10:59:21', '2025-11-18 10:59:21', '', NULL),
(944, 2, 57, 403, 'Debit', 'ticket_sale', 120.000, -220.000, 'Base amount of 120 USD deducted for ticket booking for Mr SHIRIN AGHA MUTAWAKIL with PNR: WKEPD1.', '2025-11-19 04:01:51', '2025-11-19 04:01:51', '', NULL),
(945, 2, 57, 404, 'Debit', 'ticket_sale', 120.000, -340.000, 'Base amount of 120 USD deducted for ticket booking for Mr wali with PNR: SZQXJU.', '2025-11-19 04:15:31', '2025-11-19 04:15:31', '', NULL),
(946, 2, 57, 54, 'Debit', 'hotel', 10.000, -350.000, 'Hotel booking for Mr ROHULLAH SAFI', '2025-11-19 05:22:13', '2025-11-19 05:22:13', '', NULL),
(947, 2, 57, 55, 'Debit', 'hotel', 20.000, -370.000, 'Hotel booking for Mr ROHULLAH SAFI', '2025-11-19 05:26:52', '2025-11-19 05:26:52', '', NULL),
(948, 1, 37, 56, 'Debit', 'hotel', 40.000, 1206.130, 'Hotel booking for Mr ROHULLAH SAFI', '2025-11-19 06:04:03', '2025-11-19 06:04:03', '', NULL),
(949, 2, 57, 57, 'Debit', 'hotel', 15.000, -385.000, 'Hotel booking for Mr ROHULLAH SAFI', '2025-11-19 06:22:05', '2025-11-19 06:22:05', '', NULL),
(950, 2, 57, 68, 'Debit', 'umrah', 10.000, -395.000, 'Base amount of 10 USD deducted for umrah all.', '2025-11-19 06:28:16', '2025-11-19 06:28:16', '', NULL),
(951, 2, 57, 69, 'Debit', 'umrah', 10.000, -405.000, 'Base amount of 10 USD deducted for umrah all.', '2025-11-19 06:28:58', '2025-11-19 06:28:58', '', NULL),
(952, 2, 57, 58, 'Debit', 'hotel', 40.000, -445.000, 'Hotel booking for Mr ROHULLAH SAFI', '2025-11-19 06:33:32', '2025-11-19 06:33:32', '', NULL),
(954, 2, 57, 69, 'Debit', 'weight_sale', 20.000, -465.000, 'Base amount of 20 USD deducted for weight transaction.', '2025-11-19 06:57:37', '2025-11-19 07:18:16', '', NULL),
(955, 2, 57, 141, 'Credit', 'ticket_refund', 120.000, -345.000, 'Refund for ticket wali added to account.', '2025-11-19 06:58:11', '2025-11-19 07:18:16', '', NULL),
(956, 2, 57, 69, 'Debit', 'visa_sale', 10.000, -355.000, 'Visa purchase for guli - P8798765', '2025-11-19 07:03:56', '2025-11-19 07:18:16', '', NULL),
(957, 2, 57, 30, 'Debit', 'ticket_reserve', 10.000, -365.000, 'Base amount of 10 USD deducted for ticket reservation.', '2025-11-19 07:17:37', '2025-11-19 07:18:16', '', NULL),
(958, 2, 57, 142, 'Credit', 'ticket_refund', 100.000, -265.000, 'Refund for ticket FAZALHAQ PARDES added to account.', '2025-11-19 08:03:33', '2025-11-19 08:03:33', '', NULL),
(959, 2, 57, 16, 'Credit', 'hotel_refund', 40.000, -225.000, 'Refund for hotel booking #58 - test', '2025-11-19 09:30:00', '2025-11-19 09:30:00', '', NULL),
(960, 2, 57, 26, 'Credit', 'umrah_refund', 10.000, -215.000, 'Refund for umrah booking #68 - tet', '2025-11-19 09:31:42', '2025-11-19 09:31:42', '', NULL),
(961, 2, 57, 10, 'Credit', 'visa_refund', 10.000, -205.000, 'Refund for visa application #69 - tes', '2025-11-19 09:33:48', '2025-11-19 09:33:48', '', NULL),
(962, 2, 57, 4, 'Credit', 'jv_payment', 100.000, -105.000, 'Updated JV Payment: Received 100.000 USD from client DR SAHIB. Updated by: Matiullah Rahimi. test', '2025-11-19 10:02:41', '2025-11-19 10:02:56', '1213231', NULL),
(963, 2, 57, 405, 'Debit', 'ticket_sale', 100.000, -205.000, 'Base amount of 100 USD deducted for ticket booking for Mr AHMED ALI MUBARAK JAN with PNR: WKEPD1.', '2025-11-20 04:10:46', '2025-11-20 04:10:46', '', NULL),
(964, 2, 57, 406, 'Debit', 'ticket_sale', 100.000, -305.000, 'Base amount of 100 USD deducted for ticket booking for Mr ATIQ ULLAH ZAMAN with PNR: SZQXJU.', '2025-11-20 05:25:58', '2025-11-20 05:25:58', '', NULL),
(965, 2, 57, 407, 'Debit', 'ticket_sale', 100.000, -405.000, 'Base amount of 100 USD deducted for ticket booking for Mr IHSAMUDDIN ALIKHEL with PNR: SZQXJU.', '2025-11-20 05:25:58', '2025-11-20 05:25:58', '', NULL),
(967, 2, 57, 69, 'Credit', '', 10.000, -395.000, 'Bulk cancellation for umrah booking #69 - Bulk cancel - processed 2 members', '2025-11-22 07:25:31', '2025-11-24 05:14:09', '', NULL),
(968, 2, 57, 70, 'Credit', '', 100.000, -295.000, 'Bulk cancellation for umrah booking #70 - Bulk cancel - processed 2 members', '2025-11-22 07:25:31', '2025-11-24 05:14:09', '', NULL),
(972, 2, 57, 69, 'Credit', '', 10.000, -285.000, 'Cancel for umrah booking #69 - Bulk cancel - processed 2 members', '2025-11-22 07:34:18', '2025-11-24 05:14:09', '', NULL),
(973, 2, 57, 70, 'Credit', '', 100.000, -185.000, 'Cancel for umrah booking #70 - Bulk cancel - processed 2 members', '2025-11-22 07:34:18', '2025-11-24 05:14:09', '', NULL),
(974, 2, 57, 69, 'Debit', '', 10.000, -195.000, 'Reapply for umrah booking #69 - Bulk reapply - processed 2 members', '2025-11-22 07:34:38', '2025-11-24 05:14:09', '', NULL),
(975, 2, 57, 70, 'Debit', '', 100.000, -295.000, 'Reapply for umrah booking #70 - Bulk reapply - processed 2 members', '2025-11-22 07:34:38', '2025-11-24 05:14:09', '', NULL),
(976, 2, 57, 71, 'Debit', 'umrah', 100.000, -395.000, 'Base amount of 100 USD deducted for umrah all.', '2025-11-24 05:18:30', '2025-11-24 05:18:30', '', NULL),
(978, 2, 57, 69, 'Debit', '', 10.000, -395.000, 'Re-application reversal for visa #69 - test', '2025-11-24 06:01:49', '2025-11-24 06:01:49', '', NULL),
(979, 2, 57, 69, 'Debit', '', 10.000, -395.000, 'Re-application reversal for visa #69 - test', '2025-11-24 06:09:34', '2025-11-24 06:09:34', '', NULL),
(980, 2, 57, 69, 'Debit', '', 10.000, -395.000, 'Re-application reversal for visa #69 - test', '2025-11-24 06:12:26', '2025-11-24 06:12:26', '', NULL),
(981, 2, 57, 69, 'Credit', 'visa_cancellation', 10.000, -385.000, 'Cancellation reversal for visa application #69 - test', '2025-11-24 06:13:07', '2025-11-24 06:13:07', '', NULL),
(982, 2, 57, 69, 'Debit', 'visa_sale', 10.000, -385.000, 'Re-application reversal for visa #69 - test', '2025-11-24 06:14:07', '2025-11-24 06:14:48', '', NULL),
(983, 2, 57, 69, 'Credit', 'visa_cancellation', 10.000, -385.000, 'Cancellation reversal for visa application #69 - test1', '2025-11-24 06:17:35', '2025-11-24 06:17:35', '', NULL),
(984, 2, 57, 19, 'Credit', 'fund', 100.000, -285.000, 'Supplier: KamAir, Funded by main account: SELF BANK (SAFE), processed by: ROHULLAH SAFI, Remarks: test', '2025-12-06 06:31:44', '2025-12-06 06:31:44', '2452345', 2),
(986, 2, 57, 19, 'Credit', 'supplier_bonus', 100.000, -185.000, 'Bonus added to supplier: KamAir, processed by: ROHULLAH SAFI, Remarks: test', '2025-12-06 06:34:07', '2025-12-06 06:34:07', '6524652', 2),
(987, 2, 57, 19, 'Debit', 'fund_withdrawal', 100.000, -285.000, 'Supplier: KamAir, Withdrawn to main account: SELF BANK (SAFE), processed by: ROHULLAH SAFI, Remarks: test', '2025-12-06 06:34:32', '2025-12-06 06:34:32', '245234', 2),
(988, 2, 57, 19, 'Debit', 'fund_withdrawal', 100.000, -385.000, 'Supplier: KamAir, Withdrawn to main account: SELF BANK (SAFE), processed by: ROHULLAH SAFI, Remarks: test', '2025-12-06 06:36:46', '2025-12-06 06:36:46', '245234', 2),
(989, 2, 57, 19, 'Debit', 'fund_withdrawal', 100.000, -485.000, 'Supplier: KamAir, Withdrawn to main account: SELF BANK (SAFE), processed by: ROHULLAH SAFI, Remarks: test', '2025-12-06 06:37:58', '2025-12-06 06:37:58', '2452345', 2),
(990, 2, 57, 408, 'Debit', 'ticket_sale', 100.000, -585.000, 'Base amount of 100 USD deducted for ticket booking for Mr guli with PNR: SZQXJU.', '2025-12-06 08:23:48', '2025-12-06 08:23:48', '', 2),
(991, 2, 57, 409, 'Debit', 'ticket_sale', 100.000, -685.000, 'Base amount of 100 USD deducted for ticket booking for Mr guli with PNR: SZQXJU.', '2025-12-06 08:24:29', '2025-12-06 08:24:29', '', 2),
(992, 2, 57, 410, 'Debit', 'ticket_sale', 100.000, -785.000, 'Base amount of 100 USD deducted for ticket booking for Mr guli with PNR: SZQXJU.', '2025-12-06 08:26:44', '2025-12-06 08:26:44', '', 2),
(993, 2, 57, 411, 'Debit', 'ticket_sale', 100.000, -885.000, 'Base amount of 100 USD deducted for ticket booking for Mr guli with PNR: SZQXJU.', '2025-12-06 08:28:48', '2025-12-06 08:28:48', '', 2),
(994, 2, 57, 412, 'Debit', 'ticket_sale', 100.000, -985.000, 'Base amount of 100 USD deducted for ticket booking for Mr guli with PNR: SZQXJU.', '2025-12-06 08:29:20', '2025-12-06 08:29:20', '', 2),
(995, 2, 57, 413, 'Debit', 'ticket_sale', 100.000, -1085.000, 'Base amount of 100 USD deducted for ticket booking for Mr guli with PNR: SZQXJU.', '2025-12-06 08:29:46', '2025-12-06 08:29:46', '', 2),
(997, 2, 57, 97, 'Debit', 'date_change', 20.000, -1105.000, 'Penalty for ticket Name guli date change deducted from account', '2025-12-06 08:35:45', '2025-12-06 11:03:38', '', 2),
(998, 2, 57, 70, 'Debit', 'weight_sale', 10.000, -1115.000, 'Base amount of 10 USD deducted for weight transaction.', '2025-12-06 08:36:31', '2025-12-06 11:03:38', '', 2),
(1005, 2, 57, 420, 'Debit', 'ticket_sale', 100.000, -1215.000, 'Base amount of 100 USD deducted for ticket booking for Mr FAZALHAQ PARDES with PNR: RACSXS.', '2025-12-06 09:57:22', '2025-12-06 11:03:38', '', 2),
(1006, 2, 57, 421, 'Debit', 'ticket_sale', 110.000, -1325.000, 'Base amount of 110 USD deducted for ticket booking for Mr FAZALHAQ PARDES with PNR: 188JZ0.', '2025-12-06 10:47:52', '2025-12-06 11:03:38', '', 2),
(1007, 2, 57, 144, 'Credit', 'ticket_refund', 100.000, -1225.000, 'Refund for ticket FAZALHAQ PARDES added to account.', '2025-12-06 11:07:47', '2025-12-06 11:07:47', '', 2);

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
(1, 'Al Moqadas Travel Agency', 'alpha', 'tenant-alpha-001', 'active', 'enterprise', 'billing@alpha.com', 26214400, 'image/,video/,audio/,application/pdf,text/', 0, 'current', '2025-10-17', '2025-09-17', 0, NULL, '2025-06-30 19:30:00', '2025-11-16 04:39:09', NULL),
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `tenant_peering`
--

INSERT INTO `tenant_peering` (`id`, `tenant_id`, `peer_tenant_id`, `status`, `created_at`, `branch_id`) VALUES
(3, 2, 1, 'approved', '2025-09-08 08:13:20', NULL);

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
(1, 1, '3', 'active', 'monthly', '2025-07-23 19:30:00', NULL, 4500.00, 'AFN', 'stripe', '2025-10-28 19:30:00', '0000-00-00 00:00:00', 'txn_123456789', '2025-07-23 19:30:00', '2025-11-16 05:54:03'),
(2, 2, '3', 'active', 'yearly', '2025-07-31 19:30:00', NULL, 4500.00, 'USD', 'paypal', '2025-09-16 19:30:00', '0000-00-00 00:00:00', 'txn_987654321', '2025-07-31 19:30:00', '2025-11-16 11:08:54'),
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
  `branch_id` int(11) NOT NULL,
  `group_booking_id` int(11) DEFAULT NULL,
  `supplier` varchar(255) NOT NULL,
  `sold_to` int(10) NOT NULL,
  `paid_to` int(10) NOT NULL,
  `title` varchar(10) NOT NULL,
  `passenger_name` varchar(255) NOT NULL,
  `pnr` varchar(100) NOT NULL,
  `origin` varchar(100) NOT NULL,
  `destination` varchar(100) NOT NULL,
  `phone` varchar(15) DEFAULT NULL,
  `airline` varchar(100) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `issue_date` date NOT NULL,
  `departure_date` date NOT NULL,
  `departure_time` time DEFAULT NULL,
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
  `return_departure_time` time DEFAULT NULL,
  `return_origin` varchar(100) DEFAULT NULL,
  `return_destination` varchar(100) DEFAULT NULL,
  `created_by` int(20) NOT NULL,
  `imported` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_bookings`
--

INSERT INTO `ticket_bookings` (`id`, `tenant_id`, `branch_id`, `group_booking_id`, `supplier`, `sold_to`, `paid_to`, `title`, `passenger_name`, `pnr`, `origin`, `destination`, `phone`, `airline`, `gender`, `issue_date`, `departure_date`, `departure_time`, `currency`, `price`, `sold`, `discount`, `profit`, `status`, `receipt`, `description`, `created_at`, `updated_at`, `trip_type`, `return_date`, `return_departure_time`, `return_origin`, `return_destination`, `created_by`, `imported`) VALUES
(400, 2, 2, NULL, '57', 21, 17, 'Mr', 'FAZALHAQ PARDES', 'HAUPSE', ' KBL', 'FRA', '0766202122', 'EI', 'Male', '2025-11-18', '2025-12-04', NULL, 'USD', 100.000, 120.000, 0.000, 20.000, 'Date Changed', '', 'test', '2025-11-18 10:59:21', '2025-11-25 07:30:25', 'one_way', '0000-00-00', NULL, '', '', 19, 0),
(403, 2, 2, NULL, '57', 20, 17, 'Mr', 'SHIRIN AGHA MUTAWAKIL', 'WKEPD1', 'KBL ', 'DXB', '0773513830', 'EI', 'Male', '2025-11-19', '2025-11-28', NULL, 'USD', 120.000, 150.000, 0.000, 30.000, 'Booked', '', 'test', '2025-11-19 04:01:51', '2025-11-25 07:30:38', 'round_trip', '2025-11-28', NULL, NULL, 'KBL', 19, 0),
(404, 2, 2, NULL, '57', 21, 17, 'Mr', 'wali', 'SZQXJU', ' KBL', 'ELQ', '0700907993', 'KM', 'Male', '2025-11-19', '2025-12-05', NULL, 'USD', 120.000, 130.000, 0.000, 10.000, 'Refunded', '', 'test', '2025-11-19 04:15:31', '2025-11-25 07:30:44', 'one_way', '0000-00-00', NULL, '', '', 19, 0),
(406, 2, 2, NULL, '57', 20, 17, 'Mr', 'ATIQ ULLAH ZAMAN', 'SZQXJU', 'KBL ', 'ELQ', '0700907993', 'EI', 'Male', '2025-11-20', '2025-11-28', NULL, 'USD', 100.000, 110.000, 0.000, 10.000, 'Booked', '', 'test', '2025-11-20 05:25:58', '2025-11-25 07:30:50', 'one_way', '0000-00-00', NULL, NULL, '', 19, 0),
(407, 2, 2, NULL, '57', 20, 17, 'Mr', 'IHSAMUDDIN ALIKHEL', 'SZQXJU', 'KBL ', 'ELQ', '0776564368', 'EI', 'Male', '2025-11-20', '2025-11-28', NULL, 'USD', 100.000, 110.000, 0.000, 10.000, 'Booked', '', 'Ticket booked for Mr ATIQ ULLAH ZAMAN with PNR: SZQXJU from KBL  to ELQ.', '2025-11-20 05:25:58', '2025-12-06 08:07:35', 'one_way', '0000-00-00', NULL, '', '', 19, 0),
(408, 2, 2, NULL, '57', 21, 17, 'Mr', 'guli', 'SZQXJU', 'KBL ', 'FRA', '0771781576', 'A3', 'Male', '2025-12-06', '2025-12-08', NULL, 'USD', 100.000, 120.000, 0.000, 20.000, 'Booked', '', 'test', '2025-12-06 08:23:48', '2025-12-06 08:23:48', 'one_way', '0000-00-00', NULL, NULL, '', 19, 0),
(409, 2, 2, NULL, '57', 21, 17, 'Mr', 'guli', 'SZQXJU', 'KBL ', 'FRA', '0771781576', 'A3', 'Male', '2025-12-06', '2025-12-08', NULL, 'USD', 100.000, 120.000, 0.000, 20.000, 'Booked', '', 'test', '2025-12-06 08:24:29', '2025-12-06 08:24:29', 'one_way', '0000-00-00', NULL, NULL, '', 19, 0),
(410, 2, 2, NULL, '57', 21, 17, 'Mr', 'guli', 'SZQXJU', 'KBL ', 'FRA', '0771781576', 'A3', 'Male', '2025-12-06', '2025-12-08', NULL, 'USD', 100.000, 120.000, 0.000, 20.000, 'Booked', '', 'test', '2025-12-06 08:26:44', '2025-12-06 08:26:44', 'one_way', '0000-00-00', NULL, NULL, '', 19, 0),
(411, 2, 2, NULL, '57', 21, 17, 'Mr', 'guli', 'SZQXJU', 'KBL ', 'FRA', '0771781576', 'A3', 'Male', '2025-12-06', '2025-12-08', NULL, 'USD', 100.000, 120.000, 0.000, 20.000, 'Booked', '', 'test', '2025-12-06 08:28:48', '2025-12-06 08:28:48', 'one_way', '0000-00-00', NULL, NULL, '', 19, 0),
(412, 2, 2, NULL, '57', 21, 17, 'Mr', 'guli', 'SZQXJU', 'KBL ', 'FRA', '0771781576', 'A3', 'Male', '2025-12-06', '2025-12-08', NULL, 'USD', 100.000, 120.000, 0.000, 20.000, 'Booked', '', 'test', '2025-12-06 08:29:20', '2025-12-06 08:29:20', 'one_way', '0000-00-00', NULL, NULL, '', 19, 0),
(413, 2, 2, NULL, '57', 21, 17, 'Mr', 'guli', 'SZQXJU', 'KBL ', 'FRA', '0771781576', 'A3', 'Male', '2025-12-06', '2025-12-08', NULL, 'USD', 100.000, 120.000, 0.000, 20.000, 'Date Changed', '', 'test', '2025-12-06 08:29:46', '2025-12-06 08:35:45', 'one_way', '0000-00-00', NULL, NULL, '', 19, 0),
(420, 2, 2, NULL, '57', 21, 17, 'Mr', 'FAZALHAQ PARDES', 'RACSXS', 'VKO', 'LED', '0771781576', 'A3', 'Male', '2025-12-06', '2025-12-07', '14:23:00', 'USD', 100.000, 120.000, 0.000, 20.000, 'Booked', '', 'test', '2025-12-06 09:57:22', '2025-12-06 10:20:51', 'one_way', '0000-00-00', '00:00:00', '', '', 19, 0),
(421, 2, 2, NULL, '57', 21, 17, 'Mr', 'FAZALHAQ PARDES', '188JZ0', ' KBL', 'FRA', '0766202122', 'A3', 'Male', '2025-12-06', '2025-12-14', '15:16:00', 'USD', 110.000, 120.000, 0.000, 10.000, 'Refunded', '', 'test', '2025-12-06 10:47:52', '2025-12-06 11:07:47', 'one_way', '0000-00-00', '00:00:00', NULL, '', 19, 0);

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
  `phone` varchar(255) DEFAULT NULL,
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
  `created_by` int(50) NOT NULL,
  `imported` tinyint(1) NOT NULL DEFAULT 0,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_reservations`
--

INSERT INTO `ticket_reservations` (`id`, `tenant_id`, `supplier`, `sold_to`, `paid_to`, `title`, `passenger_name`, `pnr`, `origin`, `destination`, `phone`, `airline`, `gender`, `issue_date`, `departure_date`, `currency`, `price`, `sold`, `profit`, `status`, `receipt`, `description`, `created_at`, `updated_at`, `trip_type`, `return_date`, `return_origin`, `return_destination`, `created_by`, `imported`, `branch_id`) VALUES
(30, 2, '57', 20, 17, 'Mr', 'BAKHTIAR STANIKZAI', 'HAUPSE', 'KBL ', 'HAS', '0775172181', 'A3', 'Male', '2025-11-19', '2025-12-05', 'USD', 10.000, 20.000, 10.000, 'Reserved', '', 'test', '2025-11-19 07:17:37', '2025-11-25 06:55:56', 'one_way', '0000-00-00', NULL, '', 19, 0, 2);

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
  `updated_at` datetime DEFAULT NULL,
  `imported` tinyint(1) NOT NULL DEFAULT 0,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `ticket_weights`
--

INSERT INTO `ticket_weights` (`id`, `tenant_id`, `ticket_id`, `weight`, `base_price`, `sold_price`, `profit`, `remarks`, `created_by`, `created_at`, `updated_at`, `imported`, `branch_id`) VALUES
(70, 2, 413, 10.00, 10.00, 20.00, 10.00, 'test', 0, '2025-12-06 13:06:31', '2025-12-06 13:06:31', 0, 2);

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
  `used_at` datetime DEFAULT NULL,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `totp_recovery_codes`
--

INSERT INTO `totp_recovery_codes` (`id`, `tenant_id`, `user_id`, `user_type`, `recovery_code`, `is_used`, `created_at`, `used_at`, `branch_id`) VALUES
(49, 1, 1, 'staff', 'DTCY-YDG6-DGSM-AGGP', 0, '2025-11-01 11:58:54', NULL, NULL),
(50, 1, 1, 'staff', 'M79U-Q12B-IZ54-PZGI', 0, '2025-11-01 11:58:54', NULL, NULL),
(51, 1, 1, 'staff', 'SPNY-YYX8-69Q1-RTRQ', 0, '2025-11-01 11:58:54', NULL, NULL),
(52, 1, 1, 'staff', '6DSM-M6TI-R9I6-N30U', 0, '2025-11-01 11:58:54', NULL, NULL),
(53, 1, 1, 'staff', 'MFKH-PFN9-XVZR-MQ1E', 0, '2025-11-01 11:58:54', NULL, NULL),
(54, 1, 1, 'staff', 'HE8P-ZN3E-UBYN-N8ZC', 0, '2025-11-01 11:58:54', NULL, NULL),
(55, 1, 1, 'staff', 'WLPA-GMV5-K3PR-LYO3', 0, '2025-11-01 11:58:54', NULL, NULL),
(56, 1, 1, 'staff', 'HM4G-NPIV-B28F-8DFW', 0, '2025-11-01 11:58:54', NULL, NULL);

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
  `last_used` datetime DEFAULT NULL,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `totp_secrets`
--

INSERT INTO `totp_secrets` (`id`, `tenant_id`, `user_id`, `user_type`, `secret`, `is_enabled`, `created_at`, `last_used`, `branch_id`) VALUES
(11, 1, 1, 'staff', 'F4P6P43CGPWGHI34YM6BTKDKE56HFS45XEE2PSZNDLT65E33XEQS6GUVSTZZLHOPVTMNAKN3FQHOS56GMOUM4M5QQFRCI23DXIV3KWI', 0, '2025-11-01 11:58:54', NULL, NULL);

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
  `created_at` datetime NOT NULL,
  `branch_id` bigint(20) DEFAULT NULL
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
  `status` enum('active','refunded','cancelled') NOT NULL DEFAULT 'active',
  `imported` tinyint(1) NOT NULL DEFAULT 0,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `umrah_bookings`
--

INSERT INTO `umrah_bookings` (`booking_id`, `tenant_id`, `family_id`, `sold_to`, `paid_to`, `entry_date`, `name`, `fname`, `gfname`, `relation`, `dob`, `gender`, `passport_number`, `passport_expiry`, `id_type`, `flight_date`, `return_date`, `duration`, `room_type`, `price`, `sold_price`, `discount`, `profit`, `received_bank_payment`, `bank_receipt_number`, `paid`, `due`, `currency`, `created_by`, `created_at`, `updated_at`, `remarks`, `status`, `imported`, `branch_id`) VALUES
(68, 2, 17, 21, 17, '2025-11-19', 'KamAir', 'MOHAMMAD SIDDIQ AHMADZAI ', 'ESMAT ULLAH', 'Friend', '2025-11-19', 'Male', 'P07592390', '2026-06-05', 'ID Original + Passport Original', '0000-00-00', '0000-00-00', '5 Days', '1 Bed', 10.000, 20.000, 0.000, 10.000, 0.000, '', 10.000, 0.000, 'USD', 19, '2025-11-19 06:28:08', '2025-11-25 07:41:36', 'test', 'active', 0, 2),
(69, 2, 17, 21, 17, '2025-11-19', 'KamAir', 'MOHAMMAD SIDDIQ AHMADZAI', 'ESMAT ULLAH', 'Friend', '2025-11-19', 'Male', 'P07592390', '2026-06-05', 'ID Original + Passport Original', NULL, NULL, '5 Days', '1 Bed', 10.000, 20.000, 0.000, 10.000, 0.000, '', 10.000, 10.000, 'USD', 19, '2025-11-19 06:28:55', '2025-11-25 06:09:39', 'test', 'active', 0, 2),
(71, 2, 17, 21, 17, '2025-11-24', 'Idrees', 'RAHIMI', 'ESMAT ULLAH', 'Uncle', '2025-11-24', 'Male', 'P09696377', '2026-06-04', 'ID Original + Passport Original', NULL, NULL, '18 Days', '1 Bed', 100.000, 150.000, 0.000, 50.000, 0.000, '', 0.000, 150.000, 'USD', 19, '2025-11-24 05:18:25', '2025-11-25 06:09:43', 'test', 'active', 0, 2);

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `umrah_booking_services`
--

INSERT INTO `umrah_booking_services` (`id`, `tenant_id`, `booking_id`, `service_type`, `supplier_id`, `base_price`, `sold_price`, `profit`, `currency`, `created_at`, `updated_at`, `branch_id`) VALUES
(36, 2, 68, 'all', 57, 10.000, 20.000, 10.000, 'USD', '2025-11-19 06:28:08', '2025-11-25 05:58:54', 2),
(38, 2, 69, 'all', 57, 10.000, 20.000, 10.000, 'USD', '2025-11-20 10:17:41', '2025-11-25 05:58:56', 2),
(41, 2, 71, 'all', 57, 100.000, 150.000, 50.000, 'USD', '2025-11-24 05:19:53', '2025-11-25 05:58:58', 2);

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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `umrah_refunds`
--

INSERT INTO `umrah_refunds` (`id`, `tenant_id`, `booking_id`, `refund_type`, `refund_amount`, `reason`, `currency`, `processed`, `processed_by`, `transaction_id`, `created_at`, `updated_at`, `branch_id`) VALUES
(26, 2, 68, 'full', 20.00, 'tet', 'USD', 0, NULL, NULL, '2025-11-19 09:31:42', '2025-11-25 05:59:03', 2);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `umrah_transactions`
--

INSERT INTO `umrah_transactions` (`id`, `tenant_id`, `umrah_booking_id`, `transaction_type`, `transaction_to`, `payment_date`, `payment_description`, `payment_amount`, `receipt`, `currency`, `exchange_rate`, `created_at`, `branch_id`) VALUES
(97, 2, 68, 'Credit', 'Internal Account', '2025-11-19', 'test', 10.000, '', 'USD', NULL, '2025-11-19 09:31:12', NULL),
(99, 2, 69, 'Credit', 'Internal Account', '2025-11-24', '', 10.000, '', 'USD', NULL, '2025-11-24 05:12:22', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `branch_id` int(11) DEFAULT NULL,
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

INSERT INTO `users` (`id`, `tenant_id`, `branch_id`, `name`, `email`, `password`, `created_at`, `role`, `phone`, `address`, `hire_date`, `profile_pic`, `totp_enabled`, `fired`, `fired_at`, `updated_at`, `deleted_at`) VALUES
(1, 1, 1, 'SABAOON CAR REPAIR', 'almuqadas_travel@yahoo.com', '$2y$10$McogSKPqbHafcuzb.fOiLexQrCt2CR.EUCDNxT3vD/SNDC.P2fWA2', '2024-12-24 13:11:23', 'admin', '0786011115', 'kabul, jada-ee-mewand', '2024-12-04', '68f74bc0ade30_White and Blue Modern Travel Poster.png', 0, 0, NULL, NULL, NULL),
(6, 1, 1, 'Idrees', 'idress@gmail.com', '$2y$10$LJc9TC9ekIpXNSag3kIWu.d45oVeJLbp9zP0a1a04b6lZOo/CltH6', '2025-04-09 10:29:29', 'sales', '0777555594', 'Jada-e-Maiwand', '2025-04-09', '6905da1a0e1d6_WhatsApp Image 2025-10-27 at 11.31.26 AM.jpeg', 0, 0, '2025-10-20 09:12:42', NULL, NULL),
(7, 2, NULL, 'Matiullah Rahimi', 'fluentcenter01@gmail.com', '$2y$10$Yr61g65gW/w8UjFfG3kLmephVrFKf2m6zGqMrTdgvmPcy8pg/Bv/e', '2025-04-09 10:30:25', 'tenant_super_admin', '0777555594', 'Jada-e-Maiwand', '2025-04-09', '68bd507105c6e_Blue and White Grunge Travel and Tourism Instagram Post.png', 0, 0, '2025-09-01 13:22:36', NULL, NULL),
(14, NULL, NULL, 'ALLAH DAD MUHAMMADI', 'allahdadmuhammadi01@gmail.com', '$2y$10$5BbWc37e43gokcY5etVUauiZZFP/uLeYQrGFJaUkrEGSxvvXnsQnS', '2025-05-19 08:31:47', 'super_admin', '0780310431', 'KABUL AFGHANISTAN', '2025-05-19', '682aadaaef90b.jpg', 0, 0, NULL, NULL, NULL),
(18, 1, 1, 'Matiullah', 'matiullahr970@gmail.com', '$2y$10$fh.T7oUwq/iPapaztUlf6.XSUBsCbbn2bRtEeHGiPY3Yd.chZ1iEa', '2025-10-29 16:28:44', 'finance', '0777305730', 'test', '2025-10-29', '690307f9d794a_umrah.png', 0, 0, NULL, '2025-10-29 16:28:44', NULL),
(19, 2, 2, 'ROHULLAH SAFI', 'fluentcenter001@gmail.com', '$2y$10$fUD3BMxFpn4mVHII1ZuUne..eViusEEvYE/Z7sozSSaXyyfpotbbi', '2025-11-24 14:44:55', 'admin', '0786011115', 'Jada-e-Maiwand', NULL, 'assets/images/user/avatar-2.jpg', 0, 0, NULL, NULL, NULL);

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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `uploaded_at` datetime NOT NULL,
  `branch_id` bigint(20) DEFAULT NULL
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `phone` varchar(20) DEFAULT '',
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
  `created_by` int(50) NOT NULL,
  `imported` tinyint(1) NOT NULL DEFAULT 0,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `visa_applications`
--

INSERT INTO `visa_applications` (`id`, `tenant_id`, `supplier`, `sold_to`, `paid_to`, `phone`, `title`, `gender`, `applicant_name`, `passport_number`, `country`, `visa_type`, `receive_date`, `applied_date`, `issued_date`, `base`, `sold`, `profit`, `currency`, `status`, `remarks`, `created_at`, `updated_at`, `created_by`, `imported`, `branch_id`) VALUES
(69, 2, 57, 21, 17, '0771781576', 'Mr', 'Male', 'guli', 'P8798765', 'Qatar', 'Medical', '2025-11-19', '2025-11-21', '0000-00-00', 10.000, 20.000, 10.000, 'USD', 'Pending', 'test\n[CANCELLED] Cancelled: test | 2025-11-24 10:20:56\n[RE-APPLIED] Re-applied: test | 2025-11-24 10:31:49\n[CANCELLED] Cancelled: test | 2025-11-24 10:37:56\n[RE-APPLIED] Re-applied: test | 2025-11-24 10:39:34\n[CANCELLED] Cancelled: test | 2025-11-24 10:40:29\n[RE-APPLIED] Re-applied: test | 2025-11-24 10:42:26\n[CANCELLED] Cancelled: test | 2025-11-24 10:43:07\n[RE-APPLIED] Re-applied: test | 2025-11-24 10:44:07\n[CANCELLED] Cancelled: test1 | 2025-11-24 10:47:35', '2025-11-19 07:03:56', '2025-11-25 07:42:27', 19, 0, 2);

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
  `transaction_id` int(11) DEFAULT NULL,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `visa_refunds`
--

INSERT INTO `visa_refunds` (`id`, `tenant_id`, `visa_id`, `refund_type`, `refund_amount`, `reason`, `currency`, `refund_date`, `processed`, `processed_by`, `transaction_id`, `branch_id`) VALUES
(10, 2, 69, 'full', 20.00, 'tes', 'USD', '2025-11-19 14:03:48', 0, NULL, NULL, 2);

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_analytics`
--

CREATE TABLE `whatsapp_analytics` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `date` date NOT NULL COMMENT 'Date for statistics',
  `message_type` varchar(50) NOT NULL COMMENT 'Type: visa, umrah, hotel, etc',
  `total_sent` int(11) NOT NULL DEFAULT 0 COMMENT 'Total messages sent',
  `total_delivered` int(11) NOT NULL DEFAULT 0 COMMENT 'Total messages delivered',
  `total_failed` int(11) NOT NULL DEFAULT 0 COMMENT 'Total messages failed',
  `total_read` int(11) NOT NULL DEFAULT 0 COMMENT 'Total messages read by recipients',
  `delivery_rate` decimal(5,2) DEFAULT 0.00 COMMENT 'Delivery rate percentage',
  `read_rate` decimal(5,2) DEFAULT 0.00 COMMENT 'Read rate percentage',
  `avg_response_time` int(11) DEFAULT 0 COMMENT 'Average response time in minutes',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_delivery_status`
--

CREATE TABLE `whatsapp_delivery_status` (
  `id` int(11) NOT NULL,
  `message_id` int(11) NOT NULL,
  `provider_message_id` varchar(100) NOT NULL,
  `status` enum('sent','delivered','read','failed') NOT NULL,
  `status_message` text DEFAULT NULL COMMENT 'Human readable status message',
  `delivery_timestamp` timestamp NOT NULL DEFAULT current_timestamp(),
  `raw_response` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Raw API response from provider' CHECK (json_valid(`raw_response`)),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_messages`
--

CREATE TABLE `whatsapp_messages` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `phone_number` varchar(20) NOT NULL COMMENT 'Recipient phone number with country code',
  `message` longtext NOT NULL COMMENT 'Complete message content',
  `message_type` varchar(50) NOT NULL COMMENT 'Type: visa, umrah, hotel, refund, etc',
  `reference_id` int(11) NOT NULL COMMENT 'Reference to booking ID (visa_id, booking_id, etc)',
  `template_variables` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Template variables used for message generation' CHECK (json_valid(`template_variables`)),
  `status` enum('pending','sent','delivered','failed','expired') NOT NULL DEFAULT 'pending',
  `provider_message_id` varchar(100) DEFAULT NULL COMMENT 'Message ID from WhatsApp provider',
  `error_message` text DEFAULT NULL COMMENT 'Error message if sending failed',
  `retry_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of retry attempts',
  `priority` int(11) NOT NULL DEFAULT 5 COMMENT 'Message priority (1=highest, 10=lowest)',
  `scheduled_at` timestamp NULL DEFAULT NULL COMMENT 'When to send the message (for scheduled messages)',
  `sent_at` timestamp NULL DEFAULT NULL COMMENT 'When the message was actually sent',
  `delivered_at` timestamp NULL DEFAULT NULL COMMENT 'When message was delivered to recipient',
  `failed_at` timestamp NULL DEFAULT NULL COMMENT 'When message failed to send',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `whatsapp_messages`
--

INSERT INTO `whatsapp_messages` (`id`, `tenant_id`, `phone_number`, `message`, `message_type`, `reference_id`, `template_variables`, `status`, `provider_message_id`, `error_message`, `retry_count`, `priority`, `scheduled_at`, `sent_at`, `delivered_at`, `failed_at`, `created_at`, `updated_at`, `branch_id`) VALUES
(1, 2, '+93780310431', 'Hello! This is a test message from your WhatsApp automation system.', 'test', 0, NULL, 'sent', NULL, NULL, 0, 5, NULL, '2025-11-23 09:11:20', NULL, NULL, '2025-11-23 09:08:27', '2025-11-23 09:11:20', NULL),
(2, 2, '0780310431', 'Hello! This is a test message from your WhatsApp automation system.', 'test', 0, NULL, 'sent', NULL, NULL, 0, 5, NULL, '2025-11-23 09:11:20', NULL, NULL, '2025-11-23 09:09:23', '2025-11-23 09:11:20', NULL),
(3, 2, '93780310431', 'Hello! This is a test message from your WhatsApp automation system.', 'test', 0, NULL, 'failed', NULL, NULL, 0, 5, NULL, NULL, NULL, NULL, '2025-11-23 09:52:27', '2025-11-23 09:52:27', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_settings`
--

CREATE TABLE `whatsapp_settings` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `provider` varchar(50) NOT NULL DEFAULT 'twilio' COMMENT 'whatsapp provider (twilio, messagebird, etc)',
  `api_token` text NOT NULL COMMENT 'API authentication token',
  `phone_number_id` varchar(100) NOT NULL COMMENT 'WhatsApp Business phone number ID',
  `webhook_verify_token` varchar(100) NOT NULL COMMENT 'Webhook verification token for incoming messages',
  `webhook_url` text DEFAULT NULL COMMENT 'Webhook URL for receiving message status updates',
  `status` enum('active','inactive','testing') NOT NULL DEFAULT 'inactive',
  `auto_notifications` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Automatically send notifications for new bookings',
  `real_time_notifications` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Send notifications immediately or queue them',
  `max_messages_per_hour` int(11) NOT NULL DEFAULT 1000 COMMENT 'Rate limiting - max messages per hour',
  `retry_attempts` int(11) NOT NULL DEFAULT 3 COMMENT 'Number of retry attempts for failed messages',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `whatsapp_settings`
--

INSERT INTO `whatsapp_settings` (`id`, `tenant_id`, `provider`, `api_token`, `phone_number_id`, `webhook_verify_token`, `webhook_url`, `status`, `auto_notifications`, `real_time_notifications`, `max_messages_per_hour`, `retry_attempts`, `created_at`, `updated_at`) VALUES
(1, 2, 'meta', 'provider meta status active api_token EAAT2LElKw64BQEooBfZAj0ZCA4RP7anuKh1XOpAn9SuZCCK8CeyGsnSVKtoTere00wuRLZBoWTjudVfp9HDPOgX59EEzp4zdWaxyAXqEZCsJyO7HC1ndVkpUEfrlyE1hfKJSDkBUYdZAcgWnXe6FZCsWbP9lxcBG3UvXqRiU0ZASZB6mkUeibMzkqLdPgaez4xDeHma8ox0ZBZCHWQbBacFtA5dYZClh4aW30Ht0KdJ9uuKCFCHivkKkiYN1bnssFYn48XbSQuoWT5ZAYUI8xfvhWobpM3Xgb phone_number_id 866031719930862 webhook_verify_token test max_messages_per_hour 1000 retry_attempts 3 auto_notifications on action update_settings', '866031719930862', 'test', 'test', 'active', 1, 0, 1000, 3, '2025-11-23 08:33:30', '2025-11-26 06:52:25');

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_templates`
--

CREATE TABLE `whatsapp_templates` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL COMMENT 'Template identifier name',
  `template_type` varchar(50) NOT NULL COMMENT 'Type: visa, umrah, hotel, refund, etc',
  `language` varchar(10) NOT NULL DEFAULT 'en' COMMENT 'Template language (en, fa, ps)',
  `message_template` longtext NOT NULL COMMENT 'Message template with {{variables}}',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dumping data for table `whatsapp_templates`
--

INSERT INTO `whatsapp_templates` (`id`, `tenant_id`, `template_name`, `template_type`, `language`, `message_template`, `status`, `created_by`, `created_at`, `updated_at`, `branch_id`) VALUES
(1, 1, 'default_visa_en', 'visa', 'en', '🛂 *New Visa Application*\n\nHello {{client_name}},\n\nYour visa application has been successfully processed:\n\n👤 Applicant: {{applicant_name}}\n📋 Passport: {{passport_number}}\n🌍 Country: {{country}}\n📄 Type: {{visa_type}}\n💰 Amount: {{amount}}\n\n📅 Date: {{booking_date}}\n\nThank you for choosing {{agency_name}}!\n📞 Contact: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(2, 2, 'default_visa_en', 'visa', 'en', '🛂 *New Visa Application*\n\nHello {{client_name}},\n\nYour visa application has been successfully processed:\n\n👤 Applicant: {{applicant_name}}\n📋 Passport: {{passport_number}}\n🌍 Country: {{country}}\n📄 Type: {{visa_type}}\n💰 Amount: {{amount}}\n\n📅 Date: {{booking_date}}\n\nThank you for choosing {{agency_name}}!\n📞 Contact: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(3, 4, 'default_visa_en', 'visa', 'en', '🛂 *New Visa Application*\n\nHello {{client_name}},\n\nYour visa application has been successfully processed:\n\n👤 Applicant: {{applicant_name}}\n📋 Passport: {{passport_number}}\n🌍 Country: {{country}}\n📄 Type: {{visa_type}}\n💰 Amount: {{amount}}\n\n📅 Date: {{booking_date}}\n\nThank you for choosing {{agency_name}}!\n📞 Contact: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(4, 5, 'default_visa_en', 'visa', 'en', '🛂 *New Visa Application*\n\nHello {{client_name}},\n\nYour visa application has been successfully processed:\n\n👤 Applicant: {{applicant_name}}\n📋 Passport: {{passport_number}}\n🌍 Country: {{country}}\n📄 Type: {{visa_type}}\n💰 Amount: {{amount}}\n\n📅 Date: {{booking_date}}\n\nThank you for choosing {{agency_name}}!\n📞 Contact: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(5, 3, 'default_visa_en', 'visa', 'en', '🛂 *New Visa Application*\n\nHello {{client_name}},\n\nYour visa application has been successfully processed:\n\n👤 Applicant: {{applicant_name}}\n📋 Passport: {{passport_number}}\n🌍 Country: {{country}}\n📄 Type: {{visa_type}}\n💰 Amount: {{amount}}\n\n📅 Date: {{booking_date}}\n\nThank you for choosing {{agency_name}}!\n📞 Contact: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(6, 6, 'default_visa_en', 'visa', 'en', '🛂 *New Visa Application*\n\nHello {{client_name}},\n\nYour visa application has been successfully processed:\n\n👤 Applicant: {{applicant_name}}\n📋 Passport: {{passport_number}}\n🌍 Country: {{country}}\n📄 Type: {{visa_type}}\n💰 Amount: {{amount}}\n\n📅 Date: {{booking_date}}\n\nThank you for choosing {{agency_name}}!\n📞 Contact: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(7, 1, 'default_umrah_en', 'umrah', 'en', '🕌 *Umrah Booking Confirmation*\n\nAssalamu Alaikum {{client_name}},\n\nYour Umrah booking has been confirmed:\n\n👤 Member: {{member_name}}\n📦 Package: {{package_type}}\n✈️ Flight Date: {{flight_date}}\n💰 Amount: {{amount}}\n\n📅 Booking Date: {{booking_date}}\n\nMay Allah accept your Umrah!\n📞 Contact: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(8, 2, 'default_umrah_en', 'umrah', 'en', '🕌 *Umrah Booking Confirmation*\n\nAssalamu Alaikum {{client_name}},\n\nYour Umrah booking has been confirmed:\n\n👤 Member: {{member_name}}\n📦 Package: {{package_type}}\n✈️ Flight Date: {{flight_date}}\n💰 Amount: {{amount}}\n\n📅 Booking Date: {{booking_date}}\n\nMay Allah accept your Umrah!\n📞 Contact: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(9, 4, 'default_umrah_en', 'umrah', 'en', '🕌 *Umrah Booking Confirmation*\n\nAssalamu Alaikum {{client_name}},\n\nYour Umrah booking has been confirmed:\n\n👤 Member: {{member_name}}\n📦 Package: {{package_type}}\n✈️ Flight Date: {{flight_date}}\n💰 Amount: {{amount}}\n\n📅 Booking Date: {{booking_date}}\n\nMay Allah accept your Umrah!\n📞 Contact: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(10, 5, 'default_umrah_en', 'umrah', 'en', '🕌 *Umrah Booking Confirmation*\n\nAssalamu Alaikum {{client_name}},\n\nYour Umrah booking has been confirmed:\n\n👤 Member: {{member_name}}\n📦 Package: {{package_type}}\n✈️ Flight Date: {{flight_date}}\n💰 Amount: {{amount}}\n\n📅 Booking Date: {{booking_date}}\n\nMay Allah accept your Umrah!\n📞 Contact: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(11, 3, 'default_umrah_en', 'umrah', 'en', '🕌 *Umrah Booking Confirmation*\n\nAssalamu Alaikum {{client_name}},\n\nYour Umrah booking has been confirmed:\n\n👤 Member: {{member_name}}\n📦 Package: {{package_type}}\n✈️ Flight Date: {{flight_date}}\n💰 Amount: {{amount}}\n\n📅 Booking Date: {{booking_date}}\n\nMay Allah accept your Umrah!\n📞 Contact: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(12, 6, 'default_umrah_en', 'umrah', 'en', '🕌 *Umrah Booking Confirmation*\n\nAssalamu Alaikum {{client_name}},\n\nYour Umrah booking has been confirmed:\n\n👤 Member: {{member_name}}\n📦 Package: {{package_type}}\n✈️ Flight Date: {{flight_date}}\n💰 Amount: {{amount}}\n\n📅 Booking Date: {{booking_date}}\n\nMay Allah accept your Umrah!\n📞 Contact: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(13, 1, 'default_hotel_en', 'hotel', 'en', '🏨 *Hotel Booking Confirmation*\n\nHello {{client_name}},\n\nYour hotel booking is confirmed:\n\n👤 Guest: {{guest_name}}\n🏨 Hotel: {{hotel_name}}\n📅 Check-in: {{check_in}}\n📅 Check-out: {{check_out}}\n💰 Amount: {{amount}}\n\n📅 Booking Date: {{booking_date}}\n\nThank you for choosing {{agency_name}}!\n📞 Contact: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(14, 2, 'default_hotel_en', 'hotel', 'en', '🏨 *Hotel Booking Confirmation*\n\nHello {{client_name}},\n\nYour hotel booking is confirmed:\n\n👤 Guest: {{guest_name}}\n🏨 Hotel: {{hotel_name}}\n📅 Check-in: {{check_in}}\n📅 Check-out: {{check_out}}\n💰 Amount: {{amount}}\n\n📅 Booking Date: {{booking_date}}\n\nThank you for choosing {{agency_name}}!\n📞 Contact: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(15, 4, 'default_hotel_en', 'hotel', 'en', '🏨 *Hotel Booking Confirmation*\n\nHello {{client_name}},\n\nYour hotel booking is confirmed:\n\n👤 Guest: {{guest_name}}\n🏨 Hotel: {{hotel_name}}\n📅 Check-in: {{check_in}}\n📅 Check-out: {{check_out}}\n💰 Amount: {{amount}}\n\n📅 Booking Date: {{booking_date}}\n\nThank you for choosing {{agency_name}}!\n📞 Contact: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(16, 5, 'default_hotel_en', 'hotel', 'en', '🏨 *Hotel Booking Confirmation*\n\nHello {{client_name}},\n\nYour hotel booking is confirmed:\n\n👤 Guest: {{guest_name}}\n🏨 Hotel: {{hotel_name}}\n📅 Check-in: {{check_in}}\n📅 Check-out: {{check_out}}\n💰 Amount: {{amount}}\n\n📅 Booking Date: {{booking_date}}\n\nThank you for choosing {{agency_name}}!\n📞 Contact: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(17, 3, 'default_hotel_en', 'hotel', 'en', '🏨 *Hotel Booking Confirmation*\n\nHello {{client_name}},\n\nYour hotel booking is confirmed:\n\n👤 Guest: {{guest_name}}\n🏨 Hotel: {{hotel_name}}\n📅 Check-in: {{check_in}}\n📅 Check-out: {{check_out}}\n💰 Amount: {{amount}}\n\n📅 Booking Date: {{booking_date}}\n\nThank you for choosing {{agency_name}}!\n📞 Contact: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(18, 6, 'default_hotel_en', 'hotel', 'en', '🏨 *Hotel Booking Confirmation*\n\nHello {{client_name}},\n\nYour hotel booking is confirmed:\n\n👤 Guest: {{guest_name}}\n🏨 Hotel: {{hotel_name}}\n📅 Check-in: {{check_in}}\n📅 Check-out: {{check_out}}\n💰 Amount: {{amount}}\n\n📅 Booking Date: {{booking_date}}\n\nThank you for choosing {{agency_name}}!\n📞 Contact: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(19, 1, 'default_visa_fa', 'visa', 'fa', '🛂 *درخواست ویزا جدید*\n\nسلام {{client_name}}،\n\nدرخواست ویزای شما با موفقیت پردازش شده است:\n\n👤 متقاضی: {{applicant_name}}\n📋 شماره گذرنامه: {{passport_number}}\n🌍 کشور: {{country}}\n📄 نوع: {{visa_type}}\n💰 مبلغ: {{amount}}\n\n📅 تاریخ: {{booking_date}}\n\nبا تشکر از انتخاب {{agency_name}}!\n📞 تماس: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(20, 2, 'default_visa_fa', 'visa', 'fa', '🛂 *درخواست ویزا جدید*\n\nسلام {{client_name}}،\n\nدرخواست ویزای شما با موفقیت پردازش شده است:\n\n👤 متقاضی: {{applicant_name}}\n📋 شماره گذرنامه: {{passport_number}}\n🌍 کشور: {{country}}\n📄 نوع: {{visa_type}}\n💰 مبلغ: {{amount}}\n\n📅 تاریخ: {{booking_date}}\n\nبا تشکر از انتخاب {{agency_name}}!\n📞 تماس: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(21, 4, 'default_visa_fa', 'visa', 'fa', '🛂 *درخواست ویزا جدید*\n\nسلام {{client_name}}،\n\nدرخواست ویزای شما با موفقیت پردازش شده است:\n\n👤 متقاضی: {{applicant_name}}\n📋 شماره گذرنامه: {{passport_number}}\n🌍 کشور: {{country}}\n📄 نوع: {{visa_type}}\n💰 مبلغ: {{amount}}\n\n📅 تاریخ: {{booking_date}}\n\nبا تشکر از انتخاب {{agency_name}}!\n📞 تماس: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(22, 5, 'default_visa_fa', 'visa', 'fa', '🛂 *درخواست ویزا جدید*\n\nسلام {{client_name}}،\n\nدرخواست ویزای شما با موفقیت پردازش شده است:\n\n👤 متقاضی: {{applicant_name}}\n📋 شماره گذرنامه: {{passport_number}}\n🌍 کشور: {{country}}\n📄 نوع: {{visa_type}}\n💰 مبلغ: {{amount}}\n\n📅 تاریخ: {{booking_date}}\n\nبا تشکر از انتخاب {{agency_name}}!\n📞 تماس: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(23, 3, 'default_visa_fa', 'visa', 'fa', '🛂 *درخواست ویزا جدید*\n\nسلام {{client_name}}،\n\nدرخواست ویزای شما با موفقیت پردازش شده است:\n\n👤 متقاضی: {{applicant_name}}\n📋 شماره گذرنامه: {{passport_number}}\n🌍 کشور: {{country}}\n📄 نوع: {{visa_type}}\n💰 مبلغ: {{amount}}\n\n📅 تاریخ: {{booking_date}}\n\nبا تشکر از انتخاب {{agency_name}}!\n📞 تماس: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(24, 6, 'default_visa_fa', 'visa', 'fa', '🛂 *درخواست ویزا جدید*\n\nسلام {{client_name}}،\n\nدرخواست ویزای شما با موفقیت پردازش شده است:\n\n👤 متقاضی: {{applicant_name}}\n📋 شماره گذرنامه: {{passport_number}}\n🌍 کشور: {{country}}\n📄 نوع: {{visa_type}}\n💰 مبلغ: {{amount}}\n\n📅 تاریخ: {{booking_date}}\n\nبا تشکر از انتخاب {{agency_name}}!\n📞 تماس: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(25, 1, 'default_umrah_fa', 'umrah', 'fa', '🕌 *تایید رزرو عمره*\n\nالسلام علیکم {{client_name}}،\n\nرزرو عمره شما تایید شده است:\n\n👤 عضو: {{member_name}}\n📦 بسته: {{package_type}}\n✈️ تاریخ پرواز: {{flight_date}}\n💰 مبلغ: {{amount}}\n\n📅 تاریخ رزرو: {{booking_date}}\n\nخدا عمره شما را قبول کند!\n📞 تماس: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(26, 2, 'default_umrah_fa', 'umrah', 'fa', '🕌 *تایید رزرو عمره*\n\nالسلام علیکم {{client_name}}،\n\nرزرو عمره شما تایید شده است:\n\n👤 عضو: {{member_name}}\n📦 بسته: {{package_type}}\n✈️ تاریخ پرواز: {{flight_date}}\n💰 مبلغ: {{amount}}\n\n📅 تاریخ رزرو: {{booking_date}}\n\nخدا عمره شما را قبول کند!\n📞 تماس: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(27, 4, 'default_umrah_fa', 'umrah', 'fa', '🕌 *تایید رزرو عمره*\n\nالسلام علیکم {{client_name}}،\n\nرزرو عمره شما تایید شده است:\n\n👤 عضو: {{member_name}}\n📦 بسته: {{package_type}}\n✈️ تاریخ پرواز: {{flight_date}}\n💰 مبلغ: {{amount}}\n\n📅 تاریخ رزرو: {{booking_date}}\n\nخدا عمره شما را قبول کند!\n📞 تماس: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(28, 5, 'default_umrah_fa', 'umrah', 'fa', '🕌 *تایید رزرو عمره*\n\nالسلام علیکم {{client_name}}،\n\nرزرو عمره شما تایید شده است:\n\n👤 عضو: {{member_name}}\n📦 بسته: {{package_type}}\n✈️ تاریخ پرواز: {{flight_date}}\n💰 مبلغ: {{amount}}\n\n📅 تاریخ رزرو: {{booking_date}}\n\nخدا عمره شما را قبول کند!\n📞 تماس: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(29, 3, 'default_umrah_fa', 'umrah', 'fa', '🕌 *تایید رزرو عمره*\n\nالسلام علیکم {{client_name}}،\n\nرزرو عمره شما تایید شده است:\n\n👤 عضو: {{member_name}}\n📦 بسته: {{package_type}}\n✈️ تاریخ پرواز: {{flight_date}}\n💰 مبلغ: {{amount}}\n\n📅 تاریخ رزرو: {{booking_date}}\n\nخدا عمره شما را قبول کند!\n📞 تماس: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(30, 6, 'default_umrah_fa', 'umrah', 'fa', '🕌 *تایید رزرو عمره*\n\nالسلام علیکم {{client_name}}،\n\nرزرو عمره شما تایید شده است:\n\n👤 عضو: {{member_name}}\n📦 بسته: {{package_type}}\n✈️ تاریخ پرواز: {{flight_date}}\n💰 مبلغ: {{amount}}\n\n📅 تاریخ رزرو: {{booking_date}}\n\nخدا عمره شما را قبول کند!\n📞 تماس: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(31, 1, 'default_hotel_fa', 'hotel', 'fa', '🏨 *تایید رزرو هتل*\n\nسلام {{client_name}}،\n\nرزرو هتل شما تایید شده است:\n\n👤 مهمان: {{guest_name}}\n🏨 هتل: {{hotel_name}}\n📅 ورود: {{check_in}}\n📅 خروج: {{check_out}}\n💰 مبلغ: {{amount}}\n\n📅 تاریخ رزرو: {{booking_date}}\n\nبا تشکر از انتخاب {{agency_name}}!\n📞 تماس: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(32, 2, 'default_hotel_fa', 'hotel', 'fa', '🏨 *تایید رزرو هتل*\n\nسلام {{client_name}}،\n\nرزرو هتل شما تایید شده است:\n\n👤 مهمان: {{guest_name}}\n🏨 هتل: {{hotel_name}}\n📅 ورود: {{check_in}}\n📅 خروج: {{check_out}}\n💰 مبلغ: {{amount}}\n\n📅 تاریخ رزرو: {{booking_date}}\n\nبا تشکر از انتخاب {{agency_name}}!\n📞 تماس: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(33, 4, 'default_hotel_fa', 'hotel', 'fa', '🏨 *تایید رزرو هتل*\n\nسلام {{client_name}}،\n\nرزرو هتل شما تایید شده است:\n\n👤 مهمان: {{guest_name}}\n🏨 هتل: {{hotel_name}}\n📅 ورود: {{check_in}}\n📅 خروج: {{check_out}}\n💰 مبلغ: {{amount}}\n\n📅 تاریخ رزرو: {{booking_date}}\n\nبا تشکر از انتخاب {{agency_name}}!\n📞 تماس: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(34, 5, 'default_hotel_fa', 'hotel', 'fa', '🏨 *تایید رزرو هتل*\n\nسلام {{client_name}}،\n\nرزرو هتل شما تایید شده است:\n\n👤 مهمان: {{guest_name}}\n🏨 هتل: {{hotel_name}}\n📅 ورود: {{check_in}}\n📅 خروج: {{check_out}}\n💰 مبلغ: {{amount}}\n\n📅 تاریخ رزرو: {{booking_date}}\n\nبا تشکر از انتخاب {{agency_name}}!\n📞 تماس: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(35, 3, 'default_hotel_fa', 'hotel', 'fa', '🏨 *تایید رزرو هتل*\n\nسلام {{client_name}}،\n\nرزرو هتل شما تایید شده است:\n\n👤 مهمان: {{guest_name}}\n🏨 هتل: {{hotel_name}}\n📅 ورود: {{check_in}}\n📅 خروج: {{check_out}}\n💰 مبلغ: {{amount}}\n\n📅 تاریخ رزرو: {{booking_date}}\n\nبا تشکر از انتخاب {{agency_name}}!\n📞 تماس: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(36, 6, 'default_hotel_fa', 'hotel', 'fa', '🏨 *تایید رزرو هتل*\n\nسلام {{client_name}}،\n\nرزرو هتل شما تایید شده است:\n\n👤 مهمان: {{guest_name}}\n🏨 هتل: {{hotel_name}}\n📅 ورود: {{check_in}}\n📅 خروج: {{check_out}}\n💰 مبلغ: {{amount}}\n\n📅 تاریخ رزرو: {{booking_date}}\n\nبا تشکر از انتخاب {{agency_name}}!\n📞 تماس: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(37, 1, 'default_visa_ps', 'visa', 'ps', '🛂 *د ویزا غوښتنه*\n\nسلام {{client_name}}،\n\nد ویزا غوښتنه ستاسو په بریالیتوب سره پروسه شوه:\n\n👤 غوښتنونکی: {{applicant_name}}\n📋 د پاسپورټ شمېره: {{passport_number}}\n🌍 هیواد: {{country}}\n📄 ډول: {{visa_type}}\n💰 مقدار: {{amount}}\n\n📅 نېټه: {{booking_date}}\n\nد {{agency_name}} د انتخاب څخه مننه!\n📞 اړیکه: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(38, 2, 'default_visa_ps', 'visa', 'ps', '🛂 *د ویزا غوښتنه*\n\nسلام {{client_name}}،\n\nد ویزا غوښتنه ستاسو په بریالیتوب سره پروسه شوه:\n\n👤 غوښتنونکی: {{applicant_name}}\n📋 د پاسپورټ شمېره: {{passport_number}}\n🌍 هیواد: {{country}}\n📄 ډول: {{visa_type}}\n💰 مقدار: {{amount}}\n\n📅 نېټه: {{booking_date}}\n\nد {{agency_name}} د انتخاب څخه مننه!\n📞 اړیکه: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(39, 4, 'default_visa_ps', 'visa', 'ps', '🛂 *د ویزا غوښتنه*\n\nسلام {{client_name}}،\n\nد ویزا غوښتنه ستاسو په بریالیتوب سره پروسه شوه:\n\n👤 غوښتنونکی: {{applicant_name}}\n📋 د پاسپورټ شمېره: {{passport_number}}\n🌍 هیواد: {{country}}\n📄 ډول: {{visa_type}}\n💰 مقدار: {{amount}}\n\n📅 نېټه: {{booking_date}}\n\nد {{agency_name}} د انتخاب څخه مننه!\n📞 اړیکه: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(40, 5, 'default_visa_ps', 'visa', 'ps', '🛂 *د ویزا غوښتنه*\n\nسلام {{client_name}}،\n\nد ویزا غوښتنه ستاسو په بریالیتوب سره پروسه شوه:\n\n👤 غوښتنونکی: {{applicant_name}}\n📋 د پاسپورټ شمېره: {{passport_number}}\n🌍 هیواد: {{country}}\n📄 ډول: {{visa_type}}\n💰 مقدار: {{amount}}\n\n📅 نېټه: {{booking_date}}\n\nد {{agency_name}} د انتخاب څخه مننه!\n📞 اړیکه: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(41, 3, 'default_visa_ps', 'visa', 'ps', '🛂 *د ویزا غوښتنه*\n\nسلام {{client_name}}،\n\nد ویزا غوښتنه ستاسو په بریالیتوب سره پروسه شوه:\n\n👤 غوښتنونکی: {{applicant_name}}\n📋 د پاسپورټ شمېره: {{passport_number}}\n🌍 هیواد: {{country}}\n📄 ډول: {{visa_type}}\n💰 مقدار: {{amount}}\n\n📅 نېټه: {{booking_date}}\n\nد {{agency_name}} د انتخاب څخه مننه!\n📞 اړیکه: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(42, 6, 'default_visa_ps', 'visa', 'ps', '🛂 *د ویزا غوښتنه*\n\nسلام {{client_name}}،\n\nد ویزا غوښتنه ستاسو په بریالیتوب سره پروسه شوه:\n\n👤 غوښتنونکی: {{applicant_name}}\n📋 د پاسپورټ شمېره: {{passport_number}}\n🌍 هیواد: {{country}}\n📄 ډول: {{visa_type}}\n💰 مقدار: {{amount}}\n\n📅 نېټه: {{booking_date}}\n\nد {{agency_name}} د انتخاب څخه مننه!\n📞 اړیکه: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(43, 1, 'default_umrah_ps', 'umrah', 'ps', '🕌 *د عمره ریزرویشن تایید*\n\nالسلام علیکم {{client_name}}،\n\nستاسو عمره ریزرویشن تایید شو:\n\n👤 غړی: {{member_name}}\n📦 بسته: {{package_type}}\n✈️ د الوت نېټه: {{flight_date}}\n💰 مقدار: {{amount}}\n\n📅 د ریزرویشن نېټه: {{booking_date}}\n\nخدا دې ستاسو عمره قبول کړي!\n📞 اړیکه: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(44, 2, 'default_umrah_ps', 'umrah', 'ps', '🕌 *د عمره ریزرویشن تایید*\n\nالسلام علیکم {{client_name}}،\n\nستاسو عمره ریزرویشن تایید شو:\n\n👤 غړی: {{member_name}}\n📦 بسته: {{package_type}}\n✈️ د الوت نېټه: {{flight_date}}\n💰 مقدار: {{amount}}\n\n📅 د ریزرویشن نېټه: {{booking_date}}\n\nخدا دې ستاسو عمره قبول کړي!\n📞 اړیکه: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(45, 4, 'default_umrah_ps', 'umrah', 'ps', '🕌 *د عمره ریزرویشن تایید*\n\nالسلام علیکم {{client_name}}،\n\nستاسو عمره ریزرویشن تایید شو:\n\n👤 غړی: {{member_name}}\n📦 بسته: {{package_type}}\n✈️ د الوت نېټه: {{flight_date}}\n💰 مقدار: {{amount}}\n\n📅 د ریزرویشن نېټه: {{booking_date}}\n\nخدا دې ستاسو عمره قبول کړي!\n📞 اړیکه: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(46, 5, 'default_umrah_ps', 'umrah', 'ps', '🕌 *د عمره ریزرویشن تایید*\n\nالسلام علیکم {{client_name}}،\n\nستاسو عمره ریزرویشن تایید شو:\n\n👤 غړی: {{member_name}}\n📦 بسته: {{package_type}}\n✈️ د الوت نېټه: {{flight_date}}\n💰 مقدار: {{amount}}\n\n📅 د ریزرویشن نېټه: {{booking_date}}\n\nخدا دې ستاسو عمره قبول کړي!\n📞 اړیکه: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(47, 3, 'default_umrah_ps', 'umrah', 'ps', '🕌 *د عمره ریزرویشن تایید*\n\nالسلام علیکم {{client_name}}،\n\nستاسو عمره ریزرویشن تایید شو:\n\n👤 غړی: {{member_name}}\n📦 بسته: {{package_type}}\n✈️ د الوت نېټه: {{flight_date}}\n💰 مقدار: {{amount}}\n\n📅 د ریزرویشن نېټه: {{booking_date}}\n\nخدا دې ستاسو عمره قبول کړي!\n📞 اړیکه: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(48, 6, 'default_umrah_ps', 'umrah', 'ps', '🕌 *د عمره ریزرویشن تایید*\n\nالسلام علیکم {{client_name}}،\n\nستاسو عمره ریزرویشن تایید شو:\n\n👤 غړی: {{member_name}}\n📦 بسته: {{package_type}}\n✈️ د الوت نېټه: {{flight_date}}\n💰 مقدار: {{amount}}\n\n📅 د ریزرویشن نېټه: {{booking_date}}\n\nخدا دې ستاسو عمره قبول کړي!\n📞 اړیکه: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(49, 1, 'default_hotel_ps', 'hotel', 'ps', '🏨 *د هوټل ریزرویشن تایید*\n\nسلام {{client_name}}،\n\nستاسو هوټل ریزرویشن تایید شو:\n\n👤 مېلمه: {{guest_name}}\n🏨 هوټل: {{hotel_name}}\n📅 دننول: {{check_in}}\n📅 دباندېلا: {{check_out}}\n💰 مقدار: {{amount}}\n\n📅 د ریزرویشن نېټه: {{booking_date}}\n\nد {{agency_name}} د انتخاب څخه مننه!\n📞 اړیکه: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(50, 2, 'default_hotel_ps', 'hotel', 'ps', '🏨 *د هوټل ریزرویشن تایید*\n\nسلام {{client_name}}،\n\nستاسو هوټل ریزرویشن تایید شو:\n\n👤 مېلمه: {{guest_name}}\n🏨 هوټل: {{hotel_name}}\n📅 دننول: {{check_in}}\n📅 دباندېلا: {{check_out}}\n💰 مقدار: {{amount}}\n\n📅 د ریزرویشن نېټه: {{booking_date}}\n\nد {{agency_name}} د انتخاب څخه مننه!\n📞 اړیکه: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(51, 4, 'default_hotel_ps', 'hotel', 'ps', '🏨 *د هوټل ریزرویشن تایید*\n\nسلام {{client_name}}،\n\nستاسو هوټل ریزرویشن تایید شو:\n\n👤 مېلمه: {{guest_name}}\n🏨 هوټل: {{hotel_name}}\n📅 دننول: {{check_in}}\n📅 دباندېلا: {{check_out}}\n💰 مقدار: {{amount}}\n\n📅 د ریزرویشن نېټه: {{booking_date}}\n\nد {{agency_name}} د انتخاب څخه مننه!\n📞 اړیکه: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(52, 5, 'default_hotel_ps', 'hotel', 'ps', '🏨 *د هوټل ریزرویشن تایید*\n\nسلام {{client_name}}،\n\nستاسو هوټل ریزرویشن تایید شو:\n\n👤 مېلمه: {{guest_name}}\n🏨 هوټل: {{hotel_name}}\n📅 دننول: {{check_in}}\n📅 دباندېلا: {{check_out}}\n💰 مقدار: {{amount}}\n\n📅 د ریزرویشن نېټه: {{booking_date}}\n\nد {{agency_name}} د انتخاب څخه مننه!\n📞 اړیکه: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(53, 3, 'default_hotel_ps', 'hotel', 'ps', '🏨 *د هوټل ریزرویشن تایید*\n\nسلام {{client_name}}،\n\nستاسو هوټل ریزرویشن تایید شو:\n\n👤 مېلمه: {{guest_name}}\n🏨 هوټل: {{hotel_name}}\n📅 دننول: {{check_in}}\n📅 دباندېلا: {{check_out}}\n💰 مقدار: {{amount}}\n\n📅 د ریزرویشن نېټه: {{booking_date}}\n\nد {{agency_name}} د انتخاب څخه مننه!\n📞 اړیکه: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL),
(54, 6, 'default_hotel_ps', 'hotel', 'ps', '🏨 *د هوټل ریزرویشن تایید*\n\nسلام {{client_name}}،\n\nستاسو هوټل ریزرویشن تایید شو:\n\n👤 مېلمه: {{guest_name}}\n🏨 هوټل: {{hotel_name}}\n📅 دننول: {{check_in}}\n📅 دباندېلا: {{check_out}}\n💰 مقدار: {{amount}}\n\n📅 د ریزرویشن نېټه: {{booking_date}}\n\nد {{agency_name}} د انتخاب څخه مننه!\n📞 اړیکه: {{contact_info}}', 'active', 1, '2025-11-23 06:51:01', '2025-11-23 06:51:01', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `whatsapp_webhook_log`
--

CREATE TABLE `whatsapp_webhook_log` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `webhook_type` varchar(50) NOT NULL COMMENT 'Type: message, delivery, read, etc',
  `from_number` varchar(20) DEFAULT NULL COMMENT 'Sender phone number',
  `to_number` varchar(20) DEFAULT NULL COMMENT 'Recipient phone number',
  `message_content` text DEFAULT NULL COMMENT 'Message content if applicable',
  `raw_payload` longtext DEFAULT NULL COMMENT 'Complete webhook payload',
  `processed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether webhook was processed',
  `error_message` text DEFAULT NULL COMMENT 'Error during processing if any',
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address of webhook request',
  `user_agent` text DEFAULT NULL COMMENT 'User agent of webhook request',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `branch_id` (`branch_id`);

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
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_branch_code` (`tenant_id`,`code`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `manager_id` (`manager_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `branch_audit_log`
--
ALTER TABLE `branch_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `branch_audit_log_branch_id_index` (`branch_id`),
  ADD KEY `branch_audit_log_user_id_index` (`user_id`),
  ADD KEY `branch_audit_log_table_name_index` (`table_name`),
  ADD KEY `branch_audit_log_action_index` (`action`),
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
-- Indexes for table `cancellation_reapply_log`
--
ALTER TABLE `cancellation_reapply_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_booking_id` (`booking_id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `created_by` (`created_by`);

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
-- Indexes for table `email_tracking`
--
ALTER TABLE `email_tracking`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_email_id` (`email_id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_email_type` (`email_type`);

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
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `branch_id` (`branch_id`);

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
-- Indexes for table `whatsapp_analytics`
--
ALTER TABLE `whatsapp_analytics`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_date_type` (`tenant_id`,`date`,`message_type`),
  ADD KEY `date` (`date`),
  ADD KEY `idx_whatsapp_analytics_tenant_date` (`tenant_id`,`date`);

--
-- Indexes for table `whatsapp_delivery_status`
--
ALTER TABLE `whatsapp_delivery_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `provider_message_id` (`provider_message_id`),
  ADD KEY `message_id` (`message_id`),
  ADD KEY `idx_whatsapp_delivery_status_timestamp` (`delivery_timestamp`);

--
-- Indexes for table `whatsapp_messages`
--
ALTER TABLE `whatsapp_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_status` (`tenant_id`,`status`),
  ADD KEY `message_type_ref` (`message_type`,`reference_id`),
  ADD KEY `scheduled_at` (`scheduled_at`),
  ADD KEY `priority_status` (`priority`,`status`),
  ADD KEY `idx_whatsapp_messages_status_priority` (`status`,`priority`),
  ADD KEY `idx_whatsapp_messages_tenant_status` (`tenant_id`,`status`);

--
-- Indexes for table `whatsapp_settings`
--
ALTER TABLE `whatsapp_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `whatsapp_templates`
--
ALTER TABLE `whatsapp_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_template_type_lang` (`tenant_id`,`template_type`,`language`),
  ADD KEY `template_type` (`template_type`),
  ADD KEY `created_by` (`created_by`);

--
-- Indexes for table `whatsapp_webhook_log`
--
ALTER TABLE `whatsapp_webhook_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_processed` (`tenant_id`,`processed`),
  ADD KEY `webhook_type` (`webhook_type`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `activity_log`
--
ALTER TABLE `activity_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2358;

--
-- AUTO_INCREMENT for table `additional_payments`
--
ALTER TABLE `additional_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

--
-- AUTO_INCREMENT for table `blog_posts`
--
ALTER TABLE `blog_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `branch_audit_log`
--
ALTER TABLE `branch_audit_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `budget_allocations`
--
ALTER TABLE `budget_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `cancellation_reapply_log`
--
ALTER TABLE `cancellation_reapply_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=646;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `creditors`
--
ALTER TABLE `creditors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

--
-- AUTO_INCREMENT for table `creditor_transactions`
--
ALTER TABLE `creditor_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=39;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `customer_wallets`
--
ALTER TABLE `customer_wallets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `date_change_tickets`
--
ALTER TABLE `date_change_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=98;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=47;

--
-- AUTO_INCREMENT for table `debtor_transactions`
--
ALTER TABLE `debtor_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=54;

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
-- AUTO_INCREMENT for table `email_tracking`
--
ALTER TABLE `email_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=45;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=220;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=25;

--
-- AUTO_INCREMENT for table `families`
--
ALTER TABLE `families`
  MODIFY `family_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=18;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=59;

--
-- AUTO_INCREMENT for table `hotel_refunds`
--
ALTER TABLE `hotel_refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=239;

--
-- AUTO_INCREMENT for table `jv_payments`
--
ALTER TABLE `jv_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `jv_transactions`
--
ALTER TABLE `jv_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=66;

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=141;

--
-- AUTO_INCREMENT for table `main_account`
--
ALTER TABLE `main_account`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=19;

--
-- AUTO_INCREMENT for table `main_account_transactions`
--
ALTER TABLE `main_account_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1445;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1220;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=242;

--
-- AUTO_INCREMENT for table `refunded_tickets`
--
ALTER TABLE `refunded_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=145;

--
-- AUTO_INCREMENT for table `salary_adjustments`
--
ALTER TABLE `salary_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `salary_advances`
--
ALTER TABLE `salary_advances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `salary_payments`
--
ALTER TABLE `salary_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `sarafi_transactions`
--
ALTER TABLE `sarafi_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=53;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=58;

--
-- AUTO_INCREMENT for table `supplier_transactions`
--
ALTER TABLE `supplier_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=1008;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=422;

--
-- AUTO_INCREMENT for table `ticket_reservations`
--
ALTER TABLE `ticket_reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=31;

--
-- AUTO_INCREMENT for table `ticket_weights`
--
ALTER TABLE `ticket_weights`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=71;

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
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=72;

--
-- AUTO_INCREMENT for table `umrah_booking_services`
--
ALTER TABLE `umrah_booking_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=42;

--
-- AUTO_INCREMENT for table `umrah_refunds`
--
ALTER TABLE `umrah_refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=27;

--
-- AUTO_INCREMENT for table `umrah_transactions`
--
ALTER TABLE `umrah_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=100;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=70;

--
-- AUTO_INCREMENT for table `visa_refunds`
--
ALTER TABLE `visa_refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=11;

--
-- AUTO_INCREMENT for table `whatsapp_analytics`
--
ALTER TABLE `whatsapp_analytics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `whatsapp_delivery_status`
--
ALTER TABLE `whatsapp_delivery_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `whatsapp_messages`
--
ALTER TABLE `whatsapp_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `whatsapp_settings`
--
ALTER TABLE `whatsapp_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `whatsapp_templates`
--
ALTER TABLE `whatsapp_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=64;

--
-- AUTO_INCREMENT for table `whatsapp_webhook_log`
--
ALTER TABLE `whatsapp_webhook_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `activity_log`
--
ALTER TABLE `activity_log`
  ADD CONSTRAINT `fk_activity_log_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
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
-- Constraints for table `branches`
--
ALTER TABLE `branches`
  ADD CONSTRAINT `fk_branches_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `fk_branches_manager` FOREIGN KEY (`manager_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_branches_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `branch_audit_log`
--
ALTER TABLE `branch_audit_log`
  ADD CONSTRAINT `fk_branch_audit_log_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_branch_audit_log_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `budget_allocations`
--
ALTER TABLE `budget_allocations`
  ADD CONSTRAINT `budget_allocations_ibfk_1` FOREIGN KEY (`main_account_id`) REFERENCES `main_account` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `budget_allocations_ibfk_2` FOREIGN KEY (`category_id`) REFERENCES `expense_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_budget_allocations_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `cancellation_reapply_log`
--
ALTER TABLE `cancellation_reapply_log`
  ADD CONSTRAINT `cancellation_reapply_log_ibfk_1` FOREIGN KEY (`booking_id`) REFERENCES `umrah_bookings` (`booking_id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cancellation_reapply_log_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cancellation_reapply_log_ibfk_3` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

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
  ADD CONSTRAINT `fk_users_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
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

--
-- Constraints for table `whatsapp_analytics`
--
ALTER TABLE `whatsapp_analytics`
  ADD CONSTRAINT `whatsapp_analytics_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `whatsapp_delivery_status`
--
ALTER TABLE `whatsapp_delivery_status`
  ADD CONSTRAINT `whatsapp_delivery_status_ibfk_1` FOREIGN KEY (`message_id`) REFERENCES `whatsapp_messages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `whatsapp_messages`
--
ALTER TABLE `whatsapp_messages`
  ADD CONSTRAINT `whatsapp_messages_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `whatsapp_settings`
--
ALTER TABLE `whatsapp_settings`
  ADD CONSTRAINT `whatsapp_settings_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `whatsapp_templates`
--
ALTER TABLE `whatsapp_templates`
  ADD CONSTRAINT `whatsapp_templates_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `whatsapp_templates_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Constraints for table `whatsapp_webhook_log`
--
ALTER TABLE `whatsapp_webhook_log`
  ADD CONSTRAINT `whatsapp_webhook_log_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
