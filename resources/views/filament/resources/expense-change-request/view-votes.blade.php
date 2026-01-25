<div class="space-y-6">
    <h3 class="text-lg font-semibold text-gray-900">Результаты голосования</h3>
    
    @if($votes->count() > 0)
        <!-- Одобрили -->
        <div class="space-y-3">
            <div class="flex items-center space-x-2">
                <h4 class="font-semibold text-green-700">✅ Одобрили</h4>
                <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-green-500 rounded-full">
                    {{ $votes->where('vote', 'approved')->count() }}
                </span>
            </div>
            <div class="space-y-2">
                @foreach($votes->where('vote', 'approved') as $vote)
                    <div class="p-4 border border-green-200 bg-green-50 rounded-lg hover:border-green-300 transition">
                        <div class="flex items-start space-x-3">
                            @if($vote->user->image)
                                <img src="{{ Storage::url($vote->user->image) }}" alt="{{ $vote->user->name }}" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                            @else
                                <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
                                    {{ substr($vote->user->name, 0, 1) }}
                                </div>
                            @endif
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <p class="font-semibold text-gray-900">{{ $vote->user->name }}</p>
                                    <span class="text-xs text-gray-500 whitespace-nowrap ml-2">{{ $vote->updated_at->format('d.m.Y H:i') }}</span>
                                </div>
                                <p class="text-sm text-green-700 font-medium mt-1">Одобрил изменения</p>
                                @if($vote->notes)
                                    <p class="text-sm text-gray-700 mt-2 p-2 bg-white rounded border border-green-100">
                                        <span class="font-medium">Комментарий:</span> {{ $vote->notes }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Отклонили -->
        <div class="space-y-3">
            <div class="flex items-center space-x-2">
                <h4 class="font-semibold text-red-700">❌ Отклонили</h4>
                <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-red-500 rounded-full">
                    {{ $votes->where('vote', 'rejected')->count() }}
                </span>
            </div>
            <div class="space-y-2">
                @foreach($votes->where('vote', 'rejected') as $vote)
                    <div class="p-4 border border-red-200 bg-red-50 rounded-lg hover:border-red-300 transition">
                        <div class="flex items-start space-x-3">
                            @if($vote->user->image)
                                <img src="{{ Storage::url($vote->user->image) }}" alt="{{ $vote->user->name }}" class="w-10 h-10 rounded-full object-cover flex-shrink-0">
                            @else
                                <div class="w-10 h-10 bg-red-500 rounded-full flex items-center justify-center text-white text-sm font-bold flex-shrink-0">
                                    {{ substr($vote->user->name, 0, 1) }}
                                </div>
                            @endif
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center justify-between">
                                    <p class="font-semibold text-gray-900">{{ $vote->user->name }}</p>
                                    <span class="text-xs text-gray-500 whitespace-nowrap ml-2">{{ $vote->updated_at->format('d.m.Y H:i') }}</span>
                                </div>
                                <p class="text-sm text-red-700 font-medium mt-1">Отклонил изменения</p>
                                @if($vote->notes)
                                    <p class="text-sm text-gray-700 mt-2 p-2 bg-white rounded border border-red-100">
                                        <span class="font-medium">Комментарий:</span> {{ $vote->notes }}
                                    </p>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Ожидают голосования -->
    @if($pendingUsers->count() > 0)
        <div class="space-y-3">
            <div class="flex items-center space-x-2">
                <h4 class="font-semibold text-amber-700">⏳ Ожидают голосования</h4>
                <span class="inline-flex items-center justify-center w-6 h-6 text-xs font-bold text-white bg-amber-500 rounded-full">
                    {{ $pendingUsers->count() }}
                </span>
            </div>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                @foreach($pendingUsers as $user)
                    <div class="flex items-center space-x-3 p-3 border border-amber-200 bg-amber-50 rounded-lg hover:border-amber-300 transition">
                        @if($user->image)
                            <img src="{{ Storage::url($user->image) }}" alt="{{ $user->name }}" class="w-8 h-8 rounded-full object-cover flex-shrink-0">
                        @else
                            <div class="w-8 h-8 bg-amber-500 rounded-full flex items-center justify-center text-white text-xs font-bold flex-shrink-0">
                                {{ substr($user->name, 0, 1) }}
                            </div>
                        @endif
                        <span class="font-medium text-gray-900 text-sm">{{ $user->name }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Пусто -->
    @if($votes->count() === 0 && $pendingUsers->count() === 0)
        <div class="text-center py-12 text-gray-500">
            <svg class="w-16 h-16 mx-auto mb-4 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <p class="text-lg font-medium">Голосов пока нет</p>
        </div>
    @endif
</div>