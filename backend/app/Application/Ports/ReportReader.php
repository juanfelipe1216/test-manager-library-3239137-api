<?php
namespace App\Application\Ports;
interface ReportReader { public function mostBorrowed(string $from, string $to): array; }