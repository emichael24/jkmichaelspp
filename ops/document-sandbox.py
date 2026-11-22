#!/usr/bin/python3
"""Constrain JK Michaels' PDF/PPTX parsers. Install root-owned, never setuid.

Only a single input and one document-preview directory enter the namespace.
Network, application configuration, other content and host processes are absent.
"""
import os
from pathlib import Path
import re
import resource
import signal
import subprocess
import sys
from urllib.parse import unquote, urlparse

PRIVATE = Path('/var/www/vhosts/jkmichaelspm.com/app.jkmichaelspm.com/storage/app/private')
UUID = r'[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}'


def private_path(value, pattern, directory=False):
    path = Path(value)
    if not path.is_absolute() or path.resolve() != path or not path.is_relative_to(PRIVATE):
        raise ValueError('Document path is outside the permitted private storage.')
    if not re.fullmatch(pattern, path.relative_to(PRIVATE).as_posix()):
        raise ValueError('Document path has an unsupported layout.')
    if directory and not path.is_dir():
        raise ValueError('Document preview directory does not exist.')
    if not directory and not path.is_file():
        raise ValueError('Document input does not exist.')
    return path


def sandbox(input_file=None, output_directory=None):
    command = ['/usr/bin/bwrap', '--unshare-all', '--die-with-parent', '--new-session', '--cap-drop', 'ALL',
               '--ro-bind', '/usr', '/usr', '--symlink', 'usr/bin', '/bin', '--symlink', 'usr/lib', '/lib',
               '--symlink', 'usr/lib64', '/lib64', '--proc', '/proc', '--dev', '/dev', '--tmpfs', '/tmp',
               '--dir', '/input', '--clearenv', '--setenv', 'PATH', '/usr/bin:/bin', '--setenv', 'HOME', '/tmp',
               '--setenv', 'LANG', 'C.UTF-8', '--setenv', 'SAL_USE_VCLPLUGIN', 'svp']
    for config in ['/etc/fonts', '/etc/ld.so.cache', '/etc/libreoffice']:
        if Path(config).exists():
            command += ['--ro-bind', config, config]
    command += ['--bind', str(output_directory), '/work'] if output_directory else ['--tmpfs', '/work']
    if input_file:
        command += ['--ro-bind', str(input_file), '/input/document']
    return command + ['--chdir', '/work']


def build(tool, args):
    preview = 'document-previews/'+UUID+r'/[0-9a-f]{16}'
    pdf = '(?:media/'+UUID+r'/document|'+preview+r'/presentation\.pdf)'
    if tool == 'pdfinfo' and len(args) == 1:
        source = private_path(args[0], pdf)
        return sandbox(source) + ['/usr/bin/pdfinfo', '/input/document']
    if tool == 'pdftoppm' and len(args) == 10 and args[0] == '-f' and args[2] == '-l' and args[4:6] == ['-singlefile', '-scale-to'] and args[7] == '-png':
        if not args[1].isdigit() or args[1] != args[3] or not 1 <= int(args[1]) <= 200 or args[6] != '1800':
            raise ValueError('Invalid document page or raster dimensions.')
        source = private_path(args[8], pdf)
        target = Path(args[9])
        directory = private_path(str(target.parent), preview, directory=True)
        if target.name != 'render-'+args[1]:
            raise ValueError('Invalid raster output name.')
        return sandbox(source, directory) + ['/usr/bin/pdftoppm', *args[:8], '/input/document', '/work/'+target.name]
    if tool == 'soffice' and len(args) == 10 and args[1:8] == ['--headless', '--nologo', '--nodefault', '--norestore', '--convert-to', 'pdf:impress_pdf_Export', '--outdir']:
        directory = private_path(args[8], preview, directory=True)
        source = private_path(args[9], preview+r'/presentation\.pptx')
        profile = urlparse(args[0].removeprefix('-env:UserInstallation='))
        if not args[0].startswith('-env:UserInstallation=') or profile.scheme != 'file' or profile.netloc or profile.query or profile.fragment or unquote(profile.path) != str(directory/'profile') or source.parent != directory:
            raise ValueError('Invalid isolated office profile.')
        return sandbox(output_directory=directory) + ['/usr/bin/soffice', '-env:UserInstallation=file:///work/profile', *args[1:8], '/work', '/work/presentation.pptx']
    raise ValueError('Unsupported document conversion arguments.')


def limits():
    os.umask(0o077)
    resource.setrlimit(resource.RLIMIT_AS, (1536 * 1024**2, 1536 * 1024**2))
    resource.setrlimit(resource.RLIMIT_CPU, (180, 180))
    resource.setrlimit(resource.RLIMIT_FSIZE, (100 * 1024**2, 100 * 1024**2))
    resource.setrlimit(resource.RLIMIT_NOFILE, (128, 128))
    resource.setrlimit(resource.RLIMIT_NPROC, (64, 64))
    resource.setrlimit(resource.RLIMIT_CORE, (0, 0))


def main():
    if os.geteuid() == 0:
        raise ValueError('Document conversion must use the application account.')
    command = build(sys.argv[1], sys.argv[2:])
    limits()
    child = subprocess.Popen(command, stdin=subprocess.DEVNULL, close_fds=True, start_new_session=True, env={})
    try:
        return child.wait(timeout=185)
    except subprocess.TimeoutExpired:
        os.killpg(child.pid, signal.SIGKILL)
        child.wait()
        return 124


if __name__ == '__main__':
    try:
        sys.exit(main())
    except (ValueError, IndexError, OSError) as error:
        print('Document sandbox refused: '+str(error), file=sys.stderr)
        sys.exit(126)
