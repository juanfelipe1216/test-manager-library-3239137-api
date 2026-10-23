<?php
namespace App\Application\Services;
use App\Application\Ports\{BookCatalog,Clock};
use App\Application\BusinessError;
final class DeleteBookService {
    public function __construct(private BookCatalog $books,private Clock $clock) {}
    public function execute(string $id): void {
        $original=$this->books->get($id); $book=$original['entity'];
        if($book->availableCopies()!==$book->totalCopies()) throw new BusinessError('BOOK_HAS_OPEN_LOANS','El libro tiene préstamos abiertos.',409);
        $book->softDelete($this->clock->now()); $this->books->save($book,$original);
    }
}