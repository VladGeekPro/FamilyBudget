<?php

namespace App\Filament\Resources;

use App\Models\User;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Resources\Base\BaseResource;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Components\Group;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid as FormGrid;
use Filament\Forms\Components\Hidden;

use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\Layout\Grid as TableGrid;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;

use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\Width;

class UserResource extends BaseResource
{
    protected static ?string $model = User::class;

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return 'Справочники';
    }

    protected static ?int $navigationSort = 3;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Пользователи';

    protected static ?string $modelLabel = 'пользователя';

    protected static ?string $pluralModelLabel = 'пользователи';

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
                                    TextInput::make('name')
                                        ->label(__('resources.fields.name.animate'))
                                        ->required()
                                        ->maxLength(255)
                                        ->columnSpan('full'),

                                    FormGrid::make([
                                        'default' => 1,
                                        'sm' => 4,
                                        'xl' => 10,
                                    ])
                                        ->schema([
                                            TextInput::make('email')
                                                ->label(__('resources.fields.email'))
                                                ->email()
                                                ->afterStateUpdated(fn($livewire) => $livewire->validateOnly('data.email'))
                                                ->live(onBlur: true)
                                                ->required()
                                                ->maxLength(255)
                                                ->unique(User::class, 'email', ignoreRecord: true)
                                                ->columnSpan([
                                                    'default' => 1,
                                                    'sm' => 2,
                                                    'xl' => 5,
                                                ]),

                                            DateTimePicker::make('updated_at')
                                                ->label(__('resources.fields.updated_at'))
                                                ->default(now())
                                                ->columnSpan([
                                                    'default' => 1,
                                                    'sm' => 2,
                                                    'xl' => 5,
                                                ]),
                                        ]),

                                ])
                                    ->columnSpan([
                                        'default' => 1,
                                        'sm' => 4,
                                        'xl' => 10,
                                    ]),
                            ]),

                        Group::make([
                            Hidden::make('change_password_mode')
                                ->default(false)
                                ->dehydrated(false),

                            TextInput::make('password')
                                ->label(__('resources.fields.password'))
                                ->password()
                                ->revealable()
                                ->minLength(8)
                                ->live(onBlur: true)
                                ->dehydrated(fn($state) => filled($state))
                                ->required(fn($livewire) => $livewire->changePasswordMode)
                                ->visible(fn($livewire) => $livewire->changePasswordMode)
                                ->afterStateUpdated(function ($get, $livewire) {
                                    $livewire->validateOnly('data.password');

                                    if (filled($get('password_confirmation'))) {
                                        $livewire->validateOnly('data.password_confirmation');
                                    }
                                }),

                            TextInput::make('password_confirmation')
                                ->label(__('resources.fields.password_confirmation'))
                                ->password()
                                ->revealable()
                                ->minLength(8)
                                ->live(onBlur: true)
                                ->dehydrated(false)
                                ->same('password')
                                ->required(fn($livewire) => $livewire->changePasswordMode)
                                ->visible(fn($livewire) => $livewire->changePasswordMode)
                                ->afterStateUpdated(function ($get, $livewire) {
                                    $livewire->validateOnly('data.password_confirmation');

                                    if (filled($get('password'))) {
                                        $livewire->validateOnly('data.password');
                                    }
                                })

                        ])
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
                                    ->label(__('resources.fields.name.animate'))
                                    ->size('md')
                                    ->weight(FontWeight::Bold)
                                    ->searchable(),
                                TextColumn::make('email')
                                    ->label(__('resources.fields.email'))
                                    ->searchable(),
                                TextColumn::make('created_at')
                                    ->label(__('resources.fields.created_at'))
                                    ->dateTime('d M. Y H:i:s'),
                                TextColumn::make('updated_at')
                                    ->label(__('resources.fields.updated_at'))
                                    ->dateTime('d M. Y H:i:s'),
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
            ])->filtersFormWidth(Width::Small)

            ->bulkActions([]);
    }

    public static function configureDeleteAction(\Filament\Actions\DeleteAction $action): \Filament\Actions\DeleteAction
    {
        return $action
            ->modalHeading('Удаление пользователя')
            ->modalDescription('При удалении пользователя будут каскадно удалены все привязанные к нему записи: затраты, долги, переплаты и другие связанные данные. Это действие необратимо. Вы уверены, что хотите продолжить?');
    }

    protected static function getTableActions(): array
    {
        $actions = parent::getTableActions();

        foreach ($actions as $action) {
            if ($action instanceof \Filament\Actions\DeleteAction) {
                static::configureDeleteAction($action);
            }
        }

        return $actions;
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
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
