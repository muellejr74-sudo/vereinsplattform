<?php

namespace Database\Factories;

use App\Modules\Kern\Models\Mandant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Mandant>
 */
class MandantFactory extends Factory
{
    protected $model = Mandant::class;

    public function definition(): array
    {
        return [
            'typ' => Mandant::TYP_VEREIN,
            'name' => 'Verein '.fake()->unique()->city(),
            'kuerzel' => strtoupper(fake()->unique()->lexify('???')),
            'aktiv' => true,
        ];
    }
}
