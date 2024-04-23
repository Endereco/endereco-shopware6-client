#!/bin/bash

# Define variables
container_name="admin_builder"
host_ssh_port=2222
plugin_host_dir="./"  # Directory on host where your plugin files are stored
plugin_container_dir="/var/www/html/custom/plugins/EnderecoShopware6Client"  # Directory in the container where plugins are stored
temp_host_dir="./temp_plugin_files"  # Temporary host directory for plugin files

# Start the Dockware container
docker run -d -p $host_ssh_port:22 --name $container_name dockware/dev:6.5.7.4

# Wait for container to fully start up (adjust time as needed)
sleep 10

mkdir -p $temp_host_dir
rsync -az --exclude 'shops/' --exclude '.git/' --exclude 'node_modules/' $plugin_host_dir $temp_host_dir

# Copy plugin files from temporary directory to container and set permissions
docker cp $temp_host_dir $container_name:$plugin_container_dir
docker exec -u root $container_name chown -R www-data:www-data $plugin_container_dir

# Remove temporary directory
rm -rf $temp_host_dir

# Install and activate the plugin via the console
docker exec $container_name php bin/console plugin:refresh
docker exec $container_name php bin/console plugin:install --activate EnderecoShopware6Client
docker exec $container_name php bin/console cache:clear

# Run build script inside the container
docker exec $container_name bash bin/build-js.sh

# Download the created JS bundle to the host
docker cp $container_name:/var/www/html/custom/plugins/EnderecoShopware6Client/src/Resources/public/administration/js/endereco-shopware6-client.js ./src/Resources/public/administration/js/endereco-shopware6-client.js

# Change ownership of the downloaded JS bundle to the current user
chown $(id -u):$(id -g) ./src/Resources/public/administration/js/endereco-shopware6-client.js

# Stop and remove the container if not needed anymore
docker stop $container_name
docker rm $container_name

# Echo completion message
echo "Plugin admin bundle build complete. JS Bundle downloaded."