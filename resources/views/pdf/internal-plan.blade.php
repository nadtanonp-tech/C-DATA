<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Internal Calibration Plan</title>
    <style>
        @font-face {
            font-family: 'THSarabun';
            src: url('{{ storage_path("fonts/THSarabunNew.ttf") }}') format('truetype');
            font-weight: normal;
        }
        @font-face {
            font-family: 'THSarabun';
            src: url('{{ storage_path("fonts/THSarabunNew Bold.ttf") }}') format('truetype');
            font-weight: bold;
        }
        body {
            font-family: 'THSarabun', sans-serif;
            font-size: 14px;
            margin: 0;
            padding: 30px;
        }
        h1 {
            font-style: italic;
            font-size: 24px;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 8px;
            text-align: center;
        }
        th {
            background-color: #f0f0f0;
            font-weight: bold;
        }
        .text-left {
            text-align: left;
        }
        .note-section {
            margin-top: 300px;
            font-size: 12px;
        }
        .signature-section {
            margin-top: 50px;
        }
        .signature-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        .signature-box {
            width: 45%;
        }
    </style>
</head>
<body>
    <h1>Internal Calibration Plan</h1>

    <table>
        <thead>
            <tr>
                <th style="width: 20%;">Month</th>
                <th style="width: 20%;">Department</th>
                <th style="width: 30%;">Type</th>
                <th style="width: 15%;">Set/Pcs.</th>
                <th style="width: 15%;">Level</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $month => $departments)
                @foreach($departments as $dept => $items)
                    @foreach($items as $item)
                    <tr>
                        <td>{{ $month }}</td>
                        <td>{{ $dept }}</td>
                        <td class="text-left">{{ $item->toolType?->name ?? '-' }}</td>
                        <td>{{ $item->plan_count }}</td>
                        <td>A</td>
                    </tr>
                    @endforeach
                @endforeach
            @endforeach
        </tbody>
    </table>

    <!-- Large empty space for content -->
    <div style="min-height: 300px;"></div>

    <!-- Notes -->
    <div class="note-section">
        <p><strong>หมายเหตุ:</strong> Level A คือ สภาพลิปกติ ; Level B คือ ใกล้ข่างุด (สีเหลือง)</p>
        <p style="margin-left: 50px;">ดูรายละเอียดได้ใน Calibration Web Page</p>
        <p><strong>กรุณาส่งเกจและเครื่องมือวัดสอบเทียบตามระยะเวลาที่กำหนด</strong></p>
    </div>

    <!-- Signature Section -->
    <div class="signature-section">
        <table style="border: none; width: 100%;">
            <tr>
                <td style="border: none; width: 50%; text-align: left;">
                    พน.สอบเทียบฯส่ง............................................<br><br>
                    วันที่ส่ง............................................
                </td>
                <td style="border: none; width: 50%; text-align: right;">
                    ผู้รับเอกสาร............................................<br><br>
                    วันที่รับ............................................
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
