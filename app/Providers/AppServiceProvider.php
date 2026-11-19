<?php

namespace App\Providers;

use App\Listeners\LogSentEmails;
use App\Listeners\LogSentNotifications;
use App\Models\AcademicTerm;
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
use App\Observers\ActivityLogObserver;
use Carbon\CarbonImmutable;
use Illuminate\Mail\Events\MessageSent;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiters();

        // Centralized audit trail: log all CRUD on staff/admin-managed
        // entities to activity_log (with the acting user's name + role).
        foreach ([
            FormInput::class,
            StaffInput::class,
            UACS::class,
            Membership::class,
            BankAccountInfo::class,
            PaymentDetailOption::class,
            Courses::class,
            User::class,
            GraduateLedger::class,
            LawSchoolLedger::class,
            Student::class,
            Course::class,
            AcademicTerm::class,
            AssessmentForm::class,
            SupportingDocument::class,
        ] as $observedModel) {
            $observedModel::observe(ActivityLogObserver::class);
        }

        Event::listen(MessageSent::class, LogSentEmails::class);
        Event::listen(NotificationSent::class, LogSentNotifications::class);

        $this->guardPersistentConnectionTransactions();
    }

    /**
     * A persistent PDO connection is reused across requests, so a fatal
     * error (timeout, out of memory) inside a transaction would leave the
     * connection sitting inside that transaction — Laravel only rolls back
     * on catchable exceptions. Close out any dangling transaction when the
     * process ends so the next request starts from a clean connection.
     */
    protected function guardPersistentConnectionTransactions(): void
    {
        $options = config('database.connections.pgsql.options', []);

        if (! array_key_exists(\PDO::ATTR_PERSISTENT, $options)) {
            return;
        }

        register_shutdown_function(function (): void {
            try {
                $connection = DB::connection();

                while ($connection->transactionLevel() > 0) {
                    $connection->rollBack();
                }
            } catch (Throwable) {
                // The connection is already gone; nothing left to clean up.
            }
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Register API rate limiters.
     * The 'login' limiter is owned by FortifyServiceProvider — not duplicated here.
     */
    private function configureRateLimiters(): void
    {
        RateLimiter::for('password-reset', function (Request $request) {
            return Limit::perMinute(5)->by($request->ip());
        });

        RateLimiter::for('public-form', function (Request $request) {
            return Limit::perMinute(20)->by($request->ip());
        });

        RateLimiter::for('upload', function (Request $request) {
            return Limit::perMinute(30)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('email', function (Request $request) {
            return Limit::perMinute(10)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('search', function (Request $request) {
            return Limit::perMinute(60)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('import', function (Request $request) {
            return Limit::perMinute(5)->by(optional($request->user())->id ?: $request->ip());
        });

        RateLimiter::for('export', function (Request $request) {
            return Limit::perMinute(10)->by(optional($request->user())->id ?: $request->ip());
        });
    }
}
