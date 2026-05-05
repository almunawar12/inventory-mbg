<x-modal name="product-form-modal" :title="''" maxWidth="2xl">
    <div class="p-6">
        <!-- Custom Header -->
        <div class="mb-6 space-y-1.5 text-center sm:text-left border-b border-gray-200 pb-4">
            <h3 class="text-lg font-semibold leading-none tracking-tight text-foreground">
                {{ $isEditing ? 'Edit Product' : 'Create Product' }}
            </h3>
            <p class="text-sm text-muted-foreground">
                {{ $isEditing ? 'Make changes to your product here. Click save when you\'re done.' : 'Add a new product to your inventory.' }}
            </p>
       
        </div>

        @unless($isEditing)
        <div class="mb-4 p-3 rounded-md border border-dashed border-gray-300 bg-gray-50">
            <div class="flex items-center justify-between gap-2 mb-2">
                <h4 class="text-sm font-medium">Bulk Import dari Excel</h4>
                <a href="{{ route('products.template') }}"
                   class="text-xs text-sky-600 hover:underline">
                    ⬇ Download Template
                </a>
            </div>
            @if(session('success'))
                <div class="text-xs text-emerald-700 bg-emerald-50 px-2 py-1 rounded mb-2">
                    {{ session('success') }}
                </div>
            @endif
            @if(session('warning'))
                <div class="text-xs text-amber-700 bg-amber-50 px-2 py-1 rounded mb-2">
                    {{ session('warning') }}
                </div>
            @endif
            @if(session('failures') && count(session('failures')) > 0)
                <details class="text-xs bg-red-50 px-2 py-1 rounded mb-2">
                    <summary class="cursor-pointer text-red-700">Lihat detail error ({{ count(session('failures')) }} baris)</summary>
                    <ul class="mt-1 max-h-40 overflow-y-auto space-y-0.5">
                        @foreach(session('failures') as $f)
                            <li class="text-red-700">
                                Baris {{ $f->row() }} kolom <b>{{ $f->attribute() }}</b>:
                                {{ implode(', ', $f->errors()) }}
                                @if($f->values())
                                    <span class="text-red-500">(nilai: "{{ $f->values()[$f->attribute()] ?? '' }}")</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif
            <form action="{{ route('product.import') }}" method="POST" enctype="multipart/form-data"
                  class="flex items-center gap-2">
                @csrf
                <input type="file" name="file" accept=".xls,.xlsx" required
                       class="text-xs flex-1 border rounded px-2 py-1">
                <button type="submit" class="text-xs px-3 py-1 rounded bg-sky-600 text-white hover:bg-sky-700">
                    Import
                </button>
            </form>
        </div>
        @endunless
        <form wire:submit="save" class="space-y-6">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- SKU -->
                @if($isEditing)
                    <x-form-input
                        name="sku"
                        label="SKU (Stock Keeping Unit)"
                        type="text"
                        wire:model="sku"
                        readonly
                        placeholder="e.g. SKU-1234-ABCD"
                        class="bg-muted text-muted-foreground cursor-not-allowed"
                    />
                @else
                    <!-- SKU Auto Generated -->
                    <div class="hidden">
                        <input type="hidden" wire:model="sku">
                    </div>
                @endif

                <!-- Name -->
                <x-form-input
                    name="name"
                    label="Product Name"
                    placeholder="e.g. Wireless Mouse"
                    type="text"
                    wire:model="name"
                    required
                    class="{{ !$isEditing ? 'col-span-2' : '' }}"
                />
            </div>

            <!-- Row 2: Category & Unit -->
            <div class="flex flex-col sm:flex-row gap-6">
                <!-- Category -->
                <div class="w-full sm:w-1/2 space-y-2">
                    <x-input-label for="category_id" :value="__('Category')" required />
                    <div wire:ignore>
                        <x-tom-select
                            id="category_id"
                            name="category_id"
                            wire:model="category_id"
                            :url="route('ajax.categories.search')"
                            method="POST"
                            placeholder="Select Category"
                            data-initial-label="{{ $categoryName }}"
                        />
                    </div>
                    <x-input-error :messages="$errors->get('category_id')" />
                </div>

                <!-- Unit -->
                <div class="w-full sm:w-1/2 space-y-2">
                    <x-input-label for="unit_id" :value="__('Unit')" required />
                    <div wire:ignore>
                        <x-tom-select
                            id="unit_id"
                            name="unit_id"
                            wire:model="unit_id"
                            :url="route('ajax.units.search')"
                            method="POST"
                            placeholder="Select Unit"
                            data-initial-label="{{ $unitName }}"
                        />
                    </div>
                    <x-input-error :messages="$errors->get('unit_id')" />
                </div>
            </div>

            <!-- Prices (Forced Inline) -->
            <div class="flex flex-col sm:flex-row gap-6">
                <!-- Purchase Price -->
                <div class="w-full sm:w-1/2 space-y-2">
                    <x-input-label for="purchase_price" :value="__('Purchase Price') . ' (' . \App\Models\Setting::get('currency_symbol', 'Rp') . ')'" />
                    <x-currency-input
                        id="purchase_price"
                        wire:model.live.debounce.500ms="purchase_price"
                        placeholder="0"
                        required
                    />
                    <x-input-error :messages="$errors->get('purchase_price')" />
                </div>

                <!-- Selling Price -->
                <div class="w-full sm:w-1/2 space-y-2">
                    <x-input-label for="selling_price" :value="__('Selling Price') . ' (' . \App\Models\Setting::get('currency_symbol', 'Rp') . ')'" />
                    <x-currency-input
                        id="selling_price"
                        wire:model.live.debounce.500ms="selling_price"
                        placeholder="0"
                        required
                    />
                    <x-input-error :messages="$errors->get('selling_price')" />
                </div>
            </div>

            <!-- Row 5: Qty, Min Stock, Active -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Quantity -->
                <x-form-input
                    name="quantity"
                    label="Quantity"
                    type="number"
                    wire:model="quantity"
                    min="0"
                    placeholder="0"
                    required
                />

                <!-- Min Stock -->
                <x-form-input
                    name="min_stock"
                    label="Min Stock Alert"
                    type="number"
                    wire:model="min_stock"
                    min="0"
                    placeholder="0"
                    required
                />

                <!-- Is Active -->
                <div class="flex items-center h-full pt-8">
                    <label class="inline-flex items-center cursor-pointer">
                        <input
                            type="checkbox"
                            wire:model="is_active"
                            class="w-6 h-6 rounded-full border-2 border-primary text-primary focus:ring-primary/20"
                        >
                        <span class="ml-3 text-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ __('Active') }}
                        </span>
                    </label>
                </div>
            </div>

            <!-- Description -->
            <div class="space-y-2">
                <x-input-label for="description" value="Description" />
                <textarea
                    id="description"
                    wire:model="description"
                    rows="3"
                    class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    placeholder="Optional description..."
                ></textarea>
                <x-input-error :messages="$errors->get('description')" />
            </div>

            <!-- Notes -->
            <div class="space-y-2">
                <x-input-label for="notes" value="Internal Notes" />
                <textarea
                    id="notes"
                    wire:model="notes"
                    rows="3"
                    class="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                    placeholder="Internal pricing history & notes..."
                ></textarea>
                <x-input-error :messages="$errors->get('notes')" />
            </div>

            <!-- Actions -->
            <div class="mt-6 flex justify-end gap-3 border-t pt-4 border-gray-200">
                <x-secondary-button type="button" x-on:click="$dispatch('close-modal', { name: 'product-form-modal' })">
                    {{ __('Cancel') }}
                </x-secondary-button>

                <x-primary-button type="submit" wire:loading.attr="disabled">
                    <svg wire:loading wire:target="save" class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    <x-heroicon-o-check wire:loading.remove wire:target="save" class="w-4 h-4 mr-2" />
                    {{ $isEditing ? __('Save Changes') : __('Create Product') }}
                </x-primary-button>
            </div>
        </form>
    </div>
</x-modal>
