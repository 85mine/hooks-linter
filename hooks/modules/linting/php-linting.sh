#!/usr/bin/env bash

. .git/hooks/modules/colors.sh
. .git/hooks/modules/util.sh

PROJECT=$(pwd)

FILES=$(git diff --cached --name-only --diff-filter=ACMR HEAD | grep '\.php\?$')

if [ "$FILES" != "" ]
then
    printTable ',' "${ORANGE}PHP Linting...${NC}"
    printf "${LIGHT_CYAN}"
    printTable ' ' "$FILES"
    printf "${NC}"

    php -l -d display_errors=0 $FILES

    if [ $? != 0 ]
    then
        exit 1
    fi

    ./bin/phpcbf --standard=PSR1,PSR2 --encoding=utf-8 -n -p $FILES

    for FILE in $FILES
    do
        ./bin/php-cs-fixer fix $FILE --using-cache=no
    done

    ERROR=$?
    if [ $ERROR != 0 ]
    then
        exit 1
    fi
fi