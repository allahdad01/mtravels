-- Phase 5: Rate Limiting Tables
-- Database migration for rate limiting system

-- Table: rate_limits
-- Tracks current usage of rate limited actions
CREATE TABLE IF NOT EXISTS rate_limits (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT NOT NULL,
  key_type VARCHAR(50) NOT NULL DEFAULT 'user',
  -- Types: 'user', 'email', 'ip', 'user-recipient' (for per-recipient limits)
  
  key_value VARCHAR(255) NOT NULL,
  -- Value: user_id, email address, IP address, or composite key
  
  limit_name VARCHAR(100) NOT NULL,
  -- e.g., 'messages_per_hour', 'login_attempts_per_15min', 'api_requests_per_minute'
  
  limit_value INT NOT NULL,
  -- Maximum allowed in the window
  
  window_seconds INT NOT NULL,
  -- Time window in seconds
  
  current_count INT DEFAULT 0,
  -- Current count within the window
  
  reset_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  -- When this counter resets
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  UNIQUE KEY unique_limit (tenant_id, key_type, key_value, limit_name),
  INDEX idx_reset_at (reset_at),
  INDEX idx_key_value (key_value),
  INDEX idx_tenant_action (tenant_id, limit_name),
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: rate_limit_violations
-- Logs when rate limits are exceeded
CREATE TABLE IF NOT EXISTS rate_limit_violations (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  tenant_id INT NOT NULL,
  user_id INT,
  -- User involved in the violation
  
  limit_name VARCHAR(100) NOT NULL,
  -- Which limit was violated
  
  violation_count INT DEFAULT 1,
  -- How many times this limit has been violated
  
  current_value INT,
  -- The value at time of violation
  
  limit_value INT,
  -- The limit that was exceeded
  
  ip_address VARCHAR(45),
  -- IP address of the request
  
  details JSON,
  -- Additional context in JSON format
  
  action_taken VARCHAR(50),
  -- What action was taken: 'warned', 'throttled', 'blocked', 'recorded'
  
  blocked_until TIMESTAMP NULL,
  -- When the block (if any) expires
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  INDEX idx_tenant_user (tenant_id, user_id),
  INDEX idx_user_created (user_id, created_at),
  INDEX idx_limit_name (limit_name),
  INDEX idx_created_at (created_at),
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ip_blacklist
-- Manages blocked IP addresses
CREATE TABLE IF NOT EXISTS ip_blacklist (
  id BIGINT PRIMARY KEY AUTO_INCREMENT,
  ip_address VARCHAR(45) NOT NULL,
  tenant_id INT,
  -- NULL = global block, value = tenant-specific
  
  reason VARCHAR(255),
  -- Why the IP was blocked
  
  blocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  -- When the block was created
  
  blocked_until TIMESTAMP NULL,
  -- When the block expires (NULL = permanent)
  
  permanent BOOLEAN DEFAULT FALSE,
  -- Whether this is a permanent block
  
  created_by INT,
  -- Admin user ID who created the block
  
  UNIQUE KEY unique_ip_tenant (ip_address, tenant_id),
  INDEX idx_blocked_until (blocked_until),
  INDEX idx_tenant_id (tenant_id),
  FOREIGN KEY (tenant_id) REFERENCES tenants(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add comment for migration tracking
ALTER TABLE rate_limits COMMENT = 'Phase 5: Rate limiting - tracks usage counts';
ALTER TABLE rate_limit_violations COMMENT = 'Phase 5: Rate limiting - logs violations';
ALTER TABLE ip_blacklist COMMENT = 'Phase 5: Rate limiting - IP blocking';
