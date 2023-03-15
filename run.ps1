# The wrapper script for src/main.php written in PowerShell 
# where it requires php.exe is installed and added to PATH in your system.
#
# https://www.php.net/

$executables = @{
    Editor = "notepad"; 
    Config = Join-Path (Get-Location).Path 'config\config.json'
}

function editorOpen() {
    $answer = Read-Host "Do you want to open $($executables.Editor) to edit the configuration? [Y/n]"
    if ($answer.ToLower() -eq "y") {
        Write-Host -NoNewline "Opening $($executables.Editor) ... "
        $process = Start-Process $executables.Editor $executables.Config -PassThru
        Wait-Process $process.Id
        Write-Output "done."
    }
}

function processStart() {
    $scriptPath = Join-Path (Get-Location).Path "src\main.php"
    php.exe $scriptPath --json $executables.Config;
}

function processes() {
    editorOpen;
    processStart;
}

[bool] $requisitesAvailable = $null -ne (Get-Command php.exe -ErrorAction SilentlyContinue); 

if ($requisitesAvailable -eq $true) {
    processes 
    Start-Sleep -Seconds 3 
}
else {
    Write-Output "Error: The PHP binary is not currently installed in your system."
}