<?php
namespace App\Application\Services;
use App\Application\Ports\BookCatalog;
use App\Domain\{Book,Isbn};
final class CreateBookService {
    public function __construct(private BookCatalog $books) {}
    public function execute(string $id,array $data): array {
        $book=new Book($id,$data['title'],new Isbn($data['isbn']),$data['total_copies'],$data['total_copies']);
        $this->books->create($book,$data);
        return $this->books->get($id)['response'];
    }
}