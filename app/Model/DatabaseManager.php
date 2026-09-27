<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Database\Explorer;
use Nette\Database\Table\ActiveRow;
use Nette\Database\Table\Selection;
use Nette\Utils\Strings;

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

    public function getBooks(string $order = 'books.title')
    {
        return $this->withRating($this->database->table('books'))->order($order);
    }

    /**
     * Books whose title, short name, ISBN (ignoring hyphens) or any author name contains the query.
     */
    public function searchBooks(string $query, string $order = 'books.title')
    {
        $like = '%' . addcslashes($query, '%_\\') . '%';
        $isbn = str_replace(['-', ' '], '', $query);
        $isbnLike = $isbn !== '' ? '%' . addcslashes($isbn, '%_\\') . '%' : $like;

        return $this->getBooks($order)->where(
            "books.title LIKE ? OR books.short_name LIKE ? OR REPLACE(books.isbn, '-', '') LIKE ?"
            . ' OR books.id IN (SELECT book_id FROM authors WHERE name LIKE ?)',
            $like,
            $like,
            $isbnLike,
            $like,
        );
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

    /**
     * Subcategories grouped by their parent category name, ready for a select box with optgroups.
     */
    public function getCategoryOptions(): array
    {
        $categories = $this->getCategories()->fetchAll();

        $parentNames = [];
        foreach ($categories as $category) {
            if ($category->parent_id === null) {
                $parentNames[$category->id] = $category->name;
            }
        }

        $options = [];
        foreach ($categories as $category) {
            if ($category->parent_id !== null && isset($parentNames[$category->parent_id])) {
                $options[$parentNames[$category->parent_id]][$category->id] = $category->name;
            }
        }

        return $options;
    }

    /**
     * Inserts a new book (when $book is null) or updates an existing one, and replaces its authors.
     *
     * @param string[] $authors
     */
    public function saveBook(?ActiveRow $book, array $data, array $authors): ActiveRow
    {
        return $this->database->transaction(function () use ($book, $data, $authors): ActiveRow {
            if ($book === null) {
                $data['slug'] = $this->createUniqueSlug($data['title']);
                $book = $this->database->table('books')->insert($data);
            } else {
                $book->update($data);
                $book->related('authors')->delete();
            }

            foreach ($authors as $sortOrder => $name) {
                $this->database->table('authors')->insert([
                    'book_id' => $book->id,
                    'name' => $name,
                    'sort_order' => $sortOrder,
                ]);
            }

            return $book;
        });
    }

    /**
     * @return array<string, int> subcategory slug => id
     */
    public function getSubcategoryIdsBySlug(): array
    {
        return $this->getCategories()->where('parent_id IS NOT NULL')->fetchPairs('slug', 'id');
    }

    /**
     * A book with the same ISBN (ignoring hyphens), or with the same title and year when no ISBN is given.
     */
    public function findDuplicateBook(string $title, int $publishedYear, ?string $isbn): ?ActiveRow
    {
        $books = $this->database->table('books');

        return $isbn !== null
            ? $books->where("REPLACE(isbn, '-', '') = ?", str_replace('-', '', $isbn))->fetch()
            : $books->where('title = ? AND published_year = ?', $title, $publishedYear)->fetch();
    }

    public function updateBookImage(ActiveRow $book, ?string $imageUrl): void
    {
        $book->update(['image_url' => $imageUrl]);
    }

    private function createUniqueSlug(string $title): string
    {
        $base = rtrim(substr(Strings::webalize($title), 0, 180), '-');
        $slug = $base;

        for ($i = 2; $this->database->table('books')->where('slug', $slug)->count('*') > 0; $i++) {
            $slug = $base . '-' . $i;
        }

        return $slug;
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
