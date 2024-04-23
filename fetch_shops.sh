#!/bin/bash

# Define an array of versions
versions=("6.6.0.0" "6.6.1.1")

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
