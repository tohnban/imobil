-- Migration: request chat read cursor by message id
-- Date: 2026-06-12
-- Purpose: align staging/production DB with unread chat counter queries

USE imobil_db;

ALTER TABLE request_chat_reads
    ADD COLUMN IF NOT EXISTS last_read_message_id INT UNSIGNED NULL DEFAULT NULL
    AFTER last_read_at;
