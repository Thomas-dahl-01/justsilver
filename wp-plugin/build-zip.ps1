# Pakker pluginet som justsilver.zip, klar til upload i WordPress (Plugins -> Upload plugin).
$here = Split-Path -Parent $MyInvocation.MyCommand.Path
$out  = Join-Path $here 'justsilver.zip'
if (Test-Path $out) { Remove-Item $out -Force }
Compress-Archive -Path (Join-Path $here 'justsilver') -DestinationPath $out
Write-Host "Oprettet $out"
