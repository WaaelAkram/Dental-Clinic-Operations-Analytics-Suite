<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
           /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        try {
            // This validates the input and puts any errors into the 'updatePassword' bag.
            $validated = $request->validateWithBag('updatePassword', [
                'current_password' => ['required', 'current_password'],
                'password' => ['required', 'string', 'confirmed'],
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            
            // --- THIS IS THE FIX ---
            // When validation fails, redirect back.
            // Then, take the errors from the 'updatePassword' bag
            // and merge them into the default error bag so they can be displayed.
            return back()->withErrors($e->validator->errors()->getMessages(), 'updatePassword');
        }

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back()->with('status', 'password-updated');
    }
}
