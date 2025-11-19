<?php

namespace App\Macros\Filament\Tables\Columns\Layout;

use Filament\Tables\Columns\Column;

class ComponentMacros
{
    public function isToggledHidden()
    {
        return function () {
            /** @var \Filament\Tables\Columns\Layout\Component $this */

            $items = $this->getColumns();
            $itemsCount = count($items);

            // сколько колонок скрыто toggle'ом
            $toggledHiddenItems = count(array_filter(
                $items,
                fn(Column $column): bool => $column->isToggledHidden(),
            ));

            // если скрыты все — возвращаем true
            return $itemsCount === $toggledHiddenItems;
        };
    }
}
