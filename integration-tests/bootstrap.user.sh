#!/bin/bash

echo
echo "install phpbrew and php54"
cd ~
curl -s -L -O https://github.com/phpbrew/phpbrew/raw/master/phpbrew
chmod +x phpbrew
sudo mv phpbrew /usr/bin/phpbrew
phpbrew init
phpbrew known --update
phpbrew update
phpbrew install 5.4.34 +default

echo
echo "switch php54"
echo "source $HOME/.phpbrew/bashrc" >> /home/vagrant/.bashrc
source $HOME/.phpbrew/bashrc
phpbrew switch 5.4.34

echo
echo "install php54-apc"
phpbrew ext install apc
echo "apc.enable_cli = 1" >> ~/.phpbrew/php/php-5.4.34/etc/php.ini
php -i | grep apc
echo "date.timezone =UTC" >> ~/.phpbrew/php/php-5.4.34/etc/php.ini

echo
echo "update project dependencies"
cd /home/vagrant/project/integration-tests
curl -sS https://getcomposer.org/installer | php
php composer.phar update
