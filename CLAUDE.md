# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PHP 8.3 CLI tool that syncs a local folder with laracasts.com: scrapes the series catalogue, diffs it against already-downloaded files, and downloads new episodes (whole catalogue if the local folder is empty). Requires a `.env` (copy `.env.example`) with Laracasts credentials. FFmpeg must be on PATH when `DOWNLOAD_SOURCE=vimeo`.

## Commands

```sh
composer install

# Run a full sync
php start.php

# Skip scraping, use Downloads/cache.json as-is
php start.php --cache-only

# Specific series (slug preferred) / episodes; -s/-e pairs can repeat
php start.php -s "series-slug" -e "12,15"

# Lint (Laravel Pint, preset "laravel")
composer lint        # check only (pint --test)
composer lint:fix    # auto-fix

# Rector (PHP 8.3 sets + dead code + code quality + type declarations, App/ only)
vendor/bin/rector
```

Docker alternative: `docker-compose build`, `docker-compose run --rm composer`, then `docker-compose run --rm laracastdl php ./start.php [flags]`.

There is no test suite (`tests/` is empty, no PHPUnit configured).

## Architecture

Entry flow: `start.php` → `bootstrap.php` (composer autoload, phpdotenv, defines `BASE_FOLDER`, `SERIES_FOLDER`, `LARACASTS_BASE_URL` constants) → wires Guzzle client, Flysystem rooted at `BASE_FOLDER` (default `Downloads/`), and Ubench into `App\Downloader::start()`.

`App\Downloader` is the main cycle: authenticate → parse CLI flags via `getopt` into series/episode filters → read local state → fetch online catalogue → diff → download each new episode. Everything else hangs off it:

- **`App\Http\Resolver`** — all Laracasts HTTP traffic, sharing one Guzzle `CookieJar`. Login is a CSRF flow (fetch homepage for `XSRF-TOKEN` cookie, POST to `/sessions`). `downloadEpisode()` branches on `DOWNLOAD_SOURCE` env: `laracasts` follows the page's download link redirect (30/day limit, fixed quality); `vimeo` delegates to `VimeoDownloader`.
- **`App\Laracasts\Controller`** — catalogue scraping. Pages through `/series?page=N`; skips a series when the cached `episodes` count equals the live `episode_count` (this is the incremental-update mechanism). Episode lists come from each series' `/episodes/1` page.
- **`App\Html\Parser`** — Laracasts is an Inertia.js app, so all scraping reads the JSON in the `#app` element's `data-page` attribute (Symfony DomCrawler) rather than parsing markup. `extractJsonAfter()` brace-matches JSON out of raw JS (used for Vimeo's `window.playerConfig`).
- **`App\Vimeo\*`** — `VimeoRepository` fetches `player.vimeo.com/video/{id}` (with `laracasts.com` Referer, required), regex-extracts streams, the DASH master URL (`akfire_interconnect_quic`/`google_skyfire` CDNs, with `window.playerConfig` fallback), and subtitle text tracks. `MasterDTO`/`VideoDTO` model the DASH master playlist; quality selection matches `VIDEO_QUALITY` env against stream list, falling back to highest. `VimeoDownloader` downloads video (`.m4v`) and audio (`.m4a`) as init segment + segments into the working directory, merges them with shell `ffmpeg` (separate Windows/`WINNT` and Unix command branches), deletes the parts, and optionally saves `.vtt` subtitles (`DOWNLOAD_SUBTITLES` env).
- **`App\System\Controller`** — Flysystem wrapper. Local state is *derived from the filesystem*: it scans `series/<slug>/` filenames and parses the episode number from the `NN-` prefix. It also reads/writes `cache.json`.

## Key Behaviors & Gotchas

- **The filesystem is the download state.** Episodes are saved as `series/<slug>/NN-<sanitized title>.mp4` (`%02d` number, special chars stripped by `Utils::parseEpisodeName`). The diff in `Utils::compareLocalAndOnlineSeries` compares episode-number prefixes against the online list, and skips a series entirely when local file count equals `episode_count`. Renaming or deleting files triggers re-download.
- **`Downloads/cache.json`** caches the scraped catalogue between runs (`commands/ConvertCacheToJson.php` is a one-off migration from the legacy `cache.php`). The README's mention of `cache.php` is outdated.
- Config is read from `$_ENV` directly at point of use (e.g. `VIDEO_QUALITY` inside `VideoDTO`, `DOWNLOAD_SOURCE` inside `Resolver`), not centralized — grep for `$_ENV` when adding settings.
- All Laracasts requests use `'verify' => false` and rely on the shared cookie jar from login; new endpoints in `Resolver` must pass `cookies`.
- Output is `echo`-based via `Utils::write/writeln/box`; it supports both CLI and browser SAPIs.
