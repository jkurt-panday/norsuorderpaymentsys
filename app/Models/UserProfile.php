<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserProfile extends Model
{
    /** @use HasFactory<Factory<UserProfile>> */
    use HasFactory;

    protected $table = 'user_profiles';

    protected $fillable = [
        'user_id',
        'firstname_or_office',
        'middlename_or_project',
        'lastname_or_agency',
        'contact_num',
        'office_or_college',
        'position_or_designation',
        'address',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
