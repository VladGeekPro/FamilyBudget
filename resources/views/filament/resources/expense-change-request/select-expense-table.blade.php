<div>
    <div class="flex justify-end p-4">
        <button
            type="button"
            class="text-gray-400 hover:text-gray-500"
            x-on:click="close">
            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>
    </div>
    @livewire(\App\Filament\Resources\ExpenseChangeRequestResource\Widgets\SelectExpenseTableWidget::class)
</div>