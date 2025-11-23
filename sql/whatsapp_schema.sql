-- WhatsApp Automation API Database Schema
-- This file creates the necessary tables for tenant-based WhatsApp notifications

-- WhatsApp Settings Table - Stores tenant-specific WhatsApp configuration
CREATE TABLE IF NOT EXISTS `whatsapp_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tenant_id` int(11) NOT NULL,
    `provider` varchar(50) NOT NULL DEFAULT 'twilio' COMMENT 'whatsapp provider (twilio, messagebird, etc)',
    `api_token` text NOT NULL COMMENT 'API authentication token',
    `phone_number_id` varchar(100) NOT NULL COMMENT 'WhatsApp Business phone number ID',
    `webhook_verify_token` varchar(100) NOT NULL COMMENT 'Webhook verification token for incoming messages',
    `webhook_url` text COMMENT 'Webhook URL for receiving message status updates',
    `status` enum('active','inactive','testing') NOT NULL DEFAULT 'inactive',
    `auto_notifications` tinyint(1) NOT NULL DEFAULT 1 COMMENT 'Automatically send notifications for new bookings',
    `real_time_notifications` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Send notifications immediately or queue them',
    `max_messages_per_hour` int(11) NOT NULL DEFAULT 1000 COMMENT 'Rate limiting - max messages per hour',
    `retry_attempts` int(11) NOT NULL DEFAULT 3 COMMENT 'Number of retry attempts for failed messages',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `tenant_id` (`tenant_id`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WhatsApp Message Templates Table - Stores customizable notification templates
CREATE TABLE IF NOT EXISTS `whatsapp_templates` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tenant_id` int(11) NOT NULL,
    `template_name` varchar(100) NOT NULL COMMENT 'Template identifier name',
    `template_type` varchar(50) NOT NULL COMMENT 'Type: visa, umrah, hotel, refund, etc',
    `language` varchar(10) NOT NULL DEFAULT 'en' COMMENT 'Template language (en, fa, ps)',
    `message_template` longtext NOT NULL COMMENT 'Message template with {{variables}}',
    `status` enum('active','inactive') NOT NULL DEFAULT 'active',
    `created_by` int(11) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `tenant_template_type_lang` (`tenant_id`, `template_type`, `language`),
    KEY `template_type` (`template_type`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WhatsApp Messages Queue Table - Stores messages to be sent
CREATE TABLE IF NOT EXISTS `whatsapp_messages` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tenant_id` int(11) NOT NULL,
    `phone_number` varchar(20) NOT NULL COMMENT 'Recipient phone number with country code',
    `message` longtext NOT NULL COMMENT 'Complete message content',
    `message_type` varchar(50) NOT NULL COMMENT 'Type: visa, umrah, hotel, refund, etc',
    `reference_id` int(11) NOT NULL COMMENT 'Reference to booking ID (visa_id, booking_id, etc)',
    `template_variables` json COMMENT 'Template variables used for message generation',
    `status` enum('pending','sent','delivered','failed','expired') NOT NULL DEFAULT 'pending',
    `provider_message_id` varchar(100) COMMENT 'Message ID from WhatsApp provider',
    `error_message` text COMMENT 'Error message if sending failed',
    `retry_count` int(11) NOT NULL DEFAULT 0 COMMENT 'Number of retry attempts',
    `priority` int(11) NOT NULL DEFAULT 5 COMMENT 'Message priority (1=highest, 10=lowest)',
    `scheduled_at` timestamp NULL COMMENT 'When to send the message (for scheduled messages)',
    `sent_at` timestamp NULL COMMENT 'When the message was actually sent',
    `delivered_at` timestamp NULL COMMENT 'When message was delivered to recipient',
    `failed_at` timestamp NULL COMMENT 'When message failed to send',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `tenant_status` (`tenant_id`, `status`),
    KEY `message_type_ref` (`message_type`, `reference_id`),
    KEY `scheduled_at` (`scheduled_at`),
    KEY `priority_status` (`priority`, `status`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WhatsApp Message Delivery Status Table - Tracks message delivery
CREATE TABLE IF NOT EXISTS `whatsapp_delivery_status` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `message_id` int(11) NOT NULL,
    `provider_message_id` varchar(100) NOT NULL,
    `status` enum('sent','delivered','read','failed') NOT NULL,
    `status_message` text COMMENT 'Human readable status message',
    `delivery_timestamp` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `raw_response` json COMMENT 'Raw API response from provider',
    PRIMARY KEY (`id`),
    UNIQUE KEY `provider_message_id` (`provider_message_id`),
    KEY `message_id` (`message_id`),
    FOREIGN KEY (`message_id`) REFERENCES `whatsapp_messages` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WhatsApp Webhook Log Table - Logs incoming webhook requests
CREATE TABLE IF NOT EXISTS `whatsapp_webhook_log` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `tenant_id` int(11) NOT NULL,
    `webhook_type` varchar(50) NOT NULL COMMENT 'Type: message, delivery, read, etc',
    `from_number` varchar(20) COMMENT 'Sender phone number',
    `to_number` varchar(20) COMMENT 'Recipient phone number',
    `message_content` text COMMENT 'Message content if applicable',
    `raw_payload` longtext COMMENT 'Complete webhook payload',
    `processed` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Whether webhook was processed',
    `error_message` text COMMENT 'Error during processing if any',
    `ip_address` varchar(45) COMMENT 'IP address of webhook request',
    `user_agent` text COMMENT 'User agent of webhook request',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `tenant_processed` (`tenant_id`, `processed`),
    KEY `webhook_type` (`webhook_type`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- WhatsApp Analytics Table - Stores message analytics and statistics
CREATE TABLE IF NOT EXISTS `whatsapp_analytics` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
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
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `tenant_date_type` (`tenant_id`, `date`, `message_type`),
    KEY `date` (`date`),
    FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default templates for each language and type
INSERT INTO `whatsapp_templates` (`tenant_id`, `template_name`, `template_type`, `language`, `message_template`, `status`, `created_by`) 
SELECT 
    t.id as tenant_id,
    CONCAT('default_', template_type, '_', language) as template_name,
    template_type,
    language,
    template_content,
    'active' as status,
    1 as created_by
FROM tenants t
CROSS JOIN (
    SELECT 'visa' as template_type, 'en' as language, 
    '🛂 *New Visa Application*\n\nHello {{client_name}},\n\nYour visa application has been successfully processed:\n\n👤 Applicant: {{applicant_name}}\n📋 Passport: {{passport_number}}\n🌍 Country: {{country}}\n📄 Type: {{visa_type}}\n💰 Amount: {{amount}}\n\n📅 Date: {{booking_date}}\n\nThank you for choosing {{agency_name}}!\n📞 Contact: {{contact_info}}' as template_content
    UNION ALL
    SELECT 'umrah', 'en',
    '🕌 *Umrah Booking Confirmation*\n\nAssalamu Alaikum {{client_name}},\n\nYour Umrah booking has been confirmed:\n\n👤 Member: {{member_name}}\n📦 Package: {{package_type}}\n✈️ Flight Date: {{flight_date}}\n💰 Amount: {{amount}}\n\n📅 Booking Date: {{booking_date}}\n\nMay Allah accept your Umrah!\n📞 Contact: {{contact_info}}'
    UNION ALL
    SELECT 'hotel', 'en',
    '🏨 *Hotel Booking Confirmation*\n\nHello {{client_name}},\n\nYour hotel booking is confirmed:\n\n👤 Guest: {{guest_name}}\n🏨 Hotel: {{hotel_name}}\n📅 Check-in: {{check_in}}\n📅 Check-out: {{check_out}}\n💰 Amount: {{amount}}\n\n📅 Booking Date: {{booking_date}}\n\nThank you for choosing {{agency_name}}!\n📞 Contact: {{contact_info}}'
    UNION ALL
    SELECT 'visa', 'fa',
    '🛂 *درخواست ویزا جدید*\n\nسلام {{client_name}}،\n\nدرخواست ویزای شما با موفقیت پردازش شده است:\n\n👤 متقاضی: {{applicant_name}}\n📋 شماره گذرنامه: {{passport_number}}\n🌍 کشور: {{country}}\n📄 نوع: {{visa_type}}\n💰 مبلغ: {{amount}}\n\n📅 تاریخ: {{booking_date}}\n\nبا تشکر از انتخاب {{agency_name}}!\n📞 تماس: {{contact_info}}'
    UNION ALL
    SELECT 'umrah', 'fa',
    '🕌 *تایید رزرو عمره*\n\nالسلام علیکم {{client_name}}،\n\nرزرو عمره شما تایید شده است:\n\n👤 عضو: {{member_name}}\n📦 بسته: {{package_type}}\n✈️ تاریخ پرواز: {{flight_date}}\n💰 مبلغ: {{amount}}\n\n📅 تاریخ رزرو: {{booking_date}}\n\nخدا عمره شما را قبول کند!\n📞 تماس: {{contact_info}}'
    UNION ALL
    SELECT 'hotel', 'fa',
    '🏨 *تایید رزرو هتل*\n\nسلام {{client_name}}،\n\nرزرو هتل شما تایید شده است:\n\n👤 مهمان: {{guest_name}}\n🏨 هتل: {{hotel_name}}\n📅 ورود: {{check_in}}\n📅 خروج: {{check_out}}\n💰 مبلغ: {{amount}}\n\n📅 تاریخ رزرو: {{booking_date}}\n\nبا تشکر از انتخاب {{agency_name}}!\n📞 تماس: {{contact_info}}'
    UNION ALL
    SELECT 'visa', 'ps',
    '🛂 *د ویزا غوښتنه*\n\nسلام {{client_name}}،\n\nد ویزا غوښتنه ستاسو په بریالیتوب سره پروسه شوه:\n\n👤 غوښتنونکی: {{applicant_name}}\n📋 د پاسپورټ شمېره: {{passport_number}}\n🌍 هیواد: {{country}}\n📄 ډول: {{visa_type}}\n💰 مقدار: {{amount}}\n\n📅 نېټه: {{booking_date}}\n\nد {{agency_name}} د انتخاب څخه مننه!\n📞 اړیکه: {{contact_info}}'
    UNION ALL
    SELECT 'umrah', 'ps',
    '🕌 *د عمره ریزرویشن تایید*\n\nالسلام علیکم {{client_name}}،\n\nستاسو عمره ریزرویشن تایید شو:\n\n👤 غړی: {{member_name}}\n📦 بسته: {{package_type}}\n✈️ د الوت نېټه: {{flight_date}}\n💰 مقدار: {{amount}}\n\n📅 د ریزرویشن نېټه: {{booking_date}}\n\nخدا دې ستاسو عمره قبول کړي!\n📞 اړیکه: {{contact_info}}'
    UNION ALL
    SELECT 'hotel', 'ps',
    '🏨 *د هوټل ریزرویشن تایید*\n\nسلام {{client_name}}،\n\nستاسو هوټل ریزرویشن تایید شو:\n\n👤 مېلمه: {{guest_name}}\n🏨 هوټل: {{hotel_name}}\n📅 دننول: {{check_in}}\n📅 دباندېلا: {{check_out}}\n💰 مقدار: {{amount}}\n\n📅 د ریزرویشن نېټه: {{booking_date}}\n\nد {{agency_name}} د انتخاب څخه مننه!\n📞 اړیکه: {{contact_info}}'
) templates
WHERE NOT EXISTS (
    SELECT 1 FROM whatsapp_templates wt 
    WHERE wt.tenant_id = t.id 
    AND wt.template_type = templates.template_type
    AND wt.language = templates.language
);

-- Create indexes for better performance
CREATE INDEX idx_whatsapp_messages_status_priority ON whatsapp_messages(status, priority);
CREATE INDEX idx_whatsapp_messages_tenant_status ON whatsapp_messages(tenant_id, status);
CREATE INDEX idx_whatsapp_delivery_status_timestamp ON whatsapp_delivery_status(delivery_timestamp);
CREATE INDEX idx_whatsapp_analytics_tenant_date ON whatsapp_analytics(tenant_id, date);