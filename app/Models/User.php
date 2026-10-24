<?php

namespace App\Models;

use App\Modules\Kern\Models\Mandant;
use App\Modules\Kern\Nachrichten\PasswortZuruecksetzen;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Benutzerkonto einer Person (E20: ein Konto pro Person, auch bei mehreren Vereinen).
 *
 * Das Kennzeichen "ist_superadmin" wird nie über die Webseite gesetzt, sondern
 * nur per Server-Befehl (E8a). Es ist deshalb nicht in $fillable.
 */
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'aktiv'];

    protected $hidden = [
        'password', 'remember_token', 'zwei_faktor_geheimnis', 'zwei_faktor_notfallcodes',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'ist_superadmin' => 'boolean',
            'aktiv' => 'boolean',
            'zwei_faktor_geheimnis' => 'encrypted',
            'zwei_faktor_notfallcodes' => 'encrypted:array',
            'zwei_faktor_aktiv_seit' => 'datetime',
            'letzte_anmeldung_am' => 'datetime',
        ];
    }

    public function vereine(): BelongsToMany
    {
        return $this->belongsToMany(Mandant::class, 'mitgliedschaften', 'user_id', 'mandant_id')
            ->withTimestamps()
            ->orderBy('name');
    }

    public function istSuperAdmin(): bool
    {
        return (bool) $this->ist_superadmin;
    }

    public function hatZweiFaktor(): bool
    {
        return $this->zwei_faktor_aktiv_seit !== null && $this->zwei_faktor_geheimnis !== null;
    }

    /** Vereine, in denen die Person arbeiten darf (Super-Admin: alle aktiven). */
    public function waehlbareVereine()
    {
        if ($this->istSuperAdmin()) {
            return Mandant::aktiv()->orderBy('name')->get();
        }

        return $this->vereine()->where('aktiv', true)->get();
    }

    public function darfInVerein(Mandant $mandant): bool
    {
        if (! $mandant->aktiv) {
            return false;
        }

        return $this->istSuperAdmin() || $this->vereine()->whereKey($mandant->id)->exists();
    }

    /** Deutschsprachige E-Mail statt der englischen Laravel-Vorlage. */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new PasswortZuruecksetzen($token));
    }
}
