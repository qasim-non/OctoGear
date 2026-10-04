param([switch]$AllYears)

$ErrorActionPreference = 'Stop'
$dataDirectory = Join-Path $PSScriptRoot '../data'
$makes = Get-Content -LiteralPath (Join-Path $dataDirectory 'vehicle-makes.json') -Raw | ConvertFrom-Json
if ($AllYears) {
    $makes += @(
        [pscustomobject]@{ name_en='Daihatsu'; name_ar='دايهاتسو'; country='Japan' },
        [pscustomobject]@{ name_en='Daewoo'; name_ar='دايو'; country='South Korea' }
    )
}
$catalogue = @($makes | ForEach-Object -Parallel {
    $ErrorActionPreference = 'Stop'
    $make = $_
    $escapedMake = [Uri]::EscapeDataString($make.name_en)
    $sources = @()
    $rows = foreach ($type in @('car', 'mpv', 'truck')) {
        $yearSegment = if ($using:AllYears) { '' } else { "modelyear/$($make.year)/" }
        $url = "https://vpic.nhtsa.dot.gov/api/vehicles/GetModelsForMakeYear/make/$escapedMake/$($yearSegment)vehicletype/$($type)?format=json"
        $sources += $url
        $response = Invoke-RestMethod -Uri $url -TimeoutSec 60 -MaximumRetryCount 2 -RetryIntervalSec 2
        $response.Results | Where-Object { $_.Make_Name -ieq $make.name_en }
    }
    $models = @($rows | Sort-Object Model_Name -Unique | ForEach-Object { $_.Model_Name })
    if ($models.Count -eq 0) { throw "No verified models returned for $($make.name_en); snapshot not replaced." }
    $year = if ($using:AllYears) { $null } else { $make.year }
    [pscustomobject][ordered]@{name_en=$make.name_en; name_ar=$make.name_ar; country=$make.country; year=$year; sources=$sources; models=$models}
} -ThrottleLimit 3 | Sort-Object name_en)
if ($catalogue.Count -ne $makes.Count) { throw 'Incomplete catalogue; snapshot not replaced.' }
$snapshot = [ordered]@{retrieved_at=(Get-Date -Format 'yyyy-MM-dd'); source='NHTSA vPIC manufacturer submissions'; vehicle_types=@('Passenger Car', 'Multipurpose Passenger Vehicle (MPV)', 'Truck'); makes=$catalogue}
$filename = if ($AllYears) { 'vehicles-vpic-history.json' } else { 'vehicles-vpic.json' }
$snapshot | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath (Join-Path $dataDirectory $filename) -Encoding utf8NoBOM
Write-Output "Saved $($catalogue.Count) verified makes."
