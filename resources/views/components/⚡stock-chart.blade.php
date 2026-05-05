<?php

namespace App\Http\Livewire;

use Livewire\Component;
use App\Models\Product; // Asumsikan model stok ada
use Carbon\Carbon;

class StockChart extends Component
{
    // Properti untuk menyimpan data grafik
    public $labels = [];
    public $data = [];

    public function mount()
    {
        $this->loadChartData();
    }

    public function loadChartData()
    {
        // Contoh: Mengambil data 7 hari terakhir
        $stocks = Stock::selectRaw('DATE(created_at) as date, sum(quantity) as total')
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $this->labels = $stocks->pluck('date')->toArray();
        $this->data = $stocks->pluck('total')->toArray();
    }

    public function render()
    {
        return view('livewire.stock-chart');
    }
}

