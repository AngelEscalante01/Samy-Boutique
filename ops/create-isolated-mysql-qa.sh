#!/usr/bin/env bash
set -euo pipefail

# The local MySQL container owns this account. Never connect to Neubox or
# touch the existing samy_boutique_local_angel database.
docker exec samy-boutique-mysql sh -lc \
  'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot -e "CREATE DATABASE IF NOT EXISTS samy_notifications_qa_20261010 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS samy_notifications_qa_legacy_20261010 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS samy_notifications_qa_devlegacy_20261010 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS samy_notifications_qa_fresh_20261010 CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON samy_notifications_qa_20261010.* TO '\''samy_boutique_local'\''@'\''%'\''; GRANT ALL PRIVILEGES ON samy_notifications_qa_legacy_20261010.* TO '\''samy_boutique_local'\''@'\''%'\''; GRANT ALL PRIVILEGES ON samy_notifications_qa_devlegacy_20261010.* TO '\''samy_boutique_local'\''@'\''%'\''; GRANT ALL PRIVILEGES ON samy_notifications_qa_fresh_20261010.* TO '\''samy_boutique_local'\''@'\''%'\'';"'
echo 'Isolated local QA database is ready.'
