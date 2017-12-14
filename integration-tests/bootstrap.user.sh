#!/bin/bash
set -uxe

echo
echo "update project dependencies"
cd /home/vagrant/project
curl -sS https://getcomposer.org/installer | php
php composer.phar update
