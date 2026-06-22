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

# Only fetch .chapters.txt sidecars and/or .vtt subtitles, or fix mtimes to the
# lesson publish dates (no videos); require -s, combinable
php start.php -s "series-slug" -e "12,15" --chapters-only
php start.php -s "series-slug" --subtitles-only
php start.php -s "series-slug" --timestamps-only

# (Re)write mp4 metadata tags into already-downloaded videos. Unlike the other
# *-only flags it does NOT require -s: with no filter it backfills the whole
# local library (narrow with -s/-e).
php start.php --metadata-only

# (Re)generate the "#<slug>.m3u8" series playlists for the local library.
# Also does NOT require -s (narrow with -s; -e is ignored).
php start.php --playlist-only

# Rebuild cache.json from a fresh full-catalogue scrape (re-fetches EVERY
# series, bypassing the incremental skip, so the schema is brought current).
# Downloads nothing.
php start.php --refresh-cache

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

- **`App\Http\Resolver`** — all Laracasts HTTP traffic, sharing one Guzzle `CookieJar`. Login is a CSRF flow (fetch homepage for `XSRF-TOKEN` cookie, POST to `/sessions`). `downloadEpisode()` branches on `DOWNLOAD_SOURCE` env: `laracasts` follows the page's download link redirect (30/day limit, fixed quality); `mux` (default) and `external` re-fetch the episode page at download time (Mux tokens have a ~2h TTL, and a lesson may have been migrated to Cloudflare since the scrape) and **auto-detect the host per-lesson**: a `props.lesson.cloudflarePlayback` routes to `CloudflareDownloader` (or `ExternalDownloader::downloadFromUrl`), otherwise the Mux path (`MuxDownloader` / `ExternalDownloader`) runs with a fresh signed token. `cookieHeaderFor()` builds the `Cookie` header that authorizes the cookie-gated Cloudflare CDN from the shared jar. `vimeo` delegates to `VimeoDownloader` (legacy — Laracasts no longer exposes Vimeo ids). Successful downloads optionally get the lesson number/series title/lesson title/instructor (artist) written into the mp4 tags (`applyMetadata` → `App\Utils\Metadata`, toggled by `WRITE_METADATA`; the instructor is `props.lesson.author.profile.full_name` via `Parser::getEpisodeInstructor`, also cached per-episode by `getEpisodesData`; `Metadata::write` preserves the file mtime across the remux; `--metadata-only` backfills the same tags into already-downloaded videos via `updateEpisodeMetadata`, library-wide when no `-s` is given — instructors come from cache, else a one-shot `fetchSeriesInstructors`/`getEpisodeInstructors` per-series episode-list fetch), then their mtime stamped to the lesson's publish date (`applyPublishDate`, best-effort); `commands/BackfillTimestamps.php` backfills older libraries.
- **`App\Laracasts\Controller`** — catalogue scraping. Pages through `/series?page=N`; skips a series when the cached `episodes` count equals the live `episode_count` (this is the incremental-update mechanism). Episode lists come from each series' `/episodes/1` page.
- **`App\Html\Parser`** — Laracasts is an Inertia.js app, so all scraping reads the Inertia JSON from the `<script data-page>` tag (Symfony DomCrawler; falls back to the legacy `#app` `data-page` attribute) rather than parsing markup. Episode pages expose the current episode as `props.lesson`, the series episode lists under `props.series.chapters` (a "chapter" there is a series section, *not* a video chapter). Playback data lives on the lesson: `muxPlaybackId`/`muxTokens` for Mux lessons, or `cloudflarePlayback` (`{src, captions[], ...}`) for lessons migrated to the Cloudflare CDN — `getEpisodeMuxPlayback()` / `getEpisodeCloudflarePlayback()` read these. The catalogue filter in `getEpisodesData()` keeps an episode if it carries *any* of `muxPlaybackId`/`vimeoId`/`cloudflarePlayback.src` (otherwise it is upcoming) — Cloudflare listing entries have no `muxPlaybackId`, so omitting the cloudflare check silently drops migrated series as "0 new episodes". Video chapter markers are derived from `props.lesson.transcriptSegments[].topicHeader` (`getEpisodeChapters()`; `ChapterMetadata` renders ffmetadata. `DOWNLOAD_CHAPTERS` modes: `embed`/`true` attaches them via `-map_chapters`, `file` saves a `.chapters.txt` sidecar, `both` does both; `commands/BackfillChapters.php` writes sidecars for an already-downloaded library). `extractJsonAfter()` brace-matches JSON out of raw JS (used for Vimeo's `window.playerConfig`).
- **`App\Hls\*`** — shared HLS master-playlist parsing used by both the Mux and Cloudflare paths. `MasterPlaylistParser::parse($content, $baseUrl)` extracts video variants (`#EXT-X-STREAM-INF`), audio renditions and subtitle tracks (`#EXT-X-MEDIA`), resolving relative variant URIs against `$baseUrl` (Cloudflare serves `720p/index.m3u8`-style relatives; Mux's absolute signed URIs pass through). `MasterPlaylistDTO::getVideoByQuality()` picks the `VIDEO_QUALITY` height (fallback: highest); `getAudioByGroupId()` handles dubbed renditions.
- **`App\Cloudflare\*`** — download path for lessons on the Cloudflare CDN (`media.laracasts.com`). The CDN is **cookie-gated** (no token in the url), and **audio is muxed into each variant** (no separate rendition). `CloudflareRepository` fetches the master with the `Cookie` header; `CloudflareDownloader` picks the variant by `VIDEO_QUALITY` and remuxes it to `.mp4` with a single `ffmpeg -c copy`, forwarding the cookie via `-headers "Cookie: …"` (sent **without** a trailing CRLF so it survives `escapeshellarg` + cmd.exe on Windows). Subtitles come from the lesson's `cloudflarePlayback.captions[]` (direct `.vtt` GETs — they are *not* in the HLS manifest, so the `external`/yt-dlp path fetches them itself too via the shared `CloudflareDownloader::selectCaptions()`). `SUBTITLE_LANGUAGE` (`App\Utils\SubtitleLanguages::filter()`) picks which captions — comma-list, `all`, or empty for the default/original track (for Cloudflare the original is the non-`ai_translation` caption) — and `DOWNLOAD_SUBTITLES` picks the delivery (embed/sidecar/both) via `App\Utils\Subtitles`.
- **`App\Mux\*`** — the Mux download path (Laracasts moved hosting Vimeo → Mux → Cloudflare; older lessons remain on Mux). `MuxRepository` fetches `stream.mux.com/{playbackId}.m3u8?token={jwt}` and parses it via `App\Hls\MasterPlaylistParser` into a `MasterPlaylistDTO`: video variants, audio rendition groups and subtitle tracks (`#EXT-X-MEDIA` — audio is a separate rendition, *not* muxed into the video variant). `MuxDownloader` picks the variant matching `VIDEO_QUALITY` (fallback: highest), remuxes video+audio straight to the target `.mp4` via shell `ffmpeg -c copy` (single cross-platform command, args escaped), and applies subtitles via `App\Utils\Subtitles` (`DOWNLOAD_SUBTITLES`; tracks chosen by `SUBTITLE_LANGUAGE`/`SubtitleLanguages::filter()`, honoring the `#EXT-X-MEDIA DEFAULT=YES` track). Dubbed series carry multiple audio renditions per group (es/pt/de/ja AI dubs); selection prefers `AUDIO_LANGUAGE` env, then the `DEFAULT=YES` rendition (the original audio) — never rely on rendition order, and for yt-dlp an explicit `-f` chain is required or it picks the alphabetically last dub as "best". `ExternalDownloader` instead hands the signed master url to `EXTERNAL_TOOL` (yt-dlp gets quality flags from `VIDEO_QUALITY`; `EXTERNAL_TOOL_ARGS` appended verbatim) and on failure optionally falls back to `MuxDownloader` (`EXTERNAL_TOOL_FALLBACK`). Subtitles are **not** delegated to yt-dlp — after a successful download `ExternalDownloader` re-fetches the Mux master for its subtitle renditions (`handleMuxSubtitles`) and routes them through `App\Utils\Subtitles`, so embed/sidecar/both behaves identically to the built-in paths. Its `downloadFromUrl()` handles Cloudflare lessons: same tool, cookie forwarded via `--add-header`, captions fetched directly (`Subtitles::materializeDirect`), and the fallback is `CloudflareDownloader`.
- **`App\Vimeo\*`** — legacy (episode JSON no longer carries `vimeoId`). `VimeoRepository` fetches `player.vimeo.com/video/{id}` (with `laracasts.com` Referer, required), regex-extracts streams, the DASH master URL (`akfire_interconnect_quic`/`google_skyfire` CDNs, with `window.playerConfig` fallback), and subtitle text tracks. `MasterDTO`/`VideoDTO` model the DASH master playlist; quality selection matches `VIDEO_QUALITY` env against stream list, falling back to highest. `VimeoDownloader` downloads video (`.m4v`) and audio (`.m4a`) as init segment + segments into the working directory, merges them with shell `ffmpeg` (separate Windows/`WINNT` and Unix command branches), deletes the parts, and applies subtitles via `App\Utils\Subtitles` (`DOWNLOAD_SUBTITLES`).
- **`App\Utils\Subtitles`** — subtitle delivery, mirroring `ChapterMetadata`. `DOWNLOAD_SUBTITLES` modes: `embed`/`true` muxes the `.vtt` tracks into the mp4 as `mov_text` streams (a stream-copy remux that carries chapters over, tags `language` via an ISO 639‑1→2 map, sets the default track's disposition, and is `!`/`%`-safe + mtime-preserving like `Metadata::write`), `sidecar`/`file` saves them as `.vtt` in a `subs/` subfolder, `both` does both, empty/`false`/`off` disables. Each downloader builds a track list, narrows it with `SubtitleLanguages::filter()`, materialises the chosen tracks to temp `.vtt` (`materializeHls()` for Mux HLS renditions via ffmpeg, `materializeDirect()` for Cloudflare/Vimeo direct `.vtt` via Guzzle + optional cookie), then `deliver()` embeds and/or copies to `subs/` and cleans up the temps. `--subtitles-only` always writes sidecars into `subs/` (regardless of mode); the existing-subs check in `Resolver::downloadEpisodeSubtitles` looks in `subs/`.
- **`App\System\Controller`** — Flysystem wrapper. Local state is *derived from the filesystem*: it scans `series/<slug>/` filenames and parses the episode number from the `NN-` prefix. It also reads/writes `cache.json`.

## Key Behaviors & Gotchas

- **The filesystem is the download state.** Episodes are saved as `series/<slug>/NN-<sanitized title>.mp4` (`%02d` number; `Utils::parseEpisodeName` keeps the lesson title verbatim except for Windows-illegal chars `\/:*?"<>|` and trailing dots/spaces). The diff in `Utils::compareLocalAndOnlineSeries` compares episode-number prefixes against the online list, and skips a series entirely when local file count equals `episode_count`. Only `.mp4` files count (`.vtt` subtitles and `.part` leftovers are ignored by the scan). Renaming or deleting files triggers re-download. `commands/ReconcileNames.php` (`--dry-run`, `-s slug`) renames an existing library (mp4s, sidecars, `#<slug>.m3u8` playlist entries) to the current cache.json titles after sanitizer changes.
- **Series playlists.** `App\Utils\Playlist::generate()` writes each series' `#<slug>.m3u8` — the episode mp4s in order, one bare basename per line (the simple format `ReconcileNames` keeps in sync; CRLF, no `#EXTM3U` header). `WRITE_PLAYLIST=true` regenerates it after a series downloads (the hook in `Downloader::downloadEpisodes` is source-agnostic — mux/external/cloudflare), and `--playlist-only` rebuilds it across the local library (`Downloader::generatePlaylists`, no `-s` required; `-e` ignored). Generation scans `BASE_FOLDER/series/<slug>/*.mp4` directly (top-level only, skipping `#merged`).
- **`cache.json`** (at `BASE_FOLDER/cache.json`) caches the scraped catalogue between runs (`commands/ConvertCacheToJson.php` is a one-off migration from the legacy `cache.php`). Schema per series: `slug`, `title`, `path`, `episode_count`, `is_complete`, `episodes[]`; per episode: `title`, `number`, `instructor`, `published` (all written by `Parser::mapSerieData` / `getEpisodesData`). **The incremental scrape (`isSerieUpdated`) keeps a cached series as-is whenever its episode count is unchanged, so schema changes do not propagate to existing entries** — `php start.php --refresh-cache` forces a fresh full re-scrape (`Downloader::refreshCatalogue`, passing an empty cache so every series is re-fetched) to bring the whole file to the current schema.
- Config is read from `$_ENV` directly at point of use (e.g. `VIDEO_QUALITY` inside `VideoDTO`, `DOWNLOAD_SOURCE` inside `Resolver`), not centralized — grep for `$_ENV` when adding settings.
- All Laracasts requests use `'verify' => false` and rely on the shared cookie jar from login; new endpoints in `Resolver` must pass `cookies`.
- Output is `echo`-based via `Utils::write/writeln/box`; it supports both CLI and browser SAPIs.
