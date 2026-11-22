#!/usr/bin/env bash
# Run as root on the authorized Ubuntu production host. Does not alter the
# hosting provider's masked ClamAV daemons or restart shared database services.
set -euo pipefail
umask 022
export DEBIAN_FRONTEND=noninteractive
export NEEDRESTART_MODE=l
apt-get -o DPkg::Lock::Timeout=60 install --no-install-recommends -y clamav clamav-freshclam
install -d -m 0755 /etc/jkmichaels /var/lib/jkmichaels
install -d -o clamav -g clamav -m 0755 /var/lib/jkmichaels/clamav
cat > /etc/jkmichaels/freshclam.conf <<'CONFIG'
DatabaseDirectory /var/lib/jkmichaels/clamav
DatabaseOwner clamav
DatabaseMirror database.clamav.net
ScriptedUpdates yes
ConnectTimeout 30
ReceiveTimeout 90
CONFIG
# Ubuntu confines freshclam to its default paths. Add only this application's
# configuration and signature directory to the existing confinement profile.
if [[ -f /etc/apparmor.d/usr.bin.freshclam ]]; then
    install -d -m 0755 /etc/apparmor.d/local
    touch /etc/apparmor.d/local/usr.bin.freshclam
    if ! grep -q 'JK Michaels definitions' /etc/apparmor.d/local/usr.bin.freshclam; then
        cat >> /etc/apparmor.d/local/usr.bin.freshclam <<'APPARMOR'
# JK Michaels definitions
/etc/jkmichaels/freshclam.conf r,
/var/lib/jkmichaels/clamav/ r,
/var/lib/jkmichaels/clamav/** krw,
APPARMOR
    fi
    apparmor_parser -r /etc/apparmor.d/usr.bin.freshclam
fi
cat > /usr/local/bin/jkm-clamscan <<'SCANNER'
#!/usr/bin/env bash
set -euo pipefail
stamp=/var/lib/jkmichaels/clamav/updated_at
if [[ ! -r "$stamp" ]] || (( $(date +%s) - $(stat -c %Y "$stamp") > 172800 )); then
    echo 'JK Michaels malware definitions have not been verified in 48 hours.' >&2
    exit 2
fi
exec /usr/bin/clamscan --database=/var/lib/jkmichaels/clamav "$@"
SCANNER
chmod 0755 /usr/local/bin/jkm-clamscan
cat > /etc/systemd/system/jkm-clamav-update.service <<'SERVICE'
[Unit]
Description=JK Michaels malware signature verification
After=network-online.target
Wants=network-online.target

[Service]
Type=oneshot
User=clamav
Group=clamav
ExecStart=/usr/bin/freshclam --config-file=/etc/jkmichaels/freshclam.conf --stdout
ExecStartPost=/usr/bin/touch /var/lib/jkmichaels/clamav/updated_at
TimeoutStartSec=900
NoNewPrivileges=true
PrivateTmp=true
ProtectSystem=strict
ProtectHome=true
ReadWritePaths=/var/lib/jkmichaels/clamav
MemoryMax=2G
CPUWeight=20
SERVICE
cat > /etc/systemd/system/jkm-clamav-update.timer <<'TIMER'
[Unit]
Description=Check JK Michaels malware definitions every six hours

[Timer]
OnBootSec=5min
OnUnitActiveSec=6h
RandomizedDelaySec=10min
Persistent=true

[Install]
WantedBy=timers.target
TIMER
systemctl daemon-reload
systemctl enable --now jkm-clamav-update.timer
systemctl start jkm-clamav-update.service
/usr/local/bin/jkm-clamscan --version
systemctl is-active jkm-clamav-update.timer
