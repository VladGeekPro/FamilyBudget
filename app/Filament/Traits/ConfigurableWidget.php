<?php

namespace App\Filament\Traits;

trait ConfigurableWidget
{
    abstract protected function getConfigurableSections(): array;

    public function openConfigureModal(): void
    {
        $this->configureFormData = $this->getFormData();
        $this->showConfigureModal = true;
    }

    public function closeConfigureModal(): void
    {
        $this->showConfigureModal = false;
    }

    public function handleSaveConfigureModal(): void
    {
        $normalizedData = [];

        foreach (array_keys($this->getConfigurableSections()) as $section) {
            $normalizedData[$section] = $this->configureFormData[$section] ?? false;
        }

        $this->saveConfiguration($normalizedData);
        $this->showConfigureModal = false;
    }

    public function toggleAllConfigureSections(): void
    {
        $allSelected = $this->areAllConfigureSectionsSelected();
        $newValue = ! $allSelected;

        foreach (array_keys($this->getConfigurableSections()) as $section) {
            $this->configureFormData[$section] = $newValue;
        }
    }

    public function areAllConfigureSectionsSelected(): bool
    {
        foreach (array_keys($this->getConfigurableSections()) as $section) {
            if (! ($this->configureFormData[$section] ?? false)) {
                return false;
            }
        }

        return true;
    }

    protected function getFormData(): array
    {
        $widgetClass = static::class;
        $preferences = auth()->user()->getWidgetPreferences($widgetClass);

        if (empty($preferences)) {
            return array_fill_keys(array_keys($this->getConfigurableSections()), true);
        }

        return $preferences;
    }

    protected function saveConfiguration(array $data): void
    {
        $widgetClass = static::class;
        auth()->user()->setWidgetPreferences($widgetClass, $data);

        \Filament\Notifications\Notification::make()
            ->title('Настройки сохранены')
            ->success()
            ->send();
    }

    protected function getSectionSettings(): array
    {
        $settings = [];
        foreach (array_keys($this->getConfigurableSections()) as $section) {
            $settings[$section] = auth()->user()->getWidgetPreference(static::class, $section);
        }
        return $settings;
    }
}
