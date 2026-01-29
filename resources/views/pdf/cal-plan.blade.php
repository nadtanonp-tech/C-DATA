<!DOCTYPE html>
<html lang="th">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Monthly In-house Calibration Schedule</title>
    <style>
        @page {
            margin-top: 180px; 
            margin-bottom: 80px;
            margin-left: 25px;
            margin-right: 25px;
        }
        header {
            position: fixed;
            top: -150px;
            left: 0px;
            right: 0px;
            height: 140px;
        }
        #last-page-footer {
            position: absolute;
            bottom: -50px;
            left: 0px;
            right: 0px;
            height: 50px;
            border-top: 1px solid #000;
            padding-top: 5px;
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
            padding: 0;
            line-height: 1.1;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            border-spacing: 0;
        }
        th, td {
            border: 1px solid #000;
            padding: 5px;
            vertical-align: middle;
            font-size: 14pt; /* Table content font size adjusted */
        }
        th {
            background-color: transparent; 
            text-align: center;
            font-weight: bold;
        }
        .header-table td {
            border: 1px solid #000;
            padding: 5px;
            vertical-align: top;
            height: 30px;
        }
        .no-border { border: none !important; }
        .text-left { text-align: left; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .bold { font-weight: bold; }
        
        /* Header Column Widths */
        .w-left { width: 45%; }      
        .w-right-1 { width: 18.33%; } 
        .w-right-2 { width: 18.33%; } 
        .w-right-3 { width: 18.33%; } 

        .page-count:after {
            content: "หน้าที่ " counter(page) " / " counter(pages);
        }
    </style>
</head>
<body>
    
    <header>
        <table class="header-table" cellspacing="0" cellpadding="0">
            <!-- Row 1 -->
            <tr>
                <td class="w-left text-left" style="vertical-align: top;">
                    <span class="bold">The Siam Sanitary Fittings Co.,Ltd</span><br>
                    <span>บริษัท สยามซานิทารีฟิตติ้งส์ จำกัด</span>
                </td>
                <td colspan="2" class="text-left" style="vertical-align: middle;">
                    <div style="margin-bottom: 2px;">
                        <span class="bold">Doc. No.: 1 - FM - CL - MY - 0003</span>
                    </div>
                    <div>
                        <span>รหัสเอกสาร:</span>
                        <span class="bold " style="float: right; font-size: 16pt;">DQP-447</span>
                    </div>
                </td>
                <td class="w-right-3 text-left" style="vertical-align: middle;">
                    &nbsp;
                </td>
            </tr>
    
            <!-- Row 2 -->
            <tr>
                <td class="w-left text-left" style="vertical-align: top;">
                    <span class="bold">Document Title :</span><span class ="bold"> Monthly In-house Calibration Schedule</span><br>
                    <span>ชื่อเอกสาร :</span> แผนการสอบเทียบภายในโรงงานประจำเดือน
                </td>
                <td class="w-right-1 text-left" style="vertical-align: top;">
                    <span class="bold">Issue No :</span><br>
                    ปรับปรุงครั้งที่ : <span class="bold">A</span>
                </td>
                <td class="w-right-2 text-left" style="vertical-align: top;">
                    <span class="bold">Rev. No. :</span><br>
                    เปลี่ยนแปลงครั้งที่ : <span class="bold">1</span>
                </td>
                <td class="w-right-3 text-left" style="vertical-align: top;">
                    <span class="bold">Issue Date :</span><br>
                    เริ่มใช้ : <span class="bold">24 / 07 / 46</span>
                </td>
            </tr>
        </table>
    </header>




    <!-- Info -->
    @foreach($instruments->groupBy('department') as $dept => $items)
    <div style="margin-bottom: 10px; margin-top: -15px; margin-left: 30px; page-break-after: avoid;">
        <span class="bold">แผนก : {{ $dept }}</span>
    </div>

    <!-- Table -->
    <table style="width: 100%; border-collapse: collapse; font-size: 10pt; margin-bottom: 20px;">
        <thead>
            <tr>
                <th style="width: 6%;">ลำดับ</th>
                <th style="width: 10%;">Code No</th>
                <th style="width: 22%;">Name</th>
                <th style="width: 18%;">Size</th>
                <th style="width: 8%;">Serial No</th>
                <th style="width: 9%;">Cal Date</th>
                <th style="width: 6%;">ผลการCAL</th>
                <th style="width: 4%;">Level</th>
                <th style="width: 10%;">Remark</th>
                <th style="width: 9%;">Next Cal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($items as $index => $instrument)
            <tr>
                <td class="text-center">{{ $index + 1 }}</td>
                <td class="text-center">{{ $instrument->code_no }}</td>
                <td class="text-center">{{ $instrument->tool_name }}</td>
                <td class="text-center">{{ $instrument->tool_size }}</td>
                <td class="text-center">{{ $instrument->serial_no ?? 'N/A' }}</td>
                <td class="text-center">{{ $instrument->cal_date?->format('d-M-y') ?? '-' }}</td>
                <td class="text-center">{{ $instrument->result_status ?? '-' }}</td>
                <td class="text-center">{{ $instrument->cal_level ?? '-' }}</td>
                <td class="text-center">{{ $instrument->remark ?? '-' }}</td>
                <td class="text-center">{{ $instrument->next_cal_date?->format('d-M-y') ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endforeach

    <div id="last-page-footer">
        <div style="position: absolute; left: 15px; top: 15px; writing-mode: vertical-rl; font-size: 14pt; font-weight: bold;">
            ระหว่างวันที่ {{ $startDate->format('d/m/Y') }} - {{ $endDate->format('d/m/Y') }}
        </div>

        <table style="border: none; width: 100%; margin-left: 50px;">
            <tr class="no-border">
                <td class="no-border text-right" style="width: 50%; padding-top: 5px;"></td> 
                <td class="no-border text-left" style="width: 40%; padding-top: 10px; padding-left: 90px; ">
                    <span class="bold">หน.ตรวจสอบฯ</span> .................................................
                </td>
                <td class="no-border text-left" style="width: 40%; padding-top: 10px; padding-left: 30px; ">
                    <span class="bold">หน.ประกันคุณภาพฯ</span> ..............................................
                </td>
            </tr>
        </table>
    </div>

    <script type="text/php">
        if (isset($pdf)) {
            $font = $fontMetrics->get_font("Angsana New", "bold");
            if (!$font) {
                $font = $fontMetrics->get_font("sans-serif", "bold");
            }
            // Landscape A4 width is ~842pt. Position near right margin.
            $x = 680; 
            $y = 50; // Match header vertical align
            $size = 13;
            $color = array(0, 0, 0);
            $text = "หน้าที่ 1 / {PAGE_NUM}";
            $pdf->page_text($x, $y, $text, $font, $size, $color);
        }
    </script>
</body>
</html>
