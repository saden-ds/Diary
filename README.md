Life
====

## Docker
```bash
docker-compose up --build
docker-compose stop
```

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
title: Life Waste to Resources IP

daemon:
  threads: 4
  lock_time: 10

laboratory_lookup_min_count: 20

mail:
  url: https://domain.com
  from: info@domain.com
  name: Ediary
  syncdog:
    url: https://admin.domain.com
    email: user@domain.com

memcache:
  host: memcached
  port: 11211

session:
  name: live
  expire: 604800

syncdog:
  url: https://domain.com
  client_id: [client_id]
  client_secret: [client_secret]

support_email: info@domain.com

user:
  password:
    min: 8
    max: 72

```# Diary
