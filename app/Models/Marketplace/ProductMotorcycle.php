<?php

namespace App\Models\Marketplace;

use App\Models\MotorcycleBrand;
use App\Models\MotorcycleModel;
use App\Models\MotorcycleYear;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductMotorcycle extends MarketplaceModel
{
    protected $table = 'marketplace_product_motorcycles';

    protected $fillable = ['product_id', 'moto_brand_id', 'moto_model_id', 'moto_year_id', 'is_universal'];

    protected $casts = ['is_universal' => 'boolean'];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(MotorcycleBrand::class, 'moto_brand_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(MotorcycleModel::class, 'moto_model_id');
    }

    public function year(): BelongsTo
    {
        return $this->belongsTo(MotorcycleYear::class, 'moto_year_id');
    }
}
