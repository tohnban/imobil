<?php
// Determine if an edit is requested
$editingMethod = null;
$editId = isset($_GET['edit']) ? (int) $_GET['edit'] : 0;
if ($editId > 0 && !empty($methods)) {
    foreach ($methods as $m) {
        if ((int) $m['id'] === $editId) {
            $editingMethod = $m;
            break;
        }
    }
}

$fieldLabels = [
    'account_name'    => 'Titular da Conta',
    'account_number'  => 'Número de Conta',
    'iban'            => 'IBAN',
    'bank_name'       => 'Banco / Instituição',
    'wallet_provider' => 'Provedor de Carteira',
    'phone_number'    => 'Número de Telefone',
];

function renderFieldsCheckboxes(array $fieldLabels, array $current = []): void {
    foreach ($fieldLabels as $key => $label) {
        $checked = !empty($current[$key]) ? 'checked' : '';
        echo '<label class="dashboard-field-toggle">';
        echo '<input type="checkbox" name="fields[' . htmlspecialchars($key) . ']" value="1" ' . $checked . '>';
        echo ' ' . htmlspecialchars($label);
        echo '</label> ';
    }
}
?>
<?php
$dashboardPageClass = 'payment-config-admin-view';
include DIRREQ . 'app/view/partials/dashboard_page_start.php';
$heroKicker = 'Administração';
$heroTitle = 'Métodos de Pagamento';
$heroLead = 'Crie, edite ou remova os métodos disponíveis no sistema. Os utilizadores verão automaticamente os métodos ativos com público "Utilizador" ou "Ambos".';
include DIRREQ . 'app/view/partials/dashboard_view_hero.php';
?>

    <!-- ── Tabela de métodos ─────────────────────────────────── -->
    <div class="dashboard-card">
        <div class="dashboard-card-title">
            <h2>Catálogo de Métodos</h2>
        </div>

        <?php if (!empty($methods)): ?>
            <div class="dashboard-table-wrap payment-config-table-wrap">
            <table class="dashboard-table payment-config-table">
                <thead>
                    <tr>
                        <th>Nome</th>
                        <th>Código</th>
                        <th>Direção</th>
                        <th>Público</th>
                        <th>Campos activos</th>
                        <th>Estado</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($methods as $method):
                        $fields = \App\model\PaymentMethod::parseFieldsConfig($method['fields_config'] ?? null);
                        $activeFields = array_keys(array_filter($fields));
                        $directionLabel = ['incoming' => 'Entrada', 'outgoing' => 'Saída', 'both' => 'Ambos'][$method['direction']] ?? 'N/A';
                        $audienceLabel  = ['system' => 'Sistema', 'user' => 'Utilizador', 'both' => 'Ambos'][$method['audience']] ?? 'N/A';
                    ?>
                        <tr class="payment-config-row">
                            <td data-label="Nome"><?php echo htmlspecialchars($method['name']); ?></td>
                            <td data-label="Código"><code><?php echo htmlspecialchars($method['code']); ?></code></td>
                            <td data-label="Direção"><?php echo $directionLabel; ?></td>
                            <td data-label="Público"><?php echo $audienceLabel; ?></td>
                            <td class="dashboard-inline-note" data-label="Campos activos">
                                <?php
                                $labels = array_map(fn($f) => $fieldLabels[$f] ?? $f, $activeFields);
                                echo $labels ? implode(', ', array_map('htmlspecialchars', $labels)) : '—';
                                ?>
                            </td>
                            <td data-label="Estado">
                                <span class="dashboard-chip <?php echo $method['is_active'] ? 'dashboard-chip-success' : 'dashboard-chip-neutral'; ?>">
                                    <?php echo $method['is_active'] ? 'Ativo' : 'Inativo'; ?>
                                </span>
                            </td>
                            <td data-label="Ações" class="col-actions payment-config-actions">
                                <!-- Toggle -->
                                <form action="<?php echo DIRPAGE; ?>payment_methods/toggleMethod/<?php echo (int)$method['id']; ?>" method="POST" class="dashboard-inline-form payment-config-inline-form">
                                    <?php echo $csrfField; ?>
                                    <button type="submit" class="dashboard-btn dashboard-btn-small">
                                        <?php echo $method['is_active'] ? 'Desativar' : 'Ativar'; ?>
                                    </button>
                                </form>
                                <!-- Edit -->
                                <a href="<?php echo DIRPAGE; ?>payment_methods?edit=<?php echo (int)$method['id']; ?>#edit-form"
                                   class="dashboard-btn dashboard-btn-small">Editar</a>
                                <!-- Delete -->
                                <form action="<?php echo DIRPAGE; ?>payment_methods/deleteMethod/<?php echo (int)$method['id']; ?>" method="POST"
                                      class="dashboard-inline-form payment-config-inline-form"
                                                                            data-confirm="Remover este método?">
                                    <?php echo $csrfField; ?>
                                    <button type="submit" class="dashboard-btn dashboard-btn-small dashboard-btn-danger">Remover</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        <?php else: ?>
            <p class="dashboard-empty-copy">Nenhum método configurado. Adicione um abaixo.</p>
        <?php endif; ?>
    </div>

    <!-- ── Formulário: Editar método (se ?edit=id) ────────────── -->
    <?php if ($editingMethod):
        $editFields = \App\model\PaymentMethod::parseFieldsConfig($editingMethod['fields_config'] ?? null);
    ?>
    <div class="dashboard-card" id="edit-form">
        <div class="dashboard-card-title">
            <h2>Editar: <?php echo htmlspecialchars($editingMethod['name']); ?></h2>
        </div>
        <form action="<?php echo DIRPAGE; ?>payment_methods/updateMethod/<?php echo (int)$editingMethod['id']; ?>" method="POST" class="dashboard-form">
            <?php echo $csrfField; ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Nome *</label>
                    <input type="text" name="name" value="<?php echo htmlspecialchars($editingMethod['name']); ?>" required maxlength="120">
                </div>
                <div class="form-group">
                    <label>Código único *</label>
                    <input type="text" name="code" value="<?php echo htmlspecialchars($editingMethod['code']); ?>" required maxlength="50"
                           pattern="[a-z0-9_]+" title="Apenas letras minúsculas, números e _">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Direção</label>
                    <select name="direction">
                        <?php foreach (['incoming'=>'Entrada','outgoing'=>'Saída','both'=>'Ambos'] as $v => $l): ?>
                            <option value="<?php echo $v; ?>" <?php echo $editingMethod['direction'] === $v ? 'selected' : ''; ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Público</label>
                    <select name="audience">
                        <?php foreach (['system'=>'Apenas sistema','user'=>'Apenas utilizadores','both'=>'Ambos'] as $v => $l): ?>
                            <option value="<?php echo $v; ?>" <?php echo $editingMethod['audience'] === $v ? 'selected' : ''; ?>><?php echo $l; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Campos visíveis no formulário do utilizador</label>
                <div class="dashboard-fields-grid">
                    <?php renderFieldsCheckboxes($fieldLabels, $editFields); ?>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group form-group-checkbox">
                    <label><input type="checkbox" name="requires_reference" value="1" <?php echo $editingMethod['requires_reference'] ? 'checked' : ''; ?>>
                    Requer referência de pagamento</label>
                </div>
                <div class="form-group form-group-checkbox">
                    <label><input type="checkbox" name="is_active" value="1" <?php echo $editingMethod['is_active'] ? 'checked' : ''; ?>>
                    Método ativo</label>
                </div>
            </div>
            <div style="display:flex;gap:1rem">
                <button type="submit" class="dashboard-btn dashboard-btn-primary">Guardar alterações</button>
                <a href="<?php echo DIRPAGE; ?>payment_methods" class="dashboard-btn">Cancelar</a>
            </div>
        </form>
    </div>
    <?php endif; ?>

    <!-- ── Formulário: Adicionar novo método ──────────────────── -->
    <div class="dashboard-card" id="add-form">
        <div class="dashboard-card-title">
            <h2>Adicionar Novo Método</h2>
        </div>
        <form action="<?php echo DIRPAGE; ?>payment_methods/createMethod" method="POST" class="dashboard-form">
            <?php echo $csrfField; ?>
            <div class="form-row">
                <div class="form-group">
                    <label>Nome *</label>
                    <input type="text" name="name" required maxlength="120" placeholder="Ex: PayPal">
                </div>
                <div class="form-group">
                    <label>Código único * <small>(letras minúsculas, números, _)</small></label>
                    <input type="text" name="code" required maxlength="50" placeholder="Ex: paypal"
                           pattern="[a-z0-9_]+" title="Apenas letras minúsculas, números e _">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group">
                    <label>Direção</label>
                    <select name="direction">
                        <option value="both">Ambos (entrada e saída)</option>
                        <option value="incoming">Apenas entrada</option>
                        <option value="outgoing">Apenas saída</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Público</label>
                    <select name="audience">
                        <option value="both">Ambos (sistema e utilizadores)</option>
                        <option value="user">Apenas utilizadores</option>
                        <option value="system">Apenas sistema</option>
                    </select>
                </div>
            </div>
            <div class="form-group">
                <label>Campos visíveis no formulário do utilizador</label>
                <div class="dashboard-fields-grid">
                    <?php renderFieldsCheckboxes($fieldLabels); ?>
                </div>
            </div>
            <div class="form-row">
                <div class="form-group form-group-checkbox">
                    <label><input type="checkbox" name="requires_reference" value="1">
                    Requer referência de pagamento</label>
                </div>
                <div class="form-group form-group-checkbox">
                    <label><input type="checkbox" name="is_active" value="1" checked>
                    Método ativo</label>
                </div>
            </div>
            <button type="submit" class="dashboard-btn dashboard-btn-primary">Criar Método</button>
        </form>
    </div>
<?php include DIRREQ . 'app/view/partials/dashboard_page_end.php'; ?>
