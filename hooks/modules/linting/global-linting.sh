#!/usr/bin/env bash

. .git/hooks/modules/colors.sh
. .git/hooks/modules/util.sh

PROJECT=$(pwd)

FILES=$(git diff --diff-filter=ACMRT --cached --name-only | grep '\.scss\|\.json\|\.css\|\.jsx\|\.js\?$')

if [ "$FILES" != "" ]
then
    printTable ',' "${ORANGE}Global Linting...${NC}"
   ./node_modules/prettier/bin-prettier.js --write $FILES

    ERROR=$?
    if [ $ERROR != 0 ]
    then
        exit 1
    fi
fi