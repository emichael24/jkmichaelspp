#!/bin/sh
set -eu
test "$(id -u)" = 0
test -x /usr/bin/bwrap
test -x /usr/bin/pdfinfo
test -x /usr/bin/pdftoppm
test -x /usr/bin/soffice
directory=$(CDPATH= cd -- "$(dirname -- "$0")" && pwd)
install -d -m 0755 /usr/local/lib/jkmichaels
install -o root -g root -m 0755 "$directory/document-sandbox.py" /usr/local/lib/jkmichaels/document-sandbox.py
for program in pdfinfo pdftoppm soffice; do
    target="/usr/local/bin/jkm-$program"
    printf '#!/bin/sh\nexec /usr/bin/python3 /usr/local/lib/jkmichaels/document-sandbox.py %s "$@"\n' "$program" > "$target"
    chown root:root "$target"
    chmod 0755 "$target"
done
# Configure DOCUMENT_PDFINFO, DOCUMENT_PDFTOPPM and DOCUMENT_LIBREOFFICE
# during the reviewed application deployment, after conversion tests pass.
