-- =============================================================================
-- Fixture DEV: cenários de eliminação por conformidade (conta + imóvel)
-- =============================================================================
-- Uso: executar numa base de desenvolvimento com dados seed (database_schema.sql).
-- Palavra-passe dos utilizadores seed: password
--
-- Depois de aplicar:
--   php scripts/test_compliance_deletion_smoke.php
--   php scripts/compliance_deletion_scheduler.php
--
-- Para repor o estado inicial dos utilizadores de teste:
--   mysql ... < scripts/fixture_compliance_deletion_reset.sql
-- =============================================================================

SET NAMES utf8mb4;
SET @pwd_hash = '$2y$10$r2YDr7CKjYOIqv/ef10AjeNMH5KXb38CUFt328itgFkafq5fOQKhq';

-- Garantir utilizadores de teste dedicados (cria se não existirem)
INSERT INTO users (
    email, email_verified_at, password, name, username, user_type, document_number, phone,
    is_affiliate, is_admin, role, status, account_plan, trust_badge_status, created_at
)
SELECT 'compliance_test_owner@imobil.local', NOW(), @pwd_hash,
       'Teste Compliance Owner', 'compliance_test_owner', 'pessoa_fisica',
       'COMPLIANCE0001', '+244900009901', 0, 0, 'utilizador', 'ativo', 'free', 'nenhum', NOW()
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'compliance_test_owner@imobil.local');

INSERT INTO users (
    email, email_verified_at, password, name, username, user_type, document_number, phone,
    is_affiliate, is_admin, role, status, account_plan, trust_badge_status, created_at
)
SELECT 'compliance_test_purge@imobil.local', NOW(), @pwd_hash,
       'Teste Compliance Purge', 'compliance_test_purge', 'pessoa_fisica',
       'COMPLIANCE0002', '+244900009902', 0, 0, 'utilizador', 'ativo', 'free', 'nenhum', NOW()
WHERE NOT EXISTS (SELECT 1 FROM users WHERE email = 'compliance_test_purge@imobil.local');

SET @owner_id = (SELECT id FROM users WHERE email = 'compliance_test_owner@imobil.local' LIMIT 1);
SET @purge_user_id = (SELECT id FROM users WHERE email = 'compliance_test_purge@imobil.local' LIMIT 1);

-- Imóvel disponível do utilizador de teste
INSERT INTO properties (
    title, description, type, purpose, price, country_id, region_id, location,
    bedrooms, bathrooms, area, images, affiliate_id, visibility, featured, status, created_at
)
SELECT
    'Imóvel Teste Compliance',
    'Anúncio criado apenas para testes de eliminação.',
    'apartamento', 'venda', 100000.00,
    (SELECT id FROM countries WHERE code = 'AO' LIMIT 1),
    (SELECT id FROM regions WHERE code = 'luanda' LIMIT 1),
    'Luanda Teste', 2, 1, 80.00, '[]', @owner_id, 'basic', 0, 'disponivel', NOW()
WHERE @owner_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM properties
      WHERE affiliate_id = @owner_id AND title = 'Imóvel Teste Compliance' AND deletion_purged_at IS NULL
  );

SET @property_id = (
    SELECT id FROM properties
    WHERE affiliate_id = @owner_id AND title = 'Imóvel Teste Compliance' AND deletion_purged_at IS NULL
    ORDER BY id DESC LIMIT 1
);

-- Token API activo (para validar revoke no purge de conta)
INSERT INTO api_tokens (user_id, token, name, scopes, status, expires_at, created_at, updated_at)
SELECT @purge_user_id,
       CONCAT('test_compliance_', REPLACE(UUID(), '-', '')),
       'Fixture compliance purge',
       'read:properties',
       'active',
       DATE_ADD(NOW(), INTERVAL 30 DAY),
       NOW(), NOW()
WHERE @purge_user_id IS NOT NULL
  AND NOT EXISTS (
      SELECT 1 FROM api_tokens WHERE user_id = @purge_user_id AND name = 'Fixture compliance purge' AND status = 'active'
  );

-- -----------------------------------------------------------------------------
-- Cenário 1 — Conta com prazo VENCIDO (deve purgar no scheduler)
-- Utilizador: compliance_test_purge@imobil.local / password
-- -----------------------------------------------------------------------------
UPDATE users
SET deletion_requested_at = DATE_SUB(NOW(), INTERVAL 65 DAY),
    deletion_scheduled_at   = DATE_SUB(NOW(), INTERVAL 1 HOUR),
    deletion_reminder_sent_at = DATE_SUB(NOW(), INTERVAL 10 DAY),
    status = 'ativo'
WHERE id = @purge_user_id;

-- -----------------------------------------------------------------------------
-- Cenário 2 — Imóvel com prazo VENCIDO (deve purgar no scheduler)
-- Proprietário: compliance_test_owner@imobil.local / password
-- -----------------------------------------------------------------------------
UPDATE properties
SET status = 'eliminado',
    featured = 0,
    deletion_previous_status = 'disponivel',
    deletion_requested_at = DATE_SUB(NOW(), INTERVAL 35 DAY),
    deletion_scheduled_at = DATE_SUB(NOW(), INTERVAL 2 HOUR),
    deletion_purged_at = NULL,
    deletion_reminder_sent_at = DATE_SUB(NOW(), INTERVAL 5 DAY)
WHERE id = @property_id;

-- -----------------------------------------------------------------------------
-- Cenário 3 — Lembrete de conta (agendado daqui a 7 dias, sem lembrete enviado)
-- Utilizador seed: owner1@imobil.com / password
-- -----------------------------------------------------------------------------
UPDATE users
SET deletion_requested_at = DATE_SUB(NOW(), INTERVAL 53 DAY),
    deletion_scheduled_at   = DATE_ADD(NOW(), INTERVAL 7 DAY),
    deletion_reminder_sent_at = NULL,
    status = 'ativo'
WHERE email = 'owner1@imobil.com'
  AND is_admin = 0
  AND role = 'utilizador';

-- Propagar imóvel da Carla com o MESMO prazo da conta (cenário conta→imóveis)
UPDATE properties p
INNER JOIN users u ON u.id = p.affiliate_id
SET p.status = 'eliminado',
    p.featured = 0,
    p.deletion_previous_status = IF(p.status IN ('disponivel','pendente','em_analise','rejeitado','vendido','alugado'), p.status, 'disponivel'),
    p.deletion_requested_at = DATE_SUB(NOW(), INTERVAL 53 DAY),
    p.deletion_scheduled_at = DATE_ADD(NOW(), INTERVAL 7 DAY),
    p.deletion_purged_at = NULL,
    p.deletion_reminder_sent_at = NULL
WHERE u.email = 'owner1@imobil.com'
  AND p.deletion_purged_at IS NULL
  AND p.status NOT IN ('eliminado')
  AND p.title = 'Terreno Benfica'
LIMIT 1;

-- -----------------------------------------------------------------------------
-- Cenário 4 — Lembrete de imóvel (agendado daqui a 7 dias)
-- Utilizador seed: owner2@imobil.com / password — imóvel «Apartamento Centro»
-- -----------------------------------------------------------------------------
UPDATE properties p
INNER JOIN users u ON u.id = p.affiliate_id
SET p.status = 'eliminado',
    p.featured = 0,
    p.deletion_previous_status = 'disponivel',
    p.deletion_requested_at = DATE_SUB(NOW(), INTERVAL 23 DAY),
    p.deletion_scheduled_at = DATE_ADD(NOW(), INTERVAL 7 DAY),
    p.deletion_purged_at = NULL,
    p.deletion_reminder_sent_at = NULL
WHERE u.email = 'owner2@imobil.com'
  AND p.title = 'Apartamento Centro'
  AND p.deletion_purged_at IS NULL
LIMIT 1;

-- -----------------------------------------------------------------------------
-- Cenário 5 — Conta vencida há vários dias (purge + validação de logs)
-- Utilizador seed: cliente2@imobil.com / password
-- Nota: após purge, a conta fica anonymizada; use fixture_compliance_deletion_reset.sql
--       e/ou reimportar seed se precisar de cliente2 de novo.
-- -----------------------------------------------------------------------------
UPDATE users
SET deletion_requested_at = DATE_SUB(NOW(), INTERVAL 70 DAY),
    deletion_scheduled_at   = DATE_SUB(NOW(), INTERVAL 3 DAY),
    deletion_reminder_sent_at = DATE_SUB(NOW(), INTERVAL 20 DAY),
    status = 'ativo'
WHERE email = 'cliente2@imobil.com'
  AND is_admin = 0
  AND role = 'utilizador';

-- Limpar deduplicação de alerta overdue para forçar notificação no próximo cron
DELETE FROM settings WHERE `key` = 'deletion_overdue_alert_last_sent_at';

-- -----------------------------------------------------------------------------
-- Resumo esperado após php scripts/compliance_deletion_scheduler.php
-- -----------------------------------------------------------------------------
SELECT 'Cenários aplicados' AS info;

SELECT 'Contas a purgar agora' AS cenario, email, deletion_scheduled_at
FROM users
WHERE deletion_requested_at IS NOT NULL
  AND deletion_scheduled_at IS NOT NULL
  AND deletion_scheduled_at <= NOW()
  AND is_admin = 0
  AND role = 'utilizador';

SELECT 'Imóveis a purgar agora' AS cenario, p.id, p.title, p.deletion_scheduled_at
FROM properties p
WHERE p.status = 'eliminado'
  AND p.deletion_purged_at IS NULL
  AND p.deletion_scheduled_at IS NOT NULL
  AND p.deletion_scheduled_at <= NOW();

SELECT 'Candidatos a lembrete (conta)' AS cenario, email, deletion_scheduled_at
FROM users
WHERE deletion_requested_at IS NOT NULL
  AND deletion_scheduled_at IS NOT NULL
  AND deletion_reminder_sent_at IS NULL
  AND deletion_scheduled_at > NOW()
  AND deletion_scheduled_at <= DATE_ADD(NOW(), INTERVAL 7 DAY);

SELECT 'Candidatos a lembrete (imóvel)' AS cenario, p.id, p.title, p.deletion_scheduled_at
FROM properties p
WHERE p.status = 'eliminado'
  AND p.deletion_purged_at IS NULL
  AND p.deletion_reminder_sent_at IS NULL
  AND p.deletion_scheduled_at IS NOT NULL
  AND p.deletion_scheduled_at > NOW()
  AND p.deletion_scheduled_at <= DATE_ADD(NOW(), INTERVAL 7 DAY);

SELECT 'Contas em atraso (alerta admin)' AS cenario, COUNT(*) AS total
FROM users
WHERE deletion_requested_at IS NOT NULL
  AND deletion_scheduled_at IS NOT NULL
  AND deletion_scheduled_at < DATE_SUB(NOW(), INTERVAL 1 DAY)
  AND is_admin = 0
  AND role = 'utilizador';
