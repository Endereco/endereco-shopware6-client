#!/bin/bash

error_found=0

# Check src directory if it exists
if [ -d "src" ]; then
    echo "Checking src directory"
    output=$(vendor/bin/phpmd src text unusedcode)
    if [ $? -ne 0 ] || [ -n "$output" ]; then
        echo "$output"
        error_found=1
    fi
fi

# Check tests directory if it exists
if [ -d "tests" ]; then
    echo "Checking tests directory"
    output=$(vendor/bin/phpmd tests text unusedcode)
    if [ $? -ne 0 ] || [ -n "$output" ]; then
        echo "$output"
        error_found=1
    fi
fi

# Check if any errors were found
if [ $error_found -eq 1 ]; then
    echo "Errors found. Exiting with error code."
    exit 1
else
    echo "No errors found. Exiting successfully."
    exit 0
fi