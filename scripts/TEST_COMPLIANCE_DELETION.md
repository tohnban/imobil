# Guia de teste — eliminação por conformidade

Ambiente de desenvolvimento apenas. Não executar em produção.

## Pré-requisitos

1. Migrações aplicadas:
   - `scripts/migration_20260616_account_deletion.sql`
   - `scripts/migration_20260616_property_deletion.sql`
   - `scripts/migration_20260616_compliance_deletion_hardening.sql`
2. Dados seed (`database_schema.sql`) ou base equivalente com utilizadores `*@imobil.com`.
3. Palavra-passe dos utilizadores seed: **`password`**

## Aplicar cenários SQL

```bash
mysql -u root -p NOME_DA_BASE < scripts/fixture_compliance_deletion_test.sql
```

Isto cria/atualiza:

| Cenário | Utilizador | O que testa |
|--------|------------|-------------|
| 1 | `compliance_test_purge@imobil.local` | Conta com prazo vencido → purge automático |
| 2 | `compliance_test_owner@imobil.local` | Imóvel «Imóvel Teste Compliance» vencido → purge + documentos/media |
| 3 | `owner1@imobil.com` | Lembrete de conta em 7 dias + imóvel propagado com mesmo prazo |
| 4 | `owner2@imobil.com` | Lembrete de imóvel «Apartamento Centro» em 7 dias |
| 5 | `cliente2@imobil.com` | Conta vencida há 3 dias — **também é purgada** no mesmo cron (útil para validar purge + logs) |

## Executar o scheduler

```bash
php scripts/test_compliance_deletion_smoke.php
php scripts/compliance_deletion_scheduler.php
```

**Resultado esperado** (com fixture aplicada):

- `Accounts purged: 2` (compliance_test_purge + cliente2)
- `Properties purged: 1` (Imóvel Teste Compliance)
- `Account reminders sent: 1` (owner1, se mailer activo)
- `Property reminders sent: 2` (Terreno Benfica + Apartamento Centro, se mailer activo)
- `Overdue accounts: 0` após purge bem-sucedido

> O alerta admin de «eliminações em atraso» só dispara se, **após** o cron, ainda existirem contas/imóveis vencidos não purgados (ex.: purge falhou). Para inspecionar candidatos antes do cron, use os `SELECT` no final do ficheiro SQL.

> Se o mailer estiver desactivado, lembretes contam `0` mas o scheduler continua válido.

## Testes manuais na UI

### Eliminar imóvel

1. Login: `owner2@imobil.com` / `password`
2. Ir a **Dashboard → Minhas Propriedades**
3. Escolher imóvel disponível → marcar checkbox → palavra-passe → **Eliminar imóvel**
4. Verificar: filtro «A eliminar», KPI, badge «Eliminado» no chat
5. Página pública do imóvel: indisponível; chat em negociação: ainda visível

### Eliminar conta

1. Login: `cliente1@imobil.com` / `password`
2. **Perfil → Eliminar conta** (checkbox + palavra-passe)
3. Redireccionamento para **Estado da eliminação**
4. Verificar acesso limitado; imóveis do utilizador (se existirem) propagados

### Cancelar (utilizador)

1. Na página de estado → cancelar com palavra-passe
2. Imóveis propagados devem voltar ao estado anterior

### Cancelar (admin)

1. Login admin: `admin@imobil.com` / `password`
2. **Moderar utilizadores → Acessos → filtro «A eliminar»**
3. Cancelar pedido de um utilizador em eliminação
4. Confirmar imóveis restaurados (log `properties_restored`)

### Purge admin imediato

1. Admin → utilizador com pedido activo → **Eliminar agora**
2. Conta anonymizada; tokens API revogados; documentos removidos

## Verificações SQL úteis

```sql
-- Contas pendentes de eliminação
SELECT id, email, deletion_requested_at, deletion_scheduled_at
FROM users
WHERE deletion_requested_at IS NOT NULL;

-- Imóveis em período de conformidade
SELECT id, title, status, deletion_scheduled_at, deletion_purged_at
FROM properties
WHERE status = 'eliminado' AND deletion_purged_at IS NULL;

-- Simular prazo vencido manualmente (um imóvel)
UPDATE properties
SET deletion_scheduled_at = DATE_SUB(NOW(), INTERVAL 1 HOUR)
WHERE id = 1 AND status = 'eliminado';
```

## Repor estado

```bash
mysql -u root -p NOME_DA_BASE < scripts/fixture_compliance_deletion_reset.sql
```

## Cron recomendado (produção)

```cron
0 3 * * * cd /caminho/para/htdocs && php scripts/compliance_deletion_scheduler.php >> storage/logs/compliance_deletion.log 2>&1
```

Os scripts `account_deletion_scheduler.php` e `property_deletion_scheduler.php` reencaminham para o scheduler unificado (legado).
