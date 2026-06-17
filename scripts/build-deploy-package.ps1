<#
.SYNOPSIS
    Gera a pasta deploy/ com apenas os ficheiros essenciais para o servidor.
.EXAMPLE
    pwsh -File scripts/build-deploy-package.ps1
    pwsh -File scripts/build-deploy-package.ps1 -OutputDir C:\temp\imobil-deploy
#>
param(
    [string]$OutputDir = '',
    [string]$PhpPath = '',
    [switch]$SkipComposer
)

$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
if ($OutputDir -eq '') {
    $OutputDir = Join-Path $repoRoot 'deploy'
}

$runtimeScripts = @(
    'requests_sla_scheduler.php',
    'commission_scheduler.php',
    'compliance_deletion_scheduler.php',
    'account_deletion_scheduler.php',
    'property_deletion_scheduler.php',
    'boost_expiration_scheduler.php',
    'subscription_scheduler.php',
    'mail_queue_worker.php',
    'image_queue_worker.php',
    'notify_new_property_worker.php',
    'report_queue_worker.php',
    'ensure-storage-dirs.php',
    'production-check.php',
    '.htaccess'
)

function Copy-TreeFiltered {
    param(
        [string]$Source,
        [string]$Destination,
        [string[]]$ExcludeDirNames = @(),
        [string[]]$ExcludeFilePatterns = @()
    )

    if (-not (Test-Path $Source)) {
        return
    }

    Get-ChildItem -Path $Source -Force | ForEach-Object {
        $name = $_.Name
        if ($ExcludeDirNames -contains $name) {
            return
        }
        foreach ($pattern in $ExcludeFilePatterns) {
            if ($name -like $pattern) {
                return
            }
        }

        $target = Join-Path $Destination $name
        if ($_.PSIsContainer) {
            New-Item -ItemType Directory -Path $target -Force | Out-Null
            Copy-TreeFiltered -Source $_.FullName -Destination $target -ExcludeDirNames $ExcludeDirNames -ExcludeFilePatterns $ExcludeFilePatterns
        } else {
            $parent = Split-Path $target -Parent
            if (-not (Test-Path $parent)) {
                New-Item -ItemType Directory -Path $parent -Force | Out-Null
            }
            Copy-Item -Path $_.FullName -Destination $target -Force
        }
    }
}

Write-Host "=== Imobil - Pacote de deploy ===" -ForegroundColor Cyan
Write-Host "Origem:  $repoRoot"
Write-Host "Destino: $OutputDir"

if (Test-Path $OutputDir) {
    Remove-Item -Recurse -Force $OutputDir
}
New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null

# Nucleo da aplicacao
foreach ($dir in @('app', 'config', 'public')) {
    Write-Host "Copiar $dir/ ..."
    Copy-TreeFiltered -Source (Join-Path $repoRoot $dir) -Destination (Join-Path $OutputDir $dir)
}

# src sem vendor (reinstalado com --no-dev)
Write-Host 'Copiar src/ (sem vendor) ...'
Copy-TreeFiltered -Source (Join-Path $repoRoot 'src') -Destination (Join-Path $OutputDir 'src') -ExcludeDirNames @('vendor')

# Storage: estrutura e proteccao, sem uploads locais
Write-Host 'Copiar storage/ (estrutura) ...'
$storageDirs = @(
    'storage/cache',
    'storage/logs',
    'storage/documents',
    'storage/uploads/profiles'
)
foreach ($rel in $storageDirs) {
    $dest = Join-Path $OutputDir ($rel -replace '/', [IO.Path]::DirectorySeparatorChar)
    New-Item -ItemType Directory -Path $dest -Force | Out-Null
}
foreach ($ht in @('storage/cache/.htaccess', 'storage/logs/.htaccess', 'storage/documents/.htaccess', 'storage/logs/.gitkeep', 'storage/cache/.gitkeep')) {
    $src = Join-Path $repoRoot ($ht -replace '/', [IO.Path]::DirectorySeparatorChar)
    if (Test-Path $src) {
        $dest = Join-Path $OutputDir ($ht -replace '/', [IO.Path]::DirectorySeparatorChar)
        $destDir = Split-Path $dest -Parent
        if (-not (Test-Path $destDir)) { New-Item -ItemType Directory -Path $destDir -Force | Out-Null }
        Copy-Item $src $dest -Force
    }
}

# public/storage/uploads com .htaccess
$uploadsHt = Join-Path $repoRoot 'public/storage/uploads/.htaccess'
if (Test-Path $uploadsHt) {
    $uploadsDest = Join-Path $OutputDir 'public/storage/uploads'
    New-Item -ItemType Directory -Path $uploadsDest -Force | Out-Null
    Copy-Item $uploadsHt (Join-Path $uploadsDest '.htaccess') -Force
}

# Scripts de runtime (schedulers/workers)
Write-Host 'Copiar scripts de runtime ...'
$scriptsDest = Join-Path $OutputDir 'scripts'
New-Item -ItemType Directory -Path $scriptsDest -Force | Out-Null
foreach ($script in $runtimeScripts) {
    $src = Join-Path $repoRoot "scripts/$script"
    if (Test-Path $src) {
        Copy-Item $src (Join-Path $scriptsDest $script) -Force
    }
}

# Raiz
Copy-Item (Join-Path $repoRoot 'index.php') (Join-Path $OutputDir 'index.php') -Force
Copy-Item (Join-Path $repoRoot '.htaccess') (Join-Path $OutputDir '.htaccess') -Force
Copy-Item (Join-Path $repoRoot '.env.production.example') (Join-Path $OutputDir '.env.production.example') -Force

# Composer producao
if (-not $SkipComposer) {
    if ($PhpPath -eq '') {
        $phpCmd = Get-Command php -ErrorAction SilentlyContinue
        if ($phpCmd) { $PhpPath = $phpCmd.Source }
        elseif (Test-Path 'C:\xampp\php\php.exe') { $PhpPath = 'C:\xampp\php\php.exe' }
    }
    if ($PhpPath -and (Test-Path (Join-Path $OutputDir 'src/composer.json'))) {
        Write-Host 'composer install --no-dev ...'
        Push-Location (Join-Path $OutputDir 'src')
        if (Test-Path 'composer.phar') {
            & $PhpPath composer.phar install --no-dev --optimize-autoloader --no-interaction
        } else {
            & composer install --no-dev --optimize-autoloader --no-interaction
        }
        if ($LASTEXITCODE -ne 0) { Pop-Location; throw 'composer install falhou no pacote de deploy.' }
        Pop-Location
    }
}

# LEIA-ME no pacote
$readme = @"
IMOBIL - PACOTE DE PRODUCAO
===========================

1. Envie TODO o conteudo desta pasta para o servidor (public_html ou htdocs).
2. Copie .env.production.example para .env e preencha DB + SMTP + APP_URL.
3. No servidor: php scripts/ensure-storage-dirs.php
4. Registe cron (exemplo Linux / Hostinger):
   0 * * * * php /caminho/scripts/requests_sla_scheduler.php
   0 */6 * * * php /caminho/scripts/commission_scheduler.php
   0 * * * * php /caminho/scripts/compliance_deletion_scheduler.php
   0 * * * * php /caminho/scripts/boost_expiration_scheduler.php
   0 * * * * php /caminho/scripts/subscription_scheduler.php
   */5 * * * * php /caminho/scripts/mail_queue_worker.php
   (opcional) */5 * * * * php /caminho/scripts/image_queue_worker.php
   (opcional) */5 * * * * php /caminho/scripts/notify_new_property_worker.php
   (opcional) */15 * * * * php /caminho/scripts/report_queue_worker.php
   Nota: account_deletion_scheduler.php e property_deletion_scheduler.php
   são wrappers legados; use apenas compliance_deletion_scheduler.php.
5. Aplique migracoes SQL pendentes (phpMyAdmin), se ainda nao aplicadas no servidor.
6. Permissoes de escrita em storage/ e public/storage/uploads/
7. Base de dados vazia: no PC de dev execute
   pwsh -File scripts/reset-database.ps1 -EmptyOnly
   Depois exporte/importe no MySQL da Hostinger (phpMyAdmin).
8. Primeiro Admin Total: criar manualmente na BD (seed) ou, apos login,
   em dashboard/moderateUsers?tab=equipa criar outros administradores.
9. Validar: php scripts/production-check.php e php scripts/commission_scheduler.php

NAO enviar para o servidor (ficam apenas no ambiente de desenvolvimento):
- Documentacao (*.md), .github/, phpstan, php-cs-fixer
- Scripts de teste, seed, auditoria e migracoes SQL
- .env local (criar novo no servidor)
"@
Set-Content -Path (Join-Path $OutputDir 'LEIA-ME-DEPLOY.txt') -Value $readme -Encoding UTF8

$fileCount = (Get-ChildItem -Path $OutputDir -Recurse -File).Count
$sizeMb = [math]::Round(((Get-ChildItem -Path $OutputDir -Recurse -File | Measure-Object -Property Length -Sum).Sum / 1MB), 2)

Write-Host ''
Write-Host "Pacote criado: $OutputDir" -ForegroundColor Green
Write-Host "Ficheiros: $fileCount | Tamanho: ${sizeMb} MB"
Write-Host 'Envie a pasta deploy/ para a Hostinger (FTP ou Gestor de Ficheiros).'
