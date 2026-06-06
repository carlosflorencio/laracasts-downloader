# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

PHP 8.3 CLI tool that syncs a local folder with laracasts.com: scrapes the series catalogue, diffs it against already-downloaded files, and downloads new episodes (whole catalogue if the local folder is empty). Requires a `.env` (copy `.env.example`) with Laracasts credentials. FFmpeg must be on PATH when `DOWNLOAD_SOURCE=mux` (the default) or `vimeo`.

## Commands

```sh
composer install

# Run a full sync
php start.php

# Skip scraping, use Downloads/cache.json as-is
php start.php --cache-only

# Specific series (slug preferred) / episodes; -s/-e pairs can repeat
php start.php -s "series-slug" -e "12,15"

# Only fetch .chapters.txt sidecars and/or .vtt subtitles (no videos); require -s, combinable
php start.php -s "series-slug" -e "12,15" --chapters-only
php start.php -s "series-slug" --subtitles-only

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

- **`App\Http\Resolver`** — all Laracasts HTTP traffic, sharing one Guzzle `CookieJar`. Login is a CSRF flow (fetch homepage for `XSRF-TOKEN` cookie, POST to `/sessions`). `downloadEpisode()` branches on `DOWNLOAD_SOURCE` env: `laracasts` follows the page's download link redirect (30/day limit, fixed quality); `mux` (default) and `external` re-fetch the episode page for a fresh signed playback token (~2h TTL, so tokens from scrape time would expire mid-sync) and delegate to `MuxDownloader` / `ExternalDownloader`; `vimeo` delegates to `VimeoDownloader` (legacy — Laracasts no longer exposes Vimeo ids).
- **`App\Laracasts\Controller`** — catalogue scraping. Pages through `/series?page=N`; skips a series when the cached `episodes` count equals the live `episode_count` (this is the incremental-update mechanism). Episode lists come from each series' `/episodes/1` page.
- **`App\Html\Parser`** — Laracasts is an Inertia.js app, so all scraping reads the Inertia JSON from the `<script data-page>` tag (Symfony DomCrawler; falls back to the legacy `#app` `data-page` attribute) rather than parsing markup. Episode pages expose the current episode as `props.lesson`, the series episode lists under `props.series.chapters` (a "chapter" there is a series section, *not* a video chapter). Video chapter markers are derived from `props.lesson.transcriptSegments[].topicHeader` (`getEpisodeChapters()`; `ChapterMetadata` renders ffmetadata. `DOWNLOAD_CHAPTERS` modes: `embed`/`true` attaches them via `-map_chapters`, `file` saves a `.chapters.txt` sidecar, `both` does both; `commands/BackfillChapters.php` writes sidecars for an already-downloaded library). `extractJsonAfter()` brace-matches JSON out of raw JS (used for Vimeo's `window.playerConfig`).
- **`App\Mux\*`** — the current download path (Laracasts moved hosting from Vimeo to Mux). `MuxRepository` fetches `stream.mux.com/{playbackId}.m3u8?token={jwt}` and parses the HLS master playlist into `MasterPlaylistDTO`: video variants (height/bandwidth from `#EXT-X-STREAM-INF`), audio rendition groups and subtitle tracks (`#EXT-X-MEDIA` — audio is a separate rendition, *not* muxed into the video variant). `MuxDownloader` picks the variant matching `VIDEO_QUALITY` (fallback: highest), remuxes video+audio straight to the target `.mp4` via shell `ffmpeg -c copy` (single cross-platform command, args escaped), and optionally saves `.vtt` subtitles (`DOWNLOAD_SUBTITLES` env). Dubbed series carry multiple audio renditions per group (es/pt/de/ja AI dubs); selection prefers `AUDIO_LANGUAGE` env, then the `DEFAULT=YES` rendition (the original audio) — never rely on rendition order, and for yt-dlp an explicit `-f` chain is required or it picks the alphabetically last dub as "best". `ExternalDownloader` instead hands the signed master url to `EXTERNAL_TOOL` (yt-dlp gets quality/subtitle flags derived from the same envs; `EXTERNAL_TOOL_ARGS` appended verbatim) and on failure optionally falls back to `MuxDownloader` (`EXTERNAL_TOOL_FALLBACK`).
- **`App\Vimeo\*`** — legacy (episode JSON no longer carries `vimeoId`). `VimeoRepository` fetches `player.vimeo.com/video/{id}` (with `laracasts.com` Referer, required), regex-extracts streams, the DASH master URL (`akfire_interconnect_quic`/`google_skyfire` CDNs, with `window.playerConfig` fallback), and subtitle text tracks. `MasterDTO`/`VideoDTO` model the DASH master playlist; quality selection matches `VIDEO_QUALITY` env against stream list, falling back to highest. `VimeoDownloader` downloads video (`.m4v`) and audio (`.m4a`) as init segment + segments into the working directory, merges them with shell `ffmpeg` (separate Windows/`WINNT` and Unix command branches), deletes the parts, and optionally saves `.vtt` subtitles (`DOWNLOAD_SUBTITLES` env).
- **`App\System\Controller`** — Flysystem wrapper. Local state is *derived from the filesystem*: it scans `series/<slug>/` filenames and parses the episode number from the `NN-` prefix. It also reads/writes `cache.json`.

## Key Behaviors & Gotchas

- **The filesystem is the download state.** Episodes are saved as `series/<slug>/NN-<sanitized title>.mp4` (`%02d` number, special chars stripped by `Utils::parseEpisodeName`). The diff in `Utils::compareLocalAndOnlineSeries` compares episode-number prefixes against the online list, and skips a series entirely when local file count equals `episode_count`. Only `.mp4` files count (`.vtt` subtitles and `.part` leftovers are ignored by the scan). Renaming or deleting files triggers re-download.
- **`Downloads/cache.json`** caches the scraped catalogue between runs (`commands/ConvertCacheToJson.php` is a one-off migration from the legacy `cache.php`). The README's mention of `cache.php` is outdated.
- Config is read from `$_ENV` directly at point of use (e.g. `VIDEO_QUALITY` inside `VideoDTO`, `DOWNLOAD_SOURCE` inside `Resolver`), not centralized — grep for `$_ENV` when adding settings.
- All Laracasts requests use `'verify' => false` and rely on the shared cookie jar from login; new endpoints in `Resolver` must pass `cookies`.
- Output is `echo`-based via `Utils::write/writeln/box`; it supports both CLI and browser SAPIs.
