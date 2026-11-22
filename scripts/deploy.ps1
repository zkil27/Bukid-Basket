# Deploy from PowerShell:  .\scripts\deploy.ps1   (or .\scripts\deploy.ps1 master)
# Runs scripts/deploy.sh with Git for Windows' bash. Plain `bash` in PowerShell
# is usually WSL, which does not have this machine's SSH key or config.
$gitBash = Join-Path (Split-Path (Split-Path (Get-Command git).Source)) 'bin\bash.exe'
if (-not (Test-Path $gitBash)) { $gitBash = 'C:\Program Files\Git\bin\bash.exe' }
& $gitBash (Join-Path $PSScriptRoot 'deploy.sh') @args
exit $LASTEXITCODE
