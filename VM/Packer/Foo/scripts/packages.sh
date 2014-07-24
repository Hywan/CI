sudo apt-get update
sudo apt-get install -y git \
                        curl \
                        vim \
                        htop \
                        gcc \
                        make \
                        linux-headers-$(uname -r) \
                        php5 \
                        php5-cli \
                        php5-fpm

git clone http://git.hoa-project.net/Central.git /usr/local/lib/Hoa.central
ln -s /usr/local/lib/Hoa.central/Hoa /usr/local/lib/Hoa

git clone http://github.com/Hywan/CI.git /usr/local/lib/Ci
ln -s /usr/local/lib/Ci/Standby/Application /Ci
