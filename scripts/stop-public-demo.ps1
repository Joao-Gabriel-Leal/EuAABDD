$ErrorActionPreference = 'Stop'
Set-StrictMode -Version Latest

$project = Split-Path -Parent $PSScriptRoot
$stateDir = Join-Path $project 'storage\logs'
$statePath = Join-Path $stateDir 'public-demo-state.json'
$pgCtl = 'C:\Program Files\PostgreSQL\18\bin\pg_ctl.exe'
$pgData = Join-Path $project '.local-pgsql'

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

    Stop-TrackedProcess -ProcessId $ServerState.artisanServePid
    Stop-TrackedProcess -ProcessId $ServerState.httpWorkerPid
}

Set-Location $project

if (-not (Test-Path $statePath)) {
    Write-Output 'Nenhuma publicacao publica ativa encontrada.'
    return
}

$state = Get-Content -LiteralPath $statePath -Raw | ConvertFrom-Json

if (-not $state.active -or -not $state.PSObject.Properties.Name.Contains('processes')) {
    Write-Output 'Nenhuma publicacao publica ativa encontrada.'
    return
}

Stop-PhpServerState -ServerState $state.processes
Stop-TrackedProcess -ProcessId $state.processes.cloudflaredPid

if ($state.postgresStartedByLauncher -and (Test-Path $pgCtl) -and (Test-Path $pgData)) {
    & $pgCtl -D $pgData stop | Out-Null
}

$inactiveState = [ordered]@{
    active = $false
    stoppedAt = (Get-Date).ToString('o')
    lastPublicUrl = $state.publicUrl
    postgresStartedByLauncher = $state.postgresStartedByLauncher
    artifacts = $state.artifacts
}

$inactiveState | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath $statePath -Encoding UTF8

Write-Output 'Publicacao publica encerrada.'
