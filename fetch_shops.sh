#!/bin/bash
# Define an array of versions
versions=("6.7.0.1" "6.7.1.2" "6.7.2.2" "6.7.3.1" "6.7.4.2" "6.7.5.1" "6.7.6.2" "6.7.7.1" "6.7.8.2" "6.7.9.1" "6.7.10.2" "6.7.11.1" "6.7.12.2")

# Loop through each version
for version in "${versions[@]}"; do
    # Check if directory already exists
    if [ -d "./shops/$version" ]; then
        echo "Skipping version $version - directory already exists"
        continue
    fi

    echo "Processing version $version..."

    # Download Shopware from GitHub
    github_url="https://github.com/shopware/production/archive/refs/tags/v${version}.zip"
    temp_zip="/tmp/shopware-${version}.zip"

    echo "Downloading from GitHub: $github_url"
    curl -sL "$github_url" -o "$temp_zip"

    if [ $? -ne 0 ]; then
        echo "Failed to download Shopware $version"
        continue
    fi

    # Create target directory
    mkdir -p "./shops/$version"

    # Extract the archive (GitHub creates a folder like "template-6.7.4.2")
    echo "Extracting archive..."
    unzip -q "$temp_zip" -d "/tmp"

    # Move files from extracted folder to shops directory
    extracted_folder="/tmp/template-${version}"
    if [ -d "$extracted_folder" ]; then
        mv "$extracted_folder"/* "./shops/$version/"
        mv "$extracted_folder"/.* "./shops/$version/" 2>/dev/null || true
        rm -rf "$extracted_folder"
    else
        echo "Warning: Extracted folder structure unexpected"
        # Try to find the actual folder
        actual_folder=$(find /tmp -maxdepth 1 -type d -name "*${version}*" | head -1)
        if [ -n "$actual_folder" ]; then
            mv "$actual_folder"/* "./shops/$version/"
            mv "$actual_folder"/.* "./shops/$version/" 2>/dev/null || true
            rm -rf "$actual_folder"
        fi
    fi

    # Clean up temp zip
    rm -f "$temp_zip"

    # Disable audit block-insecure in composer.json to allow installation of older packages
    echo "Disabling audit security blocks in composer.json..."
    php -r '$json = json_decode(file_get_contents("./shops/'$version'/composer.json"), true); $json["config"]["audit"]["block-insecure"] = false; file_put_contents("./shops/'$version'/composer.json", json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));'

    # Install composer dependencies using Docker with PHP 8.3
    echo "Installing composer dependencies for version $version using PHP 8.3..."
    docker run --rm \
        -v "$(pwd)/shops/$version:/app" \
        -w /app \
        composer:2 \
        composer install --no-dev --no-interaction --ignore-platform-reqs

    echo "Completed processing for version $version"
done
echo "All versions processed successfully."