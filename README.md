# Laracasts Downloader
[![Join the chat at https://gitter.im/laracasts-downloader](https://badges.gitter.im/laracasts-downloader.svg)](https://gitter.im/laracasts-downloader?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/ac2fdb9a-222b-4244-b08e-af5d2f69845d/mini.png)](https://insight.sensiolabs.com/projects/ac2fdb9a-222b-4244-b08e-af5d2f69845d)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/iamfreee/laracasts-downloader/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/iamfreee/laracasts-downloader/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/iamfreee/laracasts-downloader/badges/build.png?b=master)](https://scrutinizer-ci.com/g/iamfreee/laracasts-downloader/build-status/master)

Downloads new lessons and series from laracasts if there are updates. Or the whole catalogue.

**Working good at 07/12/2019**

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
1. Clone this repo to your local machine.
2. Make a local copy of the `.env` file:
```sh
$ cp .env.example .env
```
3. Update the `.env` with your login and API information. To obtain this, do the following:
    - Go to [laracasts.com and navigate to the Browse page](https://laracasts.com/search).
    - Open your browsers Dev Tools and open the Network tab, then refresh the page.
    - Find an XHR request to `algolia.net` and look at the request URL.
    - Within the URL, find the GET parameters:
        - Copy the `x-algolia-application-id` value to `ALGOLIA_APP_ID` in `.env`.
        - Copy the `x-algolia-api-key` value to `ALGOLIA_API_KEY` in `.env`.

### Using your local machine
1. Install project dependencies:
```sh
$ composer install
```
2. To run a download of all content, run the following command:
```sh
$ php start.php [empty for all OR provide flags]
```
3. See [downloading specific series or lessons](#downloading-specific-series-or-lessons) for optional flags.

### Using Docker
2. Build the image:
```sh
$ docker-compose build
```
3. Then, run the command of your choice as if we were running it locally, but instead against the docker container:
```sh
$ docker-compose run --rm laracastdl php ./start.php [empty for all OR provide flags]
```
3. See [downloading specific series or lessons](#downloading-specific-series-or-lessons) for optional flags.

Also works in the browser, but is better from the cli because of the instant feedback.

## Downloading specific series or lessons
- You can use series and lessons names
- You can use series and lessons slugs (preferred because there are some custom slugs too)
- You can download multiples series/lessons

### Commands to download an entire series
You can either use the Series slug (preferred):
```sh
$ php start.php -s "series-slug-example"
$ php start.php --series-name "series-slug-example"
```
Or the Series name:
```sh
$ php start.php -s "Series name example"
$ php start.php --series-name "Series name example"
```

### Command to download specific lessons
You can either use the Lessons slug (preferred):
```sh
$ php start.php -l "lesson-slug-example"
$ php start.php --lesson-name "lesson-slug-example"
```
Or the Lesson name:
```sh
$ php start.php -l "Lesson name example"
$ php start.php --lesson-name "Lessons name example"
```

## Troubleshooting
If you have a `cURL error 60: SSL certificate problem: self signed certificate in certificate chain` or `SLL error: cURL error 35` do this:

- Download [http://curl.haxx.se/ca/cacert.pem](http://curl.haxx.se/ca/cacert.pem)
- Add `curl.cainfo = "PATH_TO/cacert.pem"` to your php.ini

And you are done! If using apache you may need to restart it.

## License

This library is under the MIT License, see the complete license [here](LICENSE)
