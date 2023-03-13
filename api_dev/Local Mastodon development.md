# Notes on local Mastodon development

## Preparation

Decide on a fake domain name. I am using `mastodon.web`

Edit your hosts file to point your fake domain to `127.0.0.1`. On macOS you can use [GasMask](https://github.com/2ndalpha/gasmask). On Linux you can edit `/etc/hosts`. You will need a line like this:

```
127.0.0.1    	mastodon.web
```

## Mastodon on Docker

Clone the [Mastodon repository](https://github.com/mastodon/mastodon) e.g. to `/path/to/the/mastodon/repository`.

**IMPORTANT**: If you had already cloned it in the past and you want to start over delete the following folders inside the working copy (might require using `sudo` on Linux):
* postgres14
* public/system
* redis

Edit the `docker-compose.yml` and comment out the `build: .` lines.

_Note_: I also pinned the `tootsuite/mastodon` image to a specific version, in the following example version 4.0.2 (`tootsuite/mastodon:v4.0.2`). This is optional but can seriously save your sanity...

```yml
version: '3'
services:
  db:
    restart: always
    image: postgres:14-alpine
    shm_size: 256mb
    networks:
      - internal_network
    healthcheck:
      test: ['CMD', 'pg_isready', '-U', 'postgres']
    volumes:
      - ./postgres14:/var/lib/postgresql/data
    environment:
      - 'POSTGRES_HOST_AUTH_METHOD=trust'

  redis:
    restart: always
    image: redis:7-alpine
    networks:
      - internal_network
    healthcheck:
      test: ['CMD', 'redis-cli', 'ping']
    volumes:
      - ./redis:/data

  # es:
  #   restart: always
  #   image: docker.elastic.co/elasticsearch/elasticsearch:7.17.4
  #   environment:
  #     - "ES_JAVA_OPTS=-Xms512m -Xmx512m -Des.enforce.bootstrap.checks=true"
  #     - "xpack.license.self_generated.type=basic"
  #     - "xpack.security.enabled=false"
  #     - "xpack.watcher.enabled=false"
  #     - "xpack.graph.enabled=false"
  #     - "xpack.ml.enabled=false"
  #     - "bootstrap.memory_lock=true"
  #     - "cluster.name=es-mastodon"
  #     - "discovery.type=single-node"
  #     - "thread_pool.write.queue_size=1000"
  #   networks:
  #      - external_network
  #      - internal_network
  #   healthcheck:
  #      test: ["CMD-SHELL", "curl --silent --fail localhost:9200/_cluster/health || exit 1"]
  #   volumes:
  #      - ./elasticsearch:/usr/share/elasticsearch/data
  #   ulimits:
  #     memlock:
  #       soft: -1
  #       hard: -1
  #     nofile:
  #       soft: 65536
  #       hard: 65536
  #   ports:
  #     - '127.0.0.1:9200:9200'

  web:
    #build: .
    image: tootsuite/mastodon:v4.0.2
    restart: always
    env_file: .env.production
    command: bash -c "rm -f /mastodon/tmp/pids/server.pid; bundle exec rails s -p 3000"
    networks:
      - external_network
      - internal_network
    healthcheck:
      # prettier-ignore
      test: ['CMD-SHELL', 'wget -q --spider --proxy=off localhost:3000/health || exit 1']
    ports:
      - '127.0.0.1:3000:3000'
    depends_on:
      - db
      - redis
      # - es
    volumes:
      - ./public/system:/mastodon/public/system

  streaming:
    #build: .
    image: tootsuite/mastodon:v4.0.2
    restart: always
    env_file: .env.production
    command: node ./streaming
    networks:
      - external_network
      - internal_network
    healthcheck:
      # prettier-ignore
      test: ['CMD-SHELL', 'wget -q --spider --proxy=off localhost:4000/api/v1/streaming/health || exit 1']
    ports:
      - '127.0.0.1:4000:4000'
    depends_on:
      - db
      - redis

  sidekiq:
    #build: .
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

  ## Uncomment to enable federation with tor instances along with adding the following ENV variables
  ## http_proxy=http://privoxy:8118
  ## ALLOW_ACCESS_TO_HIDDEN_SERVICE=true
  # tor:
  #   image: sirboops/tor
  #   networks:
  #      - external_network
  #      - internal_network
  #
  # privoxy:
  #   image: sirboops/privoxy
  #   volumes:
  #     - ./priv-config:/opt/config
  #   networks:
  #     - external_network
  #     - internal_network

networks:
  external_network:
  internal_network:
    internal: true
```

Copy `.env.production.sample` to `.env.production` and follow its instructions to generate secrets and VAPID keys.

Tip: use `docker compose run --rm web bundle exec rake WHATEVER` instead of the `rake WHATEVER` command line suggested in the configuration file.

```dotenv
# This is a sample configuration file. You can generate your configuration
# with the `rake mastodon:setup` interactive setup wizard, but to customize
# your setup even further, you'll need to edit it manually. This sample does
# not demonstrate all available configuration options. Please look at
# https://docs.joinmastodon.org/admin/config/ for the full documentation.

# Note that this file accepts slightly different syntax depending on whether
# you are using `docker-compose` or not. In particular, if you use
# `docker-compose`, the value of each declared variable will be taken verbatim,
# including surrounding quotes.
# See: https://github.com/mastodon/mastodon/issues/16895

# Federation
# ----------
# This identifies your server and cannot be changed safely later
# ----------
LOCAL_DOMAIN=mastodon.web
LOCAL_HTTPS=true

# Redis
# -----
REDIS_HOST=redis
REDIS_PORT=6379

# PostgreSQL
# ----------
DB_HOST=db
DB_USER=postgres
DB_NAME=postgres
DB_PASS=
DB_PORT=5432

# Elasticsearch (optional)
# ------------------------
ES_ENABLED=false
#ES_HOST=localhost
#ES_PORT=9200
## Authentication for ES (optional)
#ES_USER=elastic
#ES_PASS=password

# Secrets
# -------
# Make sure to use `rake secret` to generate secrets
# -------
SECRET_KEY_BASE=bc70ab7ba4ea516ac222820d98f2e1d59ede51e62d67f97592f6b6284261b8f08e55c58d2e489240bfc802544378a330a7050807290954ba20386e7d43337ed4
OTP_SECRET=953517351e4373b6b35696770e47e18f7457b14db2dd9c213009f0143a4ba991ff09092893a5d95f47048ed8dfb46a53fffb39b3b46dc9619307b8f6e1b20d41

# Web Push
# --------
# Generate with `rake mastodon:webpush:generate_vapid_key`
# --------
VAPID_PRIVATE_KEY=H9QZ_IFiPK-GgZ0vgHFIdwC8g6wn0Cd3dd844Tuk3Uo=
VAPID_PUBLIC_KEY=BPQxhvTfvEpM2_wuEiwjgWjTbmgiWIWKltluVZIAqVwH2vTwvSCQrciDytgr6U-C9fOX0WwnxQPrCDPI1sMRZ8Y=

# Sending mail
# ------------
SMTP_SERVER=host.docker.internal
SMTP_PORT=1025
SMTP_LOGIN=
SMTP_PASSWORD=
SMTP_FROM_ADDRESS=notifications@mastodon.web

# File storage (optional)
# -----------------------
S3_ENABLED=false
S3_BUCKET=files.example.com
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
S3_ALIAS_HOST=files.example.com

# IP and session retention
# -----------------------
# Make sure to modify the scheduling of ip_cleanup_scheduler in config/sidekiq.yml
# to be less than daily if you lower IP_RETENTION_PERIOD below two days (172800).
# -----------------------
IP_RETENTION_PERIOD=31556952
SESSION_RETENTION_PERIOD=31556952
```

Run `docker compose up -d`. Don't worry about any failures in the `sidekiq` and `web` containers.

Initial setup: 
* `docker compose run --rm web bundle exec rake mastodon:setup`
* Follow the instructions on the screen.
* **IMPORTANT**! Note down the initial password.
* `docker compose stop`
* `docker compose start`
* Visit https://mastodon.web
* Login with the email address and initial password you noted down above.

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
    SSLCertificateFile /path/to/etc/httpd/ssl/mastodon.web.crt
    SSLCertificateKeyFile /path/to/etc/httpd/ssl/mastodon.web.key
    
    ProxyPreserveHost On
    RequestHeader set X-Forwarded-Proto "https"
    ProxyAddHeaders On
    
    # <LocationMatch "^/(assets|avatars|emoji|headers|packs|sounds|system)">
    #   Header always set Cache-Control "public, max-age=31536000, immutable"
    #   Require all granted
    # </LocationMatch>
    
    # These files / paths don't get proxied and are retrieved from DocumentRoot
    # ProxyPass /500.html !
    # ProxyPass /sw.js !
    # ProxyPass /robots.txt !
    # ProxyPass /manifest.json !
    # ProxyPass /browserconfig.xml !
    # ProxyPass /mask-icon.svg !
    # ProxyPassMatch ^(/.*\.(png|ico)$) !
    # ProxyPassMatch ^/(assets|avatars|emoji|headers|packs|sounds|system) !
    
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

The idea is that we will create an alias IP address for the loopback device using this address. 

## macOS

You can do this temporarily with:

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

## Linux

You can do this temporarily with:

```bash
sudo ip addr add 223.254.254.254/32 dev lo label lo:dummy
```

To remove the alias, run:

```bash
ip addr del 223.254.254.254/32 dev lo
```

To persist these changes, create the file `/etc/systemd/system/loopback-alias.service` with the following contents:

```ini
[Unit]
Description=loopback alias
Wants=network.target
Before=network.target

[Service]
Type=oneshot
RemainAfterExit=yes
ExecStart=/usr/bin/ip addr add 223.254.254.254/32 dev lo label lo:dummy
ExecStop=/usr/bin/ip addr del 223.254.254.254/32 dev lo

[Install]
WantedBy=multi-user.target
```

Then run the following:

```bash
sudo ip addr del 223.254.254.254/32 dev lo # If you had already enabled the alias temporarily.
sudo systemctl daemon-reload
sudo systemctl enable loopback-alias
sudo systemctl start loopback-alias
```

## Update the hosts file

Finally, remember to edit your hosts file to point your development site to this (intentionally mis-routed locally!) IP address:

```
223.254.254.254		activitypub.local.web
```

## Mastodon doesn't like self-signed TLS certificates

The other problem we have is that the local ActivityPub instance uses a self-signed certificate. Yes, even following [my custom TLS certificate tutorial](https://www.dionysopoulos.me/forge-your-own-ssl-certificates-for-local-development.html) you have a root Certification Authority which is self-signed and not recognised as a valid root CA.

The solution —assuming you don't want to do a custom build of the Mastodon container— is to add your root _and_ intermediate Certification Authority certificates to your container's certification authority cache. If you have simple self-signed certificates, without a root/intermediate CA just add the self-signed public certificate itself (one file instead of two files).

Let's say that you have followed my tutorial on custom TLS certificates, so you have a root and an intermediate certificate which are called `root.pem` and `intermediate.pem` respectively.

On the Terminal run `docker exec -u root -it mastodon_web_1 bash` to get a root console in the Web container instance of the Mastodon installation. 

_Note_: On Linux this is `docker exec -u root -it mastodon-web-1 bash`. It uses dashes instead of underscores.

Run the following:

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

Now run `docker exec -u root -it mastodon_sidekiq_1 bash` to get a root console in the Sidekiq container instance of the Mastodon installation and carry out the instructions above once again.

_Note_: On Linux this is `docker exec -u root -it mastodon-sidekiq-1 bash`. It uses dashes instead of underscores.

No need to restart the Mastodon services.


## At long last!

You can now look for `youruser@activitypub.local.web` in your Mastodon installation, where `youruser` is an Actor username you have defined in the ActivityPub component of your `activitypub.local.web` local Joomla installation.