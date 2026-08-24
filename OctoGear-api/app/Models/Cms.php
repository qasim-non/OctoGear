<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cms extends Model
{
    use SoftDeletes;

    /**
     * CMS uses the default 'id' but the table is called 'cms'.
     * Laravel guesses 'cuses' from 'Cms' — wrong!
     * We must override getTable().
     */
    public function getTable(): string
    {
        return 'cms';
    }

    protected $fillable = [
        'type',
        'arabic_text',
        'english_text',
    ];

    protected $hidden = [
        'deleted_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
            'deleted_at' => 'datetime',
        ];
    }
}
