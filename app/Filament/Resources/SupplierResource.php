<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SupplierResource\Pages;
use App\Filament\Resources\Base\BaseResource;
use App\Models\Category;
use App\Models\Supplier;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid as FormGrid;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\Layout\Grid as TableGrid;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Columns\TextColumn\TextColumnSize;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group as TableGroup;

use Illuminate\Support\Str;

use Filament\Support\Enums\FontWeight;

class SupplierResource extends BaseResource
{
    protected static ?string $model = Supplier::class;

    protected static ?string $navigationGroup = 'Справочники';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'поставщика';

    protected static ?string $pluralModelLabel = 'Поставщики';

    protected static ?string $navigationIcon = 'heroicon-o-building-storefront';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([

                Section::make(__('resources.sections.main'))
                    ->icon('heroicon-o-document-text')
                    ->iconColor('primary')
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
                                        ->height(100)
                                        ->width(100)
                                ])->grow(false),
                            Stack::make([
                                TextColumn::make('name')
                                    ->label(__('resources.fields.supplier'))
                                    ->size(TextColumnSize::Medium)
                                    ->weight(FontWeight::Bold)
                                    ->searchable()
                                    ->sortable(),

                                TextColumn::make('created_at')
                                    ->label(__('resources.fields.created_at'))
                                    ->dateTime('d M. Y H:i:s')
                                    ->sortable()
                                    ->toggleable(isToggledHiddenByDefault: true),

                                TextColumn::make('updated_at')
                                    ->label(__('resources.fields.updated_at'))
                                    ->dateTime('d M. Y H:i:s')
                                    ->sortable()
                                    ->toggleable(isToggledHiddenByDefault: true),
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

            ->filters([
                SelectFilter::make('category')
                    ->label(__('resources.fields.category'))
                    ->relationship('category', 'name')
                    ->multiple()
                    ->preload()
                    ->placeholder(''),
                SelectFilter::make('name')
                    ->label(__('resources.fields.supplier'))
                    ->options(fn() => Supplier::orderBy('name')->pluck('name', 'id')->toArray())
                    ->multiple()
                    ->placeholder('')
            ])

            ->bulkActions([]);
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
