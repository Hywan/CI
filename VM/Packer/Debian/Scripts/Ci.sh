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
    PHP

cd PHP

for version in `echo '5.5.3 5.5.9'`; do
    prefix=/Development/Php/PHP-$version

    mkdir /Development/Php/$version
    git checkout 'PHP-'$version
    ./buildconf --force
    ./configure \
        --prefix=$prefix/ \
        --disable-all \
        --enable-json \
        --enable-fpm
    make
    make install
    make clean
    make distclean

    mv $prefix/etc/php-fpm.conf.default \
       $prefix/etc/php-fpm.conf
    sed -i'' -r 's/;?listen = (.*)$/listen = 127.0.0.1:10000/' \
        $prefix/etc/php-fpm.conf

    # init.d
    initd=ci-php-$version # size must be lower than 15 characters.

    sudo cp /tmp/Packer/Scripts/Template/Init.d/Php-fpm \
            /etc/init.d/$initd
    sudo chmod 755 /etc/init.d/$initd

    sudo sed -i'' -e 's,{{PHP_PREFIX}},'$prefix',g' \
            /etc/init.d/$initd
    sudo sed -i'' -e 's,{{SCRIPT_NAME}},'$initd',g' \
            /etc/init.d/$initd

    sudo update-rc.d $initd defaults

    # Pool
    echo 'PHP-'$version >> /Development/Php/Pool
done
