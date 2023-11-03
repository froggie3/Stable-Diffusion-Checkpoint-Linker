# The wrapper script for src/main.php written in PowerShell 
# where it requires php.exe is installed and added to PATH in your system.
#
# https://www.php.net/

# self-elevating snippet for Powershell scripts which preserves the working directory
# https://stackoverflow.com/questions/7690994/running-a-command-as-administrator-using-powershell/57035712#57035712

if (!([Security.Principal.WindowsPrincipal][Security.Principal.WindowsIdentity]::GetCurrent()).IsInRole([Security.Principal.WindowsBuiltInRole]::Administrator)) {
    Start-Process PowerShell -Verb RunAs "-NoProfile -ExecutionPolicy Bypass -Command `"cd '$pwd'; & '$PSCommandPath';`"";
    exit;
}



$executables = @{
    # assign your editor
    Editor = "C:/Program Files/Notepad++/notepad++.exe"; 
    
    # specify the location to your favorite config file
    #Config = Join-Path (Get-Location).Path 'config\config.json'
	Config = "C:\mnt1\yokkin\dev\php\Stable-Diffusion-Checkpoint-Linker\config\config.json"
}

function editorOpen() {
    $answer = Read-Host "Do you want to open $($executables.Editor) to edit the configuration? [Y/n]"
    if (($answer.ToLower() -eq "y") -or ($answer.ToLower() -eq "")) {
        Write-Host -NoNewline "Opening $($executables.Editor) ... "
        $process = Start-Process $executables.Editor $executables.Config -PassThru
        Wait-Process $process.Id
        Write-Output "done."
    }
}

function processStart() {
    $scriptPath = Join-Path (Get-Location).Path "src\main.php"
    # giving `--symlink` argumant makes symbolic links instead of hardlinks
    # fyi not adding `--json` argument the config file `config/config.json` is read as default
    php.exe $scriptPath --json $executables.Config --symlink;
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
