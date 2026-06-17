<?php
$editingChannel = null;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
if ($editId > 0 && !empty($channels)) {
    foreach ($channels as $channel) {
        if ((int) ($channel['id'] ?? 0) === $editId) {
            $editingChannel = $channel;
            break;
        }
    }
}
?>

<?php
$dashboardPageClass = 'payment-config-admin-view';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
$heroKicker = 'Administração';
$heroTitle = 'Canais do Sistema';
$heroLead = 'Gira contas e canais usados pelo sistema para recebimentos e pagamentos.';
include DIRREQ . 'app/view/partials/dashboard_view_hero.php';
?>

    <div class="dashboard-card">
        <div class="dashboard-card-title">
            <h2>Selecionar Método</h2>
        </div>
        <form method="GET" action="<?php echo DIRPAGE; ?>payment_channels" class="dashboard-form">
            <div class="form-row">
                <div class="form-group">
                    <label for="method_id">Método</label>
                    <select name="method_id" id="method_id" required>
                        <?php foreach ($methods as $method): ?>
                            <option value="<?php echo (int) $method['id']; ?>" <?php echo (int) $selectedMethodId === (int) $method['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($method['name']); ?> (<?php echo htmlspecialchars($method['code']); ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <button type="submit" class="dashboard-btn dashboard-btn-small">Carregar canais</button>
        </form>
    </div>

    <?php if ($selectedMethodId > 0): ?>
        <div class="dashboard-card">
            <div class="dashboard-card-title">
                <h2>Canais de <?php echo htmlspecialchars($selectedMethod['name'] ?? 'Método selecionado'); ?></h2>
            </div>

            <?php if (!empty($channels)): ?>
                <div class="dashboard-table-wrap payment-config-table-wrap">
                <table class="dashboard-table payment-config-table">
                    <thead>
                        <tr>
                            <th>Canal</th>
                            <th>Conta</th>
                            <th>Dados</th>
                            <th>Padrão</th>
                            <th>Estado</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($channels as $channel): ?>
                            <tr class="payment-config-row">
                                <td data-label="Canal"><?php echo htmlspecialchars($channel['channel_name'] ?? 'N/A'); ?></td>
                                <td data-label="Conta"><?php echo htmlspecialchars($channel['account_name'] ?? 'N/A'); ?></td>
                                <td class="dashboard-inline-note" data-label="Dados">
                                    <?php
                                    $parts = [];
                                    if (!empty($channel['account_number'])) {
                                        $parts[] = 'Conta: ' . substr((string) $channel['account_number'], -6);
                                    }
                                    if (!empty($channel['iban'])) {
                                        $parts[] = 'IBAN: ' . substr((string) $channel['iban'], -8);
                                    }
                                    if (!empty($channel['wallet_provider'])) {
                                        $parts[] = 'Carteira: ' . $channel['wallet_provider'];
                                    }
                                    if (!empty($channel['bank_name'])) {
                                        $parts[] = 'Banco: ' . $channel['bank_name'];
                                    }
                                    echo !empty($parts) ? htmlspecialchars(implode(' | ', $parts)) : 'N/A';
                                    ?>
                                </td>
                                <td data-label="Padrão" class="col-default">
                                    <?php if (!empty($channel['is_default'])): ?>
                                        <span class="dashboard-chip dashboard-chip-success">Padrão</span>
                                    <?php else: ?>
                                        <form action="<?php echo DIRPAGE; ?>payment_channels/setDefaultChannel/<?php echo (int) $channel['id']; ?>" method="POST" class="dashboard-inline-form payment-config-inline-form">
                                            <?php echo $csrfField; ?>
                                            <button type="submit" class="dashboard-btn dashboard-btn-small">Definir padrão</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Estado">
                                    <span class="dashboard-chip <?php echo !empty($channel['is_active']) ? 'dashboard-chip-success' : 'dashboard-chip-neutral'; ?>">
                                        <?php echo !empty($channel['is_active']) ? 'Ativo' : 'Inativo'; ?>
                                    </span>
                                </td>
                                <td data-label="Ações" class="col-actions payment-config-actions">
                                    <a href="<?php echo DIRPAGE; ?>payment_channels?method_id=<?php echo (int) $selectedMethodId; ?>&edit=<?php echo (int) $channel['id']; ?>#channel-edit" class="dashboard-btn dashboard-btn-small">Editar</a>
                                    <?php if (!empty($channel['is_active'])): ?>
                                        <form action="<?php echo DIRPAGE; ?>payment_channels/deactivateChannel/<?php echo (int) $channel['id']; ?>" method="POST" class="dashboard-inline-form payment-config-inline-form" data-confirm="Desativar este canal?">
                                            <?php echo $csrfField; ?>
                                            <button type="submit" class="dashboard-btn dashboard-btn-small dashboard-btn-danger">Desativar</button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php else: ?>
                <p class="dashboard-empty-copy">Nenhum canal registado para este método.</p>
            <?php endif; ?>
        </div>

        <?php if ($editingChannel): ?>
            <div class="dashboard-card" id="channel-edit">
                <div class="dashboard-card-title">
                    <h2>Editar Canal</h2>
                </div>

                <form action="<?php echo DIRPAGE; ?>payment_channels/updateChannel/<?php echo (int) $editingChannel['id']; ?>" method="POST" class="dashboard-form">
                    <?php echo $csrfField; ?>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Nome do canal *</label>
                            <input type="text" name="channel_name" maxlength="150" required value="<?php echo htmlspecialchars($editingChannel['channel_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Nome da conta</label>
                            <input type="text" name="account_name" maxlength="150" value="<?php echo htmlspecialchars($editingChannel['account_name'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Número da conta</label>
                            <input type="text" name="account_number" maxlength="120" value="<?php echo htmlspecialchars($editingChannel['account_number'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>IBAN</label>
                            <input type="text" name="iban" maxlength="80" value="<?php echo htmlspecialchars($editingChannel['iban'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Banco</label>
                            <input type="text" name="bank_name" maxlength="120" value="<?php echo htmlspecialchars($editingChannel['bank_name'] ?? ''); ?>">
                        </div>
                        <div class="form-group">
                            <label>Provedor de carteira</label>
                            <input type="text" name="wallet_provider" maxlength="80" value="<?php echo htmlspecialchars($editingChannel['wallet_provider'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Instruções de pagamento</label>
                        <textarea name="instructions" rows="3" maxlength="500"><?php echo htmlspecialchars($editingChannel['instructions'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group form-group-checkbox">
                            <label><input type="checkbox" name="is_default" value="1" <?php echo !empty($editingChannel['is_default']) ? 'checked' : ''; ?>> Canal padrão</label>
                        </div>
                        <div class="form-group form-group-checkbox">
                            <label><input type="checkbox" name="is_active" value="1" <?php echo !empty($editingChannel['is_active']) ? 'checked' : ''; ?>> Canal ativo</label>
                        </div>
                    </div>

                    <button type="submit" class="dashboard-btn dashboard-btn-primary">Guardar alterações</button>
                    <a href="<?php echo DIRPAGE; ?>payment_channels?method_id=<?php echo (int) $selectedMethodId; ?>" class="dashboard-btn">Cancelar</a>
                </form>
            </div>
        <?php endif; ?>

        <div class="dashboard-card" id="channel-add">
            <div class="dashboard-card-title">
                <h2>Novo Canal</h2>
            </div>

            <form action="<?php echo DIRPAGE; ?>payment_channels/createChannel" method="POST" class="dashboard-form">
                <?php echo $csrfField; ?>
                <input type="hidden" name="method_id" value="<?php echo (int) $selectedMethodId; ?>">

                <div class="form-row">
                    <div class="form-group">
                        <label>Nome do canal *</label>
                        <input type="text" name="channel_name" maxlength="150" required placeholder="Ex: Conta principal banco X">
                    </div>
                    <div class="form-group">
                        <label>Nome da conta</label>
                        <input type="text" name="account_name" maxlength="150" placeholder="Ex: IMOBIL LDA">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Número da conta</label>
                        <input type="text" name="account_number" maxlength="120">
                    </div>
                    <div class="form-group">
                        <label>IBAN</label>
                        <input type="text" name="iban" maxlength="80">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Banco</label>
                        <input type="text" name="bank_name" maxlength="120">
                    </div>
                    <div class="form-group">
                        <label>Provedor de carteira</label>
                        <input type="text" name="wallet_provider" maxlength="80">
                    </div>
                </div>

                <div class="form-group">
                    <label>Instruções de pagamento</label>
                    <textarea name="instructions" rows="3" maxlength="500" placeholder="Detalhes para reconciliação e pagamento."></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group form-group-checkbox">
                        <label><input type="checkbox" name="is_default" value="1"> Definir como padrão</label>
                    </div>
                    <div class="form-group form-group-checkbox">
                        <label><input type="checkbox" name="is_active" value="1" checked> Canal ativo</label>
                    </div>
                </div>

                <button type="submit" class="dashboard-btn dashboard-btn-primary">Criar canal</button>
            </form>
        </div>
    <?php endif; ?>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
