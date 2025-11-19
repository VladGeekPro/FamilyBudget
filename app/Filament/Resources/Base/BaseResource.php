<?php

namespace App\Filament\Resources\Base;

use Filament\Resources\Resource;
use Filament\Tables\Table;

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
        $modelBase = Str::snake(class_basename(static::getModel()));
        $key = "resources.search_placeholder.resource.{$modelBase}";
        $table->searchPlaceholder(
            Lang::has($key)
                ? __($key)
                : __('resources.search_placeholder.missing')
        );



        return $table;
    }

    public static function getCleanOptionString(Model $model): string
    {
        return  view('filament.components.select-user-result')
            ->with('name', $model?->name)
            ->with('image', $model?->image)
            ->render();
    }
}
