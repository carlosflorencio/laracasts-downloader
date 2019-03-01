# Laracasts Downloader
[![Join the chat at https://gitter.im/laracasts-downloader](https://badges.gitter.im/laracasts-downloader.svg)](https://gitter.im/laracasts-downloader?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/ac2fdb9a-222b-4244-b08e-af5d2f69845d/mini.png)](https://insight.sensiolabs.com/projects/ac2fdb9a-222b-4244-b08e-af5d2f69845d)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/iamfreee/laracasts-downloader/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/iamfreee/laracasts-downloader/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/iamfreee/laracasts-downloader/badges/build.png?b=master)](https://scrutinizer-ci.com/g/iamfreee/laracasts-downloader/build-status/master)

Downloads new lessons and series from laracasts if there are updates. Or the whole catalogue.

**Working good at 10/11/2018**

## Description
Syncs your local folder with the laracasts website, when there are new lessons the app download it for you.
If your local folder is empty, all lessons and series will be downloaded!

A .skip file is used to prevent downloading deleted lessons for these with space problems. Thanks to @vinicius73

Just call `php makeskips.php` before deleting the lessons.

## An account with an active subscription is necessary!
Even to download free lessons or series. The download option is only allowed to users with a valid subscription.

## Requirements
- PHP >= 5.4
- php-cURL
- php-xml
- php-json
- Composer

## Installation
- Clone this repo to a folder in your machine
- Change your info in .env.example and rename it to .env
  - Go to [laracasts.com --> Browse](https://laracasts.com/search), open Dev Tools --> Network,
   find a request to algolia.net and save `x-algolia-api-key` and `x-algolia-application-id`
   values to your .env
- `composer install`
- `php start.php` and you are done!

Also works in the browser, but is better from the cli because of the instant feedback

## Downloading specific series or lessons
- You can use series and lessons names
- You can use series and lessons slugs (preferred because there are some custom slugs too)
- You can download multiples series/lessons

### Commands to download series
    php start.php -s "Series name example" -s "series-slug-example"
    php start.php --series-name "Series name example" --series-name "series-slug-example"
    
### Command to download lessons
    php start.php -l "Lesson name example" -l "lesson-slug-example"
    php start.php --lesson-name "Lessons name example" --lesson-name "lesson-slug-example"

### Using Docker
- Clone this repo to a folder in your machine
- Change your info in .env.example and rename it to .env
- `docker build -t image-name .`
- `docker run -d -v /host-path:/container-path-in-config.ini image-name` and the path should be absolute

## Troubleshooting
If you have a `cURL error 60: SSL certificate problem: self signed certificate in certificate chain` or `SLL error: cURL error 35` do this:

- Download [http://curl.haxx.se/ca/cacert.pem](http://curl.haxx.se/ca/cacert.pem)
- Add `curl.cainfo = "PATH_TO/cacert.pem"` to your php.ini

And you are done! If using apache you may need to restart it.

## License

This library is under the MIT License, see the complete license [here](LICENSE)
