#!/bin/bash

# Define PHP versions to test against
versions=("7.4" "8.0" "8.1" "8.2" "8.3")

# Find all PHP files in the current directory and subdirectories, excluding vendor, node_modules and shops folders
php_files=$(find . -type f -name "*.php" ! -path "./vendor/*" ! -path "./node_modules/*" ! -path "./shops/*" )

# Initialize a flag to track syntax errors
syntax_error_found=0

# Loop through each version and run tests for each PHP file
for version in "${versions[@]}"
do
    if [ $syntax_error_found -eq 1 ]; then
        break # Exit the version loop if an error is found
    fi
    echo "Testing with PHP $version..."
    for file in $php_files
    do
        if [ $syntax_error_found -eq 1 ]; then
            break # Exit the file loop if an error is found
        fi
        echo "Testing the syntax of $file"
        # Run PHP syntax check and capture the output
        output=$(docker run --rm -v "$(pwd)":/app -w /app php:$version-cli php -l $file)
        if [[ $output == *"Errors parsing"* ]]; then
            echo "Syntax error found in $file with PHP $version: $output"
            syntax_error_found=1
        fi
    done
done

# Check if any syntax errors were found during the tests
if [ $syntax_error_found -eq 1 ]; then
    echo "Syntax errors were found."
    exit 1
else
    echo "No syntax errors found."
fi
