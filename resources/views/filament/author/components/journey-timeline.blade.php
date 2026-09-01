@php $id = app()->getLocale() === 'id'; @endphp

<ol class="author-journey-list">
    @foreach($steps as $step)
        <li class="author-journey-step is-{{ $step['state'] }}">
            <span class="author-journey-number is-{{ $step['state'] }}">
                @if($step['state'] === 'complete')
                    <x-filament::icon icon="heroicon-m-check" class="h-4 w-4" />
                @elseif($step['state'] === 'failed')
                    <x-filament::icon icon="heroicon-m-x-mark" class="h-4 w-4" />
                @else
                    {{ $step['number'] }}
                @endif
            </span>

            <span class="min-w-0 flex-1">
                <span class="block text-xs text-gray-500">{{ $id ? 'Tahap' : 'Step' }} {{ $step['number'] }}</span>
                <span class="block text-sm font-medium {{ $step['state'] === 'current' ? 'text-primary-700' : ($step['state'] === 'failed' ? 'text-danger-700' : ($step['state'] === 'upcoming' ? 'text-gray-500' : 'text-gray-900')) }}">
                    {{ $step['label'] }}
                </span>

                @if($step['date'])
                    <span class="mt-1 block text-xs text-gray-500">
                        {{ $step['state'] === 'complete' ? ($id ? 'Selesai' : 'Completed') : ($id ? 'Diperbarui' : 'Updated') }}
                        {{ $step['date']->format('d M Y') }}
                    </span>
                @elseif($step['state'] === 'complete')
                    <span class="mt-1 block text-xs text-gray-500">{{ $id ? 'Selesai' : 'Completed' }}</span>
                @elseif($step['actor'])
                    <span class="mt-1 block text-xs font-medium {{ $step['actor']['key'] === 'author' ? 'text-primary-700' : 'text-gray-600' }}">
                        {{ $step['actor']['label'] }}
                    </span>
                @elseif($step['state'] === 'upcoming')
                    <span class="mt-1 block text-xs text-gray-400">{{ $id ? 'Belum terbuka' : 'Not yet available' }}</span>
                @endif
            </span>
        </li>
    @endforeach
</ol>
