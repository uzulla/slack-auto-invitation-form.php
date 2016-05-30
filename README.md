# Slack invitation request form.

## sample screen shot

![sample screen shot](sample_ss.png)

# setup

```
$ git clone https://github.com/uzulla/slack-auto-invitation-form.php.git
$ cd slack-auto-invitation-form.php
$ composer install
$ cp config.sample.php config.php
$ vi config.php
$ php -S 127.0.0.1:8080 -t public # or as you like
```

# build docker image and run

```
$ composer install
$ docker build -f docker/Dockerfile -t you/fill_me:latest .
$ docker run -e TEAM_SUB_DOMAIN=fill_me -e SLACK_API_TOKEN=xoxp-fill_me -p 8080:80 you/fill_me:latest
```

# deploy to GAE

```
$ vi app.yaml

# test
$ /usr/local/bin/dev_appserver.py .

# deploy
$ /usr/local/bin/appcfg.py -V v1 update ./
```

