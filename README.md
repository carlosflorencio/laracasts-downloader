# Laracasts Downloader
[![Codacy Badge](https://www.codacy.com/project/badge/c97c63f5736f43c488cb69aa6af8fca9)](https://www.codacy.com/public/carlosmflorencio/laracasts-downloader)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/ac2fdb9a-222b-4244-b08e-af5d2f69845d/mini.png)](https://insight.sensiolabs.com/projects/ac2fdb9a-222b-4244-b08e-af5d2f69845d)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/iamfreee/laracasts-downloader/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/iamfreee/laracasts-downloader/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/iamfreee/laracasts-downloader/badges/build.png?b=master)](https://scrutinizer-ci.com/g/iamfreee/laracasts-downloader/build-status/master)

Downloads new lessons and series from laracasts if there are updates. Or the whole catalogue.

**Working good at 27/09/2016**

## Description
Syncs your local folder with the laracasts website, when there are new lessons the app download it for you.
If your local folder is empty, all lessons and series will be downloaded!

A .skip file is used to prevent downloading deleted lessons for these with space problems. Thanks to @vinicius73

Just call `php makeskips.php` before deleting the lessons.

## An account with an active subscription is necessary!

## Requirements
- PHP >= 5.4
- php-cURL
- Composer

## Installation
- Clone this repo to a folder in your machine
- Change your info in .env.example and rename it to .env
- `composer install`
- `php start.php`and you are done!

Also works in the browser, but is better from the cli because of the instant feedback

### Using Docker
- Clone this repo to a folder in your machine
- Change your info in .env.example and rename it to .env
- `docker build -t image-name .`
- `docker run -d -v /host-path:/container-path-in-config.ini image-name` and the path should be absolute


## Troubleshooting
If you have a `cURL error 60: SSL certificate problem: self signed certificate in certificate chain` do this:

- Download [http://curl.haxx.se/ca/cacert.pem](http://curl.haxx.se/ca/cacert.pem)
- Add `curl.cainfo = "PATH_TO/cacert.pem"` to your php.ini

And you are done! If using apache you may need to restart it.

## License

This library is under the MIT License, see the complete license [here](LICENSE)
