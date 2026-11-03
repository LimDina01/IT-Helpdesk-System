param (
    [string]$Server = "192.168.11.188",
    [int]$Port = 2026,
    [string]$User = "cbvh",
    [string]$Branch = "main",
    [string]$AppPath = "/var/www/it-helpdesk"
)

$ErrorActionPreference = "Stop"

Write-Host "`n=======================================================" -ForegroundColor Cyan
Write-Host "   🚀 CBVH IT Helpdesk - Production Deployment         " -ForegroundColor Cyan
Write-Host "=======================================================" -ForegroundColor Cyan

# 1. Push latest branch to production git remote
Write-Host "`n[1/2] Pushing '$Branch' to production remote (ssh://$User@$Server:$Port/var/repo/it-helpdesk.git)..." -ForegroundColor Yellow
git push production $Branch

if ($LASTEXITCODE -ne 0) {
    Write-Host "`n❌ Error: git push to production failed!" -ForegroundColor Red
    exit $LASTEXITCODE
}
Write-Host "✅ Code pushed successfully to production remote." -ForegroundColor Green

# 2. Run post-deploy commands via SSH
Write-Host "`n[2/2] Running migrations and clearing caches via SSH..." -ForegroundColor Yellow
$remoteCommands = "cd $AppPath && php artisan migrate --force && php artisan view:clear && php artisan config:clear && php artisan route:clear"

ssh -p $Port $User@$Server $remoteCommands

if ($LASTEXITCODE -eq 0) {
    Write-Host "`n=======================================================" -ForegroundColor Green
    Write-Host "   ✅ Deployment to production completed successfully! " -ForegroundColor Green
    Write-Host "=======================================================`n" -ForegroundColor Green
} else {
    Write-Host "`n⚠️ Notice: Remote commands returned exit code $LASTEXITCODE." -ForegroundColor Yellow
    Write-Host "If your application path on the server is different from '$AppPath'," -ForegroundColor Yellow
    Write-Host "pass the path explicitly: .\deploy.ps1 -AppPath '/your/server/path'`n" -ForegroundColor Yellow
}

