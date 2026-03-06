<?php

namespace App\Filament\Support\Tables\Concerns;

use Filament\Actions\Action;
use Filament\Support\Components\Component;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\ColumnGroup;
use Filament\Tables\Columns\Layout\Component as ColumnLayoutComponent;

trait HasLayoutColumnManager
{
    public function initTableColumnManager(): void
    {
        if (blank($this->tableColumns)) {
            $this->tableColumns = $this->loadTableColumnsFromSession();
        }

        $this->applyTableColumnManager();
    }

    public function getDefaultTableColumnState(): array
    {
        return $this->cachedDefaultTableColumnState ??= collect($this->getTable()->getColumnsLayout())
            ->flatMap(fn (Component $component): array => $this->mapTableLayoutComponentToArray($component))
            ->values()
            ->all();
    }

    public function getColumnManagerApplyAction(): Action
    {
        return parent::getColumnManagerApplyAction()
            ->alpineClickHandler("applyTableColumnManager(); if (typeof close === 'function') { close() }");
    }

    /**
     * @return array<int, array{type: string, name: string, label: string, isHidden: bool, isToggled: bool, isToggleable: bool, isToggledHiddenByDefault: ?bool, columns?: array<int, array{type: string, name: string, label: string, isHidden: bool, isToggled: bool, isToggleable: bool, isToggledHiddenByDefault: ?bool}>}>
     */
    protected function mapTableLayoutComponentToArray(Component $component): array
    {
        return match (true) {
            $component instanceof ColumnGroup => $this->mapToggleableTableColumnGroupToArray($component),
            $component instanceof Column => $component->isToggleable()
                ? [$this->mapTableColumnToArray($component)]
                : [],
            $component instanceof ColumnLayoutComponent => collect($component->getComponents())
                ->flatMap(fn (Component $nested): array => $this->mapTableLayoutComponentToArray($nested))
                ->values()
                ->all(),
            default => [],
        };
    }

    /**
     * @return array<int, array{type: string, name: string, label: string, isHidden: bool, isToggled: bool, isToggleable: bool, isToggledHiddenByDefault: ?bool, columns: array<int, array{type: string, name: string, label: string, isHidden: bool, isToggled: bool, isToggleable: bool, isToggledHiddenByDefault: ?bool}>}>
     */
    protected function mapToggleableTableColumnGroupToArray(ColumnGroup $group): array
    {
        $columns = collect($group->getColumns())
            ->filter(fn (Column $column): bool => $column->isToggleable())
            ->map(fn (Column $column): array => $this->mapTableColumnToArray($column))
            ->values()
            ->all();

        if (empty($columns)) {
            return [];
        }

        $label = e($group->getLabel());

        return [[
            'type' => self::TABLE_COLUMN_MANAGER_GROUP_TYPE,
            'name' => $label,
            'label' => $label,
            'isHidden' => false,
            'isToggled' => true,
            'isToggleable' => true,
            'isToggledHiddenByDefault' => null,
            'columns' => $columns,
        ]];
    }

}
