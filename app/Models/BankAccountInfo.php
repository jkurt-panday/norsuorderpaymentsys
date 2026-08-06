<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BankAccountInfo extends Model
{
    protected $table = 'bankaccount_info';

    protected $fillable = [
        'account_name',
        'bank_name',
        'account_num',
    ];

    public function staffInput()
    {
        return $this->hasOne(StaffInput::class, 'fundcluster_id');
    }
}
