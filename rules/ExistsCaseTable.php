<?php

namespace Marketplace\Tokens\Rules;

use Db;
use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Lang;

class ExistsCaseTable implements Rule
{
    protected $table;
    protected $column;
    protected $value;

    public function __construct($table, $column, $value)
    {
        $this->table = $table;
        $this->column = $column;
        $this->value = $value;
    }

    public function passes($attribute, $value)
    {
        return DB::table($this->table)->whereRaw('LOWER(' . $this->column . ') = LOWER(?)', [$this->value])->exists();
    }

    public function message()
    {
        return 'Записей с выбранным полем ' . $this->column . ' не обнаружено.';
    }
}
