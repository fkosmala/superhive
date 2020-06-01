# SuperHive - Your HIVE unique blog engine

SuperHive is the Next-generation blog engine. It who fetch all articles from the [Hive Blockchain](https://hive.io) and create a beautiful and SEO-optimized blog. 

It' very easy to set up a blog feed with a unique design. You can write article to HIVE Blockchain (via [PeakD](https://peakd.com) or [HiveBlog](https://hive.blog)) and display it to a great personal website.

All you need is to install SuperHive, set up your author feed... and that's all ! If you want to change the design, you can go to ```/public/themes/``` and make your design (you need to know HTML / CSS and JS).

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

### Get Composer & install dependencies

You must install [Composer](https://getcomposer.org/). Aftar that, just run :
```
php composer.phar update
```

It will install all the dependencies.

### Configure your webserver

Based on Slim 4, the configuration for your webserver is avaiblable on [Slim 4 WebServer documentation](http://www.slimframework.com/docs/v4/start/web-servers.html)

### Rename the config file

Rename ```config.sample.json``` to ```config.json``` 

### Create a new username/password

It's really simple : go to ```https://YOUR_URL/``` and create a (secure) username/password couple. and click Finish !

### Have fun !
It's finished ! If you want to change settings, go to ```https://YOUR_URL/admin``` & enter your username and password (defined the step before).

### (Optional) Set up Crontab

For better performances, you can set up your crontab. All you need is to set ```$settings['cron']  ``` to ```true``` and add this line to your crontab :

```
0 */2 * * * /bin/php /path/to/your/folder/update.php > /dev/null 2>&1
```

The website will be updated every 2 hours

## Demo

You can see a demo on https://www.florent-kosmala.fr/

## Showcase

Here is the list of websites who use SuperHive. If you want to add your superHive website, send me an email to Contact|AT|florent-kosmala.fr

## Support

If you want to support the project, you can send some HIVE to my [@bambukah](https://peakd.com/@bambukah/) account. You can also tell me what do you want for future versions. 