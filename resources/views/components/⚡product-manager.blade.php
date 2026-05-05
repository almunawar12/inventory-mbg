<?php


namespace App\Livewire;


use Livewire\Component;
use Livewire\WithFileUploads;
use App\Models\InventoryItem;
use Maatwebsite\Excel\Facades\Excel;
use Livewire\Attributes\Layout;
use App\Imports\InventoryImport;
use Livewire\WithPagination;


#[Layout('layouts.app')]
class ProductManager extends Component
{
    use WithFileUploads;
    use WithPagination;


    public $file;


    public function import()
    {
        $this->validate([
            'file' => 'required|mimes:xls,xlsx',
        ]);


        Excel::import(new ProductImport, $this->file->path());


        session()->flash('message', 'Product imported successfully.');
    }


    public function render()
    {
        $items = ProductItem::paginate(5);
        return view('livewire.product-manager', [
            'items' => $items,
        ]);
    }


    public function delete($id)
    {
        ProductItem::find($id)->delete();
        session()->flash('message', 'Product Deleted Successfully.');
    }
}