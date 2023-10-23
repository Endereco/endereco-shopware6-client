#!/usr/bin/env bash

#PLATFORM_ROOT="$(git rev-parse --show-toplevel)"
#PROJECT_ROOT="${PROJECT_ROOT:-"$(cd "$PLATFORM_ROOT"/.. && git rev-parse --show-toplevel)"}"

PHP_FILES="$(git diff --cached --name-only --diff-filter=ACMR HEAD | grep -E '\.(php)$')"

# exit on non-zero return code
set -e

if [[ -z "$PHP_FILES" ]]
then
    exit 0
fi

if [[ -n "$PHP_FILES" ]]
then
    for FILE in ${PHP_FILES}
    do
        echo "Check php syntax in $FILE"
        php -l -d display_errors=0 "$FILE" 1> /dev/null
    done

    php vendor/bin/phpcs --standard=PSR1,PSR2,PSR12  --extensions=php ./src
    php vendor/bin/phpstan analyse
fi
