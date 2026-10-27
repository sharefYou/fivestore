<?php

namespace App\Filament\Pages;

use App\Models\SiteSetting;
use App\Support\ImageRules;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

/**
 * The small, fixed set of storefront texts an administrator may change.
 *
 * Deliberately not a page builder: every field below maps to one column of
 * the single site_settings row.
 */
class ManageSiteSettings extends Page
{
    protected string $view = 'filament.pages.manage-site-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?int $navigationSort = 3;

    /** @var array<string, mixed> */
    public array $data = [];

    public static function getNavigationGroup(): ?string
    {
        return __('admin.navigation.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('admin.settings.title');
    }

    public function getTitle(): string
    {
        return __('admin.settings.title');
    }

    /**
     * Shown in the sidebar only to those allowed to edit the settings.
     */
    public static function canAccess(): bool
    {
        return Gate::allows('update', SiteSetting::current());
    }

    public function mount(): void
    {
        // The page is reachable by URL, not only through the sidebar, so the
        // check is repeated here where it actually guards the data.
        $this->authorizeAccess();

        $this->form->fill(SiteSetting::current()->attributesToArray());
    }

    protected function authorizeAccess(): void
    {
        abort_unless(Gate::allows('update', SiteSetting::current()), 403);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('admin.settings.identity'))->schema([
                    Grid::make(2)->schema([
                        TextInput::make('site_name_ar')
                            ->label(__('admin.settings.site_name').' (AR)')
                            ->required()
                            ->maxLength(120)
                            ->extraInputAttributes(['dir' => 'rtl']),
                        TextInput::make('site_name_en')
                            ->label(__('admin.settings.site_name').' (EN)')
                            ->required()
                            ->maxLength(120)
                            ->extraInputAttributes(['dir' => 'ltr']),
                    ]),

                    FileUpload::make('logo_path')
                        ->label(__('admin.settings.logo'))
                        ->image()
                        ->disk(config('catalog.images.disk'))
                        ->directory(config('catalog.images.directories.site'))
                        ->visibility('public')
                        ->maxSize(ImageRules::maxKilobytes())
                        ->acceptedFileTypes(ImageRules::mimeTypes())
                        ->rules([ImageRules::file()])
                        ->getUploadedFileNameForStorageUsing(
                            fn (TemporaryUploadedFile $file): string => Str::uuid()->toString().'.'.$file->extension(),
                        ),
                ]),

                Section::make(__('admin.settings.hero'))->schema([
                    Grid::make(2)->schema([
                        TextInput::make('hero_title_ar')
                            ->label(__('admin.settings.hero_title').' (AR)')
                            ->maxLength(180),
                        TextInput::make('hero_title_en')
                            ->label(__('admin.settings.hero_title').' (EN)')
                            ->maxLength(180),
                        Textarea::make('hero_subtitle_ar')
                            ->label(__('admin.settings.hero_subtitle').' (AR)')
                            ->rows(3)
                            ->maxLength(500),
                        Textarea::make('hero_subtitle_en')
                            ->label(__('admin.settings.hero_subtitle').' (EN)')
                            ->rows(3)
                            ->maxLength(500),
                    ]),
                ]),

                Section::make(__('admin.settings.contact'))->schema([
                    Grid::make(2)->schema([
                        TextInput::make('contact_email')
                            ->label(__('admin.settings.contact_email'))
                            ->email()
                            ->maxLength(160),
                        TextInput::make('contact_phone')
                            ->label(__('admin.settings.contact_phone'))
                            ->tel()
                            ->maxLength(40),
                        TextInput::make('contact_address_ar')
                            ->label(__('admin.settings.contact_address').' (AR)')
                            ->maxLength(255),
                        TextInput::make('contact_address_en')
                            ->label(__('admin.settings.contact_address').' (EN)')
                            ->maxLength(255),
                    ]),
                ]),

                Section::make(__('admin.settings.social'))
                    ->collapsed()
                    ->schema([
                        Grid::make(3)->schema([
                            TextInput::make('social_facebook')
                                ->label(__('admin.settings.facebook'))
                                ->url()
                                ->maxLength(255),
                            TextInput::make('social_instagram')
                                ->label(__('admin.settings.instagram'))
                                ->url()
                                ->maxLength(255),
                            TextInput::make('social_whatsapp')
                                ->label(__('admin.settings.whatsapp'))
                                ->url()
                                ->maxLength(255),
                        ]),
                    ]),

                Section::make(__('admin.settings.seo'))
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            Textarea::make('meta_description_ar')
                                ->label(__('admin.settings.meta_description').' (AR)')
                                ->rows(2)
                                ->maxLength(255),
                            Textarea::make('meta_description_en')
                                ->label(__('admin.settings.meta_description').' (EN)')
                                ->rows(2)
                                ->maxLength(255),
                            TextInput::make('footer_text_ar')
                                ->label(__('admin.settings.footer_text').' (AR)')
                                ->maxLength(255),
                            TextInput::make('footer_text_en')
                                ->label(__('admin.settings.footer_text').' (EN)')
                                ->maxLength(255),
                        ]),
                    ]),
            ]);
    }

    public function save(): void
    {
        $this->authorizeAccess();

        $settings = SiteSetting::current();

        // Only the columns the model declares fillable are written, so a
        // crafted payload cannot reach anything else.
        $settings->fill($this->form->getState())->save();

        Notification::make()
            ->title(__('admin.settings.saved'))
            ->success()
            ->send();
    }
}
