#!/usr/bin/env bash

source ./build/appveyor/try_catch.sh

for f in ./src/*; do
    if [[ -d "$f" && ! -L "$f" ]]; then
        TYPE="$(basename "$f")";

        if [[ "$TYPE" = "Automatic" ]]; then
            TESTSUITE="Narrowspark Automatic Test Suite";
        elif [[ "$TYPE" = "Common" ]]; then
            TESTSUITE="Narrowspark Automatic Common Test Suite";
        elif [[ "$TYPE" = "Security" ]]; then
            TESTSUITE="Narrowspark Automatic Security Test Suite";
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
