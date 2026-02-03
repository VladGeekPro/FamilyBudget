@php
$record = $getRecord();

$approvedVotes = $record->getApprovedVotes();
$rejectedVotes = $record->getRejectedVotes();
$pendingUsers = $record->getPendingUsers();

$approvedVotesCount = $approvedVotes->count();
$rejectedVotesCount = $rejectedVotes->count();
$pendingUsersCount = $pendingUsers->count();

@endphp


<div class="space-y-4">

    <!-- Одобрили -->
    @if($approvedVotesCount)
    <div class="space-y-2">
        <h4 class="font-semibold text-sm text-green-700 flex items-center space-x-2">
            <span>✅ Одобрили</span>
            <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-green-500 rounded-full">
                {{ $approvedVotesCount }}
            </span>
        </h4>
        <div class="space-y-1">
            @foreach($approvedVotes as $vote)
            <div class="flex items-center justify-between p-2 bg-green-50 rounded border border-green-200 text-sm">
                <div class="flex items-center space-x-2">
                    @if($vote->user->image)
                    <img src="{{ Storage::url($vote->user->image) }}" alt="{{ $vote->user->name }}" class="w-10 h-10 rounded-full object-cover">
                    @else
                    <div class="w-10 h-10 bg-green-500 rounded-full flex items-center justify-center text-white text-xs font-bold">
                        {{ substr($vote->user->name, 0, 1) }}
                    </div>
                    @endif
                    <span class="font-medium text-gray-900">{{ $vote->user->name }}</span>
                </div>
                <span class="text-xs text-gray-500">{{ $vote->updated_at->format('d.m.Y H:i') }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Отклонили -->
    @if($rejectedVotes->count())
    <div class="space-y-2">
        <h4 class="font-semibold text-sm text-red-700 flex items-center space-x-2">
            <span>❌ Отклонили</span>
            <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-red-500 rounded-full">
                {{ $rejectedVotesCount }}
            </span>
        </h4>
        <div class="space-y-1">
            @foreach($rejectedVotes as $vote)
            <div class="flex items-center justify-between p-2 bg-red-50 rounded border border-red-200 text-sm">
                <div class="flex items-center space-x-2">
                    @if($vote->user->image)
                    <img src="{{ Storage::url($vote->user->image) }}" alt="{{ $vote->user->name }}" class="w-10 h-10 rounded-full object-cover">
                    @else
                    <div class="w-10 h-10 bg-red-500 rounded-full flex items-center justify-center text-white text-xs font-bold">
                        {{ substr($vote->user->name, 0, 1) }}
                    </div>
                    @endif
                    <span class="font-medium text-gray-900">{{ $vote->user->name }}</span>
                </div>
                <span class="text-xs text-gray-500">{{ $vote->updated_at->format('d.m.Y H:i') }}</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Ожидают голосования -->
    @if($pendingUsersCount > 0)
    <div class="space-y-2">
        <h4 class="font-semibold text-sm text-amber-700 flex items-center space-x-2">
            <span>⏳ Ожидают голосования</span>
            <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-amber-500 rounded-full">
                {{ $pendingUsersCount }}
            </span>
        </h4>
        <div class="space-y-1">
            @foreach($pendingUsers as $user)
            <div class="flex items-center justify-between p-2 bg-amber-50 rounded border border-amber-200 text-sm">
                <div class="flex items-center space-x-2">
                    @if($user->image)
                    <img src="{{ Storage::url($user->image) }}" alt="{{ $user->name }}" class="w-10 h-10 rounded-full object-cover">
                    @else
                    <div class="w-10 h-10 bg-amber-500 rounded-full flex items-center justify-center text-white text-xs font-bold">
                        {{ substr($user->name, 0, 1) }}
                    </div>
                    @endif
                    <span class="font-medium text-gray-900">{{ $user->name }}</span>
                </div>
                <span class="text-xs text-amber-600 font-medium">Не голосовал</span>
            </div>
            @endforeach
        </div>
    </div>
    @endif
</div>