<?php
namespace App\Application\Services;
use App\Application\Ports\{BookCatalog,CoverStorage};
final class ChangeCoverService {
    public function __construct(private BookCatalog $books,private CoverStorage $storage) {}
    public function execute(string $id,?string $bytes=null,string $mime='image/jpeg'): void {
        $record=$this->books->get($id); $old=$record['response']['cover_key'];
        $new=$bytes===null?null:$this->storage->put($bytes,$mime);
        try { $this->books->save($record['entity'],$record,['cover_key'=>$new]); }
        catch(\Throwable $e) { if($new!==null) $this->storage->delete($new); throw $e; }
        if($old!==null) $this->storage->delete($old);
    }
}