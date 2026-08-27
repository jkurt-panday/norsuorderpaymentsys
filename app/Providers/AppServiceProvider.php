<?php

namespace App\Providers;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Symfony\Component\Mailer\Bridge\Brevo\Transport\BrevoTransportFactory;
use Symfony\Component\Mailer\Transport\Dsn;

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
use App\Listeners\LogSentEmails;
use App\Listeners\LogSentNotifications;
use App\Observers\ActivityLogObserver;

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

        Mail::extend('brevo', function () {
            return (new BrevoTransportFactory)->create(
                new Dsn(
                    'brevo+api',
                    'default',
                    config('services.brevo.key')
                )
            );
        });

        Event::listen(\Illuminate\Mail\Events\MessageSent::class, LogSentEmails::class);
        Event::listen(\Illuminate\Notifications\Events\NotificationSent::class, LogSentNotifications::class);
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
}
