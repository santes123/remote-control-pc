$ErrorActionPreference = "Stop"

if (!(Test-Path ".\.env")) {
  Write-Host "Falta .env. Copia .env.example a .env y ajusta variables."
  exit 1
}

Get-Content ".\.env" | ForEach-Object {
  if ($_ -match "^\s*$") { return }
  if ($_ -match "^\s*#") { return }
  $kv = $_.Split("=", 2)
  if ($kv.Length -ne 2) { return }
  [System.Environment]::SetEnvironmentVariable($kv[0].Trim(), $kv[1].Trim())
}

docker compose up -d mysql

$guacVersion = [System.Environment]::GetEnvironmentVariable("GUACAMOLE_VERSION")
if ([string]::IsNullOrWhiteSpace($guacVersion)) {
  $guacVersion = "1.5.5"
}

$initSqlPath = Join-Path (Get-Location) "initdb.sql"
docker run --rm ("guacamole/guacamole:" + $guacVersion) /opt/guacamole/bin/initdb.sh --mysql | Out-File -Encoding utf8 $initSqlPath

$rootPass = [System.Environment]::GetEnvironmentVariable("MYSQL_ROOT_PASSWORD")
$dbName = [System.Environment]::GetEnvironmentVariable("MYSQL_DATABASE")

Get-Content $initSqlPath | docker exec -i rcp-guac-mysql mysql -uroot ("-p" + $rootPass) $dbName

Write-Host "Esquema importado. Ya puedes levantar guacd y guacamole: docker compose up -d"
