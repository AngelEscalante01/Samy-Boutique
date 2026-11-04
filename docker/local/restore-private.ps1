param(
    [Parameter(Mandatory)][string]$Source,
    [Parameter(Mandatory)][string]$ExpectedSha256,
    [Parameter(Mandatory)][string]$ReportDirectory
)
$ErrorActionPreference = 'Stop'
if ((Get-FileHash -LiteralPath $Source -Algorithm SHA256).Hash -ne $ExpectedSha256) { throw 'Backup hash mismatch' }
$compose = @('compose','--env-file','docker/local/compose.env','-f','compose.local.yaml')
$running = & docker @compose ps --services --status running
if ($LASTEXITCODE -ne 0) { throw 'Cannot inspect sandbox' }
if ($running | Where-Object { $_ -in @('web','queue-default','queue-sales','scheduler') }) { throw 'Stop web/workers/scheduler before restore' }
$container = (& docker @compose ps -q mysql).Trim()
if (-not $container) { throw 'Sandbox database is not running' }
function Invoke-LocalSql([string]$Sql) {
    $result = & docker exec -e MYSQL_PWD=local-only-root-password $container mariadb -uroot -N -B -e $Sql
    if ($LASTEXITCODE -ne 0) { throw 'Local SQL failed' }
    return $result
}
if (Invoke-LocalSql "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME='samy_restore'") {
    throw 'samy_restore already exists; refusing to overwrite it'
}
$inputFile = [IO.File]::OpenRead((Resolve-Path -LiteralPath $Source).Path)
$gzip = [IO.Compression.GZipStream]::new($inputFile,[IO.Compression.CompressionMode]::Decompress)
$reader = [IO.StreamReader]::new($gzip)
try { $sql = $reader.ReadToEnd() } finally { $reader.Dispose() }
if ($sql -match '(?im)^\s*(USE |CREATE DATABASE|DROP DATABASE)' -or $sql -match '(?i)DEFINER\s*=') {
    throw 'Database switching or DEFINER found; manual review required'
}
Invoke-LocalSql "CREATE DATABASE samy_restore CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER 'samy_restore'@'%' IDENTIFIED BY 'local-restore-only'; GRANT ALL ON samy_restore.* TO 'samy_restore'@'%';"
$processInfo = [Diagnostics.ProcessStartInfo]::new('docker')
foreach ($arg in @('exec','-i','-e','MYSQL_PWD=local-restore-only',$container,'mariadb','-usamy_restore','samy_restore')) { $processInfo.ArgumentList.Add($arg) }
$processInfo.UseShellExecute = $false
$processInfo.RedirectStandardInput = $true
$processInfo.RedirectStandardError = $true
$processInfo.RedirectStandardOutput = $true
$process = [Diagnostics.Process]::Start($processInfo)
$bytes = [Text.UTF8Encoding]::new($false).GetBytes($sql)
$process.StandardInput.BaseStream.Write($bytes,0,$bytes.Length)
$process.StandardInput.Close()
$errorRead = $process.StandardError.ReadToEndAsync()
$outputRead = $process.StandardOutput.ReadToEndAsync()
$process.WaitForExit()
if ($process.ExitCode -ne 0) { throw 'Restore failed; inspect sandbox privately. Existing restored data was retained.' }
New-Item -ItemType Directory -Force -Path $ReportDirectory | Out-Null
$tables = Invoke-LocalSql "SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA='samy_restore' ORDER BY TABLE_NAME"
$counts = foreach ($table in $tables) {
    if ($table -notmatch '^[A-Za-z0-9_]+$') { throw 'Unexpected table identifier' }
    $count = Invoke-LocalSql ('SELECT COUNT(*) FROM samy_restore.`' + $table + '`')
    [pscustomobject]@{table=$table; rows=$count}
}
$counts | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $ReportDirectory 'restore-counts-before.json')
$numericColumns = Invoke-LocalSql "SELECT CONCAT(TABLE_NAME, '.', COLUMN_NAME) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='samy_restore' AND DATA_TYPE IN ('tinyint','smallint','mediumint','int','bigint','decimal','float','double') ORDER BY TABLE_NAME,ORDINAL_POSITION"
$aggregates = foreach ($column in $numericColumns) {
    if ($column -notmatch '^([A-Za-z0-9_]+)\.([A-Za-z0-9_]+)$') { throw 'Unexpected column identifier' }
    $sum = Invoke-LocalSql ('SELECT COALESCE(SUM(`' + $Matches[2] + '`),0) FROM samy_restore.`' + $Matches[1] + '`')
    [pscustomobject]@{column=$column; sum=$sum}
}
$aggregates | ConvertTo-Json | Set-Content -LiteralPath (Join-Path $ReportDirectory 'restore-aggregates-before.json')
Write-Output ('Restored dedicated database; ' + $tables.Count + ' tables. Private baseline saved. No app/worker started.')
