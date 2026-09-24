<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;

final class DatabaseManager
{
    public function __construct(private readonly Explorer $database) {}

    public function getCategories()
    {
        return $this->database->table('categories')->where('is_active', 1)->order('parent_id, sort_order, name');
    }

    public function getCategoryBySlug(string $slug)
    {
        return $this->database->table('categories')->where('slug', $slug)->fetch();
    }

    public function getBooks()
    {
        return $this->database->table('books')->order('title');
    }

    public function getBooksByCategory(ActiveRow $category)
    {
        $categoryIds = [$category->id];

        if ($category->parent_id === null) {
            $categoryIds = $this->database->table('categories')->where('parent_id', $category->id)->fetchPairs(null, 'id');
        }

        return $this->database->table('books')->where('category_id', $categoryIds)->order('title');
    }
}
