-- Pedido de eliminação de imóvel com período de conformidade configurável.
ALTER TABLE properties
    MODIFY COLUMN status ENUM('pendente', 'em_analise', 'disponivel', 'vendido', 'alugado', 'rejeitado', 'eliminado') DEFAULT 'pendente',
    ADD COLUMN deletion_requested_at TIMESTAMP NULL DEFAULT NULL AFTER status,
    ADD COLUMN deletion_scheduled_at TIMESTAMP NULL DEFAULT NULL AFTER deletion_requested_at,
    ADD COLUMN deletion_purged_at TIMESTAMP NULL DEFAULT NULL AFTER deletion_scheduled_at,
    ADD COLUMN deletion_previous_status VARCHAR(32) NULL DEFAULT NULL AFTER deletion_purged_at,
    ADD INDEX idx_properties_deletion_scheduled (deletion_scheduled_at);

INSERT INTO settings (`key`, value, label, description)
VALUES (
    'property_deletion_grace_days',
    '30',
    'Dias de conformidade antes de eliminar imóvel',
    'Após o proprietário solicitar a eliminação, o imóvel fica indisponível ao público durante este período. Continua visível em conversas e negociações com estado «eliminado». No fim, o sistema remove o anúncio do portfólio.'
)
ON DUPLICATE KEY UPDATE
    label = VALUES(label),
    description = VALUES(description);
