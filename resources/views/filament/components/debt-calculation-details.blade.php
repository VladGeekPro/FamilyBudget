@php
use App\Models\Expense;
use App\Models\Overpayment;
use Carbon\Carbon;

$debt = $getRecord();
$date = $debt->date;

$isEditPage = $this instanceof \Filament\Resources\Pages\EditRecord;

// Получаем затраты пользователей за месяц
$expenses = Expense::selectRaw('user_id, SUM(sum) as total_sum')
->whereMonth('date', $date->month)
->whereYear('date', $date->year)
->groupBy('user_id')
->with('user')
->get();

// Если нет затрат вообще или только один пользователь, добавляем недостающих пользователей с нулевыми затратами
$allUsers = \App\Models\User::all();
$existingUserIds = $expenses->pluck('user_id')->toArray();

foreach ($allUsers as $user) {
    if (!in_array($user->id, $existingUserIds)) {
        $fakeExpense = (object) [
            'user_id' => $user->id,
            'total_sum' => 0,
            'user' => $user
        ];
        $expenses->push($fakeExpense);
    }
}

// Ограничиваем до двух пользователей (берем первых двух)
$expenses = $expenses->take(2);

// Определяем кто больше/меньше потратил
    $sorted=$expenses->sortBy('total_sum');
    $minUser = $sorted->first();
    $maxUser = $sorted->last();

    // Расчёт БЕЗ переплаты (базовый)
    $baseDifference = ($maxUser->total_sum - $minUser->total_sum) / 2;

    // Получаем переплату
    $overpayment = Overpayment::where('created_at', '<=', $date)
        ->orderByDesc('created_at')
        ->first();

        // Расчёт С учетом переплаты
        $finalDifference = $baseDifference;
        $finalMinUser = $minUser;
        $finalMaxUser = $maxUser;
        $adjustmentNote = '';

        if ($overpayment) {
        $overpaymentSum = $overpayment->sum;

        if ($minUser->user_id === $overpayment->user_id) {
        // Должник - добавляем к его долгу
        $finalDifference = $baseDifference + $overpaymentSum;
        $adjustmentNote = "К долгу " . number_format($baseDifference, 2, ',', ' ') . " MDL пользователя {$minUser->user->name} добавлена обговоренная сумма переплаты " . number_format($overpaymentSum, 2, ',', ' ') . " MDL";
        } else {
        // Должник переплатил - вычитаем из долга
        $finalDifference = $baseDifference - $overpaymentSum;

        if ($finalDifference < 0) {
            // Переплата превышает долг - меняем направление
            $finalMinUser=$maxUser;
            $finalMaxUser=$minUser;
            $finalDifference=abs($finalDifference);
            $adjustmentNote="{$overpayment->user->name} должен переплатить по договорённости " . number_format($overpaymentSum, 2, ',' , ' ' ) . " MDL — эта сумма превышает долг {$minUser->user->name} (" . number_format($baseDifference, 2, ',' , ' ' ) . " MDL), поэтому теперь {$finalMinUser->user->name} должен(на) {$finalMaxUser->user->name}" ;
            }
            else {
            $adjustmentNote="Переплата " . number_format($overpaymentSum, 2, ',' , ' ' ) . " MDL пользователя {$overpayment->user->name} вычтена из его долга, " . (abs($finalDifference) < 0.01 ? "никто никому не должен" : "должником остаётся {$minUser->user->name}" );
            }
            }
            }

            // Проверка нулевого долга (с учетом погрешности float)
            $isDebtZero=abs($finalDifference) < 0.01;

            // Форматирование для вывода
            $minName=$minUser->user->name ?? 'Unknown';
            $maxName = $maxUser->user->name ?? 'Unknown';
            $minIcon = $minUser->user->image ?? null;
            $maxIcon = $maxUser->user->image ?? null;
            $finalMinName = $finalMinUser->user->name ?? 'Unknown';
            $finalMaxName = $finalMaxUser->user->name ?? 'Unknown';

            $minSum = number_format($minUser->total_sum, 2, ',', ' ');
            $maxSum = number_format($maxUser->total_sum, 2, ',', ' ');
            $baseDiffFormatted = number_format($baseDifference, 2, ',', ' ');
            $finalDiffFormatted = number_format($finalDifference, 2, ',', ' ');
            @endphp

            <div class="w-full">
                <details {{ $isEditPage ? '' : 'open' }} class="bg-gradient-to-br from-white to-gray-50 dark:from-gray-900 dark:to-gray-800 rounded-lg border-2 border-gray-200 dark:border-gray-700 transition-all duration-300 overflow-hidden">
                    <summary class="text-gray-900 dark:text-white text-sm sm:text-base cursor-pointer px-3 py-2 bg-gradient-to-l from-gray-50 to-white dark:from-gray-800 dark:to-gray-900 hover:from-gray-100 hover:to-gray-50 dark:hover:from-gray-700 dark:hover:to-gray-800 transition-all duration-200 flex items-center gap-2 sm:gap-3 active:scale-[0.99]">
                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center shadow-md flex-shrink-0">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                            </svg>
                        </div>
                        <span class="flex-1 text-left">Расчёт за {{ $date->translatedFormat('F Y') }}</span>
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-gray-400 transition-transform duration-300 group-open:rotate-180 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </summary>

                    <div class="p-3 space-y-3 sm:space-y-5">
                        <!-- Основной расчёт -->
                        <div class="relative bg-gradient-to-br from-amber-50 via-orange-50 to-yellow-50 dark:from-amber-950/30 dark:via-orange-950/20 dark:to-yellow-950/30 rounded-lg p-3 border-2 border-amber-200/50 dark:border-amber-800/50 shadow-inner overflow-hidden">
                            <!-- Декоративный фон -->
                            <div class="absolute inset-0 bg-gradient-to-br from-transparent via-white/40 to-transparent dark:via-white/5 pointer-events-none"></div>

                            <div class="relative z-10">
                                <div class="flex items-center gap-2 sm:gap-3 mb-3 sm:mb-5">
                                    <div class="w-9 h-9 sm:w-11 sm:h-11 rounded-lg bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center shadow-lg">
                                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                        </svg>
                                    </div>
                                    <h3 class="font-bold text-gray-900 dark:text-white text-base sm:text-lg">Расчёт долга</h3>
                                </div>

                                <div class="space-y-2 sm:space-y-3">
                                    <!-- Затраты пользователей -->
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3">
                                        <div class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-sm rounded-lg p-3 border border-gray-200/50 dark:border-gray-700/50 shadow-sm hover:shadow-md transition-shadow duration-200">
                                            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mb-1">Потратил(а)</p>
                                            <div class="flex gap-3">
                                                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                                    @if($minIcon)
                                                    <img src="{{ asset('storage/' . $minIcon) }}" alt="{{ $minName }}" class="w-full h-full object-cover">
                                                    @else
                                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    @endif
                                                </div>
                                                <p class="font-bold text-gray-900 dark:text-white text-sm sm:text-base truncate">{{ $minName }}</p>
                                            </div>
                                            <p class="font-bold text-lg sm:text-2xl text-gray-900 dark:text-white mt-1 sm:mt-2">{{ $minSum }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">MDL</p>

                                        </div>

                                        <div class="bg-white/70 dark:bg-gray-800/40 backdrop-blur-sm rounded-lg p-3 border border-gray-200/50 dark:border-gray-700/50 shadow-sm hover:shadow-md transition-shadow duration-200">
                                            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400 mb-1">Потратил(а)</p>
                                            <div class="flex gap-3">
                                                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                                    @if($maxIcon)
                                                    <img src="{{ asset('storage/' . $maxIcon) }}" alt="{{ $maxName }}" class="w-full h-full object-cover">
                                                    @else
                                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    @endif
                                                </div>
                                                <p class="font-bold text-gray-900 dark:text-white text-sm sm:text-base truncate">{{ $maxName }}</p>
                                            </div>
                                            <p class="font-bold text-lg sm:text-2xl text-gray-900 dark:text-white mt-1 sm:mt-2">{{ $maxSum }}</p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">MDL</p>
                                        </div>
                                    </div>

                                    <!-- Разделитель -->
                                    <div class="flex items-center gap-2 sm:gap-3 py-2 sm:py-3">
                                        <div class="flex-1 h-px bg-gradient-to-r from-transparent via-amber-300 dark:via-amber-700 to-transparent"></div>
                                        <div class="px-5 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm rounded-lg border border-amber-200 dark:border-amber-800">
                                            <svg class="w-3 h-3 sm:w-4 sm:h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 12h14"></path>
                                            </svg>
                                        </div>
                                        <div class="flex-1 h-px bg-gradient-to-r from-transparent via-amber-300 dark:via-amber-700 to-transparent"></div>
                                    </div>

                                    <!-- Расчёт разницы -->
                                    <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-blue-950/30 dark:to-indigo-950/30 rounded-lg p-3 border border-blue-200/50 dark:border-blue-800/50">
                                        <div class="space-y-2">
                                            <div class="flex justify-between items-center text-xs sm:text-sm">
                                                <span class="text-gray-600 dark:text-gray-400">Разница расходов</span>
                                                <span class="font-mono text-gray-800 dark:text-gray-200 text-[10px] sm:text-xs">{{ $maxName }} - {{ $minName }}</span>
                                            </div>
                                            <div class="flex justify-between items-center">
                                                <span class="text-xs sm:text-sm font-semibold text-gray-700 dark:text-gray-300">Результат:</span>
                                                <span class="text-sm sm:text-base font-bold text-gray-900 dark:text-white">{{ number_format($maxUser->total_sum - $minUser->total_sum, 2, ',', ' ') }} MDL</span>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Половина разницы (базовая) -->
                                    <div class="bg-gradient-to-r from-purple-50 to-pink-50 dark:from-purple-950/30 dark:to-pink-950/30 rounded-lg p-3 border border-purple-200/50 dark:border-purple-800/50">
                                        <div class="flex justify-between items-center">
                                            <span class="text-xs sm:text-sm text-gray-600 dark:text-gray-400">Делится поровну (½)</span>
                                            <span class="text-sm sm:text-lg font-bold text-purple-600 dark:text-purple-400">{{ $baseDiffFormatted }} MDL</span>
                                        </div>
                                    </div>

                                    <!-- Уведомление о корректировке -->
                                    @if($adjustmentNote)
                                    <div class="bg-gradient-to-r from-cyan-50 to-blue-50 dark:from-cyan-950/40 dark:to-blue-950/40 rounded-lg p-3 border-2 border-cyan-300/50 dark:border-cyan-700/50 shadow-sm">
                                        <div class="flex gap-2 sm:gap-3">
                                            <div class="w-6 h-6 sm:w-7 sm:h-7 rounded-lg bg-cyan-500/20 dark:bg-cyan-500/30 flex items-center justify-center flex-shrink-0">
                                                <svg class="w-3 h-3 sm:w-4 sm:h-4 text-cyan-600 dark:text-cyan-400" fill="currentColor" viewBox="0 0 20 20">
                                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"></path>
                                                </svg>
                                            </div>
                                            <p class="text-xs my-auto sm:text-sm font-medium text-cyan-900 dark:text-cyan-100 leading-relaxed">{{ $adjustmentNote }}</p>
                                        </div>
                                    </div>
                                    @endif
                                </div>

                                <!-- Разделитель -->
                                <div class="flex items-center gap-2 sm:gap-3 py-4 sm:py-3">
                                    <div class="flex-1 h-px bg-gradient-to-r from-transparent via-amber-300 dark:via-amber-700 to-transparent"></div>
                                    <div class="px-5 bg-white/80 dark:bg-gray-800/80 backdrop-blur-sm rounded-lg border border-amber-200 dark:border-amber-800">
                                        <svg class="w-3 h-3 sm:w-4 sm:h-4 text-amber-600 dark:text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 12h14"></path>
                                        </svg>
                                    </div>
                                    <div class="flex-1 h-px bg-gradient-to-r from-transparent via-amber-300 dark:via-amber-700 to-transparent"></div>
                                </div>

                                <!-- Итоговая карточка -->
                                @if($isDebtZero)
                                <div class="relative bg-gradient-to-r from-green-500 via-emerald-500 to-teal-500 rounded-lg p-3 text-white">

                                    <div class="relative z-10">
                                        <div class="flex flex-col items-center justify-center gap-3 sm:gap-4 text-center py-2">
                                            <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-white/20 flex items-center justify-center">
                                                <svg class="w-8 h-8 sm:w-10 sm:h-10 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                                                </svg>
                                            </div>
                                            <div>
                                                <p class="text-xs sm:text-sm font-semibold opacity-90 mb-1">Итоговый расчёт</p>
                                                <p class="text-2xl sm:text-3xl md:text-4xl font-black">Никто никому ничего не должен</p>
                                                <p class="text-xs sm:text-sm opacity-90 mt-2">Долг полностью погашен ✓</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @else
                                <div class="relative bg-gradient-to-r from-amber-500 via-orange-500 to-red-500 rounded-lg p-3 text-white">
                                    <!-- Декоративные элементы -->
                                    <div class="absolute top-0 right-0 w-32 h-32 bg-white/10 rounded-full -translate-y-16 translate-x-16 blur-2xl"></div>
                                    <div class="absolute bottom-0 left-0 w-24 h-24 bg-black/10 rounded-full translate-y-12 -translate-x-12 blur-xl"></div>

                                    <div class="relative z-10">
                                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 sm:gap-4">
                                            <div class="flex-1">
                                                <p class="text-xs sm:text-sm font-semibold opacity-90 mb-1 sm:mb-2 flex items-center gap-2">
                                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M6 2a2 2 0 00-2 2v12a2 2 0 002 2h8a2 2 0 002-2V4a2 2 0 00-2-2H6zm1 2a1 1 0 000 2h6a1 1 0 100-2H7zm6 7a1 1 0 011 1v3a1 1 0 11-2 0v-3a1 1 0 011-1zm-3 3a1 1 0 100 2h.01a1 1 0 100-2H10zm-4 1a1 1 0 011-1h.01a1 1 0 110 2H7a1 1 0 01-1-1zm1-4a1 1 0 100 2h.01a1 1 0 100-2H7zm2 1a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1zm4-4a1 1 0 100 2h.01a1 1 0 100-2H13zM9 9a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1zM7 8a1 1 0 000 2h.01a1 1 0 000-2H7z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    Итоговый долг
                                                </p>
                                                <div class="flex items-center gap-2 sm:gap-3">
                                                    <p class="text-base sm:text-xl font-bold truncate">{{ $finalMinName }}</p>
                                                    <svg class="w-5 h-5 sm:w-6 sm:h-6 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                                        <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 01-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"></path>
                                                    </svg>
                                                    <p class="text-base sm:text-xl font-bold truncate">{{ $finalMaxName }}</p>
                                                </div>
                                            </div>
                                            <div class="text-left sm:text-right">
                                                <p class="text-3xl sm:text-4xl md:text-5xl font-black leading-none">{{ $finalDiffFormatted }}</p>
                                                <p class="text-xs sm:text-sm opacity-90 mt-1">MDL</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                @endif
                            </div>
                        </div>

                        <!-- Детали затрат -->
                        <details class="group/expenses bg-white dark:bg-gray-800 rounded-lg border-2 border-gray-200 dark:border-gray-700 overflow-hidden">
                            <summary class="font-bold text-gray-900 dark:text-white text-sm sm:text-base cursor-pointer px-3 py-3 bg-gradient-to-r from-gray-50 to-white dark:from-gray-800 dark:to-gray-900 hover:from-gray-100 hover:to-gray-50 dark:hover:from-gray-700 dark:hover:to-gray-800 transition-all duration-200 flex items-center gap-2 sm:gap-3 active:scale-[0.99]">
                                <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-gradient-to-br from-blue-400 to-indigo-500 flex items-center justify-center shadow-md flex-shrink-0">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"></path>
                                    </svg>
                                </div>
                                <span class="flex-1 text-left">Детализация затрат</span>
                                <svg class="w-5 h-5 sm:w-6 sm:h-6 text-gray-400 transition-transform duration-300 group-open/expenses:rotate-180 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </summary>

                            <div class="p-3 space-y-2 sm:space-y-3 bg-gray-50 dark:bg-gray-900/30">
                                @foreach($expenses as $userExpense)
                                @php
                                $userName = $userExpense->user->name ?? 'Unknown';
                                $userImage = $userExpense->user->image ?? null;
                                $userTotal = number_format($userExpense->total_sum, 2, ',', ' ');
                                $userExpenseDetails = Expense::where('user_id', $userExpense->user_id)
                                ->whereMonth('date', $date->month)
                                ->whereYear('date', $date->year)
                                ->with('category:id,name', 'supplier:id,name')
                                ->orderBy('date')
                                ->get();
                                @endphp

                                <details class="group/user bg-white dark:bg-gray-800 rounded-lg border-l-4 border-amber-400 dark:border-amber-600 overflow-hidden">
                                    <summary class="cursor-pointer px-3 py-3 hover:bg-gray-50 dark:hover:bg-gray-750 transition-colors duration-150 flex items-center gap-2 sm:gap-3 active:scale-[0.98]">
                                        <div class="w-8 h-8 sm:w-10 sm:h-10 rounded-lg bg-gradient-to-br from-amber-400 to-orange-500 flex items-center justify-center flex-shrink-0 overflow-hidden">
                                            @if($userImage)
                                            <img src="{{ asset('storage/' . $userImage) }}" alt="{{ $userName }}" class="w-full h-full object-cover">
                                            @else
                                            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="currentColor" viewBox="0 0 20 20">
                                                <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"></path>
                                            </svg>
                                            @endif
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="font-bold text-gray-900 dark:text-white text-sm sm:text-base truncate">{{ $userName }}</p>
                                            <p class="text-xs sm:text-sm text-gray-500 dark:text-gray-400">{{ $userExpenseDetails->count() }} операций</p>
                                        </div>
                                        <div class="text-right flex-shrink-0">
                                            <p class="font-black text-gray-900 dark:text-white text-sm sm:text-lg">{{ $userTotal }}</p>
                                            <p class="text-[10px] sm:text-xs text-gray-500 dark:text-gray-400">MDL</p>
                                        </div>
                                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-400 transition-transform duration-300 group-open/user:rotate-180 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path>
                                        </svg>
                                    </summary>

                                    <div class="border-t-2 border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-900/50">
                                        <!-- Мобильная версия (карточки) -->
                                        <div class="sm:hidden p-3 space-y-2">
                                            @foreach($userExpenseDetails as $expense)
                                            @php
                                            $expDate = $expense->date instanceof Carbon ? $expense->date->format('d.m.Y') : $expense->date;
                                            $expSum = number_format($expense->sum, 2, ',', ' ');
                                            @endphp
                                            <a href="{{ \App\Filament\Resources\ExpenseResource::getUrl('view', ['record' => $expense]) }}" class="block bg-white dark:bg-gray-800 rounded-lg p-3 border border-gray-200 dark:border-gray-700 shadow-sm hover:shadow-md hover:border-amber-400 dark:hover:border-amber-600 transition-all duration-200 active:scale-[0.98]">
                                                <div class="flex justify-between items-start mb-2">
                                                    <span class="text-xs font-semibold text-gray-900 dark:text-white">{{ $expDate }}</span>
                                                    <span class="text-sm font-bold text-amber-600 dark:text-amber-400">{{ $expSum }} MDL</span>
                                                </div>
                                                <div class="space-y-1">
                                                    <div class="flex items-center gap-2 text-xs">
                                                        <span class="text-gray-500 dark:text-gray-400">Категория:</span>
                                                        <span class="text-gray-700 dark:text-gray-300">{{ $expense->category?->name ?? '—' }}</span>
                                                    </div>
                                                    <div class="flex items-center gap-2 text-xs">
                                                        <span class="text-gray-500 dark:text-gray-400">Поставщик:</span>
                                                        <span class="text-gray-700 dark:text-gray-300">{{ $expense->supplier?->name ?? '—' }}</span>
                                                    </div>
                                                </div>
                                            </a>
                                            @endforeach
                                        </div>

                                        <!-- Десктопная версия (таблица) -->
                                        <div class="hidden sm:block overflow-x-auto">
                                            <table class="w-full text-xs sm:text-sm">
                                                <thead>
                                                    <tr class="bg-gray-100 dark:bg-gray-800 border-b-2 border-gray-300 dark:border-gray-600">
                                                        <th class="text-left py-3 px-3 text-gray-700 dark:text-gray-300 font-bold">Дата</th>
                                                        <th class="text-left py-3 px-3 text-gray-700 dark:text-gray-300 font-bold">Категория</th>
                                                        <th class="text-left py-3 px-3 text-gray-700 dark:text-gray-300 font-bold">Поставщик</th>
                                                        <th class="text-right py-3 px-3 text-gray-700 dark:text-gray-300 font-bold">Сумма</th>
                                                    </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                                                    @foreach($userExpenseDetails as $expense)
                                                    @php
                                                    $expDate = $expense->date instanceof Carbon ? $expense->date->format('d.m.Y') : $expense->date;
                                                    $expSum = number_format($expense->sum, 2, ',', ' ');
                                                    $expenseUrl = \App\Filament\Resources\ExpenseResource::getUrl('view', ['record' => $expense]);
                                                    @endphp
                                                    <tr class="hover:bg-white dark:hover:bg-gray-800 hover:shadow-sm transition-all duration-100 cursor-pointer" onclick="window.location.href='{{ $expenseUrl }}'">
                                                        <td class="py-3 px-3 text-gray-600 dark:text-gray-400 whitespace-nowrap">{{ $expDate }}</td>
                                                        <td class="py-3 px-3 text-gray-600 dark:text-gray-400">{{ $expense->category?->name ?? '—' }}</td>
                                                        <td class="py-3 px-3 text-gray-600 dark:text-gray-400">{{ $expense->supplier?->name ?? '—' }}</td>
                                                        <td class="py-3 px-3 text-right text-gray-900 dark:text-white font-bold whitespace-nowrap">{{ $expSum }} MDL</td>
                                                    </tr>
                                                    @endforeach
                                                </tbody>
                                                <tfoot>
                                                    <tr class="bg-gradient-to-r from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-700 border-t-2 border-gray-300 dark:border-gray-600">
                                                        <td colspan="3" class="py-3 px-3 font-black text-gray-900 dark:text-white">Итого:</td>
                                                        <td class="py-3 px-3 text-right font-black text-gray-900 dark:text-white whitespace-nowrap text-base sm:text-lg">{{ $userTotal }} MDL</td>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>

                                        <!-- Итого для мобильной версии -->
                                        <div class="sm:hidden bg-gradient-to-r from-gray-100 to-gray-200 dark:from-gray-800 dark:to-gray-700 p-3 border-t-2 border-gray-300 dark:border-gray-600">
                                            <div class="flex justify-between items-center">
                                                <span class="font-black text-gray-900 dark:text-white text-sm">Итого:</span>
                                                <span class="font-black text-gray-900 dark:text-white text-lg">{{ $userTotal }} MDL</span>
                                            </div>
                                        </div>
                                    </div>
                                </details>
                                @endforeach
                            </div>
                        </details>
                    </div>
                </details>
            </div>