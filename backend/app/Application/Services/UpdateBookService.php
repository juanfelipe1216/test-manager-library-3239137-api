<?php
namespace App\Application\Services;
use App\Application\Ports\BookCatalog;
final class UpdateBookService {
    public function __construct(private BookCatalog $books) {}
    public function execute(string $id,array $data): array {
        $original=$this->books->get($id); $book=$original['entity'];
        $book->rename($data['title']); $book->changeTotal($data['total_copies']);
        $this->books->save($book,$original,['author'=>trim($data['author']),'category_id'=>$data['category_id']]);
        return $this->books->get($id)['response'];
    }
}