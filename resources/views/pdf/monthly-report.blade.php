<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>ใบรายงานสรุปผลการสอบเทียบประจำเดือน {{ $startDate->format('F Y') }}</title>
    <style>
        @page {
            margin-top: 180px; /* Increased space for Fixed Header */
            margin-left: 25px;
            margin-right: 25px;
        }
        header {
            position: fixed;
            top: -150px;
            left: 0px;
            right: 0px;
            height: 130px;
        }
        @font-face {
            font-family: 'Angsana New';
            src: url('{{ storage_path("fonts/Angsa.ttf") }}') format('truetype');
            font-weight: normal;
        }
        @font-face {
            font-family: 'Angsana New';
            src: url('{{ storage_path("fonts/Angsab.ttf") }}') format('truetype');
            font-weight: bold;
        }
        body {
            font-family: 'Angsana New', sans-serif;
            font-size: 16pt; 
            margin: 0;
            padding: 0; /* Clear padding handled by @page */
            line-height: 1.1;
        }

        .bold { font-weight: bold; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        .text-right { text-align: right; }
        
        /* Table Default */
        table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
            margin-bottom: 10px;
        }
        
        td, th {
            border: 1px solid #000;
            padding: 5px; 
            vertical-align: middle;
            font-size: 14pt;
        }

        /* --- Header Configuration --- */
        .w-left { width: 45%; }      
        .w-right-1 { width: 18.33%; } 
        .w-right-2 { width: 18.33%; } 
        .w-right-3 { width: 18.33%; } 

        .header-table td {
            vertical-align: top;
            height: 30px; 
        }
        
        /* Type Box */
        .type-container { margin-bottom: 5px; margin-top: 10px; }
        .type-label { 
            font-weight: bold; 
            display: inline-block; 
            width: 50px; 
            margin-right: 5px;
        }
        .type-box { 
            border: 1px solid #000; 
            padding: 3px 40px; 
            display: inline-block; 
            min-width: 150px; 
            text-align: center; 
            font-weight: bold; 
        }

        /* --- Data Table --- */
        .data-table th {
            background-color: #fff;
            font-weight: bold;
            text-align: center;
            font-size: 12pt;
            padding: 4px;
        }
        .data-table td {
            text-align: center;
            font-size: 12pt;
            height: 24px;
        }
        
        /* Remark Styling */
        .row-data td { /* border-bottom: none !important; REMOVED */ }
        .row-remark td { 
            /* border-top: none !important; REMOVED */
            text-align: left; 
            vertical-align: top;
            padding-top: 0;
            height: 25px;
        }
        
        /* Remark Label & Content */
        .remark-label {
            border: 1px solid #000;
            /* border-top: none; REMOVED */
            text-align: center;
            font-weight: bold;
            vertical-align: top;
            padding-top: 4px;
            width: 12%; 
        }
        .remark-content {
            border: 1px solid #000;
            /* border-top: none; REMOVED */
            border-left: none; 
            vertical-align: top;
            padding-top: 4px;
        }
        
        /* Department Column Adjustments - REMOVED */
        /* .data-table td:first-child {
            border-bottom: none; 
        } */

        /* Signature */
        .no-border { border: none !important; }

    </style>
</head>
<body>

<header>
   <table class="header-table" cellspacing="0" cellpadding="0">
    <!-- แถวที่ 1 -->
    <tr>
        <td class="w-left text-left" style="vertical-align: top;">
            <span class="bold">The Siam Sanitary Fittings Co.,Ltd</span><br>
            <span>บริษัท สยามซานิทารีฟิตติ้งส์ จำกัด</span>
            
        </td>
        <td colspan="2" class="text-left" style="vertical-align: middle;">
            <div style="margin-bottom: 2px;">
                <span class="bold">Doc. No.:  1 - FM - CL - MY - 0002</span>
            </div>
            <div>
                <span>รหัสเอกสาร:</span>
                <span class="bold" style="float: right;">DQP-484</span>
            </div>
        </td>
        <td class="w-right-3 text-left" style="vertical-align: bottom;">
            <!-- Content filled by PHP Script -->
            &nbsp;
        </td>
    </tr>

    <!-- แถวที่ 3 -->
    <tr>
        <td class="w-left text-left" style="vertical-align: top;">
            <span class="bold">Document Title :</span><span> ใบรายงานสรุปผลการสอบเทียบประจำเดือน</span><br>
            <span>ชื่อเอกสาร :</span> 
        </td>
        <td class="w-right-1 text-left" style="vertical-align: top;">
            <span class="bold">Issue No :</span><br>
            ปรับปรุงครั้งที่ : <span class="bold">A</span>
        </td>
        <td class="w-right-2 text-left" style="vertical-align: top;">
            <span class="bold">Rev. No. :</span><br>
            เปลี่ยนแปลงครั้งที่ : <span class="bold">2</span>
        </td>
        <td class="w-right-3 text-left" style="vertical-align: top;">
            <span class="bold">Issue Date :</span><br>
            เริ่มใช้ : <span class="bold">24 / 03 / 47</span>
        </td>
    </tr>
</table>
</header>

    @foreach($data as $typeName => $items)
    
    <div style="margin-bottom: 20px; margin-top: {{ $loop->first ? '5px' : '20px' }}; page-break-after: avoid; page-break-inside: avoid;">
        <table style="width: 35%;" cellspacing="0" cellpadding="0">
            @if($loop->first)
            <tr>
                <td width="35%" class="bold text-center">Month:</td>
                <td class="bold text-center">{{ $startDate->format('F Y') }}</td> 
            </tr>
            @endif
            <tr>
                <td class="bold text-center">Status:</td>
                <td class="bold text-center">{{ $items->first()->status ?? '-' }}</td>
            </tr>
            <tr>
                <td width="35%" class="bold text-center">Type :</td>
                <td class="bold text-center">{{ \Illuminate\Support\Str::headline($typeName) }}</td>
            </tr>
        </table>
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th width="12%">Department</th>
                <th>Plan</th>
                <th>Cal</th>
                <th>% Cal</th>
                <th>Level A</th>
                <th>%Level A</th>
                <th>Level B</th>
                <th>%Level B</th>
                <th>Level C</th>
                <th>%Level C</th>
                <th>Remain</th>
                <th>% Remain</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $item)
            <tr>
                <td class="bold">{{ $item->department }}</td>
                <td class="bold">{{ $item->plan_count }}</td>
                <td class="bold">{{ $item->cal_count }}</td>
                <td class="bold">{{ $item->cal_percent }}</td>
                <td class="bold">{{ $item->level_a }}</td>
                <td class="bold">{{ $item->level_a_percent }}</td>
                <td class="bold">{{ $item->level_b }}</td>
                <td class="bold">{{ $item->level_b_percent }}</td>
                <td class="bold">{{ $item->level_c }}</td>
                <td class="bold">{{ $item->level_c_percent }}</td>
                <td class="bold">{{ $item->remain_count }}</td>
                <td class="bold">{{ $item->remain_percent }}</td>
            </tr>
            <tr>
                <td class="remark-label bold">Remark:</td>
                <td colspan="11" class="text-left remark-content">
                    {{ $item->remark ?? '' }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    
    @endforeach

    <table style="border: none; margin-top: 20px;">
        <tr class="no-border">
            <td class="no-border text-right" style="width: 50%;"></td> <td class="no-border text-right" style="width: 50%;">
                <span class="bold">หน.ตรวจสอบฯ</span> ......................................................................
            </td>
        </tr>
        <tr class="no-border">
            <td class="no-border text-right" style="width: 50%;"></td> <td class="no-border text-right" style="width: 50%;">
                <span class="bold">หผ.ประกันคุณภาพ</span> .................................................................
            </td>
        </tr>
    </table>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font("Angsana New", "bold");
            if (!$font) {
                $font = $fontMetrics->get_font("sans-serif", "bold");
            }
            $x = 480;
            $y = 50;
            $size = 13;
            $color = array(0, 0, 0);
            $text = "หน้าที่ 1 / {PAGE_NUM}";
            $pdf->page_text($x, $y, $text, $font, $size, $color);
        }
    </script>
</body>
</html>