# Data Model: Parallel Downloads

## Entities

### 1. DownloadQueue
**Responsibility**: Manages the list of episodes to download and their states.
**Attributes**:
- `items`: List of `EpisodeWorkItem`
- `max_concurrency`: int (Configurable)
- `active_count`: int
**Methods**:
- `push(EpisodeWorkItem)`
- `pop()`
- `onComplete(EpisodeWorkItem)`

### 2. EpisodeWorkItem
**Responsibility**: Represents a single episode task.
**Attributes**:
- `episode_id`: string
- `slug`: string
- `destination_path`: string
- `state`: { PENDING, DOWNLOADING, MERGING, COMPLETE, FAILED }
- `segments`: List of `SegmentWorkItem` (for Vimeo)

### 3. SegmentWorkItem
**Responsibility**: Represents a specific file part (chunk).
**Attributes**:
- `url`: string
- `temp_path`: string
- `index`: int
- `size`: int (optional)
- `state`: { PENDING, DOWNLOADING, COMPLETE, FAILED }

## Relationships

- `Downloader` has 1 `DownloadQueue`.
- `DownloadQueue` has N `EpisodeWorkItem`.
- `EpisodeWorkItem` has N `SegmentWorkItem`.
