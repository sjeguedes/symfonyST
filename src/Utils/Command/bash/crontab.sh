#!/usr/bin/env bash
# IMPORTANT! this file works only inside Symfony project directory or sub directories
# Execute this file: ./crontab.sh in public working directory (PWD)
# give it execute permissions: chmod +x ./crontab.sh
# Find php binary path
PHP=$(command -v php)
# Get public working directory
ST_CURRENT_PATH="$(pwd)"
# Search for Symfony console absolute path
DIRECTORY_TO_FIND="bin"
# Init var to empty string
PATH_TO_SF_CONSOLE=""
# Loop in pwd to find "bin" directory
while true; do
    # Try to find "bin" directory
    if [[ -d "$ST_CURRENT_PATH/$DIRECTORY_TO_FIND" ]]; then
        PATH_TO_SF_CONSOLE="$ST_CURRENT_PATH/$DIRECTORY_TO_FIND/console"
    fi
    if [[ $ST_CURRENT_PATH =~ ^/?[^/]+$ ]]; then
        break
    fi
    ST_CURRENT_PATH="${ST_CURRENT_PATH%/*}"
done
# Exit if path to symfony console is not found
[[ -z "$PATH_TO_SF_CONSOLE" ]] && exit
# Use absolute path to php binary and symfony console run command correctly
ST_CALL_SYMFONY_CONSOLE="$PHP $PATH_TO_SF_CONSOLE"
# Use "eval" to test command
ST_DELETE_UNUSED_IMAGE_COMMAND="app:delete-unused-image --call=automatic --category=all --temporary=1 --regexmode=1 --timelimit=86400"
# Define a cron job: once a day at midnight without email
ST_CRON_JOB1="0 0 * * * $ST_CALL_SYMFONY_CONSOLE $ST_DELETE_UNUSED_IMAGE_COMMAND 2>&1"
# Append cronjob in crontab
# First create crontab for current user if it does not exist (can emit state for use priviledge or crontab existence)
crontab -u "$USER" -e
# Write out current crontab in mycronjobs temp file
crontab -l > mycronjobs
# Echo new cron into mycronjobs temp file
echo "$ST_CRON_JOB1" >> mycronjobs
# Install new mycronjobs temp file and remove it after
crontab mycronjobs
rm mycronjobs
exit
