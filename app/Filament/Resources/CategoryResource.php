<?php

namespace App\Filament\Resources;

use App\Models\Category;
use App\Filament\Resources\CategoryResource\Pages;
use App\Filament\Resources\Base\BaseResource;
use App\Filament\Resources\CategoryResource\RelationManagers\SuppliersRelationManager;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Group;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Grid as FormGrid;
use Filament\Forms\Components\MarkdownEditor;

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

class CategoryResource extends BaseResource
{

    protected static ?string $model = Category::class;

    protected static ?string $navigationGroup = 'Справочники';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationLabel = 'Категории';

    protected static ?string $modelLabel = 'категорию';

    protected static ?string $pluralModelLabel = 'категории';

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
                                    TextInput::make('name')
                                        ->label(__('resources.fields.name.inanimate'))
                                        ->required()
                                        ->maxLength(255)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(fn($operation, $state, $set, $livewire) => $operation === 'create' ? tap($set('slug', Str::slug($state)), fn() => $livewire->validateOnly('data.slug')) : null)
                                        ->columnSpan('full'),
                                    TextInput::make('slug')
                                        ->label(__('resources.fields.slug'))
                                        ->required()
                                        ->maxLength(255)
                                        ->disabled()
                                        ->unique(Category::class, 'slug', ignoreRecord: true)
                                        ->dehydrated(fn($operation) => $operation === "create")
                                        ->columnSpan('full'),
                                ])->columnSpan([
                                    'default' => 1,
                                    'sm' => 4,
                                    'xl' => 10,
                                ]),
                            ]),

                        MarkdownEditor::make('notes')
                            ->label(__('resources.fields.notes'))
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn($livewire) => $livewire->validateOnly('data.notes'))
                            ->disableToolbarButtons([
                                'attachFiles',
                                'blockquote',
                                'codeBlock',
                                'heading',
                                'table',
                                'link',
                            ])
                            ->columnSpanFull(),
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
                                    ->label(__('resources.fields.category'))
                                    ->size(TextColumnSize::Medium)
                                    ->weight(FontWeight::Bold)
                                    ->searchable()
                                    ->sortable(),
                                ImageColumn::make('suppliers.image')
                                    ->circular()
                                    ->stacked()
                                    ->ring(7)
                                    ->overlap(3)
                                    ->limit(6)
                                    ->limitedRemainingText()
                                    ->extraAttributes(fn($record) => $record->suppliers->isNotEmpty() ? ['class' => 'py-4'] : []),
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
                                TextColumn::make('notes')
                                    ->label(__('resources.fields.notes'))
                                    ->html()
                                    ->formatStateUsing(fn($state) => Str::markdown($state))
                                    ->color("gray")
                                    ->limit(100)
                                    ->searchable()
                                    ->toggleable(),
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
            ])

            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            SuppliersRelationManager::class
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCategories::route('/'),
            'create' => Pages\CreateCategory::route('/create'),
            'edit' => Pages\EditCategory::route('/{record}/edit'),
        ];
    }
}
