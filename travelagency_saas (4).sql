-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Nov 16, 2025 at 11:22 AM
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
(48, 14, 'update_tenant', 'tenant', 1, '{\"tenant_id\":1,\"name\":\"Al Moqadas Travel Agency\",\"subdomain\":\"alpha\",\"status\":\"active\"}', '::1', '2025-11-15 03:54:05'),
(49, 14, 'update_tenant', 'tenant', 1, '{\"tenant_id\":1,\"name\":\"Al Moqadas Travel Agency\",\"subdomain\":\"alpha\",\"status\":\"active\"}', '::1', '2025-11-16 04:39:09'),
(50, 14, 'update_subscription', 'subscription', 1, '{\"subscription_id\":1,\"plan_id\":\"3\",\"status\":\"active\",\"billing_cycle\":\"monthly\"}', '::1', '2025-11-16 05:54:03'),
(51, 14, 'update_subscription', 'subscription', 2, '{\"subscription_id\":2,\"plan_id\":\"4\",\"status\":\"active\",\"billing_cycle\":\"yearly\"}', '::1', '2025-11-16 10:28:12'),
(52, 14, 'update_subscription', 'subscription', 2, '{\"subscription_id\":2,\"plan_id\":\"1\",\"status\":\"active\",\"billing_cycle\":\"yearly\"}', '::1', '2025-11-16 11:06:25'),
(53, 14, 'update_subscription', 'subscription', 2, '{\"subscription_id\":2,\"plan_id\":\"2\",\"status\":\"active\",\"billing_cycle\":\"yearly\"}', '::1', '2025-11-16 11:07:54'),
(54, 14, 'update_subscription', 'subscription', 2, '{\"subscription_id\":2,\"plan_id\":\"3\",\"status\":\"active\",\"billing_cycle\":\"yearly\"}', '::1', '2025-11-16 11:08:54');

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
(40, 'u-1-7', 1, 7, 1, 'hi', '2025-10-29 10:16:34', '2025-11-16 10:31:29'),
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
(18, 1, '', 'DR SAHIBs', 'admin@abc-construction.com', '$2y$10$wNU7te9YSBzmM86Q3Z9D6eLp2SloDOyIz29TUmz/JDc9ZJMwT2EG.', '0777305730', -4264.190, 0.000, 'Jada-e-Maiwand', '2025-09-01 10:44:22', '2025-11-16 09:50:23', 'active', 'regular', 0),
(19, 1, '690854d3a4ecb_Blue and White Simple Furniture Doubled Sided Poster.png', 'Walking Customer', 'almuqadas_travel@yahoo.com', '$2y$10$RCql5Ieq91Jkd8FaykSbKOxX/QNNJ9LD4jKJORz6Zkw9rlalh0qVS', '0777305730', 0.000, 0.000, 'Jada-e-Maiwand', '2025-09-07 08:42:27', '2025-11-16 09:50:27', 'active', 'agency', 0),
(20, 2, '', 'DR SAHIB', 'DRal@GMAIL.COM', '$2y$10$AgeoMovkpOzvOgWKT7b5tegnAHDaUcLYwr3SJ80aDsw0o20llzJrm', '0777305730', 0.000, 0.000, 'Jada-e-Maiwand', '2025-09-10 11:48:39', '2025-09-10 13:11:15', 'active', 'regular', 0),
(21, 2, '', 'walkings', 'esmati@gmail.com', '$2y$10$JWMeEbMm9oA4FxG9DhK6.uMNh.NLazftrApSj/oG3KPJcM1MikAJK', '0777305730', 0.000, 0.000, 'Jada-e-Maiwand', '2025-09-10 12:31:12', '2025-09-10 12:31:12', 'active', 'agency', 0),
(22, 1, '', 'MINA Khan', 'mina@yahoo.com', '$2y$10$E70pRj3P2NYVedD5bA1UgeOB0srLqm.0nFlsDsGmhIB5BsKDl/qha', '0777305730', 0.000, 0.000, 'adfads', '2025-10-16 11:29:38', '2025-11-16 09:50:39', 'active', 'regular', 0);

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
(118, 1, 1, 'logout', '2025-11-01 09:54:02'),
(119, 1, 1, 'logout', '2025-11-16 04:38:53'),
(120, 1, 1, 'logout', '2025-11-16 04:39:36'),
(121, 1, 1, 'logout', '2025-11-16 06:03:28'),
(122, 1, 1, 'logout', '2025-11-16 10:24:44'),
(123, 1, 1, 'logout', '2025-11-16 10:27:58'),
(124, 1, 6, 'logout', '2025-11-16 10:28:33'),
(125, 1, 18, 'logout', '2025-11-16 10:29:07'),
(126, 2, 7, 'logout', '2025-11-16 11:05:06'),
(127, 1, 1, 'logout', '2025-11-16 11:06:10'),
(128, 2, 7, 'logout', '2025-11-16 11:07:40'),
(129, 2, 7, 'logout', '2025-11-16 11:08:41'),
(130, 2, 7, 'logout', '2025-11-16 11:09:36'),
(131, 1, 18, 'logout', '2025-11-16 11:10:24'),
(132, 1, 6, 'logout', '2025-11-16 11:10:41');

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
(13, 1, 'SELF BANK (SAFE)', 'internal', NULL, NULL, NULL, 7251.300, 533185.000, 450.000, 505.000, '2025-11-16 11:15:18', 'active'),
(14, 1, 'AZIZI BANK', 'bank', NULL, '143241234', 'AZIZI', 123.000, 3375.000, 0.000, 0.000, '2025-11-16 11:16:08', 'active'),
(16, 1, 'AFGHAN UNITED BANK', 'bank', '23451534', '3254524afs453', 'AUB', 648.000, 26700.000, 0.000, 0.000, '2025-11-16 11:16:41', 'active');

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
(31, 1, 'KamAir', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 651.490, '2025-10-15 11:11:34', '2025-11-16 06:50:35', 'active'),
(32, 1, 'NSTTRIP', 'SABAOON', 'External', '0777305730', 'admin@abc-construction.com', 'nsst', 'USD', 699.500, '2025-10-15 11:57:18', '2025-11-16 06:52:42', 'active'),
(33, 1, 'Ariana', 'SABAOON', 'External', '0777305730', 'admin@abc-construction.com', 'nsst', 'AFS', 32093.000, '2025-10-15 11:59:01', '2025-11-16 06:50:57', 'active'),
(34, 1, 'Yasin', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'yasin', 'USD', 0.000, '2025-10-15 12:05:10', '2025-11-16 06:52:27', 'active'),
(35, 1, 'ALAIN T', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'adfa', 'USD', -6618.000, '2025-10-16 11:31:23', '2025-11-16 06:53:13', 'active'),
(37, 1, '\r\nTOLO TRIP', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 1246.130, '2025-10-15 11:11:34', '2025-11-16 06:50:35', 'active'),
(38, 1, '\r\nFLY DUBAI (FZ)', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 1369.960, '2025-10-15 11:11:34', '2025-11-16 06:50:35', 'active'),
(39, 1, '\r\nHADITA', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 56.920, '2025-10-15 11:11:34', '2025-11-16 06:50:35', 'active'),
(40, 1, 'SALAM (PAR)', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 692.200, '2025-10-15 11:11:34', '2025-11-16 06:50:35', 'active'),
(41, 1, 'MASHALLAH TRAVEL AGENCY', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 0.000, '2025-10-15 11:11:34', '2025-11-16 06:50:35', 'active'),
(42, 1, 'NOOR WALI TRAVEL AGENCY', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', -3.000, '2025-10-15 11:11:34', '2025-11-16 06:50:35', 'active'),
(43, 1, 'AL MAJLAS TRAVEL AGENCY', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 0.000, '2025-10-15 11:11:34', '2025-11-16 06:50:35', 'active'),
(44, 1, 'SAFI ETEMAD TRAVEL PAK VISA PAYMENT', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'AFS', 0.000, '2025-10-15 11:11:34', '2025-11-16 06:50:35', 'active'),
(45, 1, 'AL WESAL TRAVEL AGENCY', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', -9.990, '2025-10-15 11:11:34', '2025-11-16 09:53:14', 'active'),
(46, 1, 'BAHRIA TRAVEL AGENCY', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 0.000, '2025-10-15 11:11:34', '2025-11-16 06:50:35', 'active'),
(47, 1, 'SKY TRAVELER', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 1093.590, '2025-10-15 11:11:34', '2025-11-16 06:50:35', 'active'),
(48, 1, 'CITY LAB', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'AFS', 0.000, '2025-10-15 11:11:34', '2025-11-16 06:50:35', 'active'),
(49, 1, 'AL MOQADAS TRAVEL UMRA PORTION', 'Wali', 'Internal', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 0.000, '2025-10-15 11:11:34', '2025-11-16 06:50:35', 'active'),
(50, 1, 'Shamshad Travel Agency', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 0.000, '2025-10-15 11:11:34', '2025-11-16 06:50:35', 'active'),
(51, 1, 'ZIA TRAVEL AGENCY', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', -15.000, '2025-10-15 11:11:34', '2025-11-16 06:50:35', 'active'),
(52, 1, 'SHIRZAD TRAVL AGINCY', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 0.000, '2025-10-15 11:11:34', '2025-11-16 06:50:35', 'active'),
(53, 1, 'RAHI BARAKAT', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', -6240.000, '2025-10-15 11:11:34', '2025-11-16 06:50:35', 'active'),
(54, 1, 'AL EKHLAS TRAVEL AGENCY', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', -1560.000, '2025-10-15 11:11:34', '2025-11-16 06:50:35', 'active'),
(55, 1, 'New Mustfa Travel Agency', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', -10.000, '2025-10-15 11:11:34', '2025-11-16 06:50:35', 'active'),
(56, 1, 'AIR ARABIA', 'Wali', 'External', '0777305730', 'admin@abc-construction.com', 'wazir', 'USD', 0.000, '2025-10-15 11:11:34', '2025-11-16 06:50:35', 'active');

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2082;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=55;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=133;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=57;

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
