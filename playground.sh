#!/bin/bash

# List of supported Shopware versions
declare -a versions=("6.5.0.0" "6.5.1.1" "6.5.2.1" "6.5.3.3" "6.5.4.1" "6.5.5.2" "6.5.6.1" "6.5.7.4" "6.5.8.15")

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
    
    # Prepare Docker run options
    docker_options="-d --name shopware-$version -v $(pwd):/var/www/html/custom/plugins/EnderecoShopware6Client -p 80:80"
    sleep_time=10
    
    # Add XDebug options if requested
    if [[ "$enable_xdebug" =~ ^[Yy]$ ]]; then
        docker_options="$docker_options --add-host host.docker.internal=host-gateway --env=XDEBUG_ENABLED=1"
        sleep_time=15
        echo "XDebug enabled - container will take longer to start"
    fi
    
    # Start the Docker container
    docker run $docker_options dockware/dev:$version

    sleep $sleep_time
    
    echo "Container started, Shopware 6 is available at http://localhost"
    echo "Your plugin is mounted at /var/www/html/custom/plugins/EnderecoShopware6Client"
    
    if [[ "$enable_xdebug" =~ ^[Yy]$ ]]; then
        echo "XDebug is enabled and ready for debugging"
    fi

    # Activate the plugin
    docker exec shopware-$version bash -c "cd /var/www/html && ./bin/console plugin:refresh && ./bin/console plugin:install --activate EnderecoShopware6Client"

    echo "Plugin is activated."
else
    echo "Invalid version. Please enter a valid version from the list."
fi
