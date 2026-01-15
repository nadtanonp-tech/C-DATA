<div class="relative flex items-center" x-data="{ open: false }">
    {{-- Trigger Button --}}
    <button 
        @click="open = !open" 
        @click.outside="open = false"
        type="button"
        class="fi-topbar-item flex items-center justify-center gap-x-2 rounded-lg px-3 py-2 text-sm font-medium outline-none transition duration-75 hover:bg-gray-50 focus-visible:bg-gray-50 dark:hover:bg-white/5 dark:focus-visible:bg-white/5 text-gray-700 dark:text-gray-200"
    >
        <x-heroicon-o-plus-circle class="h-5 w-5" />
        <span>New Calibration Report</span>
        <x-heroicon-m-chevron-down class="h-4 w-4 transition duration-75" x-bind:class="{ 'rotate-180': open }" />
    </button>

    {{-- Dropdown Menu - Right aligned --}}
    <div 
        x-show="open"
        x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="transform opacity-0 scale-95"
        x-transition:enter-end="transform opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-75"
        x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="fixed z-50 mt-1 w-72 rounded-xl bg-white shadow-lg ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10"
        x-ref="dropdown"
        x-init="$watch('open', value => {
            if (value) {
                $nextTick(() => {
                    const button = $el.previousElementSibling;
                    const rect = button.getBoundingClientRect();
                    $refs.dropdown.style.top = (rect.bottom + 4) + 'px';
                    $refs.dropdown.style.right = (window.innerWidth - rect.right) + 'px';
                    $refs.dropdown.style.left = 'auto';
                });
            }
        })"
    >
        <div class="p-1 max-h-96 overflow-y-auto">
            @foreach ($menuItems as $group)
                <div class="mb-1 last:mb-0">
                    {{-- Group Header --}}
                    <div class="flex items-center gap-2 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">
                        <x-dynamic-component :component="$group['icon']" class="h-4 w-4" />
                        {{ $group['label'] }}
                    </div>

                    {{-- Group Items --}}
                    <div class="space-y-0.5">
                        @foreach ($group['items'] as $item)
                            <a 
                                href="{{ $item['url'] }}" 
                                class="fi-dropdown-list-item flex w-full items-center gap-2 whitespace-nowrap rounded-md px-3 py-2 text-sm outline-none transition duration-75 hover:bg-gray-50 focus-visible:bg-gray-50 dark:hover:bg-white/5 dark:focus-visible:bg-white/5 text-gray-700 dark:text-gray-200"
                                @click="open = false"
                            >
                                <x-dynamic-component :component="$item['icon']" class="h-4 w-4 text-primary-500" />
                                {{ $item['label'] }}
                            </a>
                        @endforeach
                    </div>
                </div>

                @if (!$loop->last)
                    <div class="my-1 border-t border-gray-200 dark:border-white/5"></div>
                @endif
            @endforeach
        </div>
    </div>
</div>
