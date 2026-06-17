<?php
$dashboardPageClass = 'payment-accounts-dashboard-view';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
$heroKicker = 'Pagamentos';
$heroTitle = 'Meus Dados de Pagamento';
$heroLead = 'Adicione e gira as suas contas para receber pagamentos.';
include DIRREQ . 'app/view/partials/dashboard_view_hero.php';
?>

    <div class="dashboard-card payment-accounts-list-card">
        <div class="dashboard-card-title">
            <h2>Minhas Contas de Pagamento</h2>
        </div>

        <?php if (!empty($accounts)): ?>
            <div class="dashboard-table-wrap payment-accounts-table-wrap">
            <table class="dashboard-table payment-accounts-table">
                <thead>
                    <tr>
                        <th>Rótulo</th>
                        <th>Método</th>
                        <th>Detalhes</th>
                        <th>Padrão</th>
                        <th>Estado</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($accounts as $account): ?>
                        <tr class="payment-accounts-row">
                            <td data-label="Rótulo"><?php echo htmlspecialchars($account['account_label'] ?? 'Sem rótulo'); ?></td>
                            <td data-label="Método"><?php echo htmlspecialchars($account['method_name']); ?></td>
                            <td class="dashboard-inline-note" data-label="Detalhes">
                                <?php
                                $details = [];
                                if (!empty($account['account_number'])) $details[] = 'Conta: ' . substr($account['account_number'], -4);
                                if (!empty($account['iban'])) $details[] = 'IBAN: ' . substr($account['iban'], -6);
                                if (!empty($account['phone_number'])) $details[] = 'Tel: ' . $account['phone_number'];
                                echo implode(' | ', $details) ?: 'N/A';
                                ?>
                            </td>
                            <td data-label="Padrão" class="col-default">
                                <?php if ($account['is_default']): ?>
                                    <span class="dashboard-chip dashboard-chip-success">Padrão</span>
                                <?php else: ?>
                                    <form action="<?php echo DIRPAGE; ?>dashboard/setDefaultPaymentAccount/<?php echo (int)$account['id']; ?>" method="POST" class="dashboard-inline-form payment-accounts-inline-form">
                                        <?php echo $csrfField; ?>
                                        <button type="submit" class="dashboard-btn dashboard-btn-link">Usar como conta principal</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                            <td data-label="Estado">
                                <span class="dashboard-chip <?php echo $account['is_active'] ? 'dashboard-chip-success' : 'dashboard-chip-neutral'; ?>">
                                    <?php echo $account['is_active'] ? 'Ativa' : 'Inativa'; ?>
                                </span>
                            </td>
                            <td data-label="Ações" class="col-actions">
                                <?php if ($account['is_active']): ?>
                                    <form action="<?php echo DIRPAGE; ?>dashboard/deactivatePaymentAccount/<?php echo (int)$account['id']; ?>" method="POST" class="dashboard-inline-form payment-accounts-inline-form">
                                        <?php echo $csrfField; ?>
                                        <button type="submit" class="dashboard-btn dashboard-btn-small dashboard-btn-danger" data-confirm="Desativar esta conta?">Desativar</button>
                                    </form>
                                <?php else: ?>
                                    <span class="dashboard-inline-note">–</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php else: ?>
            <p class="dashboard-empty-copy">Nenhuma conta de pagamento configurada. Adicione uma abaixo.</p>
        <?php endif; ?>
    </div>

    <div class="dashboard-card payment-accounts-form-card">
        <div class="dashboard-card-title">
            <h2>Adicionar Nova Conta</h2>
        </div>

        <form action="<?php echo DIRPAGE; ?>dashboard/addPaymentAccount" method="POST" class="dashboard-form payment-accounts-form">
            <?php echo $csrfField; ?>

            <div class="form-group">
                <label for="method_id">Método de Pagamento *</label>
                <select name="method_id" id="method_id" required>
                    <option value="">-- Selecione --</option>
                    <?php foreach ($methods as $method):
                        $fields = \App\model\PaymentMethod::parseFieldsConfig($method['fields_config'] ?? null);
                    ?>
                        <option value="<?php echo (int)$method['id']; ?>"
                                data-account_name="<?php echo !empty($fields['account_name']) ? '1' : '0'; ?>"
                                data-account_number="<?php echo !empty($fields['account_number']) ? '1' : '0'; ?>"
                                data-iban="<?php echo !empty($fields['iban']) ? '1' : '0'; ?>"
                                data-bank_name="<?php echo !empty($fields['bank_name']) ? '1' : '0'; ?>"
                                data-wallet_provider="<?php echo !empty($fields['wallet_provider']) ? '1' : '0'; ?>"
                                data-phone_number="<?php echo !empty($fields['phone_number']) ? '1' : '0'; ?>">
                            <?php echo htmlspecialchars($method['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="account_label">Rótulo (ex: Minha Conta Principal)</label>
                <input type="text" name="account_label" id="account_label" maxlength="120" placeholder="Opcional">
            </div>

            <div id="dynamic-payment-fields" class="payment-fields-container">
                <p class="dashboard-inline-note">Selecione um método para carregar os campos correspondentes.</p>
            </div>

            <div class="form-group form-group-checkbox payment-accounts-default-choice">
                <label class="payment-accounts-checkbox-label" for="payment_account_is_default">
                    <input type="checkbox" name="is_default" id="payment_account_is_default" value="1">
                    <span>Usar como conta principal para receber pagamentos</span>
                </label>
            </div>

            <button type="submit" class="dashboard-btn dashboard-btn-primary">Adicionar Conta</button>
        </form>
    </div>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
