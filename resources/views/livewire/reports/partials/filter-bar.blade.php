@props(['title' => 'Report', 'subtitle' => null, 'showSearch' => true])

<div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 bg-sky-200 bg-card p-4 rounded-lg border border-border shadow-sm print:hidden">
    <div>
        <h2 class="text-lg font-semibold text-foreground">{{ $title }}</h2>
        @if ($subtitle)
            <p class="text-sm text-muted-foreground">{{ $subtitle }}</p>
        @endif
    </div>
    <div class="flex flex-wrap items-center gap-2">
        @if ($showSearch)
            <input type="text" wire:model.live.debounce.300ms="search" placeholder="Search..."
                class="h-9 w-[180px] rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring" />
        @endif

        <select wire:model.live="dateFilter"
            class="h-9 w-[160px] rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring">
            @foreach (\App\Enums\DatePeriod::cases() as $period)
                <option value="{{ $period->value }}">{{ $period->label() }}</option>
            @endforeach
        </select>

        <div x-show="$wire.dateFilter === 'custom'" x-transition class="flex items-center gap-2"
            x-data="{
                init() {
                    flatpickr(this.$refs.picker, {
                        mode: 'range',
                        dateFormat: 'Y-m-d',
                        defaultDate: [this.$wire.customStartDate, this.$wire.customEndDate],
                        onChange: (selectedDates, dateStr, instance) => {
                            if (selectedDates.length === 2) {
                                this.$wire.updateCustomRange(
                                    instance.formatDate(selectedDates[0], 'Y-m-d'),
                                    instance.formatDate(selectedDates[1], 'Y-m-d')
                                );
                            }
                        }
                    });
                }
            }">
            <input x-ref="picker" type="text"
                class="h-9 w-[240px] rounded-md border border-input bg-background px-3 py-1 text-sm shadow-sm focus-visible:outline-none focus-visible:ring-1 focus-visible:ring-ring"
                placeholder="Select date range...">
        </div>

        <button wire:click="refresh"
            class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground h-9 w-9"
            title="Refresh">
            <x-heroicon-o-arrow-path wire:loading.class="animate-spin" class="h-4 w-4" />
        </button>

        <button wire:click="exportExcel"
            class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-emerald-600 text-white hover:bg-emerald-700 h-9 px-3 gap-1">
            <x-heroicon-o-document-arrow-down class="h-4 w-4" />
            <span class="hidden sm:inline">Excel</span>
        </button>

        <button wire:click="exportPdf"
            class="inline-flex items-center justify-center whitespace-nowrap rounded-md text-sm font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 bg-rose-600 text-white hover:bg-rose-700 h-9 px-3 gap-1">
            <x-heroicon-o-document-text class="h-4 w-4" />
            <span class="hidden sm:inline">PDF</span>
        </button>
    </div>
</div>
