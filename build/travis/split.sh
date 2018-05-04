#!/bin/bash

source ./build/travis/try_catch.sh
source ./build/travis/tfold.sh

git subsplit init git@github.com:narrowspark/discovery.git

cp ./build/common/composer.json ./.subsplit/src/Common/composer.json
cp ./build/common/README.md ./.subsplit/src/Common/README.md
cp ./LICENSE ./.subsplit/src/Common/LICENSE

component_array=(
    'src/Common:git@github.com:narrowspark/discovery-common.git'
)

for i in "${component_array[@]}"
do
    try
        tfold ${i##*:} "git subsplit publish $i --update --heads='master'";
    catch || {
        exit 1
    }
done

rm -rf .subsplit
