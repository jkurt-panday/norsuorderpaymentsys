<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

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
        /**
         * By default, Eloquent snake_cases relation keys when serializing a
         * model to an array/JSON (e.g. the `staffInput()` relation becomes
         * `staff_input` in the JSON Inertia sends to the frontend). React
         * code across this app checks camelCase keys that match the
         * relationship method names exactly (`formInput.staffInput`,
         * `formInput.paymentDetailOption`, etc), so those checks were
         * silently always false — the data was there, just under a
         * different key name than the frontend was looking for.
         *
         * Disabling this only changes relation key casing; actual database
         * column attributes (already snake_case in the DB) are unaffected.
         */


        /**
         * This is very important, without this most of the code wont work, always keep snake attributes at false
         * Never state it as true because it wont connect with the database.
        */
         
        Model::$snakeAttributes = false;
    }
}
