<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffInput extends Model
{
    protected $table = 'staff_inputs';

    protected $fillable = [
        'form_input_id',
        'fundcluster_id',
        'ref_document_id',
        'ref_date',
        'uacs_id',
        'status',
    ];

    public function fundCluster()
    {
        return $this->belongsTo(BankAccountInfo::class, 'fundcluster_id');
    }

    public function uacs()
    {
        return $this->belongsTo(UACS::class);
    }

    public function formInput()
    {
        return $this->belongsTo(FormInput::class);
    }
}
