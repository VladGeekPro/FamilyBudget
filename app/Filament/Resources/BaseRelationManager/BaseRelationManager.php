<?php

namespace App\Filament\Resources\BaseRelationManager;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Tables\Actions\Action;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Lang;

use Filament\Tables\Actions\BulkAction;
use Illuminate\Database\Eloquent\Collection;

abstract class BaseRelationManager extends RelationManager
{

    protected static ?string $modelLabel = 'поставщика';

    /** Можно переопределять в дочерних ресурсах */
    protected static ?string $defaultSortColumn = 'name';
    protected static string  $defaultSortDirection = 'asc';

    /** Пагинация по умолчанию (можно переопределить в дочерних) */
    protected static int    $defaultPerPage = 25;
    protected static array  $defaultPerPageOptions = [10, 25, 50, 100, 'all'];

    public function table(Table $table): Table
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
        $modelBase = Str::singular(static::getRelationshipName());
        $key = "resources.search_placeholder.resource.{$modelBase}";
        $table->searchPlaceholder(
            Lang::has($key)
                ? __($key)
                : __('resources.search_placeholder.missing')
        );

        $table->headerActions([
            Tables\Actions\CreateAction::make()
                ->successNotificationTitle(__("resources.notifications.create.{$modelBase}"))
                ->extraModalFooterActions(fn(Action $action): array => [
                    $action->makeModalSubmitAction('createAnother', arguments: ['another' => true])
                        ->label(__('resources.buttons.create_another')),
                ]),
        ]);

        $table->actions([
            Tables\Actions\EditAction::make()->extraAttributes(['style' => 'margin-left: auto;'])
                ->successNotificationTitle(__("resources.notifications.edit.{$modelBase}")),
            Tables\Actions\DeleteAction::make()
                ->successNotificationTitle(__("resources.notifications.delete.{$modelBase}")),
        ]);

        return $table;
    }
}
