<?php

namespace App\Filament\Resources\Categories\Schemas;

use App\Models\Category;
use App\Services\SlugGenerator;
use App\Support\ImageRules;
use Filament\Forms\Components\FileUpload;
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

class CategoryForm
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

                Section::make(__('admin.category.media'))
                    ->schema([
                        FileUpload::make('image_path')
                            ->label(__('admin.fields.image'))
                            ->image()
                            ->disk(config('catalog.images.disk'))
                            ->directory(config('catalog.images.directories.categories'))
                            ->visibility('public')
                            ->maxSize(ImageRules::maxKilobytes())
                            // The browser is told what to offer; the rules below
                            // are what actually decides, on the server.
                            ->acceptedFileTypes(ImageRules::mimeTypes())
                            ->rules([ImageRules::file()])
                            ->getUploadedFileNameForStorageUsing(
                                fn (TemporaryUploadedFile $file): string => Str::uuid()->toString().'.'.$file->extension(),
                            )
                            ->helperText(__('admin.hints.image', [
                                'types' => strtoupper(implode(', ', ImageRules::extensions())),
                                'size' => ImageRules::maxKilobytes() / 1024,
                            ])),
                    ]),

                Section::make(__('admin.category.publishing'))
                    ->schema([
                        Grid::make(2)->schema([
                            Toggle::make('is_active')
                                ->label(__('admin.fields.is_active'))
                                ->helperText(__('admin.hints.category_is_active'))
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
                // Only fills an empty slug: an existing URL is never silently
                // rewritten when someone corrects a title.
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
                // Soft-deleted rows keep their slug, exactly as the database
                // unique index does, so a restored category still resolves.
                ->unique(Category::class, "slug_{$locale}", ignoreRecord: true)
                ->helperText(__('admin.hints.slug', ['locale' => $locale])),

            Textarea::make("description_{$locale}")
                ->label(__('admin.fields.description'))
                ->rows(4)
                ->maxLength(2000),
        ];
    }
}
