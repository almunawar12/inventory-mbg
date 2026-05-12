<?php

namespace App\Livewire\Reports;

use Livewire\Component;
use App\Models\Product;

class ProductStockCard extends Component
{
    public Product $product;

    public function mount(Product $product): void
    {
        $this->product = $product;
    }

    public function render()
    {
        return view('livewire.reports.product-stock-card', [
            'product' => $this->product,
        ]);
    }
}
