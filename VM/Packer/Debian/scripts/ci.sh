sudo sed -i'' -r 's/;?listen = (.*)$/listen = 9001/' \
    /etc/php5/fpm/pool.d/www.conf

sudo git clone http://git.hoa-project.net/Central.git /usr/local/lib/Hoa.central
sudo ln -s /usr/local/lib/Hoa.central/Hoa /usr/local/lib/Hoa

sudo git clone http://github.com/Hywan/CI.git /usr/local/lib/Ci
sudo ln -s /usr/local/lib/Ci/Standby/Application /Ci
