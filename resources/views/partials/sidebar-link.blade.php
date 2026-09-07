{{-- Sidebar nav link. The icon and label come from props so the loop
     stays declarative (the icon set lives in partials/sidebar-icon). --}}
@props(['href', 'label', 'icon', 'active' => false])

<a href="{{ $href }}" @class(['sidebar-link', 'active' => $active]) @if($active) aria-current="page" @endif>
    @include('partials.sidebar-icon', ['name' => $icon])
    {{ $label }}
</a>