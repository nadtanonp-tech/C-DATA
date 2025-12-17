<div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden bg-white dark:bg-gray-800">
    <div class="max-h-96 overflow-y-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
                <tr>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700 dark:text-white">รหัส Master</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700 dark:text-white">ชื่อเครื่องมือ</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700 dark:text-white">ขนาด</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700 dark:text-white">ตรวจสอบจุด</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700 dark:text-white">Cal Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                @forelse($masters as $master)
                    <tr class="hover:bg-gray-50 dark:hover:bg-gray-800/50">
                        <td class="px-4 py-3 font-medium text-gray-900 dark:text-white">
                            {{ $master->master_code }}
                        </td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                            {{ $master->name }}
                        </td>
                        <td class="px-4 py-3 text-gray-700 dark:text-gray-200">
                            {{ $master->size ?? '-' }}
                        </td>
                        <td class="px-4 py-3">
                            @if($master->pivot && $master->pivot->check_point)
                                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium" style="background-color: #DBEAFE; color: #1E40AF;">
                                    {{ $master->pivot->check_point }}
                                </span>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">-</span>
                            @endif
                        </td>
                        <td class="px-4 py-3">
                            @php
                                $calStatus = $master->cal_status;
                            @endphp
                            
                            @if($calStatus === 'Pass')
                                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium" style="background-color: #D1FAE5; color: #065F46;">
                                    Pass
                                </span>
                            @elseif($calStatus === 'Reject')
                                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium" style="background-color: #FEE2E2; color: #991B1B;">
                                    Reject
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium" style="background-color: #F3F4F6; color: #1F2937;">
                                    Unknown
                                </span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-300">
                            ไม่มีข้อมูล Master
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>