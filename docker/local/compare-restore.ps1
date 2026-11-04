param([Parameter(Mandatory)][string]$ReportDirectory)

$ErrorActionPreference = 'Stop'
$beforeCounts = Get-Content -LiteralPath (Join-Path $ReportDirectory 'restore-counts-before.json') -Raw | ConvertFrom-Json
$beforeAggregates = Get-Content -LiteralPath (Join-Path $ReportDirectory 'restore-aggregates-before.json') -Raw | ConvertFrom-Json
$compose = @('compose', '--env-file', 'docker/local/compose.env', '-f', 'compose.local.yaml')
$container = (& docker @compose ps -q mysql).Trim()
if (-not $container) { throw 'Sandbox database is not running' }

function Invoke-LocalSql([string]$Sql) {
    $result = & docker exec -e MYSQL_PWD=local-only-root-password $container mariadb -uroot -N -B -e $Sql
    if ($LASTEXITCODE -ne 0) { throw 'Local SQL failed' }
    return [string]$result
}

$countDiffs = @()
$afterCounts = foreach ($item in $beforeCounts) {
    $table = [string]$item.table
    if ($table -notmatch '^[A-Za-z0-9_]+$') { throw 'Unexpected table identifier' }
    $actual = Invoke-LocalSql ('SELECT COUNT(*) FROM samy_restore.`' + $table + '`')
    if ($table -ne 'migrations' -and [long]$actual -ne [long]$item.rows) { $countDiffs += $table }
    [pscustomobject]@{table=$table; rows=$actual}
}
$afterCounts | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath (Join-Path $ReportDirectory 'restore-counts-after.json')

$numberStyle = [Globalization.NumberStyles]::Float
$culture = [Globalization.CultureInfo]::InvariantCulture
$sumDiffs = @()
$afterAggregates = foreach ($item in $beforeAggregates) {
    $column = [string]$item.column
    if ($column -notmatch '^([A-Za-z0-9_]+)\.([A-Za-z0-9_]+)$') { throw 'Unexpected column identifier' }
    $table = $Matches[1]
    $field = $Matches[2]
    $actual = Invoke-LocalSql ('SELECT COALESCE(SUM(`' + $field + '`),0) FROM samy_restore.`' + $table + '`')
    $beforeValue = [decimal]::Parse([string]$item.sum, $numberStyle, $culture)
    $afterValue = [decimal]::Parse($actual, $numberStyle, $culture)
    if ($table -ne 'migrations' -and $beforeValue -ne $afterValue) { $sumDiffs += $column }
    [pscustomobject]@{column=$column; sum=$actual}
}
$afterAggregates | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath (Join-Path $ReportDirectory 'restore-aggregates-after.json')

$result = [pscustomobject]@{
    original_tables = @($beforeCounts).Count
    numeric_columns = @($beforeAggregates).Count
    changed_row_counts = $countDiffs
    changed_numeric_sums = $sumDiffs
}
$result | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath (Join-Path $ReportDirectory 'restore-comparison.json')
Write-Output ("Compared {0} original tables and {1} numeric columns; row-count changes={2}, sum changes={3}." -f $result.original_tables, $result.numeric_columns, $countDiffs.Count, $sumDiffs.Count)
if ($countDiffs.Count -gt 0 -or $sumDiffs.Count -gt 0) { exit 1 }
