#!/bin/bash

SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# List of supported Shopware versions
declare -a versions=("6.7.0.1" "6.7.1.2" "6.7.2.2" "6.7.3.1" "6.7.4.2" "6.7.5.1" "6.7.6.2" "6.7.7.1" "6.7.8.2" "6.7.9.1" "6.7.10.2" "6.7.11.1" "6.7.12.2")

# Function to determine which dockware image to use based on version
get_dockware_image() {
  local version=$1
  # Versions 6.7.2.x and newer are in dockware/shopware
  # Older versions remain in dockware/dev
  if [[ "$version" =~ ^6\.7\.[0-1]\. ]]; then
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

    # Prepare Docker run options.
    # Plugin source is mounted read-only at /opt/endereco-src (a path Dockware's
    # entrypoint never touches), then symlinked into the plugin directory after
    # startup so the entrypoint's chown sweep over /var/www/html can't touch
    # our host files.
    docker_options="-d --name shopware-$version -p 80:80"
    docker_options="$docker_options -v $SCRIPT_DIR/src:/opt/endereco-src/src:ro"
    docker_options="$docker_options -v $SCRIPT_DIR/composer.json:/opt/endereco-src/composer.json:ro"

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

    echo "Container is ready! Linking plugin into container..."

    docker exec -u root shopware-$version mkdir -p /var/www/html/custom/plugins
    docker exec -u root shopware-$version rm -rf /var/www/html/custom/plugins/EnderecoShopware6Client
    docker exec -u root shopware-$version ln -s /opt/endereco-src /var/www/html/custom/plugins/EnderecoShopware6Client
    docker exec -u root shopware-$version chown -h www-data:www-data /var/www/html/custom/plugins/EnderecoShopware6Client

    echo "Plugin linked. Shopware 6 is available at http://localhost"

    if [[ "$enable_xdebug" =~ ^[Yy]$ ]]; then
        echo "XDebug is enabled and ready for debugging"
    fi

    # Activate the plugin
    echo "Activating plugin..."
    docker exec shopware-$version php /var/www/html/bin/console plugin:refresh
    docker exec shopware-$version php /var/www/html/bin/console plugin:install --activate EnderecoShopware6Client
    docker exec shopware-$version php /var/www/html/bin/console cache:clear

    echo "Plugin is activated and cache cleared."
else
    echo "Invalid version. Please enter a valid version from the list."
fi

