<x-layouts.portal>

    <x-slot name="title">
        {{ __('Anfragen aus deiner Region') }}
    </x-slot>

    <x-slot name="description">
        {{ __('Auf unseren Branchenportalen fragen taeglich Menschen nach Handwerkern, Aerzten und Dienstleistern. Du siehst die Anfragen, bevor du kaufst.') }}
    </x-slot>

    @include('pages.home.hero')
    @include('pages.home.payment-modes')
    @include('pages.home.requests')
    @include('pages.home.how-it-works')
    @include('pages.home.pricing')
    @include('pages.home.trust')
    @include('pages.home.faq')
    @include('pages.home.signup')

</x-layouts.portal>
