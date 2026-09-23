<#
.SYNOPSIS
    Refresh the user guide's screenshots (docs/screenshots) from a local dev
    server, using headless Microsoft Edge. Not part of CI; used for releases
    (see "Refresh the screenshots" in DEVELOPMENT.md).

.DESCRIPTION
    For Windows with the dev server running in WSL, the usual GRACe setup:

      1. In WSL, from the repo root, seed a new-install demo:
           php tests/seed_demo_data.php --force
      2. In WSL, start the dev server:
           cd grace_addon/files/general/www/public && php -S 127.0.0.1:8420
      3. In PowerShell, from the repo root:
           .\tests\capture_screenshots.ps1
         or just some of them:
           .\tests\capture_screenshots.ps1 -Only desktop-dashboard,mobile-dashboard
         If PowerShell refuses to run scripts from the WSL folder, use:
           powershell -ExecutionPolicy Bypass -File .\tests\capture_screenshots.ps1

    Each shot loads a page, optionally fills in the form (so the guide shows
    real-looking entries), then saves a PNG. Desktop shots are 1280 px wide
    and as tall as the page (up to a limit); phone shots are a 390 x 844
    screen at 2x. The last two show the "What's new" pop-up: the script marks
    the latest notes unseen in /data/grace.db for those, and marks them seen
    again at the end, so every other shot is free of it.

    To add a shot, add a line to $shots and reference the PNG from
    grace_addon/DOCS.md.
#>
param(
    [string]$OutDir = (Join-Path $PSScriptRoot '..\docs\screenshots'),
    [string]$BaseUrl = 'http://127.0.0.1:8420',
    [string[]]$Only = @(),
    [string]$Browser = ''
)
$ErrorActionPreference = 'Stop'

# --- Find a browser --------------------------------------------------------------
if (-not $Browser) {
    $Browser = @(
        "${env:ProgramFiles(x86)}\Microsoft\Edge\Application\msedge.exe",
        "$env:ProgramFiles\Microsoft\Edge\Application\msedge.exe"
    ) | Where-Object { $_ -and (Test-Path $_) } | Select-Object -First 1
}
if (-not $Browser) {
    throw "Microsoft Edge wasn't found. Pass -Browser with the path to msedge.exe or chrome.exe."
}

# --- Check the dev server is up ------------------------------------------------------
try {
    Invoke-WebRequest "$BaseUrl/dashboard.php" -UseBasicParsing -TimeoutSec 5 | Out-Null
} catch {
    throw "No GRACe dev server answering at $BaseUrl. Seed and start one first (see the notes at the top of this script)."
}

# The version the What's new pop-up would show: the newest changelog heading
$changelog = Join-Path $PSScriptRoot '..\grace_addon\CHANGELOG.md'
$version = (Select-String -Path $changelog -Pattern '^## \[([^\]]+)\]' | Select-Object -First 1).Matches[0].Groups[1].Value

# Mark What's new seen ($value = a version) or unseen ('pending') in the WSL
# dev database. Runs PHP in WSL, which the dev setup already needs.
function Set-WhatsNewMarker([string]$value) {
    $code = '$v = $argv[1] === ''pending'' ? '''' : $argv[1];' +
            '$p = new PDO(''sqlite:/data/grace.db'');' +
            '$p->prepare(''UPDATE Settings SET value = ? WHERE name = ?'')->execute([$v, ''whatsNewSeenVersion'']);'
    & wsl.exe -e php -r $code $value
    if ($LASTEXITCODE -ne 0) {
        throw "Couldn't update the What's new marker in /data/grace.db through WSL."
    }
}

# --- The shots --------------------------------------------------------------------------
# Dates the demo data is relative to: the dashboard pretends it's the 3rd of
# this month (so the monthly report reminder shows) and the stocktake uses
# this year.
$demoDate = (Get-Date -Day 3).ToString('yyyy-MM-dd')
$stocktakeYear = (Get-Date).Year

# Injected before every setup script: set a field and fire 'change'
$helper = "const set=(id,v)=>{const el=document.getElementById(id);el.value=v;el.dispatchEvent(new Event('change',{bubbles:true}));};const opt=(id,i)=>document.getElementById(id).options[i].value;"

$quickSelect = "set('quickSelectGenetics','Northern Lights');document.getElementById('quickSelectCount').value=3;set('quickSelectOrder','oldest');document.getElementById('quickSelectButton').click();"
# Genetics 1 (Northern Lights) has flower on hand, so the stock hint isn't a warning
$dryWeight = "set('geneticsName','1');document.getElementById('weight').value=120;set('transactionType','Subtract');set('reason','Send external');set('companyId',opt('companyId',1));"
$addGenetics = "set('geneticsName','__quick_add__');setTimeout(()=>{document.getElementById('quickAdd_geneticsName').value='Aotearoa Gold';},200);"

# name, path, width, max height, scale, phone?, setup script ('WHATSNEW' = show the pop-up)
$shots = @(
    @('desktop-dashboard',            "/dashboard.php?demo_date=$demoDate",                     1280, 1000, 1, $false, ''),
    @('desktop-tracking',             '/tracking.php',                                          1280, 900,  1, $false, ''),
    @('desktop-receive-plants',       '/receive_genetics.php',                                  1280, 1250, 1, $false, "document.getElementById('plantCount').value=12;set('geneticsName',opt('geneticsName',1));"),
    @('desktop-add-new-genetics',     '/receive_genetics.php',                                  1280, 900,  1, $false, "document.getElementById('plantCount').value=12;$addGenetics"),
    @('desktop-harvest-quick-select', '/harvest_plants.php',                                    1280, 1150, 1, $false, $quickSelect),
    @('desktop-harvest-confirm',      '/harvest_plants.php',                                    1280, 900,  1, $false, "$quickSelect set('action','destroy');document.getElementById('processSelectedButton').click();"),
    @('desktop-record-dry-weight',    '/record_dry_weight.php',                                 1280, 1500, 1, $false, $dryWeight),
    @('desktop-list-all-plants',      '/list_all_genetics.php',                                 1280, 1000, 1, $false, ''),
    @('desktop-generate-manifest',    '/generate_shipping_manifest.php',                        1280, 1500, 1, $false, "document.getElementById('quantity').value=100;set('geneticsId',document.getElementById('geneticsId').value);"),
    @('desktop-complete-manifest',    '/complete_manifest.php',                                 1280, 1000, 1, $false, ''),
    @('desktop-manifest-summary',     '/manifest_summary.php?id=1',                             1280, 1000, 1, $false, ''),
    @('desktop-reporting',            '/reporting.php',                                         1280, 900,  1, $false, ''),
    @('desktop-last-months-report',   '/last_months_flower_transactions.php',                   1280, 1000, 1, $false, ''),
    @('desktop-annual-stocktake',     "/annual_stocktake.php?year=$stocktakeYear&autorun=1",    1280, 1300, 1, $false, ''),
    @('desktop-administration',       '/administration.php',                                    1280, 1150, 1, $false, ''),
    @('desktop-verified-companies',   '/verified_companies.php',                                1280, 800,  1, $false, ''),
    @('desktop-edit-company',         '/edit_verified_company.php?id=1',                        1280, 1000, 1, $false, ''),
    @('desktop-company-licenses',     '/company_licenses.php',                                  1280, 1000, 1, $false, ''),
    @('mobile-dashboard',             "/dashboard.php?demo_date=$demoDate",                     390,  844,  2, $true,  ''),
    @('mobile-menu-open',             '/tracking.php',                                          390,  844,  2, $true,  "document.getElementById('nav-toggle').checked=true;"),
    @('mobile-tracking',              '/tracking.php',                                          390,  844,  2, $true,  ''),
    @('mobile-harvest',               '/harvest_plants.php',                                    390,  844,  2, $true,  $quickSelect),
    @('mobile-record-dry-weight',     '/record_dry_weight.php',                                 390,  844,  2, $true,  $dryWeight),
    @('mobile-last-months-report',    '/last_months_flower_transactions.php',                   390,  844,  2, $true,  ''),
    @('mobile-list-all-plants',       '/list_all_genetics.php',                                 390,  844,  2, $true,  ''),
    @('mobile-administration',        '/administration.php',                                    390,  844,  2, $true,  ''),
    # Last, because they switch the demo database to "notes not seen yet"
    @('desktop-whats-new',            '/dashboard.php',                                         1280, 900,  1, $false, 'WHATSNEW'),
    @('mobile-whats-new',             '/dashboard.php',                                         390,  844,  2, $true,  'WHATSNEW')
)

# --- Headless browser, driven over the DevTools protocol ----------------------------------
$port = 9335
$browserProfile = Join-Path $env:TEMP 'grace-screenshot-profile'
Remove-Item -Recurse -Force $browserProfile -ErrorAction SilentlyContinue
New-Item -ItemType Directory -Force $OutDir | Out-Null

$proc = Start-Process -FilePath $Browser -PassThru -WindowStyle Hidden -ArgumentList @(
    '--headless=new', '--disable-gpu', '--hide-scrollbars', '--no-first-run', '--no-default-browser-check',
    "--user-data-dir=$browserProfile", "--remote-debugging-port=$port", '--window-size=1280,900', 'about:blank')

$ws = $null
try {
    $deadline = (Get-Date).AddSeconds(25)
    $targets = $null
    do {
        try { $targets = Invoke-RestMethod "http://127.0.0.1:$port/json" -TimeoutSec 2 } catch { Start-Sleep -Milliseconds 300 }
    } while (-not $targets -and (Get-Date) -lt $deadline)
    if (-not $targets) { throw "The browser's DevTools endpoint never came up." }
    $page = $targets | Where-Object { $_.type -eq 'page' } | Select-Object -First 1

    $ws = [System.Net.WebSockets.ClientWebSocket]::new()
    $ws.ConnectAsync([Uri]$page.webSocketDebuggerUrl, [Threading.CancellationToken]::None).GetAwaiter().GetResult() | Out-Null
    $script:msgId = 0
    $recvBuf = [byte[]]::new(2MB)

    function Send-Cdp([string]$method, [hashtable]$params = @{}) {
        $script:msgId++
        $id = $script:msgId
        $json = @{ id = $id; method = $method; params = $params } | ConvertTo-Json -Depth 8 -Compress
        $bytes = [Text.Encoding]::UTF8.GetBytes($json)
        $ws.SendAsync([ArraySegment[byte]]::new($bytes), [Net.WebSockets.WebSocketMessageType]::Text, $true, [Threading.CancellationToken]::None).GetAwaiter().GetResult() | Out-Null
        while ($true) {
            $sb = [Text.StringBuilder]::new()
            do {
                $res = $ws.ReceiveAsync([ArraySegment[byte]]::new($recvBuf), [Threading.CancellationToken]::None).GetAwaiter().GetResult()
                [void]$sb.Append([Text.Encoding]::UTF8.GetString($recvBuf, 0, $res.Count))
            } while (-not $res.EndOfMessage)
            $msg = $sb.ToString() | ConvertFrom-Json
            if ($msg.id -eq $id) {
                if ($msg.error) { throw "DevTools $method failed: $($msg.error.message)" }
                return $msg.result
            }
            # anything else is an event we didn't ask for
        }
    }

    function Invoke-PageJs([string]$js) {
        $r = Send-Cdp 'Runtime.evaluate' @{ expression = $js; returnByValue = $true; awaitPromise = $true }
        return $r.result.value
    }

    # Every shot except the What's new ones is taken with the notes seen
    Set-WhatsNewMarker $version
    $saved = 0

    foreach ($s in $shots) {
        $name, $path, $w, $maxH, $scale, $mobile, $setup = $s
        if ($Only.Count -gt 0 -and $Only -notcontains $name) { continue }
        if ($setup -eq 'WHATSNEW') {
            Set-WhatsNewMarker 'pending' # nothing closes the pop-up here, so it stays up
            $setup = ''
        }

        $viewH = if ($mobile) { $maxH } else { 900 }
        Send-Cdp 'Emulation.setDeviceMetricsOverride' @{ width = $w; height = $viewH; deviceScaleFactor = $scale; mobile = $mobile } | Out-Null
        Send-Cdp 'Page.navigate' @{ url = "$BaseUrl$path" } | Out-Null

        # Wait for the page to load, then give fetch()-filled tables a moment
        $pathOnly = ($path -split '\?')[0]
        $t = (Get-Date).AddSeconds(15)
        do {
            Start-Sleep -Milliseconds 200
            $ready = Invoke-PageJs "document.readyState==='complete' && location.pathname.endsWith('$pathOnly')"
        } while (-not $ready -and (Get-Date) -lt $t)
        Start-Sleep -Milliseconds 1500

        if ($setup -ne '') {
            Invoke-PageJs "(()=>{ $helper $setup return true; })()" | Out-Null
            Start-Sleep -Milliseconds 700
        }

        if ($mobile) {
            $clip = @{ x = 0; y = 0; width = $w; height = $viewH; scale = 1 }
        } else {
            $m = Send-Cdp 'Page.getLayoutMetrics'
            $contentH = [math]::Ceiling($m.cssContentSize.height)
            $h = [math]::Min([math]::Max($contentH, 600), $maxH)
            $clip = @{ x = 0; y = 0; width = $w; height = $h; scale = 1 }
        }
        $shot = Send-Cdp 'Page.captureScreenshot' @{ format = 'png'; captureBeyondViewport = $true; clip = $clip }
        $out = Join-Path $OutDir "$name.png"
        [IO.File]::WriteAllBytes($out, [Convert]::FromBase64String($shot.data))
        $saved++
        "OK   $name  ($([math]::Round((Get-Item $out).Length / 1KB)) KB, $($clip.width)x$($clip.height) at ${scale}x)"
    }

    "Saved $saved screenshot(s) to $((Resolve-Path $OutDir).Path)"
} finally {
    # Leave the dev database with the notes seen, and close the browser
    try { Set-WhatsNewMarker $version } catch { Write-Warning $_ }
    if ($ws) { $ws.Dispose() }
    Stop-Process -Id $proc.Id -Force -ErrorAction SilentlyContinue
}
