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

## Requirements
- PHP >= 8.3
- php-cURL
- php-xml
- php-json
- Composer
- [FFmpeg](https://www.google.com/url?sa=t&rct=j&q=&esrc=s&source=web&cd=&cad=rja&uact=8&ved=2ahUKEwio6vX03pT7AhU0X_EDHSx9BMkQFnoECAkQAQ&url=https%3A%2F%2Fffmpeg.org%2F&usg=AOvVaw19lCX0sMAnAOlyM2Pvp5-v) (required if ``DOWNLOAD_SOURCE=mux``, the default)

OR

- Docker

## Installation
1. Clone this repo to your local machine.
2. Make a local copy of the `.env` file:
```sh
$ cp .env.example .env
```
3. Update your Laracasts account credentials (`EMAIL`, `PASSWORD`) in .env
4. Decide whether you want to use **mux** (default), **external** or **laracasts** as `DOWNLOAD_SOURCE`.
   By using Laracasts link you are limited to 30 downloads per day and can't customize video quality.
   With **external** the signed stream url is handed to an external tool such as [yt-dlp](https://github.com/yt-dlp/yt-dlp) (see the `EXTERNAL_TOOL*` settings in `.env.example`).
   Laracasts is migrating its videos from Mux to its own **Cloudflare** CDN, lesson by lesson; both **mux** and **external** detect this automatically per lesson and download whichever applies, so no setting change is needed.
   (**vimeo** is kept for legacy reasons but no longer works — Laracasts moved its videos off Vimeo.)
6. Choose your preferred quality (240p, 360p, 540p, 720p, 1080p, 1440p, 2160p) by changing **VIDEO_QUALITY** in ``.env``.
   (will be ignored if `DOWNLOAD_SOURCE=laracasts`)
7. The next steps, choose if you want a [local installation](#using-your-local-machine) or [a Docker based installation](#using-docker) and follow along.

### Details About Mux / Cloudflare

If you are using the mux source, FFmpeg downloads the video and audio HLS streams and merges
them straight into a single mp4 in your series folder (stream copy, no re-encoding).
Lessons already migrated to Laracasts' Cloudflare CDN are handled the same way (one FFmpeg
stream copy; the login cookie is forwarded automatically) — `DOWNLOAD_SUBTITLES` and
`DOWNLOAD_CHAPTERS` work identically on both.

Set `DOWNLOAD_SUBTITLES=true` in `.env` to also save the closed captions next to each episode (`.vtt`).
By default only the default/original track (normally English) is saved; set `SUBTITLE_LANGUAGE=en,es`
to pick specific languages, or `SUBTITLE_LANGUAGE=all` for every available track.

Set `DOWNLOAD_CHAPTERS=embed` to embed chapter markers (built from the episode's transcript topics)
into each mp4 — players like VLC, mpv and PotPlayer show them as a native chapter menu.
Use `DOWNLOAD_CHAPTERS=file` to instead save a `NN-title.chapters.txt` ffmetadata sidecar
next to each episode, which you can merge into a video yourself (`both` embeds **and** keeps
the sidecar, placing it in a `#merged` subfolder since its chapters are already embedded):

```sh
ffmpeg -i "01-foo.mp4" -i "01-foo.chapters.txt" -map_chapters 1 -c copy "01-foo.chaptered.mp4"
```

Set `WRITE_METADATA=true` to tag each finished mp4 with the lesson number (`track`),
the series title (`album`), the lesson title (`title`) and the instructor (`artist`)
via a final ffmpeg stream-copy pass.

Set `WRITE_PLAYLIST=true` to (re)generate a `#<slug>.m3u8` playlist in each series folder
after downloading — a plain list of the episode mp4s in order, so you can play the whole
series in VLC/mpv/PotPlayer. The `#` keeps it at the top of the folder.

On Windows, `commands/merge-chapters.ps1` batch-merges every sidecar in a folder into its
video in place (skips videos that already have chapters, so it is safe to re-run):

```powershell
cd "path\to\series\some-series"
& "path\to\laracasts-downloader\commands\merge-chapters.ps1"

# or the whole library at once
& .\commands\merge-chapters.ps1 -Path "path\to\series" -Recurse

# -MoveSidecars additionally moves each embedded sidecar into a "#merged"
# subfolder next to its video (also when the chapters were already embedded)
& .\commands\merge-chapters.ps1 -Path "path\to\series" -Recurse -MoveSidecars
```

To fetch **only** the chapter sidecars and/or subtitles of a series or single episodes,
or fix the downloaded files' last-modified time to the lesson's original publish date
(no videos, regardless of what is downloaded locally; the flags can be combined):

```sh
php start.php -s "series-slug" --chapters-only
php start.php -s "series-slug" -e "12,15" --chapters-only
php start.php -s "series-slug" --subtitles-only
php start.php -s "series-slug" --timestamps-only
```

To (re)write the metadata tags (lesson number, series title, lesson title, instructor) into
already-downloaded videos, use `--metadata-only`. Unlike the flags above it does **not** require `-s`:
with no series filter it walks the **whole** local library; narrow it with `-s`/`-e` if you prefer.

```sh
php start.php --metadata-only                       # every downloaded video
php start.php --metadata-only -s "series-slug"      # one series
php start.php --metadata-only -s "series-slug" -e "12,15"
```

Likewise, to (re)build the `#<slug>.m3u8` playlists for an already-downloaded library
without downloading anything, use `--playlist-only`. It also does **not** require `-s`
(narrow it with `-s` if you prefer; `-e` is ignored, a playlist always covers the whole series):

```sh
php start.php --playlist-only                        # every downloaded series
php start.php --playlist-only -s "series-slug"       # one series
```

For episodes you downloaded **before** this feature existed, backfill the sidecars for the
whole local library without re-downloading any videos:

```sh
php commands/BackfillChapters.php              # whole library
php commands/BackfillChapters.php -s "series-slug" -e "1,5"
```

Downloads automatically get their last-modified time set to the lesson's original publish
date on laracasts.com (day precision, normalised to 12:00). For files downloaded before
this feature existed, backfill the whole library (one request per series):

```sh
php commands/BackfillTimestamps.php              # whole library
php commands/BackfillTimestamps.php --dry-run    # preview only
php commands/BackfillTimestamps.php -s "series-slug" -e "1,5"
```

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
