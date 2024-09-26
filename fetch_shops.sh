#!/bin/bash

# Define an array of versions
versions=("6.5.0.0" "6.5.1.1" "6.5.2.1" "6.5.3.3" "6.5.4.1" "6.5.5.2" "6.5.6.1" "6.5.7.4" "6.5.8.13")

# Loop through each version
for version in "${versions[@]}"; do
    echo "Processing version $version..."
    
    # Define image name
    image_name="dockware/dev:$version"
    
    # Pull the Docker image
    docker pull "$image_name"
    
    # Start a temporary container
    container_id=$(docker run -d --rm "$image_name" tail -f /dev/null)

    # Create target directory for shop files
    rm -rf "./shops/$version"
    mkdir -p "./shops/$version"
    
    # Copy files from container to host
    docker cp "$container_id:/var/www/html/." "./shops/$version"
    
    # Stop and remove the container
    docker stop "$container_id"
    
    # Change ownership of the copied files to the current user
    sudo chown -R $(whoami):$(whoami) "./shops/$version"
    
    echo "Completed processing for version $version"
done

echo "All versions processed successfully."
