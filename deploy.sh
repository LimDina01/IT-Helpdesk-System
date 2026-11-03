#!/bin/bash
set -e

SERVER="192.168.11.188"
PORT="2026"
USER="cbvh"
BRANCH="main"
APP_PATH="/var/www/it-helpdesk"

echo "======================================================="
echo "   🚀 CBVH IT Helpdesk - Production Deployment         "
echo "======================================================="

echo ""
echo "[1/2] Pushing '${BRANCH}' to production remote..."
git push production "${BRANCH}"

echo ""
echo "[2/2] Running migrations and cache refresh on server..."
ssh -p "${PORT}" "${USER}@${SERVER}" "cd ${APP_PATH} && php artisan migrate --force && php artisan view:clear && php artisan config:clear && php artisan route:clear"

echo ""
echo "======================================================="
echo "   ✅ Deployment to production completed successfully! "
echo "======================================================="
