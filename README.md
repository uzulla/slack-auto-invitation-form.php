# Slack invitation request form.

## sample screen shot

![sample screen shot](sample_ss.png)

# setup

```
$ git clone https://github.com/uzulla/slack-auto-invitation-form.php.git
$ cd slack-auto-invitation-form.php
$ composer install
$ cp config.sample.php config.php # or setting SLACK_AUTO_INVITAION_SETTINGS_JSON Env
$ vi config.php
$ php -S 127.0.0.1:8080 -t public # or as you like
```

# url

```
$ open http://127.0.0.1:8080/team/team-sub-domain
```
