#!/usr/bin/env bash

. .git/hooks/modules/colors.sh
. .git/hooks/modules/util.sh

PROJECT=$(pwd)

FILES=$(git diff --cached --name-only --diff-filter=ACMR HEAD | grep '\.php\?$')

if [ "$FILES" != "" ]
then
    printTable ',' "${ORANGE}Checking PHP Lint...${NC}"
    printf "${LIGHT_CYAN}"
    printTable ' ' "$FILES"
    printf "${NC}"

    php -l -d $FILES
    if [ $? != 0 ]
    then
        exit 1
    fi

   ./bin/phpcs --standard=PSR1,PSR2 --encoding=utf-8 -n -p  $FILES

    ERROR=$?
    if [ $ERROR != 0 ]
    then
        exit 1
    fi
fi

exit $?