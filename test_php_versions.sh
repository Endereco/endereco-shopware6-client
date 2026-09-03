#!/bin/bash

# Define PHP versions to test against
versions=("8.2" "8.3" "8.4")

# Find all PHP files in the current directory and subdirectories, excluding vendor, node_modules and shops folders
php_files=$(find . -type d \( -path './vendor' -o -path './node_modules' -o -path './shops' \) -prune -o -type f -name '*.php' -print)

# Run syntax check for a single PHP version inside one container (all files at once).
# Progress and status go to stderr (terminal). Error lines go to stdout (captured by parent via FD).
run_version() {
    local version=$1
    echo "Testing with PHP $version..." >&2

    local container_output
    if container_output=$(echo "$php_files" | docker run --rm -i \
        -v "$(pwd)":/app -w /app "php:$version-cli" \
        sh -c '
            version=$1
            errors=0
            while IFS= read -r f; do
                printf "Testing %s with PHP %s...\n" "$f" "$version" >&2
                out=$(php -l "$f" 2>&1)
                if [ $? -ne 0 ]; then
                    first_line=$(printf "%s" "$out" | grep -v "^$" | head -1)
                    printf "%s\t%s\n" "$f" "$first_line"
                    errors=1
                fi
            done
            exit $errors
        ' _ "$version"); then
        echo "PHP $version: OK" >&2
    else
        while IFS=$'\t' read -r file output; do
            printf "%s\t%s\t%s\n" "$version" "$file" "$output"
        done <<< "$container_output"
        exit 1
    fi
}

# Run all versions in parallel; each gets its own FD to capture stdout (error lines only)
pids=()
pids_versions=()
fds=()

for version in "${versions[@]}"; do
    exec {fd}< <(run_version "$version")
    pids+=($!)
    pids_versions+=("$version")
    fds+=("$fd")
done

# Wait for all parallel jobs and read error lines from each version's pipe buffer
failed_versions=()
all_errors=()

for i in "${!pids[@]}"; do
    wait "${pids[$i]}"
    exit_code=$?
    fd="${fds[$i]}"

    if [ $exit_code -ne 0 ]; then
        failed_versions+=("${pids_versions[$i]}")
        while IFS= read -r line; do
            all_errors+=("$line")
        done <&"$fd"
    fi

    exec {fd}<&-
done

if [ ${#failed_versions[@]} -gt 0 ]; then
    echo ""
    echo "Syntax errors found:"
    for error_line in "${all_errors[@]}"; do
        IFS=$'\t' read -r version file output <<< "$error_line"
        printf "Syntax error found in %s with PHP %s:\n%s\n" "$file" "$version" "$output"
    done
    exit 1
else
    echo "No syntax errors found."
fi
