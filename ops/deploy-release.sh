#!/usr/bin/env bash
# Deploy a JK Michaels release archive to app.jkmichaelspm.com (Plesk host).
#
# Run as root on the server:
#   bash deploy-release.sh /root/jkm-release-<name>/release.tar.gz
#
# Mirrors the 5 October 2026 release procedure (ops/README.md): a new release
# directory beside the live one, shared storage and .env reused, database
# backup taken in maintenance mode, atomic symlink switch owned by the
# application account, and automatic rollback to the previous release if any
# step fails before traffic reopens. The application database is never rolled
# back automatically; migrations in this release are additive.
set -Eeuo pipefail
umask 022

ARCHIVE=${1:?Usage: bash deploy-release.sh /path/to/release.tar.gz}
HOST_DIR=/var/www/vhosts/jkmichaelspm.com
LIVE=$HOST_DIR/app.jkmichaelspm.com
RELEASES=$HOST_DIR/.jkm-releases
APP_USER=jkmichaelspm.com_8glivfes07m
APP_GROUP=psacln
PHP=/opt/plesk/php/8.2/bin/php
COMPOSER=$RELEASES/composer.phar
BASE_URL=https://app.jkmichaelspm.com
UNITS_STOP=(jkm-health.timer jkm-scheduler.timer jkm-scheduler.service jkm-queue.service jkm-contact-imports.service)
UNITS_START=(jkm-queue.service jkm-contact-imports.service jkm-scheduler.timer jkm-health.timer)

NAME=$(date -u +%Y%m%dT%H%M%SZ)-lms
STAGE=$RELEASES/$NAME
WORK=/root/jkm-deploy-$NAME
mkdir -p -m 700 "$WORK"
exec > >(tee -a "$WORK/deploy.log") 2>&1

say() { printf '\n[%s] %s\n' "$(date -u +%H:%M:%S)" "$*"; }
die() { say "ERROR: $*"; fail 1; }
as_app() { local dir=$1; shift; (cd "$dir" && runuser -u "$APP_USER" -- "$@"); }
artisan() { local dir=$1; shift; as_app "$dir" "$PHP" artisan "$@"; }

stop() { say "ERROR: $*"; exit 1; }
[[ $EUID -eq 0 ]] || stop "Run as root."
[[ -f $ARCHIVE ]] || stop "Archive not found: $ARCHIVE"
[[ -L $LIVE ]] || stop "$LIVE is not a release symlink; refusing to continue."
[[ -x $PHP ]] || stop "PHP not found at $PHP"
OLD=$(readlink -f "$LIVE")
[[ -f $OLD/.env && -d $OLD/vendor ]] || stop "Current release $OLD is missing .env or vendor."
SHARED_STORAGE=$(readlink -f "$OLD/storage")
DB_NAME=$(grep -E '^DB_DATABASE=' "$OLD/.env" | tail -1 | cut -d= -f2- | tr -d "\"'" )
[[ -n $DB_NAME ]] || stop "DB_DATABASE not found in $OLD/.env"
say "Current release: $OLD"
say "New release:     $STAGE"
say "Database:        $DB_NAME"
say "Logs and backup: $WORK"

PAUSED=0 SWITCHED=0 STAGED=0
pool_reset() { pkill -TERM -f '^php-fpm: pool app\.jkmichaelspm\.com$' || true; }
switch_to() {
    local target=$1 pointer=$HOST_DIR/.jkm-pointer-$NAME
    rm -f "$pointer"
    ln -s "$target" "$pointer"
    chown -h "$APP_USER:$APP_GROUP" "$pointer"   # Plesk requires the symlink to be owned by the site account.
    mv -T "$pointer" "$LIVE"
    pool_reset
}
# Every failure (ERR trap or die) lands here; before staging starts nothing needs undoing.
fail() {
    local status=${1:-1}
    trap - ERR
    if (( STAGED )); then
        say "Deployment failed (exit $status). Rolling back to $OLD."
        if (( SWITCHED )); then switch_to "$OLD" || true; fi
        artisan "$OLD" up || true
        if (( PAUSED )); then systemctl start "${UNITS_START[@]}" || true; fi
        say "Live release is now: $(readlink -f "$LIVE")"
        say "The database was NOT restored. Backup (if taken): $WORK/production.sql"
    fi
    exit "$status"
}
trap 'fail $?' ERR
STAGED=1

say "1/9 Unpacking release"
mkdir -p "$STAGE"
tar -xzf "$ARCHIVE" -C "$STAGE"
[[ -f $STAGE/artisan && -f $STAGE/public/build/manifest.json ]] || die "Archive is missing artisan or built assets."
rm -rf "$STAGE/storage"
ln -s "$SHARED_STORAGE" "$STAGE/storage"
rm -rf "$STAGE/public/storage"
ln -s "$STAGE/storage/app/public" "$STAGE/public/storage"
cp -p "$OLD/.env" "$STAGE/.env"
mkdir -p "$STAGE/bootstrap/cache"
find "$STAGE" -path "$STAGE/storage" -prune -o -type d -exec chmod 755 {} +
find "$STAGE" -path "$STAGE/storage" -prune -o -type f -exec chmod 644 {} +
chmod 755 "$STAGE/artisan"; chmod 600 "$STAGE/.env"
# Keep the previous build's hashed assets so already-open browser tabs keep working.
for asset in "$OLD"/public/build/assets/*; do
    [[ -e $STAGE/public/build/assets/$(basename "$asset") ]] || cp -p "$asset" "$STAGE/public/build/assets/"
done
chown -R "$APP_USER:$APP_GROUP" "$STAGE"
chown -h "$APP_USER:$APP_GROUP" "$STAGE/storage" "$STAGE/public/storage"

say "2/9 PHP dependencies"
if cmp -s "$OLD/composer.lock" "$STAGE/composer.lock"; then
    cp -a "$OLD/vendor" "$STAGE/vendor"
    as_app "$STAGE" "$PHP" "$COMPOSER" dump-autoload --no-dev --optimize --no-scripts
else
    as_app "$STAGE" "$PHP" "$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --no-progress --no-scripts
fi
artisan "$STAGE" package:discover
artisan "$STAGE" config:cache
artisan "$STAGE" route:cache

say "3/9 Migration preview"
artisan "$STAGE" migrate:status | tail -n 15
artisan "$STAGE" migrate --pretend --force | tee "$WORK/migration-preview.sql"

say "4/9 Maintenance mode and service pause"
artisan "$OLD" down --retry=30
systemctl stop "${UNITS_STOP[@]}"
PAUSED=1

say "5/9 Backup (database dump + .env + shared storage)"
mysqldump --single-transaction --skip-lock-tables --hex-blob "$DB_NAME" > "$WORK/production.sql"
[[ -s $WORK/production.sql ]] || die "Database dump is empty."
tar -czf "$WORK/runtime.tar.gz" -C "$OLD" .env -C "$(dirname "$SHARED_STORAGE")" "$(basename "$SHARED_STORAGE")"
sha256sum "$WORK/production.sql" "$WORK/runtime.tar.gz" | tee "$WORK/backup.sha256"

say "6/9 Migrate"
artisan "$STAGE" migrate --force
artisan "$STAGE" view:cache

say "7/9 Switch release (still in maintenance)"
SECRET=$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')
artisan "$STAGE" down --retry=30 --secret="$SECRET"
switch_to "$STAGE"; SWITCHED=1
sleep 2
JAR=$WORK/cookies.txt
curl -fsS -o /dev/null -c "$JAR" -L "$BASE_URL/$SECRET"
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" "$BASE_URL/up"); [[ $code == 200 ]] || die "/up returned $code"
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" "$BASE_URL/login"); [[ $code == 200 ]] || die "/login returned $code"
app_js=$("$PHP" -r 'echo json_decode(file_get_contents($argv[1]), true)["resources/js/app.js"]["file"] ?? "";' "$STAGE/public/build/manifest.json")
[[ -n $app_js ]] || die "app.js missing from the build manifest"
code=$(curl -s -o /dev/null -w '%{http_code}' -b "$JAR" "$BASE_URL/build/$app_js"); [[ $code == 200 ]] || die "Built asset $app_js returned $code"
say "Pre-opening checks passed."

say "8/9 Reopen and resume services"
artisan "$STAGE" up
trap - ERR
set +e   # Traffic is open on the new release; report problems instead of rolling back.
systemctl start "${UNITS_START[@]}" || say "WARNING: could not start one or more services"
systemctl start jkm-scheduler.service

say "9/9 Post-deploy checks"
artisan "$STAGE" documents:check || true
artisan "$STAGE" enterprise:check --json --allow-test-payments > "$WORK/readiness.json" || say "WARNING: enterprise:check reported problems; see $WORK/readiness.json"
for unit in "${UNITS_START[@]}"; do printf '%-32s %s\n' "$unit" "$(systemctl is-active "$unit" || true)"; done
rm -f "$JAR"

say "Deployed $NAME. Live: $(readlink -f "$LIVE")"
cat <<EOF

Rollback (code only; the database stays on the new schema, which the previous release tolerates):
  ln -s "$OLD" "$HOST_DIR/.jkm-pointer-rollback" && chown -h $APP_USER:$APP_GROUP "$HOST_DIR/.jkm-pointer-rollback" && mv -T "$HOST_DIR/.jkm-pointer-rollback" "$LIVE" && pkill -TERM -f '^php-fpm: pool app\.jkmichaelspm\.com\$'
Backups: $WORK
EOF
