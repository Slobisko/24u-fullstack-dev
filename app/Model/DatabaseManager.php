<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Database\Explorer;

final class DatabaseManager
{
    public function __construct(private readonly Explorer $database) {}

    public function getCategories()
    {
        return $this->database->table('categories')->where('is_active', 1)->order('parent_id, sort_order, name');
    }
}
