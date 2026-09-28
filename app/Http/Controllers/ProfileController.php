<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('profile.edit', [
            'user' => $request->user()->load('section.gradeLevel', 'section.trackStrand'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('profile', [
            'name' => ['required', 'string', 'max:255'],
        ]);

        $request->user()->update($data);

        return back()->with('status', 'Your name was updated.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validateWithBag('password', [
            'current_password' => ['required', 'current_password'],
            'password'         => ['required', 'confirmed', 'different:current_password', Password::min(8)->letters()->numbers()],
        ], [
            'current_password.current_password' => 'Your current password is incorrect.',
            'password.different'                => 'Choose a password different from your current one.',
        ]);

        $request->user()->update(['password' => $data['password']]); // hashed by the model cast
        $request->session()->regenerate();

        return back()->with('status', 'Your password was changed.');
    }
}
