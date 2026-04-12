import re

conf_path = '/etc/nginx/plesk.conf.d/vhosts/app.jkmichaelspm.com.conf'
patch_path = '/tmp/nginx-patch.conf'

with open(conf_path, 'r') as f:
    conf = f.read()

with open(patch_path, 'r') as f:
    patch = f.read()

marker = '#extension sectigo end'
idx = conf.find(marker)
if idx == -1:
    print('ERROR: marker not found')
    exit(1)

loc_idx = conf.find('location / {', idx)
if loc_idx == -1:
    print('ERROR: location / not found after marker')
    exit(1)

new_conf = conf[:loc_idx] + patch + '\n' + conf[loc_idx:]

with open(conf_path, 'w') as f:
    f.write(new_conf)

print('PATCHED OK')
