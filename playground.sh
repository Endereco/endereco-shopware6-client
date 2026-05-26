#!/bin/bash

# List of supported Shopware versions
declare -a versions=("6.6.0.0" "6.6.1.2" "6.6.2.0" "6.6.3.1" "6.6.4.1" "6.6.5.1" "6.6.6.1" "6.6.7.1" "6.6.8.2" "6.6.9.0" "6.6.10.18")

# Function to determine which dockware image to use based on version
get_dockware_image() {
  local version=$1
  # dockware/dev has 6.6.0.0 through 6.6.10.6
  # dockware/shopware has 6.6.10.7 and newer
  if [[ "$version" =~ ^6\.6\.[0-9]\. ]] || [[ "$version" =~ ^6\.6\.10\.[0-6]$ ]]; then
    echo "dockware/dev"
  else
    echo "dockware/shopware"
  fi
}

# Function to check if an element is in the array
containsElement () {
  local e match="$1"
  shift
  for e; do [[ "$e" == "$match" ]] && return 0; done
  return 1
}

echo "Available Shopware 6 versions:"
printf " - %s\n" "${versions[@]}"

# Ask the user for the desired version
read -p "Enter the version of Shopware 6 you want to use: " version

# Ask if user wants to enable XDebug
read -p "Enable XDebug for debugging? (y/N): " enable_xdebug

# Check if the version is valid
if containsElement "$version" "${versions[@]}"; then
    echo "Preparing to start Shopware 6 in Dockware container with version $version"
    
    # Check and remove existing container if necessary
    if [ "$(docker ps -aq -f name=^shopware-$version$)" ]; then
        echo "Removing existing container named shopware-$version"
        docker rm -f shopware-$version
    fi
    
    # Determine which dockware image to use
    dockware_image=$(get_dockware_image "$version")
    echo "Using Docker image: $dockware_image:$version"

    # Prepare Docker run options (no volume mount - we'll copy files instead)
    docker_options="-d --name shopware-$version -p 80:80"

    # Add XDebug options if requested
    if [[ "$enable_xdebug" =~ ^[Yy]$ ]]; then
        docker_options="$docker_options --add-host host.docker.internal=host-gateway --env=XDEBUG_ENABLED=1"
        echo "XDebug enabled - container will take longer to start"
    fi

    # Start the Docker container
    docker run $docker_options $dockware_image:$version

    echo "Waiting for container to be ready..."

    # Wait for the container to be fully ready by checking if console is accessible
    max_wait=60
    wait_count=0
    until docker exec shopware-$version php /var/www/html/bin/console list > /dev/null 2>&1; do
        if [ $wait_count -ge $max_wait ]; then
            echo "Container failed to become ready in time"
            exit 1
        fi
        echo -n "."
        sleep 1
        ((wait_count++))
    done
    echo ""

    echo "Container is ready! Copying plugin files..."

    # Copy plugin files to container (excluding vendor, node_modules, shops, .git)
    docker exec shopware-$version mkdir -p /var/www/html/custom/plugins/EnderecoShopware6Client
    docker cp ./src shopware-$version:/var/www/html/custom/plugins/EnderecoShopware6Client/
    docker cp ./composer.json shopware-$version:/var/www/html/custom/plugins/EnderecoShopware6Client/

    # Set correct ownership inside container
    docker exec -u root shopware-$version chown -R www-data:www-data /var/www/html/custom/plugins/EnderecoShopware6Client

    echo "Plugin files copied. Shopware 6 is available at http://localhost"

    if [[ "$enable_xdebug" =~ ^[Yy]$ ]]; then
        echo "XDebug is enabled and ready for debugging"
    fi

    # Activate the plugin
    echo "Activating plugin..."
    docker exec shopware-$version php /var/www/html/bin/console plugin:refresh
    docker exec shopware-$version php /var/www/html/bin/console plugin:install --activate EnderecoShopware6Client
    docker exec shopware-$version php /var/www/html/bin/console cache:clear

    echo "Plugin is activated."
else
    echo "Invalid version. Please enter a valid version from the list."
fi

