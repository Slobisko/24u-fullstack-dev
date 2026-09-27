<?php

declare(strict_types=1);

namespace App\Model;

use Nette\Http\FileUpload;
use Nette\Utils\FileSystem;
use Nette\Utils\Random;

final class BookImageStorage
{
    public const Extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    public function __construct(private readonly string $directory) {}

    public function save(FileUpload $upload, string $slug): string
    {
        $fileName = $slug . '-' . Random::generate(6) . '.' . self::Extensions[$upload->getContentType()];
        $upload->move($this->directory . '/' . $fileName);

        return $fileName;
    }

    public function delete(?string $fileName): void
    {
        if ($fileName !== null && $fileName !== '') {
            FileSystem::delete($this->directory . '/' . basename($fileName));
        }
    }
}
