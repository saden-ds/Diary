eDiary
====

## Requirements

* php 8.1
* mysql 8.1.0
* memcached 3.2.0
* yaml 2.2.3

## Data Base Configuration 

SQL dump is stored in the directory db

## Config
/config/{env name}.yml

Default environment __development__ /config/development.yml

The suggested syntax for YAML files is to use 2 spaces for indentation.


```bash
log: true
uid: www-data
gid: www-data
cookie_domain: domain_com
dir: /var/www/html/web
data_dir: /var/www/data
tmp_dir: /var/www/data/tmp
secret: [secret]
title: eDiary

databases:
  default: database_name
  database_name:
    host: localhost
    username: [username]
    password: [password]

memcache:
  host: memcached
  port: 11211

session:
  name: ediary
  expire: 7200

user:
  password:
    min: 8
    max: 72

```
