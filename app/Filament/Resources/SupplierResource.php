<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Filament\Resources\Base\BaseResource;
use App\Models\Category;
use App\Models\Supplier;

use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid as FormGrid;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\Layout\Grid as TableGrid;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group as TableGroup;

use Illuminate\Support\Str;

use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;

class SupplierResource extends BaseResource
{
    protected static ?string $model = Supplier::class;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Справочники';
    }

    protected static ?int $navigationSort = 1;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-building-storefront';

    protected static ?string $navigationLabel = 'Поставщики';

    protected static ?string $modelLabel = 'поставщика';

    protected static ?string $pluralModelLabel = 'поставщики';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([

                Section::make(__('resources.sections.main'))
                    ->icon('heroicon-o-document-text')
                    ->iconColor('primary')
                    ->columnSpanFull()
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
                        ->imageEditorAspectRatioOptions([
                                        null,
                                        '16:9',
                                        '4:3',
                                        '1:1',
                                    ])
                                    ->required()
                                    ->afterStateUpdated(fn($livewire) => $livewire->validateOnly('data.image'))
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
                                                ->afterStateUpdated(fn($operation, $state, $set, $livewire) => $operation === 'create' ? tap($set('slug', Str::slug($state)), fn() => $livewire->validateOnly('data.slug')) : null)
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
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn($livewire) => $livewire->validateOnly('data.category_id'))
                                        ->allowHtml()
                                        ->options(fn() => Category::all()->mapWithKeys(function ($category) {
                                            return [$category->getKey() => static::formatOptionWithIcon($category->name, $category->image)];
                                        })->toArray())
                                        ->getSearchResultsUsing(function (string $search) {
                                            $categories = Category::where('name', 'like', "%{$search}%")->limit(50)->get();
                                            return $categories->mapWithKeys(function ($category) {
                                                return [$category->getKey() => static::formatOptionWithIcon($category->name, $category->image)];
                                            })->toArray();
                                        })
                                        ->getOptionLabelUsing(function ($value): string {
                                            $category = Category::find($value);
                                            return static::formatOptionWithIcon($category->name, $category->image);
                                        })
                                        ->optionsLimit(10)
                                        ->preload()
                                        ->searchable()
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

    public static function table(Table $table): Table
    {
        $table = parent::table($table);

        return $table
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
                                        ->imageHeight(80)
                                        ->imageWidth(80)
                                ])->grow(false),
                            Stack::make([
                                TextColumn::make('name')
                                    ->label(__('resources.fields.supplier'))
                                    ->size('md')
                                    ->weight(FontWeight::Bold)
                                    ->searchable()
                                    ->sortable(),

                                TextColumn::make('created_at')
                                    ->label(__('resources.fields.created_at'))
                                    ->dateTime('d M. Y H:i:s')
                                    ->sortable(),

                                TextColumn::make('updated_at')
                                    ->label(__('resources.fields.updated_at'))
                                    ->dateTime('d M. Y H:i:s')
                                    ->sortable(),
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

            ->defaultGroup(
                TableGroup::make('category.name')
                    ->label("")
                    ->collapsible()
            )

            ->filters(static::getSupplierTableFilters())->filtersFormWidth(Width::Small)

            ->bulkActions([]);
    }

    protected static function getSupplierTableFilters(): array
    {
        return [
            static::makeRelationshipFilter('category', __('resources.fields.category'), 'category'),
            static::makeSelectOptionsFilter(
                'name',
                __('resources.fields.supplier'),
                fn() => Supplier::orderBy('name')->pluck('name', 'id')->toArray(),
                true,
            ),
        ];
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSuppliers::route('/'),
            'create' => Pages\CreateSupplier::route('/create'),
            'edit' => Pages\EditSupplier::route('/{record}/edit'),
        ];
    }
}

