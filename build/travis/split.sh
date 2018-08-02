#!/bin/bash

source ./build/travis/try_catch.sh
source ./build/travis/tfold.sh

git subsplit init git@github.com:narrowspark/automatic.git

component_array=(
    'src/Common:git@github.com:narrowspark/automatic-common.git'
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
