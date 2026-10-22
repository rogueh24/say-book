# Arrête la copie locale de say-book (serveur PHP sur le port 8080 + MariaDB de local\).
$local = Join-Path (Split-Path -Parent (Split-Path -Parent $PSScriptRoot)) 'local'

Get-NetTCPConnection -LocalPort 8080 -State Listen -ErrorAction SilentlyContinue |
	ForEach-Object { Stop-Process -Id $_.OwningProcess -Force -ErrorAction SilentlyContinue }
Get-Process mariadbd -ErrorAction SilentlyContinue | Where-Object { $_.Path -like "$local\*" } |
	ForEach-Object { & "$local\mariadb\bin\mariadb-admin.exe" '--host=127.0.0.1' '--port=3307' '--user=root' shutdown }
Write-Output 'Copie locale arrêtée.'
