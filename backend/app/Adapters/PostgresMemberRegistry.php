<?php
namespace App\Adapters;
use App\Application\Ports\MemberRegistry;
use App\Application\BusinessError;
use App\Domain\Member;
use Illuminate\Support\Facades\DB;
final class PostgresMemberRegistry implements MemberRegistry {
    public function get(string $id): array {
        $r=DB::table('library.member')->where('id',$id)->first();
        if (!$r) throw new BusinessError('NOT_FOUND','Socio no encontrado.',404);
        return ['id'=>$r->id,'full_name'=>$r->full_name,'document_number'=>$r->document_number,
            'active_loans'=>DB::table('library.loan_item as i')->join('library.loan as l','l.id','=','i.loan_id')->where('l.member_id',$id)->whereNull('i.returned_at')->count()];
    }
    public function create(Member $member): void {
        DB::table('library.member')->insert(['id'=>$member->id,'full_name'=>$member->fullName,'document_number'=>$member->documentNumber]);
    }
    public function lock(string $id): void {
        DB::select('SELECT pg_advisory_xact_lock(hashtextextended(?, 0))',[$id]);
        $this->get($id);
    }
    public function search(array $f): array {
        $q=DB::table('library.member');
        if (!empty($f['q'])) $q->where(function($q) use($f) {
            $q->where('full_name','ilike','%'.$f['q'].'%')->orWhere('document_number','ilike','%'.$f['q'].'%');
        });
        $page=(int)($f['page']??1); $size=(int)($f['size']??20); $total=(clone $q)->count();
        return ['items'=>$q->orderBy('full_name')->offset(($page-1)*$size)->limit($size)->get()->map(fn($r)=>(array)$r)->all(),'total'=>$total,'page'=>$page,'size'=>$size];
    }
}