-- Pedido de eliminação de conta com período de conformidade configurável.
ALTER TABLE users
    ADD COLUMN deletion_requested_at TIMESTAMP NULL DEFAULT NULL AFTER suspended_until,
    ADD COLUMN deletion_scheduled_at TIMESTAMP NULL DEFAULT NULL AFTER deletion_requested_at,
    ADD INDEX idx_users_deletion_scheduled (deletion_scheduled_at);

INSERT INTO settings (`key`, value, label, description)
VALUES (
    'account_deletion_grace_days',
    '60',
    'Dias de conformidade antes de eliminar conta',
    'Após o utilizador solicitar a eliminação, a conta fica inacessível durante este período. No fim, o sistema elimina a conta automaticamente.'
)
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    description = VALUES(description);
