#!/bin/bash

# FPM
sudo sed -i'' -r 's/;?listen = (.*)$/listen = 9001/' \
    /etc/php5/fpm/pool.d/www.conf

# Hoa
sudo git clone \
    http://git.hoa-project.net/Central.git \
    /usr/local/lib/Hoa.central
sudo ln -s \
    /usr/local/lib/Hoa.central/Hoa \
    /usr/local/lib/Hoa
sudo ln -s \
    /usr/local/lib/Hoa/Core/Bin/hoa \
    /usr/local/bin/hoa

# CI
sudo git clone \
    https://github.com/Hywan/CI.git \
    /usr/local/lib/Ci
sudo ln -s \
    /usr/local/lib/Ci/Standby/Application \
    /Ci

# atoum
sudo git clone \
    --branch edge \
    --single-branch \
    https://github.com/atoum/atoum.git \
    /usr/local/lib/atoum
sudo ln -s \
    /usr/local/lib/atoum/bin/atoum \
    /usr/local/bin/atoum

# Composer
curl -sS \
    https://getcomposer.org/installer | \
    sudo php -- --install-dir /usr/local/lib
sudo ln -s \
    /usr/local/lib/composer.phar \
    /usr/local/bin/composer

# PHP
sudo mkdir -p /Development/Php
sudo chown -R packer:packer /Development

cd /Development/Php

git clone \
    https://git.php.net/repository/php-src.git \
    Source

cd Source

for version in `echo '5.5.3 5.5.9'`; do
    mkdir /Development/Php/$version
    git checkout 'PHP-'$version
    ./buildconf --force
    ./configure \
        --prefix=/Development/Php/$version/ \
        --disable-all \
        --enable-fpm
    make
    make install
    make clean
    make distclean

    mv /Development/Php/$version/etc/php-fpm.conf.default \
       /Development/Php/$version/etc/php-fpm.conf
    sed -i'' -r 's/;?listen = (.*)$/listen = 9001/' \
        /Development/Php/$version/etc/php-fpm.conf

    echo 'PHP-'$version >> /Development/Php/Pool
done
