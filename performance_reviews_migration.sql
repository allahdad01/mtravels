-- Migration for Performance Reviews functionality
-- Run this SQL to add performance reviews table

CREATE TABLE IF NOT EXISTS `performance_reviews` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `tenant_id` int(11) NOT NULL,
  `reviewer_id` int(11) NOT NULL,
  `review_date` date NOT NULL,
  `period_start` date NOT NULL,
  `period_end` date NOT NULL,
  `overall_rating` decimal(3,2) DEFAULT NULL COMMENT 'Rating from 1.00 to 5.00',
  `comments` text,
  `achievements` text,
  `areas_for_improvement` text,
  `goals` text,
  `recommendations` text,
  `status` enum('draft','submitted','approved','rejected') DEFAULT 'draft',
  `created_at` timestamp DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_tenant` (`user_id`, `tenant_id`),
  KEY `idx_reviewer` (`reviewer_id`),
  KEY `idx_period` (`period_start`, `period_end`),
  CONSTRAINT `fk_performance_reviews_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_performance_reviews_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_performance_reviews_reviewer` FOREIGN KEY (`reviewer_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add some sample data for testing
INSERT INTO `performance_reviews` (`user_id`, `tenant_id`, `reviewer_id`, `review_date`, `period_start`, `period_end`, `overall_rating`, `comments`, `achievements`, `areas_for_improvement`, `goals`, `recommendations`, `status`) VALUES
(1, 1, 1, '2024-01-15', '2023-10-01', '2023-12-31', 4.50, 'Excellent performance this quarter. Consistently met targets and showed great initiative.', 'Successfully closed 15 deals, improved customer satisfaction by 20%', 'Could improve on time management', 'Increase sales targets by 25% next quarter', 'Consider for promotion to senior sales role', 'approved'),
(2, 1, 1, '2024-01-20', '2023-10-01', '2023-12-31', 3.75, 'Good performance with room for improvement.', 'Completed assigned tasks on time', 'Needs to improve communication skills', 'Attend communication training', 'Monitor progress closely', 'approved');