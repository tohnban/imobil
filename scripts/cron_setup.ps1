<#
.SYNOPSIS
    Regista as tarefas agendadas do Imobil no Windows Task Scheduler.
    Execute como Administrador:  .\scripts\cron_setup.ps1
#>

param(
    [string]$PhpPath  = "C:\xampp\php\php.exe",
    [string]$RootDir  = "C:\xampp\htdocs"
)

function Register-ImobilTask {
    param(
        [string]$TaskName,
        [string]$ScriptPath,
        [string]$Description,
        [string]$RepeatInterval   # PT5M, PT1H, etc.
    )

    $action  = New-ScheduledTaskAction -Execute $PhpPath -Argument "`"$ScriptPath`""
    $trigger = New-ScheduledTaskTrigger -RepetitionInterval ([TimeSpan]::Parse($RepeatInterval.Replace('PT','').Replace('M',' min').Replace('H',' hr'))) -Once -At (Get-Date)
    $trigger = New-ScheduledTaskTrigger -Daily -At "00:00"
    $settings = New-ScheduledTaskSettingsSet -ExecutionTimeLimit (New-TimeSpan -Minutes 5) -MultipleInstances IgnoreNew

    # Trigger repetitivo a partir da meia-noite
    $repeat = New-ScheduledTaskTrigger -RepetitionInterval ([System.Xml.XmlConvert]::ToTimeSpan($RepeatInterval)) -Once -At (Get-Date "00:00")

    if (Get-ScheduledTask -TaskName $TaskName -ErrorAction SilentlyContinue) {
        Unregister-ScheduledTask -TaskName $TaskName -Confirm:$false
        Write-Host "Tarefa '$TaskName' removida (reinstalando)."
    }

    Register-ScheduledTask `
        -TaskName    $TaskName `
        -Action      $action `
        -Trigger     $repeat `
        -Settings    $settings `
        -Description $Description `
        -RunLevel    Highest `
        -Force | Out-Null

    Write-Host "OK  $TaskName  (a cada $RepeatInterval)" -ForegroundColor Green
}

Write-Host "`n=== Imobil – Configuracao de Tarefas Agendadas ===" -ForegroundColor Cyan

# SLA Alerts + auto-expire de solicitações (a cada 1 hora)
Register-ImobilTask `
    -TaskName     "Imobil_SLA_Scheduler" `
    -ScriptPath   "$RootDir\scripts\requests_sla_scheduler.php" `
    -Description  "Alertas SLA e expiração automática de solicitações" `
    -RepeatInterval "PT1H"

# Comissões do sistema (a cada 6 horas)
Register-ImobilTask `
    -TaskName     "Imobil_Commission_Scheduler" `
    -ScriptPath   "$RootDir\scripts\commission_scheduler.php" `
    -Description  "Regista receita de comissões do sistema e marca vencidas" `
    -RepeatInterval "PT6H"

# Expiração automática de destaque (a cada 1 hora)
Register-ImobilTask `
    -TaskName     "Imobil_Boost_Expiration_Scheduler" `
    -ScriptPath   "$RootDir\scripts\boost_expiration_scheduler.php" `
    -Description  "Expira boosts vencidos e remove destaque quando aplicável" `
    -RepeatInterval "PT1H"

# Renovação de planos e downgrades automáticos (a cada 1 hora)
Register-ImobilTask `
    -TaskName     "Imobil_Subscription_Scheduler" `
    -ScriptPath   "$RootDir\scripts\subscription_scheduler.php" `
    -Description  "Renovação de subscrições e downgrade por expiração" `
    -RepeatInterval "PT1H"

# Compliance deletion (a cada 1 hora)
Register-ImobilTask `
    -TaskName     "Imobil_Compliance_Deletion_Scheduler" `
    -ScriptPath   "$RootDir\scripts\compliance_deletion_scheduler.php" `
    -Description  "Purge e lembretes de eliminacao por conformidade" `
    -RepeatInterval "PT1H"

# Worker de fila de emails (a cada 5 minutos)
Register-ImobilTask `
    -TaskName     "Imobil_Mail_Queue_Worker" `
    -ScriptPath   "$RootDir\scripts\mail_queue_worker.php" `
    -Description  "Processa a fila assíncrona de emails" `
    -RepeatInterval "PT5M"

# Workers opcionais (recomendado em produção com volume de imagens/alertas)
Register-ImobilTask `
    -TaskName     "Imobil_Image_Queue_Worker" `
    -ScriptPath   "$RootDir\scripts\image_queue_worker.php" `
    -Description  "Processamento assíncrono de imagens de imóveis" `
    -RepeatInterval "PT5M"

Register-ImobilTask `
    -TaskName     "Imobil_Notify_New_Property_Worker" `
    -ScriptPath   "$RootDir\scripts\notify_new_property_worker.php" `
    -Description  "Alertas de novos imóveis para pesquisas guardadas" `
    -RepeatInterval "PT5M"

Register-ImobilTask `
    -TaskName     "Imobil_Report_Queue_Worker" `
    -ScriptPath   "$RootDir\scripts\report_queue_worker.php" `
    -Description  "Relatórios pesados em background" `
    -RepeatInterval "PT15M"

Write-Host "`nTarefas registadas com sucesso.`n" -ForegroundColor Cyan
