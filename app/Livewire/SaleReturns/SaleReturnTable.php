<?php

namespace App\Livewire\SaleReturns;

use Carbon\Carbon;
use App\Models\SaleReturn;
use App\Services\SaleReturnService;
use App\Exceptions\SaleReturnException;
use Illuminate\Database\Eloquent\Builder;
use PowerComponents\LivewirePowerGrid\Button;
use PowerComponents\LivewirePowerGrid\Column;
use PowerComponents\LivewirePowerGrid\Facades\Filter;
use PowerComponents\LivewirePowerGrid\PowerGridFields;
use PowerComponents\LivewirePowerGrid\Facades\PowerGrid;
use PowerComponents\LivewirePowerGrid\Traits\WithExport;
use PowerComponents\LivewirePowerGrid\PowerGridComponent;
use PowerComponents\LivewirePowerGrid\Components\SetUp\Exportable;

final class SaleReturnTable extends PowerGridComponent
{
    use WithExport;

    public string $tableName = 'sale-returns-table';
    public string $sortField = 'created_at';
    public string $sortDirection = 'desc';

    public function boot(): void
    {
        config(['livewire-powergrid.filter' => 'outside']);
    }

    public function setUp(): array
    {
        $this->showCheckBox();

        return [
            PowerGrid::exportable('sale_returns_export_' . now()->format('Y_m_d'))
                ->type(Exportable::TYPE_XLS, Exportable::TYPE_CSV),
            PowerGrid::header()->showSearchInput(),
            PowerGrid::footer()->showPerPage()->showRecordCount(),
        ];
    }

    public function datasource(): Builder
    {
        return SaleReturn::query()->with(['sale.customer', 'creator']);
    }

    public function fields(): PowerGridFields
    {
        return PowerGrid::fields()
            ->add('id')
            ->add('return_number')
            ->add('invoice_number', fn(SaleReturn $m) => $m->sale?->invoice_number ?: '-')
            ->add('customer_name',  fn(SaleReturn $m) => $m->sale?->customer?->name ?? 'Guest')
            ->add('return_date_formatted', fn(SaleReturn $m) => Carbon::parse($m->return_date)->format('d/m/Y'))
            ->add('total_refund_formatted', fn(SaleReturn $m) => format_money($m->total_refund))
            ->add('creator_name',  fn(SaleReturn $m) => $m->creator?->name ?? '-')
            ->add('created_at');
    }

    public function columns(): array
    {
        return [
            Column::action('Action'),
            Column::make('ID', 'id')->hidden(),
            Column::make('Return #', 'return_number')->searchable()->sortable(),
            Column::make('Invoice', 'invoice_number', 'sale_id')->searchable()->sortable(),
            Column::make('Customer', 'customer_name'),
            Column::make('Date', 'return_date_formatted', 'return_date')->sortable(),
            Column::make('Refund', 'total_refund_formatted', 'total_refund')
                ->sortable()
                ->headerAttribute('text-right')->bodyAttribute('text-right'),
            Column::make('Created By', 'creator_name', 'created_by'),
        ];
    }

    public function relationSearch(): array
    {
        return [
            'sale' => ['invoice_number'],
        ];
    }

    public function filters(): array
    {
        return [
            Filter::datepicker('return_date_formatted', 'return_date')
                ->params([
                    'enableTime' => false,
                    'dateFormat' => 'Y-m-d',
                    'altInput'   => true,
                    'altFormat'  => 'd/m/Y',
                ]),
        ];
    }

    public function actions(SaleReturn $row): array
    {
        return [
            Button::add('view')
                ->slot('View')
                ->class('bg-blue-500 hover:bg-blue-600 text-white px-3 py-1 rounded')
                ->route('sale-returns.show', ['saleReturn' => $row->id]),

            Button::add('print')
                ->slot('Print')
                ->class('bg-indigo-500 hover:bg-indigo-600 text-white px-3 py-1 rounded')
                ->route('sale-returns.print', ['saleReturn' => $row->id]),

            Button::add('delete')
                ->slot('Delete')
                ->class('bg-red-500 hover:bg-red-600 text-white px-3 py-1 rounded')
                ->dispatch('open-delete-modal', [
                    'component'   => 'sale-returns.sale-return-table',
                    'method'      => 'delete',
                    'params'      => ['rowId' => $row->id],
                    'title'       => 'Delete Return?',
                    'description' => "Delete return '{$row->return_number}'? Stock will be decremented again and the refund finance entry removed.",
                ]),
        ];
    }

    #[\Livewire\Attributes\On('delete')]
    public function delete($rowId, SaleReturnService $service): void
    {
        $return = SaleReturn::find($rowId);
        if (!$return) {
            return;
        }
        try {
            $service->deleteReturn($return);
            $this->dispatch('toast', message: 'Return deleted successfully.', type: 'success');
        } catch (SaleReturnException $e) {
            $this->dispatch('toast', message: 'Delete failed: ' . $e->getMessage(), type: 'error');
        } catch (\Exception $e) {
            $this->dispatch('toast', message: 'Error: ' . $e->getMessage(), type: 'error');
        }
    }
}
