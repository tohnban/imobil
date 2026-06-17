-- Setting interno para rate-limit de alertas de eliminação em atraso.
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
