<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UACS extends Model
{
    protected $table = 'uacs';
    
    protected $fillable = [
        'account_title',
        'object_code'
    ];
    
    public function staffInput()
    {
        return $this->hasOne(StaffInput::class);
    }
}