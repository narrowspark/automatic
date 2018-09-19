#!/usr/bin/env bash

source ./build/travis/try_catch.sh
source ./build/travis/tfold.sh

if [[ "$PHPUNIT" = true ]]; then
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

            try
                tfold "$TESTSUITE" "$TEST -c ./phpunit.xml.dist --testsuite=\"$TESTSUITE\"";
            catch || {
                exit 1
            }
        fi
    done
fi
