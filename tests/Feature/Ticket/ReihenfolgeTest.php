<?php

namespace Tests\Feature\Ticket;

use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Models\Station;
use App\Modules\Ticket\Models\Ticketkategorie;

/** Reihenfolge per Ziehen oder ▲▼ für Stationen und Ticket-Kategorien (Wunsch des Betreibers, 24.10.2026). */
class ReihenfolgeTest extends TicketTestCase
{
    public function test_stationen_neu_ordnen_und_bearbeiten_behaelt_reihenfolge(): void
    {
        $admin = $this->vereinsadmin($this->tvm);
        $this->als($admin)->get('/verein/stationen')->assertOk()->assertSee('data-sortierbar', false)->assertSee('Sortable-1.15.7.min.js', false);

        $ids = $this->im($this->tvm, fn () => Station::orderBy('reihenfolge')->pluck('id')->all());
        $neu = array_reverse($ids);
        $this->postJson('/verein/stationen/reihenfolge', ['ids' => $neu])->assertOk()->assertJson(['ok' => true]);
        $this->assertSame($neu, $this->im($this->tvm, fn () => Station::orderBy('reihenfolge')->pluck('id')->all()));

        // Bearbeiten ohne Reihenfolge-Feld verschiebt die Station nicht.
        $erste = $this->im($this->tvm, fn () => Station::find($neu[0]));
        $this->put('/verein/stationen/'.$erste->id, ['name' => 'Kasse vorne', 'art' => $erste->art])->assertSessionHasNoErrors();
        $this->assertSame($neu[0], $this->im($this->tvm, fn () => Station::orderBy('reihenfolge')->value('id')));

        // Fremde Kennungen werden abgelehnt.
        $fremd = Mandant::factory()->create();
        $this->postJson('/verein/stationen/reihenfolge', ['ids' => [...$neu, 999999]])->assertStatus(422);
        $this->assertNotNull($fremd);

        $this->als($this->verkaeufer)->postJson('/verein/stationen/reihenfolge', ['ids' => $neu])->assertForbidden();
    }

    public function test_kategorien_neu_ordnen(): void
    {
        $person = $this->personMitRechten($this->tvm, ['ticket.kategorien']);
        $url = '/tickets/veranstaltungen/'.$this->theater->id.'/kategorien/reihenfolge';

        $this->als($person)->get('/tickets/veranstaltungen/'.$this->theater->id)->assertSee('data-sortierbar', false);
        $this->postJson($url, ['ids' => [$this->kinder->id, $this->erwachsene->id]])->assertOk();
        $this->assertSame(['Kinder', 'Erwachsene'], $this->im($this->tvm, fn () => Ticketkategorie::where('ist_freikarte', false)->orderBy('reihenfolge')->pluck('name')->all()));

        // Bearbeiten behält die Reihenfolge.
        $this->put('/tickets/veranstaltungen/'.$this->theater->id.'/kategorien/'.$this->erwachsene->id, ['name' => 'Erwachsene', 'preis' => '13,00'])->assertSessionHasNoErrors();
        $this->assertSame('Kinder', $this->im($this->tvm, fn () => Ticketkategorie::where('ist_freikarte', false)->orderBy('reihenfolge')->value('name')));

        $this->als($this->verkaeufer)->postJson($url, ['ids' => [$this->erwachsene->id, $this->kinder->id]])->assertForbidden();
    }
}
