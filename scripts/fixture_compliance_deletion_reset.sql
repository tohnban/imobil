-- =============================================================================
-- Reset DEV: repõe utilizadores/imóveis usados nos testes de conformidade
-- =============================================================================

SET NAMES utf8mb4;
SET @pwd_hash = '$2y$10$r2YDr7CKjYOIqv/ef10AjeNMH5KXb38CUFt328itgFkafq5fOQKhq';

-- Utilizadores seed — limpar pedidos de eliminação
UPDATE users
SET deletion_requested_at = NULL,
    deletion_scheduled_at = NULL,
    deletion_reminder_sent_at = NULL
WHERE email IN (
    'owner1@imobil.com',
    'owner2@imobil.com',
    'cliente1@imobil.com',
    'cliente2@imobil.com',
    'compliance_test_owner@imobil.local',
    'compliance_test_purge@imobil.local'
);

-- Imóveis seed — restaurar estados típicos
UPDATE properties SET status = 'disponivel', featured = 1, deletion_requested_at = NULL, deletion_scheduled_at = NULL, deletion_purged_at = NULL, deletion_previous_status = NULL, deletion_reminder_sent_at = NULL
WHERE title = 'Apartamento Centro';

UPDATE properties SET status = 'disponivel', featured = 0, deletion_requested_at = NULL, deletion_scheduled_at = NULL, deletion_purged_at = NULL, deletion_previous_status = NULL, deletion_reminder_sent_at = NULL
WHERE title = 'Terreno Benfica';

-- Imóvel de teste dedicado
UPDATE properties
SET status = 'disponivel',
    title = 'Imóvel Teste Compliance',
    description = 'Anúncio criado apenas para testes de eliminação.',
    images = '[]',
    video_url = NULL,
    featured = 0,
    deletion_requested_at = NULL,
    deletion_scheduled_at = NULL,
    deletion_purged_at = NULL,
    deletion_previous_status = NULL,
    deletion_reminder_sent_at = NULL
WHERE title IN ('Imóvel Teste Compliance', 'Imóvel removido')
  AND affiliate_id = (SELECT id FROM users WHERE email = 'compliance_test_owner@imobil.local' LIMIT 1);

DELETE FROM settings WHERE `key` = 'deletion_overdue_alert_last_sent_at';

-- Repor cliente2 se foi purgada num teste anterior (email anonymizado)
UPDATE users
SET email = 'cliente2@imobil.com',
    phone = '+244900000007',
    name = 'Filipe Investidor',
    username = 'filipe_investidor',
    document_number = '66666666666666',
    password = @pwd_hash,
    profile_photo = NULL,
    document_file = 'doc_cliente2.pdf',
    is_affiliate = 0,
    affiliate_code = NULL,
    status = 'ativo',
    suspended_until = NULL,
    deletion_requested_at = NULL,
    deletion_scheduled_at = NULL,
    deletion_reminder_sent_at = NULL
WHERE username = 'removed_7' OR email LIKE 'removed_7@%' OR email = 'cliente2@imobil.com';

SELECT 'Fixture de conformidade reposta' AS info;
