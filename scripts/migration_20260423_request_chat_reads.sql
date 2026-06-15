-- Migration: request chat read tracking per user
-- Date: 2026-04-23
-- Purpose: unread counters for request negotiation chat

USE imobil_db;

CREATE TABLE IF NOT EXISTS request_chat_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    thread_id INT NOT NULL,
    user_id INT NOT NULL,
    last_read_at TIMESTAMP NULL DEFAULT NULL,
    last_read_message_id INT UNSIGNED NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_request_chat_read_user (thread_id, user_id),
    KEY idx_request_chat_reads_user (user_id, updated_at),
    CONSTRAINT fk_request_chat_reads_thread FOREIGN KEY (thread_id) REFERENCES request_chat_threads(id) ON DELETE CASCADE,
    CONSTRAINT fk_request_chat_reads_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
