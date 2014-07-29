sudo sed -i'' -r 's/;?listen = (.*)$/listen = 9001/' \
    /etc/php5/fpm/pool.d/www.conf

sudo git clone \
    http://git.hoa-project.net/Central.git \
    /usr/local/lib/Hoa.central
sudo ln -s \
    /usr/local/lib/Hoa.central/Hoa \
    /usr/local/lib/Hoa
sudo ln -s \
    /usr/local/lib/Hoa/Core/Bin/hoa \
    /usr/local/bin/hoa

sudo git clone \
    https://github.com/Hywan/CI.git \
    /usr/local/lib/Ci
sudo ln -s \
    /usr/local/lib/Ci/Standby/Application \
    /Ci

sudo git clone \
    --branch edge \
    --single-branch \
    https://github.com/atoum/atoum.git \
    /usr/local/lib/atoum
sudo ln -s \
    /usr/local/lib/atoum/bin/atoum \
    /usr/local/bin/atoum

curl -sS \
    https://getcomposer.org/installer | \
    sudo php -- --install-dir /usr/local/lib
sudo ln -s \
    /usr/local/lib/composer.phar \
    /usr/local/bin/composer
