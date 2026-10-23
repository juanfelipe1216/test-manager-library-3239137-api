<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
use App\Application\Services\LendBooksService;
use App\Application\Ports\{BookCatalog,MemberRegistry,LoanLedger,UnitOfWork,Clock};
use App\Application\BusinessError;
final class LendBooksServiceTest extends TestCase {
    private function service(array $open): LendBooksService {
        $books=$this->createMock(BookCatalog::class);
        $books->expects($this->never())->method('save');
        $members=$this->createMock(MemberRegistry::class);
        $loans=$this->createMock(LoanLedger::class);
        $loans->method('outstanding')->willReturn($open);
        $loans->expects($this->never())->method('append');
        $work=$this->createStub(UnitOfWork::class);
        $work->method('run')->willReturnCallback(fn($operation)=>$operation());
        $clock=$this->createStub(Clock::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-10-22T12:00:00Z'));
        return new LendBooksService($books,$members,$loans,$work,$clock);
    }
    public function test_overdue_member_cannot_borrow(): void {
        $service=$this->service([['due_at'=>'2026-10-01T12:00:00Z']]);
        try { $service->execute('l','m','u',[['id'=>'i','book_id'=>'b']]); $this->fail(); }
        catch(BusinessError $e) { $this->assertSame('MEMBER_HAS_OVERDUE',$e->errorCode); }
    }
    public function test_total_pending_limit_is_checked_across_loans(): void {
        $open=array_fill(0,3,['due_at'=>'2026-11-01T12:00:00Z']);
        $service=$this->service($open);
        try { $service->execute('l','m','u',[['id'=>'i','book_id'=>'b']]); $this->fail(); }
        catch(BusinessError $e) { $this->assertSame('MEMBER_LIMIT_EXCEEDED',$e->errorCode); }
    }
    public function test_duplicate_books_are_rejected_before_persistence(): void {
        $service=$this->service([]);
        try { $service->execute('l','m','u',[['id'=>'i1','book_id'=>'b'],['id'=>'i2','book_id'=>'b']]); $this->fail(); }
        catch(BusinessError $e) { $this->assertSame('DUPLICATE_BOOK_IN_LOAN',$e->errorCode); }
    }
    public function test_empty_loan_is_rejected(): void {
        $this->expectException(BusinessError::class);
        $this->service([])->execute('l','m','u',[]);
    }
}
