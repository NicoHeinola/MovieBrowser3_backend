<#
.SYNOPSIS
    Registers a logon task that starts the MovieBrowser3 VLC bridge in WSL, without a window.

.DESCRIPTION
    The backend runs in Docker, where it cannot start vlc.exe. The bridge (vlc_bridge.py) runs in WSL instead.
    Started through wsl.exe it has Windows interop, so the VLC window shows on the desktop, and it keeps WSL (and
    Docker inside it) running. install.sh runs this script; run it again to update the task.

.EXAMPLE
    powershell.exe -ExecutionPolicy Bypass -File register-vlc-bridge.ps1 -Distro Ubuntu -BridgeScript /home/me/moviebrowser3/vlc_bridge.py
    powershell.exe -ExecutionPolicy Bypass -File register-vlc-bridge.ps1 -Unregister
#>
param(
    [string] $Distro,
    [string] $BridgeScript,
    [switch] $Unregister
)

$ErrorActionPreference = 'Stop'
$taskName = 'MovieBrowser3 VLC bridge'

if ($Unregister) {
    Stop-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
    Unregister-ScheduledTask -TaskName $taskName -Confirm:$false -ErrorAction SilentlyContinue
    Write-Host "Removed the '$taskName' task."
    exit 0
}

if (-not $Distro -or -not $BridgeScript) {
    throw 'Pass -Distro and -BridgeScript, or -Unregister.'
}

# conhost --headless runs wsl.exe without a console window
$action = New-ScheduledTaskAction -Execute 'conhost.exe' -Argument "--headless wsl.exe -d `"$Distro`" -e python3 `"$BridgeScript`""
$trigger = New-ScheduledTaskTrigger -AtLogOn -User "$env:USERDOMAIN\$env:USERNAME"
$principal = New-ScheduledTaskPrincipal -UserId "$env:USERDOMAIN\$env:USERNAME" -LogonType Interactive -RunLevel Limited
# No time limit (the default stops the task after 3 days), restart if it dies, run on battery
$settings = New-ScheduledTaskSettingsSet -ExecutionTimeLimit ([TimeSpan]::Zero) -RestartCount 3 `
    -RestartInterval (New-TimeSpan -Minutes 1) -MultipleInstances IgnoreNew -AllowStartIfOnBatteries -DontStopIfGoingOnBatteries

Register-ScheduledTask -TaskName $taskName -Action $action -Trigger $trigger -Principal $principal -Settings $settings `
    -Description 'Starts VLC for the MovieBrowser3 backend running in Docker (WSL).' -Force | Out-Null

# Restart it now so a re-run picks up a new bridge script
Stop-ScheduledTask -TaskName $taskName -ErrorAction SilentlyContinue
Start-ScheduledTask -TaskName $taskName
Write-Host "Registered and started the '$taskName' task."
