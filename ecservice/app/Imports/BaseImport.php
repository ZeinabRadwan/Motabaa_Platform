<?php

namespace App\Imports;

use App\User;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class BaseImport implements WithHeadingRow
{
    public function headingRow(): int
    {
        return 1;
    }
}