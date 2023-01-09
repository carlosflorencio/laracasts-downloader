# Laracasts Downloader
[![Join the chat at https://gitter.im/laracasts-downloader](https://badges.gitter.im/laracasts-downloader.svg)](https://gitter.im/laracasts-downloader?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge&utm_content=badge)
[![SensioLabsInsight](https://insight.sensiolabs.com/projects/ac2fdb9a-222b-4244-b08e-af5d2f69845d/mini.png)](https://insight.sensiolabs.com/projects/ac2fdb9a-222b-4244-b08e-af5d2f69845d)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/iamfreee/laracasts-downloader/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/iamfreee/laracasts-downloader/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/iamfreee/laracasts-downloader/badges/build.png?b=master)](https://scrutinizer-ci.com/g/iamfreee/laracasts-downloader/build-status/master)

Downloads new lessons and series from laracasts if there are updates. Or the whole catalogue.

**Currently looking for maintainers.**

## Description
Syncs your local folder with the laracasts website, when there are new lessons the app download it for you.
If your local folder is empty, all lessons and series will be downloaded!

A .skip file is used to prevent downloading deleted lessons for these with space problems. Thanks to @vinicius73

Just call `php makeskips.php` before deleting the lessons.

> You need an active subscription account to use this script.


## Requirements
- PHP >= 7.2
- php-cURL
- php-xml
- php-json
- Composer
- [FFmpeg](https://www.google.com/url?sa=t&rct=j&q=&esrc=s&source=web&cd=&cad=rja&uact=8&ved=2ahUKEwio6vX03pT7AhU0X_EDHSx9BMkQFnoECAkQAQ&url=https%3A%2F%2Fffmpeg.org%2F&usg=AOvVaw19lCX0sMAnAOlyM2Pvp5-v) (required if ``DOWNLOAD_SOURCE=vimeo``)

OR

- Docker

## Installation
1. Clone this repo to your local machine.
2. Make a local copy of the `.env` file:
```sh
$ cp .env.example .env
```
3. Update your Laracasts account credentials (`EMAIL`, `PASSWORD`) in .env
4. Decide whether you want to use **vimeo** or **laracasts** as `DOWNLOAD_SOURCE`.
   By using Laracasts link you are limited to 30 downloads per day and can't customize video quality.
6. Choose your preferred quality (240p, 360p, 540p, 720p, 1080p, 1440p, 2160p) by changing **VIDEO_QUALITY** in ``.env``.
   (will be ignored if `DOWNLOAD_SOURCE=laracasts`)
7. The next steps, choose if you want a [local installation](#using-your-local-machine) or [a Docker based installation](#using-docker) and follow along.

### Details About Vimeo 

If you using vimeo source, will download 2 files for each episode, a video file and an audio file. 

After all download will be done, the project will merge files and will moving to your respective folder. 

### Using your local machine
1. Install project dependencies:
```sh
$ composer install
```
2. To run a download of all content, run the following command:
```sh
$ php start.php
```
3. See [downloading specific series or lessons](#downloading-specific-series-or-lessons) for optional flags.

### Using Docker
1. Build the image:
```sh
$ docker-compose build
```
2. Install project dependencies:
```sh
$ docker-compose run --rm composer
```
3. Then, run the command of your choice as if we were running it locally, but instead against the docker container:
```sh
$ docker-compose run --rm laracastdl php ./start.php [empty for all OR provide flags]
```
4. See [downloading specific series or lessons](#downloading-specific-series-or-lessons) for optional flags.

Also works in the browser, but is better from the cli because of the instant feedback.

## Options

### Disable Scrapping

The script scraps each Laracasts pages and caches them to memories its latest state
and stores them in ``Downloads/cache.php``. If you already make sure this file is updated
and do not want to experience impatience of scrapping; you can use ``--cache-only`` option.

```sh
php start.php --cache-only
```

### Download specific series
You can either use the Series slug (preferred):
```sh
$ php start.php -s "series-slug-example"
$ php start.php --series-name "series-slug-example"
```
Or the Series name (NOT recommended):
```sh
$ php start.php -s "Series name example"
$ php start.php --series-name "Series name example"
```

### Download specific episodes
You can provide episode number(s) separated by comma ```,```:

```sh
$ php start.php -s "lesson-slug-example" -e "12,15"
$ php start.php --series-name "series-slug-example" --series-episodes "12,15"
```

This will only download episodes which you mentioned in
-e or --series-episodes flag, it will also ignore already downloaded episodes
as usual.

```sh
$ php start.php -s "nuxtjs-from-scratch" -e "12,15" -s "laravel-from-scratch" -e "5"
```

It will download episode 12 and 15 for "nuxtjs-from-scratch" and episode 5 for "laravel-from-scratch" course.

```sh
$ php start.php -s "nuxtjs-from-scratch" -e "12,15" -s "laravel-from-scratch"
```

It will download episode 12 and 15 for "nuxtjs-from-scratch" course and all episodes for "laravel-from-scratch" course.

## Troubleshooting
If you have a `cURL error 60: SSL certificate problem: self signed certificate in certificate chain` or `SLL error: cURL error 35` do this:

- Download [http://curl.haxx.se/ca/cacert.pem](http://curl.haxx.se/ca/cacert.pem)
- Add `curl.cainfo = "PATH_TO/cacert.pem"` to your php.ini

And you are done! If using apache you may need to restart it.

## License

This library is under the MIT License, see the complete license [here](LICENSE)
