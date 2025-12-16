<div class="rounded-lg border border-gray-200 dark:border-gray-700 overflow-hidden bg-white dark:bg-gray-800">
    <div class="max-h-96 overflow-y-auto">
        <table class="w-full text-sm">
            <thead class="bg-gray-50 dark:bg-gray-800 sticky top-0">
                <tr>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700 dark:text-white">รหัส Master</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700 dark:text-white">ชื่อเครื่องมือ</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700 dark:text-white">ขนาด</th>
                    <th class="px-4 py-2 text-left font-semibold text-gray-700 dark:text-white">ตรวจสอบจุด</th>
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
                                <span class="inline-flex items-center px-2 py-1 rounded-md text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200">
                                    {{ $master->pivot->check_point }}
                                </span>
                            @else
                                <span class="text-gray-400 dark:text-gray-500">-</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-300">
                            ไม่มีข้อมูล Master
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
