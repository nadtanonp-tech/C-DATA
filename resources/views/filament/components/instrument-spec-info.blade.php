<div class="p-3 bg-blue-50 rounded-lg border border-blue-200">
    <div class="flex items-center gap-4 text-sm">
        <div class="flex items-center gap-1">
            <span class="font-semibold text-purple-700">Cs:</span>
            <span class="font-mono text-purple-900">{{ is_numeric($csValue) ? rtrim(rtrim(number_format((float)$csValue, 6), '0'), '.') : ($csValue ?? '0') }} mm.</span>
        </div>
    </div>
</div>
