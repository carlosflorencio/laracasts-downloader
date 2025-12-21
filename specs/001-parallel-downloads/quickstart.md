# Quickstart: Parallel Downloads

## Overview
This feature dramatically speeds up downloads by fetching multiple episodes and segments simultaneously.

## Usage

### Default Behavior
By default, the downloader now uses sensible concurrency values: (e.g., 3 episodes, 5 segments).

### Custom Concurrency
Control the load on your system/network with new flags:

```bash
# Download series with 5 episodes in parallel, and 10 segments per video
php start.php -s "laravel-from-scratch" --concurrency-episodes=5 --concurrency-segments=10
```

### Configuration
You can also set defaults in `.env`:

```ini
MAX_CONCURRENT_EPISODES=3
MAX_CONCURRENT_SEGMENTS=5
```

## Performance Tips
- High concurrency uses more CPU/Disk IO.
- If downloads fail (timeout), try reducing concurrency.
