<?php

declare(strict_types=1);

namespace App\Model;

/**
 * The import file as a whole cannot be processed (invalid JSON, wrong structure, no books).
 */
final class BookImportException extends \RuntimeException
{
}
