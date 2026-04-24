<?php

namespace App\Filament\Resources\ExpenseResource\Widgets;

use App\Models\Supplier;
use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PredictedExpensesWidget extends Widget
{
    protected string $view = 'filament.resources.expense-resource.widgets.predicted-expenses-widget';

    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = -5;

    protected function getViewData(): array
    {
        $currentUser = Auth::user();
        $userId = (int) $currentUser?->getAuthIdentifier();

        $suppliersMap = Supplier::query()
            ->with('category:id,name')
            ->get(['id', 'name', 'image', 'category_id'])
            ->keyBy('id')
            ->map(fn (Supplier $s) => [
                'name'          => $s->name,
                'category_name' => $s->category?->name ?? '',
                'image_url'     => $s->image ? Storage::url($s->image) : null,
            ])
            ->toArray();

        $usersMap = User::query()
            ->get(['id', 'name', 'image'])
            ->keyBy('id')
            ->map(fn (User $u) => [
                'name'      => $u->name,
                'image_url' => $u->image ? Storage::url($u->image) : null,
            ])
            ->toArray();

        return [
            'predictUrl'     => route('expense-recommendations.predict'),
            'storeUrl'       => route('expense-recommendations.expenses.store'),
            'currentUserId'  => $userId,
            'suppliersMap'   => $suppliersMap,
            'usersMap'       => $usersMap,
        ];
    }
}
