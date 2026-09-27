<?php

declare(strict_types=1);

namespace App\Model;

use Tracy\Debugger;

/**
 * Imports books from a JSON file. Every book is validated and saved on its own,
 * so an invalid or duplicate book is skipped without stopping the rest of the import.
 */
final class BookImporter
{
    public function __construct(private readonly DatabaseManager $databaseManager) {}

    /**
     * @return array{total: int, imported: list<array{position: int, id: int, title: string}>, skipped: list<array{position: int, title: ?string, reasons: list<string>}>}
     * @throws BookImportException
     */
    public function import(string $json): array
    {
        try {
            $items = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new BookImportException('Soubor není platný JSON (' . $e->getMessage() . ').');
        }

        if (!is_array($items) || !array_is_list($items)) {
            throw new BookImportException('Soubor musí obsahovat pole knih, tedy začínat znakem [ a končit znakem ].');
        }

        if ($items === []) {
            throw new BookImportException('Soubor neobsahuje žádné knihy.');
        }

        $categoryIds = $this->databaseManager->getSubcategoryIdsBySlug();
        $result = ['total' => count($items), 'imported' => [], 'skipped' => []];

        foreach ($items as $index => $item) {
            $position = $index + 1;
            $title = is_array($item) && is_string($item['title'] ?? null) && trim($item['title']) !== '' ? trim($item['title']) : null;

            $reasons = is_array($item) && !array_is_list($item)
                ? $this->validate($item, $categoryIds)
                : ['Záznam není objekt knihy ({ … }).'];

            if ($reasons === []) {
                $duplicateReason = $this->findDuplicateReason($item);
                if ($duplicateReason !== null) {
                    $reasons[] = $duplicateReason;
                }
            }

            if ($reasons !== []) {
                $result['skipped'][] = ['position' => $position, 'title' => $title, 'reasons' => $reasons];
                continue;
            }

            try {
                $book = $this->databaseManager->saveBook(null, [
                    'title' => $title,
                    'short_name' => $this->optionalString($item, 'short_name'),
                    'category_id' => isset($item['category']) ? $categoryIds[$item['category']] : null,
                    'published_year' => $item['published_year'],
                    'isbn' => $this->optionalString($item, 'isbn'),
                    'annotation' => $this->optionalString($item, 'annotation'),
                ], array_map('trim', $item['authors']));
            } catch (\Throwable $e) {
                Debugger::log($e, Debugger::EXCEPTION);
                $result['skipped'][] = ['position' => $position, 'title' => $title, 'reasons' => ['Knihu se nepodařilo uložit do databáze.']];
                continue;
            }

            $result['imported'][] = ['position' => $position, 'id' => $book->id, 'title' => $book->title];
        }

        return $result;
    }

    /**
     * Same rules as the book form in the administration.
     *
     * @param array<int|string, int> $categoryIds
     * @return list<string>
     */
    private function validate(array $item, array $categoryIds): array
    {
        $errors = [];

        $title = $item['title'] ?? null;
        if (!is_string($title) || trim($title) === '') {
            $errors[] = 'Chybí název knihy (title).';
        } elseif (mb_strlen(trim($title)) > 255) {
            $errors[] = 'Název (title) může mít nejvýše 255 znaků.';
        }

        $shortName = $item['short_name'] ?? null;
        if ($shortName !== null && !is_string($shortName)) {
            $errors[] = 'Krátký název (short_name) musí být text nebo null.';
        } elseif (is_string($shortName) && mb_strlen(trim($shortName)) > 160) {
            $errors[] = 'Krátký název (short_name) může mít nejvýše 160 znaků.';
        }

        $authors = $item['authors'] ?? null;
        if (!is_array($authors) || !array_is_list($authors) || $authors === []) {
            $errors[] = 'Autoři (authors) musí být neprázdné pole jmen, např. ["Jo Nesbø"].';
        } else {
            foreach ($authors as $author) {
                if (!is_string($author) || trim($author) === '') {
                    $errors[] = 'Každý autor (authors) musí být neprázdný text.';
                    break;
                }
                if (mb_strlen(trim($author)) > 180) {
                    $errors[] = 'Jméno autora (authors) může mít nejvýše 180 znaků.';
                    break;
                }
            }
        }

        $maxYear = (int) date('Y') + 1;
        $year = $item['published_year'] ?? null;
        if (!is_int($year) || $year < 1 || $year > $maxYear) {
            $errors[] = "Rok vydání (published_year) musí být celé číslo mezi 1 a {$maxYear}.";
        }

        $category = $item['category'] ?? null;
        if ($category !== null && (!is_string($category) || !isset($categoryIds[$category]))) {
            $errors[] = 'Kategorie (category) „' . (is_scalar($category) ? $category : '?') . '“ neexistuje. Použijte slug podkategorie, např. „detektivky-krimi“.';
        }

        $isbn = $item['isbn'] ?? null;
        if ($isbn !== null && (!is_string($isbn) || ($isbn !== '' && !preg_match('~^[0-9Xx-]{10,17}$~', $isbn)))) {
            $errors[] = 'ISBN (isbn) může obsahovat jen číslice, pomlčky a písmeno X (10–17 znaků).';
        }

        $annotation = $item['annotation'] ?? null;
        if ($annotation !== null && !is_string($annotation)) {
            $errors[] = 'Anotace (annotation) musí být text nebo null.';
        }

        return $errors;
    }

    private function findDuplicateReason(array $item): ?string
    {
        $isbn = $this->optionalString($item, 'isbn');
        $existing = $this->databaseManager->findDuplicateBook(trim($item['title']), $item['published_year'], $isbn);

        if ($existing === null) {
            return null;
        }

        return $isbn !== null
            ? "Kniha se stejným ISBN už existuje („{$existing->title}“)."
            : "Kniha se stejným názvem a rokem vydání už existuje („{$existing->title}“, {$existing->published_year}).";
    }

    private function optionalString(array $item, string $key): ?string
    {
        $value = isset($item[$key]) ? trim($item[$key]) : '';

        return $value !== '' ? $value : null;
    }
}
