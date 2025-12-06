-- Migration script to add transit flights and departure times support
-- Run this script on your database before using the updated ticket system

-- Add time fields to ticket_bookings table
ALTER TABLE ticket_bookings
ADD COLUMN departure_time TIME NULL AFTER departure_date,
ADD COLUMN return_departure_time TIME NULL AFTER return_date;

-- Create ticket_segments table for transit flights
CREATE TABLE ticket_segments (
  id INT(11) NOT NULL AUTO_INCREMENT,
  ticket_id INT(11) NOT NULL,
  segment_order INT(11) NOT NULL,
  origin VARCHAR(255) NOT NULL,
  destination VARCHAR(255) NOT NULL,
  airline VARCHAR(255) NOT NULL,
  flight_number VARCHAR(255) DEFAULT NULL,
  departure_date DATE NOT NULL,
  departure_time TIME DEFAULT NULL,
  arrival_date DATE DEFAULT NULL,
  arrival_time TIME DEFAULT NULL,
  tenant_id INT(11) NOT NULL,
  branch_id INT(11) NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_ticket_id (ticket_id),
  KEY idx_tenant_branch (tenant_id, branch_id),
  CONSTRAINT fk_ticket_segments_ticket FOREIGN KEY (ticket_id) REFERENCES ticket_bookings (id) ON DELETE CASCADE,
  CONSTRAINT fk_ticket_segments_tenant FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add indexes for better performance
ALTER TABLE ticket_segments
ADD INDEX idx_segment_order (ticket_id, segment_order),
ADD INDEX idx_departure_date (departure_date);