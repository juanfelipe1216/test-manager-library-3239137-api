<?php
namespace App\Adapters;
use App\Application\Ports\CoverStorage;
final class DiskCoverStorage implements CoverStorage {
    public function __construct(private string $directory) {}
    private function path(string $key): string {
        if(!preg_match('/^[a-f0-9-]{36}\.(jpg|png)$/D',$key)) throw new \InvalidArgumentException('Clave de portada inválida.');
        return $this->directory.'/'.$key;
    }
    public function put(string $bytes,string $mime): string {
        if(!is_dir($this->directory)) mkdir($this->directory,0775,true);
        $key=(string)\Illuminate\Support\Str::uuid().($mime==='image/png'?'.png':'.jpg');
        if(file_put_contents($this->path($key),$bytes)===false) throw new \RuntimeException('No se pudo guardar la portada.');
        return $key;
    }
    public function read(string $key): ?array {
        $path=$this->path($key);
        return is_file($path)?['bytes'=>file_get_contents($path),'mime'=>str_ends_with($key,'.png')?'image/png':'image/jpeg']:null;
    }
    public function delete(string $key): void { $path=$this->path($key); if(is_file($path)) unlink($path); }
}