{{--
    FB-050: Kaeufer-Registrierung. Tailwind und daisyUI, keine Filament-
    Komponenten -- die Seite liegt ausserhalb jedes Panels.
--}}
<div class="max-w-2xl mx-auto">
    @if ($registration !== null)
        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <h1 class="card-title">{{ __('marketplace.buyer.form.received_heading') }}</h1>

                <p>{{ __('marketplace.buyer.form.received_text', ['company' => $registration->company_name]) }}</p>

                <div class="mt-4">
                    <span class="badge badge-outline">{{ $registration->status->label() }}</span>
                </div>

                @if ($registration->status->isPending())
                    <p class="mt-4 text-sm opacity-70">{{ __('marketplace.buyer.form.received_hint') }}</p>
                @endif

                @if ($registration->rejection_reason !== null)
                    <div class="alert alert-warning mt-4">
                        <span>{{ $registration->rejection_reason }}</span>
                    </div>
                @endif
            </div>
        </div>
    @else
        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <h1 class="card-title">{{ __('marketplace.buyer.form.heading') }}</h1>
                <p class="text-sm opacity-70">{{ __('marketplace.buyer.form.description') }}</p>

                <form wire:submit="submit" class="mt-6 space-y-4">
                    <div class="form-control">
                        <label class="label" for="company-name">
                            <span class="label-text">{{ __('marketplace.buyer.form.company_name') }}</span>
                        </label>
                        <input id="company-name" type="text" wire:model="companyName"
                               class="input input-bordered w-full" required>
                        @error('companyName')
                            <span class="text-error text-sm mt-1">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="form-control">
                            <label class="label" for="contact-name">
                                <span class="label-text">{{ __('marketplace.buyer.form.contact_name') }}</span>
                            </label>
                            <input id="contact-name" type="text" wire:model="contactName"
                                   class="input input-bordered w-full" required>
                            @error('contactName')
                                <span class="text-error text-sm mt-1">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-control">
                            <label class="label" for="contact-email">
                                <span class="label-text">{{ __('marketplace.buyer.form.contact_email') }}</span>
                            </label>
                            <input id="contact-email" type="email" wire:model="contactEmail"
                                   class="input input-bordered w-full" required>
                            @error('contactEmail')
                                <span class="text-error text-sm mt-1">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="form-control">
                            <label class="label" for="contact-phone">
                                <span class="label-text">{{ __('marketplace.buyer.form.contact_phone') }}</span>
                            </label>
                            <input id="contact-phone" type="tel" wire:model="contactPhone"
                                   class="input input-bordered w-full">
                            @error('contactPhone')
                                <span class="text-error text-sm mt-1">{{ $message }}</span>
                            @enderror
                        </div>

                        <div class="form-control">
                            <label class="label" for="vat-id">
                                <span class="label-text">{{ __('marketplace.buyer.form.vat_id') }}</span>
                            </label>
                            <input id="vat-id" type="text" wire:model="vatId"
                                   class="input input-bordered w-full" placeholder="DE123456789" required>
                            @error('vatId')
                                <span class="text-error text-sm mt-1">{{ $message }}</span>
                            @enderror
                        </div>
                    </div>

                    <div class="form-control">
                        <label class="label" for="broker-register-number">
                            <span class="label-text">{{ __('marketplace.buyer.form.broker_register_number') }}</span>
                        </label>
                        <input id="broker-register-number" type="text" wire:model="brokerRegisterNumber"
                               class="input input-bordered w-full">
                        <span class="text-sm opacity-70 mt-1">
                            {{ __('marketplace.buyer.form.broker_register_number_helper') }}
                        </span>
                        @error('brokerRegisterNumber')
                            <span class="text-error text-sm mt-1">{{ $message }}</span>
                        @enderror
                    </div>

                    <div class="form-control">
                        <label class="label cursor-pointer justify-start gap-3">
                            <input type="checkbox" wire:model="avAccepted" class="checkbox" required>
                            <span class="label-text">{{ __('marketplace.buyer.form.av_accepted') }}</span>
                        </label>
                        <span class="text-sm opacity-70">{{ __('marketplace.buyer.form.av_accepted_helper') }}</span>
                        @error('avAccepted')
                            <span class="text-error text-sm mt-1">{{ $message }}</span>
                        @enderror
                    </div>

                    <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                        {{ __('marketplace.buyer.form.submit') }}
                    </button>
                </form>
            </div>
        </div>
    @endif
</div>
