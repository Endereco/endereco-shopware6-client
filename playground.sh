#!/bin/bash

# List of supported Shopware versions
declare -a versions=("6.6.0.0" "6.6.1.2" "6.6.2.0" "6.6.3.1" "6.6.4.1" "6.6.5.1" "6.6.6.1" "6.6.7.1" "6.6.8.2" "6.6.9.0" "6.6.10.4")

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

# Check if the version is valid
if containsElement "$version" "${versions[@]}"; then
    echo "Preparing to start Shopware 6 in Dockware container with version $version"
    
    # Check and remove existing container if necessary
    if [ "$(docker ps -aq -f name=^shopware-$version$)" ]; then
        echo "Removing existing container named shopware-$version"
        docker rm -f shopware-$version
    fi
    
    # Start the Docker container
    docker run -d --name shopware-$version -v $(pwd):/var/www/html/custom/plugins/EnderecoShopware6Client -p 80:80 dockware/dev:$version

    sleep 10
    
    echo "Container started, Shopware 6 is available at http://localhost"
    echo "Your plugin is mounted at /var/www/html/custom/plugins/EnderecoShopware6Client"

    # Activate the plugin
    docker exec shopware-$version bash -c "cd /var/www/html && ./bin/console plugin:refresh && ./bin/console plugin:install --activate EnderecoShopware6Client"

    echo "Plugin is activated."
else
    echo "Invalid version. Please enter a valid version from the list."
fi

