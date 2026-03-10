<?php

namespace App\Filament\Traits;

trait HasBeautifulHeading
{
    protected function getHeaderGradient(): string
    {
        return 'from-violet-600 via-purple-600 to-indigo-600';
    }

    protected function getHeaderIcon(): string
    {
        return 'heroicon-o-chart-bar';
    }

    protected function getHeaderTitle(): string
    {
        return '';
    }

    protected function getHeaderPill(): ?string
    {
        return null;
    }

    protected function getHeaderDescription(): ?string
    {
        return null;
    }
}
