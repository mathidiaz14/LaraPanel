@foreach($nodes as $node)
    <div style="margin-bottom: 2px;">
        <div style="display:flex;align-items:center;padding:6px 12px;padding-left:{{ 12 + ($level * 16) }}px;border-radius:8px;background:{{ $currentPath === $node['path'] ? 'rgba(99,102,241,0.15)' : 'transparent' }};color:{{ $currentPath === $node['path'] ? 'var(--accent-light)' : 'var(--text-secondary)' }};font-size:13px;font-weight:600;cursor:pointer;transition:background 0.2s;">
            
            {{-- Toggle Button (Arrow) --}}
            <button wire:click.stop="toggleNode('{{ $node['path'] }}')" style="background:transparent;border:none;color:inherit;cursor:pointer;padding:4px;margin-right:4px;width:20px;display:flex;align-items:center;justify-content:center;transition:transform 0.2s;{{ $node['isExpanded'] ? 'transform:rotate(90deg);' : '' }}">
                <i class="fa-solid fa-chevron-right" style="font-size:10px;opacity:0.6;"></i>
            </button>
            
            {{-- Folder Icon & Name (Navigate) --}}
            <div wire:click.stop="navigate('{{ $node['path'] }}')" style="display:flex;align-items:center;flex:1;gap:8px;">
                <i class="fa-solid fa-{{ $node['isExpanded'] ? 'folder-open' : 'folder' }}" style="font-size:14px;color:{{ $currentPath === $node['path'] ? 'var(--accent-light)' : '#38bdf8' }};"></i> 
                <span>{{ $node['name'] }}</span>
            </div>
            
        </div>
        
        @if($node['isExpanded'] && !empty($node['children']))
            @include('livewire.files.tree-node', ['nodes' => $node['children'], 'level' => $level + 1])
        @elseif($node['isExpanded'] && empty($node['children']))
            <div style="padding:6px 12px;padding-left:{{ 12 + (($level + 1) * 16) + 24 }}px;font-size:11px;color:var(--text-muted);font-style:italic;">
                (vacío)
            </div>
        @endif
    </div>
@endforeach
