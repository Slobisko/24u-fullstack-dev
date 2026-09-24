<?php

declare(strict_types=1);

namespace App\Presentation\Home;

use App\Model\DatabaseManager;
use Nette\Application\UI\Presenter;
use Nette\Utils\Validators;

final class HomePresenter extends Presenter
{
    public function __construct(private readonly DatabaseManager $databaseManager) {
        parent::__construct();
    }

    public function startup(): void
    {
        parent::startup();

        if ($this->isAjax()) {
            $this->setLayout(false);
        }
    }

    public function renderDefault(?string $slug = null): void
    {
        $category = $slug !== null ? $this->databaseManager->getCategoryBySlug($slug) : null;

        $this->template->activeCategory = $category;
        $this->template->categoryNotFound = $slug !== null && $category === null;
        $this->template->books = match (true) {
            $category !== null => $this->databaseManager->getBooksByCategory($category),
            $slug !== null => [],
            default => $this->databaseManager->getBooks(),
        };
    }

    public function renderDetail(string $slug): void
    {
        $book = $this->databaseManager->getBookBySlug($slug);

        if ($book === null) {
            $this->redirect('Home:default');
        }

        $comments = $this->databaseManager->getBookComments($book)->fetchAll();

        $ratingBreakdown = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
        foreach ($comments as $comment) {
            if ($comment->rating !== null) {
                $ratingBreakdown[(int) $comment->rating]++;
            }
        }

        $this->template->book = $book;
        $this->template->authors = $this->databaseManager->getBookAuthors($book);
        $this->template->comments = $comments;
        $this->template->commentsCount = count($comments);
        $this->template->ratingBreakdown = $ratingBreakdown;
    }

    public function actionReserve(): void
    {
        $request = $this->getHttpRequest();

        if (!$request->isMethod('POST')) {
            $this->getHttpResponse()->setCode(405);
            $this->sendJson(['ok' => false, 'errors' => ['form' => 'Nepovolená metoda.']]);
        }

        $bookSlug = trim((string) $request->getPost('book_slug'));
        $firstName = trim((string) $request->getPost('first_name'));
        $lastName = trim((string) $request->getPost('last_name'));
        $email = trim((string) $request->getPost('email'));
        $note = trim((string) $request->getPost('note'));

        $book = $bookSlug !== '' ? $this->databaseManager->getBookBySlug($bookSlug) : null;

        $errors = [];
        if ($book === null) {
            $errors['book'] = 'Kniha nebyla nalezena.';
        }
        if ($firstName === '') {
            $errors['first_name'] = 'Vyplňte jméno.';
        }
        if ($lastName === '') {
            $errors['last_name'] = 'Vyplňte příjmení.';
        }
        if ($email === '' || !Validators::isEmail($email)) {
            $errors['email'] = 'Vyplňte platný e-mail.';
        }

        if ($errors) {
            $this->getHttpResponse()->setCode(422);
            $this->sendJson(['ok' => false, 'errors' => $errors]);
        }

        $this->databaseManager->createReservation($book, $firstName, $lastName, $email, $note !== '' ? $note : null);

        $this->sendJson(['ok' => true]);
    }

    protected function beforeRender(): void
    {
        parent::beforeRender();

        $this->template->categories = $this->getCategoryTree();
        $this->template->activeCategory = null;
        $this->template->categoryNotFound = false;
    }

    private function getCategoryTree(): array
    {
        $categories = $this->databaseManager->getCategories()->fetchAll();

        $childrenByParent = [];
        foreach ($categories as $category) {
            if ($category->parent_id !== null) {
                $childrenByParent[$category->parent_id][] = $category;
            }
        }

        $tree = [];
        foreach ($categories as $category) {
            if ($category->parent_id === null) {
                $tree[] = [
                    'name' => $category->name,
                    'slug' => $category->slug,
                    'children' => $childrenByParent[$category->id] ?? [],
                ];
            }
        }

        return $tree;
    }
}
