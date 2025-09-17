#!/bin/bash

mkdir -p /opt/mautic/cron

if [ "$DEPLOYMENT_ENV" = "development" ]; then
  cp -p /templates/mautic_cron_dev /opt/mautic/cron/mautic
else
  cp -p /templates/mautic_cron /opt/mautic/cron/mautic
fi

# register the crontab file for the www-data user
crontab -u www-data /opt/mautic/cron/mautic

# if /tmp/stdout exists clear it out
if [ ! -p /tmp/stdout ]; then
  rm -f /tmp/stdout
fi
# create the fifo file to be able to redirect cron output for non-root users
mkfifo /tmp/stdout
chmod 777 /tmp/stdout

# ensure the PHP env vars are present during cronjobs
declare -p | grep 'PHP_INI_VALUE_' > /tmp/cron.env
declare -p | grep 'MAUTIC' >> /tmp/cron.env

# run cron and print the output
cron -f | tail -f /tmp/stdout
