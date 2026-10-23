<?php
namespace App\Application\Ports;
interface UnitOfWork { public function run(callable $operation): mixed; }