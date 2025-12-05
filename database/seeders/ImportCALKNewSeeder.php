<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ImportCALKNewSeeder extends Seeder
{
    public function run()
    {
        // 1. ดึงข้อมูลจากตารางเก่า (CALKNew)
        $oldLogs = DB::table('CALKNew')->get();

        $batchData = [];
        $batchSize = 50; 

        foreach ($oldLogs as $row) {
            
            // 2. หา ID เครื่องมือ
            $instrument = DB::table('instruments')
                            ->where('code_no', trim($row->CodeNo))
                            ->select('id')
                            ->first();

            if (!$instrument) continue; 

            // 3. ปั้น JSON (ความยากอยู่ที่ตรงนี้ เพราะฟิลด์เยอะมาก)
            $results = [];
            $chars = ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q'];

            foreach ($chars as $char) {
                // เก็บค่าพื้นฐานที่มีทุกตัว
                $item = [
                    'Result' => $row->{'Result'.$char} ?? null,
                    'Lip'    => $row->{'Result'.$char.'Lip'} ?? null,
                    'Judge'  => $row->{'Judge'.$char} ?? null,
                    'Grade'  => $row->{'Grade'.$char} ?? null,
                ];

                // เก็บค่าพิเศษ (Special Cases)
                // กลุ่ม A, B, C มี Major/Pitch
                if (in_array($char, ['A', 'B', 'C'])) {
                    $item['Major']       = $row->{'Result'.$char.'Major'} ?? null;
                    $item['Major_Judge'] = $row->{'Judge'.$char.'Major'} ?? null;
                    $item['Pitch']       = $row->{'Result'.$char.'Pitch'} ?? null;
                    $item['Pitch_Judge'] = $row->{'Judge'.$char.'Pitch'} ?? null;
                }
                
                // กลุ่ม A, B มี Part
                if (in_array($char, ['A', 'B'])) {
                    $item['Part']        = $row->{'Result'.$char.'Part'} ?? null;
                }

                // กลุ่ม D มี Fit/Wear
                if ($char === 'D') {
                    $item['Fit']         = $row->{'ResultDFit'} ?? null;
                    $item['Fit_Judge']   = $row->{'JudgeDFit'} ?? null;
                    $item['Wear']        = $row->{'ResultDWear'} ?? null;
                    $item['Wear_Judge']  = $row->{'JudgeDWear'} ?? null;
                }

                // กรองค่าว่างออก (จะได้ไม่รก JSON)
                $cleanedItem = array_filter($item, function($v) { return !is_null($v) && $v !== ''; });
                
                if (!empty($cleanedItem)) {
                    $results[$char] = $cleanedItem;
                }
            }

            // รวบรวม Master ที่ใช้ (1-7)
            $masters = [];
            for($i=1; $i<=7; $i++) {
                if(!empty($row->{'CALMaster'.$i})) $masters[] = $row->{'CALMaster'.$i};
            }

            // รวมร่าง JSON
            $calData = [
                'Type' => 'K-Gauge / Special',
                'Readings' => $results,
                'MastersUsed' => $masters,
                'TotalGrade' => $row->Grade
            ];

            // 4. เตรียมข้อมูลบันทึก
            $batchData[] = [
                'instrument_id' => $instrument->id,
                'cal_date'      => $this->parseDate($row->CalDate),
                'next_cal_date' => $this->parseDate($row->DueDate),
                'cal_by'        => trim($row->Section),
                'cal_place'     => 'Internal',
                
                'calibration_data' => json_encode($calData, JSON_UNESCAPED_UNICODE),
                
                'environment'   => "Temp: {$row->Temp} / Humid: {$row->Humidity}",
                'result_status' => trim($row->Total),
                'remark'        => trim($row->RemarkC),
                'legacy_source_table' => 'CALKNew',
                
                'created_at'    => now(),
                'updated_at'    => now(),
            ];

            if (count($batchData) >= $batchSize) {
                DB::table('calibration_logs')->insert($batchData);
                $batchData = [];
            }
        }

        if (!empty($batchData)) {
            DB::table('calibration_logs')->insert($batchData);
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