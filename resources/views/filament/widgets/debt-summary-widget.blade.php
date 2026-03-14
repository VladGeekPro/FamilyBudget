@php
    use App\Models\User;

    $fmt = fn(float $v): string => number_format($v, 2, ',', ' ') . ' MDL';

    /* gender helper */
    $owed = function(?string $name): string {
        $last = mb_strtolower(mb_substr((string) $name, -1, 1, 'UTF-8'), 'UTF-8');
        return in_array($last, ['а', 'я'], true) ? 'должна' : 'должен';
    };

    /* avatar helper */
    $av = function(object $u): string {
        if ($u->user->image) {
            return '<img src="' . asset('storage/' . e($u->user->image)) . '"
                         alt="' . e($u->user->name) . '"
                         class="h-14 w-14 rounded-full object-cover ring-4 ring-white/80 dark:ring-white/20 shadow-lg" />';
        }
        $icon = e(\App\Models\User::getIcon($u->user->email));
        return '<span class="inline-flex h-14 w-14 items-center justify-center rounded-full bg-gray-100 dark:bg-gray-700 text-3xl shadow">' . $icon . '</span>';
    };
@endphp

<x-filament-widgets::widget>
    <div x-data="{ isCollapsed: false }" class="rounded-2xl overflow-hidden shadow-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900">

        {{-- ═══════════ HEADER ═══════════ --}}
        <div class="px-6 py-4 bg-gradient-to-br from-violet-500 via-violet-600 to-purple-700">
            <div
                x-data="{ isRightWrapped: false, syncRightWrap() { const row = this.$refs.headerRow; const right = this.$refs.headerRight; if (!row || !right) return; this.isRightWrapped = false; this.$nextTick(() => { this.isRightWrapped = right.offsetTop > row.offsetTop + 1; }); } }"
                x-init="$nextTick(() => { syncRightWrap(); const observer = new ResizeObserver(() => syncRightWrap()); observer.observe($refs.headerRow); observer.observe($refs.headerRight); window.addEventListener('resize', syncRightWrap); })"
                x-ref="headerRow"
                class="flex flex-wrap gap-x-4 gap-y-3"
            >
                {{-- Icon: always first --}}
                <div class="order-2 aspect-square min-h-[40px] rounded-xl bg-white/20 backdrop-blur-md flex items-center justify-center shadow-lg">
                    <x-heroicon-o-scale class="w-6 h-6 text-white" />
                </div>

                {{-- Title & Subtitle: on mobile goes to new line, on desktop between icon and actions --}}
                <div
                    :class="isRightWrapped ? 'order-3' : 'order-4 sm:order-3'"
                    class="sm:flex-1 self-center"
                >
                    <h2 class="text-white font-bold text-xl leading-tight">Баланс долгов</h2>
                    <p class="text-violet-100 text-sm mt-1 capitalize">{{ $monthLabel }}</p>
                </div>

                {{-- Progress Indicator: stay with icon on first line when wrapped --}}
                <div
                    x-ref="headerRight"
                    :class="isRightWrapped ? 'order-1 ml-0 basis-full' : 'order-3 ml-auto sm:order-4'"
                    class="flex items-stretch rounded-xl border border-white/15 bg-white/10 backdrop-blur-md shadow-md overflow-hidden"
                >
                    <div class="flex items-center gap-2 px-2 py-1">
                        <div class="text-right">
                            <div class="text-white text-[11px] font-semibold uppercase tracking-wider opacity-90">Прогресс</div>
                            <div class="text-violet-100 text-sm font-bold">{{ $daysElapsed }}/{{ $daysInMonth }}</div>
                        </div>
                        <div class="relative w-10 h-10">
                            <svg class="w-10 h-10 -rotate-90 drop-shadow" viewBox="0 0 36 36">
                                <circle cx="18" cy="18" r="15.5" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="2" />
                                <circle cx="18" cy="18" r="15.5" fill="none"
                                    stroke="white" stroke-width="2.5"
                                    stroke-dasharray="{{ round($monthProgress * 97.4 / 100, 1) }}, 97.4"
                                    stroke-linecap="round" 
                                    class="transition-all duration-500" />
                            </svg>
                            <span class="absolute inset-0 flex items-center justify-center text-white text-[10px] font-extrabold">{{ $monthProgress }}%</span>
                        </div>
                    </div>

                    <button
                        type="button"
                        x-on:click.stop="isCollapsed = !isCollapsed"
                        class="inline-flex items-center justify-center px-3 text-white border-l border-white/20 hover:bg-white/20 hover:border-white/30 transition-all duration-200"
                        aria-label="Свернуть виджет"
                    >
                        <svg class="w-5 h-5 transition-transform duration-300" :class="isCollapsed && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                </div>
            </div>
        </div>

        <div
            x-show="!isCollapsed"
            x-transition:enter="transition-all ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition-all ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="overflow-hidden"
        >

        @if($noData)
            <div class="px-6 py-12 text-center text-gray-400 dark:text-gray-500">
                <x-heroicon-o-users class="w-12 h-12 mx-auto mb-3 opacity-40" />
                <p class="font-medium">Недостаточно данных</p>
                <p class="text-sm mt-1">Требуется минимум 2 пользователя</p>
            </div>
        @else

            {{-- ═══════════ MAIN SECTION ═══════════ --}}
            <div class="px-6 py-5">

                {{-- User balance cards --}}
                <div class="grid grid-cols-1 lg:grid-cols-[1fr_auto_1fr] gap-4 items-stretch">

                    @foreach($userTotals->take(2) as $index => $ut)
                        @php
                            $share = $totalSpent > 0 ? round($ut->total_sum / $totalSpent * 100, 1) : 0;
                            $isDebtor = !$isSettled && $finalDebtor && $ut->user_id === $finalDebtor->user_id;
                            $isCreditor = !$isSettled && $finalCreditor && $ut->user_id === $finalCreditor->user_id;
                            $borderClass = $isDebtor
                                ? 'border-red-200 dark:border-red-700/50 bg-red-50/60 dark:bg-red-900/10'
                                : ($isCreditor ? 'border-green-200 dark:border-green-700/50 bg-green-50/60 dark:bg-green-900/10'
                                               : 'border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/60');
                        @endphp
                        <div class="rounded-xl border-2 {{ $borderClass }} p-4 flex flex-col gap-3 relative">

                            {{-- Badge --}}
                            @if($isDebtor)
                                <span class="absolute top-3 right-3 text-xs font-semibold bg-red-100 dark:bg-red-800/40 text-red-600 dark:text-red-400 rounded-full px-2 py-0.5">Должник</span>
                            @elseif($isCreditor)
                                <span class="absolute top-3 right-3 text-xs font-semibold bg-green-100 dark:bg-green-800/40 text-green-600 dark:text-green-400 rounded-full px-2 py-0.5">Кредитор</span>
                            @endif

                            <div class="flex items-center gap-3">
                                {!! $av($ut) !!}
                                <div>
                                    <div class="font-bold text-gray-900 dark:text-white">{{ $ut->user->name }}</div>
                                    <div class="text-xs text-gray-500 dark:text-gray-400">{{ $ut->user->email }}</div>
                                </div>
                            </div>

                            <div>
                                <div class="text-2xl font-extrabold {{ $isDebtor ? 'text-red-600 dark:text-red-400' : ($isCreditor ? 'text-green-600 dark:text-green-400' : 'text-gray-800 dark:text-white') }}">
                                    {{ $fmt($ut->total_sum) }}
                                </div>
                                <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">{{ $ut->tx_count }} {{ trans_choice('resources.terms.expense_count', $ut->tx_count) }}</div>
                            </div>

                            {{-- Share progress bar --}}
                            <div>
                                <div class="flex justify-between text-xs text-gray-500 dark:text-gray-400 mb-1">
                                    <span>{{ __('resources.widgets.debt_summary.expense_share') }}</span>
                                    <span class="font-semibold text-gray-700 dark:text-gray-200">{{ $share }}%</span>
                                </div>
                                <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-2">
                                    <div class="h-2 rounded-full transition-all duration-700 {{ $isDebtor ? 'bg-red-500' : ($isCreditor ? 'bg-green-500' : 'bg-violet-500') }}"
                                         style="width: {{ $share }}%"></div>
                                </div>
                            </div>
                        </div>

                        {{-- Arrow in the middle (desktop) --}}
                        @if($index === 0)
                            <div class="hidden lg:flex flex-col items-center justify-center gap-2 px-2">
                                @if($isSettled)
                                    <div class="w-14 h-14 rounded-full bg-green-100 dark:bg-green-900/40 flex items-center justify-center shadow-md">
                                        <x-heroicon-m-check-badge class="w-8 h-8 text-green-500" />
                                    </div>
                                    <span class="text-xs font-semibold text-green-600 dark:text-green-400 text-center">Счёт<br>выровнен</span>
                                @else
                                    <div class="text-xs text-gray-400 dark:text-gray-500 font-medium text-center leading-tight">{{ $owed($finalDebtor?->user->name) }}</div>
                                    <div class="flex items-center gap-1">
                                        <div class="h-px w-4 bg-gray-300 dark:bg-gray-600 rounded-full"></div>
                                        <div class="w-14 h-14 rounded-full bg-gradient-to-br from-red-500 to-orange-500 shadow-lg flex items-center justify-center">
                                            <x-heroicon-m-banknotes class="w-7 h-7 text-white" />
                                        </div>
                                        <span class="w-5 inline-flex items-center justify-center text-gray-400 dark:text-gray-500 text-lg leading-none">→</span>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-lg font-extrabold text-gray-900 dark:text-white leading-none">{{ $fmt($finalDifference) }}</div>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">к переводу</div>
                                    </div>
                                @endif
                            </div>
                        @endif
                    @endforeach
                </div>

                {{-- Mobile debt indicator --}}
                <div class="lg:hidden mt-4 rounded-xl {{ $isSettled ? 'bg-green-50 dark:bg-green-900/20 border-green-200 dark:border-green-700' : 'bg-red-50 dark:bg-red-900/20 border-red-200 dark:border-red-700' }} border p-4 text-center">
                    @if($isSettled)
                        <x-heroicon-m-check-badge class="w-8 h-8 text-green-500 mx-auto mb-1" />
                        <div class="font-bold text-green-600 dark:text-green-400">Счёт выровнен</div>
                    @else
                        <x-heroicon-m-banknotes class="w-8 h-8 text-red-500 mx-auto mb-1" />
                        <div class="text-sm text-gray-500 dark:text-gray-400">{{ $finalDebtor?->user->name }} {{ $owed($finalDebtor?->user->name) }} {{ $finalCreditor?->user->name }}</div>
                        <div class="text-2xl font-extrabold text-red-600 dark:text-red-400">{{ $fmt($finalDifference) }}</div>
                    @endif
                </div>

                {{-- ═══════════ CALCULATION BREAKDOWN ═══════════ --}}
                <div class="mt-4 rounded-xl bg-gray-50 dark:bg-gray-800/60 border border-gray-200 dark:border-gray-700 p-4">
                    <div class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wide mb-3 flex items-center gap-1.5">
                        <x-heroicon-m-calculator class="w-3.5 h-3.5" />
                        Расчёт
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-sm">
                        <div class="flex flex-col gap-0.5">
                            <span class="text-xs text-gray-400 dark:text-gray-500">Всего потрачено</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ $fmt($totalSpent) }}</span>
                        </div>
                        <div class="flex flex-col gap-0.5">
                            @php $baseDebtor = $userTotals->first(); $baseCreditor = $userTotals->last(); @endphp
                            <span class="text-xs text-gray-400 dark:text-gray-500">{{ $baseDebtor?->user->name }} {{ $owed($baseDebtor?->user->name) }} {{ $baseCreditor?->user->name }}</span>
                            <span class="font-bold text-gray-900 dark:text-white">{{ $fmt($baseDifference) }}</span>
                        </div>
                        @if($overpayment)
                            <div class="flex flex-col gap-0.5">
                                <span class="text-xs text-gray-400 dark:text-gray-500">Переплата ({{ $overpayment->user->name }})</span>
                                <span class="font-bold {{ $isSettled ? 'text-green-600 dark:text-green-400' : 'text-orange-500 dark:text-orange-400' }}">{{ number_format($overpayment->sum, 2, ',', ' ') }} MDL</span>
                            </div>
                        @else
                            <div class="flex flex-col gap-0.5">
                                <span class="text-xs text-gray-400 dark:text-gray-500">Переплата</span>
                                <span class="text-gray-400 dark:text-gray-500 italic">Нет</span>
                            </div>
                        @endif
                    </div>

                    @if($overpaymentNote)
                        <div class="mt-3 flex items-start gap-2 text-xs text-orange-700 dark:text-orange-300 bg-orange-50 dark:bg-orange-900/20 rounded-lg px-3 py-2 border border-orange-200 dark:border-orange-700/50">
                            <x-heroicon-m-information-circle class="w-4 h-4 flex-shrink-0 mt-0.5" />
                            <span>{{ $overpaymentNote }}</span>
                        </div>
                    @endif
                </div>

                {{-- ═══════════ RESULT BANNER ═══════════ --}}
                <div class="mt-4 rounded-xl {{ $isSettled ? 'bg-gradient-to-r from-green-500 to-emerald-600' : 'bg-gradient-to-r from-red-500 to-rose-600' }} p-4 text-white">
                    @if($isSettled)
                        <div class="flex items-center gap-4">
                            <div class="flex-shrink-0 w-12 h-12 rounded-full bg-white/20 flex items-center justify-center">
                                <x-heroicon-m-check-badge class="w-7 h-7" />
                            </div>
                            <div>
                                <div class="font-bold text-lg leading-tight">Счёт выровнен!</div>
                                <div class="text-green-100 text-sm">Никто никому не должен в {{ $monthLabel }}</div>
                            </div>
                        </div>
                    @else
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-3">
                            {{-- Row 1: Icon + Amount --}}
                            <div class="flex-shrink-0 w-12 h-12 rounded-full bg-white/20 flex items-center justify-center order-1">
                                <x-heroicon-m-arrow-right class="w-7 h-7" />
                            </div>
                            <div class="flex-shrink-0 text-right order-2 ml-auto">
                                <div class="text-2xl font-extrabold">{{ $fmt($finalDifference) }}</div>
                                <div class="text-red-200 text-xs">к переводу</div>
                            </div>

                            {{-- Row 2: Full width debt info --}}
                            <div class="w-full order-3">
                                <div class="font-bold text-lg leading-tight">
                                    {{ $finalDebtor?->user->name }}
                                    <span class="font-normal text-red-100 mx-1">{{ $owed($finalDebtor?->user->name) }}</span>
                                    {{ $finalCreditor?->user->name }}
                                </div>
                                <div class="text-red-100 text-sm mt-0.5">
                                    По итогам {{ $monthLabel }} • осталось {{ $daysRemaining }} дн. в месяце
                                </div>
                            </div>
                        </div>
                    @endif
                </div>

            </div>
        @endif
        </div>
    </div>
</x-filament-widgets::widget>
