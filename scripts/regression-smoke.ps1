param(
    [string]$BaseUrl = 'http://localhost',
    [switch]$RunHttp
)

$ErrorActionPreference = 'Stop'

$repoRoot = Split-Path -Parent $PSScriptRoot
Set-Location $repoRoot

if (-not (Test-Path "$repoRoot/src/vendor/autoload.php")) {
    Write-Host '[bootstrap] src/vendor ausente — a executar composer install...'
    & pwsh -File "$repoRoot/scripts/composer-install.ps1" -Dev
    if ($LASTEXITCODE -ne 0) {
        throw 'composer install falhou.'
    }
}

$failures = @()
$phpCmd = $null

function Add-Failure {
    param([string]$Message)
    $script:failures += $Message
}

function Join-Url {
    param(
        [string]$Base,
        [string]$Path
    )

    return ($Base.TrimEnd('/') + '/' + $Path.TrimStart('/'))
}

function Assert-Contains {
    param(
        [string]$Path,
        [string]$Pattern,
        [string]$Description
    )

    if (-not (Test-Path $Path)) {
        Add-Failure "Arquivo nao encontrado: ${Path} ($Description)"
        return
    }

    $match = Select-String -Path $Path -Pattern $Pattern -SimpleMatch
    if (-not $match) {
        Add-Failure "Nao encontrado em ${Path}: $Description"
    }
}

function Get-CsrfTokenFromHtml {
    param([string]$Html)

    if ([string]::IsNullOrWhiteSpace($Html)) {
        return $null
    }

    $regexA = [regex]::Match($Html, 'name=["'']csrf_token["''][^>]*value=["'']([^"'']+)["'']', [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)
    if ($regexA.Success) {
        return $regexA.Groups[1].Value
    }

    $regexB = [regex]::Match($Html, 'value=["'']([^"'']+)["''][^>]*name=["'']csrf_token["'']', [System.Text.RegularExpressions.RegexOptions]::IgnoreCase)
    if ($regexB.Success) {
        return $regexB.Groups[1].Value
    }

    return $null
}

function Read-HttpResponseContent {
    param([System.Net.HttpWebResponse]$Response)

    if ($null -eq $Response) {
        return ''
    }

    try {
        $stream = $Response.GetResponseStream()
        if ($null -eq $stream) {
            return ''
        }
        $reader = New-Object System.IO.StreamReader($stream)
        return [string]$reader.ReadToEnd()
    } catch {
        return ''
    }
}

function Invoke-HttpScenario {
    param(
        [string]$Method,
        [string]$Url,
        [Microsoft.PowerShell.Commands.WebRequestSession]$Session,
        [hashtable]$Form = @{}
    )

    $iwrCommand = Get-Command Invoke-WebRequest
    $supportsSkip = $iwrCommand.Parameters.ContainsKey('SkipHttpErrorCheck')
    $supportsBasicParsing = $iwrCommand.Parameters.ContainsKey('UseBasicParsing')
    $supportsTimeout = $iwrCommand.Parameters.ContainsKey('TimeoutSec')

    try {
        $invokeParams = @{
            Uri = $Url
            Method = $Method
            WebSession = $Session
            MaximumRedirection = 0
        }

        # PS 5.1 without UseBasicParsing can hang on HTML parsing (MSHTML/IE host).
        if ($supportsBasicParsing) {
            $invokeParams['UseBasicParsing'] = $true
        }

        if ($supportsTimeout) {
            $invokeParams['TimeoutSec'] = 30
        }

        if ($supportsSkip) {
            $invokeParams['SkipHttpErrorCheck'] = $true
        }

        # PS 5.1 without SkipHttpErrorCheck: Stop turns 3xx into InvalidOperationException.
        $invokeParams['ErrorAction'] = 'SilentlyContinue'

        if ($Method -ne 'GET' -and $Form.Count -gt 0) {
            if ($iwrCommand.Parameters.ContainsKey('Form')) {
                $invokeParams['Form'] = $Form
            } else {
                $pairs = foreach ($key in $Form.Keys) {
                    '{0}={1}' -f [System.Uri]::EscapeDataString([string]$key), [System.Uri]::EscapeDataString([string]$Form[$key])
                }
                $invokeParams['Body'] = ($pairs -join '&')
                $invokeParams['ContentType'] = 'application/x-www-form-urlencoded'
            }
        }

        $response = Invoke-WebRequest @invokeParams
        return [pscustomobject]@{
            Success = $true
            StatusCode = [int]$response.StatusCode
            Location = [string]($response.Headers['Location'])
            Content = [string]$response.Content
            Error = ''
        }
    } catch {
        $httpResponse = $null
        if ($_.Exception -is [System.Net.WebException]) {
            $httpResponse = $_.Exception.Response
        }

        if ($httpResponse -is [System.Net.HttpWebResponse]) {
            $statusCode = [int]$httpResponse.StatusCode
            $location = ''
            try {
                $location = [string]$httpResponse.Headers['Location']
            } catch {
                $location = ''
            }

            $content = ''
            if ($statusCode -eq 200) {
                $content = Read-HttpResponseContent -Response $httpResponse
            }

            try {
                $httpResponse.Close()
            } catch {
            }

            return [pscustomobject]@{
                Success = $true
                StatusCode = $statusCode
                Location = $location
                Content = $content
                Error = ''
            }
        }

        return [pscustomobject]@{
            Success = $false
            StatusCode = 0
            Location = ''
            Content = ''
            Error = $_.Exception.Message
        }
    }
}

function Assert-HttpStatus {
    param(
        [pscustomobject]$Result,
        [int[]]$AllowedStatus,
        [string]$Description
    )

    if (-not $Result.Success) {
        Add-Failure "HTTP falhou em $Description :: $($Result.Error)"
        return
    }

    if ($AllowedStatus -notcontains $Result.StatusCode) {
        Add-Failure "Status inesperado em $Description :: recebido=$($Result.StatusCode), esperado=$($AllowedStatus -join ',')"
    }
}

function Assert-HttpLocationContains {
    param(
        [pscustomobject]$Result,
        [string]$Expected,
        [string]$Description
    )

    if (-not $Result.Success) {
        return
    }

    if ([string]::IsNullOrWhiteSpace($Result.Location)) {
        Add-Failure "Sem cabecalho Location em $Description"
        return
    }

    if ($Result.Location -notlike "*$Expected*") {
        Add-Failure "Redirect inesperado em $Description :: Location=$($Result.Location), esperado conter=$Expected"
    }
}

Write-Host "[1/4] Lint PHP da base ativa..."
$phpOnPath = Get-Command php -ErrorAction SilentlyContinue
if ($phpOnPath) {
    $phpCmd = $phpOnPath.Source
} elseif (Test-Path 'C:\xampp\php\php.exe') {
    $phpCmd = 'C:\xampp\php\php.exe'
} else {
    Add-Failure 'PHP nao encontrado no PATH e fallback C:\xampp\php\php.exe inexistente.'
}

$phpFiles = Get-ChildItem -Path "$repoRoot/app", "$repoRoot/src", "$repoRoot/public", "$repoRoot/config" -Recurse -File -Filter *.php |
    Where-Object { $_.FullName -notmatch '[\\/]vendor[\\/]' }

if ($phpCmd) {
    foreach ($file in $phpFiles) {
        $result = & $phpCmd -l $file.FullName 2>&1
        if ($LASTEXITCODE -ne 0) {
            Add-Failure "Lint falhou: $($file.FullName) :: $result"
        }
    }

    $auditOutput = & $phpCmd "$repoRoot/scripts/audit-legacy-routes.php" 2>&1
    if ($LASTEXITCODE -ne 0) {
        Add-Failure "Auditoria legacy/declarativa falhou :: $auditOutput"
    }

    $viewAuditOutput = & $phpCmd "$repoRoot/scripts/audit-view-routes.php" 2>&1
    if ($LASTEXITCODE -ne 0) {
        Add-Failure "Auditoria de links em views falhou :: $viewAuditOutput"
    }

    $importAuditOutput = & $phpCmd "$repoRoot/scripts/audit-controller-imports.php" 2>&1
    if ($LASTEXITCODE -ne 0) {
        Add-Failure "Auditoria de imports em controllers falhou :: $importAuditOutput"
    }

    $classAuditOutput = & $phpCmd "$repoRoot/scripts/audit-controller-classes.php" 2>&1
    if ($LASTEXITCODE -ne 0) {
        Add-Failure "Auditoria de classes de controllers falhou :: $classAuditOutput"
    }

    $routeShadowOutput = & $phpCmd "$repoRoot/scripts/audit-route-shadows.php" 2>&1
    if ($LASTEXITCODE -ne 0) {
        Add-Failure "Auditoria de sombras de rotas falhou :: $routeShadowOutput"
    }

    $duplicateRouteOutput = & $phpCmd "$repoRoot/scripts/audit-duplicate-routes.php" 2>&1
    if ($LASTEXITCODE -ne 0) {
        Add-Failure "Auditoria de rotas duplicadas falhou :: $duplicateRouteOutput"
    }
}

Write-Host "[2/4] Regras criticas em controllers..."
Assert-Contains -Path "$repoRoot/public/index.php" -Pattern 'ErrorHandler::register()' -Description 'handler global de erros registado'
Assert-Contains -Path "$repoRoot/config/config.php" -Pattern "define('LOG_CHANNEL'" -Description 'LOG_CHANNEL configuravel'
Assert-Contains -Path "$repoRoot/.gitignore" -Pattern 'src/vendor/' -Description 'src/vendor ignorado pelo git'
Assert-Contains -Path "$repoRoot/app/controller/ControllerRequestWorkflow.php" -Pattern 'public function updateStatus($id, $status = null)' -Description 'updateStatus com status opcional'
$statusFallbackPattern = '$status = $_POST[' + [char]39 + 'status' + [char]39 + '] ?? $status;'
Assert-Contains -Path "$repoRoot/app/controller/ControllerRequestWorkflow.php" -Pattern $statusFallbackPattern -Description 'fallback de status via POST'
Assert-Contains -Path "$repoRoot/app/Dispatch.php" -Pattern 'enforceLegacyAuthenticatedCsrf' -Description 'CSRF global em POST legacy autenticado'
Assert-Contains -Path "$repoRoot/src/classes/ClassCsrf.php" -Pattern 'enforcePostToken' -Description 'helper central de CSRF em POST'
Assert-Contains -Path "$repoRoot/config/routes.php" -Pattern "controller' => 'ControllerDashboardHome'" -Description 'rota GET do painel principal'
Assert-Contains -Path "$repoRoot/app/controller/ControllerDashboard.php" -Pattern 'class ControllerDashboard' -Description 'facade legacy do dashboard'
Assert-Contains -Path "$repoRoot/app/controller/ControllerPayment.php" -Pattern 'ControllerPaymentMethods' -Description 'facade legacy de pagamentos'
Assert-Contains -Path "$repoRoot/config/routes.php" -Pattern "controller' => 'ControllerPaymentTransactions'" -Description 'rotas declarativas de transacoes'
Assert-Contains -Path "$repoRoot/app/controller/ControllerProperty.php" -Pattern 'ControllerPropertyCatalog' -Description 'facade legacy de imoveis'
Assert-Contains -Path "$repoRoot/config/routes.php" -Pattern "controller' => 'ControllerPropertyCatalog'" -Description 'rotas de catalogo de imoveis'
Assert-Contains -Path "$repoRoot/app/controller/ControllerPropertyCatalog.php" -Pattern 'normalizePropertyListFilters' -Description 'whitelist de filtros em properties'
Assert-Contains -Path "$repoRoot/app/controller/ControllerRequest.php" -Pattern 'ControllerRequestWorkflow' -Description 'facade legacy de pedidos'
Assert-Contains -Path "$repoRoot/app/controller/RequestControllerSupport.php" -Pattern 'trait RequestControllerSupport' -Description 'helpers partilhados de pedidos'
Assert-Contains -Path "$repoRoot/config/routes.php" -Pattern "controller' => 'ControllerNotificationInbox'" -Description 'rotas GET de notificacoes'
Assert-Contains -Path "$repoRoot/app/controller/ControllerAuth.php" -Pattern 'ControllerAuthSession' -Description 'facade legacy de autenticacao'
Assert-Contains -Path "$repoRoot/config/routes.php" -Pattern "controller' => 'ControllerAuthPages'" -Description 'rotas GET de login e registo'
Assert-Contains -Path "$repoRoot/app/controller/ControllerHome.php" -Pattern 'public function index()' -Description 'home com action index'
Assert-Contains -Path "$repoRoot/config/routes.php" -Pattern "controller' => 'ControllerLegal'" -Description 'rotas declarativas de paginas legais'
Assert-Contains -Path "$repoRoot/config/routes.php" -Pattern "controller' => 'ControllerSitemap'" -Description 'rotas declarativas de sitemap'
Assert-Contains -Path "$repoRoot/src/classes/RouteRegistry.php" -Pattern "#^`$#i" -Description 'RouteRegistry suporta path raiz'
Assert-Contains -Path "$repoRoot/app/controller/ControllerApi.php" -Pattern 'ControllerApiPages' -Description 'facade legacy da API'
Assert-Contains -Path "$repoRoot/app/controller/ApiControllerSupport.php" -Pattern 'trait ApiControllerSupport' -Description 'helpers partilhados da API'
Assert-Contains -Path "$repoRoot/config/routes.php" -Pattern "controller' => 'ControllerApiV1Properties'" -Description 'rotas declarativas API v1 properties'
Assert-Contains -Path "$repoRoot/config/routes.php" -Pattern "path' => 'moderate_users'" -Description 'alias legacy moderate_users'
Assert-Contains -Path "$repoRoot/src/classes/ClassRoutes.php" -Pattern 'LEGACY_SEGMENT_CONTROLLERS' -Description 'ClassRoutes reduzido a segmentos dinamicos'

Write-Host "[3/4] Regras criticas em views/rotas..."
Assert-Contains -Path "$repoRoot/app/view/partials/request_feed_item.php" -Pattern 'request/updateStatus/' -Description 'formulario de update status (partial)'
Assert-Contains -Path "$repoRoot/app/view/partials/request_feed_item.php" -Pattern 'method="POST"' -Description 'request actions via POST (partial)'
Assert-Contains -Path "$repoRoot/app/view/partials/request_feed_item.php" -Pattern 'ClassCsrf::field()' -Description 'csrf em request actions (partial)'
Assert-Contains -Path "$repoRoot/app/view/property/moderate/Main.php" -Pattern 'property/approve/' -Description 'acao de aprovar imovel'
Assert-Contains -Path "$repoRoot/app/view/property/moderate/Main.php" -Pattern 'property/reject/' -Description 'acao de rejeitar imovel'
Assert-Contains -Path "$repoRoot/app/view/property/moderate/Main.php" -Pattern 'ClassCsrf::field()' -Description 'csrf na moderacao de imovel'
Assert-Contains -Path "$repoRoot/config/routes.php" -Pattern "path' => 'dashboard/moderate_users'" -Description 'rota snake_case de moderacao no painel'

# CSP must not allow unsafe-inline scripts.
$cspLine = (Select-String -Path "$repoRoot/public/index.php" -Pattern "Content-Security-Policy" -SimpleMatch | Select-Object -First 1).Line
if ($cspLine -and ($cspLine -match 'script-src' -and $cspLine -match 'unsafe-inline')) {
    Add-Failure 'CSP contem unsafe-inline em script-src - remova essa diretiva'
}
if ($cspLine -and -not ($cspLine -match "script-src")) {
    Add-Failure "CSP nao define script-src em public/index.php"
}

# No inline <script> blocks (without src=) allowed in views — JSON-LD is exempt.
$inlineScriptFiles = Get-ChildItem -Path "$repoRoot/app/view" -Recurse -Filter '*.php' |
    Select-String -Pattern '<script\b(?![^>]*\bsrc=)(?![^>]*type\s*=\s*["'']application/ld\+json["''])' -AllMatches |
    Select-Object -ExpandProperty Path -Unique
if ($inlineScriptFiles) {
    # Layout.php contains a tiny inline bootstrap assignment (window.IMOBIL_DIRCSS) which is acceptable.
    $inlineScriptFiles = $inlineScriptFiles | Where-Object { $_ -notlike '*\app\view\Layout.php' }
    foreach ($f in $inlineScriptFiles) {
        Add-Failure "Bloco <script> inline encontrado em: $f"
    }
}

# No inline event-handler attributes (onclick=, onchange=, etc.) in views.
$inlineHandlerFiles = Get-ChildItem -Path "$repoRoot/app/view" -Recurse -Filter '*.php' |
    Select-String -Pattern '\bon[a-z]+\s*=' -AllMatches |
    Select-Object -ExpandProperty Path -Unique
if ($inlineHandlerFiles) {
    foreach ($f in $inlineHandlerFiles) {
        Add-Failure "Atributo de evento inline encontrado em: $f (use data-* + script.js)"
    }
}

Write-Host "[4/4] Cenarios HTTP locais..."
if ($RunHttp) {
    $session = New-Object Microsoft.PowerShell.Commands.WebRequestSession

    $loginPage = Invoke-HttpScenario -Method 'GET' -Url (Join-Url -Base $BaseUrl -Path 'login') -Session $session
    Assert-HttpStatus -Result $loginPage -AllowedStatus @(200) -Description 'GET /login'

    $csrfToken = $null
    if ($loginPage.Success -and $loginPage.StatusCode -eq 200) {
        $csrfToken = Get-CsrfTokenFromHtml -Html $loginPage.Content
        if ([string]::IsNullOrWhiteSpace($csrfToken)) {
            Add-Failure 'Nao foi possivel extrair csrf_token de /login'
        }
    }

    if (-not [string]::IsNullOrWhiteSpace($csrfToken)) {
        $authPost = Invoke-HttpScenario -Method 'POST' -Url (Join-Url -Base $BaseUrl -Path 'authenticate') -Session $session -Form @{
            csrf_token = $csrfToken
            login = 'smoke.invalid@example.com'
            password = 'invalid-password'
        }
        Assert-HttpStatus -Result $authPost -AllowedStatus @(302) -Description 'POST /authenticate (credencial invalida)'
        Assert-HttpLocationContains -Result $authPost -Expected 'login' -Description 'POST /authenticate (credencial invalida)'
    }

    $updateStatusPost = Invoke-HttpScenario -Method 'POST' -Url (Join-Url -Base $BaseUrl -Path 'request/updateStatus/1') -Session $session -Form @{
        csrf_token = 'invalid'
        status = 'analise'
    }
    Assert-HttpStatus -Result $updateStatusPost -AllowedStatus @(302) -Description 'POST /request/updateStatus/1 (sem auth)'
    Assert-HttpLocationContains -Result $updateStatusPost -Expected 'login' -Description 'POST /request/updateStatus/1 (sem auth)'

    $approvePost = Invoke-HttpScenario -Method 'POST' -Url (Join-Url -Base $BaseUrl -Path 'property/approve/1') -Session $session -Form @{ csrf_token = 'invalid' }
    Assert-HttpStatus -Result $approvePost -AllowedStatus @(302) -Description 'POST /property/approve/1 (sem auth)'
    Assert-HttpLocationContains -Result $approvePost -Expected 'login' -Description 'POST /property/approve/1 (sem auth)'

    $rejectPost = Invoke-HttpScenario -Method 'POST' -Url (Join-Url -Base $BaseUrl -Path 'property/reject/1') -Session $session -Form @{ csrf_token = 'invalid' }
    Assert-HttpStatus -Result $rejectPost -AllowedStatus @(302) -Description 'POST /property/reject/1 (sem auth)'
    Assert-HttpLocationContains -Result $rejectPost -Expected 'login' -Description 'POST /property/reject/1 (sem auth)'

    # Register page must return 200 (publicly accessible).
    $homePage = Invoke-HttpScenario -Method 'GET' -Url ($BaseUrl.TrimEnd('/')) -Session $session
    Assert-HttpStatus -Result $homePage -AllowedStatus @(200) -Description 'GET / (pagina inicial)'

    $cookiesPage = Invoke-HttpScenario -Method 'GET' -Url (Join-Url -Base $BaseUrl -Path 'cookies') -Session $session
    Assert-HttpStatus -Result $cookiesPage -AllowedStatus @(200) -Description 'GET /cookies (pagina legal)'

    $apiHealth = Invoke-HttpScenario -Method 'GET' -Url (Join-Url -Base $BaseUrl -Path 'api/health') -Session $session
    Assert-HttpStatus -Result $apiHealth -AllowedStatus @(200) -Description 'GET /api/health'

    $apiV1 = Invoke-HttpScenario -Method 'GET' -Url (Join-Url -Base $BaseUrl -Path 'api/v1') -Session $session
    Assert-HttpStatus -Result $apiV1 -AllowedStatus @(401) -Description 'GET /api/v1 (sem token)'

    $registerPage = Invoke-HttpScenario -Method 'GET' -Url (Join-Url -Base $BaseUrl -Path 'register') -Session $session
    Assert-HttpStatus -Result $registerPage -AllowedStatus @(200) -Description 'GET /register (pagina publica)'

    $propertiesPage = Invoke-HttpScenario -Method 'GET' -Url (Join-Url -Base $BaseUrl -Path 'properties') -Session $session
    Assert-HttpStatus -Result $propertiesPage -AllowedStatus @(200) -Description 'GET /properties (catalogo publico)'

    $recoverPage = Invoke-HttpScenario -Method 'GET' -Url (Join-Url -Base $BaseUrl -Path 'recover') -Session $session
    Assert-HttpStatus -Result $recoverPage -AllowedStatus @(200) -Description 'GET /recover (pagina publica)'

    $verifyPage = Invoke-HttpScenario -Method 'GET' -Url (Join-Url -Base $BaseUrl -Path 'verify') -Session $session
    Assert-HttpStatus -Result $verifyPage -AllowedStatus @(200) -Description 'GET /verify (pagina publica)'

    $dashboardGet = Invoke-HttpScenario -Method 'GET' -Url (Join-Url -Base $BaseUrl -Path 'dashboard') -Session $session
    Assert-HttpStatus -Result $dashboardGet -AllowedStatus @(302) -Description 'GET /dashboard (sem auth)'
    Assert-HttpLocationContains -Result $dashboardGet -Expected 'login' -Description 'GET /dashboard (sem auth)'

    $recoverCsrf = $null
    if ($recoverPage.Success -and $recoverPage.StatusCode -eq 200) {
        $recoverCsrf = Get-CsrfTokenFromHtml -Html $recoverPage.Content
    }
    if (-not [string]::IsNullOrWhiteSpace($recoverCsrf)) {
        $recoverPost = Invoke-HttpScenario -Method 'POST' -Url (Join-Url -Base $BaseUrl -Path 'recover') -Session $session -Form @{
            csrf_token = $recoverCsrf
            email = 'smoke.invalid@example.com'
        }
        Assert-HttpStatus -Result $recoverPost -AllowedStatus @(302) -Description 'POST /recover (email generico)'
        Assert-HttpLocationContains -Result $recoverPost -Expected 'recover' -Description 'POST /recover (email generico)'
    }
} else {
    Write-Host ' - HTTP smoke desativado (use -RunHttp -BaseUrl http://localhost)'
}

if ($failures.Count -gt 0) {
    Write-Host "`nSMOKE CHECK: FALHOU" -ForegroundColor Red
    $failures | ForEach-Object { Write-Host " - $_" -ForegroundColor Red }
    exit 1
}

Write-Host "`nSMOKE CHECK: OK" -ForegroundColor Green
exit 0
