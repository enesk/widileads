<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Constants\FunnelFieldKey;
use App\Constants\FunnelStatus;
use App\Constants\TenantType;
use App\Models\Funnel;
use App\Models\FunnelOption;
use App\Models\FunnelQuestion;
use App\Models\FunnelStep;
use App\Models\Tenant;
use Illuminate\Database\Seeder;

/**
 * FB-010: Legt einen kleinen Beispiel-Funnel an, damit Builder und Runtime
 * gegen echte Daten entwickelt werden koennen.
 *
 * Bewusst nicht Teil des DatabaseSeeder -- der Seeder wird gezielt aufgerufen:
 * `php artisan db:seed --class=FunnelExampleSeeder`. Die vollstaendige
 * Pfotencheck-Vorlage kommt mit FB-019.
 */
class FunnelExampleSeeder extends Seeder
{
    private const SLUG = 'beispiel-funnel';

    public function run(): void
    {
        $tenant = Tenant::query()->where('type', TenantType::OPERATOR->value)->first()
            ?? Tenant::query()->first();

        if ($tenant === null) {
            $this->command?->warn('Kein Mandant vorhanden - der Beispiel-Funnel wurde uebersprungen.');

            return;
        }

        if (Funnel::query()->withoutGlobalScopes()->where('tenant_id', $tenant->id)->where('slug', self::SLUG)->exists()) {
            $this->command?->info('Der Beispiel-Funnel existiert bereits.');

            return;
        }

        $funnel = Funnel::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Beispiel-Funnel',
            'slug' => self::SLUG,
            'status' => FunnelStatus::DRAFT,
            'contact_step_position' => 2,
        ]);

        $questionStep = FunnelStep::query()->create([
            'funnel_id' => $funnel->id,
            'position' => 1,
            'title' => 'Deine Situation',
            'description' => 'Zwei kurze Fragen, damit wir passend beraten koennen.',
        ]);

        $animalQuestion = FunnelQuestion::query()->create([
            'step_id' => $questionStep->id,
            'position' => 1,
            'type' => 'single_choice',
            'field_key' => 'tierart',
            'label' => 'Welches Tier moechtest du versichern?',
            'required' => true,
        ]);

        foreach ([['Hund', 'hund', 10], ['Katze', 'katze', 8], ['Anderes Tier', 'anderes', 2]] as $position => [$label, $value, $score]) {
            FunnelOption::query()->create([
                'question_id' => $animalQuestion->id,
                'position' => $position + 1,
                'label' => $label,
                'value' => $value,
                'score' => $score,
            ]);
        }

        FunnelQuestion::query()->create([
            'step_id' => $questionStep->id,
            'position' => 2,
            'type' => 'number',
            'field_key' => 'alter_in_jahren',
            'label' => 'Wie alt ist dein Tier?',
            'required' => true,
        ]);

        $contactStep = FunnelStep::query()->create([
            'funnel_id' => $funnel->id,
            'position' => 2,
            'title' => 'Deine Kontaktdaten',
            'description' => 'Damit dich ein passender Anbieter erreichen kann.',
        ]);

        $contactFields = [
            [FunnelFieldKey::VORNAME, 'text', 'Vorname', true],
            [FunnelFieldKey::NACHNAME, 'text', 'Nachname', true],
            [FunnelFieldKey::EMAIL, 'email', 'E-Mail-Adresse', true],
            [FunnelFieldKey::TELEFON, 'phone', 'Telefonnummer', true],
            [FunnelFieldKey::PLZ, 'postal_code', 'Postleitzahl', true],
            [FunnelFieldKey::EINWILLIGUNG, 'consent', 'Ich darf zu meiner Anfrage kontaktiert werden.', true],
        ];

        foreach ($contactFields as $position => [$fieldKey, $type, $label, $required]) {
            FunnelQuestion::query()->create([
                'step_id' => $contactStep->id,
                'position' => $position + 1,
                'type' => $type,
                'field_key' => $fieldKey->value,
                'label' => $label,
                'required' => $required,
            ]);
        }

        $this->command?->info(sprintf('Beispiel-Funnel angelegt (Token %s).', $funnel->public_token));
    }
}
