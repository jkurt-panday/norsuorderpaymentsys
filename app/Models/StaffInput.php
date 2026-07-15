<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StaffInput extends Model
{
    protected $table = 'staff_input';
    
    protected $fillable = [
        'fundcluster_id',
        'ref_doc_1',
        'ref_date_1',
        'ref_doc_2',
        'ref_date_2',
        'uacs_id',
        'form_input_id'
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