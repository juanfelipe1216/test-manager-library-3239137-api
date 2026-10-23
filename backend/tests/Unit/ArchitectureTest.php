<?php
namespace Tests\Unit;
use PHPUnit\Framework\TestCase;
final class ArchitectureTest extends TestCase {
    public function test_core_does_not_import_frameworks_or_adapters(): void {
        foreach(['Domain','Application'] as $layer) {
            $files=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__.'/../../app/'.$layer));
            foreach($files as $file) {
                if($file->getExtension()!=='php') continue;
                $code=file_get_contents($file->getPathname());
                foreach(['Illuminate\\','Firebase\\','App\\Adapters\\','App\\Http\\'] as $forbidden)
                    $this->assertStringNotContainsString($forbidden,$code,$file->getPathname());
            }
        }
    }
    public function test_domain_does_not_depend_on_application(): void {
        $files=new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(__DIR__.'/../../app/Domain'));
        foreach($files as $file) if($file->getExtension()==='php')
            $this->assertStringNotContainsString('App\\Application\\',file_get_contents($file->getPathname()));
    }
}
