<?php

namespace App\Filament\Resources\Base;

use Filament\Actions\DeleteAction;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Actions\Action;

use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;

abstract class BaseResource extends Resource
{
    /** Можно переопределять в дочерних ресурсах */
    protected static ?string $defaultSortColumn = 'name';
    protected static string  $defaultSortDirection = 'asc';

    /** Пагинация по умолчанию (можно переопределить в дочерних) */
    protected static int    $defaultPerPage   = 10;
    protected static array  $defaultPerPageOptions   = [10, 25, 50, 100, 'all'];

    protected static function getModelBase(): string
    {
        return Str::snake(class_basename(static::getModel()));
    }

    protected static function getTableActions(): array
    {
        $modelBase = static::getModelBase();

        return [
            Tables\Actions\EditAction::make()
                ->extraAttributes(['style' => 'margin-left: auto;']),

            Tables\Actions\DeleteAction::make()
                ->successNotificationTitle(__("resources.notifications.delete.{$modelBase}")),
        ];
    }

    public static function table(Table $table): Table
    {
        // пагинация
        $table
            ->defaultPaginationPageOption(static::$defaultPerPage)
            ->paginated(static::$defaultPerPageOptions);

        // дефолтная сортировка (если указана колонка)
        if (filled(static::$defaultSortColumn)) {
            $table->defaultSort(static::$defaultSortColumn, static::$defaultSortDirection);
        }

        // плейсхолдер поиска из переводов:
        $modelBase = static::getModelBase();
        $key = "resources.search_placeholder.resource.{$modelBase}";
        $table->searchPlaceholder(
            Lang::has($key)
                ? __($key)
                : __('resources.search_placeholder.missing')
        );

        //действия
        $table->actions(
            static::getTableActions()
        );

        return $table;
    }

    public static function formatOptionWithIcon(string $name, ?string $image, ?string $bgColor = null): string
    {
        return view('filament.components.select-user-result')
            ->with('name', $name)
            ->with('image', $image)
            ->with('bgColor', $bgColor)
            ->render();
    }

    protected function getDefaultDeleteAction(?string $label = null): DeleteAction
    {
        return DeleteAction::make()
            ->label($label ?? __('Удалить'))
            ->successNotificationTitle(__('Удалено!'));
    }
}
