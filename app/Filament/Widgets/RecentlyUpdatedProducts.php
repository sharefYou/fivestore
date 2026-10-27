<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * The real edit history available without an audit log: the rows themselves,
 * newest change first.
 */
class RecentlyUpdatedProducts extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('admin.dashboard.recent_updates'))
            ->query(
                fn (): Builder => Product::query()
                    ->with('category')
                    ->latest('updated_at')
            )
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->emptyStateHeading(__('admin.dashboard.no_recent'))
            ->columns([
                TextColumn::make('name_ar')->label(__('admin.fields.name_ar'))->limit(40),
                TextColumn::make('name_en')->label(__('admin.fields.name_en'))->limit(40),
                TextColumn::make('category.name_en')->label(__('admin.fields.category')),
                IconColumn::make('is_active')->label(__('admin.fields.is_active'))->boolean(),
                TextColumn::make('updated_at')->label(__('admin.fields.updated_at'))->since(),
            ])
            ->recordActions([
                Action::make('edit')
                    ->url(fn (Product $record): string => ProductResource::getUrl('edit', ['record' => $record]))
                    ->icon('heroicon-m-pencil-square')
                    ->iconButton(),
            ]);
    }
}
