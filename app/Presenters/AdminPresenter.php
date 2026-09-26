<?php

declare(strict_types=1);

namespace App\Presentation\Admin;

use App\Model\DatabaseManager;
use Nette\Application\Attributes\Requires;
use Nette\Application\UI\Form;
use Nette\Application\UI\Presenter;
use Nette\Security\AuthenticationException;

final class AdminPresenter extends Presenter
{
    public function __construct(private readonly DatabaseManager $databaseManager) {
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
            $this->redirect('Admin:default');
        }
    }

    public function actionLogout(): void
    {
        $this->getUser()->logout(true);
        $this->flashMessage('Byli jste odhlášeni.', 'success');
        $this->redirect('Admin:login');
    }

    public function renderBooks(): void
    {
        $this->template->books = $this->databaseManager->getBooks();
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
        $this->databaseManager->deleteBook($book);

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

    private function loginFormSucceeded(Form $form, \stdClass $values): void
    {
        try {
            $this->getUser()->login($values->username, $values->password);
        } catch (AuthenticationException) {
            $form->addError('Nesprávné uživatelské jméno nebo heslo.');
            return;
        }

        $this->restoreRequest((string) $this->getParameter('backlink'));
        $this->redirect('Admin:default');
    }
}
