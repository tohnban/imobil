-- =============================================================================
-- Imobil — schema consolidado para fresh install
-- Estado alinhado com migrações em scripts/ e raiz até 2026-06-16 (inclusive)
-- Bases existentes: aplicar scripts/migration_*.sql incrementalmente
--
-- Tabelas (ordem de dependência):
--   users, password_resets, email_verifications, login_attempts, api_tokens
--   property_types, countries, regions, settings
--   payment_methods, system_payment_channels, user_payment_accounts, payment_transactions
--   subscription_plans, user_subscriptions, subscription_events
--   properties, favorites, property_affiliates, property_boost_requests
--   property_behavior_events, property_impressions
--   requests, request_chat_threads, request_chat_messages, request_chat_reads, commissions
--   notifications, documents, logs, saved_searches, metric_events, background_jobs
-- =============================================================================

CREATE DATABASE IF NOT EXISTS imobil_db;
USE imobil_db;

-- -----------------------------------------------------------------------------
-- Utilizadores e autenticação
-- -----------------------------------------------------------------------------

CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) UNIQUE,
    email_verified_at DATETIME NULL DEFAULT NULL,
    password VARCHAR(255) NOT NULL,
    name VARCHAR(255) NOT NULL,
    username VARCHAR(32) NOT NULL,
    username_changed_at TIMESTAMP NULL DEFAULT NULL,
    UNIQUE KEY uq_users_username (username),
    user_type ENUM('pessoa_fisica', 'pessoa_juridica') NOT NULL DEFAULT 'pessoa_fisica',
    document_number VARCHAR(14) UNIQUE NOT NULL,
    phone VARCHAR(20) UNIQUE,
    is_affiliate BOOLEAN DEFAULT FALSE,
    affiliate_code VARCHAR(50) UNIQUE,
    is_admin BOOLEAN DEFAULT FALSE,
    role ENUM('super_admin', 'moderador', 'financeiro', 'suporte', 'utilizador') DEFAULT 'utilizador',
    status ENUM('pendente', 'ativo', 'rejeitado') DEFAULT 'pendente',
    suspended_until TIMESTAMP NULL,
    deletion_requested_at TIMESTAMP NULL DEFAULT NULL,
    deletion_scheduled_at TIMESTAMP NULL DEFAULT NULL,
    deletion_reminder_sent_at TIMESTAMP NULL DEFAULT NULL,
    account_plan ENUM('free', 'premium') NOT NULL DEFAULT 'free'
        COMMENT 'DEPRECATED: use user_subscriptions + subscription_plans',
    trust_badge_status ENUM('nenhum', 'pendente', 'aprovado', 'rejeitado') DEFAULT 'nenhum',
    trust_badge_requested_at TIMESTAMP NULL,
    trust_badge_approved_at TIMESTAMP NULL,
    trust_badge_duration_months INT NULL,
    trust_badge_fee_required DECIMAL(10,2) DEFAULT 0,
    trust_badge_fee_paid BOOLEAN DEFAULT FALSE,
    trust_badge_payment_proof VARCHAR(255) NULL,
    document_file VARCHAR(255),
    profile_photo VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_users_deletion_scheduled (deletion_scheduled_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    pending_email VARCHAR(255) NULL DEFAULT NULL,
    token VARCHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_email_verif_token (token),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE login_attempts (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    login_identifier VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_attempts_identifier_time (login_identifier, attempted_at),
    INDEX idx_login_attempts_ip_time (ip_address, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE api_tokens (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    token VARCHAR(128) NOT NULL UNIQUE,
    name VARCHAR(120) NOT NULL,
    scopes VARCHAR(255) NOT NULL DEFAULT 'read:properties',
    status ENUM('active', 'revoked') NOT NULL DEFAULT 'active',
    expires_at DATETIME NULL,
    last_used_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_api_tokens_status_expires (status, expires_at),
    INDEX idx_api_tokens_user_id (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Catálogos (tipos, regiões, definições)
-- -----------------------------------------------------------------------------

CREATE TABLE property_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) UNIQUE NOT NULL,
    label_pt VARCHAR(100) NOT NULL,
    category VARCHAR(50) NOT NULL,
    icon VARCHAR(50),
    color VARCHAR(7),
    is_legacy BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_code (code),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE countries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) UNIQUE NOT NULL,
    name VARCHAR(120) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_countries_name (name),
    INDEX idx_countries_active_sort (is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE regions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    country_id INT NOT NULL,
    code VARCHAR(50) UNIQUE NOT NULL,
    name VARCHAR(120) NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_regions_country (country_id),
    INDEX idx_regions_name (name),
    INDEX idx_regions_active_sort (is_active, sort_order),
    FOREIGN KEY (country_id) REFERENCES countries(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL,
    value VARCHAR(255) NOT NULL,
    label VARCHAR(150) NOT NULL COMMENT 'Human-readable label for the admin panel',
    description TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_settings_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Pagamentos (hub)
-- -----------------------------------------------------------------------------

CREATE TABLE payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    name VARCHAR(120) NOT NULL,
    direction ENUM('incoming', 'outgoing', 'both') NOT NULL DEFAULT 'both',
    audience ENUM('system', 'user', 'both') NOT NULL DEFAULT 'both',
    requires_reference BOOLEAN NOT NULL DEFAULT FALSE,
    fields_config JSON NULL COMMENT 'Boolean map of fields shown in user form',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    deleted_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_payment_method_code (code),
    INDEX idx_payment_methods_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE system_payment_channels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    method_id INT NOT NULL,
    channel_name VARCHAR(150) NOT NULL,
    account_name VARCHAR(150) NULL,
    account_number VARCHAR(120) NULL,
    iban VARCHAR(80) NULL,
    bank_name VARCHAR(120) NULL,
    wallet_provider VARCHAR(80) NULL,
    instructions TEXT NULL,
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_spc_method FOREIGN KEY (method_id) REFERENCES payment_methods(id),
    INDEX idx_spc_method_active (method_id, is_active),
    INDEX idx_spc_default (is_default)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_payment_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    method_id INT NOT NULL,
    account_label VARCHAR(120) NULL,
    account_name VARCHAR(150) NULL,
    account_number VARCHAR(120) NULL,
    iban VARCHAR(80) NULL,
    bank_name VARCHAR(120) NULL,
    wallet_provider VARCHAR(80) NULL,
    phone_number VARCHAR(30) NULL,
    metadata JSON NULL,
    is_default BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    verified_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_upa_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_upa_method FOREIGN KEY (method_id) REFERENCES payment_methods(id),
    INDEX idx_upa_user_active (user_id, is_active),
    INDEX idx_upa_user_default (user_id, is_default),
    INDEX idx_upa_method (method_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE payment_transactions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    transaction_type ENUM(
        'commission_owner_payment',
        'commission_payout',
        'system_commission',
        'boost_fee',
        'trust_badge_fee',
        'manual_adjustment',
        'subscription_fee'
    ) NOT NULL,
    direction ENUM('incoming', 'outgoing') NOT NULL,
    status ENUM('pendente', 'processando', 'confirmado', 'cancelado', 'falhado', 'rejeitado') NOT NULL DEFAULT 'pendente',
    amount DECIMAL(15,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'AOA',
    method_id INT NULL,
    system_channel_id INT NULL,
    user_account_id INT NULL,
    counterparty_user_id INT NULL,
    related_entity_type VARCHAR(40) NULL,
    related_entity_id INT NULL,
    reference_code VARCHAR(120) NULL,
    proof_file VARCHAR(255) NULL,
    notes TEXT NULL,
    created_by INT NULL,
    confirmed_by INT NULL,
    confirmed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_pt_method FOREIGN KEY (method_id) REFERENCES payment_methods(id),
    CONSTRAINT fk_pt_system_channel FOREIGN KEY (system_channel_id) REFERENCES system_payment_channels(id),
    CONSTRAINT fk_pt_user_account FOREIGN KEY (user_account_id) REFERENCES user_payment_accounts(id),
    CONSTRAINT fk_pt_counterparty_user FOREIGN KEY (counterparty_user_id) REFERENCES users(id),
    CONSTRAINT fk_pt_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    CONSTRAINT fk_pt_confirmed_by FOREIGN KEY (confirmed_by) REFERENCES users(id),
    INDEX idx_pt_status_created (status, created_at),
    INDEX idx_pt_type_created (transaction_type, created_at),
    INDEX idx_pt_entity (related_entity_type, related_entity_id),
    INDEX idx_pt_counterparty (counterparty_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Subscrições
-- -----------------------------------------------------------------------------

CREATE TABLE subscription_plans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL,
    name VARCHAR(120) NOT NULL,
    monthly_price_aoa DECIMAL(12,2) NOT NULL DEFAULT 0,
    max_active_properties INT NULL,
    ranking_weight INT NOT NULL DEFAULT 0,
    visibility_tier ENUM('basic', 'premium') NOT NULL DEFAULT 'basic',
    has_featured_in_results BOOLEAN NOT NULL DEFAULT FALSE,
    has_reports BOOLEAN NOT NULL DEFAULT FALSE,
    has_advanced_reports BOOLEAN NOT NULL DEFAULT FALSE,
    has_priority_support BOOLEAN NOT NULL DEFAULT FALSE,
    has_auto_renew BOOLEAN NOT NULL DEFAULT FALSE,
    has_institutional_page BOOLEAN NOT NULL DEFAULT FALSE,
    is_custom_pricing BOOLEAN NOT NULL DEFAULT FALSE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_subscription_plans_code (code),
    INDEX idx_subscription_plans_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE user_subscriptions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    plan_id INT NOT NULL,
    status ENUM('pending_activation', 'active', 'past_due', 'expired', 'cancelled') NOT NULL DEFAULT 'active',
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NULL,
    grace_until DATETIME NULL,
    auto_renew BOOLEAN NOT NULL DEFAULT FALSE,
    negotiated_price_aoa DECIMAL(12,2) NULL,
    billing_cycle_months INT NOT NULL DEFAULT 1,
    last_payment_transaction_id BIGINT NULL,
    notes VARCHAR(255) NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_subscriptions_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_user_subscriptions_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id),
    CONSTRAINT fk_user_subscriptions_payment_tx FOREIGN KEY (last_payment_transaction_id) REFERENCES payment_transactions(id),
    CONSTRAINT fk_user_subscriptions_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_user_subscriptions_user_status_dates (user_id, status, starts_at, ends_at),
    INDEX idx_user_subscriptions_status_end (status, ends_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscription_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    from_plan_id INT NULL,
    to_plan_id INT NULL,
    event_type ENUM('activated', 'renewed', 'upgraded', 'downgraded', 'cancelled', 'expired', 'payment_failed', 'manual_adjustment') NOT NULL,
    metadata JSON NULL,
    created_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_subscription_events_user FOREIGN KEY (user_id) REFERENCES users(id),
    CONSTRAINT fk_subscription_events_from_plan FOREIGN KEY (from_plan_id) REFERENCES subscription_plans(id),
    CONSTRAINT fk_subscription_events_to_plan FOREIGN KEY (to_plan_id) REFERENCES subscription_plans(id),
    CONSTRAINT fk_subscription_events_created_by FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_subscription_events_user_created (user_id, created_at),
    INDEX idx_subscription_events_type_created (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Imóveis e relacionados
-- -----------------------------------------------------------------------------

CREATE TABLE properties (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    type VARCHAR(50) NOT NULL,
    purpose ENUM('venda', 'aluguer_curto', 'aluguer_longo') NOT NULL,
    rent_payment_terms JSON NULL,
    rental_days INT NULL,
    rental_months INT NULL,
    price DECIMAL(15,2) NOT NULL,
    country_id INT NULL,
    region_id INT NULL,
    location VARCHAR(255) NOT NULL,
    latitude DOUBLE NULL,
    longitude DOUBLE NULL,
    bedrooms INT DEFAULT 0,
    bathrooms INT DEFAULT 0,
    area DECIMAL(10,2),
    images JSON,
    video_url VARCHAR(255),
    affiliate_id INT COMMENT 'ID do proprietário (nome legado da coluna)',
    affiliate_approval_mode ENUM('manual', 'auto', 'disabled') NOT NULL DEFAULT 'auto',
    owner_bonus_pct DECIMAL(5,2) DEFAULT 0,
    visibility ENUM('basic', 'premium') DEFAULT 'basic',
    featured BOOLEAN DEFAULT FALSE,
    has_garage TINYINT(1) NOT NULL DEFAULT 0,
    has_pool TINYINT(1) NOT NULL DEFAULT 0,
    has_elevator TINYINT(1) NOT NULL DEFAULT 0,
    has_security TINYINT(1) NOT NULL DEFAULT 0,
    status ENUM('pendente', 'em_analise', 'disponivel', 'vendido', 'alugado', 'rejeitado', 'eliminado') DEFAULT 'pendente',
    deletion_requested_at TIMESTAMP NULL DEFAULT NULL,
    deletion_scheduled_at TIMESTAMP NULL DEFAULT NULL,
    deletion_reminder_sent_at TIMESTAMP NULL DEFAULT NULL,
    deletion_purged_at TIMESTAMP NULL DEFAULT NULL,
    deletion_previous_status VARCHAR(32) NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_properties_type (type),
    INDEX idx_properties_country (country_id),
    INDEX idx_properties_region (region_id),
    INDEX idx_properties_status_visibility_created (status, visibility, created_at),
    INDEX idx_properties_type_purpose (type, purpose),
    INDEX idx_properties_location (location),
    INDEX idx_properties_country_status (country_id, status, created_at),
    INDEX idx_properties_region_status (region_id, status, created_at),
    INDEX idx_properties_affiliate (affiliate_id),
    INDEX idx_properties_status_featured (status, featured),
    INDEX idx_properties_country_region (country_id, region_id),
    INDEX idx_properties_price (price),
    INDEX idx_properties_bedrooms (bedrooms),
    INDEX idx_properties_bathrooms (bathrooms),
    INDEX idx_properties_area (area),
    INDEX idx_properties_status_affiliate_created_at (status, affiliate_id, created_at),
    INDEX idx_properties_affiliate_status_created (affiliate_id, status, created_at),
    INDEX idx_properties_deletion_scheduled (deletion_scheduled_at),
    FULLTEXT INDEX idx_properties_fulltext_title_description_location (title, description, location),
    FOREIGN KEY (affiliate_id) REFERENCES users(id),
    FOREIGN KEY (country_id) REFERENCES countries(id),
    FOREIGN KEY (region_id) REFERENCES regions(id),
    FOREIGN KEY (type) REFERENCES property_types(code) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE favorites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    property_id INT NOT NULL,
    deleted_at DATETIME NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (property_id) REFERENCES properties(id),
    UNIQUE KEY unique_favorite (user_id, property_id),
    INDEX idx_favorites_deleted_at (deleted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE property_affiliates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    property_id INT NOT NULL,
    status ENUM('pendente', 'ativo', 'rejeitado') DEFAULT 'pendente',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL,
    rejected_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (property_id) REFERENCES properties(id),
    UNIQUE KEY unique_property_affiliate (user_id, property_id),
    INDEX idx_property_affiliates_user (user_id),
    INDEX idx_property_affiliates_property (property_id),
    INDEX idx_property_affiliates_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE property_boost_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    property_id INT NOT NULL,
    user_id INT NOT NULL,
    boost_type ENUM('destaque', 'premium') NOT NULL DEFAULT 'destaque',
    duration_days INT NOT NULL DEFAULT 30,
    fee_required DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    payment_reference VARCHAR(200) DEFAULT NULL,
    payment_proof VARCHAR(255) NULL,
    status ENUM('pendente', 'aprovado', 'rejeitado', 'expirado') NOT NULL DEFAULT 'pendente',
    notes TEXT DEFAULT NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_at TIMESTAMP NULL DEFAULT NULL,
    rejected_at TIMESTAMP NULL DEFAULT NULL,
    expires_at TIMESTAMP NULL DEFAULT NULL,
    expired_at DATETIME NULL DEFAULT NULL,
    CONSTRAINT fk_pbr_property FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE,
    CONSTRAINT fk_pbr_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_pbr_status (status),
    INDEX idx_pbr_property (property_id),
    INDEX idx_pbr_status_expires_at (status, expires_at),
    INDEX idx_pbr_property_status_expires (property_id, status, expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE property_behavior_events (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    visitor_key VARCHAR(64) NULL,
    property_id INT NOT NULL,
    event_type ENUM('view', 'favorite', 'request') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (property_id) REFERENCES properties(id),
    INDEX idx_pbe_property_time (property_id, created_at),
    INDEX idx_pbe_user_time (user_id, created_at),
    INDEX idx_pbe_visitor_time (visitor_key, created_at),
    INDEX idx_pbe_event_time (event_type, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE property_impressions (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    visitor_key VARCHAR(64) NULL,
    property_id INT NOT NULL,
    surface VARCHAR(64) NOT NULL,
    shown_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pi_property (property_id),
    INDEX idx_pi_visitor_surface_time (visitor_key, surface, shown_at),
    INDEX idx_pi_user_surface_time (user_id, surface, shown_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (property_id) REFERENCES properties(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Pedidos (requests), chat e comissões
-- -----------------------------------------------------------------------------

CREATE TABLE requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    property_id INT NOT NULL,
    affiliate_id INT,
    type ENUM('compra', 'aluguer_curto', 'aluguer_longo') NOT NULL,
    payment_term ENUM('mensal', 'trimestral', 'semestral', 'anual') NULL,
    months_count TINYINT NULL,
    monthly_reference_amount DECIMAL(15,2) NULL,
    modality_total_amount DECIMAL(15,2) NULL,
    status ENUM('em_contacto', 'fechado_ganho', 'cancelado', 'expirado', 'em_disputa') NOT NULL DEFAULT 'em_contacto',
    commercial_status ENUM('em_contacto', 'fechado_ganho', 'cancelado', 'expirado') NULL DEFAULT NULL,
    dispute_status ENUM('nenhuma', 'aberta', 'em_analise', 'julgada_procedente', 'julgada_improcedente') NOT NULL DEFAULT 'nenhuma',
    next_followup_at TIMESTAMP NULL,
    last_sla_alert_at TIMESTAMP NULL,
    attribution_expires_at TIMESTAMP NULL,
    closing_confirmation_status ENUM('pendente', 'confirmado', 'contestada') NULL,
    payment_confirmation_status ENUM('pendente', 'declarado_comprador', 'confirmado_proprietario', 'contestado') NULL,
    payment_declared_by INT NULL,
    payment_declared_at TIMESTAMP NULL,
    payment_proof_path VARCHAR(255) NULL,
    payment_received_confirmed_by INT NULL,
    payment_received_confirmed_at TIMESTAMP NULL,
    closing_declared_by INT NULL,
    closing_declared_at TIMESTAMP NULL,
    closing_confirmed_by INT NULL,
    closing_confirmed_at TIMESTAMP NULL,
    contact_started_at TIMESTAMP NULL DEFAULT NULL,
    dispute_open_until TIMESTAMP NULL DEFAULT NULL,
    last_interaction_at TIMESTAMP NULL DEFAULT NULL,
    message TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (property_id) REFERENCES properties(id),
    FOREIGN KEY (affiliate_id) REFERENCES users(id),
    FOREIGN KEY (closing_declared_by) REFERENCES users(id),
    FOREIGN KEY (closing_confirmed_by) REFERENCES users(id),
    FOREIGN KEY (payment_declared_by) REFERENCES users(id),
    FOREIGN KEY (payment_received_confirmed_by) REFERENCES users(id),
    INDEX idx_requests_user_created (user_id, created_at),
    INDEX idx_requests_property_status (property_id, status),
    INDEX idx_requests_affiliate (affiliate_id),
    INDEX idx_requests_next_followup (next_followup_at),
    INDEX idx_requests_attribution_expires (attribution_expires_at),
    INDEX idx_commercial_status (commercial_status),
    INDEX idx_dispute_status (dispute_status),
    INDEX idx_requests_payment_confirmation_status (payment_confirmation_status),
    INDEX idx_requests_payment_declared_at (payment_declared_at),
    INDEX idx_requests_status_property_user (status, property_id, user_id),
    INDEX idx_requests_status_updated (status, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE request_chat_threads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    request_id INT NOT NULL,
    status ENUM('ativo', 'bloqueado', 'encerrado') NOT NULL DEFAULT 'ativo',
    last_message_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_request_chat_thread (request_id),
    INDEX idx_request_chat_threads_last_message_at (last_message_at),
    CONSTRAINT fk_request_chat_threads_request FOREIGN KEY (request_id) REFERENCES requests(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE request_chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    thread_id INT NOT NULL,
    sender_user_id INT NOT NULL,
    message_type ENUM('text', 'system') NOT NULL DEFAULT 'text',
    message_text TEXT NOT NULL,
    attachment_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    edited_at TIMESTAMP NULL DEFAULT NULL,
    deleted_at TIMESTAMP NULL DEFAULT NULL,
    KEY idx_request_chat_messages_thread_created (thread_id, created_at),
    KEY idx_rcm_thread_sender_created (thread_id, sender_user_id, created_at),
    KEY idx_rcm_thread_deleted_created (thread_id, deleted_at, created_at),
    CONSTRAINT fk_request_chat_messages_thread FOREIGN KEY (thread_id) REFERENCES request_chat_threads(id) ON DELETE CASCADE,
    CONSTRAINT fk_request_chat_messages_sender FOREIGN KEY (sender_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- last_read_message_id: cursor de leitura por mensagem (scripts/migration_20260612_request_chat_reads_message_cursor.sql)
CREATE TABLE request_chat_reads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    thread_id INT NOT NULL,
    user_id INT NOT NULL,
    last_read_at TIMESTAMP NULL DEFAULT NULL,
    last_read_message_id INT UNSIGNED NULL DEFAULT NULL COMMENT 'Última mensagem lida (contador de não lidas)',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_request_chat_read_user (thread_id, user_id),
    KEY idx_request_chat_reads_user (user_id, updated_at),
    CONSTRAINT fk_request_chat_reads_thread FOREIGN KEY (thread_id) REFERENCES request_chat_threads(id) ON DELETE CASCADE,
    CONSTRAINT fk_request_chat_reads_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE commissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    affiliate_id INT NOT NULL,
    property_id INT NOT NULL,
    request_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    total_pct DECIMAL(5,2) NOT NULL DEFAULT 5.00,
    system_pct DECIMAL(5,2) NOT NULL DEFAULT 2.00,
    affiliate_pct DECIMAL(5,2) NOT NULL DEFAULT 3.00,
    system_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    affiliate_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
    status ENUM('pendente', 'pago', 'cancelado') DEFAULT 'pendente',
    due_at TIMESTAMP NULL,
    paid_at TIMESTAMP NULL,
    payment_reference VARCHAR(100) NULL,
    owner_payment_proof_path VARCHAR(255) NULL,
    owner_payment_reference VARCHAR(120) NULL,
    owner_payment_submitted_at TIMESTAMP NULL,
    owner_payment_method_id INT NULL,
    owner_payment_channel_id INT NULL,
    owner_payment_status ENUM('nenhum', 'enviado', 'aprovado', 'rejeitado') NOT NULL DEFAULT 'nenhum',
    owner_payment_validated_by INT NULL,
    owner_payment_validated_at TIMESTAMP NULL,
    owner_payment_rejection_reason VARCHAR(255) NULL,
    affiliate_payout_account_id INT NULL,
    affiliate_payout_completed_at TIMESTAMP NULL,
    affiliate_payout_status ENUM('nenhum', 'pendente', 'pago') NOT NULL DEFAULT 'nenhum',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_commission_request (request_id),
    FOREIGN KEY (affiliate_id) REFERENCES users(id),
    FOREIGN KEY (property_id) REFERENCES properties(id),
    FOREIGN KEY (request_id) REFERENCES requests(id),
    INDEX idx_commissions_affiliate_status (affiliate_id, status),
    INDEX idx_commissions_request (request_id),
    INDEX idx_commissions_due_status (due_at, status),
    INDEX idx_commissions_owner_payment_submitted (owner_payment_submitted_at),
    INDEX idx_commissions_owner_payment_status (owner_payment_status, status),
    INDEX idx_commissions_affiliate_payout_queue (affiliate_payout_status, status, paid_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Notificações, documentos, auditoria, métricas, filas
-- -----------------------------------------------------------------------------

CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_group VARCHAR(100) NULL,
    grouped_count INT NOT NULL DEFAULT 1,
    actor_id INT NULL,
    type VARCHAR(80) NOT NULL,
    title VARCHAR(180) NOT NULL,
    message TEXT NOT NULL,
    metadata JSON NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    is_archived TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    read_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (actor_id) REFERENCES users(id),
    INDEX idx_notifications_user_read_created (user_id, is_read, created_at),
    INDEX idx_notifications_user_read_archived (user_id, is_read, is_archived),
    INDEX idx_notifications_type (type),
    INDEX idx_notifications_group (notification_group),
    INDEX idx_notifications_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    property_id INT,
    type VARCHAR(50) NOT NULL,
    filename VARCHAR(255) NOT NULL,
    version VARCHAR(20) DEFAULT 'v1',
    status ENUM('pendente', 'aprovado', 'rejeitado') DEFAULT 'pendente',
    rejection_reason TEXT,
    reviewed_by INT,
    reviewed_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (property_id) REFERENCES properties(id),
    FOREIGN KEY (reviewed_by) REFERENCES users(id),
    INDEX idx_documents_user_status (user_id, status),
    INDEX idx_documents_property_status (property_id, status),
    INDEX idx_documents_user_version (user_id, version),
    INDEX idx_documents_type_status (type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    action VARCHAR(255) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT,
    details TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_logs_entity (entity_type, entity_id),
    INDEX idx_logs_user_created (user_id, created_at),
    INDEX idx_logs_entity_created (entity_type, entity_id, created_at),
    INDEX idx_logs_action_created (action, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE saved_searches (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    filters JSON NOT NULL,
    search_type VARCHAR(80) NULL,
    search_purpose VARCHAR(80) NULL,
    country_id INT NULL,
    region_id INT NULL,
    min_price DECIMAL(14,2) NULL,
    max_price DECIMAL(14,2) NULL,
    min_area DECIMAL(14,2) NULL,
    max_area DECIMAL(14,2) NULL,
    bedrooms INT NULL,
    bathrooms INT NULL,
    search_keyword VARCHAR(255) NULL,
    trusted_only TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_saved_searches_user (user_id),
    INDEX idx_saved_searches_search_type (search_type),
    INDEX idx_saved_searches_search_purpose (search_purpose),
    INDEX idx_saved_searches_region (region_id),
    INDEX idx_saved_searches_country (country_id),
    INDEX idx_saved_searches_keyword (search_keyword),
    INDEX idx_saved_searches_trusted_only (trusted_only)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE metric_events (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NULL,
    entity_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    metadata TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_metric_events_event_type_created_at (event_type, created_at),
    INDEX idx_metric_events_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE background_jobs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    queue_name VARCHAR(60) NOT NULL,
    payload JSON NOT NULL,
    status ENUM('pending', 'processing', 'completed', 'failed') NOT NULL DEFAULT 'pending',
    priority TINYINT NOT NULL DEFAULT 5,
    attempts INT NOT NULL DEFAULT 0,
    max_attempts INT NOT NULL DEFAULT 5,
    run_after DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    locked_at DATETIME NULL,
    locked_by VARCHAR(80) NULL,
    completed_at DATETIME NULL,
    failed_at DATETIME NULL,
    last_error TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_jobs_status_queue_run_after (status, queue_name, run_after),
    INDEX idx_jobs_locked_at_status (locked_at, status),
    INDEX idx_jobs_priority_created (priority, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -----------------------------------------------------------------------------
-- Índices adicionais em users
-- -----------------------------------------------------------------------------

CREATE INDEX idx_users_status ON users(status);
CREATE INDEX idx_users_affiliate_code ON users(affiliate_code);
CREATE INDEX idx_users_trust_badge_status ON users(trust_badge_status);
CREATE INDEX idx_users_email_status_role ON users(email, status, role);
CREATE INDEX idx_users_role_status_created ON users(role, status, created_at);

-- =============================================================================
-- Dados iniciais (catálogos e exemplos)
-- =============================================================================

INSERT INTO countries (code, name, sort_order) VALUES
('AO', 'Angola', 10);

INSERT INTO regions (country_id, code, name, sort_order)
SELECT c.id, r.code, r.name, r.sort_order
FROM countries c
JOIN (
    SELECT 'luanda' AS code, 'Luanda' AS name, 10 AS sort_order
    UNION ALL SELECT 'bengo', 'Bengo', 20
    UNION ALL SELECT 'benguela', 'Benguela', 30
    UNION ALL SELECT 'bie', 'Bié', 40
    UNION ALL SELECT 'cabinda', 'Cabinda', 50
    UNION ALL SELECT 'cuando_cubango', 'Cuando Cubango', 60
    UNION ALL SELECT 'cuanza_norte', 'Cuanza Norte', 70
    UNION ALL SELECT 'cuanza_sul', 'Cuanza Sul', 80
    UNION ALL SELECT 'cunene', 'Cunene', 90
    UNION ALL SELECT 'huambo', 'Huambo', 100
    UNION ALL SELECT 'huila', 'Huíla', 110
    UNION ALL SELECT 'malanje', 'Malanje', 120
    UNION ALL SELECT 'moxico', 'Moxico', 130
    UNION ALL SELECT 'namibe', 'Namibe', 140
    UNION ALL SELECT 'uige', 'Uíge', 150
    UNION ALL SELECT 'zaire', 'Zaire', 160
    UNION ALL SELECT 'lunda_norte', 'Lunda Norte', 170
    UNION ALL SELECT 'lunda_sul', 'Lunda Sul', 180
) r
WHERE c.code = 'AO';

INSERT INTO property_types (code, label_pt, category, icon, is_legacy) VALUES
('casa', 'Casa', 'residential', 'home', TRUE),
('edificio', 'Edifício', 'residential', 'building', TRUE),
('vivenda', 'Vivenda', 'residential', 'mansion', TRUE),
('terreno', 'Terreno', 'land', 'map', TRUE),
('apartamento', 'Apartamento', 'residential', 'door', TRUE);

INSERT INTO property_types (code, label_pt, category, icon) VALUES
('moradia_simples', 'Moradia Simples', 'residential', 'home-alt'),
('moradia_geminada', 'Moradia Geminada', 'residential', 'home-heart'),
('moradia_duplex', 'Moradia Duplex', 'residential', 'home-lg-alt'),
('estudio_kitnet', 'Estúdio/Kitnet', 'residential', 'door-open'),
('loft', 'Loft', 'residential', 'building'),
('condominio_fechado_unidade', 'Condomínio Fechado (Unidade)', 'residential', 'fence'),
('quarto_suite', 'Quarto/Suite', 'residential', 'door-closed'),
('loja', 'Loja', 'commercial', 'shop'),
('escritorio', 'Escritório', 'commercial', 'building-check'),
('sala_comercial', 'Sala Comercial', 'commercial', 'door'),
('pavilhao_armazem', 'Pavilhão/Armazém', 'commercial', 'boxes'),
('galpao', 'Galpão', 'commercial', 'boxes'),
('centro_comercial_loja_interna', 'Centro Comercial (Loja Interna)', 'commercial', 'shop-window'),
('quiosque', 'Quiosque', 'commercial', 'shop-small'),
('fabrica', 'Fábrica', 'industrial', 'cog'),
('parque_industrial', 'Parque Industrial', 'industrial', 'cogs'),
('armazem_logistico', 'Armazém Logístico', 'industrial', 'boxes-stacked'),
('deposito', 'Depósito', 'industrial', 'boxes'),
('terreno_urbano', 'Terreno Urbano', 'land', 'map-pin'),
('loteamento', 'Loteamento', 'land', 'map-quad'),
('terreno_rural', 'Terreno Rural', 'land', 'map-tree'),
('terreno_agricola', 'Terreno Agrícola', 'land', 'map-grain'),
('terreno_industrial', 'Terreno Industrial', 'land', 'map-factory'),
('hotel', 'Hotel', 'tourism', 'bed-front'),
('motel', 'Motel', 'tourism', 'bed'),
('hostel', 'Hostel', 'tourism', 'bed-alt'),
('apart_hotel', 'Apart Hotel', 'tourism', 'bed-filled'),
('alojamento_local', 'Alojamento Local', 'tourism', 'house-door'),
('guest_house', 'Guest House', 'tourism', 'house-fill'),
('escola', 'Escola', 'institutional', 'book'),
('hospital_clinica', 'Hospital/Clínica', 'institutional', 'heart-pulse'),
('igreja_templo', 'Igreja/Templo', 'institutional', 'cross'),
('predio_publico', 'Prédio Público', 'institutional', 'building-columns'),
('centro_comunitario', 'Centro Comunitário', 'institutional', 'people'),
('garagem', 'Garagem', 'complementary', 'car'),
('estacionamento', 'Estacionamento', 'complementary', 'parking'),
('arrecadacao_anexo', 'Arrecadação/Anexo', 'complementary', 'box'),
('box', 'Box', 'complementary', 'boxes');

INSERT INTO subscription_plans (
    code, name, monthly_price_aoa, max_active_properties, ranking_weight, visibility_tier,
    has_featured_in_results, has_reports, has_advanced_reports, has_priority_support,
    has_auto_renew, has_institutional_page, is_custom_pricing, is_active
) VALUES
    ('essential', 'Plano Essencial', 0.00, 3, 0, 'basic', FALSE, FALSE, FALSE, FALSE, FALSE, FALSE, FALSE, TRUE),
    ('professional', 'Plano Profissional', 25000.00, 15, 50, 'premium', TRUE, TRUE, FALSE, TRUE, TRUE, FALSE, FALSE, TRUE),
    ('enterprise', 'Plano Empresarial', 100000.00, NULL, 100, 'premium', TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE, TRUE);

INSERT INTO payment_methods (code, name, direction, audience, requires_reference, fields_config, is_active) VALUES
    ('bank_transfer', 'Depósito/Transferência Bancária', 'both', 'both', TRUE,
     '{"account_name":true,"account_number":true,"iban":true,"bank_name":true,"wallet_provider":false,"phone_number":false}', TRUE);

INSERT INTO system_payment_channels (method_id, channel_name, account_name, account_number, iban, bank_name, is_default, is_active)
SELECT pm.id, 'Conta principal BAI', 'Imobil Facil Lda', '1234567890123', 'AO06004000001234567890123', 'BAI', TRUE, TRUE
FROM payment_methods pm WHERE pm.code = 'bank_transfer';

INSERT INTO settings (`key`, value, label, description) VALUES
    ('commission_system_pct', '2.00', 'Taxa do sistema (%)', 'Percentagem da comissão para o sistema com afiliado válido.'),
    ('commission_affiliate_pct', '3.00', 'Taxa do afiliado (%)', 'Percentagem da comissão para o afiliado.'),
    ('commission_system_only_pct', '5.00', 'Taxa do sistema sem afiliado (%)', 'Percentagem total sem afiliado válido.'),
    ('commission_due_days', '7', 'Prazo de vencimento da comissão (dias)', 'Dias até vencimento após lançamento.'),
    ('subscription_grace_days', '5', 'Subscrição: dias de tolerância', 'Dias após vencimento antes de expirar subscrição ativa.'),
    ('boost_daily_fee', '2000', 'Destaque: valor por dia (Kz)', 'Preço por dia de destaque.'),
    ('boost_min_days', '7', 'Destaque: duração mínima (dias)', 'Menor duração de destaque.'),
    ('boost_max_days', '90', 'Destaque: duração máxima (dias)', 'Maior duração de destaque.'),
    ('boost_default_days', '30', 'Destaque: duração padrão (dias)', 'Duração predefinida no formulário.'),
    ('trust_badge_monthly_fee', '5000', 'Selo confiança: valor por mês (Kz)', 'Preço por mês no pedido de selo.'),
    ('trust_badge_min_months', '1', 'Selo confiança: duração mínima (meses)', 'Menor duração do selo.'),
    ('trust_badge_max_months', '12', 'Selo confiança: duração máxima (meses)', 'Maior duração do selo.'),
    ('trust_badge_default_months', '6', 'Selo confiança: duração padrão (meses)', 'Duração predefinida no formulário.'),
    ('trust_badge_min_won_deals', '3', 'Selo confiança: negócios ganhos mínimos', 'Mínimo de fechos ganhos para solicitar selo.'),
    ('trust_badge_min_account_days', '90', 'Selo confiança: dias mínimos na plataforma', 'Dias desde registo para solicitar selo.'),
    ('trust_badge_require_confirmed_closing', '1', 'Selo confiança: exigir fecho confirmado', '1 = só fechos confirmados contam.'),
    ('behavior_ranking_enabled', '0', 'Ranking comportamental ativado', '0 = safe mode; 1 = ordenação por comportamento.'),
    ('behavior_ranking_lookback_days', '90', 'Ranking: janela comportamental (dias)', 'Dias de histórico de eventos por utilizador.'),
    ('behavior_weight_view', '1', 'Ranking: peso visualização', 'Peso do evento view.'),
    ('behavior_weight_favorite', '4', 'Ranking: peso favorito', 'Peso do evento favorite.'),
    ('behavior_weight_request', '8', 'Ranking: peso solicitação', 'Peso do evento request.'),
    ('behavior_max_score_per_property', '50', 'Ranking: teto de score por imóvel', 'Limite de score por imóvel/visitante.'),
    ('behavior_decay_lambda', '0.035', 'Discovery: decaimento temporal (lambda)', 'Decaimento por dia nos eventos.'),
    ('behavior_view_penalty_threshold', '4', 'Discovery: limiar views sem conversão', 'Views sem favorito/pedido antes de penalizar.'),
    ('behavior_view_penalty_points', '6', 'Discovery: pontos de penalização', 'Pontos subtraídos no limiar.'),
    ('behavior_explore_ratio', '15', 'Discovery: % exploração', 'Percentagem de slots de exploração (0-30).'),
    ('behavior_impression_cooldown_hours', '24', 'Discovery: cooldown de impressão (h)', 'Horas antes de repetir imóvel na superfície.'),
    ('behavior_home_carousel_size', '8', 'Discovery: carrossel na home', 'Imóveis no carrossel personalizado.'),
    ('behavior_continue_exploring_size', '6', 'Discovery: continuar a explorar', 'Imóveis no bloco de retoma.'),
    ('behavior_promoted_interval', '4', 'Discovery: intervalo patrocinados', 'Intervalo de patrocinados na grelha.'),
    ('property_search_fulltext_enabled', '1', 'Pesquisa fulltext ativa', '1 = usar índice FULLTEXT em propriedades.'),
    ('property_offset_default_limit', '50', 'Listagens: limite por defeito', 'Máximo de registos por página em listagens.'),
    ('page_cache_enabled', '1', 'Cache de páginas ativo', '1 = PageCache ativo em rotas públicas.'),
    ('page_cache_home_ttl_seconds', '300', 'Cache home (segundos)', 'TTL da página inicial.'),
    ('page_cache_property_list_ttl_seconds', '120', 'Cache listagem imóveis (s)', 'TTL da listagem pública.'),
    ('page_cache_property_show_ttl_seconds', '180', 'Cache detalhe imóvel (s)', 'TTL da ficha do imóvel.'),
    ('cache_featured_ttl_seconds', '3600', 'Cache destaques (segundos)', 'TTL da listagem de featured.'),
    ('cache_property_list_ttl_seconds', '120', 'Cache listagem interna (s)', 'TTL de listagens no modelo Property.'),
    ('cache_property_count_ttl_seconds', '300', 'Cache contagem (segundos)', 'TTL de contagens agregadas.'),
    ('mail_queue_enabled', '1', 'Fila assíncrona de emails ativa', 'Emails via worker quando 1.'),
    ('mail_queue_max_attempts', '5', 'Máximo de tentativas por email', 'Tentativas por job de email.'),
    ('mail_queue_batch_size', '20', 'Lote worker de email', 'Jobs por ciclo do mail_queue_worker.'),
    ('mail_queue_lock_timeout_seconds', '300', 'Lock worker email (s)', 'Lock órfão devolvido a pending.'),
    ('image_queue_batch_size', '10', 'Lote worker de imagens', 'Jobs por ciclo do image_queue_worker.'),
    ('image_queue_lock_timeout_seconds', '300', 'Lock worker imagens (s)', 'Timeout de lock do worker de imagens.'),
    ('notify_property_batch_size', '20', 'Lote notificação novo imóvel', 'Jobs por ciclo do notify_new_property_worker.'),
    ('notify_property_lock_timeout_seconds', '300', 'Lock notificação imóvel (s)', 'Timeout de lock do worker.'),
    ('report_queue_batch_size', '5', 'Lote worker de relatórios', 'Jobs por ciclo do report_queue_worker.'),
    ('report_queue_lock_timeout_seconds', '600', 'Lock worker relatórios (s)', 'Timeout de lock do worker.'),
    ('api_rate_limit_max', '300', 'API rate limit (token/IP)', 'Pedidos API por IP/token por janela.'),
    ('api_rate_limit_window_seconds', '60', 'Janela rate limit API', 'Janela em segundos.'),
    ('api_token_ttl_days', '365', 'Validade tokens API (dias)', 'Validade padrão de novos tokens.'),
    ('rate_limit_post_max', '60', 'Limite global POST por janela', 'POST por IP e rota.'),
    ('rate_limit_post_window_seconds', '60', 'Janela rate limit POST (s)', 'Duração da janela POST.'),
    ('rate_limit_auth_login_max', '10', 'Limite de login por janela', 'Tentativas de login por IP/rota.'),
    ('rate_limit_auth_login_window_seconds', '60', 'Janela rate limit login (s)', 'Janela do endpoint de login.'),
    ('rate_limit_auth_register_max', '5', 'Limite de registos por janela', 'Registos por IP/rota.'),
    ('rate_limit_auth_register_window_seconds', '300', 'Janela rate limit registo (s)', 'Janela do endpoint de registo.'),
    ('rate_limit_auth_recover_max', '5', 'Limite recuperação senha', 'Pedidos de recuperação por IP/rota.'),
    ('rate_limit_auth_recover_window_seconds', '300', 'Janela recuperação senha (s)', 'Janela de recuperação.'),
    ('rate_limit_auth_reset_max', '10', 'Limite de reset por janela', 'Redefinições por IP/rota.'),
    ('rate_limit_auth_reset_window_seconds', '600', 'Janela rate limit reset (s)', 'Janela de redefinição.'),
    ('rate_limit_api_max', '120', 'Limite API por rota', 'Pedidos API por IP/rota.'),
    ('rate_limit_api_window_seconds', '60', 'Janela API por rota (s)', 'Janela dos endpoints web API.'),
    ('account_deletion_grace_days', '60', 'Dias de conformidade antes de eliminar conta', 'Após o utilizador solicitar a eliminação, a conta fica inacessível durante este período. No fim, o sistema elimina a conta automaticamente.'),
    ('property_deletion_grace_days', '30', 'Dias de conformidade antes de eliminar imóvel', 'Após o proprietário solicitar a eliminação, o imóvel fica indisponível ao público durante este período. Continua visível em conversas e negociações com estado «eliminado». No fim, o sistema remove o anúncio do portfólio.'),
    ('deletion_reminder_days_before', '7', 'Aviso antes da eliminação (dias)', 'Quantos dias antes da data agendada enviar email de lembrete ao utilizador (conta ou imóvel).'),
    ('deletion_overdue_alert_last_sent_at', '', 'Último alerta de eliminação em atraso', 'Timestamp interno do último email de alerta a admins sobre eliminações em atraso (rate-limit).')
ON DUPLICATE KEY UPDATE value = VALUES(value), label = VALUES(label), description = VALUES(description);

-- Password hash: "password" (bcrypt)
INSERT INTO users (
    email, email_verified_at, password, name, username, user_type, document_number, phone,
    is_affiliate, affiliate_code, is_admin, role, status,
    account_plan, trust_badge_status, trust_badge_requested_at,
    trust_badge_approved_at, trust_badge_fee_required, trust_badge_fee_paid,
    document_file, profile_photo, created_at
) VALUES
('admin@imobil.com', NOW(), '$2y$10$r2YDr7CKjYOIqv/ef10AjeNMH5KXb38CUFt328itgFkafq5fOQKhq', 'Admin Plataforma', 'admin', 'pessoa_fisica', '00000000000000', '+244900000001', FALSE, NULL, TRUE, 'super_admin', 'ativo', 'premium', 'aprovado', NULL, NOW(), 0, TRUE, 'doc_admin.pdf', NULL, DATE_SUB(NOW(), INTERVAL 900 DAY)),
('affiliate1@imobil.com', NOW(), '$2y$10$r2YDr7CKjYOIqv/ef10AjeNMH5KXb38CUFt328itgFkafq5fOQKhq', 'Ana Parceira', 'ana_parceira', 'pessoa_fisica', '11111111111111', '+244900000002', TRUE, 'AFF001', FALSE, 'utilizador', 'ativo', 'premium', 'aprovado', DATE_SUB(NOW(), INTERVAL 400 DAY), DATE_SUB(NOW(), INTERVAL 360 DAY), 15000, TRUE, 'doc_aff1.pdf', NULL, DATE_SUB(NOW(), INTERVAL 420 DAY)),
('affiliate2@imobil.com', NOW(), '$2y$10$r2YDr7CKjYOIqv/ef10AjeNMH5KXb38CUFt328itgFkafq5fOQKhq', 'Bruno Corretor', 'bruno_corretor', 'pessoa_fisica', '22222222222222', '+244900000003', TRUE, 'AFF002', FALSE, 'utilizador', 'ativo', 'free', 'pendente', DATE_SUB(NOW(), INTERVAL 8 DAY), NULL, 0, FALSE, 'doc_aff2.pdf', NULL, DATE_SUB(NOW(), INTERVAL 220 DAY)),
('owner1@imobil.com', NOW(), '$2y$10$r2YDr7CKjYOIqv/ef10AjeNMH5KXb38CUFt328itgFkafq5fOQKhq', 'Carla Proprietaria', 'carla_prop', 'pessoa_fisica', '33333333333333', '+244900000004', FALSE, NULL, FALSE, 'utilizador', 'ativo', 'free', 'nenhum', NULL, NULL, 0, FALSE, 'doc_owner1.pdf', NULL, DATE_SUB(NOW(), INTERVAL 120 DAY)),
('owner2@imobil.com', NOW(), '$2y$10$r2YDr7CKjYOIqv/ef10AjeNMH5KXb38CUFt328itgFkafq5fOQKhq', 'Domingos Premium', 'domingos_premium', 'pessoa_fisica', '44444444444444', '+244900000005', FALSE, NULL, FALSE, 'utilizador', 'ativo', 'premium', 'nenhum', NULL, NULL, 0, FALSE, 'doc_owner2.pdf', NULL, DATE_SUB(NOW(), INTERVAL 320 DAY)),
('cliente1@imobil.com', NOW(), '$2y$10$r2YDr7CKjYOIqv/ef10AjeNMH5KXb38CUFt328itgFkafq5fOQKhq', 'Elisa Compradora', 'elisa_compradora', 'pessoa_fisica', '55555555555555', '+244900000006', FALSE, NULL, FALSE, 'utilizador', 'ativo', 'free', 'nenhum', NULL, NULL, 0, FALSE, 'doc_cliente1.pdf', NULL, DATE_SUB(NOW(), INTERVAL 60 DAY)),
('cliente2@imobil.com', NOW(), '$2y$10$r2YDr7CKjYOIqv/ef10AjeNMH5KXb38CUFt328itgFkafq5fOQKhq', 'Filipe Investidor', 'filipe_investidor', 'pessoa_fisica', '66666666666666', '+244900000007', FALSE, NULL, FALSE, 'utilizador', 'ativo', 'free', 'nenhum', NULL, NULL, 0, FALSE, 'doc_cliente2.pdf', NULL, DATE_SUB(NOW(), INTERVAL 35 DAY)),
('empresa@imobil.com', NULL, '$2y$10$r2YDr7CKjYOIqv/ef10AjeNMH5KXb38CUFt328itgFkafq5fOQKhq', 'Gama Construcoes', 'gama_construcoes', 'pessoa_juridica', '1234567890', '+244900000008', FALSE, NULL, FALSE, 'utilizador', 'pendente', 'premium', 'nenhum', NULL, NULL, 0, FALSE, 'doc_empresa.pdf', NULL, DATE_SUB(NOW(), INTERVAL 10 DAY)),
('moderador@imobil.com', NOW(), '$2y$10$r2YDr7CKjYOIqv/ef10AjeNMH5KXb38CUFt328itgFkafq5fOQKhq', 'Helena Moderadora', 'helena_mod', 'pessoa_fisica', '77777777777777', '+244900000009', FALSE, NULL, FALSE, 'moderador', 'ativo', 'premium', 'nenhum', NULL, NULL, 0, FALSE, 'doc_moderador.pdf', NULL, DATE_SUB(NOW(), INTERVAL 200 DAY)),
('financeiro@imobil.com', NOW(), '$2y$10$r2YDr7CKjYOIqv/ef10AjeNMH5KXb38CUFt328itgFkafq5fOQKhq', 'Ivo Financeiro', 'ivo_financeiro', 'pessoa_fisica', '88888888888888', '+244900000010', FALSE, NULL, FALSE, 'financeiro', 'ativo', 'premium', 'nenhum', NULL, NULL, 0, FALSE, 'doc_financeiro.pdf', NULL, DATE_SUB(NOW(), INTERVAL 180 DAY)),
('suporte@imobil.com', NOW(), '$2y$10$r2YDr7CKjYOIqv/ef10AjeNMH5KXb38CUFt328itgFkafq5fOQKhq', 'Joana Suporte', 'joana_suporte', 'pessoa_fisica', '99999999999999', '+244900000011', FALSE, NULL, FALSE, 'suporte', 'ativo', 'free', 'nenhum', NULL, NULL, 0, FALSE, 'doc_suporte.pdf', NULL, DATE_SUB(NOW(), INTERVAL 150 DAY));

INSERT INTO user_subscriptions (user_id, plan_id, status, starts_at, auto_renew, billing_cycle_months, notes)
SELECT u.id, sp.id, 'active', NOW(),
    CASE WHEN sp.code = 'professional' THEN TRUE ELSE FALSE END,
    1, 'Seed: subscrição inicial'
FROM users u
JOIN subscription_plans sp ON sp.code = CASE WHEN u.account_plan = 'premium' THEN 'professional' ELSE 'essential' END;

INSERT INTO properties (
    title, description, type, purpose, price, country_id, region_id, location,
    bedrooms, bathrooms, area, images, video_url, affiliate_id, owner_bonus_pct,
    visibility, featured, status, created_at
)
SELECT
    s.title, s.description, s.type, s.purpose, s.price, c.id, r.id, s.location,
    s.bedrooms, s.bathrooms, s.area, s.images, s.video_url, s.affiliate_id,
    s.owner_bonus_pct, s.visibility, s.featured, s.status, s.created_at
FROM (
    SELECT 'Casa Moderna em Luanda' AS title, 'Casa espacosa com 3 quartos e quintal amplo.' AS description, 'casa' AS type, 'venda' AS purpose, 150000.00 AS price, 'Luanda' AS location, 3 AS bedrooms, 2 AS bathrooms, 210.00 AS area, '["img/casa1.jpg", "img/casa1_2.jpg"]' AS images, 'https://www.youtube.com/watch?v=dQw4w9WgXcQ' AS video_url, 4 AS affiliate_id, 0.00 AS owner_bonus_pct, 'basic' AS visibility, TRUE AS featured, 'disponivel' AS status, DATE_SUB(NOW(), INTERVAL 25 DAY) AS created_at
    UNION ALL SELECT 'Apartamento Centro', 'Apartamento no centro da cidade com excelente acesso.', 'apartamento', 'aluguer_longo', 500.00, 'Luanda Centro', 2, 1, 78.00, '["img/apto1.jpg"]', NULL, 5, 1.50, 'premium', TRUE, 'disponivel', DATE_SUB(NOW(), INTERVAL 20 DAY)
    UNION ALL SELECT 'Vivenda Talatona', 'Vivenda com piscina e 4 suites.', 'vivenda', 'venda', 420000.00, 'Talatona', 4, 5, 450.00, '["img/vivenda1.jpg"]', 'https://www.youtube.com/watch?v=ysz5S6PUM-U', 5, 2.00, 'premium', TRUE, 'disponivel', DATE_SUB(NOW(), INTERVAL 18 DAY)
    UNION ALL SELECT 'Terreno Benfica', 'Terreno murado pronto para construcao.', 'terreno', 'venda', 90000.00, 'Benfica', 0, 0, 600.00, '["img/terreno1.jpg"]', NULL, 4, 0.50, 'basic', FALSE, 'disponivel', DATE_SUB(NOW(), INTERVAL 16 DAY)
    UNION ALL SELECT 'Edificio Kilamba', 'Edificio comercial com 5 andares.', 'edificio', 'venda', 980000.00, 'Kilamba', 10, 8, 1800.00, '["img/edificio1.jpg"]', NULL, 5, 3.00, 'premium', FALSE, 'disponivel', DATE_SUB(NOW(), INTERVAL 14 DAY)
    UNION ALL SELECT 'Apartamento Maianga', 'T2 mobilado para aluguer curto.', 'apartamento', 'aluguer_curto', 350.00, 'Maianga', 2, 2, 82.00, '["img/apto2.jpg"]', NULL, 4, 0.00, 'basic', FALSE, 'disponivel', DATE_SUB(NOW(), INTERVAL 12 DAY)
    UNION ALL SELECT 'Casa Viana', 'Casa familiar com anexo independente.', 'casa', 'aluguer_longo', 700.00, 'Viana', 3, 2, 190.00, '["img/casa2.jpg"]', NULL, 4, 1.00, 'basic', FALSE, 'pendente', DATE_SUB(NOW(), INTERVAL 5 DAY)
    UNION ALL SELECT 'Terreno Cacuaco', 'Terreno para projeto habitacional.', 'terreno', 'venda', 65000.00, 'Cacuaco', 0, 0, 520.00, '["img/terreno2.jpg"]', NULL, 5, 0.00, 'premium', FALSE, 'rejeitado', DATE_SUB(NOW(), INTERVAL 3 DAY)
) s
CROSS JOIN countries c
CROSS JOIN regions r
WHERE c.code = 'AO' AND r.code = 'luanda';

INSERT INTO favorites (user_id, property_id, created_at) VALUES
(6, 2, DATE_SUB(NOW(), INTERVAL 7 DAY)),
(6, 3, DATE_SUB(NOW(), INTERVAL 6 DAY)),
(7, 1, DATE_SUB(NOW(), INTERVAL 4 DAY)),
(7, 5, DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 2, DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO requests (
    user_id, property_id, affiliate_id, type, status, commercial_status, dispute_status,
    message, contact_started_at, last_interaction_at, created_at, updated_at
) VALUES
(6, 1, 2, 'compra', 'fechado_ganho', 'fechado_ganho', 'nenhuma', 'Tenho interesse em visita presencial.', DATE_SUB(NOW(), INTERVAL 11 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 11 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(7, 2, 2, 'aluguer_longo', 'fechado_ganho', 'fechado_ganho', 'nenhuma', 'Preciso para mudar ainda este mes.', DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 9 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY), DATE_SUB(NOW(), INTERVAL 9 DAY)),
(6, 3, 2, 'compra', 'em_contacto', 'em_contacto', 'nenhuma', 'Pretendo negociar condicoes.', DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY)),
(7, 4, 3, 'compra', 'em_contacto', 'em_contacto', 'nenhuma', 'Gostaria de confirmar documentacao.', DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 7 DAY)),
(6, 5, 2, 'compra', 'fechado_ganho', 'fechado_ganho', 'nenhuma', 'Investimento para renda.', DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
(7, 6, 3, 'aluguer_curto', 'cancelado', 'cancelado', 'nenhuma', 'Procuro estadia por 2 meses.', DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 4 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY)),
(6, 2, 2, 'aluguer_longo', 'fechado_ganho', 'fechado_ganho', 'nenhuma', 'Condominio familiar.', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO commissions (
    affiliate_id, property_id, request_id, amount,
    total_pct, system_pct, affiliate_pct,
    system_amount, affiliate_amount, status, due_at, created_at
) VALUES
(2, 1, 1, 7500.00, 5.00, 2.00, 3.00, 3000.00, 4500.00, 'pago', DATE_SUB(NOW(), INTERVAL 3 DAY), DATE_SUB(NOW(), INTERVAL 10 DAY)),
(2, 2, 2, 27.50, 5.50, 2.00, 3.50, 10.00, 17.50, 'pago', DATE_SUB(NOW(), INTERVAL 2 DAY), DATE_SUB(NOW(), INTERVAL 9 DAY)),
(2, 5, 5, 49000.00, 5.00, 2.00, 3.00, 19600.00, 29400.00, 'pendente', DATE_ADD(NOW(), INTERVAL 5 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2, 2, 7, 27.50, 5.50, 2.00, 3.50, 10.00, 17.50, 'pendente', DATE_ADD(NOW(), INTERVAL 6 DAY), DATE_SUB(NOW(), INTERVAL 2 DAY));

INSERT INTO logs (user_id, action, entity_type, entity_id, details, ip_address, created_at) VALUES
(4, 'create_property', 'property', 1, 'Imovel cadastrado e enviado para moderacao', '127.0.0.1', DATE_SUB(NOW(), INTERVAL 26 DAY)),
(5, 'create_property', 'property', 2, 'Imovel premium com acrescimo de comissao', '127.0.0.1', DATE_SUB(NOW(), INTERVAL 21 DAY)),
(1, 'approve_property', 'property', 1, 'Aprovacao manual concluida', '127.0.0.1', DATE_SUB(NOW(), INTERVAL 24 DAY)),
(1, 'approve_user', 'user', 6, 'Perfil verificado apos analise documental', '127.0.0.1', DATE_SUB(NOW(), INTERVAL 59 DAY)),
(3, 'request_trusted_badge', 'user', 3, 'Solicitou selo de utilizador de confianca', '127.0.0.1', DATE_SUB(NOW(), INTERVAL 8 DAY)),
(1, 'approve_trusted_badge', 'user', 2, 'Selo aprovado com taxa de 15000 Kz', '127.0.0.1', DATE_SUB(NOW(), INTERVAL 360 DAY));

-- -----------------------------------------------------------------------------
-- Dados de demonstração adicionais (fluxos operacionais)
-- Password de todas as contas acima: password
-- -----------------------------------------------------------------------------

INSERT INTO user_subscriptions (user_id, plan_id, status, starts_at, auto_renew, billing_cycle_months, notes)
SELECT u.id, sp.id, 'active', NOW(), TRUE, 1, 'Seed: staff e suporte'
FROM users u
JOIN subscription_plans sp ON sp.code = 'professional'
WHERE u.email IN ('moderador@imobil.com', 'financeiro@imobil.com')
  AND NOT EXISTS (SELECT 1 FROM user_subscriptions us WHERE us.user_id = u.id AND us.status = 'active');

INSERT INTO subscription_events (user_id, from_plan_id, to_plan_id, event_type, metadata, created_by, created_at)
SELECT u.id, NULL, sp.id, 'activated', JSON_OBJECT('source', 'seed'), 1, DATE_SUB(NOW(), INTERVAL 30 DAY)
FROM users u
JOIN subscription_plans sp ON sp.code = 'professional'
WHERE u.email = 'owner2@imobil.com'
  AND NOT EXISTS (
      SELECT 1 FROM subscription_events se
      WHERE se.user_id = u.id AND se.event_type = 'activated'
  );

INSERT INTO user_payment_accounts (user_id, method_id, account_label, account_name, account_number, bank_name, is_default, is_active, created_at)
SELECT u.id, pm.id, 'Conta principal', 'Carla Proprietaria', '9876543210001', 'BFA', TRUE, TRUE, DATE_SUB(NOW(), INTERVAL 60 DAY)
FROM users u
CROSS JOIN payment_methods pm
WHERE u.email = 'owner1@imobil.com' AND pm.code = 'bank_transfer'
  AND NOT EXISTS (SELECT 1 FROM user_payment_accounts upa WHERE upa.user_id = u.id AND upa.is_default = TRUE);

INSERT INTO property_affiliates (user_id, property_id, status, created_at, approved_at)
VALUES
(2, 1, 'ativo', DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 19 DAY)),
(2, 2, 'ativo', DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY)),
(3, 4, 'pendente', DATE_SUB(NOW(), INTERVAL 3 DAY), NULL);

UPDATE properties SET rental_days = 7 WHERE id = 6 AND purpose = 'aluguer_curto';
UPDATE properties SET rental_months = 12, rent_payment_terms = JSON_ARRAY('mensal', 'trimestral') WHERE id = 2 AND purpose = 'aluguer_longo';

INSERT INTO property_boost_requests (property_id, user_id, boost_type, duration_days, fee_required, status, requested_at, approved_at, expires_at)
VALUES
(1, 4, 'destaque', 30, 60000.00, 'aprovado', DATE_SUB(NOW(), INTERVAL 22 DAY), DATE_SUB(NOW(), INTERVAL 21 DAY), DATE_ADD(NOW(), INTERVAL 8 DAY)),
(2, 5, 'premium', 14, 28000.00, 'pendente', DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, NULL);

INSERT INTO payment_transactions (
    transaction_type, direction, status, amount, method_id, system_channel_id,
    counterparty_user_id, related_entity_type, related_entity_id,
    reference_code, created_by, confirmed_by, confirmed_at, created_at
)
SELECT
    'boost_fee', 'incoming', 'confirmado', 60000.00, pm.id, spc.id,
    4, 'property_boost_request', pbr.id,
    'BOOST-SEED-001', 1, 10, DATE_SUB(NOW(), INTERVAL 21 DAY), DATE_SUB(NOW(), INTERVAL 22 DAY)
FROM property_boost_requests pbr
JOIN payment_methods pm ON pm.code = 'bank_transfer'
JOIN system_payment_channels spc ON spc.method_id = pm.id AND spc.is_default = TRUE
WHERE pbr.property_id = 1 AND pbr.status = 'aprovado'
  AND NOT EXISTS (SELECT 1 FROM payment_transactions pt WHERE pt.reference_code = 'BOOST-SEED-001');

INSERT INTO documents (user_id, property_id, type, filename, version, status, reviewed_by, reviewed_at, created_at)
VALUES
(8, NULL, 'user_registration', 'doc_empresa_pendente_v1.pdf', 'v1', 'pendente', NULL, NULL, DATE_SUB(NOW(), INTERVAL 10 DAY)),
(NULL, 7, 'property_ownership', 'doc_casa_viana_v1.pdf', 'v1', 'pendente', NULL, NULL, DATE_SUB(NOW(), INTERVAL 5 DAY)),
(3, NULL, 'user_registration', 'doc_aff2_rejeitado_v1.pdf', 'v1', 'rejeitado', 9, DATE_SUB(NOW(), INTERVAL 7 DAY), DATE_SUB(NOW(), INTERVAL 8 DAY));

UPDATE documents SET rejection_reason = 'Documento ilegivel ou incompleto.' WHERE filename = 'doc_aff2_rejeitado_v1.pdf';

INSERT INTO notifications (user_id, actor_id, type, title, message, metadata, is_read, is_archived, created_at)
VALUES
(6, 2, 'request_new_message', 'Nova mensagem no pedido', 'O afiliado respondeu sobre a Casa Moderna.', JSON_OBJECT('request_id', 1), 0, 0, DATE_SUB(NOW(), INTERVAL 2 HOUR)),
(4, 1, 'property_approved', 'Imovel aprovado', 'A sua listagem Casa Moderna foi publicada.', JSON_OBJECT('property_id', 1), 1, 0, DATE_SUB(NOW(), INTERVAL 24 DAY)),
(2, 10, 'commission_paid', 'Comissao liquidada', 'Comissao do pedido #1 marcada como paga.', JSON_OBJECT('commission_id', 1), 1, 0, DATE_SUB(NOW(), INTERVAL 3 DAY)),
(3, NULL, 'trust_badge_pending', 'Selo em analise', 'O seu pedido de selo de confianca aguarda validacao.', JSON_OBJECT('user_id', 3), 0, 0, DATE_SUB(NOW(), INTERVAL 7 DAY));

INSERT INTO request_chat_threads (request_id, status, last_message_at, created_at)
SELECT r.id, 'ativo', DATE_SUB(NOW(), INTERVAL 1 DAY), DATE_SUB(NOW(), INTERVAL 11 DAY)
FROM requests r WHERE r.id = 1
  AND NOT EXISTS (SELECT 1 FROM request_chat_threads t WHERE t.request_id = r.id);

INSERT INTO request_chat_messages (thread_id, sender_user_id, message_type, message_text, created_at)
SELECT t.id, 6, 'text', 'Bom dia, gostaria de agendar uma visita este fim de semana.', DATE_SUB(NOW(), INTERVAL 10 DAY)
FROM request_chat_threads t WHERE t.request_id = 1
UNION ALL
SELECT t.id, 2, 'text', 'Confirmado. Sabado as 10h na Morro Bento.', DATE_SUB(NOW(), INTERVAL 9 DAY)
FROM request_chat_threads t WHERE t.request_id = 1
UNION ALL
SELECT t.id, (SELECT id FROM users WHERE email = 'admin@imobil.com' LIMIT 1), 'system', 'Pedido marcado como fechado ganho.', DATE_SUB(NOW(), INTERVAL 10 DAY)
FROM request_chat_threads t WHERE t.request_id = 1;

INSERT INTO request_chat_reads (thread_id, user_id, last_read_at, last_read_message_id, created_at)
SELECT
    t.id,
    u.id,
    DATE_SUB(NOW(), INTERVAL 1 DAY),
    (
        SELECT COALESCE(MAX(m.id), NULL)
        FROM request_chat_messages m
        WHERE m.thread_id = t.id
          AND m.deleted_at IS NULL
    ),
    DATE_SUB(NOW(), INTERVAL 11 DAY)
FROM request_chat_threads t
JOIN users u ON u.id IN (6, 2, 4)
WHERE t.request_id = 1
ON DUPLICATE KEY UPDATE
    last_read_at = VALUES(last_read_at),
    last_read_message_id = VALUES(last_read_message_id);

INSERT INTO saved_searches (user_id, name, filters, search_type, search_purpose, country_id, region_id, min_price, max_price, bedrooms, trusted_only, created_at)
SELECT
    6, 'Apartamentos Luanda', JSON_OBJECT('type', 'apartamento', 'purpose', 'aluguer_longo'),
    'apartamento', 'aluguer_longo', c.id, r.id, 300.00, 1500.00, 2, 0, DATE_SUB(NOW(), INTERVAL 14 DAY)
FROM countries c
JOIN regions r ON r.country_id = c.id AND r.code = 'luanda'
WHERE c.code = 'AO';

INSERT INTO property_behavior_events (user_id, visitor_key, property_id, event_type, created_at)
VALUES
(6, NULL, 1, 'view', DATE_SUB(NOW(), INTERVAL 12 DAY)),
(6, NULL, 1, 'favorite', DATE_SUB(NOW(), INTERVAL 11 DAY)),
(6, NULL, 1, 'request', DATE_SUB(NOW(), INTERVAL 11 DAY)),
(NULL, 'visitor_seed_abc123', 2, 'view', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(NULL, 'visitor_seed_abc123', 3, 'view', DATE_SUB(NOW(), INTERVAL 4 DAY));

INSERT INTO property_impressions (user_id, visitor_key, property_id, surface, shown_at)
VALUES
(6, NULL, 2, 'home_carousel', DATE_SUB(NOW(), INTERVAL 3 DAY)),
(NULL, 'visitor_seed_abc123', 1, 'search_grid', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(7, NULL, 5, 'continue_exploring', DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO metric_events (event_type, entity_type, entity_id, user_id, metadata, created_at)
VALUES
('property_search', 'search', NULL, 6, '{"keyword":"luanda","results":8}', DATE_SUB(NOW(), INTERVAL 6 DAY)),
('user_login', 'user', 1, 1, '{"method":"password"}', DATE_SUB(NOW(), INTERVAL 1 DAY));

INSERT INTO api_tokens (user_id, token, name, scopes, status, expires_at, created_at)
SELECT 1, SHA2(CONCAT('dev_seed_token_', UUID()), 256), 'Admin API Dev', 'read:properties', 'active', DATE_ADD(NOW(), INTERVAL 365 DAY), NOW()
FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM api_tokens WHERE name = 'Admin API Dev');

INSERT INTO background_jobs (queue_name, payload, status, priority, run_after, created_at)
VALUES
('mail', JSON_OBJECT('to', 'cliente1@imobil.com', 'subject', 'Seed welcome', 'template', 'generic'), 'pending', 5, NOW(), NOW()),
('images', JSON_OBJECT('property_id', 7, 'path', 'uploads/pending/casa_viana.jpg'), 'pending', 3, NOW(), NOW());

UPDATE requests SET
    commercial_status = 'fechado_ganho',
    closing_confirmation_status = 'confirmado',
    payment_confirmation_status = 'confirmado_proprietario',
    payment_declared_by = 6,
    payment_declared_at = DATE_SUB(NOW(), INTERVAL 10 DAY),
    payment_received_confirmed_by = 4,
    payment_received_confirmed_at = DATE_SUB(NOW(), INTERVAL 9 DAY),
    closing_declared_by = 6,
    closing_declared_at = DATE_SUB(NOW(), INTERVAL 10 DAY),
    closing_confirmed_by = 4,
    closing_confirmed_at = DATE_SUB(NOW(), INTERVAL 9 DAY)
WHERE id = 1;

UPDATE requests SET
    commercial_status = 'em_contacto',
    dispute_status = 'aberta',
    dispute_open_until = DATE_ADD(NOW(), INTERVAL 7 DAY)
WHERE id = 3;

UPDATE commissions SET
    owner_payment_status = 'aprovado',
    owner_payment_submitted_at = DATE_SUB(NOW(), INTERVAL 8 DAY),
    owner_payment_validated_by = 10,
    owner_payment_validated_at = DATE_SUB(NOW(), INTERVAL 7 DAY),
    affiliate_payout_status = 'pago',
    affiliate_payout_completed_at = DATE_SUB(NOW(), INTERVAL 3 DAY),
    paid_at = DATE_SUB(NOW(), INTERVAL 3 DAY)
WHERE id = 1;

UPDATE commissions SET
    owner_payment_status = 'enviado',
    owner_payment_submitted_at = DATE_SUB(NOW(), INTERVAL 2 DAY),
    affiliate_payout_status = 'pendente'
WHERE id = 3;

-- -----------------------------------------------------------------------------
-- Referência rápida (desenvolvimento)
-- Senha de todas as contas @imobil.com: password
--
-- | Email                  | Papel        | Estado útil                          |
-- |------------------------|--------------|--------------------------------------|
-- | admin@imobil.com       | super_admin  | Administração total                  |
-- | moderador@imobil.com   | moderador    | Moderação de imóveis e documentos    |
-- | financeiro@imobil.com  | financeiro   | Pagamentos e comissões               |
-- | suporte@imobil.com     | suporte      | Suporte operacional                  |
-- | affiliate1@imobil.com  | afiliado     | Selo aprovado, comissões pagas       |
-- | affiliate2@imobil.com  | afiliado     | Selo pendente                        |
-- | owner1@imobil.com      | proprietário | Conta de pagamento seed              |
-- | owner2@imobil.com      | proprietário | Plano premium                        |
-- | cliente1@imobil.com    | cliente      | Pedidos, chat e favoritos            |
-- | cliente2@imobil.com    | cliente      | Investidor                           |
-- | empresa@imobil.com     | PJ pendente  | Email não verificado                 |
-- -----------------------------------------------------------------------------
