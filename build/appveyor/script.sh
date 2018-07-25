#!/usr/bin/env bash

source ./build/appveyor/try_catch.sh

for f in ./src/*; do
    if [[ -d "$f" && ! -L "$f" ]]; then
        TYPE="$(basename "$f")";

        if [[ "$TYPE" = "Common" ]]; then
            TESTSUITE="Narrowspark Discovery Common Test Suite";
        elif [[ "$TYPE" = "Discovery" ]]; then
            TESTSUITE="Narrowspark Discovery Test Suite";
        fi

        echo "";
        echo -e "$TESTSUITE";
        echo "";

        try
            sh vendor/bin/phpunit --verbose -c ./phpunit.xml.dist --testsuite="$TESTSUITE";
        catch || {
            exit 1
        }
    fi
done
