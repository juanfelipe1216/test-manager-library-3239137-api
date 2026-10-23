<?php
namespace App\Adapters;
use App\Application\Ports\LoanLedger;
use App\Application\BusinessError;
use App\Domain\Loan;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
final class PostgresLoanLedger implements LoanLedger {
    private function stamp($v): ?string { return $v===null ? null : (new DateTimeImmutable($v))->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'); }
    public function outstanding(string $memberId): array {
        return DB::table('library.loan_item as i')->join('library.loan as l','l.id','=','i.loan_id')
            ->where('l.member_id',$memberId)->whereNull('i.returned_at')->select('i.*')->get()->map(fn($r)=>(array)$r)->all();
    }
    public function append(Loan $loan): void {
        DB::table('library.loan')->insert(['id'=>$loan->id,'member_id'=>$loan->memberId,'loaned_by_user_id'=>$loan->loanedByUserId,'loaned_at'=>$loan->loanedAt->format(DATE_ATOM)]);
        foreach($loan->items() as $i) DB::table('library.loan_item')->insert([
            'id'=>$i->id,'loan_id'=>$loan->id,'book_id'=>$i->bookId,'book_title'=>$i->bookTitle,
            'isbn'=>$i->isbn,'category_name'=>$i->categoryName,'due_at'=>$i->dueAt->format(DATE_ATOM),'returned_at'=>null]);
    }
    public function get(string $id,DateTimeImmutable $now): array {
        $loan=DB::table('library.loan')->where('id',$id)->first();
        if (!$loan) throw new BusinessError('NOT_FOUND','Préstamo no encontrado.',404);
        $items=DB::table('library.loan_item')->where('loan_id',$id)->orderBy('id')->get();
        $state='closed';
        foreach($items as $i) if ($i->returned_at===null) {
            if (new DateTimeImmutable($i->due_at)<$now) { $state='overdue'; break; }
            $state='open';
        }
        return ['id'=>$id,'member_id'=>$loan->member_id,'loaned_at'=>$this->stamp($loan->loaned_at),'status'=>$state,
            'items'=>$items->map(function($i) { $r=(array)$i; unset($r['loan_id']); $r['due_at']=$this->stamp($i->due_at); $r['returned_at']=$this->stamp($i->returned_at); return $r; })->all()];
    }
    public function search(array $f,DateTimeImmutable $now): array {
        $q=DB::table('library.loan as l');
        if (!empty($f['member_id'])) $q->where('l.member_id',$f['member_id']);
        if (!empty($f['from'])) $q->where('l.loaned_at','>=',$f['from'].'T00:00:00Z');
        if (!empty($f['to'])) $q->where('l.loaned_at','<',(new DateTimeImmutable($f['to'].'T00:00:00Z'))->modify('+1 day')->format(DATE_ATOM));
        $nowText=$now->format(DATE_ATOM);
        if (!empty($f['status'])) {
            $open="EXISTS (SELECT 1 FROM library.loan_item i WHERE i.loan_id=l.id AND i.returned_at IS NULL)";
            $overdue="EXISTS (SELECT 1 FROM library.loan_item i WHERE i.loan_id=l.id AND i.returned_at IS NULL AND i.due_at < ?)";
            if($f['status']==='closed') $q->whereRaw("NOT $open");
            elseif($f['status']==='overdue') $q->whereRaw($overdue,[$nowText]);
            else $q->whereRaw($open)->whereRaw("NOT $overdue",[$nowText]);
        }
        $page=(int)($f['page']??1); $size=(int)($f['size']??20); $total=(clone $q)->count();
        return ['items'=>$q->orderByDesc('l.loaned_at')->orderBy('l.id')->offset(($page-1)*$size)->limit($size)->pluck('l.id')->map(fn($id)=>$this->get($id,$now))->all(),'page'=>$page,'size'=>$size,'total'=>$total];
    }
    public function overdue(DateTimeImmutable $now): array {
        $rows=DB::table('library.loan_item as i')->join('library.loan as l','l.id','=','i.loan_id')->join('library.member as m','m.id','=','l.member_id')
            ->whereNull('i.returned_at')->where('i.due_at','<',$now->format(DATE_ATOM))->orderBy('i.due_at')
            ->select('i.loan_id','i.id as item_id','i.book_title','m.full_name as member_name','i.due_at')->get();
        return ['items'=>$rows->map(function($r) use($now) {
            $v=(array)$r; $v['due_at']=$this->stamp($r->due_at); $v['days_late']=(int)floor(($now->getTimestamp()-(new DateTimeImmutable($r->due_at))->getTimestamp())/86400); return $v;
        })->all()];
    }
    public function returnLine(string $loanId,string $itemId,DateTimeImmutable $now): array {
        $item=DB::table('library.loan_item')->where('loan_id',$loanId)->where('id',$itemId)->first();
        if(!$item) throw new BusinessError('NOT_FOUND','Línea no encontrada.',404);
        if($item->returned_at!==null) throw new BusinessError('ITEM_ALREADY_RETURNED','La línea ya fue devuelta.',409);
        $n=DB::table('library.loan_item')->where('id',$itemId)->whereNull('returned_at')->update(['returned_at'=>$now->format(DATE_ATOM)]);
        if($n!==1) throw new BusinessError('ITEM_ALREADY_RETURNED','La línea ya fue devuelta.',409);
        $n=DB::table('library.book')->where('id',$item->book_id)->whereColumn('available_copies','<','total_copies')->increment('available_copies');
        if($n!==1) throw new BusinessError('BOOK_NOT_AVAILABLE','Las existencias no permiten registrar la devolución.',409);
        foreach($this->get($loanId,$now)['items'] as $line) if($line['id']===$itemId) return $line;
        throw new BusinessError('NOT_FOUND','Línea no encontrada.',404);
    }
}