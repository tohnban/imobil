-- Reforço do modelo de eliminação: lembretes e setting de aviso prévio.
ALTER TABLE users
    ADD COLUMN deletion_reminder_sent_at TIMESTAMP NULL DEFAULT NULL AFTER deletion_scheduled_at;

ALTER TABLE properties
    ADD COLUMN deletion_reminder_sent_at TIMESTAMP NULL DEFAULT NULL AFTER deletion_scheduled_at;

INSERT INTO settings (`key`, value, label, description)
VALUES (
    'deletion_reminder_days_before',
    '7',
    'Aviso antes da eliminação (dias)',
    'Quantos dias antes da data agendada enviar email de lembrete ao utilizador (conta ou imóvel).'
)
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    description = VALUES(description);

INSERT INTO settings (`key`, value, label, description)
VALUES (
    'deletion_overdue_alert_last_sent_at',
    '',
    'Último alerta de eliminação em atraso',
    'Timestamp interno do último email de alerta a admins sobre eliminações em atraso (rate-limit).'
)
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    description = VALUES(description);
