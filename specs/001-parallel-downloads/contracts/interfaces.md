# Contracts

## Interface: `AsyncDownloader`

All downloaders (Vimeo, Laracasts) must implement this interface to support the parallel queue.

```php
namespace App\Contracts;

use GuzzleHttp\Promise\PromiseInterface;

interface AsyncDownloader
{
    /**
     * Initiates the download process and returns a promise.
     * The promise resolves when the file is fully downloaded and (if needed) merged.
     *
     * @param string $sourceId (e.g. Vimeo ID or URL)
     * @param string $destinationPath
     * @param array $options (e.g. concurrency settings)
     * @return PromiseInterface
     */
    public function downloadAsync(string $sourceId, string $destinationPath, array $options = []): PromiseInterface;
}
```

## Interface: `ConcurrencyManager`

Manages the pool of active downloads.

```php
namespace App\Contracts;

use Closure;
use GuzzleHttp\Promise\PromiseInterface;

interface ConcurrencyManager
{
    /**
     * Adds a task generator to the queue.
     * 
     * @param iterable $tasks Iterator yielding Promises
     * @param int $concurrency Max concurrent tasks
     * @return PromiseInterface Resolves when all tasks complete
     */
    public function pool(iterable $tasks, int $concurrency): PromiseInterface;
}
```
