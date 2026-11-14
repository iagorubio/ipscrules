<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

/** Aceptación pública de una invitación: el invitado elige su nombre y contraseña. */
class InvitationController extends Controller
{
    public function show(string $token): View
    {
        $invitation = Invitation::findValid($token);

        return view('invitation.accept', ['invitation' => $invitation, 'token' => $token]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = Invitation::findValid($token);
        abort_unless($invitation, 404);

        $data = $request->validate([
            'name' => 'required|string|max:120',
            'password' => ['required', 'confirmed', Password::min(10)],
        ]);

        if (User::where('email', $invitation->email)->exists()) {
            return back()->withErrors(['name' => 'Ya existe una cuenta con ese correo.']);
        }

        $user = DB::transaction(function () use ($invitation, $data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $invitation->email,
                'password' => $data['password'],
                'role' => $invitation->role,
                'monthly_quota' => config('ipscrules.default_quota'),
            ]);
            $invitation->update(['accepted_at' => now()]);

            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('ask');
    }
}
