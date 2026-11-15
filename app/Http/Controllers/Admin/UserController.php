<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\InvitationMail;
use App\Models\AskLog;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class UserController extends Controller
{
    public function index(): View
    {
        $monthStart = now()->startOfMonth();
        $usage = AskLog::where('created_at', '>=', $monthStart)
            ->selectRaw('user_id, count(*) as questions, sum(input_tokens) as tin, sum(output_tokens) as tout')
            ->groupBy('user_id')->get()->keyBy('user_id');

        return view('admin.users', [
            'users' => User::orderBy('name')->get(),
            'usage' => $usage,
            'pending' => Invitation::whereNull('accepted_at')->where('expires_at', '>', now())->latest()->get(),
            'monthTotal' => (int) $usage->sum('questions'),
            'globalLimit' => config('ipscrules.global_monthly_limit'),
        ]);
    }

    public function invite(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => 'required|email|max:190',
            'role' => ['required', Rule::in([User::ROLE_ARBITRO, User::ROLE_ADMIN])],
        ]);
        $email = mb_strtolower(trim($data['email']));

        if (User::where('email', $email)->exists()) {
            return back()->withErrors(['email' => 'Ya existe una cuenta con ese correo.'])->withInput();
        }

        Invitation::where('email', $email)->whereNull('accepted_at')->delete();
        [$invitation, $token] = Invitation::issue($email, $data['role'], $request->user()->id);

        return $this->issued($request, $invitation, $token, $request->boolean('send_email'));
    }

    /** El token solo se guarda como hash: reenviar crea una invitación nueva con otro enlace. */
    public function resend(Request $request, Invitation $invitation): RedirectResponse
    {
        abort_if($invitation->accepted_at, 404);
        $email = $invitation->email;
        $role = $invitation->role;
        $invitation->delete();
        [$new, $token] = Invitation::issue($email, $role, $request->user()->id);

        return $this->issued($request, $new, $token, true);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $data = $request->validate([
            'monthly_quota' => 'required|integer|min:0|max:5000',
            'role' => ['required', Rule::in([User::ROLE_ARBITRO, User::ROLE_ADMIN])],
        ]);
        $data['is_active'] = $request->boolean('is_active');

        // Un admin no puede quitarse a sí mismo el acceso ni el rol
        if ($user->is($request->user())) {
            $data['is_active'] = true;
            $data['role'] = User::ROLE_ADMIN;
        }

        $user->update($data);

        return back()->with('status', 'Usuario actualizado.');
    }

    public function revoke(Invitation $invitation): RedirectResponse
    {
        $invitation->delete();

        return back()->with('status', 'Invitación anulada.');
    }

    /** Muestra el enlace una sola vez y, si se pide, lo envía por correo avisando si el envío falla. */
    private function issued(Request $request, Invitation $invitation, string $token, bool $sendEmail): RedirectResponse
    {
        $link = route('invitation.show', $token);
        $back = back()->with('invite_link', $link)->with('invite_email', $invitation->email);

        if (! $sendEmail) {
            return $back;
        }

        try {
            Mail::to($invitation->email)->send(new InvitationMail($link, $invitation->role, $request->user()->name, $invitation->expires_at));
        } catch (Throwable $e) {
            report($e);

            return $back->with('mail_error', 'No se pudo enviar el correo. Copia el enlace y envíalo tú.');
        }

        return $back->with('mail_sent', "Correo enviado a {$invitation->email}.");
    }
}
