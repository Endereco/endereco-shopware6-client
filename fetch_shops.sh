#!/bin/bash
# Define an array of versions
versions=("6.6.0.0" "6.6.1.2" "6.6.2.0" "6.6.3.1" "6.6.4.1" "6.6.5.1" "6.6.6.1" "6.6.7.1" "6.6.8.2" "6.6.9.0" "6.6.10.4")

# Loop through each version
for version in "${versions[@]}"; do
    # Check if directory already exists
    if [ -d "./shops/$version" ]; then
        echo "Skipping version $version - directory already exists"
        continue
    fi

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