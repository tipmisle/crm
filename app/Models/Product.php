<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A catalog item of type "product" — the Ponudba entries orders are created
 * from. Backed by the same catalog_items table as Service; scoped to
 * type=product so both remain distinct in every query and route-model bind.
 */
class Product extends CatalogItem
{
    protected static function booted(): void
    {
        static::addGlobalScope('type', fn ($query) => $query->where('type', 'product'));

        static::creating(fn (Product $product) => $product->type = 'product');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'catalog_item_id');
    }
}
