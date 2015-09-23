#!/bin/bash

# init
apt-get update 2> /dev/null

# redis
apt-get install -y redis-server 2> /dev/null

# ntp
apt-get install ntp -y 2> /dev/null
service ntp restart

# install dependencies and services
apt-get install unzip -y 2> /dev/null
apt-get install -y vim curl 2> /dev/null
apt-get install git -y 2> /dev/null

# PHP things
echo "Install PHP things"
apt-get install -y php-apc 2> /dev/null
apt-get install -y phpunit 2> /dev/null

# phpbrew stuff for 5.4
apt-get build-dep php5 2> /dev/null
apt-get install -y php5 php5-dev php-pear autoconf automake curl build-essential libxslt1-dev re2c libxml2 libxml2-dev php5-cli bison libbz2-dev libreadline-dev 2> /dev/null
apt-get install -y libfreetype6 libfreetype6-dev libpng12-0 libpng12-dev libjpeg-dev libjpeg8-dev libjpeg8  libgd-dev libgd3 libxpm4 libltdl7 libltdl-dev 2> /dev/null
apt-get install -y libssl-dev openssl 2> /dev/null
apt-get install -y gettext libgettextpo-dev libgettextpo0 2> /dev/null
apt-get install -y php5-cli 2> /dev/null
apt-get install -y libmcrypt-dev 2> /dev/null
apt-get install -y libreadline-dev 2> /dev/null

# set vim tabs
cat <<EOF > /home/vagrant/.vimrc
set tabstop=4
EOF
chown vagrant.vagrant /home/vagrant/.vimrc

su - vagrant
cd ~vagrant
pwd
curl -s -L -O https://github.com/phpbrew/phpbrew/raw/master/phpbrew
chmod +x phpbrew
sudo mv phpbrew /usr/bin/phpbrew
phpbrew init
phpbrew known --update
phpbrew update
phpbrew install 5.4.34 +default

echo "source $HOME/.phpbrew/bashrc" >> /home/vagrant/.bashrc
source $HOME/.bashrc
phpbrew switch php-5.4.34
phpbrew ext install apc
echo "apc.enable_cli = 1" >> ~/.phpbrew/php/php-5.4.34/etc/php.ini


cd /home/vagrant/project/integration-tests
curl -sS https://getcomposer.org/installer | php
php composer.phar install
