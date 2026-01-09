<x-filament-widgets::widget>
    <style>
        .collapsible-table-container .fi-ta > .fi-ta-header {
            display: none !important;
        }
    </style>
    <div x-data="{ collapsed: false }" class="collapsible-table-container fi-wi-table rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div 
            @click="collapsed = !collapsed" 
            class="flex items-center justify-between gap-3 p-4 sm:px-6 cursor-pointer hover:bg-gray-50 dark:hover:bg-gray-800 transition-colors rounded-t-xl"
            :class="{ 'rounded-b-xl': collapsed }"
        >
            <div class="flex items-center gap-2">
                <x-heroicon-m-chevron-right 
                    class="w-5 h-5 text-gray-500 transition-transform duration-200" 
                    x-bind:class="{ 'rotate-90': !collapsed }"
                />
                <h3 class="text-base font-semibold leading-6 text-gray-950 dark:text-white">
                    {{ $this->getTableHeading() }}
                </h3>
            </div>
            <span class="text-sm text-gray-500">
                คลิกเพื่อ<span x-text="collapsed ? 'ขยาย' : 'ย่อ'"></span>
            </span>
        </div>
        
        <div x-show="!collapsed" x-collapse>
            {{ $this->table }}
        </div>
    </div>
</x-filament-widgets::widget>
