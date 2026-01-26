#!/bin/bash
# Deploy Script - Build React + Push to Production
# Este é o padrão de deploy para SOCIMOB

set -e

echo "🚀 === SOCIMOB Deploy Script === 🚀"
echo ""

# 1. Build React localmente
echo "1️⃣  Building React frontend..."
cd client
pnpm install --frozen-lockfile
pnpm build
cd ..

# 2. Copiar build para public/
echo "2️⃣  Copying build to public/..."
rm -rf public/assets public/index.html
cp -r dist/public/* public/

# 3. Otimizar PHP autoloader
echo "3️⃣  Optimizing PHP autoloader..."
composer dump-autoload --optimize

# 4. Commit changes
echo "4️⃣  Committing build..."
git add -A
git commit -m "build: react production build $(date +%Y-%m-%d)" || echo "No changes to commit"

# 5. Deploy via SSH
echo "5️⃣  Deploying to production via SSH..."
bash deploy.sh

echo ""
echo "✅ Deploy completed successfully!"
