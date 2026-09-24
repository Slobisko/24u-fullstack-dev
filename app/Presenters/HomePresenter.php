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

    public function renderDefault(): void
    {
        $this->template->phpVersion = PHP_VERSION;
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
                    'children' => $childrenByParent[$category->id] ?? [],
                ];
            }
        }

        return $tree;
    }
}
