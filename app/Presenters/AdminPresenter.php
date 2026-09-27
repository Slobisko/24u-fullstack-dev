<?php

declare(strict_types=1);

namespace App\Presentation\Admin;

use App\Model\BookImageStorage;
use App\Model\BookImporter;
use App\Model\BookImportException;
use App\Model\DatabaseManager;
use Nette\Application\Attributes\Requires;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Database\Table\ActiveRow;
use Nette\Security\AuthenticationException;

final class AdminPresenter extends Presenter
{
    private const MaxImageSize = 2 * 1024 * 1024;

    private const MaxImportFileSize = 2 * 1024 * 1024;

    /**
     * Sortable columns of the books table: column key => SQL expression to order by
     */
    private const BookSortColumns = [
        'title' => 'books.title',
        'authors' => '(SELECT name FROM authors WHERE book_id = books.id ORDER BY sort_order, id LIMIT 1)',
        'category' => 'category.name',
        'year' => 'books.published_year',
        'rating' => 'rating',
    ];

    private const DefaultBookSort = 'title';

    private ?ActiveRow $book = null;

    public function __construct(private readonly DatabaseManager $databaseManager, private readonly BookImageStorage $bookImageStorage, private readonly BookImporter $bookImporter) {
        parent::__construct();
    }

    public function startup(): void
    {
        parent::startup();

        $isLoginPage = $this->getAction() === 'login';

        if (!$this->getUser()->isLoggedIn() && !$isLoginPage) {
            $this->redirect('Admin:login', ['backlink' => $this->storeRequest()]);
        }

        if ($this->getUser()->isLoggedIn() && $isLoginPage) {
            $this->redirect('Admin:books');
        }
    }

    public function actionLogout(): void
    {
        $this->getUser()->logout(true);
        $this->flashMessage('Byli jste odhlášeni.', 'success');
        $this->redirect('Admin:login');
    }

    public function renderBooks(string $q = '', string $sort = self::DefaultBookSort, string $dir = 'asc'): void
    {
        $query = trim($q);
        $sort = isset(self::BookSortColumns[$sort]) ? $sort : self::DefaultBookSort;
        $dir = $dir === 'desc' ? 'desc' : 'asc';
        $order = self::BookSortColumns[$sort] . ' ' . strtoupper($dir) . ', books.title ASC';

        $this->template->query = $query;
        $this->template->sort = $sort;
        $this->template->dir = $dir;
        $this->template->isDefaultOrder = $sort === self::DefaultBookSort && $dir === 'asc';
        $this->template->sortableColumns = array_keys(self::BookSortColumns);
        $this->template->books = $query !== ''
            ? $this->databaseManager->searchBooks($query, $order)
            : $this->databaseManager->getBooks($order);

        if ($this->isAjax()) {
            $this->redrawControl('count');
            $this->redrawControl('books');
        }
    }

    public function actionImportResult(): void
    {
        $result = $this->getSession('bookImport')->get('result');

        if ($result === null) {
            $this->redirect('Admin:import');
        }

        $this->template->result = $result;
    }

    public function actionAdd(): void
    {
        $this->setView('bookForm');
    }

    public function actionEdit(int $id): void
    {
        $this->book = $this->databaseManager->getBookById($id) ?? $this->error('Kniha nebyla nalezena.');

        $this['bookForm']->setDefaults([
            'title' => $this->book->title,
            'short_name' => $this->book->short_name,
            'authors' => implode(', ', $this->databaseManager->getBookAuthors($this->book)->fetchPairs(null, 'name')),
            'category_id' => $this->book->category_id,
            'published_year' => $this->book->published_year,
            'isbn' => $this->book->isbn,
            'annotation' => $this->book->annotation,
        ]);

        $this->setView('bookForm');
    }

    public function renderBookForm(): void
    {
        $this->template->book = $this->book;
    }

    #[Requires(methods: 'POST')]
    public function handleDeleteBook(int $id): void
    {
        $book = $this->databaseManager->getBookById($id);

        if ($book === null) {
            $this->flashMessage('Kniha nebyla nalezena.', 'error');
            $this->redirect('this');
        }

        $title = $book->title;
        $imageUrl = $book->image_url;
        $this->databaseManager->deleteBook($book);
        $this->bookImageStorage->delete($imageUrl);

        $this->flashMessage("Kniha „{$title}“ byla odstraněna.", 'success');
        $this->redirect('this');
    }

    protected function createComponentLoginForm(): Form
    {
        $form = new Form;
        $form->addText('username', 'Uživatelské jméno')
            ->setRequired('Vyplňte uživatelské jméno.')
            ->setHtmlAttribute('autocomplete', 'username')
            ->setHtmlAttribute('autofocus');
        $form->addPassword('password', 'Heslo')
            ->setRequired('Vyplňte heslo.')
            ->setHtmlAttribute('autocomplete', 'current-password');
        $form->addSubmit('send', 'Přihlásit se');

        $form->onSuccess[] = $this->loginFormSucceeded(...);

        return $form;
    }

    protected function createComponentBookForm(): Form
    {
        $form = new Form;
        $form->addText('title', 'Název')
            ->setRequired('Vyplňte název knihy.')
            ->addRule($form::MaxLength, 'Název může mít nejvýše %d znaků.', 255);
        $form->addText('short_name', 'Krátký název')
            ->setNullable()
            ->addRule($form::MaxLength, 'Krátký název může mít nejvýše %d znaků.', 160);
        $form->addText('authors', 'Autoři')
            ->setRequired('Vyplňte alespoň jednoho autora.')
            ->setHtmlAttribute('placeholder', 'např. Jo Nesbø, Karel Čapek');
        $form->addSelect('category_id', 'Kategorie', $this->databaseManager->getCategoryOptions())
            ->setPrompt('— bez kategorie —');
        $form->addInteger('published_year', 'Rok vydání')
            ->setRequired('Vyplňte rok vydání.')
            ->addRule($form::Range, 'Rok vydání musí být mezi %d a %d.', [1, (int) date('Y') + 1]);
        $form->addText('isbn', 'ISBN')
            ->setNullable()
            ->setHtmlAttribute('placeholder', 'např. 978-80-7662-333-0')
            ->addRule($form::Pattern, 'ISBN může obsahovat jen číslice, pomlčky a písmeno X (10–17 znaků).', '[0-9Xx-]{10,17}');
        $form->addTextArea('annotation', 'Anotace')
            ->setNullable()
            ->setHtmlAttribute('rows', 8);
        $form->addUpload('image', 'Obálka')
            ->addRule($form::MimeType, 'Obálka musí být obrázek ve formátu JPG nebo PNG.', array_keys(BookImageStorage::Extensions))
            ->addRule($form::MaxFileSize, 'Obrázek může mít nejvýše 2 MB.', self::MaxImageSize);
        $form->addSubmit('send', $this->book ? 'Uložit změny' : 'Přidat knihu');

        $form->onSuccess[] = $this->bookFormSucceeded(...);

        return $form;
    }

    protected function createComponentImportForm(): Form
    {
        $form = new Form;
        $form->addUpload('file', 'Soubor JSON')
            ->setRequired('Vyberte soubor JSON s knihami.')
            ->setHtmlAttribute('accept', '.json,application/json')
            ->addRule($form::MaxFileSize, 'Soubor může mít nejvýše 2 MB.', self::MaxImportFileSize);
        $form->addSubmit('send', 'Importovat knihy');

        $form->onSuccess[] = $this->importFormSucceeded(...);

        return $form;
    }

    private function importFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            $result = $this->bookImporter->import($values->file->getContents());
        } catch (BookImportException $e) {
            $form['file']->addError($e->getMessage());
            return;
        }

        $result['fileName'] = $values->file->getUntrustedName();
        $this->getSession('bookImport')->set('result', $result);

        // Redirect so that refreshing the result page does not import the file again.
        $this->redirect('Admin:importResult');
    }

    private function bookFormSucceeded(Form $form, \stdClass $values): void
    {
        $authors = array_values(array_filter(array_map('trim', explode(',', $values->authors))));

        if (!$authors) {
            $form['authors']->addError('Vyplňte alespoň jednoho autora.');
            return;
        }

        $isNew = $this->book === null;
        $book = $this->databaseManager->saveBook($this->book, [
            'title' => $values->title,
            'short_name' => $values->short_name,
            'category_id' => $values->category_id,
            'published_year' => $values->published_year,
            'isbn' => $values->isbn,
            'annotation' => $values->annotation,
        ], $authors);

        if ($values->image->hasFile()) {
            $oldImageUrl = $book->image_url;
            $this->databaseManager->updateBookImage($book, $this->bookImageStorage->save($values->image, $book->slug));
            $this->bookImageStorage->delete($oldImageUrl);
        }

        $this->flashMessage($isNew ? "Kniha „{$book->title}“ byla přidána." : "Změny knihy „{$book->title}“ byly uloženy.", 'success');
        $this->redirect('Admin:books');
    }

    private function loginFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            $this->getUser()->login($values->username, $values->password);
        } catch (AuthenticationException) {
            $form->addError('Nesprávné uživatelské jméno nebo heslo.');
            return;
        }

        $this->restoreRequest((string) $this->getParameter('backlink'));
        $this->redirect('Admin:books');
    }
}
