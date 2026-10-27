<?php

namespace App\Filament\Resources\Categories\Tables;

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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                ImageColumn::make('image_path')
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

                TextColumn::make('products_count')
                    ->label(__('admin.fields.products_count'))
                    ->counts('products')
                    ->sortable(),

                ToggleColumn::make('is_active')
                    ->label(__('admin.fields.is_active')),

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
                TernaryFilter::make('is_active')->label(__('admin.fields.is_active')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    // CategoryPolicy refuses a category that still has products;
                    // this explains why instead of failing silently.
                    ->before(function (Category $record, DeleteAction $action): void {
                        if ($record->products()->withTrashed()->exists()) {
                            $action->failureNotificationTitle(__('admin.notifications.category_has_products'));
                            $action->failure();
                            $action->halt();
                        }
                    }),
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
