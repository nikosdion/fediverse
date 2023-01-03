# Notes on local Mastodon development

## Preparation

Decide on a fake domain name. I am using `mastodon.web`

Edit your hosts file to point your fake domain to `127.0.0.1`. On macOS you can use [GasMask](https://github.com/2ndalpha/gasmask). On Linux you can edit `/etc/hosts`. You will need a line like this:

```
127.0.0.1    	mastodon.web
```

## Mastodon on Docker

Clone the [Mastodon repository](https://github.com/mastodon/mastodon) e.g. to `/path/to/the/mastodon/repository`.

Edit the `docker-compose.yml`:

```yml
version: '3'
# See https://vdna.be/site/index.php/2020/11/hosting-your-own-mastodon-instance-via-docker-compose/
# See https://github.com/McKael/mastodon-documentation/blob/master/Running-Mastodon/Docker-Guide.md
# See https://gist.github.com/TrillCyborg/84939cd4013ace9960031b803a0590c4
services:
  db:
    restart: always
    image: postgres:14-alpine
    shm_size: 256mb
    networks:
      - internal_network
    healthcheck:
      test: ['CMD', 'pg_isready', '-U', 'mastodon', '-d', 'mastodon_production']
    volumes:
      - ./postgres14:/var/lib/postgresql/data
    environment:
      - 'POSTGRES_PASSWORD=9SyEbgWWNBHa7FdeXtT7nTJj'
      - 'POSTGRES_DB=mastodon_production'
      - 'POSTGRES_USER=mastodon'

  redis:
    restart: always
    image: redis:6-alpine
    networks:
      - internal_network
    healthcheck:
      test: ['CMD', 'redis-cli', 'ping']
    volumes:
      - ./redis:/data

  web:
    image: tootsuite/mastodon:v4.0.2
    restart: always
    env_file: .env.production
    command: bash -c "rm -f /mastodon/tmp/pids/server.pid; bundle exec rails s -p 3000"
    networks:
      - external_network
      - internal_network
    healthcheck:
      test: ['CMD-SHELL', 'wget -q --spider --proxy=off localhost:3000/health || exit 1']
    ports:
      - '127.0.0.1:3000:3000'
    depends_on:
      - db
      - redis
    volumes:
      - ./public/system:/mastodon/public/system

  streaming:
    image: tootsuite/mastodon:v4.0.2
    restart: always
    env_file: .env.production
    command: node ./streaming
    networks:
      - external_network
      - internal_network
    healthcheck:
      test: ['CMD-SHELL', 'wget -q --spider --proxy=off localhost:4000/api/v1/streaming/health || exit 1']
    ports:
      - '127.0.0.1:4000:4000'
    depends_on:
      - db
      - redis

  sidekiq:
    image: tootsuite/mastodon:v4.0.2
    restart: always
    env_file: .env.production
    command: bundle exec sidekiq
    depends_on:
      - db
      - redis
    networks:
      - external_network
      - internal_network
    volumes:
      - ./public/system:/mastodon/public/system
    healthcheck:
      test: ['CMD-SHELL', "ps aux | grep '[s]idekiq\ 6' || false"]

networks:
  external_network:
  internal_network:
    internal: true
```

The referenced `.env.production` file looks like this:

```dotenv
LOCAL_DOMAIN=mastodon.web
SINGLE_USER_MODE=false

REDIS_HOST=mastodon_redis_1
REDIS_PORT=6379

DB_HOST=mastodon_db_1
DB_USER=mastodon
DB_NAME=mastodon_production
DB_PASS=9SyEbgWWNBHa7FdeXtT7nTJj
DB_PORT=5432

# Secrets
# -------
# Make sure to use `rake secret` to generate secrets
# -------
SECRET_KEY_BASE=e928c88bdda184e33fc50258069bbfd390399e732c471ec09d02aa08b22efa55b89d8fa306b2e4691c869e8468561830aa9086ed9f95e52f568c4597726158e2
OTP_SECRET=a9920472b3336b3a2822d79ae7628910212d531ffdc25df834382947ae12911163ad67835477596a6142e2d9b9e8f4dd892bd42956bba6bc9e475327d6a7c677

# Web Push
# --------
# Generate with `rake mastodon:webpush:generate_vapid_key`
# --------
VAPID_PRIVATE_KEY=CZVLeviRSM-zlaGxGff35C4KQDuYd2yg82bjr0xTCYA=
VAPID_PUBLIC_KEY=BCufrndxEtzf-Xwh0OGIQ20r6mrZOKEbasa-TNdvmRxKjMOZ9oyCmbfS8TwhyMQloqqUnA-Fh8VSub0fTmJ49yU=

SMTP_SERVER=localhost
SMTP_PORT=25
SMTP_AUTH_METHOD=none
SMTP_OPENSSL_VERIFY_MODE=none
SMTP_LOGIN=
SMTP_PASSWORD=
SMTP_FROM_ADDRESS=Mastodon <notifications@mastodon.web>
```

## Apache proxy

I have Apache running on my local server, handling all HTTP(S) requests. I need to proxy the Mastodon instance through Apache, so I can access my Mastodon instance.

This requires the following Apache configuration file:

```apacheconf
<VirtualHost *:80>
   ServerAdmin contact@local.web
   ServerName mastodon.web
   Redirect Permanent / https://mastodon.web/
</VirtualHost>

<VirtualHost *:443>
   ServerAdmin contact@local.web
   ServerName mastodon.web

   Protocols h2 h2c http/1.1

   # Remember to change this path
   DocumentRoot /path/to/the/mastodon/repository/public

   SSLEngine on
   SSLProtocol -all +TLSv1.2
   SSLHonorCipherOrder on
   SSLCipherSuite EECDH+AESGCM:AES256+EECDH:AES128+EECDH

   # Obviously, you need to provide your own TLS key files. See https://www.dionysopoulos.me/forge-your-own-ssl-certificates-for-local-development.html
   SSLCertificateFile /opt/homebrew/etc/httpd/ssl/mastodon.web.crt
   SSLCertificateKeyFile /opt/homebrew/etc/httpd/ssl/mastodon.web.key

   ProxyPreserveHost On
   RequestHeader set X-Forwarded-Proto "https"
   # Ports 3000 and 4000 are defined in the docker-compose.yml file. They are WebSocket ports.
   ProxyPass /api/v1/streaming/ ws://localhost:4000/
   ProxyPassReverse /api/v1/streaming/ ws://localhost:4000/
   ProxyPass / http://localhost:3000/
   ProxyPassReverse / http://localhost:3000/
   ErrorDocument 500 /500.html
   ErrorDocument 501 /500.html
   ErrorDocument 502 /500.html
   ErrorDocument 503 /500.html
   ErrorDocument 504 /500.html
</VirtualHost>
```

## Mastodon doesn't like federating to private IP address space

If you try to federate with a local ActivityPub server, Mastodon will reply with an error about the IP address being private. You can NOT federate with a server running on the [reserved IPv4 address space](https://en.wikipedia.org/wiki/Reserved_IP_addresses#IPv4).

You need to choose which public IP address to “sacrifice”. I chose `223.254.254.254`, an address in China I am extremely unlikely to need to access in any other way.

The idea is that we will create an alias IP address for the loopback device using this address. On macOS this is:

```bash
sudo ifconfig lo0 alias 223.254.254.254 netmask 255.255.255.0
```

To persist these changes, create the file `/Library/LaunchDaemons/org.localhost.alias.plist` with the following contents:

```xml
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple Computer//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<!--
Save in /Library/LaunchDaemons/org.localhost.alias.plist
-->
<plist version="1.0">
<dict>
        <key>Label</key>
        <string>Create localhost IP alias at 254.254.254.254</string>

        <key>ProgramArguments</key>
        <array>
            <string>/sbin/ifconfig</string>
            <string>lo0</string>
            <string>alias</string>
            <string>223.254.254.254</string>
            <string>netmask</string>
            <string>255.255.255.0</string>
        </array>

        <key>RunAtLoad</key>
        <true/>
    </dict>
</plist>
```

Finally, remember to edit your hosts file to point your development site to this (misrouted locally!) IP address:

```
223.254.254.254		activitypub.local.web
```

## Mastodon doesn't like self-signed TLS certificates

The other problem we have is that the local ActivityPub instance uses a self-signed certificate. Yes, even following [my custom TLS certificate tutorial](https://www.dionysopoulos.me/forge-your-own-ssl-certificates-for-local-development.html) you have a root Certification Authority which is self-signed and not recognised as a valid root CA.

The solution —assuming you don't want to do a custom build of the Mastodon container— is to add your root _and_ intermediate Certification Authority certificates to your container's certification authority cache. If you have simple self-signed certificates, without a root/intermediate CA just add the self-signed public certificate itself (one file instead of two files).

Let's say that you have followed my tutorial on custom TLS certificates, so you have a root and an intermediate certificate which are called `root.pem` and `intermediate.pem` respectively.

On the Terminal run `docker exec -u root -it mastodon_web_1 bash` to get a root console in the Web container instance of the Mastodon installation. Run the following:

```bash
apt update
mkdir -p /var/cache/apt/archives/partial
apt install nano
cd ~
nano custom_root.crt
# At this point past your root.pem contents, hit CTRL-X, Y, ENTER
nano custom_intermediate.crt
# At this point past your intermediate.pem contents, hit CTRL-X, Y, ENTER
cp custom*.crt /usr/local/share/ca-certificates -v
update-ca-certificates
# Hit CTRL-D to exit
```

## At long last!

You can now look for `youruser@activitypub.local.web` in your Mastodon installation, where `youruser` is an Actor username you have defined in the ActivityPub component of your `activitypub.local.web` local Joomla installation.