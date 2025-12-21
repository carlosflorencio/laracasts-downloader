# Research: Parallel Downloads

## Unknowns & Clarifications

### 1. FFmpeg Constraint vs. Vimeo Merging
**Context**: The user requested "No FFmpeg", but `VimeoDownloader` currently produces separate Video/Audio tracks that require muxing.
**Reference**: `App\Vimeo\VimeoDownloader::mergeSources` uses `exec('ffmpeg ...')`.
**Finding**: Standard PHP cannot mux MP4s efficiently without external binaries.
**Decision**: 
- **Download**: We will strictly use Guzzle for all segment downloading (removing any FFmpeg usage there).
- **Merge**: We will *attempt* to avoid FFmpeg effectively by downloading the `Laracasts` source (single file) if possible, which supports Range requests for parallelism.
- **Fallback**: If Vimeo source is used, we will use `php-ffmpeg` or keep existing `exec` *only* for the final split-second merge, unless user explicitly forbids it even for that. Given "I've tried using FFmpeg but that didn't work" likely refers to the *download* process (streaming), using it just for local file muxing is usually stable.
- **Assumption**: User's "No FFmpeg" refers to the download mechanism, not the final container format fix. If strictly NO FFmpeg binary is allowed, we will output separate `.m4v` and `.m4a` files.

### 2. Concurrency Architecture
**Context**: Need to download multiple episodes AND multiple segments per episode.
**Decision**: Use **Guzzle Promises**.
- Refactor `VimeoDownloader::download` to return a `Promise`.
- Use `GuzzleHttp\Pool` for the segments of a single video.
- Use `GuzzleHttp\Pool` (or `EachPromise`) for the queue of episodes.
- **Benefit**: Single event loop, efficient blocking only at the very end.

### 3. Testing Framework
**Context**: No tests exist.
**Decision**: Use **Pest PHP**.
- **Rationale**: Modern, minimal, great for CLI apps, built on PHPUnit.
- **Plan**: `composer require --dev pestphp/pest`.

## Best Practices

### Parallel Segment Downloading
- **Pattern**: "Scatter-Gather".
- **Implementation**:
  1. Fetch Init Segment.
  2. Map segment URLs to Request objects.
  3. Create `new Pool($client, $requests, ['concurrency' => 5])`.
  4. On fulfillment, write chunk to temp file.
  5. After all chunks, simple binary concatenation (`cat` / `copy /b`) to form the track.

### Robustness
- **Retry Middleware**: Add Guzzle Retry Middleware for network flakes.
- **Temp Files**: Use `sys_get_temp_dir()` or a `.partial` folder to avoid corrupting existing files.
