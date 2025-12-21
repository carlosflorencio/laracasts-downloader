# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

Laracasts Downloader is a PHP CLI application that syncs a local folder with the Laracasts website, automatically downloading new lessons and series or the entire catalog. It supports two download sources: direct Laracasts downloads (limited to 30/day) or Vimeo extraction (with customizable quality up to 2160p).

## Common Commands

### Setup & Dependencies
```sh
composer install          # Install dependencies
composer require package-name
composer update
```

### Code Quality
```sh
composer run lint         # Check code style (Laravel Pint)
composer run lint:fix     # Auto-fix code style issues
./vendor/bin/rector       # Run Rector for modernization and refactoring
```

### Running the Application
```sh
php start.php                                    # Download all new episodes
php start.php -s "series-slug"                  # Download specific series
php start.php -s "series-slug" -e "1,5,10"     # Download specific episodes
php start.php --series-name "series-slug" --cache-only  # Use cached data only
```

### Docker
```sh
docker-compose build
docker-compose run --rm composer install
docker-compose run --rm laracastdl php ./start.php [options]
```

## Project Structure

### Core Architecture Flow

1. **Entry Point** (`start.php`): Bootstraps the application and initializes the main Downloader class
2. **Downloader** (`App/Downloader.php`): Main orchestrator that:
   - Manages authentication
   - Collects series/episodes data from Laracasts
   - Compares local vs remote to find new episodes
   - Coordinates downloads

3. **HTTP Layer** (`App/Http/Resolver.php`):
   - Handles all HTTP communication with Laracasts
   - Manages authentication and CSRF tokens
   - Provides methods to fetch series data and download links
   - Abstracts download logic (delegates to VimeoDownloader for Vimeo source)

4. **Data Scraping** (`App/Laracasts/Controller.php` + `App/Html/Parser.php`):
   - Fetches paginated series data via `Resolver::getSeries()`
   - Parses HTML responses to extract episode details
   - Implements caching to avoid re-scraping unchanged series
   - Supports filtered series/episode retrieval

5. **Vimeo Integration** (`App/Vimeo/`):
   - `VimeoDownloader`: Handles Vimeo source downloads
   - Downloads both video and audio segments separately, then merges with FFmpeg
   - `VimeoRepository`: Fetches Vimeo master.json containing segment URLs
   - `DTO` classes: Data transfer objects for Vimeo API responses

6. **File System** (`App/System/Controller.php`):
   - Uses League Flysystem for filesystem abstraction
   - Manages local folder structure (series/lessons organization)
   - Handles caching and cache invalidation

### Key Design Decisions

- **Caching Strategy**: Series data is cached in `Downloads/cache.php` to speed up subsequent runs. Cache is invalidated per-series when episode counts change.
- **Two Download Sources**:
  - Laracasts direct links (30/day limit, no quality control)
  - Vimeo extraction (quality configurable, requires FFmpeg)
- **Filter System**: Supports multiple series and per-series episode filtering via command-line options
- **Progress Tracking**: Uses Ubench for timing and memory profiling

## Configuration

Create a `.env` file (copy from `.env.example`) with:
- `EMAIL`, `PASSWORD`: Laracasts account credentials
- `LOCAL_PATH`, `LESSONS_FOLDER`, `SERIES_FOLDER`: Local directory structure
- `VIDEO_QUALITY`: Resolution for Vimeo downloads (240p-2160p)
- `DOWNLOAD_SOURCE`: Either "vimeo" or "laracasts"
- `TIMEZONE`: PHP timezone

## Code Quality Standards

- **PHP 8.3+**: Strict types enforced, readonly properties, modern syntax
- **Pint Configuration**: Uses Laravel preset with strict import checking
- **Rector Automation**: Dead code removal, type declarations, PHP 8.3 modernization
- **Dependencies**:
  - Guzzle: HTTP client with cookie jar for session management
  - Symfony DOM Crawler: HTML parsing
  - League Flysystem: Filesystem abstraction
  - php-dotenv: Environment variable management
  - Cocur Slugify: URL slug generation

## Development Notes

- **SSL Verification Disabled**: `'verify' => false` is used in HTTP requests due to certificate issues on some systems. This is documented as a potential troubleshooting step.
- **FFmpeg Requirement**: Vimeo downloads merge video/audio using FFmpeg with platform-specific command handling (Windows vs Unix paths).
- **No Tests**: The project currently has no automated test suite. Any additions should follow PSR-12 standards.
- **Recent Work**: Branch `001-parallel-downloads` indicates ongoing work on parallel download optimization.
