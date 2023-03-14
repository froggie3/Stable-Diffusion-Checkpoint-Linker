# The wrapper script for src/main.php written in PowerShell 
# where it requires php.exe is installed and added to PATH in your system.
#
# https://www.php.net/

if (($null -ne (Get-Command php.exe -ErrorAction SilentlyContinue)) -eq $true) {
    php -f ((Get-Location).Path + "\src\main.php") $Args;
}
else {
    Write-Output "Error: The PHP binary is not currently installed in your system."
}