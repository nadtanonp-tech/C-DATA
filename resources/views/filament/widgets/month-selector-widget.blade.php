<x-filament-widgets::widget>
    <div class="fi-wi-stats-overview-stat relative rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <!-- Header with Reset on Right -->
        <div class="flex items-center justify-between mb-4">
            <!-- Left: Icon and Title -->
            <div class="flex items-center gap-3">
                <div class="flex items-center justify-center w-10 h-10 rounded-lg bg-primary-500/10 dark:bg-primary-400/10">
                    <x-heroicon-o-funnel class="w-5 h-5 text-primary-500 dark:text-primary-400" />
                </div>
                <div>
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">
                        ตัวกรองข้อมูล
                    </h3>
                    <p class="text-xs text-gray-500 dark:text-gray-400">
                        เลือกเดือน ปี และ Level ที่ต้องการแสดง
                    </p>
                </div>
            </div>
            
            <!-- Right: Search and Reset buttons -->
            <div class="flex items-center gap-2">
                <button 
                    type="button"
                    wire:click="dispatchFilters"
                    class="group inline-flex items-center gap-1.5 px-4 py-2 text-sm font-medium text-white bg-primary-600 rounded-lg shadow-sm transition-all duration-200 ease-in-out hover:bg-primary-500 hover:shadow-md hover:scale-[1.02] active:scale-[0.98] dark:bg-primary-500 dark:hover:bg-primary-400"
                >
                    <x-heroicon-o-magnifying-glass class="w-4 h-4 transition-all duration-200 ease-in-out group-hover:scale-110 group-hover:rotate-12" wire:loading.class="animate-pulse" wire:target="dispatchFilters" />
                    <span class="transition-all duration-200 ease-in-out group-hover:tracking-wider group-hover:font-semibold">ค้นหา</span>
                </button>
                <button 
                    type="button"
                    wire:click="resetFilters"
                    class="group inline-flex items-center gap-1.5 px-3 py-2 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg shadow-sm transition-all duration-200 ease-in-out hover:bg-gray-200 hover:text-gray-800 hover:shadow-md hover:scale-[1.02] active:scale-[0.98] dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 dark:hover:text-white"
                >
                    <x-heroicon-o-arrow-path class="w-4 h-4 transition-all duration-200 ease-in-out group-hover:scale-110 group-hover:-rotate-180" wire:loading.class="animate-spin" wire:target="resetFilters" />
                    <span class="transition-all duration-200 ease-in-out group-hover:tracking-wider group-hover:font-semibold">รีเซ็ต</span>
                </button>
            </div>
        </div>
        
        <!-- Filters using Filament Form -->
        {{ $this->form }}
    </div>
</x-filament-widgets::widget>
