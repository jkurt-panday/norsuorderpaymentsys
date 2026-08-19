<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
<<<<<<< HEAD
use App\Models\User;
=======
use App\Models\FormInput;
use App\Models\User;
use App\Models\UserProfile;
use Illuminate\Http\Request;
>>>>>>> 1c0c01473aaa7337a8c0deb7fca3a03823e4c703
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class GoogleController extends Controller
{
<<<<<<< HEAD
    public function redirect()
    {
=======
    public function redirect(Request $request)
    {
        if ($request->has('ref')) {
            session(['pending_reference_number' => $request->query('ref')]);
        }

>>>>>>> 1c0c01473aaa7337a8c0deb7fca3a03823e4c703
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        $googleUser = Socialite::driver('google')->user();

        $user = User::where('email', $googleUser->getEmail())->first();

        if (!$user) {
            $user = User::create([
                'name' => $googleUser->getName(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'password' => Str::random(40),
                'email_verified_at' => now(),
                'role' => 'client',
            ]);
        } else {
            $user->update([
                'google_id' => $googleUser->getId(),
            ]);
        }

<<<<<<< HEAD
        Auth::login($user);

=======
        // Link pending form submission profile info if user logged in via post-submission prompt
        $pendingRef = session('pending_reference_number');
        if ($pendingRef) {
            $formInput = FormInput::where('reference_number', $pendingRef)->first();
            if ($formInput) {
                UserProfile::updateOrCreate(
                    ['user_id' => $user->id],
                    [
                        'firstname_or_office'     => $formInput->firstname_or_office,
                        'middlename_or_project'   => $formInput->middlename_or_project,
                        'lastname_or_agency'      => $formInput->lastname_or_agency,
                        'contact_num'             => $formInput->contact_num,
                        'office_or_college'       => $formInput->office_or_college,
                        'position_or_designation' => $formInput->position_or_designation,
                        'address'                 => $formInput->address,
                    ]
                );
            }
            session()->forget('pending_reference_number');
        }

        Auth::login($user);

        if ($user->role === 'client') {
            return redirect()->route('public.submit')->with('success', 'Account linked! Your details have been saved for future submissions.');
        }

>>>>>>> 1c0c01473aaa7337a8c0deb7fca3a03823e4c703
        return redirect()->route('dashboard');
    }
}