<div class="flex items-center gap-4 p-3 rounded-lg bg-gray-50 dark:bg-gray-800 border border-gray-200 dark:border-gray-700">
    {{-- Point Name - เปลี่ยนจากวงกลมเป็น Badge ที่รองรับชื่อยาว --}}
    {{-- Trend --}}
    <div class="flex flex-col min-w-[80px]">
        <span class="text-xs text-gray-500 dark:text-gray-400">Trend</span>
        <span class="font-semibold text-sm {{ $trend === 'Smaller' ? 'text-blue-600' : ($trend === 'Bigger' ? 'text-orange-600' : 'text-gray-600') }}">
            @if($trend === 'Smaller')
                ↓ เล็กลง
            @elseif($trend === 'Bigger')
                ↑ ใหญ่ขึ้น
            @else
                ↔ ทั่วไป
            @endif
        </span>
    </div>
    
    {{-- Spec Range --}}
    @if($stdLabel !== 'วัดเกลียว')
        <div class="flex flex-col">
            <span class="text-xs text-gray-500 dark:text-gray-400">Min Spec</span>
            <span class="font-mono font-semibold text-sm text-green-600 dark:text-green-400">{{ $minSpec ?? '-' }}</span>
        </div>
        
        <div class="flex flex-col">
            <span class="text-xs text-gray-500 dark:text-gray-400">Max Spec</span>
            <span class="font-mono font-semibold text-sm text-red-600 dark:text-red-400">{{ $maxSpec ?? '-' }}</span>
        </div>
    @else
        <div class="flex flex-col">
            <span class="text-xs text-gray-500 dark:text-gray-400">Standard</span>
            <span class="font-mono font-semibold text-sm text-purple-600 dark:text-purple-400">{{ $minSpec ?? '-' }}</span>
        </div>
        <div class="px-2 py-1 rounded bg-purple-100 dark:bg-purple-900 text-purple-700 dark:text-purple-300 text-xs font-medium">
            วัดเกลียว
        </div>
    @endif
</div>
