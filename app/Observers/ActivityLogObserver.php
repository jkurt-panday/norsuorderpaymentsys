<?php

namespace App\Observers;

use App\Models\AcademicTerm;
use App\Models\ActivityLog;
use App\Models\AssessmentForm;
use App\Models\BankAccountInfo;
use App\Models\Course;
use App\Models\Courses;
use App\Models\FormInput;
use App\Models\GraduateLedger;
use App\Models\LawSchoolLedger;
use App\Models\Membership;
use App\Models\PaymentDetailOption;
use App\Models\StaffInput;
use App\Models\Student;
use App\Models\SupportingDocument;
use App\Models\UACS;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Centralized audit trail. Every create / update / delete on the entities
 * staff and admins work with is recorded in activity_log with the acting
 * user's name + role (snapshotted) so the dashboard feed is complete and
 * stays readable even if the user is later removed.
 */
class ActivityLogObserver
{
    /**
     * @return array{type:string,label:string,action:string,verb:string,extra:string}|null
     */
    private function describe(Model $model, string $event): ?array
    {
        // Special-case Order of Payment: "created" means it was processed/
        // accepted, so we surface it as a distinct "processed" event.
        if ($model instanceof StaffInput) {
            $name = $model->formInput?->reference_number ?? ('#' . $model->getKey());

            if ($event === 'created') {
                return [
                    'type' => 'Order of Payment',
                    'label' => $name,
                    'action' => 'processed',
                    'verb' => 'processed',
                    'extra' => ' (status: ' . ($model->status ?? 'pending') . ')',
                ];
            }

            if ($event === 'updated') {
                $statusNote = $model->wasChanged('status')
                    ? ' (status changed to ' . ($model->status ?? 'unknown') . ')'
                    : '';

                return [
                    'type' => 'Order of Payment',
                    'label' => $name,
                    'action' => 'updated',
                    'verb' => 'updated',
                    'extra' => $statusNote,
                ];
            }

            return [
                'type' => 'Order of Payment',
                'label' => $name,
                'action' => 'deleted',
                'verb' => 'deleted',
                'extra' => '',
            ];
        }

        if ($model instanceof FormInput) {
            $name = $model->reference_number ?? ('#' . $model->getKey());
            $map = [
                'created' => ['action' => 'created', 'verb' => 'submitted'],
                'updated' => ['action' => 'updated', 'verb' => 'updated'],
                'deleted' => ['action' => 'deleted', 'verb' => 'deleted'],
            ];

            return [
                'type' => 'Payment Request',
                'label' => $name,
                'action' => $map[$event]['action'],
                'verb' => $map[$event]['verb'],
                'extra' => '',
            ];
        }

        if ($model instanceof UACS) {
            $name = $model->display_name;
            $map = [
                'created' => ['action' => 'created', 'verb' => 'added'],
                'updated' => ['action' => 'updated', 'verb' => 'updated'],
                'deleted' => ['action' => 'deleted', 'verb' => 'deleted'],
            ];

            return [
                'type' => 'UACS',
                'label' => $name,
                'action' => $map[$event]['action'],
                'verb' => $map[$event]['verb'],
                'extra' => '',
            ];
        }

        if ($model instanceof Membership) {
            $name = $model->member_code . ' - ' . $model->member_desc;
            $map = [
                'created' => ['action' => 'created', 'verb' => 'added'],
                'updated' => ['action' => 'updated', 'verb' => 'updated'],
                'deleted' => ['action' => 'deleted', 'verb' => 'deleted'],
            ];

            return [
                'type' => 'Membership',
                'label' => $name,
                'action' => $map[$event]['action'],
                'verb' => $map[$event]['verb'],
                'extra' => '',
            ];
        }

        if ($model instanceof BankAccountInfo) {
            $name = $model->display_name;
            $map = [
                'created' => ['action' => 'created', 'verb' => 'added'],
                'updated' => ['action' => 'updated', 'verb' => 'updated'],
                'deleted' => ['action' => 'deleted', 'verb' => 'deleted'],
            ];

            return [
                'type' => 'Bank Account',
                'label' => $name,
                'action' => $map[$event]['action'],
                'verb' => $map[$event]['verb'],
                'extra' => '',
            ];
        }

        if ($model instanceof PaymentDetailOption) {
            $name = $model->payment_desc;
            $map = [
                'created' => ['action' => 'created', 'verb' => 'added'],
                'updated' => ['action' => 'updated', 'verb' => 'updated'],
                'deleted' => ['action' => 'deleted', 'verb' => 'deleted'],
            ];

            return [
                'type' => 'Payment Option',
                'label' => $name,
                'action' => $map[$event]['action'],
                'verb' => $map[$event]['verb'],
                'extra' => '',
            ];
        }

        if ($model instanceof Courses) {
            $name = $model->course_code . ' - ' . $model->course_desc;
            $map = [
                'created' => ['action' => 'created', 'verb' => 'added'],
                'updated' => ['action' => 'updated', 'verb' => 'updated'],
                'deleted' => ['action' => 'deleted', 'verb' => 'deleted'],
            ];

            return [
                'type' => 'Course',
                'label' => $name,
                'action' => $map[$event]['action'],
                'verb' => $map[$event]['verb'],
                'extra' => '',
            ];
        }

        if ($model instanceof GraduateLedger) {
            $name = $model->student?->full_name
                ?? ($model->reference_or_jev_number ?: ('#' . $model->getKey()));
            $map = [
                'created' => ['action' => 'created', 'verb' => 'added'],
                'updated' => ['action' => 'updated', 'verb' => 'updated'],
                'deleted' => ['action' => 'deleted', 'verb' => 'deleted'],
            ];

            return [
                'type' => 'Graduate Ledger',
                'label' => $name,
                'action' => $map[$event]['action'],
                'verb' => $map[$event]['verb'],
                'extra' => '',
            ];
        }

        if ($model instanceof LawSchoolLedger) {
            $name = trim(($model->last_name ?? '') . ', ' . ($model->first_name ?? ''))
                ?: ('#' . $model->getKey());
            $map = [
                'created' => ['action' => 'created', 'verb' => 'added'],
                'updated' => ['action' => 'updated', 'verb' => 'updated'],
                'deleted' => ['action' => 'deleted', 'verb' => 'deleted'],
            ];

            return [
                'type' => 'Law Ledger',
                'label' => $name,
                'action' => $map[$event]['action'],
                'verb' => $map[$event]['verb'],
                'extra' => '',
            ];
        }

        if ($model instanceof Student) {
            $name = $model->full_name ?: ($model->student_number ?: ('#' . $model->getKey()));
            $map = [
                'created' => ['action' => 'created', 'verb' => 'added'],
                'updated' => ['action' => 'updated', 'verb' => 'updated'],
                'deleted' => ['action' => 'deleted', 'verb' => 'deleted'],
            ];

            return [
                'type' => 'Student',
                'label' => $name,
                'action' => $map[$event]['action'],
                'verb' => $map[$event]['verb'],
                'extra' => '',
            ];
        }

        if ($model instanceof Course) {
            $name = $model->code . ' - ' . $model->title;
            $map = [
                'created' => ['action' => 'created', 'verb' => 'added'],
                'updated' => ['action' => 'updated', 'verb' => 'updated'],
                'deleted' => ['action' => 'deleted', 'verb' => 'deleted'],
            ];

            return [
                'type' => 'Graduate Course',
                'label' => $name,
                'action' => $map[$event]['action'],
                'verb' => $map[$event]['verb'],
                'extra' => '',
            ];
        }

        if ($model instanceof AcademicTerm) {
            $name = trim($model->school_year . ' ' . $model->semester)
                ?: ('#' . $model->getKey());
            $map = [
                'created' => ['action' => 'created', 'verb' => 'added'],
                'updated' => ['action' => 'updated', 'verb' => 'updated'],
                'deleted' => ['action' => 'deleted', 'verb' => 'deleted'],
            ];

            return [
                'type' => 'Academic Term',
                'label' => $name,
                'action' => $map[$event]['action'],
                'verb' => $map[$event]['verb'],
                'extra' => '',
            ];
        }

        if ($model instanceof AssessmentForm) {
            $name = $model->reference_number ?: ('#' . $model->getKey());
            $map = [
                'created' => ['action' => 'created', 'verb' => 'submitted'],
                'updated' => ['action' => 'updated', 'verb' => 'updated'],
                'deleted' => ['action' => 'deleted', 'verb' => 'deleted'],
            ];

            return [
                'type' => 'Assessment',
                'label' => $name,
                'action' => $map[$event]['action'],
                'verb' => $map[$event]['verb'],
                'extra' => '',
            ];
        }

        if ($model instanceof SupportingDocument) {
            $name = $model->original_filename ?: ('#' . $model->getKey());
            $map = [
                'created' => ['action' => 'created', 'verb' => 'uploaded'],
                'updated' => ['action' => 'updated', 'verb' => 'updated'],
                'deleted' => ['action' => 'deleted', 'verb' => 'deleted'],
            ];

            return [
                'type' => 'Document',
                'label' => $name,
                'action' => $map[$event]['action'],
                'verb' => $map[$event]['verb'],
                'extra' => '',
            ];
        }

        if ($model instanceof User) {
            $name = $model->name;

            if ($event === 'created') {
                $action = 'user.created';
                $verb = 'provisioned';
                $extra = ' (role: ' . ($model->role ?? 'unknown') . ')';
            } elseif ($event === 'deleted') {
                $action = 'user.deactivated';
                $verb = 'deactivated';
                $extra = '';
            } else {
                $action = $event;
                $verb = $event;
                $extra = '';
            }

            return [
                'type' => 'System Account',
                'label' => $name,
                'action' => $action,
                'verb' => $verb,
                'extra' => $extra,
            ];
        }

        return null;
    }

    private function record(Model $model, string $event): void
    {
        $actor = Auth::guard('web')->user();

        if (! $actor) {
            return;
        }

        $descriptor = $this->describe($model, $event);

        if ($descriptor === null) {
            return;
        }

        $changes = $event === 'deleted' ? [] : $this->changeList($model, $event);

        ActivityLog::create([
            'actor_id' => $actor?->getAuthIdentifier(),
            'actor_name' => $actor?->name,
            'actor_role' => $actor?->role,
            'action' => $descriptor['action'],
            'target_id' => $model instanceof User ? $model->getKey() : null,
            'subject_type' => get_class($model),
            'subject_id' => $model->getKey(),
            'description' => $descriptor['type'] . ' ' . $descriptor['label']
                . ' was ' . $descriptor['verb'] . $descriptor['extra'] . '.',
            'meta' => [
                'subject_name' => $descriptor['label'],
                'changes' => $changes,
            ],
        ]);
    }

    /**
     * Build a list of the actual field changes for the audit entry so the
     * feed can show exactly what was edited (e.g. Purpose: "old" → "new").
     * For "updated" we only capture the dirty attributes; for "created" we
     * capture every populated attribute. Sensitive fields are excluded.
     *
     * @return array<int, array{field: string, old: ?string, new: ?string}>
     */
    private function changeList(Model $model, string $event): array
    {
        $exclude = [
            'id',
            'created_at',
            'updated_at',
            'password',
            'remember_token',
            'email_verified_at',
            'meta',
            'two_factor_secret',
            'two_factor_recovery_codes',
        ];

        if ($event === 'updated') {
            $keys = array_keys($model->getDirty());
        } else {
            $keys = array_keys($model->getAttributes());
        }

        $changes = [];

        foreach ($keys as $key) {
            if (in_array($key, $exclude, true)) {
                continue;
            }

            $old = $event === 'updated' ? $model->getOriginal($key) : null;
            $new = $model->getAttribute($key);

            if ($event === 'created' && ($new === null || $new === '')) {
                continue;
            }

            $changes[] = [
                'field' => Str::headline($key),
                'old' => $this->formatValue($old),
                'new' => $this->formatValue($new),
            ];
        }

        return $changes;
    }

    private function formatValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        if (is_array($value) || is_object($value)) {
            return json_encode($value);
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        return (string) $value;
    }

    public function created(Model $model): void
    {
        $this->record($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->record($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->record($model, 'deleted');
    }
}
