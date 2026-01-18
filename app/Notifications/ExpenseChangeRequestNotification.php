<?php

namespace App\Notifications;

use App\Filament\Resources\ExpenseChangeRequestResource;
use App\Models\ExpenseChangeRequest;
use App\Models\User;
use Filament\Notifications\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class ExpenseChangeRequestNotification extends Notification
{
    public function __construct(
        public ExpenseChangeRequest $changeRequest,
        public User $creator
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        $actionType = $this->changeRequest->action_type;
        
        // Определяем тип уведомления в зависимости от действия
        $notificationType = match($actionType) {
            'create' => 'expense_change_request_create',
            'edit' => 'expense_change_request_edit',
            'delete' => 'expense_change_request_delete',
            default => 'expense_change_request_create',
        };

        // Получаем данные для подстановки в уведомление
        $expense = $this->changeRequest->expense;
        $substitutions = [
            'user' => $this->creator->name,
            'action' => $this->getActionLabel($actionType),
            'date' => $expense?->date?->format('d.m.Y') ?? 'N/A',
            'supplier' => $expense?->supplier?->name ?? $this->changeRequest->requestedSupplier?->name ?? 'N/A',
            'sum' => $expense?->sum ?? $this->changeRequest->requested_sum ?? 'N/A',
        ];

        if (is_numeric($substitutions['sum'])) {
            $substitutions['sum'] = number_format($substitutions['sum'], 2, ',', ' ') . ' MDL';
        }

        $body = __('resources.notifications.warn.' . $notificationType . '.body', $substitutions);

        return FilamentNotification::make()
            ->title(__('resources.notifications.warn.' . $notificationType . '.title'))
            ->body(new \Illuminate\Support\HtmlString($body))
            ->icon($this->getIcon($actionType))
            ->iconColor($this->getIconColor($actionType))
            ->actions([
                Action::make('view')
                    ->label(__('resources.buttons.view'))
                    ->icon('heroicon-o-eye')
                    ->button()
                    ->url(fn() => ExpenseChangeRequestResource::getUrl('view', ['record' => $this->changeRequest->id])),

                Action::make('markAsRead')
                    ->label(__('resources.buttons.mark_as_read'))
                    ->icon('heroicon-o-check')
                    ->button()
                    ->color('success')
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }

    private function getActionLabel(string $actionType): string
    {
        return match($actionType) {
            'create' => __('resources.fields.action_type.options.create'),
            'edit' => __('resources.fields.action_type.options.edit'),
            'delete' => __('resources.fields.action_type.options.delete'),
            default => $actionType,
        };
    }

    private function getIcon(string $actionType): string
    {
        return match($actionType) {
            'create' => 'heroicon-o-plus-circle',
            'edit' => 'heroicon-o-pencil-square',
            'delete' => 'heroicon-o-trash',
            default => 'heroicon-o-document-arrow-up',
        };
    }

    private function getIconColor(string $actionType): string
    {
        return match($actionType) {
            'create' => 'success',
            'edit' => 'warning',
            'delete' => 'danger',
            default => 'info',
        };
    }
}
