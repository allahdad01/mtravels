-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Aug 04, 2026 at 01:26 PM
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
-- Database: `mtravels`
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

-- --------------------------------------------------------

--
-- Table structure for table `advanced_rate_limits`
--

CREATE TABLE `advanced_rate_limits` (
  `id` int(11) NOT NULL,
  `limit_key` varchar(255) NOT NULL,
  `bucket_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL,
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ;

-- --------------------------------------------------------

--
-- Table structure for table `advanced_rate_limit_requests`
--

CREATE TABLE `advanced_rate_limit_requests` (
  `id` int(11) NOT NULL,
  `limit_key` varchar(255) NOT NULL,
  `timestamp` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `api_versions`
--

CREATE TABLE `api_versions` (
  `id` int(11) NOT NULL,
  `version` varchar(10) NOT NULL,
  `is_active` tinyint(1) DEFAULT 1,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance`
--

CREATE TABLE `attendance` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `check_in_time` time DEFAULT NULL,
  `check_out_time` time DEFAULT NULL,
  `working_minutes` int(11) DEFAULT 0,
  `status` enum('present','late','half_day','absent') DEFAULT 'absent',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `attendance_settings`
--

CREATE TABLE `attendance_settings` (
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `office_start_time` time NOT NULL DEFAULT '09:00:00',
  `office_end_time` time NOT NULL DEFAULT '17:00:00',
  `late_after_minutes` int(11) NOT NULL DEFAULT 15,
  `half_day_minutes` int(11) NOT NULL DEFAULT 240,
  `working_days` varchar(50) NOT NULL DEFAULT 'Mon-Fri',
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- --------------------------------------------------------

--
-- Table structure for table `branch_addons`
--

CREATE TABLE `branch_addons` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `base_branches` int(11) NOT NULL DEFAULT 1 COMMENT 'Branches included in the plan',
  `additional_branches` int(11) NOT NULL DEFAULT 0 COMMENT 'Extra branches purchased',
  `addon_price_per_branch` decimal(10,2) NOT NULL DEFAULT 50.00 COMMENT 'Price for each additional branch',
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `total_addon_cost` decimal(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Total cost of all additional branches',
  `billing_cycle` enum('monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
  `status` enum('active','inactive','pending','cancelled') NOT NULL DEFAULT 'pending',
  `activated_at` datetime DEFAULT NULL,
  `next_renewal_date` datetime DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branch_addon_payments`
--

CREATE TABLE `branch_addon_payments` (
  `id` int(11) NOT NULL,
  `addon_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `payment_method` varchar(50) DEFAULT NULL COMMENT 'e.g., stripe, paypal, bank_transfer',
  `transaction_id` varchar(100) DEFAULT NULL COMMENT 'Payment gateway transaction ID',
  `status` enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `payment_date` datetime DEFAULT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `receipt_url` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branch_addon_requests`
--

CREATE TABLE `branch_addon_requests` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `requested_additional_branches` int(11) NOT NULL,
  `billing_cycle` enum('monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
  `estimated_monthly_cost` decimal(10,2) NOT NULL,
  `currency` varchar(3) NOT NULL DEFAULT 'USD',
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `approval_notes` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Table structure for table `branch_chat_settings`
--

CREATE TABLE `branch_chat_settings` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `chat_max_file_bytes` bigint(20) NOT NULL DEFAULT 26214400,
  `chat_allowed_mime_prefixes` text NOT NULL,
  `chat_default_auto_download` tinyint(1) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branch_peering`
--

CREATE TABLE `branch_peering` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `peer_tenant_id` int(11) NOT NULL,
  `peer_branch_id` int(11) DEFAULT NULL,
  `status` enum('approved','pending','blocked') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `branch_performance_alerts`
--

CREATE TABLE `branch_performance_alerts` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `severity` enum('WARNING','CRITICAL') NOT NULL DEFAULT 'WARNING',
  `message` mediumtext NOT NULL,
  `status` enum('new','acknowledged','resolved') DEFAULT 'new',
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
  `created_by` int(11) NOT NULL,
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

-- --------------------------------------------------------

--
-- Table structure for table `chat_audit_log`
--

CREATE TABLE `chat_audit_log` (
  `id` bigint(20) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL COMMENT 'send_message, read_message, block_user, unblock_user, mute_user, unmute_user, encrypt_message, decrypt_message, settings_change, access_denied, etc.',
  `target_user_id` int(11) DEFAULT NULL COMMENT 'The user affected by the action',
  `message_id` bigint(20) DEFAULT NULL COMMENT 'Reference to chat_messages table',
  `room_id` varchar(50) DEFAULT NULL COMMENT 'Chat room identifier',
  `details` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'Client IP address (IPv4 or IPv6)',
  `user_agent` varchar(255) DEFAULT NULL COMMENT 'User-Agent header',
  `status` varchar(20) DEFAULT NULL COMMENT 'success, failed, denied, error',
  `error_message` text DEFAULT NULL COMMENT 'Error details if action failed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Complete audit trail for chat operations for compliance and security tracking';

-- --------------------------------------------------------

--
-- Table structure for table `chat_audit_log_archive`
--

CREATE TABLE `chat_audit_log_archive` (
  `id` bigint(20) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) DEFAULT NULL,
  `user_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL,
  `target_user_id` int(11) DEFAULT NULL,
  `message_id` bigint(20) DEFAULT NULL,
  `room_id` varchar(50) DEFAULT NULL,
  `details` longtext DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` varchar(255) DEFAULT NULL,
  `status` varchar(20) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `archived_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Archive table for audit logs older than 90 days';

-- --------------------------------------------------------

--
-- Table structure for table `chat_groups`
--

CREATE TABLE `chat_groups` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `group_name` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `group_type` enum('branch','custom') NOT NULL DEFAULT 'custom',
  `branch_id` int(11) DEFAULT NULL COMMENT 'If group_type=branch, branch ID',
  `created_by_id` int(11) NOT NULL,
  `created_by_type` enum('user','client') NOT NULL DEFAULT 'user',
  `profile_pic` varchar(255) DEFAULT NULL,
  `max_members` int(11) DEFAULT 100,
  `is_archived` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_group_members`
--

CREATE TABLE `chat_group_members` (
  `id` int(11) NOT NULL,
  `group_id` int(11) NOT NULL,
  `member_id` int(11) NOT NULL,
  `member_type` enum('user','client') NOT NULL DEFAULT 'user',
  `role` enum('admin','member') DEFAULT 'member',
  `joined_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `left_at` timestamp NULL DEFAULT NULL COMMENT 'When member left the group'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_group_messages`
--

CREATE TABLE `chat_group_messages` (
  `id` bigint(20) NOT NULL,
  `group_id` int(11) NOT NULL,
  `from_user_id` int(11) NOT NULL,
  `from_user_type` enum('user','client') NOT NULL DEFAULT 'user',
  `content` longtext DEFAULT NULL,
  `encrypted_content` longtext DEFAULT NULL,
  `is_encrypted` tinyint(1) DEFAULT 0,
  `encryption_key_id` int(11) DEFAULT NULL,
  `message_type` enum('text','file','voice','image') DEFAULT 'text',
  `file_url` varchar(500) DEFAULT NULL,
  `file_name` varchar(255) DEFAULT NULL,
  `file_size` bigint(20) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `edited_at` timestamp NULL DEFAULT NULL,
  `deleted_by_user_id` int(11) DEFAULT NULL COMMENT 'User who deleted the message',
  `deleted_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `chat_group_message_reads`
--

CREATE TABLE `chat_group_message_reads` (
  `id` bigint(20) NOT NULL,
  `message_id` bigint(20) NOT NULL,
  `member_id` int(11) NOT NULL,
  `member_type` enum('user','client') NOT NULL DEFAULT 'user',
  `read_at` timestamp NOT NULL DEFAULT current_timestamp()
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
  `encrypted_content` longblob DEFAULT NULL COMMENT 'Encrypted message content',
  `encryption_key_id` int(11) DEFAULT NULL COMMENT 'Reference to encryption_keys table',
  `is_encrypted` tinyint(1) DEFAULT 0 COMMENT 'Flag: 1 = encrypted, 0 = plaintext',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `seen_at` timestamp NULL DEFAULT NULL,
  `branch_id` bigint(20) DEFAULT NULL,
  `message_type` varchar(20) DEFAULT 'text',
  `duration` int(11) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `clients`
--

CREATE TABLE `clients` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `image` varchar(100) DEFAULT NULL,
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
  `balance` decimal(15,3) NOT NULL DEFAULT 0.000,
  `currency` enum('USD','AFS') NOT NULL,
  `description` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `transaction_of` enum('ticket_sale','visa_sale','ticket_refund','date_change','fund','umrah','hotel','hotel_refund','ticket_reserve','jv_payment','additional_payment','visa_refund','hotel_refund','umrah_refund','additional_payment','weight_sale','umrah_date_change','umrah_cancellation','visa_cancellation','umrah_transaction') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `receipt` varchar(100) DEFAULT '',
  `exchange_rate` decimal(10,5) DEFAULT NULL,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `communication_addons`
--

CREATE TABLE `communication_addons` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `addon_type` enum('whatsapp','smtp') NOT NULL,
  `addon_price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `billing_cycle` enum('monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `status` enum('active','inactive','cancelled') NOT NULL DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `communication_addon_requests`
--

CREATE TABLE `communication_addon_requests` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `addon_type` enum('whatsapp','smtp') NOT NULL,
  `billing_cycle` enum('monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
  `estimated_monthly_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `status` enum('pending','approved','rejected') NOT NULL DEFAULT 'pending',
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `approval_notes` text DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
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
  `status` enum('unread','read','replied') NOT NULL DEFAULT 'unread',
  `branch_id` bigint(20) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL
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

-- --------------------------------------------------------

--
-- Table structure for table `custom_plan_requests`
--

CREATE TABLE `custom_plan_requests` (
  `id` int(11) NOT NULL,
  `contact_name` varchar(100) NOT NULL,
  `contact_email` varchar(100) NOT NULL,
  `contact_phone` varchar(50) NOT NULL,
  `agency_name` varchar(255) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `selected_features` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL CHECK (json_valid(`selected_features`)),
  `max_users` int(11) NOT NULL DEFAULT 1,
  `max_branches` int(11) NOT NULL DEFAULT 1,
  `trial_days` int(11) NOT NULL DEFAULT 14,
  `suggested_price` decimal(10,2) DEFAULT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'AFN',
  `negotiated_price` decimal(10,2) DEFAULT NULL,
  `status` enum('pending','contacted','negotiating','approved','rejected','converted') NOT NULL DEFAULT 'pending',
  `admin_notes` text DEFAULT NULL,
  `converted_tenant_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
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
  `return_origin` varchar(255) DEFAULT NULL,
  `return_destination` varchar(255) DEFAULT NULL,
  `phone` varchar(50) DEFAULT NULL,
  `airline` varchar(255) NOT NULL,
  `gender` enum('Male','Female','Other') NOT NULL,
  `issue_date` date NOT NULL,
  `departure_date` date NOT NULL,
  `return_date` date DEFAULT NULL,
  `date_type` enum('departure','return','both') DEFAULT 'departure',
  `currency` enum('USD','AFS') NOT NULL,
  `sold` decimal(10,3) NOT NULL,
  `base` decimal(10,3) NOT NULL,
  `supplier_penalty` decimal(10,3) NOT NULL,
  `service_penalty` decimal(10,3) NOT NULL,
  `status` enum('Date Changed') NOT NULL,
  `remarks` mediumtext DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `imported` tinyint(1) NOT NULL DEFAULT 0,
  `branch_id` bigint(20) DEFAULT NULL
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
  `branch_id` bigint(20) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `email_tracking`
--

CREATE TABLE `email_tracking` (
  `id` int(11) NOT NULL,
  `email_id` varchar(255) DEFAULT NULL,
  `recipient_email` varchar(255) DEFAULT NULL,
  `email_type` varchar(100) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `tenant_id` int(11) NOT NULL,
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `opened_at` timestamp NULL DEFAULT NULL,
  `opened` tinyint(1) DEFAULT 0,
  `user_agent` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `branch_id` bigint(20) DEFAULT NULL
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `encryption_audit`
--

CREATE TABLE `encryption_audit` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `action` varchar(50) NOT NULL COMMENT 'encrypt, decrypt, rotate, access',
  `user_id` int(11) DEFAULT NULL,
  `message_id` int(11) DEFAULT NULL,
  `key_id` int(11) DEFAULT NULL,
  `success` tinyint(1) DEFAULT 1,
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `encryption_keys`
--

CREATE TABLE `encryption_keys` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `key_name` varchar(100) NOT NULL,
  `key_hash` varchar(255) NOT NULL COMMENT 'SHA256 hash of the actual key',
  `algorithm` varchar(50) DEFAULT 'aes-256-cbc',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `rotated_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','retired','archived') DEFAULT 'active'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `encryption_key_rotations`
--

CREATE TABLE `encryption_key_rotations` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `old_key_id` int(11) DEFAULT NULL,
  `new_key_id` int(11) NOT NULL,
  `messages_rotated` int(11) DEFAULT 0 COMMENT 'Number of messages re-encrypted',
  `status` enum('pending','in_progress','completed','failed') DEFAULT 'pending',
  `error_message` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `completed_at` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `exchange_rates`
--

CREATE TABLE `exchange_rates` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
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
  `sub_category_id` int(11) DEFAULT NULL,
  `main_account_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `description` mediumtext NOT NULL,
  `amount` decimal(10,3) NOT NULL,
  `currency` enum('USD','AFS') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `allocation_id` int(11) DEFAULT NULL,
  `global_allocation_id` int(11) DEFAULT NULL,
  `receipt_file` varchar(50) DEFAULT NULL,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expense_categories`
--

CREATE TABLE `expense_categories` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `expense_report_config`
--

CREATE TABLE `expense_report_config` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `quarter` varchar(3) NOT NULL,
  `year` int(11) NOT NULL,
  `expense_category` varchar(100) NOT NULL,
  `included` tinyint(1) DEFAULT 1,
  `amount` decimal(12,2) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `finance_tracker`
--

CREATE TABLE `finance_tracker` (
  `id` int(11) NOT NULL,
  `date` date NOT NULL,
  `type` enum('income','expense') NOT NULL,
  `amount` decimal(12,2) NOT NULL,
  `currency` enum('USD','AFS','EUR','DARHAM','SAR') NOT NULL DEFAULT 'USD',
  `description` text DEFAULT NULL,
  `branch_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `floating_tasks`
--

CREATE TABLE `floating_tasks` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL DEFAULT 1,
  `branch_id` int(11) NOT NULL DEFAULT 1,
  `task_text` varchar(255) NOT NULL,
  `completed` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `user_type` enum('user','client') DEFAULT 'user'
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
  `funded_by` int(11) NOT NULL,
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

-- --------------------------------------------------------

--
-- Table structure for table `global_budget_allocations`
--

CREATE TABLE `global_budget_allocations` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `main_account_id` int(11) NOT NULL,
  `allocated_amount` decimal(15,2) NOT NULL,
  `remaining_amount` decimal(15,2) NOT NULL,
  `currency` enum('USD','AFS','EUR','DARHAM','SAR') NOT NULL,
  `allocation_date` date NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `group_tickets`
--

CREATE TABLE `group_tickets` (
  `ticket_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `family_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'JSON array of family IDs included in this group ticket',
  `member_ids` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'JSON array of booking IDs (umrah_bookings.booking_id) included',
  `airline_name` varchar(100) NOT NULL,
  `pnr` varchar(50) NOT NULL,
  `flight_type` enum('direct','indirect') NOT NULL DEFAULT 'direct',
  `flight_date` date NOT NULL,
  `return_date` date NOT NULL,
  `departure_city` varchar(100) DEFAULT NULL,
  `arrival_city` varchar(100) DEFAULT NULL,
  `flight_number_1` varchar(50) DEFAULT NULL,
  `flight_number_2` varchar(50) DEFAULT NULL,
  `departure_time` time DEFAULT NULL,
  `arrival_time` time DEFAULT NULL,
  `return_time` time DEFAULT NULL,
  `return_arrival_time` time DEFAULT NULL,
  `duration` varchar(20) DEFAULT NULL,
  `remarks` text DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `leg1_departure_city` varchar(100) DEFAULT NULL,
  `leg1_arrival_city` varchar(100) DEFAULT NULL,
  `leg1_flight_number` varchar(50) DEFAULT NULL,
  `leg1_departure_date` date DEFAULT NULL,
  `leg1_departure_time` time DEFAULT NULL,
  `leg1_arrival_date` date DEFAULT NULL,
  `leg1_arrival_time` time DEFAULT NULL,
  `leg2_departure_city` varchar(100) DEFAULT NULL,
  `leg2_arrival_city` varchar(100) DEFAULT NULL,
  `leg2_flight_number` varchar(50) DEFAULT NULL,
  `leg2_departure_date` date DEFAULT NULL,
  `leg2_departure_time` time DEFAULT NULL,
  `leg2_arrival_date` date DEFAULT NULL,
  `leg2_arrival_time` time DEFAULT NULL,
  `return_leg1_departure_city` varchar(100) DEFAULT NULL,
  `return_leg1_arrival_city` varchar(100) DEFAULT NULL,
  `return_leg1_flight_number` varchar(50) DEFAULT NULL,
  `return_leg1_departure_date` date DEFAULT NULL,
  `return_leg1_departure_time` time DEFAULT NULL,
  `return_leg1_arrival_date` date DEFAULT NULL,
  `return_leg1_arrival_time` time DEFAULT NULL,
  `return_leg2_departure_city` varchar(100) DEFAULT NULL,
  `return_leg2_arrival_city` varchar(100) DEFAULT NULL,
  `return_leg2_flight_number` varchar(50) DEFAULT NULL,
  `return_leg2_departure_date` date DEFAULT NULL,
  `return_leg2_departure_time` time DEFAULT NULL,
  `return_leg2_arrival_date` date DEFAULT NULL,
  `return_leg2_arrival_time` time DEFAULT NULL,
  `status` enum('active','archived') DEFAULT 'active'
) ;

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
  `paid_to` int(11) NOT NULL,
  `contact_no` varchar(20) DEFAULT NULL,
  `base_amount` decimal(10,3) DEFAULT NULL,
  `sold_amount` decimal(10,3) DEFAULT NULL,
  `profit` decimal(10,3) DEFAULT NULL,
  `currency` enum('USD','AFS') DEFAULT NULL,
  `remarks` mediumtext DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','refunded') NOT NULL DEFAULT 'active',
  `imported` tinyint(1) NOT NULL DEFAULT 0,
  `branch_id` bigint(20) DEFAULT NULL
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
  `supplier_penalty` decimal(10,2) DEFAULT 0.00,
  `service_penalty` decimal(10,2) DEFAULT 0.00,
  `base` decimal(10,2) DEFAULT 0.00,
  `sold` decimal(10,2) DEFAULT 0.00,
  `reason` text NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `exchange_rate` decimal(10,4) DEFAULT 1.0000,
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ip_blacklist`
--

CREATE TABLE `ip_blacklist` (
  `id` bigint(20) NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `tenant_id` int(11) DEFAULT NULL,
  `reason` varchar(255) DEFAULT NULL,
  `blocked_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `blocked_until` timestamp NULL DEFAULT NULL,
  `permanent` tinyint(1) DEFAULT 0,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Phase 5: Rate limiting - IP blocking';

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
  `euro_balance` decimal(10,3) NOT NULL DEFAULT 0.000,
  `darham_balance` decimal(10,3) NOT NULL DEFAULT 0.000,
  `sar_balance` decimal(10,3) NOT NULL DEFAULT 0.000,
  `last_updated` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `currency` enum('USD','AFS','EUR','DARHAM','SAR') NOT NULL,
  `description` mediumtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `transaction_of` enum('ticket_sale','visa_sale','ticket_refund','date_change','fund','umrah','hotel','hotel_refund','expense','debtor','supplier_fund','client_fund','budget_allocation','ticket_reserve','transfer','additional_payment','creditor','jv_payment','salary_payment','visa_refund','deposit_sarafi','hawala_sarafi','withdrawal_sarafi','umrah_refund','weight','supplier_fund_withdrawal','umrah_transaction','global_budget_allocation','withdraw_fund') NOT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `receipt` varchar(100) DEFAULT NULL,
  `exchange_rate` decimal(10,5) DEFAULT NULL,
  `branch_id` bigint(20) DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `cash_settlements`
--

CREATE TABLE `cash_settlements` (
  `id` int(10) unsigned NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'finance user being settled',
  `currency` enum('USD','AFS','EUR','DARHAM','SAR') NOT NULL DEFAULT 'USD',
  `amount` decimal(14,2) NOT NULL,
  `status` enum('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
  `request_note` text DEFAULT NULL,
  `requested_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `confirmed_by` int(11) DEFAULT NULL,
  `confirmed_at` datetime DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `rejected_at` datetime DEFAULT NULL,
  `reject_reason` varchar(500) DEFAULT NULL
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
  `status` enum('draft','sent','archived') NOT NULL DEFAULT 'draft',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `language` varchar(10) DEFAULT 'english',
  `file_path` varchar(255) DEFAULT NULL,
  `pdf_path` varchar(255) DEFAULT NULL,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `maktob_logs`
--

CREATE TABLE `maktob_logs` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `maktob_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `action` enum('create','edit','delete','send','archive') NOT NULL,
  `old_values` text DEFAULT NULL,
  `new_values` text DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
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

-- --------------------------------------------------------

--
-- Table structure for table `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `transaction_id` int(11) DEFAULT NULL,
  `transaction_type` enum('visa','supplier','ticket_date_change','ticket_refund','umrah','hotel','hotel_refund','ticket_sale','ticket_reserve','additional_payment','debtor','creditor','deposit_sarafi','hawala_sarafi','withdrawal_sarafi','supplier_fund','client_fund','weight','expense','expense_update','expense_delete','supplier_bonus','umrah_refund','visa_refund','supplier_fund_withdrawal','mtravels','withdraw_fund','cash_settlement') NOT NULL,
  `message` mediumtext NOT NULL,
  `recipient_role` enum('Admin','Sales','Finance') NOT NULL,
  `status` enum('Unread','Read') DEFAULT 'Unread',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_resets`
--

CREATE TABLE `password_resets` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'User who requested password reset',
  `token` varchar(255) NOT NULL COMMENT 'SHA-256 hashed reset token',
  `token_expiry` datetime NOT NULL COMMENT 'When token expires',
  `used` tinyint(1) DEFAULT 0 COMMENT 'Whether token has been used',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'When request was created',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp() COMMENT 'When record was last updated',
  `used_at` datetime DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL COMMENT 'IP address that created the reset request',
  `user_agent` text DEFAULT NULL COMMENT 'Browser user agent for the reset request'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `password_reset_requests`
--

CREATE TABLE `password_reset_requests` (
  `id` int(11) NOT NULL,
  `ip_address` varchar(45) NOT NULL COMMENT 'IPv4 or IPv6 address',
  `email` varchar(255) DEFAULT NULL COMMENT 'Email attempted for reset',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `payment_sessions`
--

CREATE TABLE `payment_sessions` (
  `id` int(11) NOT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `subscription_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `currency` varchar(10) DEFAULT NULL,
  `status` enum('pending','completed','failed') DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `features` longtext DEFAULT NULL,
  `price` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(50) NOT NULL,
  `max_users` int(11) NOT NULL DEFAULT 0,
  `max_branches` int(11) NOT NULL DEFAULT 1,
  `trial_days` int(11) NOT NULL DEFAULT 0,
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- --------------------------------------------------------

--
-- Table structure for table `rate_limits`
--

CREATE TABLE `rate_limits` (
  `id` bigint(20) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `key_type` varchar(50) NOT NULL DEFAULT 'user',
  `key_value` varchar(255) NOT NULL,
  `limit_name` varchar(100) NOT NULL,
  `limit_value` int(11) NOT NULL,
  `window_seconds` int(11) NOT NULL,
  `current_count` int(11) DEFAULT 0,
  `reset_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Phase 5: Rate limiting - tracks usage counts';

-- --------------------------------------------------------

--
-- Table structure for table `rate_limit_violations`
--

CREATE TABLE `rate_limit_violations` (
  `id` bigint(20) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `user_id` int(11) DEFAULT NULL,
  `limit_name` varchar(100) NOT NULL,
  `violation_count` int(11) DEFAULT 1,
  `current_value` int(11) DEFAULT NULL,
  `limit_value` int(11) DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `details` longtext DEFAULT NULL,
  `action_taken` varchar(50) DEFAULT NULL,
  `blocked_until` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Phase 5: Rate limiting - logs violations';

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
  `status` enum('Refunded') NOT NULL DEFAULT 'Refunded',
  `remarks` mediumtext NOT NULL,
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `calculation_method` varchar(11) NOT NULL,
  `imported` tinyint(1) NOT NULL DEFAULT 0,
  `branch_id` bigint(20) DEFAULT NULL
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `payment_day` int(11) NOT NULL DEFAULT 1 COMMENT 'Day of month when salary is paid',
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_agents`
--

CREATE TABLE `sales_agents` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `phone` varchar(20) DEFAULT NULL,
  `province` varchar(100) NOT NULL COMMENT 'Province or region where this agent operates',
  `region` varchar(100) DEFAULT NULL COMMENT 'Sub-region within province',
  `address` text DEFAULT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Reference to users table for authentication',
  `commission_rate` decimal(5,2) NOT NULL DEFAULT 10.00 COMMENT 'Commission percentage',
  `salary_type` enum('salary','commission','hybrid') NOT NULL DEFAULT 'commission' COMMENT 'Type of compensation',
  `base_salary` decimal(15,2) DEFAULT NULL COMMENT 'Monthly base salary (if applicable)',
  `status` enum('active','inactive','suspended') NOT NULL DEFAULT 'active',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_by` int(11) DEFAULT NULL COMMENT 'Super admin who created this agent'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_agent_commissions`
--

CREATE TABLE `sales_agent_commissions` (
  `id` int(11) NOT NULL,
  `sales_agent_id` int(11) NOT NULL,
  `period_month` date NOT NULL COMMENT 'Month for which commission is calculated',
  `base_amount` decimal(15,2) NOT NULL COMMENT 'Total subscription amount sold',
  `commission_rate` decimal(5,2) NOT NULL,
  `commission_amount` decimal(15,2) NOT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `status` enum('pending','approved','paid','cancelled') NOT NULL DEFAULT 'pending',
  `notes` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` datetime DEFAULT NULL,
  `paid_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_agent_performance`
--

CREATE TABLE `sales_agent_performance` (
  `id` int(11) NOT NULL,
  `sales_agent_id` int(11) NOT NULL,
  `month` date NOT NULL,
  `tenants_acquired` int(11) DEFAULT 0,
  `revenue_generated` decimal(15,2) DEFAULT 0.00,
  `commission_earned` decimal(15,2) DEFAULT 0.00,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_agent_salary_payments`
--

CREATE TABLE `sales_agent_salary_payments` (
  `id` int(11) NOT NULL,
  `sales_agent_id` int(11) NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `payment_date` date NOT NULL,
  `status` enum('paid','processing') NOT NULL DEFAULT 'paid',
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `sales_agent_tenants`
--

CREATE TABLE `sales_agent_tenants` (
  `id` int(11) NOT NULL,
  `sales_agent_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `status` enum('active','inactive','cancelled') NOT NULL DEFAULT 'active',
  `commission_earned` decimal(15,2) NOT NULL DEFAULT 0.00,
  `commission_currency` varchar(10) NOT NULL DEFAULT 'USD',
  `subscription_start_date` date NOT NULL,
  `subscription_end_date` date DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
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
  `updated_at` timestamp NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `security_audit_log`
--

CREATE TABLE `security_audit_log` (
  `id` int(10) UNSIGNED NOT NULL,
  `event_type` varchar(50) NOT NULL COMMENT 'Event type (LOGIN_FAILED, CSRF_ATTACK, etc)',
  `severity` varchar(20) NOT NULL COMMENT 'INFO, WARNING, CRITICAL',
  `user_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'User who triggered the event',
  `ip_address` varchar(45) NOT NULL COMMENT 'IPv4 or IPv6 address',
  `user_agent` varchar(255) DEFAULT NULL COMMENT 'Browser/client user agent',
  `tenant_id` int(10) UNSIGNED DEFAULT NULL COMMENT 'Tenant context',
  `endpoint` varchar(500) DEFAULT NULL COMMENT 'Request endpoint/URI',
  `description` varchar(500) DEFAULT NULL COMMENT 'Event description',
  `details` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin DEFAULT NULL COMMENT 'Additional event details',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp() COMMENT 'Event timestamp'
) ;

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
  `smtp_from_name` varchar(255) DEFAULT NULL,
  `monthly_price` decimal(10,2) DEFAULT 50.00 COMMENT 'Monthly price per additional branch',
  `quarterly_price` decimal(10,2) DEFAULT 150.00 COMMENT 'Quarterly price per additional branch',
  `yearly_price` decimal(10,2) DEFAULT 600.00 COMMENT 'Yearly price per additional branch',
  `umrah_id` varchar(100) DEFAULT NULL,
  `user_addon_monthly_price` decimal(10,2) DEFAULT 25.00,
  `user_addon_quarterly_price` decimal(10,2) DEFAULT 75.00,
  `user_addon_yearly_price` decimal(10,2) DEFAULT 300.00,
  `whatsapp_addon_monthly_price` decimal(10,2) DEFAULT NULL,
  `whatsapp_addon_quarterly_price` decimal(10,2) DEFAULT NULL,
  `whatsapp_addon_yearly_price` decimal(10,2) DEFAULT NULL,
  `smtp_addon_monthly_price` decimal(10,2) DEFAULT NULL,
  `smtp_addon_quarterly_price` decimal(10,2) DEFAULT NULL,
  `smtp_addon_yearly_price` decimal(10,2) DEFAULT NULL,
  `smtp_enabled` tinyint(1) DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ssl_certificates`
--

CREATE TABLE `ssl_certificates` (
  `id` int(11) NOT NULL,
  `domain` varchar(255) NOT NULL,
  `port` int(11) DEFAULT 443,
  `description` text DEFAULT NULL,
  `is_valid` tinyint(1) DEFAULT 0,
  `issuer` varchar(255) DEFAULT NULL,
  `subject` varchar(255) DEFAULT NULL,
  `valid_from` datetime DEFAULT NULL,
  `valid_to` datetime DEFAULT NULL,
  `days_until_expiry` int(11) DEFAULT NULL,
  `alert_level` enum('ok','info','warning','critical','expired','unknown') DEFAULT 'unknown',
  `serial_number` varchar(255) DEFAULT NULL,
  `error_message` text DEFAULT NULL,
  `last_checked` datetime DEFAULT NULL,
  `last_alert_sent` datetime DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `category` varchar(50) DEFAULT 'all',
  `phone` varchar(15) NOT NULL,
  `email` varchar(255) DEFAULT NULL,
  `address` mediumtext DEFAULT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `route_payment_to_main_account` tinyint(1) NOT NULL DEFAULT 0,
  `balance` decimal(10,3) NOT NULL DEFAULT 0.000,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `status` enum('active','inactive') NOT NULL DEFAULT 'active',
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supplier_transactions`
--

CREATE TABLE `supplier_transactions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `reference_id` int(11) NOT NULL,
  `transaction_type` enum('Debit','Credit') NOT NULL,
  `transaction_of` enum('ticket_sale','visa_sale','ticket_refund','date_change','fund','umrah','hotel','hotel_refund','ticket_reserve','jv_payment','visa_refund','hotel_refund','umrah_refund','additional_payment','weight_sale','supplier_bonus','fund_withdrawal','umrah_date_change','umrah_cancellation','visa_cancellation','umrah_transaction') NOT NULL,
  `amount` decimal(10,3) NOT NULL,
  `balance` decimal(15,3) NOT NULL,
  `remarks` text DEFAULT NULL,
  `transaction_date` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `receipt` varchar(100) NOT NULL DEFAULT '',
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `supplier_transaction_records`
--

CREATE TABLE `supplier_transaction_records` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `quarter` varchar(3) NOT NULL,
  `year` int(11) NOT NULL,
  `transaction_type` varchar(50) NOT NULL COMMENT 'ticket, service, visa, etc.',
  `amount` decimal(12,2) NOT NULL,
  `quantity` int(11) DEFAULT 1,
  `description` text DEFAULT NULL,
  `reference_id` varchar(100) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `support_tickets`
--

CREATE TABLE `support_tickets` (
  `id` int(11) NOT NULL,
  `ticket_number` varchar(20) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `created_by_user_id` int(11) NOT NULL,
  `created_by_role` enum('admin','finance','sales','umrah','super_admin') NOT NULL,
  `category_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` longtext NOT NULL,
  `priority` enum('low','medium','high','critical') DEFAULT 'medium',
  `screenshot_path` varchar(500) DEFAULT NULL,
  `status` enum('open','in_progress','resolved','closed') DEFAULT 'open',
  `resolved_at` timestamp NULL DEFAULT NULL,
  `resolved_by_user_id` int(11) DEFAULT NULL,
  `resolution_summary` longtext DEFAULT NULL,
  `sla_due_at` timestamp NULL DEFAULT NULL,
  `sla_status` enum('on_track','at_risk','breached','resolved') DEFAULT 'on_track',
  `first_response_at` timestamp NULL DEFAULT NULL,
  `is_first_response_met` tinyint(1) DEFAULT 0,
  `is_resolution_met` tinyint(1) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `suspicious_reset_activity`
-- (See below for the actual view)
--
CREATE TABLE `suspicious_reset_activity` (
`ip_address` varchar(45)
,`reset_attempts_last_hour` bigint(21)
,`unique_emails_targeted` bigint(21)
,`last_attempt_time` timestamp
);

-- --------------------------------------------------------

--
-- Table structure for table `system_expenses`
--

CREATE TABLE `system_expenses` (
  `id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `date` date NOT NULL,
  `description` mediumtext NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` enum('USD','AFS') NOT NULL DEFAULT 'USD',
  `payment_method` varchar(100) DEFAULT NULL,
  `reference_number` varchar(255) DEFAULT NULL,
  `receipt_file` varchar(255) DEFAULT NULL,
  `notes` mediumtext DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System-wide expenses tracked by admin';

-- --------------------------------------------------------

--
-- Table structure for table `system_expense_categories`
--

CREATE TABLE `system_expense_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System-wide expense categories for admin';

-- --------------------------------------------------------

--
-- Table structure for table `system_profit_loss_by_category`
--

CREATE TABLE `system_profit_loss_by_category` (
  `id` int(11) NOT NULL,
  `report_id` int(11) NOT NULL,
  `category_id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `total_amount` decimal(15,2) DEFAULT 0.00,
  `expense_count` int(11) DEFAULT 0,
  `percentage_of_total` decimal(5,2) DEFAULT 0.00,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Expense breakdown by category in profit/loss reports';

-- --------------------------------------------------------

--
-- Table structure for table `system_profit_loss_reports`
--

CREATE TABLE `system_profit_loss_reports` (
  `id` int(11) NOT NULL,
  `report_period` varchar(50) NOT NULL COMMENT 'e.g., 2025-01 (YYYY-MM)',
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `total_revenue` decimal(15,2) DEFAULT 0.00,
  `total_expenses` decimal(15,2) DEFAULT 0.00,
  `currency` enum('USD','AFS') NOT NULL DEFAULT 'USD',
  `gross_profit` decimal(15,2) DEFAULT 0.00 COMMENT 'Revenue - Expenses',
  `profit_margin_percentage` decimal(5,2) DEFAULT 0.00 COMMENT 'Profit as percentage of revenue',
  `notes` mediumtext DEFAULT NULL,
  `generated_by` int(11) DEFAULT NULL,
  `generated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `last_updated` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Monthly profit/loss summary for system admin';

-- --------------------------------------------------------

--
-- Table structure for table `system_revenue`
--

CREATE TABLE `system_revenue` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `revenue_type` enum('subscription','addon','commission','fee','other') NOT NULL,
  `amount` decimal(15,2) NOT NULL,
  `currency` enum('USD','AFS') NOT NULL DEFAULT 'USD',
  `description` mediumtext DEFAULT NULL,
  `reference_id` varchar(255) DEFAULT NULL COMMENT 'Link to tenant subscription, addon, or transaction',
  `payment_date` date NOT NULL,
  `status` enum('pending','completed','failed') DEFAULT 'completed',
  `notes` mediumtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='System admin revenue from subscriptions and services';

-- --------------------------------------------------------

--
-- Table structure for table `tax_reports`
--

CREATE TABLE `tax_reports` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `quarter` varchar(3) NOT NULL,
  `year` int(11) NOT NULL,
  `report_type` enum('supplier','general') NOT NULL,
  `report_data` longtext NOT NULL COMMENT 'JSON formatted report data',
  `supplier_id` int(11) DEFAULT NULL COMMENT 'For supplier-specific reports',
  `branch_id` bigint(20) DEFAULT NULL COMMENT 'Branch identifier for multi-branch support',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `tax_report_specifications`
--

CREATE TABLE `tax_report_specifications` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `quarter` varchar(3) NOT NULL COMMENT 'Q1, Q2, Q3, Q4',
  `year` int(11) NOT NULL,
  `data_type` enum('actual','random') NOT NULL DEFAULT 'actual',
  `profit_min` decimal(12,2) DEFAULT NULL COMMENT 'Minimum profit for random generation',
  `profit_max` decimal(12,2) DEFAULT NULL COMMENT 'Maximum profit for random generation',
  `item_count` int(11) DEFAULT NULL COMMENT 'Number of items/services to include',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
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
  `sales_agent_id` int(11) DEFAULT NULL,
  `status` enum('active','suspended','trial','deleted') NOT NULL DEFAULT 'trial',
  `plan` enum('basic','pro','enterprise') DEFAULT 'basic',
  `trial_days` int(11) NOT NULL DEFAULT 0,
  `trial_end_date` date DEFAULT NULL,
  `total_subscription_amount` decimal(15,2) DEFAULT 0.00,
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

-- --------------------------------------------------------

--
-- Table structure for table `tenant_subscriptions`
--

CREATE TABLE `tenant_subscriptions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `plan_id` varchar(50) NOT NULL,
  `status` enum('active','pending','trial','cancelled','expired') NOT NULL DEFAULT 'pending',
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

-- --------------------------------------------------------

--
-- Table structure for table `tenant_templates`
--

CREATE TABLE `tenant_templates` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `template_name` varchar(100) NOT NULL,
  `language` enum('ps','dari') NOT NULL DEFAULT 'ps',
  `template_content` longtext NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `position` varchar(255) DEFAULT NULL,
  `rating` int(11) DEFAULT NULL,
  `active` tinyint(1) DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `sold_to` int(11) NOT NULL,
  `paid_to` int(11) NOT NULL,
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
  `description` mediumtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `trip_type` enum('one_way','round_trip') NOT NULL DEFAULT 'one_way',
  `return_date` date DEFAULT NULL,
  `return_departure_time` time DEFAULT NULL,
  `return_origin` varchar(100) DEFAULT NULL,
  `return_destination` varchar(100) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `imported` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_categories`
--

CREATE TABLE `ticket_categories` (
  `id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `icon` varchar(50) DEFAULT NULL,
  `color` varchar(10) DEFAULT NULL,
  `status` enum('active','inactive') DEFAULT 'active',
  `sort_order` int(11) DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_notifications`
--

CREATE TABLE `ticket_notifications` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `notification_type` enum('created','reply','status_change','sla_breach') NOT NULL,
  `sent_via` enum('email','whatsapp','in_app') DEFAULT 'email',
  `sent_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_replies`
--

CREATE TABLE `ticket_replies` (
  `id` int(11) NOT NULL,
  `ticket_id` int(11) NOT NULL,
  `replied_by_user_id` int(11) NOT NULL,
  `is_internal_note` tinyint(1) DEFAULT 0,
  `reply_text` longtext NOT NULL,
  `screenshot_path` varchar(500) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_reservations`
--

CREATE TABLE `ticket_reservations` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `supplier` varchar(255) NOT NULL,
  `sold_to` int(11) NOT NULL,
  `paid_to` int(11) NOT NULL,
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
  `description` mediumtext DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `trip_type` enum('one_way','round_trip') NOT NULL DEFAULT 'one_way',
  `return_date` date DEFAULT NULL,
  `return_origin` varchar(100) DEFAULT NULL,
  `return_destination` varchar(100) DEFAULT NULL,
  `created_by` int(11) NOT NULL,
  `imported` tinyint(1) NOT NULL DEFAULT 0,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `ticket_sla_rules`
--

CREATE TABLE `ticket_sla_rules` (
  `id` int(11) NOT NULL,
  `priority` enum('low','medium','high','critical') NOT NULL,
  `first_response_hours` int(11) NOT NULL,
  `resolution_hours` int(11) NOT NULL,
  `description` varchar(255) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
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
  `created_by` int(11) NOT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  `imported` tinyint(1) NOT NULL DEFAULT 0,
  `branch_id` bigint(20) DEFAULT NULL
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
  `used_at` datetime DEFAULT NULL,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- --------------------------------------------------------

--
-- Table structure for table `tutorials`
--

CREATE TABLE `tutorials` (
  `id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text DEFAULT NULL,
  `category` varchar(100) DEFAULT NULL,
  `page` varchar(255) DEFAULT NULL,
  `video_type` enum('youtube','vimeo') NOT NULL DEFAULT 'vimeo',
  `video_id` varchar(255) NOT NULL DEFAULT '',
  `duration` varchar(20) DEFAULT '5:00',
  `level` enum('Beginner','Intermediate','Advanced') DEFAULT 'Beginner',
  `roles` longtext DEFAULT NULL COMMENT 'JSON array of allowed roles',
  `sort_order` int(11) DEFAULT 0,
  `show_on_load` tinyint(1) DEFAULT 0,
  `status` tinyint(1) DEFAULT 1,
  `chapters` longtext DEFAULT NULL COMMENT 'JSON array of chapters (label, time, seconds)',
  `created_by` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
  `exchange_rate` decimal(12,4) NOT NULL DEFAULT 1.0000,
  `created_by` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `remarks` varchar(100) NOT NULL,
  `photo_path` varchar(500) DEFAULT NULL,
  `passport_path` varchar(500) DEFAULT NULL,
  `visa_path` varchar(255) DEFAULT NULL,
  `photo_uploaded_at` timestamp NULL DEFAULT NULL,
  `passport_uploaded_at` timestamp NULL DEFAULT NULL,
  `visa_uploaded_at` timestamp NULL DEFAULT NULL,
  `status` enum('active','refunded','cancelled','pending') NOT NULL DEFAULT 'pending',
  `imported` tinyint(1) NOT NULL DEFAULT 0,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `umrah_booking_services`
--

CREATE TABLE `umrah_booking_services` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `booking_id` int(11) NOT NULL,
  `service_type` varchar(50) NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `base_price` decimal(10,3) NOT NULL DEFAULT 0.000,
  `sold_price` decimal(10,3) NOT NULL DEFAULT 0.000,
  `profit` decimal(10,3) NOT NULL DEFAULT 0.000,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
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
  `supplier_penalty` decimal(10,2) DEFAULT 0.00,
  `service_penalty` decimal(10,2) DEFAULT 0.00,
  `base` decimal(10,2) DEFAULT 0.00,
  `sold` decimal(10,2) DEFAULT 0.00,
  `reason` text NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `processed_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `umrah_transactions`
--

CREATE TABLE `umrah_transactions` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `umrah_booking_id` int(11) NOT NULL,
  `transaction_type` enum('Credit') DEFAULT NULL,
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

-- --------------------------------------------------------

--
-- Table structure for table `user_addons`
--

CREATE TABLE `user_addons` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `plan_id` int(11) NOT NULL,
  `base_users` int(11) NOT NULL DEFAULT 0,
  `additional_users` int(11) NOT NULL DEFAULT 0,
  `addon_price_per_user` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `total_addon_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `billing_cycle` enum('monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
  `status` enum('active','inactive','cancelled') NOT NULL DEFAULT 'active',
  `next_renewal_date` date DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_addon_payments`
--

CREATE TABLE `user_addon_payments` (
  `id` int(11) NOT NULL,
  `addon_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `amount` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `payment_method` varchar(50) DEFAULT NULL,
  `transaction_id` varchar(255) DEFAULT NULL,
  `status` enum('pending','completed','failed','refunded') NOT NULL DEFAULT 'pending',
  `payment_date` timestamp NULL DEFAULT NULL,
  `period_start` date DEFAULT NULL,
  `period_end` date DEFAULT NULL,
  `receipt_url` varchar(500) DEFAULT NULL,
  `notes` text DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_addon_requests`
--

CREATE TABLE `user_addon_requests` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `requested_additional_users` int(11) NOT NULL DEFAULT 0,
  `billing_cycle` enum('monthly','quarterly','yearly') NOT NULL DEFAULT 'monthly',
  `estimated_monthly_cost` decimal(10,2) NOT NULL DEFAULT 0.00,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `status` enum('pending','approved','rejected','cancelled') NOT NULL DEFAULT 'pending',
  `approval_notes` text DEFAULT NULL,
  `approved_by` int(11) DEFAULT NULL,
  `approved_at` timestamp NULL DEFAULT NULL,
  `rejection_reason` text DEFAULT NULL,
  `rejected_by` int(11) DEFAULT NULL,
  `rejected_at` timestamp NULL DEFAULT NULL,
  `requested_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
-- Table structure for table `user_online_sessions`
--

CREATE TABLE `user_online_sessions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `session_id` varchar(255) DEFAULT NULL,
  `last_activity` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_tutorial_learned`
--

CREATE TABLE `user_tutorial_learned` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `tutorial_id` int(11) NOT NULL,
  `learned_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `user_typing_status`
--

CREATE TABLE `user_typing_status` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `peer_id` int(11) NOT NULL,
  `is_typing` tinyint(4) DEFAULT 0,
  `last_update` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
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
  `paid_to` int(11) NOT NULL,
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
  `created_by` int(11) NOT NULL,
  `imported` tinyint(1) NOT NULL DEFAULT 0,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `visa_documents`
--

CREATE TABLE `visa_documents` (
  `id` int(11) NOT NULL,
  `visa_id` int(11) NOT NULL,
  `doc_type` varchar(100) NOT NULL COMMENT 'Document type name (can be custom)',
  `file_path` varchar(255) NOT NULL,
  `original_filename` varchar(255) DEFAULT NULL,
  `file_size` int(11) DEFAULT NULL COMMENT 'Size in bytes',
  `mime_type` varchar(50) DEFAULT NULL,
  `is_required` tinyint(1) DEFAULT 0,
  `status` enum('pending','approved','rejected') DEFAULT 'pending',
  `rejection_reason` text DEFAULT NULL,
  `uploaded_by` int(11) NOT NULL,
  `uploaded_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `tenant_id` int(11) NOT NULL,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Table structure for table `visa_document_types`
--

CREATE TABLE `visa_document_types` (
  `id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `name` varchar(100) NOT NULL,
  `description` text DEFAULT NULL,
  `is_required` tinyint(1) DEFAULT 0,
  `visa_type` varchar(50) DEFAULT NULL,
  `category` enum('passport','identity','financial','medical','legal','other') DEFAULT 'other',
  `status` enum('active','inactive') DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
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
  `supplier_penalty` decimal(10,2) DEFAULT 0.00,
  `service_penalty` decimal(10,2) DEFAULT 0.00,
  `base` decimal(10,2) DEFAULT 0.00,
  `sold` decimal(10,2) DEFAULT 0.00,
  `reason` mediumtext NOT NULL,
  `description` mediumtext DEFAULT NULL,
  `currency` varchar(10) NOT NULL DEFAULT 'USD',
  `refund_date` datetime DEFAULT current_timestamp(),
  `processed_by` int(11) DEFAULT NULL,
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Stand-in structure for view `v_tickets_with_sla`
-- (See below for the actual view)
--
CREATE TABLE `v_tickets_with_sla` (
`id` int(11)
,`ticket_number` varchar(20)
,`title` varchar(255)
,`description` longtext
,`priority` enum('low','medium','high','critical')
,`status` enum('open','in_progress','resolved','closed')
,`category_id` int(11)
,`category_name` varchar(100)
,`tenant_id` int(11)
,`branch_id` int(11)
,`created_by_user_id` int(11)
,`created_by_name` varchar(100)
,`created_by_email` varchar(50)
,`created_at` timestamp
,`sla_due_at` timestamp
,`sla_status` enum('on_track','at_risk','breached','resolved')
,`first_response_hours` int(11)
,`resolution_hours` int(11)
,`hours_remaining` bigint(21)
,`Name_exp_20` varchar(8)
);

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
  `raw_response` longtext DEFAULT NULL,
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
  `template_variables` longtext DEFAULT NULL,
  `status` enum('pending','sent','delivered','failed','expired') NOT NULL DEFAULT 'pending',
  `provider_message_id` varchar(100) DEFAULT NULL COMMENT 'Message ID from WhatsApp provider',
  `error_message` text DEFAULT NULL COMMENT 'Error message if sending failed',
  `retry_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of retry attempts',
  `priority` int(11) NOT NULL DEFAULT 5 COMMENT 'Message priority (1=highest, 10=lowest)',
  `scheduled_at` timestamp NULL DEFAULT NULL COMMENT 'When to send the message (for scheduled messages)',
  `sent_at` timestamp NULL DEFAULT NULL COMMENT 'When the message was actually sent',
  `sent_via` varchar(20) NOT NULL DEFAULT 'text',
  `delivered_at` timestamp NULL DEFAULT NULL COMMENT 'When message was delivered to recipient',
  `failed_at` timestamp NULL DEFAULT NULL COMMENT 'When message failed to send',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `branch_id` bigint(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- --------------------------------------------------------

--
-- Structure for view `suspicious_reset_activity`
--
DROP TABLE IF EXISTS `suspicious_reset_activity`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `suspicious_reset_activity`  AS SELECT `pr`.`ip_address` AS `ip_address`, count(0) AS `reset_attempts_last_hour`, count(distinct `pr`.`email`) AS `unique_emails_targeted`, max(`pr`.`created_at`) AS `last_attempt_time` FROM `password_reset_requests` AS `pr` WHERE `pr`.`created_at` > current_timestamp() - interval 1 hour GROUP BY `pr`.`ip_address` HAVING count(0) > 5 ORDER BY count(0) DESC ;

-- --------------------------------------------------------

--
-- Structure for view `v_tickets_with_sla`
--
DROP TABLE IF EXISTS `v_tickets_with_sla`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `v_tickets_with_sla`  AS SELECT `t`.`id` AS `id`, `t`.`ticket_number` AS `ticket_number`, `t`.`title` AS `title`, `t`.`description` AS `description`, `t`.`priority` AS `priority`, `t`.`status` AS `status`, `t`.`category_id` AS `category_id`, `tc`.`name` AS `category_name`, `t`.`tenant_id` AS `tenant_id`, `t`.`branch_id` AS `branch_id`, `t`.`created_by_user_id` AS `created_by_user_id`, `u`.`name` AS `created_by_name`, `u`.`email` AS `created_by_email`, `t`.`created_at` AS `created_at`, `t`.`sla_due_at` AS `sla_due_at`, `t`.`sla_status` AS `sla_status`, `sr`.`first_response_hours` AS `first_response_hours`, `sr`.`resolution_hours` AS `resolution_hours`, timestampdiff(HOUR,current_timestamp(),`t`.`sla_due_at`) AS `hours_remaining`, CASE WHEN `t`.`status` = 'resolved' THEN 'Resolved' WHEN current_timestamp() > `t`.`sla_due_at` THEN 'BREACHED' WHEN timestampdiff(HOUR,current_timestamp(),`t`.`sla_due_at`) <= `sr`.`first_response_hours` * 0.25 THEN 'At Risk' ELSE 'On Track' END AS `Name_exp_20` FROM (((`support_tickets` `t` left join `ticket_categories` `tc` on(`t`.`category_id` = `tc`.`id`)) left join `users` `u` on(`t`.`created_by_user_id` = `u`.`id`)) left join `ticket_sla_rules` `sr` on(`t`.`priority` = `sr`.`priority`)) ;

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
-- Indexes for table `advanced_rate_limits`
--
ALTER TABLE `advanced_rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_limit_key` (`limit_key`),
  ADD KEY `idx_last_updated` (`last_updated`);

--
-- Indexes for table `advanced_rate_limit_requests`
--
ALTER TABLE `advanced_rate_limit_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_limit_key_timestamp` (`limit_key`,`timestamp`),
  ADD KEY `idx_timestamp` (`timestamp`);

--
-- Indexes for table `api_versions`
--
ALTER TABLE `api_versions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_version` (`version`);

--
-- Indexes for table `assets`
--
ALTER TABLE `assets`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `attendance`
--
ALTER TABLE `attendance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_attendance` (`tenant_id`,`branch_id`,`user_id`,`date`),
  ADD KEY `idx_tenant_branch` (`tenant_id`,`branch_id`),
  ADD KEY `idx_user_date` (`user_id`,`date`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `attendance_settings`
--
ALTER TABLE `attendance_settings`
  ADD PRIMARY KEY (`tenant_id`,`branch_id`),
  ADD KEY `idx_tenant_branch` (`tenant_id`,`branch_id`);

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
-- Indexes for table `branch_addons`
--
ALTER TABLE `branch_addons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `plan_id` (`plan_id`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_tenant_status` (`tenant_id`,`status`);

--
-- Indexes for table `branch_addon_payments`
--
ALTER TABLE `branch_addon_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `addon_id` (`addon_id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `status` (`status`),
  ADD KEY `idx_payment_date` (`payment_date`);

--
-- Indexes for table `branch_addon_requests`
--
ALTER TABLE `branch_addon_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `status` (`status`),
  ADD KEY `approved_by` (`approved_by`),
  ADD KEY `idx_tenant_status` (`tenant_id`,`status`);

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
-- Indexes for table `branch_chat_settings`
--
ALTER TABLE `branch_chat_settings`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_branch_settings` (`tenant_id`,`branch_id`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_branch` (`branch_id`);

--
-- Indexes for table `branch_peering`
--
ALTER TABLE `branch_peering`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `branch_peer_unique` (`branch_id`,`peer_branch_id`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_branch` (`branch_id`),
  ADD KEY `idx_peer_tenant` (`peer_tenant_id`),
  ADD KEY `fk_bp_peer_branch` (`peer_branch_id`);

--
-- Indexes for table `branch_performance_alerts`
--
ALTER TABLE `branch_performance_alerts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_branch_id` (`branch_id`),
  ADD KEY `idx_severity` (`severity`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_recent_alerts` (`tenant_id`,`status`,`created_at`);

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
-- Indexes for table `chat_audit_log`
--
ALTER TABLE `chat_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_action` (`tenant_id`,`action`,`created_at`),
  ADD KEY `idx_user_time` (`user_id`,`created_at`),
  ADD KEY `idx_target_user` (`target_user_id`,`created_at`),
  ADD KEY `idx_message_id` (`message_id`),
  ADD KEY `idx_status` (`status`,`created_at`),
  ADD KEY `idx_action_time` (`action`,`created_at`);

--
-- Indexes for table `chat_audit_log_archive`
--
ALTER TABLE `chat_audit_log_archive`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_time` (`tenant_id`,`created_at`),
  ADD KEY `idx_user_time` (`user_id`,`created_at`);

--
-- Indexes for table `chat_groups`
--
ALTER TABLE `chat_groups`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `created_by_id` (`created_by_id`),
  ADD KEY `group_type` (`group_type`),
  ADD KEY `is_archived` (`is_archived`);

--
-- Indexes for table `chat_group_members`
--
ALTER TABLE `chat_group_members`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_member` (`group_id`,`member_id`,`member_type`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `member_id` (`member_id`),
  ADD KEY `member_type` (`member_type`),
  ADD KEY `idx_group_members_active` (`group_id`,`left_at`);

--
-- Indexes for table `chat_group_messages`
--
ALTER TABLE `chat_group_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `group_id` (`group_id`),
  ADD KEY `from_user_id` (`from_user_id`),
  ADD KEY `created_at` (`created_at`),
  ADD KEY `from_user_type` (`from_user_type`),
  ADD KEY `idx_group_messages` (`group_id`,`created_at`);

--
-- Indexes for table `chat_group_message_reads`
--
ALTER TABLE `chat_group_message_reads`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_read` (`message_id`,`member_id`,`member_type`),
  ADD KEY `message_id` (`message_id`),
  ADD KEY `member_id` (`member_id`);

--
-- Indexes for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_room_time` (`room_id`,`created_at`),
  ADD KEY `idx_to_user` (`to_user_id`),
  ADD KEY `fk_cm_from_user` (`from_user_id`),
  ADD KEY `idx_tenant_from_user` (`tenant_id_from`,`from_user_id`),
  ADD KEY `idx_to_user_seen` (`to_user_id`,`seen_at`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_is_encrypted` (`is_encrypted`),
  ADD KEY `idx_encryption_key` (`encryption_key_id`),
  ADD KEY `idx_tenant_from` (`tenant_id_from`);

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
-- Indexes for table `communication_addons`
--
ALTER TABLE `communication_addons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_type_status` (`tenant_id`,`addon_type`,`status`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `communication_addon_requests`
--
ALTER TABLE `communication_addon_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_type_status` (`tenant_id`,`addon_type`,`status`),
  ADD KEY `idx_status` (`status`);

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
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `customer_wallets_ibfk_1` (`customer_id`);

--
-- Indexes for table `custom_plan_requests`
--
ALTER TABLE `custom_plan_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `status` (`status`),
  ADD KEY `contact_email` (`contact_email`);

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
-- Indexes for table `encryption_audit`
--
ALTER TABLE `encryption_audit`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_action` (`action`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `fk_ea_user` (`user_id`);

--
-- Indexes for table `encryption_keys`
--
ALTER TABLE `encryption_keys`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `tenant_key_name` (`tenant_id`,`key_name`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `encryption_key_rotations`
--
ALTER TABLE `encryption_key_rotations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_status` (`status`);

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
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `idx_expenses_sub_category` (`sub_category_id`,`category_id`);

--
-- Indexes for table `expense_categories`
--
ALTER TABLE `expense_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `idx_expense_categories_parent` (`parent_id`,`tenant_id`,`branch_id`);

--
-- Indexes for table `expense_report_config`
--
ALTER TABLE `expense_report_config`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_quarter_year` (`quarter`,`year`),
  ADD KEY `idx_category` (`expense_category`);

--
-- Indexes for table `families`
--
ALTER TABLE `families`
  ADD PRIMARY KEY (`family_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `finance_tracker`
--
ALTER TABLE `finance_tracker`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_branch_tenant` (`branch_id`,`tenant_id`),
  ADD KEY `idx_date` (`date`),
  ADD KEY `idx_type_currency` (`type`,`currency`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `fk_finance_tracker_tenant` (`tenant_id`);

--
-- Indexes for table `floating_tasks`
--
ALTER TABLE `floating_tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_tenant` (`user_id`,`tenant_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_branch_id` (`branch_id`);

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
-- Indexes for table `global_budget_allocations`
--
ALTER TABLE `global_budget_allocations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `main_account_id` (`main_account_id`);

--
-- Indexes for table `group_tickets`
--
ALTER TABLE `group_tickets`
  ADD PRIMARY KEY (`ticket_id`),
  ADD KEY `idx_tenant_branch` (`tenant_id`,`branch_id`),
  ADD KEY `idx_created_by` (`created_by`),
  ADD KEY `idx_flight_date` (`flight_date`);

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
-- Indexes for table `ip_blacklist`
--
ALTER TABLE `ip_blacklist`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_ip_tenant` (`ip_address`,`tenant_id`),
  ADD KEY `idx_blocked_until` (`blocked_until`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_blacklist_active` (`blocked_until`,`permanent`);

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
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `idx_mat_created_by` (`created_by`, `created_at`);

--
-- Indexes for table `maktobs`
--
ALTER TABLE `maktobs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sender_id` (`sender_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `maktob_logs`
--
ALTER TABLE `maktob_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `maktob_id` (`maktob_id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `action` (`action`);

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
-- Indexes for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `token` (`token`),
  ADD KEY `idx_token` (`token`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_token_expiry` (`token_expiry`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_used_status` (`used`,`token_expiry`);

--
-- Indexes for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ip_created` (`ip_address`,`created_at`),
  ADD KEY `idx_email_created` (`email`,`created_at`);

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
-- Indexes for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_limit` (`tenant_id`,`key_type`,`key_value`,`limit_name`),
  ADD KEY `idx_reset_at` (`reset_at`),
  ADD KEY `idx_key_value` (`key_value`),
  ADD KEY `idx_tenant_action` (`tenant_id`,`limit_name`),
  ADD KEY `idx_rate_limits_window` (`reset_at`,`current_count`);

--
-- Indexes for table `rate_limit_violations`
--
ALTER TABLE `rate_limit_violations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_user` (`tenant_id`,`user_id`),
  ADD KEY `idx_user_created` (`user_id`,`created_at`),
  ADD KEY `idx_limit_name` (`limit_name`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_violations_action` (`action_taken`,`created_at`);

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
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `salary_management_ibfk_1` (`user_id`);

--
-- Indexes for table `salary_payments`
--
ALTER TABLE `salary_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `main_account_id` (`main_account_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `sales_agents`
--
ALTER TABLE `sales_agents`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`),
  ADD UNIQUE KEY `unique_email` (`email`),
  ADD UNIQUE KEY `unique_user_id` (`user_id`),
  ADD KEY `idx_province` (`province`),
  ADD KEY `idx_region` (`region`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created_by` (`created_by`);

--
-- Indexes for table `sales_agent_commissions`
--
ALTER TABLE `sales_agent_commissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_agent_period` (`sales_agent_id`,`period_month`),
  ADD KEY `idx_sales_agent_id` (`sales_agent_id`),
  ADD KEY `idx_period_month` (`period_month`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_approved_by` (`approved_by`);

--
-- Indexes for table `sales_agent_performance`
--
ALTER TABLE `sales_agent_performance`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `agent_month` (`sales_agent_id`,`month`),
  ADD KEY `sales_agent_id` (`sales_agent_id`),
  ADD KEY `month` (`month`);

--
-- Indexes for table `sales_agent_salary_payments`
--
ALTER TABLE `sales_agent_salary_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `sales_agent_id` (`sales_agent_id`),
  ADD KEY `payment_date` (`payment_date`),
  ADD KEY `status` (`status`);

--
-- Indexes for table `sales_agent_tenants`
--
ALTER TABLE `sales_agent_tenants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_agent_tenant` (`sales_agent_id`,`tenant_id`),
  ADD KEY `idx_sales_agent_id` (`sales_agent_id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `sarafi_transactions`
--
ALTER TABLE `sarafi_transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `security_audit_log`
--
ALTER TABLE `security_audit_log`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_ip_address` (`ip_address`),
  ADD KEY `idx_severity` (`severity`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_combined` (`severity`,`created_at`),
  ADD KEY `idx_event_user` (`event_type`,`user_id`,`created_at`);

--
-- Indexes for table `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `ssl_certificates`
--
ALTER TABLE `ssl_certificates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_domain` (`domain`),
  ADD KEY `idx_alert_level` (`alert_level`),
  ADD KEY `idx_last_checked` (`last_checked`),
  ADD KEY `idx_days_until_expiry` (`days_until_expiry`);

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
-- Indexes for table `supplier_transaction_records`
--
ALTER TABLE `supplier_transaction_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_supplier` (`supplier_id`),
  ADD KEY `idx_quarter_year` (`quarter`,`year`),
  ADD KEY `idx_reference` (`reference_id`);

--
-- Indexes for table `support_tickets`
--
ALTER TABLE `support_tickets`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `ticket_number` (`ticket_number`),
  ADD KEY `idx_ticket_number` (`ticket_number`),
  ADD KEY `idx_tenant_branch` (`tenant_id`,`branch_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_sla_status` (`sla_status`),
  ADD KEY `idx_priority` (`priority`),
  ADD KEY `idx_category` (`category_id`),
  ADD KEY `idx_created_by` (`created_by_user_id`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `idx_sla_due` (`sla_due_at`);
ALTER TABLE `support_tickets` ADD FULLTEXT KEY `ft_title_desc` (`title`,`description`);

--
-- Indexes for table `system_expenses`
--
ALTER TABLE `system_expenses`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category_id` (`category_id`),
  ADD KEY `date` (`date`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `idx_system_expenses_date` (`date`),
  ADD KEY `idx_system_expenses_category_date` (`category_id`,`date`);

--
-- Indexes for table `system_expense_categories`
--
ALTER TABLE `system_expense_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`);

--
-- Indexes for table `system_profit_loss_by_category`
--
ALTER TABLE `system_profit_loss_by_category`
  ADD PRIMARY KEY (`id`),
  ADD KEY `report_id` (`report_id`),
  ADD KEY `category_id` (`category_id`);

--
-- Indexes for table `system_profit_loss_reports`
--
ALTER TABLE `system_profit_loss_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `report_period` (`report_period`),
  ADD KEY `start_date` (`start_date`),
  ADD KEY `end_date` (`end_date`),
  ADD KEY `generated_by` (`generated_by`),
  ADD KEY `idx_system_pl_period` (`report_period`);

--
-- Indexes for table `system_revenue`
--
ALTER TABLE `system_revenue`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `revenue_type` (`revenue_type`),
  ADD KEY `payment_date` (`payment_date`),
  ADD KEY `idx_system_revenue_date` (`payment_date`),
  ADD KEY `idx_system_revenue_tenant_date` (`tenant_id`,`payment_date`);

--
-- Indexes for table `tax_reports`
--
ALTER TABLE `tax_reports`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_supplier_report` (`tenant_id`,`supplier_id`,`quarter`,`year`,`branch_id`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_quarter_year` (`quarter`,`year`),
  ADD KEY `idx_supplier` (`supplier_id`),
  ADD KEY `idx_report_type` (`report_type`),
  ADD KEY `idx_branch` (`branch_id`);

--
-- Indexes for table `tax_report_specifications`
--
ALTER TABLE `tax_report_specifications`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_spec` (`tenant_id`,`supplier_id`,`quarter`,`year`),
  ADD KEY `idx_tenant` (`tenant_id`),
  ADD KEY `idx_supplier` (`supplier_id`),
  ADD KEY `idx_quarter_year` (`quarter`,`year`);

--
-- Indexes for table `tenants`
--
ALTER TABLE `tenants`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `identifier` (`identifier`),
  ADD KEY `fk_tenants_sales_agent` (`sales_agent_id`);

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
-- Indexes for table `tenant_templates`
--
ALTER TABLE `tenant_templates`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_tenant_template_lang` (`tenant_id`,`template_name`,`language`),
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
-- Indexes for table `ticket_categories`
--
ALTER TABLE `ticket_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `name` (`name`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_sort` (`sort_order`);

--
-- Indexes for table `ticket_notifications`
--
ALTER TABLE `ticket_notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_ticket_user` (`ticket_id`,`user_id`),
  ADD KEY `idx_notification_type` (`notification_type`),
  ADD KEY `idx_sent_at` (`sent_at`);

--
-- Indexes for table `ticket_replies`
--
ALTER TABLE `ticket_replies`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_ticket` (`ticket_id`),
  ADD KEY `idx_internal` (`is_internal_note`),
  ADD KEY `idx_created` (`created_at`);
ALTER TABLE `ticket_replies` ADD FULLTEXT KEY `ft_reply_text` (`reply_text`);

--
-- Indexes for table `ticket_reservations`
--
ALTER TABLE `ticket_reservations`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `ticket_sla_rules`
--
ALTER TABLE `ticket_sla_rules`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `priority` (`priority`),
  ADD KEY `idx_priority` (`priority`);

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
-- Indexes for table `tutorials`
--
ALTER TABLE `tutorials`
  ADD PRIMARY KEY (`id`),
  ADD KEY `category` (`category`),
  ADD KEY `status` (`status`);

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
-- Indexes for table `user_addons`
--
ALTER TABLE `user_addons`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_tenant_status` (`tenant_id`,`status`);

--
-- Indexes for table `user_addon_payments`
--
ALTER TABLE `user_addon_payments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_addon_id` (`addon_id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_status` (`status`);

--
-- Indexes for table `user_addon_requests`
--
ALTER TABLE `user_addon_requests`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_tenant_id` (`tenant_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_tenant_status` (`tenant_id`,`status`);

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
-- Indexes for table `user_online_sessions`
--
ALTER TABLE `user_online_sessions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_session` (`user_id`,`session_id`),
  ADD KEY `idx_last_activity` (`last_activity`);

--
-- Indexes for table `user_tutorial_learned`
--
ALTER TABLE `user_tutorial_learned`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `user_tutorial` (`user_id`,`tutorial_id`),
  ADD KEY `tutorial_id` (`tutorial_id`);

--
-- Indexes for table `user_typing_status`
--
ALTER TABLE `user_typing_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_typing` (`user_id`,`peer_id`),
  ADD KEY `idx_last_update` (`last_update`);

--
-- Indexes for table `visa_applications`
--
ALTER TABLE `visa_applications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `tenant_id` (`tenant_id`);

--
-- Indexes for table `visa_documents`
--
ALTER TABLE `visa_documents`
  ADD PRIMARY KEY (`id`),
  ADD KEY `visa_id` (`visa_id`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `status` (`status`),
  ADD KEY `uploaded_at` (`uploaded_at`);

--
-- Indexes for table `visa_document_types`
--
ALTER TABLE `visa_document_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_type_per_tenant` (`tenant_id`,`name`),
  ADD KEY `tenant_id` (`tenant_id`),
  ADD KEY `status` (`status`);

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
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `additional_payments`
--
ALTER TABLE `additional_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `advanced_rate_limits`
--
ALTER TABLE `advanced_rate_limits`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `advanced_rate_limit_requests`
--
ALTER TABLE `advanced_rate_limit_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `api_versions`
--
ALTER TABLE `api_versions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `assets`
--
ALTER TABLE `assets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `attendance`
--
ALTER TABLE `attendance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `audit_logs`
--
ALTER TABLE `audit_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `blog_posts`
--
ALTER TABLE `blog_posts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branch_addons`
--
ALTER TABLE `branch_addons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branch_addon_payments`
--
ALTER TABLE `branch_addon_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branch_addon_requests`
--
ALTER TABLE `branch_addon_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branch_audit_log`
--
ALTER TABLE `branch_audit_log`
  MODIFY `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branch_chat_settings`
--
ALTER TABLE `branch_chat_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branch_peering`
--
ALTER TABLE `branch_peering`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `branch_performance_alerts`
--
ALTER TABLE `branch_performance_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `budget_allocations`
--
ALTER TABLE `budget_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `cancellation_reapply_log`
--
ALTER TABLE `cancellation_reapply_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_audit_log`
--
ALTER TABLE `chat_audit_log`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_audit_log_archive`
--
ALTER TABLE `chat_audit_log_archive`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_groups`
--
ALTER TABLE `chat_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_group_members`
--
ALTER TABLE `chat_group_members`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_group_messages`
--
ALTER TABLE `chat_group_messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_group_message_reads`
--
ALTER TABLE `chat_group_message_reads`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `chat_messages`
--
ALTER TABLE `chat_messages`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `clients`
--
ALTER TABLE `clients`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `client_transactions`
--
ALTER TABLE `client_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `communication_addons`
--
ALTER TABLE `communication_addons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `communication_addon_requests`
--
ALTER TABLE `communication_addon_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `contact_messages`
--
ALTER TABLE `contact_messages`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `creditors`
--
ALTER TABLE `creditors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `creditor_transactions`
--
ALTER TABLE `creditor_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `customer_wallets`
--
ALTER TABLE `customer_wallets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `custom_plan_requests`
--
ALTER TABLE `custom_plan_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `date_change_tickets`
--
ALTER TABLE `date_change_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `date_change_umrah`
--
ALTER TABLE `date_change_umrah`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `deals`
--
ALTER TABLE `deals`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `debtors`
--
ALTER TABLE `debtors`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `debtor_transactions`
--
ALTER TABLE `debtor_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `email_tracking`
--
ALTER TABLE `email_tracking`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `employee_terminations`
--
ALTER TABLE `employee_terminations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `encryption_audit`
--
ALTER TABLE `encryption_audit`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `encryption_keys`
--
ALTER TABLE `encryption_keys`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `encryption_key_rotations`
--
ALTER TABLE `encryption_key_rotations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exchange_rates`
--
ALTER TABLE `exchange_rates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `exchange_transactions`
--
ALTER TABLE `exchange_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expenses`
--
ALTER TABLE `expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expense_categories`
--
ALTER TABLE `expense_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `expense_report_config`
--
ALTER TABLE `expense_report_config`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `families`
--
ALTER TABLE `families`
  MODIFY `family_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `finance_tracker`
--
ALTER TABLE `finance_tracker`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `floating_tasks`
--
ALTER TABLE `floating_tasks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `funding_transactions`
--
ALTER TABLE `funding_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `general_ledger`
--
ALTER TABLE `general_ledger`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `global_budget_allocations`
--
ALTER TABLE `global_budget_allocations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `group_tickets`
--
ALTER TABLE `group_tickets`
  MODIFY `ticket_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hawala_transfers`
--
ALTER TABLE `hawala_transfers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hotel_bookings`
--
ALTER TABLE `hotel_bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `hotel_refunds`
--
ALTER TABLE `hotel_refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `invoices`
--
ALTER TABLE `invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ip_blacklist`
--
ALTER TABLE `ip_blacklist`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jv_payments`
--
ALTER TABLE `jv_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `jv_transactions`
--
ALTER TABLE `jv_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `login_history`
--
ALTER TABLE `login_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `main_account`
--
ALTER TABLE `main_account`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `main_account_transactions`
--
ALTER TABLE `main_account_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `maktobs`
--
ALTER TABLE `maktobs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `maktob_logs`
--
ALTER TABLE `maktob_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `message_reactions`
--
ALTER TABLE `message_reactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_resets`
--
ALTER TABLE `password_resets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `password_reset_requests`
--
ALTER TABLE `password_reset_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payment_sessions`
--
ALTER TABLE `payment_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_details`
--
ALTER TABLE `payroll_details`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `payroll_records`
--
ALTER TABLE `payroll_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `performance_reviews`
--
ALTER TABLE `performance_reviews`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `plans`
--
ALTER TABLE `plans`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `platform_settings`
--
ALTER TABLE `platform_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rate_limits`
--
ALTER TABLE `rate_limits`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `rate_limit_violations`
--
ALTER TABLE `rate_limit_violations`
  MODIFY `id` bigint(20) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `refunded_tickets`
--
ALTER TABLE `refunded_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `salary_adjustments`
--
ALTER TABLE `salary_adjustments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `salary_advances`
--
ALTER TABLE `salary_advances`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `salary_bonuses`
--
ALTER TABLE `salary_bonuses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `salary_deductions`
--
ALTER TABLE `salary_deductions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `salary_management`
--
ALTER TABLE `salary_management`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `salary_payments`
--
ALTER TABLE `salary_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_agents`
--
ALTER TABLE `sales_agents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_agent_commissions`
--
ALTER TABLE `sales_agent_commissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_agent_performance`
--
ALTER TABLE `sales_agent_performance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_agent_salary_payments`
--
ALTER TABLE `sales_agent_salary_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sales_agent_tenants`
--
ALTER TABLE `sales_agent_tenants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `sarafi_transactions`
--
ALTER TABLE `sarafi_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `security_audit_log`
--
ALTER TABLE `security_audit_log`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ssl_certificates`
--
ALTER TABLE `ssl_certificates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `subscription_payments`
--
ALTER TABLE `subscription_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supplier_transactions`
--
ALTER TABLE `supplier_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `supplier_transaction_records`
--
ALTER TABLE `supplier_transaction_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `support_tickets`
--
ALTER TABLE `support_tickets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_expenses`
--
ALTER TABLE `system_expenses`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_expense_categories`
--
ALTER TABLE `system_expense_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_profit_loss_by_category`
--
ALTER TABLE `system_profit_loss_by_category`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_profit_loss_reports`
--
ALTER TABLE `system_profit_loss_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `system_revenue`
--
ALTER TABLE `system_revenue`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tax_reports`
--
ALTER TABLE `tax_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tax_report_specifications`
--
ALTER TABLE `tax_report_specifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tenants`
--
ALTER TABLE `tenants`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tenant_peering`
--
ALTER TABLE `tenant_peering`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tenant_subscriptions`
--
ALTER TABLE `tenant_subscriptions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tenant_templates`
--
ALTER TABLE `tenant_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `testimonials`
--
ALTER TABLE `testimonials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_bookings`
--
ALTER TABLE `ticket_bookings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_categories`
--
ALTER TABLE `ticket_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_notifications`
--
ALTER TABLE `ticket_notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_replies`
--
ALTER TABLE `ticket_replies`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_reservations`
--
ALTER TABLE `ticket_reservations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_sla_rules`
--
ALTER TABLE `ticket_sla_rules`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `ticket_weights`
--
ALTER TABLE `ticket_weights`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `totp_recovery_codes`
--
ALTER TABLE `totp_recovery_codes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `totp_secrets`
--
ALTER TABLE `totp_secrets`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `tutorials`
--
ALTER TABLE `tutorials`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `umrah_bookings`
--
ALTER TABLE `umrah_bookings`
  MODIFY `booking_id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `umrah_booking_services`
--
ALTER TABLE `umrah_booking_services`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `umrah_refunds`
--
ALTER TABLE `umrah_refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `umrah_transactions`
--
ALTER TABLE `umrah_transactions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_addons`
--
ALTER TABLE `user_addons`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_addon_payments`
--
ALTER TABLE `user_addon_payments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_addon_requests`
--
ALTER TABLE `user_addon_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_agreements`
--
ALTER TABLE `user_agreements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_blocks`
--
ALTER TABLE `user_blocks`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_documents`
--
ALTER TABLE `user_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_mutes`
--
ALTER TABLE `user_mutes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_online_sessions`
--
ALTER TABLE `user_online_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_tutorial_learned`
--
ALTER TABLE `user_tutorial_learned`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `user_typing_status`
--
ALTER TABLE `user_typing_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `visa_applications`
--
ALTER TABLE `visa_applications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `visa_documents`
--
ALTER TABLE `visa_documents`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `visa_document_types`
--
ALTER TABLE `visa_document_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `visa_refunds`
--
ALTER TABLE `visa_refunds`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `whatsapp_settings`
--
ALTER TABLE `whatsapp_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `whatsapp_templates`
--
ALTER TABLE `whatsapp_templates`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

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
-- Constraints for table `branch_addons`
--
ALTER TABLE `branch_addons`
  ADD CONSTRAINT `fk_branch_addons_plan` FOREIGN KEY (`plan_id`) REFERENCES `plans` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_branch_addons_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `branch_addon_payments`
--
ALTER TABLE `branch_addon_payments`
  ADD CONSTRAINT `fk_branch_addon_payments_addon` FOREIGN KEY (`addon_id`) REFERENCES `branch_addons` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_branch_addon_payments_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `branch_addon_requests`
--
ALTER TABLE `branch_addon_requests`
  ADD CONSTRAINT `fk_branch_addon_requests_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_branch_addon_requests_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `branch_audit_log`
--
ALTER TABLE `branch_audit_log`
  ADD CONSTRAINT `fk_branch_audit_log_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_branch_audit_log_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `branch_chat_settings`
--
ALTER TABLE `branch_chat_settings`
  ADD CONSTRAINT `fk_bcs_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bcs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `branch_peering`
--
ALTER TABLE `branch_peering`
  ADD CONSTRAINT `fk_bp_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bp_peer_branch` FOREIGN KEY (`peer_branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_bp_peer_tenant` FOREIGN KEY (`peer_tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_bp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `branch_performance_alerts`
--
ALTER TABLE `branch_performance_alerts`
  ADD CONSTRAINT `fk_performance_alerts_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_performance_alerts_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `chat_audit_log`
--
ALTER TABLE `chat_audit_log`
  ADD CONSTRAINT `chat_audit_log_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `chat_audit_log_ibfk_2` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_audit_log_archive`
--
ALTER TABLE `chat_audit_log_archive`
  ADD CONSTRAINT `chat_audit_log_archive_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_groups`
--
ALTER TABLE `chat_groups`
  ADD CONSTRAINT `fk_chat_groups_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_group_members`
--
ALTER TABLE `chat_group_members`
  ADD CONSTRAINT `fk_chat_group_members_group` FOREIGN KEY (`group_id`) REFERENCES `chat_groups` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_group_messages`
--
ALTER TABLE `chat_group_messages`
  ADD CONSTRAINT `fk_chat_group_messages_group` FOREIGN KEY (`group_id`) REFERENCES `chat_groups` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_group_message_reads`
--
ALTER TABLE `chat_group_message_reads`
  ADD CONSTRAINT `fk_chat_group_message_reads_message` FOREIGN KEY (`message_id`) REFERENCES `chat_group_messages` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `chat_messages`
--
ALTER TABLE `chat_messages`
  ADD CONSTRAINT `fk_cm_encryption_key` FOREIGN KEY (`encryption_key_id`) REFERENCES `encryption_keys` (`id`) ON DELETE SET NULL,
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
-- Constraints for table `encryption_audit`
--
ALTER TABLE `encryption_audit`
  ADD CONSTRAINT `fk_ea_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_ea_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `encryption_keys`
--
ALTER TABLE `encryption_keys`
  ADD CONSTRAINT `fk_ek_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `encryption_key_rotations`
--
ALTER TABLE `encryption_key_rotations`
  ADD CONSTRAINT `fk_ekr_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `expense_report_config`
--
ALTER TABLE `expense_report_config`
  ADD CONSTRAINT `fk_expense_config_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `families`
--
ALTER TABLE `families`
  ADD CONSTRAINT `fk_families_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `finance_tracker`
--
ALTER TABLE `finance_tracker`
  ADD CONSTRAINT `fk_finance_tracker_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_finance_tracker_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_finance_tracker_user` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `floating_tasks`
--
ALTER TABLE `floating_tasks`
  ADD CONSTRAINT `fk_floating_tasks_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `ip_blacklist`
--
ALTER TABLE `ip_blacklist`
  ADD CONSTRAINT `ip_blacklist_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ip_blacklist_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

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
-- Indexes for table `cash_settlements`
--
ALTER TABLE `cash_settlements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_cs_branch_user` (`branch_id`, `user_id`, `currency`, `status`),
  ADD KEY `idx_cs_tenant` (`tenant_id`);

--
-- Constraints for table `maktobs`
--
ALTER TABLE `maktobs`
  ADD CONSTRAINT `fk_maktobs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `maktobs_ibfk_1` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `maktob_logs`
--
ALTER TABLE `maktob_logs`
  ADD CONSTRAINT `fk_maktob_logs_maktob` FOREIGN KEY (`maktob_id`) REFERENCES `maktobs` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_maktob_logs_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_maktob_logs_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `password_resets`
--
ALTER TABLE `password_resets`
  ADD CONSTRAINT `fk_password_resets_user_id` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `password_resets_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `rate_limits`
--
ALTER TABLE `rate_limits`
  ADD CONSTRAINT `rate_limits_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `rate_limit_violations`
--
ALTER TABLE `rate_limit_violations`
  ADD CONSTRAINT `rate_limit_violations_ibfk_1` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `rate_limit_violations_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `sales_agents`
--
ALTER TABLE `sales_agents`
  ADD CONSTRAINT `fk_sales_agents_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_sales_agents_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales_agent_commissions`
--
ALTER TABLE `sales_agent_commissions`
  ADD CONSTRAINT `fk_commissions_agent` FOREIGN KEY (`sales_agent_id`) REFERENCES `sales_agents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_commissions_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `sales_agent_performance`
--
ALTER TABLE `sales_agent_performance`
  ADD CONSTRAINT `fk_sales_agent_performance` FOREIGN KEY (`sales_agent_id`) REFERENCES `sales_agents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales_agent_salary_payments`
--
ALTER TABLE `sales_agent_salary_payments`
  ADD CONSTRAINT `fk_sales_agent_salary_payments_agent` FOREIGN KEY (`sales_agent_id`) REFERENCES `sales_agents` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `sales_agent_tenants`
--
ALTER TABLE `sales_agent_tenants`
  ADD CONSTRAINT `fk_agent_tenants_agent` FOREIGN KEY (`sales_agent_id`) REFERENCES `sales_agents` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_agent_tenants_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `supplier_transaction_records`
--
ALTER TABLE `supplier_transaction_records`
  ADD CONSTRAINT `fk_supplier_trans_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_expenses`
--
ALTER TABLE `system_expenses`
  ADD CONSTRAINT `fk_system_expenses_category` FOREIGN KEY (`category_id`) REFERENCES `system_expense_categories` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_system_expenses_created_by` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `system_profit_loss_by_category`
--
ALTER TABLE `system_profit_loss_by_category`
  ADD CONSTRAINT `fk_system_pl_category_report` FOREIGN KEY (`report_id`) REFERENCES `system_profit_loss_reports` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `system_profit_loss_reports`
--
ALTER TABLE `system_profit_loss_reports`
  ADD CONSTRAINT `fk_system_pl_generated_by` FOREIGN KEY (`generated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Constraints for table `system_revenue`
--
ALTER TABLE `system_revenue`
  ADD CONSTRAINT `fk_system_revenue_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tax_reports`
--
ALTER TABLE `tax_reports`
  ADD CONSTRAINT `fk_tax_report_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tax_report_specifications`
--
ALTER TABLE `tax_report_specifications`
  ADD CONSTRAINT `fk_tax_spec_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `tenants`
--
ALTER TABLE `tenants`
  ADD CONSTRAINT `fk_tenants_sales_agent` FOREIGN KEY (`sales_agent_id`) REFERENCES `sales_agents` (`id`) ON DELETE SET NULL;

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
-- Constraints for table `tenant_templates`
--
ALTER TABLE `tenant_templates`
  ADD CONSTRAINT `fk_tenant_templates_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `ticket_notifications`
--
ALTER TABLE `ticket_notifications`
  ADD CONSTRAINT `ticket_notifications_ibfk_1` FOREIGN KEY (`ticket_id`) REFERENCES `support_tickets` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `ticket_notifications_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

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
-- Constraints for table `visa_documents`
--
ALTER TABLE `visa_documents`
  ADD CONSTRAINT `fk_visa_documents_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_visa_documents_visa` FOREIGN KEY (`visa_id`) REFERENCES `visa_applications` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `visa_document_types`
--
ALTER TABLE `visa_document_types`
  ADD CONSTRAINT `fk_visa_doc_types_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE;

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
