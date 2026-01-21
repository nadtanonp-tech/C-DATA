@php
    // ดึงข้อมูล Instrument จาก ID ที่ส่งมา
    $instrument = null;
    $picturePath = null;
    
    if ($instrumentId) {
        $instrument = \App\Models\Instrument::with('toolType')->find($instrumentId);
        
        // ถ้าเจอ Instrument และมี ToolType
        if ($instrument && $instrument->toolType) {
            $picturePath = $instrument->toolType->picture_path;
        }
    }
@endphp

<div class="w-full">
    @if($picturePath)
        {{-- แสดงรูปภาพถ้ามี --}}
        <div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden bg-white dark:bg-gray-800">
            <img 
                src="{{ asset('storage/' . $picturePath) }}" 
                alt="Tool Type Drawing" 
                class="w-full h-auto object-contain max-h-96"
            />
        </div>
        
        {{-- แสดงข้อมูลเพิ่มเติม --}}
        <div class="mt-2 text-xs text-gray-500 dark:text-gray-400">
            <p class="font-medium">{{ $instrument->toolType->name ?? 'N/A' }}</p>
            <p>Drawing No. {{ $instrument->toolType->drawing_no ?? 'N/A' }}</p>
        </div>
    @else
        {{-- กรณีไม่มีรูปภาพ --}}
        <div class="flex items-center justify-center h-48 bg-gray-100 dark:bg-gray-800 rounded-lg border-2 border-dashed border-gray-300 dark:border-gray-600">
            <div class="text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                    {{ $instrumentId ? 'ไม่มีรูปภาพ Drawing' : 'กรุณาเลือกเครื่องมือก่อน' }}
                </p>
            </div>
        </div>
    @endif
</div>
