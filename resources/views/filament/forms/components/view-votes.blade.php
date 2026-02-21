@php
$record = $getRecord();

$approvedVotes = $record->getApprovedVotes();
$rejectedVotes = $record->getRejectedVotes();
$pendingUsers = $record->getPendingUsers();

$approvedVotesCount = $record->getApprovedVotesCount();
$rejectedVotesCount = $record->getRejectedVotesCount();
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
        <div class="space-y-3">
            @foreach($approvedVotes as $vote)
            <article class="rounded-lg border border-emerald-200 bg-gradient-to-br from-emerald-50 to-white shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-emerald-100 px-4 py-3">
                    <div class="flex min-w-0 items-center gap-3">
                        @if($vote->user->image)
                        <img
                            src="{{ Storage::url($vote->user->image) }}"
                            alt="{{ $vote->user->name }}"
                            class="h-10 w-10 rounded-full object-cover ring-2 ring-white shadow-sm">
                        @else
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-emerald-600 text-sm font-semibold text-white shadow-sm">
                            {{ mb_substr($vote->user->name, 0, 1) }}
                        </div>
                        @endif

                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $vote->user->name }}</p>
                        </div>
                    </div>

                    <span class="flex min-w-[110px] items-center justify-center rounded-full border border-emerald-300 bg-emerald-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-emerald-800">
                        {{ $vote->updated_at->format('d.m.Y H:i') }}
                    </span>
                </div>

                @if($vote->notes)
                <div class="px-4 py-3">
                    <p class="text-sm leading-relaxed text-gray-700">
                        {{ $vote->notes }}
                    </p>
                </div>
                @endif
            </article>
            @endforeach
        </div>

    </div>
    @endif

    <!-- Отклонили -->
    @if($rejectedVotesCount)
    <div class="space-y-2">
        <h4 class="font-semibold text-sm text-rose-700 flex items-center space-x-2">
            <span>❌ Отклонили</span>
            <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-rose-500 rounded-full">
                {{ $rejectedVotesCount }}
            </span>
        </h4>

        <div class="space-y-3">
            @foreach($rejectedVotes as $vote)
            <article class="rounded-lg border border-rose-200 bg-gradient-to-br from-rose-50 to-white shadow-sm">
                <div class="flex items-center justify-between gap-3 border-b border-rose-100 px-4 py-3">
                    <div class="flex min-w-0 items-center gap-3">
                        @if($vote->user->image)
                        <img
                            src="{{ Storage::url($vote->user->image) }}"
                            alt="{{ $vote->user->name }}"
                            class="h-10 w-10 rounded-full object-cover ring-2 ring-white shadow-sm">
                        @else
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-rose-600 text-sm font-semibold text-white shadow-sm">
                            {{ mb_substr($vote->user->name, 0, 1) }}
                        </div>
                        @endif

                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $vote->user->name }}</p>
                        </div>
                    </div>

                    <span class="flex min-w-[110px] items-center justify-center rounded-full border border-rose-300 bg-rose-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-wide text-rose-800">
                        {{ $vote->updated_at->format('d.m.Y H:i') }}
                    </span>
                </div>

                @if($vote->notes)
                <div class="px-4 py-3">
                    <p class="text-sm leading-relaxed text-gray-700 whitespace-pre-line">
                        {{ $vote->notes }}
                    </p>
                </div>
                @endif
            </article>
            @endforeach
        </div>
    </div>
    @endif

    <!-- Ожидают голосования -->
    @if($pendingUsersCount)
    <div class="space-y-2">
        <h4 class="font-semibold text-sm text-amber-700 flex items-center space-x-2">
            <span>⏳ Ожидают голосования</span>
            <span class="inline-flex items-center justify-center w-5 h-5 text-xs font-bold text-white bg-amber-500 rounded-full">
                {{ $pendingUsersCount }}
            </span>
        </h4>

        <div class="space-y-3">
            @foreach($pendingUsers as $user)
            <article class="rounded-lg border border-amber-200 bg-gradient-to-br from-amber-50 to-white shadow-sm">
                <div class="flex items-center justify-between gap-3 px-4 py-3">
                    <div class="flex min-w-0 items-center gap-3">
                        @if($user->image)
                        <img
                            src="{{ Storage::url($user->image) }}"
                            alt="{{ $user->name }}"
                            class="h-10 w-10 rounded-full object-cover ring-2 ring-white shadow-sm">
                        @else
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-amber-600 text-sm font-semibold text-white shadow-sm">
                            {{ mb_substr($user->name, 0, 1) }}
                        </div>
                        @endif

                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900">{{ $user->name }}</p>
                        </div>
                    </div>

                    <span class="flex min-w-[110px] items-center justify-center rounded-full border border-amber-300 bg-amber-100 px-2.5 py-1 text-[11px] font-semibold tracking-wide text-amber-800">
                        Не голосовал
                    </span>
                </div>
            </article>
            @endforeach
        </div>
    </div>
    @endif


</div>