param(
    [Parameter(Mandatory = $true)]
    [string] $Root
)

$ErrorActionPreference = 'Stop'
$Root = [System.IO.Path]::GetFullPath($Root)
if (-not (Test-Path -LiteralPath $Root -PathType Container)) {
    throw "No se encontró la carpeta del proyecto: $Root"
}

$Php = $env:OMNISTOCK_PHP_EXE
if (-not $Php -or -not (Test-Path -LiteralPath $Php -PathType Leaf)) {
    $phpCommand = Get-Command php.exe -ErrorAction SilentlyContinue
    if ($phpCommand) {
        $Php = $phpCommand.Source
    } else {
        $phpCandidates = @('C:\xampp\php\php.exe', 'C:\xampp2\php\php.exe')
        $Php = $phpCandidates | Where-Object { Test-Path -LiteralPath $_ -PathType Leaf } | Select-Object -First 1
    }
}

if (-not $Php) {
    throw 'No se encontró PHP. Instálalo o define OMNISTOCK_PHP_EXE con la ruta a php.exe.'
}

$BaseUrl = 'http://127.0.0.1:8765'
$loginUrl = "$BaseUrl/login.php"

function Test-OmniStockReady {
    try {
        $response = Invoke-WebRequest -Uri $loginUrl -UseBasicParsing -TimeoutSec 3 -ErrorAction Stop
        return $response.StatusCode -eq 200 -and $response.Content.Contains('Iniciar sesión · OmniStock POS')
    } catch {
        return $false
    }
}

$ready = Test-OmniStockReady
if (-not $ready) {
    $listener = Get-NetTCPConnection -LocalPort 8765 -State Listen -ErrorAction SilentlyContinue
    if ($listener) {
        throw 'El puerto 8765 está ocupado por otro servicio. No se iniciará un servidor duplicado.'
    }

    $serverProcess = Start-Process -FilePath $Php `
        -ArgumentList ('-S 127.0.0.1:8765 -t "' + $Root + '"') `
        -WorkingDirectory $Root `
        -WindowStyle Hidden `
        -PassThru

    for ($attempt = 0; $attempt -lt 60; $attempt++) {
        if ($serverProcess.HasExited) {
            throw "El servidor PHP se cerró antes de responder. Revisa la instalación en $Php."
        }

        Start-Sleep -Milliseconds 500
        if (Test-OmniStockReady) {
            $ready = $true
            break
        }
    }

    if (-not $ready) {
        try {
            Stop-Process -Id $serverProcess.Id -ErrorAction Stop
        } catch {
            Write-Warning "No se pudo detener el proceso PHP de inicio (PID $($serverProcess.Id))."
        }
        throw 'El servidor local no respondió. Verifica PHP, MySQL y el puerto 8765.'
    }
}

Start-Process -FilePath $loginUrl
