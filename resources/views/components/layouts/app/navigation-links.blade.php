<x-nav.item route="#features">{{ __('Features') }}</x-nav.item>
<x-nav.item route="#tech-stack">{{ __('Tech Stack') }}</x-nav.item>
<x-nav.item route="#pricing">{{ __('Pricing') }}</x-nav.item>
<x-nav.item route="#faq">{{ __('FAQ') }}</x-nav.item>
@if(config('funnel.features.roadmap') && config('app.roadmap_enabled'))
    <x-nav.item route="roadmap">{{ __('Roadmap') }}</x-nav.item>
@endif
@if(config('funnel.features.blog'))
    <x-nav.item route="blog">{{ __('Blog') }}</x-nav.item>
@endif
@guest
    <x-nav.item route="login" class="md:hidden">{{ __('Login') }}</x-nav.item>
@endguest
