# SuperHive - Your Hive unique blog engine

SuperHive is a PHP Small blog engine who fetch all articles from the Hive Blockchain. It' very easy to set up a blog feed with a unique design. You can write article to Hive Blockchain (via PeakD or HiveBlog) and display it to a great personnal website. 

All you need is to install SuperHive, set up your author feed... and that's all ! If you want to change the design, you can change ```views/index.html``` to make your design (you need to know HTML / CSS and JS).

## How to install SuperHive

### Clone this repository

Download zip and extract it onto your webserver or use GIT :

 ```git clone https://github.com/fkosmala/superhive``` 

### Configure your webserver

Based on Slim4, the configuration for your webserver is avaiblable on [Slim 4 WebServer documentation](http://www.slimframework.com/docs/v3/start/web-servers.html) 

### Change settings

It's really simple : You need to change the settings in ```./config.php``` file :

| Name of the var         | What is it ?                                                 |
| ----------------------- | ------------------------------------------------------------ |
| $settings['author']     | Name of the feed (without @)                                 |
| $settings['api']        | Link to the Hive API End-point                               |
| $settings['title']      | The title of your website (can be different than author)     |
| $settings['baseline']   | The small text below the title and descriptino of the website |
| $settings['nextbutton'] | The text onto the button at the bottom of the website        |
| $settings['cron']       | Enable/disable the automatic fetch of author feed            |

### (Optional) Set up Crontab

For better performances, you can set up your crontab. All you need is to set ```$settings['cron']  ``` to ```true``` and add this line to your crontab :

``` 
0 */4 * * * /bin/php /path/to/your/folder/update.php > /dev/null 2>&1
```

The website will be updated every 4 hours

## Demo

You can see a demo on https://hive.florent-kosmala.fr/

## Showcase

Here is the list of websites who use SuperHive. If you want to add your superHive website, send me an email to Contact|AT|florent-kosmala.fr