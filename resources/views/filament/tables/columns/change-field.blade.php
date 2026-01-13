@php
    $data = $getState();
@endphp

@if($data['action_type'] === 'delete')
    <span class="inline-flex items-center pr-2.5 py-1 rounded-lg bg-gradient-to-br from-red-50 to-red-100 dark:from-red-900/30 dark:to-red-900/20 text-red-700 dark:text-red-400 text-sm font-semibold shadow-sm">
        <span class="mr-1">❌</span>
        {{ $data['old'] }}
    </span>
@elseif($data['action_type'] === 'create')
    <span class="inline-flex items-center pr-2.5 py-1 rounded-lg bg-gradient-to-br from-green-50 to-green-100 dark:from-green-900/30 dark:to-green-900/20 text-green-700 dark:text-green-400 text-sm font-semibold shadow-sm">
        <span class="mr-1">✅</span>
        {{ $data['new'] }}
    </span>
@elseif($data['changed'])
    <div class="flex items-center gap-2 flex-wrap">
        <span class="inline-block pr-2 py-0.5 rounded bg-gray-100 dark:bg-gray-800 text-gray-600 dark:text-gray-400 text-xs line-through opacity-75">
            {{ $data['old'] }}
        </span>
        <span class="text-amber-500 dark:text-amber-400 font-bold text-base">→</span>
        <span class="inline-flex items-center pr-2.5 py-1 rounded-lg bg-gradient-to-br from-blue-50 to-blue-100 dark:from-blue-900/30 dark:to-blue-900/20 text-blue-700 dark:text-blue-400 text-sm font-bold shadow-sm ring-1 ring-blue-600/10 dark:ring-blue-400/20">
            {{ $data['new'] }}
        </span>
    </div>
@else
    <span class="inline-flex items-center pr-2.5 py-1 rounded-lg bg-gradient-to-br from-gray-50 to-gray-100 dark:from-gray-900/30 dark:to-gray-900/20 text-gray-700 dark:text-gray-400 text-sm font-semibold shadow-sm">
        {{ $data['old'] }}
    </span>
@endif
