<?php
namespace App\Application\Services;
use App\Application\Ports\{BookCatalog,MemberRegistry,LoanLedger,UnitOfWork,Clock};
use App\Application\BusinessError;
use App\Domain\Loan;
final class LendBooksService {
    public function __construct(private BookCatalog $books,private MemberRegistry $members,private LoanLedger $loans,private UnitOfWork $work,private Clock $clock) {}
    public function execute(string $id,string $memberId,string $userId,array $items): array {
        $ids=array_column($items,'book_id');
        if(count($items)<1 || count($items)>3) throw new BusinessError('VALIDATION_ERROR','El préstamo debe tener entre uno y tres libros.');
        if(count(array_unique($ids))!==count($ids)) throw new BusinessError('DUPLICATE_BOOK_IN_LOAN','No se puede repetir un libro.');
        return $this->work->run(function() use($id,$memberId,$userId,$items) {
            $this->members->lock($memberId);
            $now=$this->clock->now(); $open=$this->loans->outstanding($memberId);
            foreach($open as $line) if(new \DateTimeImmutable($line['due_at'])<$now) throw new BusinessError('MEMBER_HAS_OVERDUE','El socio tiene libros vencidos.',409);
            if(count($open)+count($items)>3) throw new BusinessError('MEMBER_LIMIT_EXCEEDED','El socio ya tiene '.count($open).' libros pendientes.',422,[['field'=>'member_id','issue'=>'Pendientes actuales: '.count($open)]]);
            $loan=new Loan($id,$memberId,$userId,$now); $changes=[];
            foreach($items as $item) {
                $record=$this->books->get($item['book_id']); $book=$record['entity'];
                if($book->availableCopies()<1) throw new BusinessError('BOOK_NOT_AVAILABLE','No quedan ejemplares disponibles.',409);
                $loan->addItem($item['id'],$book,$record['category_name']); $changes[]=[$book,$record];
            }
            foreach($changes as [$book,$record]) $this->books->save($book,$record);
            $this->loans->append($loan);
            return $this->loans->get($id,$now);
        });
    }
}