#!/usr/bin/env bash

. .git/hooks/modules/colors.sh
. .git/hooks/modules/util.sh

.git/hooks/modules/linting/css-linting.sh
.git/hooks/modules/linting/js-linting.sh
.git/hooks/modules/linting/json-linting.sh
.git/hooks/modules/linting/php-linting.sh
.git/hooks/modules/linting/global-linting.sh

