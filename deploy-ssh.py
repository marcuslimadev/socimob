#!/usr/bin/env python3
"""
Deploy Script for SOCIMOB
Realiza deploy de produção via SSH

Uso:
  python deploy.py --host <host> --user <user> --env <prod|staging>
  
Exemplo:
  python deploy.py --host lojadaesquina.store --user u815655858 --env prod
"""

import os
import sys
import subprocess
import argparse
from datetime import datetime

class SOCIMOBDeploy:
    def __init__(self, host, user, env='prod'):
        self.host = host
        self.user = user
        self.env = env
        self.remote_path = f"/home/{user}/public_html"
        self.timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
        
    def run_command(self, cmd, description=""):
        """Executar comando localmente"""
        if description:
            print(f"▶️  {description}")
        print(f"   $ {cmd}")
        result = subprocess.run(cmd, shell=True)
        if result.returncode != 0:
            print(f"❌ Erro ao executar: {cmd}")
            sys.exit(1)
            
    def ssh_command(self, cmd):
        """Executar comando remotamente via SSH"""
        full_cmd = f"ssh {self.user}@{self.host} '{cmd}'"
        return subprocess.run(full_cmd, shell=True, capture_output=True, text=True)
    
    def deploy(self):
        """Executar deploy completo"""
        print("\n🚀 ===== SOCIMOB PRODUCTION DEPLOY ===== 🚀")
        print(f"   Host: {self.host}")
        print(f"   User: {self.user}")
        print(f"   Environment: {self.env}")
        print(f"   Timestamp: {self.timestamp}\n")
        
        # 1. Build local
        print("📦 STEP 1: Building React locally...\n")
        self.run_command("cd client && pnpm build", "Building React with Vite")
        
        # 2. Copy to public
        print("\n📁 STEP 2: Copying build to public/...\n")
        self.run_command("rm -rf public/assets public/index.html", "Cleaning old assets")
        self.run_command("cp -r dist/public/* public/", "Copying new build")
        
        # 3. Prepare files
        print("\n📝 STEP 3: Preparing deployment package...\n")
        self.run_command("composer dump-autoload --optimize", "Optimizing autoloader")
        
        # 4. Upload via rsync
        print("\n⬆️  STEP 4: Uploading files via rsync...\n")
        rsync_cmd = (
            f"rsync -avz --delete "
            f"--exclude='.git' "
            f"--exclude='node_modules' "
            f"--exclude='client' "
            f"--exclude='.env.local' "
            f"--exclude='storage/logs' "
            f"--exclude='bootstrap/cache' "
            f"./ {self.user}@{self.host}:{self.remote_path}/"
        )
        self.run_command(rsync_cmd, "Uploading code via rsync")
        
        # 5. Remote commands
        print("\n🔧 STEP 5: Running remote setup commands...\n")
        
        # Run migrations
        response = self.ssh_command("cd /home/u815655858/public_html && php artisan migrate --force")
        print(f"   ✓ Migrations: {response.returncode == 0 and 'OK' or 'CHECK LOG'}")
        
        # Clear cache
        response = self.ssh_command("cd /home/u815655858/public_html && php artisan cache:clear")
        print(f"   ✓ Cache cleared: {response.returncode == 0 and 'OK' or 'CHECK LOG'}")
        
        # Set permissions
        response = self.ssh_command(f"chmod -R 775 /home/u815655858/public_html/storage /home/u815655858/public_html/bootstrap/cache")
        print(f"   ✓ Permissions: {response.returncode == 0 and 'OK' or 'CHECK LOG'}")
        
        print("\n✅ ===== DEPLOY COMPLETED SUCCESSFULLY ===== ✅\n")
        
if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="SOCIMOB Production Deployer")
    parser.add_argument("--host", required=True, help="Remote host (e.g., lojadaesquina.store)")
    parser.add_argument("--user", required=True, help="SSH user (e.g., u815655858)")
    parser.add_argument("--env", default="prod", help="Environment (prod/staging)")
    
    args = parser.parse_args()
    
    deployer = SOCIMOBDeploy(args.host, args.user, args.env)
    deployer.deploy()
