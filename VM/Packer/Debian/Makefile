all: up

build:
	packer build -var-file Variables/i386.json Template.json

network:
	VBoxManage modifyvm debian-i386 --nic1 bridged
	VBoxManage modifyvm debian-i386 --nicpromisc1 allow-all

up:
	VBoxHeadless -s debian-i386
