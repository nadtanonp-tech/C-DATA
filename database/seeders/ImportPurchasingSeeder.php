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
            $status = ($row->Status) ? 'Received' : 'Pending';
            // ถ้ามีวันที่รับของแล้ว ให้ถือว่า Received แน่นอน
            if (!empty($row->{'Recieve Date'})) $status = 'Received';

            // 4. เตรียมข้อมูล
            $batchData[] = [
                'instrument_id'   => $instrumentId,
                
                'pr_no'           => trim($row->{'PR No'} ?? ''),
                'pr_date'         => $this->parseDate($row->{'PR Date'}),
                'po_no'           => trim($row->{'PO No'} ?? ''),
                
                'vendor_name'     => trim($row->{'Place Cal'} ?? ''),     // ส่งไปแล็บไหน
                'requester'       => trim($row->{'Place Request'} ?? ''), // ใครขอ
                
                'quantity'        => (int) ($row->Amount ?? 1),
                'estimated_price' => (float) ($row->{'Price Request'} ?? 0),
                'net_price'       => (float) ($row->Price ?? 0),
                
                'status'          => $status,
                'receive_date'    => $this->parseDate($row->{'Recieve Date'}),
                'remark'          => trim($row->Remark ?? ''),
                
                'created_at'      => now(),
                'updated_at'      => now(),
            ];

            // Batch Insert
            if (count($batchData) >= $batchSize) {
                DB::table('purchasing_records')->insert($batchData);
                $batchData = [];
            }
        }

        if (!empty($batchData)) {
            DB::table('purchasing_records')->insert($batchData);
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