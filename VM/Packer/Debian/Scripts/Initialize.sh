#!/bin/bash

# Directory to exchange files during the provisionning
sudo mkdir -p /tmp/Packer

# Users and groups
sudo groupadd ci
sudo useradd \
    --no-create-home
    --shell /bin/bash
    --gid ci
    ci
