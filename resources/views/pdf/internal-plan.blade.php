<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Internal Calibration Plan {{ $startDate->format('F Y') }}</title>
    <style>
        @page {
            margin: 30px;
        }
        @font-face {
            font-family: 'Angsana';
            src: url('{{ storage_path("fonts/Angsa.ttf") }}') format('truetype');
            font-weight: normal;
        }
        @font-face {
            font-family: 'Angsana';
            src: url('{{ storage_path("fonts/Angsab.ttf") }}') format('truetype');
            font-weight: bold;
        }
        @font-face {
            font-family: 'Angsana';
            src: url('{{ storage_path("fonts/Angsai.ttf") }}') format('truetype');
            font-style: italic;
        }
        @font-face {
            font-family: 'Angsana';
            src: url('{{ storage_path("fonts/Angsabi.ttf") }}') format('truetype');
            font-weight: bold;
            font-style: italic;
        }
        body {
            font-family: 'Angsana', sans-serif;
            font-size: 16px;
            margin: 0;
            padding: 0;
        }
        .header {
            font-family: 'Angsana', sans-serif;
            margin-bottom: 20px;
            border-top: 1px solid #000;
            border-bottom: none;
        }
        h1 {
            font-size: 30px;
            font-style: italic;
            font-weight: normal;
            margin: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            margin-top: 10px;
        }
        th {
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            border-left: none;
            border-right: none;
            padding: 4px;
            text-align: center;
            font-weight: bold;
            font-size: 16px;
        }
        td {
            border: 1px solid #000;
            padding: 4px;
            text-align: center;
            vertical-align: top;
            font-size: 16px;
        }
        thead th {
            border-top: 2px solid #000;
            border-bottom: 2px solid #000;
        }
        
        .footer {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            height: 100px;
            border-top: 2px solid #000;
            padding-top: 10px;
        }
        .note-section {
            margin-top: 10px;
            float: left;
            width: 50%;
            font-size: 16px;
        }
        .signature-section {
            float: right;
            width: 50%;
            text-align: left;
            font-size: 14px;
        }
        .signature-line {
            margin-bottom: 20px;
        }
        .clearfix::after {
            content: "";
            clear: both;
            display: table;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Internal Calibration Plan</h1>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 20%;">Month</th>
                <th style="width: 20%;">Department</th>
                <th style="width: 30%;">Type</th>
                <th style="width: 15%;">Set/Pcs.</th>
                <th style="width: 15%;">Level</th>
            </tr>
            <!-- Spacer Row moved to thead to repeat on every page -->
            <tr style="height: 10px;">
                <th colspan="5" style="border: none; padding: 0; font-size: 4px;">&nbsp;</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $month => $departments)
                @foreach($departments as $dept => $items)
                    @foreach($items as $item)
                    <tr>
                        <td>{{ $month }}</td>
                        <td>{{ $dept }}</td>
                        <td style="text-align: center;">{{ $item->calibration_type }}</td>
                        <td>{{ $item->plan_count }}</td>
                        <td>{{ $level }}</td>
                    </tr>
                    @endforeach
                @endforeach
            @endforeach
    </table>
    
    <div style="height: 100px;"></div>

    <div class="footer clearfix">
        <div class="note-section">
            <div><strong>หมายเหตุ :</strong> Level A คือ สภาพปกติ ; Level B คือ ใกล้ชำรุด ( สีเหลือง )</div>
            <div style="margin-left: 55px;">ดูรายละเอียดได้ใน Calibration Wep Page</div>
            <div style="margin-top: 10px;"><strong>กรุณาส่งเกจและเครื่องมือวัดสอบเทียบตามระยะเวลาที่กำหนด</strong></div>
        </div>
        <div class="signature-section">
            <table style="width: 100%; border: none;">
                <tr>
                    <td style="border: none; text-align: left; padding: 0;">พนง.สอบเทียบฯส่ง....................................</td>
                    <td style="border: none; text-align: left; padding: 0;">ผู้รับเอกสาร..........................................</td>
                </tr>
                <tr>
                    <td style="border: none; text-align: left; padding-top: 15px;">วันที่ส่ง..............................................</td>
                    <td style="border: none; text-align: left; padding-top: 15px;">วันที่รับ..............................................</td>
                </tr>
            </table>
        </div>
    </div>
</body>
</html>
