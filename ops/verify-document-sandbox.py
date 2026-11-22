#!/usr/bin/python3
"""Probe the installed document sandbox as the application account (run as root).

Repeats the 5 October 2026 isolation probe against the real shared storage and
writes evidence for `php artisan enterprise:check`. Pass --write-evidence to
replace /var/lib/jkmichaels/document-sandbox-health.json when every check passes.
"""
from datetime import datetime, timezone
from pathlib import Path
import hashlib
import json
import os
import pwd
import shutil
import subprocess
import sys
import uuid

FIXTURES = Path('/root/jkm-readiness-validation-20261005')
LIVE = Path('/var/www/vhosts/jkmichaelspm.com/app.jkmichaelspm.com')
PRIVATE = Path('/var/www/vhosts/jkmichaelspm.com/.jkm-shared/storage/app/private')
POLICY = Path('/usr/local/lib/jkmichaels/document-sandbox.py')
EVIDENCE = Path('/var/lib/jkmichaels/document-sandbox-health.json')
PROGRAMS = {'pdfinfo': '/usr/local/bin/jkm-pdfinfo', 'pdftoppm': '/usr/local/bin/jkm-pdftoppm', 'libreoffice': '/usr/local/bin/jkm-soffice'}

assert os.geteuid() == 0, 'Run as root.'
assert PRIVATE.resolve() == PRIVATE, 'Shared private storage path must be canonical.'
account = pwd.getpwnam('jkmichaelspm.com_8glivfes07m')
identifier = str(uuid.uuid4())
digest = hashlib.sha256((FIXTURES / 'sandbox-test.pptx').read_bytes()).hexdigest()[:16]
media = PRIVATE / 'media' / identifier
preview_parent = PRIVATE / 'document-previews' / identifier
preview = preview_parent / digest
assert not media.exists() and not preview_parent.exists()
media.mkdir(parents=True, mode=0o700)
preview.mkdir(parents=True, mode=0o700)
shutil.copyfile(FIXTURES / 'sandbox-test.pdf', media / 'document')
shutil.copyfile(FIXTURES / 'sandbox-test.pdf', media / 'pdf')
shutil.copyfile(FIXTURES / 'sandbox-test.pptx', preview / 'presentation.pptx')
(preview / 'profile/user').mkdir(parents=True)
(preview / 'profile/user/registrymodifications.xcu').write_text('<?xml version="1.0"?><oor:items xmlns:oor="http://openoffice.org/2001/registry"><item oor:path="/org.openoffice.Office.Common/Security/Scripting"><prop oor:name="MacroSecurityLevel" oor:op="fuse"><value>3</value></prop></item></oor:items>')
for base in [media, preview_parent]:
    for path in [base, *base.rglob('*')]:
        os.chown(path, account.pw_uid, account.pw_gid)

checks = {}


def run(name, args, expected=0):
    p = subprocess.run(['runuser', '-u', account.pw_name, '--', *args], capture_output=True, text=True, timeout=195, cwd='/tmp')
    checks[name] = {'passed': p.returncode == expected, 'exit': p.returncode}
    if p.returncode != expected:
        checks[name]['diagnostic'] = p.stderr[-600:]
    return p


try:
    p = run('pdf_metadata', [PROGRAMS['pdfinfo'], str(media / 'document')])
    checks['pdf_metadata']['one_page'] = 'Pages:           1' in p.stdout
    p = run('lesson_upload_metadata', [PROGRAMS['pdfinfo'], str(media / 'pdf')])
    checks['lesson_upload_metadata']['one_page'] = 'Pages:           1' in p.stdout
    run('pdf_raster', [PROGRAMS['pdftoppm'], '-f', '1', '-l', '1', '-singlefile', '-scale-to', '1800', '-png', str(media / 'pdf'), str(preview / 'render-1')])
    checks['pdf_raster']['png_created'] = (preview / 'render-1.png').is_file()
    p = run('pptx_conversion', [PROGRAMS['libreoffice'], '-env:UserInstallation=file://' + str(preview / 'profile'), '--headless', '--nologo', '--nodefault', '--norestore', '--convert-to', 'pdf:impress_pdf_Export', '--outdir', str(preview), str(preview / 'presentation.pptx')])
    checks['pptx_conversion']['pdf_created'] = (preview / 'presentation.pdf').is_file()
    run('reject_configuration_path', [PROGRAMS['pdfinfo'], str((LIVE / '.env').resolve())], 126)
    run('reject_program_arguments', [PROGRAMS['pdfinfo'], '-js', str(media / 'pdf')], 126)
    (media / 'linked').symlink_to(media / 'pdf')
    run('reject_symlink_input', [PROGRAMS['pdfinfo'], str(media / 'linked')], 126)
    run('reject_unresolved_release_path', [PROGRAMS['pdfinfo'], str(LIVE / 'storage/app/private/media' / identifier / 'pdf')], 126)
    probe = "import os,socket,pathlib; assert 'JK_SANDBOX_SENTINEL' not in os.environ; assert not pathlib.Path('/var/www').exists(); assert not pathlib.Path('/root').exists(); s=socket.socket();s.settimeout(1);assert s.connect_ex(('127.0.0.1',22))!=0; print('isolated')"
    launcher = "import importlib.util,subprocess; spec=importlib.util.spec_from_file_location('sandbox','" + str(POLICY) + "'); m=importlib.util.module_from_spec(spec);spec.loader.exec_module(m);m.limits();raise SystemExit(subprocess.run(m.sandbox()+['/usr/bin/python3','-c'," + repr(probe) + "],env={'JK_SANDBOX_SENTINEL':'synthetic'},timeout=20).returncode)"
    run('filesystem_network_environment_isolation', ['/usr/bin/python3', '-c', launcher])
    passed = all(v['passed'] and v.get('one_page', True) and v.get('png_created', True) and v.get('pdf_created', True) for v in checks.values())
    result = {
        'result': 'passed' if passed else 'failed',
        'verified_at': datetime.now(timezone.utc).isoformat(),
        'checks': checks,
        'application_configuration_changed': False,
        'programs': {name: {'path': path, 'sha256': hashlib.sha256(Path(path).read_bytes()).hexdigest()} for name, path in PROGRAMS.items()},
        'policy': {'path': str(POLICY), 'sha256': hashlib.sha256(POLICY.read_bytes()).hexdigest()},
    }
    print(json.dumps(result, indent=2))
    if passed and '--write-evidence' in sys.argv:
        tmp = EVIDENCE.with_suffix('.json.new')
        tmp.write_text(json.dumps(result, indent=2))
        tmp.chmod(0o644)
        tmp.replace(EVIDENCE)
        print('Evidence written to ' + str(EVIDENCE))
    sys.exit(0 if passed else 1)
finally:
    for directory in [media, preview_parent]:
        assert directory.resolve().is_relative_to(PRIVATE) and directory.name == identifier
        shutil.rmtree(directory)
