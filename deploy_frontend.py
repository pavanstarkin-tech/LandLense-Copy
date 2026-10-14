import os
import paramiko

ssh = paramiko.SSHClient()
ssh.set_missing_host_key_policy(paramiko.AutoAddPolicy())
ssh.connect('193.203.185.110', port=65002, username='u833088220', password='Sanvith@27')
sftp = ssh.open_sftp()

dist_dir = r'c:\Users\shese\Desktop\LandLense - Copy\frontend-react\dist'
remote_base = '/home/u833088220/domains/landlense.acheva.in/public_html'

# Upload index.html
sftp.put(os.path.join(dist_dir, 'index.html'), f'{remote_base}/index.html')
print("Uploaded index.html")

# Upload assets
assets_dir = os.path.join(dist_dir, 'assets')
remote_assets = f'{remote_base}/assets'
try:
    sftp.mkdir(remote_assets)
except:
    pass

for fname in os.listdir(assets_dir):
    local_path = os.path.join(assets_dir, fname)
    remote_path = f'{remote_assets}/{fname}'
    # Only upload if file doesn't exist or is .js / .css / .html
    if fname.endswith(('.js', '.css', '.html')):
        try:
            rstat = sftp.stat(remote_path)
            lstat = os.stat(local_path)
            if rstat.st_size == lstat.st_size:
                print(f"Skipping existing: {fname}")
                continue
        except:
            pass
        print(f"Uploading: {fname} ({os.path.getsize(local_path)} bytes)...")
        sftp.put(local_path, remote_path)
        print(f"Done: {fname}")

sftp.close()
ssh.close()
print("Frontend deployment complete!")
