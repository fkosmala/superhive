# SuperHive - Your Hive unique blog engine

SuperHive is a PHP Small blog engine who fetch all articles from the Hive Blockchain. It' very easy to set up a blog feed with a unique design. You can write article to Hive Blockchain (via PeakD or HiveBlog) and display it to a great personnal website.

All you need is to install SuperHive, set up your author feed... and that's all ! If you want to change the design, you can go to ```public/themes/``` and make your design (you need to know HTML / CSS and JS).

## How to install SuperHive

### Requirements

To run SuperHive you need :

- PHP 7.2+ with CLI
- php-zip, php-xml & php-curl packages

### Clone this repository

Download zip and extract it onto your webserver or use GIT :
```
git clone https://github.com/fkosmala/superhive
```

### Get Composer & install dependancies

You must install [Composer](https://getcomposer.org/). Aftar that, just run :
```
php composer.phar update
```

It will install all the dependancies.

### Configure your webserver

Based on Slim4, the configuration for your webserver is avaiblable on [Slim 4 WebServer documentation](http://www.slimframework.com/docs/v3/start/web-servers.html)

### Create a new username/password

It's really simple : go to ```https://YOUR_URL/``` and create a (secure) username/password couple. and click Finish !

### Have fun !
It's finished ! If you want to change settings, go to ```https://YOUR_URL/admin``` & enter your username and password (defined the step before).

### (Optional) Set up Crontab

For better performances, you can set up your crontab. All you need is to set ```$settings['cron']  ``` to ```true``` and add this line to your crontab :

```
0 */4 * * * /bin/php /path/to/your/folder/update.php > /dev/null 2>&1
```

The website will be updated every 4 hours

## Demo

You can see a demo on https://www.florent-kosmala.fr/

## Showcase

Here is the list of websites who use SuperHive. If you want to add your superHive website, send me an email to Contact|AT|florent-kosmala.fr
