$ErrorActionPreference = 'Stop'

$project = Split-Path -Parent $PSScriptRoot
$pgCtl = 'C:\Program Files\PostgreSQL\18\bin\pg_ctl.exe'
$data = Join-Path $project '.local-pgsql'
$log = Join-Path $data 'server.log'

& $pgCtl -D $data -l $log start
Set-Location $project
php artisan serve --host=127.0.0.1 --port=8000
