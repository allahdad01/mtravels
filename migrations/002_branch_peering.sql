-- Migration: Create branch_peering table for branch-level peering
-- Date: 2025-12-10
-- Purpose: Enable branches to have independent peering relationships
-- Status: Phase 2 Implementation

CREATE TABLE IF NOT EXISTS `branch_peering` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tenant_id` int(11) NOT NULL,
  `branch_id` int(11) NOT NULL,
  `peer_tenant_id` int(11) NOT NULL,
  `peer_branch_id` int(11) DEFAULT NULL,
  `status` enum('approved','pending','blocked') NOT NULL DEFAULT 'pending',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `branch_peer_unique` (`branch_id`, `peer_branch_id`),
  KEY `idx_tenant` (`tenant_id`),
  KEY `idx_branch` (`branch_id`),
  KEY `idx_peer_tenant` (`peer_tenant_id`),
  CONSTRAINT `fk_bp_tenant` FOREIGN KEY (`tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bp_branch` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bp_peer_tenant` FOREIGN KEY (`peer_tenant_id`) REFERENCES `tenants` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_bp_peer_branch` FOREIGN KEY (`peer_branch_id`) REFERENCES `branches` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Enable branch peering in the system
INSERT IGNORE INTO branch_peering (tenant_id, branch_id, peer_tenant_id, peer_branch_id, status)
SELECT DISTINCT 
    t1.id,
    b1.id,
    tp.peer_tenant_id,
    b2.id,
    'approved'
FROM tenant_peering tp
JOIN tenants t1 ON t1.id = tp.tenant_id
JOIN tenants t2 ON t2.id = tp.peer_tenant_id
JOIN branches b1 ON b1.tenant_id = t1.id
JOIN branches b2 ON b2.tenant_id = t2.id
WHERE tp.status = 'approved';
