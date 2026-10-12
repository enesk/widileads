{{--
    Der Verifizierungs-Screen der Telefonsperre (LP-ONBOARD-005).

    Fokus-Rahmen und nichts daneben: keine Navigation, kein Workspace-Wechsler,
    keine Guthaben-Anzeige, keine Daten des Workspaces. Wer hier ist, hat genau
    eine Aufgabe -- und soll bis dahin nichts vom System sehen. Der einzige
    Ausweg ist "Abmelden", klein im Fuss des Rahmens.

    Die Seite selbst traegt nur das Markenband und seine Begruessung; die drei
    Screens sind Zustaende einer Livewire-Komponente.
--}}
<x-layouts.portal-onboarding :heading="__('portal.onboarding.phone.welcome', ['name' => \Illuminate\Support\Str::before(auth()->user()?->name ?? '', ' ')])">

    <x-slot name="title">
        {{ __('portal.onboarding.phone.title') }}
    </x-slot>

    <livewire:portal.onboarding.phone-verification />

</x-layouts.portal-onboarding>
