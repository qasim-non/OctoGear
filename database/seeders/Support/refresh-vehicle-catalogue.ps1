$ErrorActionPreference = 'Stop'
$dataDirectory = Join-Path $PSScriptRoot '../data'
$makes = Get-Content -LiteralPath (Join-Path $dataDirectory 'vehicle-makes.json') -Raw | ConvertFrom-Json
$catalogue = @($makes | ForEach-Object -Parallel {
    $ErrorActionPreference = 'Stop'
    $make = $_
    $escapedMake = [Uri]::EscapeDataString($make.name_en)
    $sources = @()
    $rows = foreach ($type in @('car', 'mpv', 'truck')) {
        $url = "https://vpic.nhtsa.dot.gov/api/vehicles/GetModelsForMakeYear/make/$escapedMake/modelyear/$($make.year)/vehicletype/$($type)?format=json"
        $sources += $url
        $response = Invoke-RestMethod -Uri $url -TimeoutSec 60 -MaximumRetryCount 2 -RetryIntervalSec 2
        $response.Results | Where-Object { $_.Make_Name -ieq $make.name_en }
    }
    $models = @($rows | Sort-Object Model_Name -Unique | ForEach-Object { $_.Model_Name })
    if ($models.Count -eq 0) { throw "No verified models returned for $($make.name_en); snapshot not replaced." }
    [pscustomobject][ordered]@{name_en=$make.name_en; name_ar=$make.name_ar; country=$make.country; year=$make.year; sources=$sources; models=$models}
} -ThrottleLimit 3 | Sort-Object name_en)
if ($catalogue.Count -ne $makes.Count) { throw 'Incomplete catalogue; snapshot not replaced.' }
$snapshot = [ordered]@{retrieved_at=(Get-Date -Format 'yyyy-MM-dd'); source='NHTSA vPIC manufacturer submissions'; vehicle_types=@('Passenger Car', 'Multipurpose Passenger Vehicle (MPV)', 'Truck'); makes=$catalogue}
$snapshot | ConvertTo-Json -Depth 8 | Set-Content -LiteralPath (Join-Path $dataDirectory 'vehicles-vpic.json') -Encoding utf8NoBOM
Write-Output "Saved $($catalogue.Count) verified makes."
