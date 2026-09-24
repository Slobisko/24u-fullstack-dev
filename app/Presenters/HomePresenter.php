<?php

declare(strict_types=1);

namespace App\Presentation\Home;

use App\Model\DatabaseManager;
use Nette\Application\UI\Presenter;

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

    protected function beforeRender(): void
    {
        parent::beforeRender();

        $this->template->categories = $this->getCategoryTree();
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
