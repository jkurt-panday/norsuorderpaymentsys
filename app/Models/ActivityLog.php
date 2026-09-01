<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class ActivityLog extends Model
{
    use HasFactory;

    protected $table = 'activity_log';

    protected $fillable = [
        'actor_id',
        'actor_name',
        'actor_role',
        'action',
        'target_id',
        'subject_type',
        'subject_id',
        'description',
        'meta',
    ];

    protected function casts(): array
    {
        return ['meta' => 'array'];
    }

    /**
     * Map a stored action token to the UI event category used for badge
     * coloring on the dashboard (created / updated / deleted / processed).
     */
    public function eventCategory(): string
    {
        return match ($this->action) {
            'processed', 'user.created' => $this->action === 'processed' ? 'processed' : 'created',
            'updated' => 'updated',
            'deleted', 'user.deactivated' => 'deactivated',
            'user.reactivated' => 'reactivated',
            default => 'created',
        };
    }

    /**
     * Friendly label for the model class that was changed.
     */
    public function subjectTypeLabel(): string
    {
        return match (class_basename($this->subject_type ?? '')) {
            'StaffInput' => 'Order of Payment',
            'FormInput' => 'Payment Request',
            'UACS' => 'UACS',
            'Membership' => 'Membership',
            'BankAccountInfo' => 'Bank Account',
            'PaymentDetailOption' => 'Payment Option',
            'Courses' => 'Course',
            'Course' => 'Graduate Course',
            'GraduateLedger' => 'Graduate Ledger',
            'LawSchoolLedger' => 'Law Ledger',
            'Student' => 'Student',
            'AcademicTerm' => 'Academic Term',
            'AssessmentForm' => 'Assessment',
            'SupportingDocument' => 'Document',
            'User' => 'System Account',
            default => class_basename($this->subject_type ?? 'Record'),
        };
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'target_id');
    }

    /**
     * Log a bulk import as a single summary entry. Bulk inserts use
     * `Model::insert()` which bypasses Eloquent events, so the observer
     * never fires for them — call this explicitly after a successful import.
     */
    public static function recordImport(string $subjectType, int $count, string $label): void
    {
        if ($count <= 0) {
            return;
        }

        $actor = Auth::guard('web')->user();

        self::create([
            'actor_id' => $actor?->getAuthIdentifier(),
            'actor_name' => $actor?->name,
            'actor_role' => $actor?->role,
            'action' => 'created',
            'target_id' => null,
            'subject_type' => $subjectType,
            'subject_id' => null,
            'description' => "{$label}: {$count} record(s) imported via bulk upload.",
            'meta' => ['import' => true, 'count' => $count],
        ]);
    }
}
