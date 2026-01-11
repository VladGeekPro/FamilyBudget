<div class="space-y-4">
    <h3 class="text-lg font-semibold">Результаты голосования</h3>
    
    @if($votes->count() > 0)
        <div class="space-y-2">
            <h4 class="font-medium text-green-700">✅ Одобрили ({{ $votes->where('vote', 'approved')->count() }})</h4>
            @foreach($votes->where('vote', 'approved') as $vote)
                <div class="flex items-center justify-between p-3 bg-green-50 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center text-white text-sm font-semibold">
                            {{ substr($vote->user->name, 0, 1) }}
                        </div>
                        <span class="font-medium">{{ $vote->user->name }}</span>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-gray-500">{{ $vote->updated_at->format('d.m.Y H:i') }}</div>
                        @if($vote->notes)
                            <div class="text-sm text-gray-700 mt-1">{{ $vote->notes }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        <div class="space-y-2">
            <h4 class="font-medium text-red-700">❌ Отклонили ({{ $votes->where('vote', 'rejected')->count() }})</h4>
            @foreach($votes->where('vote', 'rejected') as $vote)
                <div class="flex items-center justify-between p-3 bg-red-50 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 bg-red-500 rounded-full flex items-center justify-center text-white text-sm font-semibold">
                            {{ substr($vote->user->name, 0, 1) }}
                        </div>
                        <span class="font-medium">{{ $vote->user->name }}</span>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-gray-500">{{ $vote->updated_at->format('d.m.Y H:i') }}</div>
                        @if($vote->notes)
                            <div class="text-sm text-gray-700 mt-1">{{ $vote->notes }}</div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if($pendingUsers->count() > 0)
        <div class="space-y-2">
            <h4 class="font-medium text-yellow-700">⏳ Ожидают голосования ({{ $pendingUsers->count() }})</h4>
            <div class="grid grid-cols-2 gap-2">
                @foreach($pendingUsers as $user)
                    <div class="flex items-center space-x-3 p-3 bg-yellow-50 rounded-lg">
                        <div class="w-8 h-8 bg-yellow-500 rounded-full flex items-center justify-center text-white text-sm font-semibold">
                            {{ substr($user->name, 0, 1) }}
                        </div>
                        <span class="font-medium">{{ $user->name }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if($votes->count() === 0 && $pendingUsers->count() === 0)
        <div class="text-center py-8 text-gray-500">
            <p>Голосов пока нет</p>
        </div>
    @endif
</div>