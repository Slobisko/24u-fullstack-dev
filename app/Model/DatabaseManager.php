<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;

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
        return $this->withRating($this->database->table('books'))->order('title');
    }

    public function getBooksByCategory(ActiveRow $category)
    {
        $categoryIds = [$category->id];

        if ($category->parent_id === null) {
            $categoryIds = $this->database->table('categories')->where('parent_id', $category->id)->fetchPairs(null, 'id');
        }

        return $this->withRating($this->database->table('books')->where('category_id', $categoryIds))->order('title');
    }

    public function getBookById(int $id)
    {
        return $this->database->table('books')->get($id);
    }

    public function deleteBook(ActiveRow $book): void
    {
        $book->delete();
    }

    public function getBookBySlug(string $slug)
    {
        return $this->withRating($this->database->table('books')->where('slug', $slug))->fetch();
    }

    private function withRating(Selection $books)
    {
        return $books->select('books.*, ROUND(AVG(:comments.rating), 1) AS rating, COUNT(:comments.id) AS ratings_count')->group('books.id');
    }

    public function getBookAuthors(ActiveRow $book)
    {
        return $book->related('authors')->order('sort_order, id');
    }

    public function getBookComments(ActiveRow $book)
    {
        return $book->related('comments')->order('created_at DESC');
    }

    public function createReservation(ActiveRow $book, string $firstName, string $lastName, string $email, ?string $note): ActiveRow {
        return $this->database->table('reservations')->insert([
            'book_id' => $book->id,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'email' => $email,
            'note' => $note,
        ]);
    }
}
