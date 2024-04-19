#!/bin/bash

# Find PHP files and run PHPMD, excluding certain paths
OUTPUT=$(find . -type f -name '*.php' ! -path './vendor/*' ! -path './node_modules/*' ! -path './shops/*' -exec phpmd {} text unusedcode \;)

if [ -n "$OUTPUT" ]; then
    echo "PHPMD reported issues:"
    echo "$OUTPUT"
    exit 1
else
    echo "No issues found."
fi
