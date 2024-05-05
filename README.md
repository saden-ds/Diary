eDiary
====

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
salt: [7 random symbols]
title: eDiary

memcache:
  host: memcached
  port: 11211

session:
  name: ediary
  expire: 604800

user:
  password:
    min: 8
    max: 72

```
