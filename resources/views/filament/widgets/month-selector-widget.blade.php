<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-heroicon-m-calendar class="w-5 h-5" />
                <span>เลือกเดือนที่ต้องการดู</span>
            </div>
        </x-slot>
        
        <div class="flex items-center gap-4">
            {{ $this->form }}
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
