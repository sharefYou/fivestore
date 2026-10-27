<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Category;
use App\Models\Product;
use App\Services\SlugGenerator;
use App\Support\ImageRules;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('translations')
                    ->tabs([
                        Tabs\Tab::make(__('admin.locales.ar'))
                            ->schema(self::translatedFields('ar'))
                            ->extraAttributes(['dir' => 'rtl']),
                        Tabs\Tab::make(__('admin.locales.en'))
                            ->schema(self::translatedFields('en'))
                            ->extraAttributes(['dir' => 'ltr']),
                    ])
                    ->columnSpanFull(),

                Section::make(__('admin.product.classification'))
                    ->schema([
                        Grid::make(2)->schema([
                            Select::make('category_id')
                                ->label(__('admin.fields.category'))
                                ->relationship('category', 'name_en')
                                // The label follows the admin's language, while
                                // the stored value is still the category id.
                                ->getOptionLabelFromRecordUsing(
                                    fn (Category $record): string => $record->translate('name') ?? ''
                                )
                                ->searchable()
                                ->preload()
                                ->required()
                                // exists() is the server-side guarantee that the
                                // posted id is a real category.
                                ->rule('exists:categories,id'),

                            TextInput::make('sku')
                                ->label(__('admin.fields.sku'))
                                ->maxLength(64)
                                ->unique(Product::class, 'sku', ignoreRecord: true)
                                ->helperText(__('admin.hints.sku_unique')),
                        ]),
                    ]),

                Section::make(__('admin.product.media'))
                    ->description(__('admin.hints.primary_image'))
                    ->schema([
                        Repeater::make('images')
                            ->hiddenLabel()
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->reorderable()
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['alt_en'] ?? null)
                            ->schema([
                                FileUpload::make('path')
                                    ->label(__('admin.fields.image'))
                                    ->image()
                                    ->required()
                                    ->disk(config('catalog.images.disk'))
                                    ->directory(config('catalog.images.directories.products'))
                                    ->visibility('public')
                                    ->maxSize(ImageRules::maxKilobytes())
                                    ->acceptedFileTypes(ImageRules::mimeTypes())
                                    ->rules([ImageRules::file()])
                                    ->getUploadedFileNameForStorageUsing(
                                        fn (TemporaryUploadedFile $file): string => Str::uuid()->toString().'.'.$file->extension(),
                                    ),

                                Grid::make(2)->schema([
                                    TextInput::make('alt_ar')
                                        ->label(__('admin.fields.alt').' (AR)')
                                        ->maxLength(180),
                                    TextInput::make('alt_en')
                                        ->label(__('admin.fields.alt').' (EN)')
                                        ->maxLength(180),
                                ]),

                                Toggle::make('is_primary')
                                    ->label(__('admin.fields.is_primary'))
                                    ->default(false),
                            ])
                            ->defaultItems(0)
                            ->addActionLabel(__('admin.fields.images'))
                            // The database allows only one primary row per
                            // product; this check reports it as a form error
                            // instead of letting the insert fail.
                            ->rule(function (): \Closure {
                                return function (string $attribute, mixed $value, \Closure $fail): void {
                                    $primaries = collect($value)
                                        ->filter(fn (array $item): bool => (bool) ($item['is_primary'] ?? false))
                                        ->count();

                                    if ($primaries > 1) {
                                        $fail(__('admin.validation.single_primary'));
                                    }

                                    if ($primaries === 0 && count($value) > 0) {
                                        $fail(__('admin.validation.primary_required'));
                                    }
                                };
                            }),
                    ]),

                Section::make(__('admin.product.publishing'))
                    ->schema([
                        Grid::make(3)->schema([
                            Toggle::make('is_active')
                                ->label(__('admin.fields.is_active'))
                                ->helperText(__('admin.hints.product_is_active'))
                                ->default(false),

                            Toggle::make('is_featured')
                                ->label(__('admin.fields.is_featured'))
                                ->default(false),

                            TextInput::make('sort_order')
                                ->label(__('admin.fields.sort_order'))
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(65535)
                                ->default(0)
                                ->required(),
                        ]),
                    ]),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    protected static function translatedFields(string $locale): array
    {
        return [
            TextInput::make("name_{$locale}")
                ->label(__('admin.fields.name'))
                ->required()
                ->maxLength(160)
                ->live(onBlur: true)
                ->afterStateUpdated(function (?string $state, callable $get, callable $set) use ($locale): void {
                    if (filled($get("slug_{$locale}")) || blank($state)) {
                        return;
                    }

                    $set("slug_{$locale}", app(SlugGenerator::class)->slugify($state, $locale));
                }),

            TextInput::make("slug_{$locale}")
                ->label(__('admin.fields.slug'))
                ->required()
                ->maxLength(180)
                ->rule('regex:/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u')
                ->validationMessages(['regex' => __('admin.validation.slug')])
                ->unique(Product::class, "slug_{$locale}", ignoreRecord: true)
                ->helperText(__('admin.hints.slug', ['locale' => $locale])),

            Textarea::make("description_{$locale}")
                ->label(__('admin.fields.description'))
                ->rows(5)
                ->maxLength(5000),
        ];
    }
}
