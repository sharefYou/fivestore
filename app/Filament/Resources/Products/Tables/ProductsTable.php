<?php

namespace App\Filament\Resources\Products\Tables;

use App\Models\Category;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('primaryImage.path')
                    ->label(__('admin.fields.image'))
                    ->disk(config('catalog.images.disk'))
                    ->square(),

                TextColumn::make('name_ar')
                    ->label(__('admin.fields.name_ar'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name_en')
                    ->label(__('admin.fields.name_en'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('category.name_en')
                    ->label(__('admin.fields.category'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('sku')
                    ->label(__('admin.fields.sku'))
                    ->searchable()
                    ->toggleable(),

                ToggleColumn::make('is_active')
                    ->label(__('admin.fields.is_active')),

                ToggleColumn::make('is_featured')
                    ->label(__('admin.fields.is_featured')),

                TextColumn::make('sort_order')
                    ->label(__('admin.fields.sort_order'))
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('updated_at')
                    ->label(__('admin.fields.updated_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('category_id')
                    ->label(__('admin.fields.category'))
                    ->options(fn (): array => Category::query()
                        ->orderBy('sort_order')
                        ->get()
                        ->mapWithKeys(fn (Category $category): array => [
                            $category->id => $category->translate('name') ?? '',
                        ])
                        ->all())
                    ->searchable(),

                TernaryFilter::make('is_active')->label(__('admin.fields.is_active')),
                TernaryFilter::make('is_featured')->label(__('admin.fields.is_featured')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
