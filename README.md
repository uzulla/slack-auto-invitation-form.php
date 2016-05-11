# Slack invitation request form.

# INSTALL

```
$ composer install
$ cp config.sample.php
$ vi config.php
```

# docker

```
$ docker build -t you/fill_me:latest .
$ docker run -e TEAM_SUB_DOMAIN=fill_me -e SLACK_API_TOKEN=xoxp-fill_me -p 8080:80 you/fill_me:latest
```
