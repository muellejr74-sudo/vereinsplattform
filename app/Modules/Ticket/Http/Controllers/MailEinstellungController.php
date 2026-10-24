<?php

namespace App\Modules\Ticket\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** „Mein Konto“: E-Mails zu neuen Druckaufträgen an/aus (E132). */
class MailEinstellungController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $id = $request->user()->id;
        if ($request->boolean('druckauftraege')) {
            DB::table('ticket_mail_abmeldungen')->where('user_id', $id)->delete();
        } else {
            DB::table('ticket_mail_abmeldungen')->insertOrIgnore(['user_id' => $id, 'created_at' => now(), 'updated_at' => now()]);
        }

        return back()->with('hinweis', $request->boolean('druckauftraege') ? 'Sie erhalten E-Mails zu neuen Druckaufträgen.' : 'E-Mails zu neuen Druckaufträgen sind abgeschaltet.');
    }
}
