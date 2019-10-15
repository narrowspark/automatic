#!/bin/bash

source ./build/travis/try_catch.sh
source ./build/travis/tfold.sh

git subsplit init git@github.com:narrowspark/automatic.git

component_array=(
    'src/Common:git@github.com:narrowspark/automatic-common.git'
    'src/Prefetcher:git@github.com:narrowspark/automatic-composer-prefetcher.git'
    'src/Security:git@github.com:narrowspark/automatic-security-audit.git'
)

for i in "${component_array[@]}"
do
    try
        if [[ ! -z "$TRAVIS_TAG" ]]; then
            OPTION="--tags=\"${TRAVIS_TAG}\"";
        else
            OPTION="--heads=\"master\" --no-tags";
        fi

        tfold ${i##*:} "git subsplit publish $i --update ${OPTION}";
    catch || {
        exit 1
    }
done

rm -rf .subsplit
