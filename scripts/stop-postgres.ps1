$ErrorActionPreference = 'Stop'

$project = Split-Path -Parent $PSScriptRoot
$pgCtl = 'C:\Program Files\PostgreSQL\18\bin\pg_ctl.exe'
$data = Join-Path $project '.local-pgsql'

& $pgCtl -D $data stop
