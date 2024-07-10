#!/bin/bash

error_found=0

files=$(find . -type d \( -path './vendor' -o -path './node_modules' -o -path './shops' \) -prune -o -type f -name '*.php' -print)

for file in $files; do
    echo "Checking $file"
    output=$(vendor/bin/phpmd "$file" text unusedcode)
    if [ -n "$output" ]; then
        echo "$output"
        error_found=1
    fi
done

# Check if any errors were found
if [ $error_found -eq 1 ]; then
    echo "Errors found. Exiting with error code."
    exit 1
else
    echo "No errors found. Exiting successfully."
    exit 0
fi