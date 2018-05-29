#!/usr/bin/env bash

cp -rp hooks/* .git/hooks/
chmod +x .git/hooks/pre-commit
chmod -R +x .git/hooks/modules/*
chmod +x bin/*