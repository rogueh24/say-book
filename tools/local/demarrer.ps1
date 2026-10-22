# Démarre la copie locale de say-book : MariaDB (port 3307) + serveur PHP 8.4 (port 8080).
# Site : http://saybook.localhost:8080   —   Admin : /wp-admin (accès dans local\ACCES-LOCAL.txt)
$racine = Split-Path -Parent (Split-Path -Parent $PSScriptRoot)
$local  = Join-Path $racine 'local'
$php    = Get-ChildItem "$env:LOCALAPPDATA\Microsoft\WinGet\Packages" -Directory -Filter 'PHP.PHP.NTS.8.4*' | Select-Object -First 1 | ForEach-Object { Join-Path $_.FullName 'php.exe' }

if (-not (Get-Process mariadbd -ErrorAction SilentlyContinue)) {
	Start-Process "$local\mariadb\bin\mariadbd.exe" -ArgumentList "--defaults-file=`"$local\data\my.ini`"" -WindowStyle Hidden
	Write-Output 'MariaDB démarré (port 3307).'
} else { Write-Output 'MariaDB tourne déjà.' }

$occupe = Get-NetTCPConnection -LocalPort 8080 -State Listen -ErrorAction SilentlyContinue
if (-not $occupe) {
	Start-Process $php -ArgumentList "-c `"$local\php.ini`" -S 127.0.0.1:8080 -t `"$local\site`" `"$local\router.php`"" -WindowStyle Hidden
	Write-Output 'Serveur PHP démarré : http://saybook.localhost:8080'
} else { Write-Output 'Le port 8080 est déjà utilisé (serveur déjà lancé ?).' }
