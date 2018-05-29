#!/usr/bin/env bash

. .git/hooks/modules/colors.sh
. .git/hooks/modules/util.sh

PROJECT=$(pwd)

FILES=$(git diff --cached --name-only --diff-filter=ACMR HEAD | grep '\.css\?$')

if [ "$FILES" != "" ]
then
    printTable ',' "${ORANGE}CSS Linting...${NC}"
    printf "${LIGHT_CYAN}"
    printTable ' ' "$FILES"
    printf "${NC}"
    
    ./node_modules/stylelint/bin/stylelint.js $FILES --fix

    ERROR=$?
    if [ $ERROR != 0 ]
    then
        exit 1
    fi
fi