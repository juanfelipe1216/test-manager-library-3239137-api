<?php
namespace App\Adapters;
use App\Application\Ports\Clock;
use DateTimeImmutable;
use DateTimeZone;
final class SystemClock implements Clock {
    public function __construct(private ?string $demoFile=null) {}
    public function now(): DateTimeImmutable {
        if($this->demoFile!==null && is_file($this->demoFile)) {
            $text=trim(file_get_contents($this->demoFile));
            if($text!=='') return new DateTimeImmutable($text,new DateTimeZone('UTC'));
        }
        return new DateTimeImmutable('now',new DateTimeZone('UTC'));
    }
}
