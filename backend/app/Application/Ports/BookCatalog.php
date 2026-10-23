<?php
namespace App\Application\Ports;
use App\Domain\Book;
interface BookCatalog {
    public function get(string $id): array;
    public function search(array $filters): array;
    public function categories(): array;
    public function create(Book $book, array $data): void;
    public function save(Book $book, array $original, array $changes = []): void;
}