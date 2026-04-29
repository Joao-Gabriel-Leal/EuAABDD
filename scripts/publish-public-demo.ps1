$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$project = Split-Path -Parent $PSScriptRoot
$stateDir = Join-Path $project 'storage\logs'
$statePath = Join-Path $stateDir 'public-demo-state.json'
$sharePath = Join-Path $stateDir 'public-demo-share.txt'
$phpStdOutLog = Join-Path $stateDir 'public-demo-php.out.log'
$phpStdErrLog = Join-Path $stateDir 'public-demo-php.err.log'
$cloudflaredStdOutLog = Join-Path $stateDir 'public-demo-cloudflared.out.log'
$cloudflaredStdErrLog = Join-Path $stateDir 'public-demo-cloudflared.err.log'
$cloudflaredConfig = Join-Path $stateDir 'public-demo-cloudflared-empty.yml'
$pgCtl = 'C:\Program Files\PostgreSQL\18\bin\pg_ctl.exe'
$pgIsReady = 'C:\Program Files\PostgreSQL\18\bin\pg_isready.exe'
$pgData = Join-Path $project '.local-pgsql'
$pgLog = Join-Path $pgData 'server.log'
$phpHost = '127.0.0.1'
$phpPort = 8000
$localUrl = "http://${phpHost}:${phpPort}"
$publicUrl = $null
$postgresStartedByLauncher = $false
$temporaryServer = $null
$activeServer = $null
$cloudflaredProcess = $null

function Quote-PowerShellString {
    param([Parameter(Mandatory = $true)][string] $Value)

    return "'" + $Value.Replace("'", "''") + "'"
}

function Require-Command {
    param([Parameter(Mandatory = $true)][string] $Name)

    if (-not (Get-Command $Name -ErrorAction SilentlyContinue)) {
        throw "Comando obrigatorio nao encontrado: $Name"
    }
}

function Clear-FileIfExists {
    param([Parameter(Mandatory = $true)][string] $Path)

    if (Test-Path $Path) {
        Remove-Item -LiteralPath $Path -Force
    }
}

function Stop-TrackedProcess {
    param([Nullable[int]] $ProcessId)

    if (-not $ProcessId) {
        return
    }

    $process = Get-Process -Id $ProcessId -ErrorAction SilentlyContinue
    if ($process) {
        Stop-Process -Id $ProcessId -Force -ErrorAction SilentlyContinue
        $process.WaitForExit(5000) | Out-Null
    }
}

function Stop-PhpServerState {
    param($ServerState)

    if (-not $ServerState) {
        return
    }

    Stop-TrackedProcess -ProcessId $ServerState.LauncherPid
    Stop-TrackedProcess -ProcessId $ServerState.WorkerPid
}

function Get-PortOwnerPid {
    param([Parameter(Mandatory = $true)][int] $Port)

    $connection = Get-NetTCPConnection -State Listen -LocalPort $Port -ErrorAction SilentlyContinue |
        Select-Object -First 1

    if ($connection) {
        return [int] $connection.OwningProcess
    }

    return $null
}

function Wait-ForPortFree {
    param(
        [Parameter(Mandatory = $true)][int] $Port,
        [int] $TimeoutSeconds = 20
    )

    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)

    while ((Get-Date) -lt $deadline) {
        if (-not (Get-PortOwnerPid -Port $Port)) {
            return
        }

        Start-Sleep -Milliseconds 500
    }

    throw "A porta $Port nao foi liberada dentro de ${TimeoutSeconds}s."
}

function Wait-ForPortOwner {
    param(
        [Parameter(Mandatory = $true)][int] $Port,
        [int] $TimeoutSeconds = 45
    )

    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)

    while ((Get-Date) -lt $deadline) {
        $ownerProcessId = Get-PortOwnerPid -Port $Port
        if ($ownerProcessId) {
            return $ownerProcessId
        }

        Start-Sleep -Milliseconds 500
    }

    throw "A porta $Port nao ficou disponivel dentro de ${TimeoutSeconds}s."
}

function Resolve-CompatibleExistingServer {
    param([Parameter(Mandatory = $true)][int] $WorkerPid)

    $process = Get-CimInstance Win32_Process -Filter "ProcessId = $WorkerPid" -ErrorAction SilentlyContinue
    if (-not $process) {
        return $null
    }

    $commandLine = [string] $process.CommandLine
    if (
        $process.Name -ne 'php.exe' -or
        $commandLine -notlike "*$project*" -or
        $commandLine -notlike '*server.php*'
    ) {
        return $null
    }

    return [pscustomobject]@{
        LauncherPid = if ($process.ParentProcessId -gt 0) { [int] $process.ParentProcessId } else { $null }
        WorkerPid = [int] $process.ProcessId
    }
}

function Wait-ForHttpOk {
    param(
        [Parameter(Mandatory = $true)][string] $Url,
        [int] $TimeoutSeconds = 45
    )

    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)

    while ((Get-Date) -lt $deadline) {
        try {
            $response = Invoke-WebRequest -Uri $Url -UseBasicParsing -TimeoutSec 5
            if ($response.StatusCode -ge 200 -and $response.StatusCode -lt 400) {
                return $response
            }
        } catch {
        }

        Start-Sleep -Milliseconds 750
    }

    throw "A URL $Url nao respondeu com sucesso dentro de ${TimeoutSeconds}s."
}

function Test-PostgresReady {
    if (-not (Test-Path $pgIsReady)) {
        throw "Nao foi encontrado o pg_isready em $pgIsReady"
    }

    $originalPassword = [Environment]::GetEnvironmentVariable('PGPASSWORD', 'Process')

    try {
        [Environment]::SetEnvironmentVariable('PGPASSWORD', 'aabb_demo_2026', 'Process')
        & $pgIsReady -h 127.0.0.1 -p 55432 -d aabb_brasilia -U aabb_app *> $null
        return ($LASTEXITCODE -eq 0)
    } finally {
        [Environment]::SetEnvironmentVariable('PGPASSWORD', $originalPassword, 'Process')
    }
}

function Ensure-PostgresReady {
    if (Test-PostgresReady) {
        return $false
    }

    if (-not (Test-Path $pgCtl)) {
        throw "Nao foi encontrado o pg_ctl em $pgCtl"
    }

    if (-not (Test-Path $pgData)) {
        throw "Nao foi encontrado o cluster local em $pgData"
    }

    & $pgCtl -D $pgData -l $pgLog start | Out-Null

    $deadline = (Get-Date).AddSeconds(30)
    while ((Get-Date) -lt $deadline) {
        if (Test-PostgresReady) {
            return $true
        }

        Start-Sleep -Milliseconds 500
    }

    throw 'O PostgreSQL local nao ficou pronto apos a tentativa de inicializacao.'
}

function Start-PhpServer {
    param(
        [Parameter(Mandatory = $true)][hashtable] $EnvironmentOverrides,
        [Parameter(Mandatory = $true)][string] $StdOutLog,
        [Parameter(Mandatory = $true)][string] $StdErrLog
    )

    $originalValues = @{}

    foreach ($entry in $EnvironmentOverrides.GetEnumerator()) {
        $originalValues[$entry.Key] = [Environment]::GetEnvironmentVariable($entry.Key, 'Process')
        [Environment]::SetEnvironmentVariable($entry.Key, [string] $entry.Value, 'Process')
    }

    try {
        $process = Start-Process -FilePath 'php' `
            -ArgumentList @('artisan', 'serve', "--host=$phpHost", "--port=$phpPort") `
            -WorkingDirectory $project `
            -WindowStyle Hidden `
            -RedirectStandardOutput $StdOutLog `
            -RedirectStandardError $StdErrLog `
            -PassThru
    } finally {
        foreach ($entry in $originalValues.GetEnumerator()) {
            [Environment]::SetEnvironmentVariable($entry.Key, $entry.Value, 'Process')
        }
    }

    Wait-ForHttpOk -Url "$localUrl/up" -TimeoutSeconds 45 | Out-Null
    $workerPid = Wait-ForPortOwner -Port $phpPort -TimeoutSeconds 15

    return [pscustomobject]@{
        LauncherPid = [int] $process.Id
        WorkerPid = [int] $workerPid
    }
}

function Wait-ForTryCloudflareUrl {
    param(
        [Parameter(Mandatory = $true)][string[]] $LogPaths,
        [int] $TimeoutSeconds = 60
    )

    $pattern = 'https://[a-z0-9-]+\.trycloudflare\.com'
    $deadline = (Get-Date).AddSeconds($TimeoutSeconds)

    while ((Get-Date) -lt $deadline) {
        foreach ($logPath in $LogPaths) {
            if (-not (Test-Path $logPath)) {
                continue
            }

            $match = Select-String -Path $logPath -Pattern $pattern -AllMatches -ErrorAction SilentlyContinue |
                Select-Object -Last 1

            if ($match -and $match.Matches.Count -gt 0) {
                return $match.Matches[$match.Matches.Count - 1].Value
            }
        }

        Start-Sleep -Milliseconds 750
    }

    throw 'Nao foi possivel capturar a URL publica do TryCloudflare dentro do tempo esperado.'
}

function Write-StateFile {
    param(
        [Parameter(Mandatory = $true)][string] $Url,
        [Parameter(Mandatory = $true)][pscustomobject] $ServerState,
        [Parameter(Mandatory = $true)][System.Diagnostics.Process] $TunnelProcess,
        [Parameter(Mandatory = $true)][bool] $PostgresStarted
    )

    $state = [ordered]@{
        active = $true
        launchedAt = (Get-Date).ToString('o')
        localUrl = $localUrl
        publicUrl = $Url
        postgresStartedByLauncher = $PostgresStarted
        processes = [ordered]@{
            artisanServePid = $ServerState.LauncherPid
            httpWorkerPid = $ServerState.WorkerPid
            cloudflaredPid = [int] $TunnelProcess.Id
        }
        artifacts = [ordered]@{
            sharePath = $sharePath
            phpStdOutLog = $phpStdOutLog
            phpStdErrLog = $phpStdErrLog
            cloudflaredStdOutLog = $cloudflaredStdOutLog
            cloudflaredStdErrLog = $cloudflaredStdErrLog
        }
    }

    $state | ConvertTo-Json -Depth 5 | Set-Content -LiteralPath $statePath -Encoding UTF8
}

function Write-ShareFile {
    param([Parameter(Mandatory = $true)][string] $Url)

    $share = @(
        'AABB Brasilia - validacao publica'
        ''
        "Link principal: $Url"
        "Login: $Url/login"
        "Portal do associado: $Url/portal"
        "Painel da equipe: $Url/equipe"
        ''
        'Logins demo'
        '- Associado: associado@aabb.demo / aabb2026'
        '- Equipe: equipe@aabb.demo / aabb2026'
        '- Financeiro: financeiro@aabb.demo / aabb2026'
        '- Secretaria: secretaria@aabb.demo / aabb2026'
        '- Portaria: portaria@aabb.demo / aabb2026'
        ''
        'Observacoes'
        '- A URL e temporaria e muda a cada nova publicacao.'
        '- Sua maquina precisa ficar ligada e conectada enquanto a validacao estiver acontecendo.'
        '- Para encerrar tudo, rode: .\scripts\stop-public-demo.ps1'
    )

    $share | Set-Content -LiteralPath $sharePath -Encoding UTF8
}

function Invoke-TrackedCleanup {
    if ($temporaryServer) {
        Stop-PhpServerState -ServerState $temporaryServer
    }

    if ($activeServer) {
        Stop-PhpServerState -ServerState $activeServer
    }

    if ($cloudflaredProcess) {
        Stop-TrackedProcess -ProcessId $cloudflaredProcess.Id
    }

    if ($postgresStartedByLauncher) {
        & $pgCtl -D $pgData stop | Out-Null
    }
}

Set-Location $project
New-Item -ItemType Directory -Path $stateDir -Force | Out-Null

try {
    if (Test-Path $statePath) {
        $previousState = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json
        if ($previousState.active) {
            & (Join-Path $PSScriptRoot 'stop-public-demo.ps1')
        }
    }

    Require-Command -Name 'php'
    Require-Command -Name 'npm'
    Require-Command -Name 'cloudflared'

    $occupiedPid = Get-PortOwnerPid -Port $phpPort
    if ($occupiedPid) {
        $compatibleServer = Resolve-CompatibleExistingServer -WorkerPid $occupiedPid

        if (-not $compatibleServer) {
            throw "A porta $phpPort ja esta em uso pelo processo $occupiedPid. Libere essa porta antes de publicar o demo."
        }

        Stop-PhpServerState -ServerState $compatibleServer
        Wait-ForPortFree -Port $phpPort -TimeoutSeconds 20
    }

    Clear-FileIfExists -Path $phpStdOutLog
    Clear-FileIfExists -Path $phpStdErrLog
    Clear-FileIfExists -Path $cloudflaredStdOutLog
    Clear-FileIfExists -Path $cloudflaredStdErrLog

    if (-not (Test-Path (Join-Path $project 'public\storage'))) {
        php artisan storage:link | Out-Null
    }

    php artisan config:clear | Out-Null
    npm run build | Out-Null

    $postgresStartedByLauncher = [bool] (Ensure-PostgresReady)

    $temporaryServer = Start-PhpServer -EnvironmentOverrides @{} -StdOutLog $phpStdOutLog -StdErrLog $phpStdErrLog

    Set-Content -LiteralPath $cloudflaredConfig -Encoding UTF8 -Value "# Quick Tunnel sem config local`n"
    $cloudflaredProcess = Start-Process -FilePath 'cloudflared' `
        -ArgumentList @(
            'tunnel',
            '--config', $cloudflaredConfig,
            '--no-autoupdate',
            '--url', $localUrl
        ) `
        -WorkingDirectory $project `
        -WindowStyle Hidden `
        -RedirectStandardOutput $cloudflaredStdOutLog `
        -RedirectStandardError $cloudflaredStdErrLog `
        -PassThru

    $publicUrl = Wait-ForTryCloudflareUrl -LogPaths @($cloudflaredStdOutLog, $cloudflaredStdErrLog) -TimeoutSeconds 60

    Stop-PhpServerState -ServerState $temporaryServer
    Wait-ForPortFree -Port $phpPort -TimeoutSeconds 20
    $temporaryServer = $null

    $activeServer = Start-PhpServer -EnvironmentOverrides @{
        APP_ENV = 'production'
        APP_DEBUG = 'false'
        APP_URL = $publicUrl
        SESSION_SECURE_COOKIE = 'true'
    } -StdOutLog $phpStdOutLog -StdErrLog $phpStdErrLog

    Wait-ForHttpOk -Url $publicUrl -TimeoutSeconds 45 | Out-Null
    Wait-ForHttpOk -Url "$publicUrl/login" -TimeoutSeconds 45 | Out-Null

    Write-StateFile -Url $publicUrl -ServerState $activeServer -TunnelProcess $cloudflaredProcess -PostgresStarted $postgresStartedByLauncher
    Write-ShareFile -Url $publicUrl

    Get-Content -LiteralPath $sharePath
} catch {
    Invoke-TrackedCleanup
    throw
}
