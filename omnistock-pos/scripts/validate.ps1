param(
    [string]$PhpPath = '',
    [string]$NodePath = ''
)

$ErrorActionPreference = 'Stop'
$projectRoot = Split-Path -Parent $PSScriptRoot

function Resolve-Executable([string]$Path, [string]$Name, [string[]]$Fallbacks) {
    if ($Path -ne '') {
        if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
            throw "Executable not found: $Path"
        }
        return $Path
    }

    $command = Get-Command $Name -ErrorAction SilentlyContinue
    if ($null -ne $command -and $command.CommandType -eq 'Application') {
        return $command.Source
    }

    foreach ($fallback in $Fallbacks) {
        if (Test-Path -LiteralPath $fallback -PathType Leaf) {
            return $fallback
        }
    }

    throw "Could not find $Name. Pass its path using -$($Name.Substring(0, 1).ToUpper() + $Name.Substring(1))Path."
}

$php = Resolve-Executable $PhpPath 'php' @('C:\xampp\php\php.exe', 'C:\xampp2\php\php.exe')
$node = Resolve-Executable $NodePath 'node' @('C:\Program Files\nodejs\node.exe')
$excluded = '\\(?:vendor|node_modules)\\'

$phpFiles = @(Get-ChildItem -LiteralPath $projectRoot -Recurse -File -Filter '*.php' |
    Where-Object { $_.FullName -notmatch $excluded })
foreach ($file in $phpFiles) {
    & $php -l $file.FullName
    if ($LASTEXITCODE -ne 0) {
        throw "PHP syntax check failed: $($file.FullName)"
    }
}

$jsFiles = @(Get-ChildItem -LiteralPath $projectRoot -Recurse -File -Filter '*.js' |
    Where-Object { $_.FullName -notmatch $excluded })
foreach ($file in $jsFiles) {
    & $node --check $file.FullName
    if ($LASTEXITCODE -ne 0) {
        throw "JavaScript syntax check failed: $($file.FullName)"
    }
}

& $php (Join-Path $projectRoot 'tests\run.php')
if ($LASTEXITCODE -ne 0) {
    throw 'Automated PHP checks failed.'
}

Write-Output "Validated $($phpFiles.Count) PHP files and $($jsFiles.Count) JavaScript files."
