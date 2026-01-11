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
            
            <!-- Right: Reset button -->
            <button 
                type="button"
                wire:click="resetFilters"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 text-sm font-medium text-gray-600 bg-gray-100 rounded-lg hover:bg-gray-200 transition-colors dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700"
            >
                <x-heroicon-o-arrow-path class="w-4 h-4" wire:loading.class="animate-spin" wire:target="resetFilters" />
                รีเซ็ต
            </button>
        </div>
        
        <!-- Filters using Filament Form -->
        {{ $this->form }}
    </div>
</x-filament-widgets::widget>
