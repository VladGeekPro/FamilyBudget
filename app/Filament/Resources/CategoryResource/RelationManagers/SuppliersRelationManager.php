<?php

namespace App\Filament\Resources\CategoryResource\RelationManagers;

use App\Filament\Resources\BaseRelationManager\BaseRelationManager;

use App\Filament\Resources\Base\BaseResource;
use App\Models\Category;
use App\Models\Supplier;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid as FormGrid;
use Filament\Forms\Components\Select;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\Layout\Grid as TableGrid;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn\TextColumnSize;

use Illuminate\Support\Str;

use Filament\Support\Enums\FontWeight;

class SuppliersRelationManager extends BaseRelationManager
{
    protected static string $relationship = 'suppliers';

    public function form(Form $form): Form
    {
        return $form
            ->schema([

                Section::make(__('resources.sections.user.main'))
                    ->icon('heroicon-o-identification')
                    ->iconColor('warning')
                    ->schema([

                        FormGrid::make([
                            'default' => 1,
                            'sm' => 6,
                            'xl' => 12,
                        ])
                            ->schema([

                                FileUpload::make('image')
                                    ->label(__('resources.fields.image'))
                                    ->avatar()
                                    ->image()
                                    ->imageEditor()
                                    ->circleCropper()
                                    ->imageEditorAspectRatios([
                                        null,
                                        '16:9',
                                        '4:3',
                                        '1:1',
                                    ])
                                    ->required()
                                    ->afterStateUpdated(fn($livewire) => $livewire->validateOnly('mountedTableActionsData.0.image'))
                                    ->columnSpan([
                                        'default' => 1,
                                        'sm' => 2,
                                        'xl' => 2,
                                    ]),

                                Group::make([

                                    FormGrid::make([
                                        'default' => 1,
                                        'sm' => 4,
                                        'xl' => 10,
                                    ])
                                        ->schema([
                                            TextInput::make('name')
                                                ->label(__('resources.fields.name.inanimate'))
                                                ->required()
                                                ->maxLength(255)
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(fn($operation, $state, $set, $livewire) => $operation === 'create' ? tap($set('slug', Str::slug($state)), fn() => $livewire->validateOnly('mountedTableActionsData.0.slug')) : null)
                                                ->columnSpan([
                                                    'default' => 1,
                                                    'sm' => 2,
                                                    'xl' => 5,
                                                ]),

                                            TextInput::make('slug')
                                                ->label(__('resources.fields.slug'))
                                                ->required()
                                                ->maxLength(255)
                                                ->disabled()
                                                ->unique(Supplier::class, 'slug', ignoreRecord: true)
                                                ->dehydrated(fn($operation) => $operation === "create")
                                                ->columnSpan([
                                                    'default' => 1,
                                                    'sm' => 2,
                                                    'xl' => 5,
                                                ]),

                                        ]),

                                    Select::make('category_id')
                                        ->label(__('resources.fields.category'))
                                        ->required()
                                        ->default(fn($livewire) => $livewire->ownerRecord->id)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn($livewire) => $livewire->validateOnly('data.category_id'))
                                        ->allowHtml()
                                        ->options(fn() => Category::all()->mapWithKeys(function ($category) {
                                            return [$category->getKey() => BaseResource::getCleanOptionString($category)];
                                        })->toArray())
                                        ->getSearchResultsUsing(function (string $search) {
                                            $categories = Category::where('name', 'like', "%{$search}%")->limit(50)->get();
                                            return $categories->mapWithKeys(function ($category) {
                                                return [$category->getKey() => BaseResource::getCleanOptionString($category)];
                                            })->toArray();
                                        })
                                        ->getOptionLabelUsing(function ($value): string {
                                            $category = Category::find($value);
                                            return BaseResource::getCleanOptionString($category);
                                        })
                                        ->optionsLimit(10)
                                        ->searchable()
                                        ->preload()
                                        ->selectablePlaceholder(false)
                                        ->loadingMessage(__('resources.notifications.load.categories'))
                                        ->noSearchResultsMessage(__('resources.notifications.skip.categories'))
                                        ->columnSpanFull(),
                                ])
                                    ->columnSpan([
                                        'default' => 1,
                                        'sm' => 4,
                                        'xl' => 10,
                                    ]),
                            ]),
                    ])
            ]);
    }

    public function table(Table $table): Table
    {
        $table = parent::table($table);

        return $table
            ->heading('Поставщики')

            ->recordTitleAttribute('name')

            ->columns([
                TableGrid::make()
                    ->columns(1)
                    ->schema([
                        Split::make([
                            TableGrid::make()
                                ->columns(1)
                                ->schema([
                                    ImageColumn::make('image')
                                        ->circular()
                                        ->height(100)
                                        ->width(100)
                                ])->grow(false),
                            Stack::make([
                                TextColumn::make('name')
                                    ->label(__('resources.fields.name.inanimate'))
                                    ->size(TextColumnSize::Medium)
                                    ->weight(FontWeight::Bold)
                                    ->searchable(),
                            ])->grow(),
                        ])
                    ])
            ])
            ->contentGrid([
                'md' => 2,
                'lg' => 1,
                'xl' => 2,
                '2xl' => 3,

            ])

            ->filters([
                //
            ]);
    }
}
