<?php

namespace App\Providers;

use Filament\Forms\Components\Select;
use Filament\Tables\Table;
use Illuminate\Support\ServiceProvider;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        parent::register();

        \Filament\Tables\Columns\Layout\Component::mixin(
            new \App\Macros\Filament\Tables\Columns\Layout\ComponentMacros()
        );

        $resolveNotFoundMessage = static function (string $name): string {
            $name = trim($name);

            if ($name === '') {
                $name = 'Запись';
            }

            $ending = mb_substr(mb_strtolower($name, 'UTF-8'), -1, 1, 'UTF-8');

            if (in_array($ending, ['а', 'я'], true)) {
                $suffix = 'не найдена';
            } elseif (in_array($ending, ['о', 'е'], true)) {
                $suffix = 'не найдено';
            } else {
                $suffix = 'не найден';
            }

            return "{$name} {$suffix}";
        };

        Select::configureUsing(function (Select $select) use ($resolveNotFoundMessage): void {
            $messageResolver = static function (Select $component) use ($resolveNotFoundMessage): string {
                $rawField = (string) $component->getName();
                $fieldKey = Str::of($rawField)
                    ->afterLast('.')
                    ->replace(['current_', 'requested_'], '')
                    ->replace('_id', '')
                    ->toString();

                $name = __('resources.fields.' . $fieldKey);
                return $resolveNotFoundMessage($name);
            };

            $select
                ->noSearchResultsMessage($messageResolver)
                ->noOptionsMessage($messageResolver);
        });

        // Global message for searchable table results (Filament Tables).
        Table::configureUsing(function (Table $table) use ($resolveNotFoundMessage): void {
            $table->emptyStateHeading(function (Table $table) use ($resolveNotFoundMessage): string {
                $modelClass = $table->getModel();
                $modelBase = Str::snake(class_basename($modelClass));
                $name = __('resources.fields.' . $modelBase);
                return $resolveNotFoundMessage((string) $name);
            });
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
