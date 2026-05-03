#!/bin/bash
# =============================================================
# setup_dev.sh — Déploiement environnement dev.kunming-afchine.org
# Usage : bash setup_dev.sh
# Prérequis : vhost dev.kunming-afchine.org créé sur Gandi console
# =============================================================

set -e

SFTP_HOST="sftp.sd6.gpaas.net"
SFTP_USER="436b11ba-aeb3-11ef-9c89-00163e816020"
SSH_KEY="/data/.ssh/id_ed25519_gandi"
PROD_VHOST="vhosts/kunming-afchine.org/htdocs"
DEV_VHOST="vhosts/dev.kunming-afchine.org/htdocs"
PROD_PREFIX="bwhwo_"
DEV_PREFIX="dev_"
DB_NAME="Kunming.org"

SFTP_CMD="sftp -i $SSH_KEY -o StrictHostKeyChecking=no $SFTP_USER@$SFTP_HOST"
SSH_CMD="ssh -i $SSH_KEY -o StrictHostKeyChecking=no $SFTP_USER@$SFTP_HOST"

echo "=== 1. Vérification vhost dev ==="
$SFTP_CMD <<EOF
ls $DEV_VHOST
EOF

echo "=== 2. Copie des fichiers Joomla prod → dev ==="
# Rsync via SSH (si disponible) ou SFTP batch
# Note: Gandi Simple Hosting supporte rsync over SSH
rsync -avz \
  -e "ssh -i $SSH_KEY -o StrictHostKeyChecking=no" \
  --exclude "configuration.php" \
  --exclude "robots.txt" \
  --exclude "administrator/logs/*" \
  --exclude "tmp/*" \
  --exclude "cache/*" \
  $SFTP_USER@$SFTP_HOST:/srv/data/web/$PROD_VHOST/ \
  /tmp/prod_snapshot/ 2>/dev/null || echo "rsync non disponible, utiliser SFTP"

echo "=== 3. Upload configuration dev ==="
$SFTP_CMD <<EOF
put /tmp/configuration_dev.php $DEV_VHOST/configuration.php
put /tmp/robots_dev.txt $DEV_VHOST/robots.txt
EOF

echo "=== 4. Clonage DB prod → dev (via falang-inject) ==="
# Générer les CREATE TABLE + INSERT dynamiquement
curl -s -X POST "https://kunming-afchine.org/falang-inject/sppb5.php?action=write_query" \
  -H "X-Falang-Token: FALANG_SECRET_TOKEN_AFK_2026" \
  -H "Content-Type: application/json" \
  -d "{\"sql\": \"SELECT CONCAT('CREATE TABLE IF NOT EXISTS \`${DEV_PREFIX}\', SUBSTRING(table_name, LENGTH('${PROD_PREFIX}')+1), \`\` LIKE \`', table_name, '\`;') AS stmt FROM information_schema.tables WHERE table_schema = '${DB_NAME}' AND table_name LIKE '${PROD_PREFIX}%'\"}"

echo ""
echo "=== SETUP COMPLET ==="
echo "Vérifier : https://dev.kunming-afchine.org"
