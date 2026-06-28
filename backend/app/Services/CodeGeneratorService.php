<?php

namespace App\Services;

class CodeGeneratorService
{
    public function generateLotCode(string $cooperativeCode): string
    {
        return strtoupper($cooperativeCode) . 'L' . now()->format('YmdHis');
    }

    public function generateProducteurCode(string $cooperativeCode): string
    {
        return strtoupper($cooperativeCode) . 'P' . now()->format('YmdHis');
    }
}
