#!/usr/bin/env bash

. .git/hooks/modules/colors.sh
. .git/hooks/modules/util.sh

PROJECT=$(pwd)

FILES=$(git diff --cached --name-only --diff-filter=ACMR HEAD | grep '\.json\?$')

if [ "$FILES" != "" ]
then
    printTable ',' "${ORANGE}Checking JSON Lint...${NC}"
    printf "${LIGHT_CYAN}"
    printTable ' ' "$FILES"
    printf "${NC}"

    for FILE in $FILES
    do
        ./node_modules/jsonlint/lib/cli.js -c $FILE -q
    done

    ERROR=$?
    if [ $ERROR != 0 ]
    then
        exit 1
    fi
fi

exit $?