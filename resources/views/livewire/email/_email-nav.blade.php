@php
    $navItems = [
        'buzones'        => ['route' => 'email.index',         'icon' => 'fa-inbox',         'label' => 'Buzones'],
        'aliases'        => ['route' => 'email.aliases',       'icon' => 'fa-at',            'label' => 'Aliases'],
        'autoresponders' => ['route' => 'email.autoresponders','icon' => 'fa-reply',         'label' => 'Autoresponders'],
        'dkim'           => ['route' => 'email.dkim',          'icon' => 'fa-shield-halved', 'label' => 'DKIM/SPF/DMARC'],
        'stats'          => ['route' => 'email.stats',         'icon' => 'fa-chart-bar',     'label' => 'Estadísticas'],
    ];
@endphp
<div style="display:flex;gap:8px;margin-bottom:24px;border-bottom:1px solid var(--glass-border);padding-bottom:16px;flex-wrap:wrap;">
    @foreach($navItems as $key => $item)
    <a href="{{ route($item['route']) }}" class="btn {{ $active === $key ? 'btn-primary' : 'btn-ghost' }} btn-sm">
        <i class="fa-solid {{ $item['icon'] }}"></i>
        {{ $item['label'] }}
    </a>
    @endforeach
</div>
