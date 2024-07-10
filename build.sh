#!/bin/bash

# Make store version
rm -rf EnderecoShopware6ClientStore
rsync -ar --exclude 'vendor' --exclude 'bin' --exclude '*.zip' --exclude 'node_modules' --exclude 'shops' ./* ./EnderecoShopware6ClientStore

# Clean up
rm -rf EnderecoShopware6ClientStore/node_modules
rm -f EnderecoShopware6ClientStore/.gitignore
rm -f EnderecoShopware6ClientStore/.idea
rm -f EnderecoShopware6ClientStore/*.sh
rm -f EnderecoShopware6ClientStore/*.neon
rm -f EnderecoShopware6ClientStore/composer.lock
rm -f EnderecoShopware6ClientStore/check_imports.php
rm -f EnderecoShopware6ClientStore/docker-compose.yml
rm -f EnderecoShopware6ClientStore/*.js
rm -f EnderecoShopware6ClientStore/endereco.scss
rm -f EnderecoShopware6ClientStore/package.json
rm -f EnderecoShopware6ClientStore/package-lock.json
rm -f EnderecoShopware6ClientStore/webpack.config.js

# Prevent encoding issues with sed
export LC_ALL=C

# Rename
find ./EnderecoShopware6ClientStore -type f ! -name '*.bak' -exec sed -i.bak -e 's/Shopware6Client/Shopware6ClientStore/g' {} \;
find ./EnderecoShopware6ClientStore -type f ! -name '*.bak' -exec sed -i.bak -e 's/ (Download)//g' {} \;
find ./EnderecoShopware6ClientStore -type f ! -name '*.bak' -exec sed -i.bak -e 's/endereco-shopware6-client/endereco-shopware6-client-store/g' {} \;
find ./EnderecoShopware6ClientStore -type f ! -name '*.bak' -exec sed -i.bak -e 's/endereco_shopware6_client/endereco_shopware6_client_store/g' {} \;
find ./EnderecoShopware6ClientStore -type f ! -name '*.bak' -exec sed -i.bak -e 's/enderecoshopware6client/enderecoshopware6clientstore/g' {} \;
mv ./EnderecoShopware6ClientStore/src/EnderecoShopware6Client.php ./EnderecoShopware6ClientStore/src/EnderecoShopware6ClientStore.php
mv ./EnderecoShopware6ClientStore/src/Resources/public/administration/js/endereco-shopware6-client.js ./EnderecoShopware6ClientStore/src/Resources/public/administration/js/endereco-shopware6-client-store.js


# Find all Twig files and process them
TWIG_FILES_PATH="./EnderecoShopware6ClientStore/src/Resources/views"
find "$TWIG_FILES_PATH" -type f ! -name '*.bak' -name "*.twig" | while read -r file; do
    # Remove single-line JavaScript comments
    sed -i.bak -e '/\/\/.*/d' "$file"

    # Remove multi-line JavaScript comments
    # This is more complex and might not work correctly for all cases
    sed -i.bak -e '/\/\*\*/,/\*\//d' "$file"
done

find ./EnderecoShopware6ClientStore -type f -name '*.bak' -delete
rm -f EnderecoShopware6ClientStore-rc.zip
zip -r EnderecoShopware6ClientStore-rc.zip EnderecoShopware6ClientStore
rm -rf EnderecoShopware6ClientStore

# Make github version
rm -rf EnderecoShopware6Client
rsync -ar --exclude 'vendor' --exclude 'bin' --exclude '*.zip' --exclude 'node_modules' --exclude 'shops' ./* ./EnderecoShopware6Client

# Clean up
rm -rf EnderecoShopware6Client/node_modules
rm -f EnderecoShopware6Client/.gitignore
rm -f EnderecoShopware6Client/.idea
rm -f EnderecoShopware6Client/*.sh
rm -f EnderecoShopware6Client/*.neon
rm -f EnderecoShopware6Client/composer.lock
rm -f EnderecoShopware6Client/check_imports.php
rm -f EnderecoShopware6Client/docker-compose.yml
rm -f EnderecoShopware6Client/*.js
rm -f EnderecoShopware6Client/endereco.scss
rm -f EnderecoShopware6Client/package.json
rm -f EnderecoShopware6Client/package-lock.json
rm -f EnderecoShopware6Client/webpack.config.js
TWIG_FILES_PATH="./EnderecoShopware6Client/src/Resources/views"
find "$TWIG_FILES_PATH" -type f ! -name '*.bak' -name "*.twig" | while read -r file; do
    # Remove single-line JavaScript comments
    sed -i.bak -e '/\/\/.*/d' "$file"

    # Remove multi-line JavaScript comments
    # This is more complex and might not work correctly for all cases
    sed -i.bak -e '/\/\*\*/,/\*\//d' "$file"
done

find ./EnderecoShopware6Client -type f -name '*.bak' -delete
rm -f EnderecoShopware6Client-rc.zip
zip -r EnderecoShopware6Client-rc.zip EnderecoShopware6Client
rm -rf EnderecoShopware6Client