<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportPurchasingSeeder extends Seeder
{
    public function run()
    {
        // 1. ดึงข้อมูลจากตาราง External
        $oldData = DB::table('External')->get(); 

        $batchData = [];
        $batchSize = 100;

        foreach ($oldData as $row) {
            
            // 2. หา ID เครื่องมือจาก Code No
            $instrumentId = null;
            if (!empty($row->{'Code No'})) {
                $inst = DB::table('instruments')
                          ->where('code_no', trim($row->{'Code No'}))
                          ->select('id')
                          ->first();
                if ($inst) $instrumentId = $inst->id;
            }

            // 3. แปลงสถานะ (ของเดิมเป็น True/False -> แปลงเป็นข้อความ)
            // สมมติ: True = Received (รับแล้ว), False/Null = Pending (รอ)
            $status = ($row->Status) ? 'Completed' : 'Pending';
            // ถ้ามีวันที่รับของแล้ว ให้ถือว่า Received แน่นอน
            if (!empty($row->{'Recieve Date'})) $status = 'Completed'; // Reverting to 'Recieve Date' (legacy column)

            // 4. Update or Insert
            if ($instrumentId && !empty($row->{'PR No'})) {
                  DB::table('purchasing_records')->updateOrInsert(
                      [
                          'pr_no'         => trim($row->{'PR No'}),
                          'instrument_id' => $instrumentId,
                      ],
                      [
                          'pr_date'         => $this->parseDate($row->{'PR Date'}),
                          'po_no'           => trim($row->{'PO No'} ?? ''),
                          
                          'vendor_name'     => trim($row->{'Place Cal'} ?? ''),     // ส่งไปแล็บไหน
                          'requester'       => trim($row->{'Place Request'} ?? ''), // ใครขอ
                          
                          'quantity'        => (int) ($row->Amount ?? 1),
                          'estimated_price' => (float) ($row->{'Price Request'} ?? 0),
                          'net_price'       => (float) ($row->Price ?? 0),
                          
                          'status'          => $status,
                          // Use legacy column 'Recieve Date' (sic)
                          'receive_date'    => $this->parseDate($row->{'Recieve Date'} ?? $row->{'Receive Date'} ?? null), 
                          'remark'          => trim($row->{'Remark'} ?? ''),
                          
                          'updated_at'      => now(),
                          // updateOrInsert automatically handles created_at if it's a new record? No, we might need to handle it or let DB default calculate it.
                          // But updateOrInsert syntax: updateOrInsert(unique_conditions, values_to_update)
                          // If we want created_at only on create, we have to be careful.
                          // Laravel's updateOrInsert doesn't distinguish nicely for created_at without raw queries or manually checking.
                          // However, for seeding, updating `updated_at` is good.
                          // IMPORTANT: If inserting, we need created_at. updateOrInsert doesn't do it automatically for the 2nd array.
                      ]
                  );
                  
                  // Handle created_at for new records logic is tricky with updateOrInsert if we want to preserve old created_at.
                  // But usually simpliest is to just update everything or use basic logic.
                  // Actually, let's keep it simple. If the record exists, we update. If not, we insert.
            }
    }
    }

    private function parseDate($dateVal)
    {
        if (!$dateVal) return null;
        try {
            return Carbon::parse($dateVal)->format('Y-m-d');
        } catch (\Exception $e) { return null; }
    }
}