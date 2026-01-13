@php
    $record = $getRecord();
    $fields = [
        'user' => ['label' => '👤 Плательщик', 'icon' => 'heroicon-o-user'],
        'date' => ['label' => '📅 Дата', 'icon' => 'heroicon-o-calendar'],
        'category' => ['label' => '📂 Категория', 'icon' => 'heroicon-o-folder'],
        'supplier' => ['label' => '🏪 Поставщик', 'icon' => 'heroicon-o-building-storefront'],
        'sum' => ['label' => '💰 Сумма', 'icon' => 'heroicon-o-banknotes'],
        'notes' => ['label' => '📝 Примечания', 'icon' => 'heroicon-o-document-text'],
    ];
@endphp

@if($record && $record->expense)
<div class="space-y-4">
    <div class="bg-gradient-to-br from-blue-50 to-indigo-50 dark:from-blue-900/20 dark:to-indigo-900/20 rounded-xl p-6 border border-blue-200 dark:border-blue-800">
        <div class="flex items-center gap-3 mb-4">
            <div class="p-2 bg-blue-100 dark:bg-blue-900/50 rounded-lg">
                <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                </svg>
            </div>
            <h3 class="text-lg font-bold text-blue-900 dark:text-blue-100">Сравнение изменений</h3>
        </div>

        <div class="space-y-3">
            @foreach($fields as $field => $config)
                @php
                    $oldValue = null;
                    $newValue = null;
                    $changed = false;
                    
                    switch ($field) {
                        case 'user':
                            $oldValue = $record->expense?->user?->name;
                            $newValue = $record->requestedUser?->name;
                            $changed = $record->requested_user_id != $record->expense->user_id;
                            break;
                        case 'date':
                            $oldValue = $record->expense?->date?->format('d.m.Y');
                            $newValue = $record->requested_date ? \Carbon\Carbon::parse($record->requested_date)->format('d.m.Y') : null;
                            $changed = $record->requested_date != $record->expense->date;
                            break;
                        case 'category':
                            $oldValue = $record->expense?->category?->name;
                            $newValue = $record->requestedCategory?->name;
                            $changed = $record->requested_category_id != $record->expense->category_id;
                            break;
                        case 'supplier':
                            $oldValue = $record->expense?->supplier?->name;
                            $newValue = $record->requestedSupplier?->name;
                            $changed = $record->requested_supplier_id != $record->expense->supplier_id;
                            break;
                        case 'sum':
                            $oldValue = $record->expense?->sum ? number_format($record->expense->sum, 2) . ' MDL' : null;
                            $newValue = $record->requested_sum ? number_format($record->requested_sum, 2) . ' MDL' : null;
                            $changed = $record->requested_sum != $record->expense->sum;
                            break;
                        case 'notes':
                            $oldValue = $record->expense?->notes ?: 'Не указано';
                            $newValue = $record->requested_notes ?: 'Не указано';
                            $changed = $record->requested_notes != $record->expense->notes;
                            break;
                    }
                @endphp

                @if($changed)
                <div class="bg-white dark:bg-gray-800 rounded-lg p-4 shadow-sm border border-gray-200 dark:border-gray-700">
                    <div class="text-sm font-semibold text-gray-700 dark:text-gray-300 mb-3">
                        {{ $config['label'] }}
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Старое значение -->
                        <div class="space-y-1">
                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                Текущее значение
                            </div>
                            <div class="bg-gray-50 dark:bg-gray-900/50 rounded-lg p-3 border border-gray-200 dark:border-gray-700">
                                <div class="flex items-start gap-2">
                                    <span class="text-red-500 text-lg flex-shrink-0">━</span>
                                    <span class="text-gray-900 dark:text-gray-100 break-words">{{ $oldValue }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Новое значение -->
                        <div class="space-y-1">
                            <div class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wide">
                                Новое значение
                            </div>
                            <div class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-lg p-3 border border-green-300 dark:border-green-700 shadow-sm">
                                <div class="flex items-start gap-2">
                                    <span class="text-green-600 dark:text-green-400 text-lg flex-shrink-0">✓</span>
                                    <span class="text-green-900 dark:text-green-100 font-semibold break-words">{{ $newValue }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                @else
                <div class="bg-gray-50 dark:bg-gray-900/30 rounded-lg p-4 border border-gray-200 dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-semibold text-gray-700 dark:text-gray-300">
                            {{ $config['label'] }}
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="text-gray-600 dark:text-gray-400">{{ $oldValue ?: 'Не указано' }}</span>
                            <span class="text-xs text-gray-500 dark:text-gray-500 italic">(без изменений)</span>
                        </div>
                    </div>
                </div>
                @endif
            @endforeach
        </div>
    </div>
</div>
@elseif($record && $record->action_type === 'create')
<div class="bg-gradient-to-br from-green-50 to-emerald-50 dark:from-green-900/20 dark:to-emerald-900/20 rounded-xl p-6 border border-green-200 dark:border-green-800">
    <div class="flex items-center gap-3 mb-4">
        <div class="p-2 bg-green-100 dark:bg-green-900/50 rounded-lg">
            <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4" />
            </svg>
        </div>
        <h3 class="text-lg font-bold text-green-900 dark:text-green-100">Создание нового расхода</h3>
    </div>
    
    <div class="space-y-2">
        @foreach($fields as $field => $config)
            @php
                $value = null;
                switch ($field) {
                    case 'user':
                        $value = $record->requestedUser?->name;
                        break;
                    case 'date':
                        $value = $record->requested_date ? \Carbon\Carbon::parse($record->requested_date)->format('d.m.Y') : null;
                        break;
                    case 'category':
                        $value = $record->requestedCategory?->name;
                        break;
                    case 'supplier':
                        $value = $record->requestedSupplier?->name;
                        break;
                    case 'sum':
                        $value = $record->requested_sum ? number_format($record->requested_sum, 2) . ' MDL' : null;
                        break;
                    case 'notes':
                        $value = $record->requested_notes ?: 'Не указано';
                        break;
                }
            @endphp
            @if($value)
            <div class="flex items-center justify-between bg-white dark:bg-gray-800 rounded-lg p-3 border border-green-200 dark:border-green-700">
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $config['label'] }}</span>
                <span class="text-sm text-green-700 dark:text-green-300 font-medium">{{ $value }}</span>
            </div>
            @endif
        @endforeach
    </div>
</div>
@elseif($record && $record->action_type === 'delete')
<div class="bg-gradient-to-br from-red-50 to-rose-50 dark:from-red-900/20 dark:to-rose-900/20 rounded-xl p-6 border border-red-200 dark:border-red-800">
    <div class="flex items-center gap-3 mb-4">
        <div class="p-2 bg-red-100 dark:bg-red-900/50 rounded-lg">
            <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
            </svg>
        </div>
        <h3 class="text-lg font-bold text-red-900 dark:text-red-100">Удаление расхода</h3>
    </div>
    
    <div class="space-y-2">
        @foreach($fields as $field => $config)
            @php
                $value = null;
                switch ($field) {
                    case 'user':
                        $value = $record->expense?->user?->name;
                        break;
                    case 'date':
                        $value = $record->expense?->date?->format('d.m.Y');
                        break;
                    case 'category':
                        $value = $record->expense?->category?->name;
                        break;
                    case 'supplier':
                        $value = $record->expense?->supplier?->name;
                        break;
                    case 'sum':
                        $value = $record->expense?->sum ? number_format($record->expense->sum, 2) . ' MDL' : null;
                        break;
                    case 'notes':
                        $value = $record->expense?->notes ?: 'Не указано';
                        break;
                }
            @endphp
            @if($value)
            <div class="flex items-center justify-between bg-white dark:bg-gray-800 rounded-lg p-3 border border-red-200 dark:border-red-700">
                <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">{{ $config['label'] }}</span>
                <span class="text-sm text-red-700 dark:text-red-300 line-through">{{ $value }}</span>
            </div>
            @endif
        @endforeach
    </div>
</div>
@endif
