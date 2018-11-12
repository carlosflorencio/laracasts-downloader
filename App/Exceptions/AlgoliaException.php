<?php
/**
 * Algolia Exception
 */
namespace App\Exceptions;

use Exception;
use Throwable;

/**
 * Class AlgoliaException
 * @package App\Exceptions
 */
class AlgoliaException extends Exception
{
    /**
     * AlgoliaException constructor.
     *
     * @param string $message
     * @param int $code
     * @param Throwable|null $previous
     */
    public function __construct(string $message = "", int $code = 0, Throwable $previous = null)
    {
        parent::__construct('Algolia error: ' . $message, $code, $previous);
    }
}
