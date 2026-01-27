<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Gauge And Instrument Cal Plan</title>
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
            padding: 15px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            border: 1px solid #000;
            padding: 4px;
            text-align: center;
            font-size: 10px;
        }
        th {
            background-color: #f0f0f0;
        }
        .header-table {
            margin-bottom: 15px;
        }
        .header-table td {
            border: 1px solid #000;
            padding: 5px;
        }
        .no-border {
            border: none !important;
        }
        .text-left {
            text-align: left;
        }
        .date-range {
            writing-mode: vertical-rl;
            text-orientation: mixed;
            position: absolute;
            left: 20px;
            top: 100px;
        }
        .signature-section {
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <!-- Date Range (Vertical) -->
    <div style="position: absolute; left: 10px; top: 80px; writing-mode: vertical-rl; transform: rotate(180deg); font-size: 12px;">
        ระหว่างวันที่ {{ $startDate->format('d/m/Y') }} - {{ $endDate->format('d/m/Y') }}
    </div>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td rowspan="2" style="width: 30%; text-align: left;">
                <strong>The Siam Sanitary Fittings Co.,Ltd</strong><br>
                บริษัท สยามซานิทารีฟิตติ้งส์ จำกัด
            </td>
            <td style="width: 15%;">Doc. No.:</td>
            <td colspan="2">I - FM - CL - MY - 0003</td>
        </tr>
        <tr>
            <td>รหัสเอกสาร:</td>
            <td><strong>DQP-447</strong></td>
            <td>หน้า 1/{{ ceil($instruments->count() / 20) }}</td>
        </tr>
        <tr>
            <td colspan="4" style="text-align: left;">
                <strong>Document Title:</strong> แผนการสอบเทียบภายในโรงงานประจำเดือน
            </td>
        </tr>
        <tr>
            <td style="text-align: left;">ชื่อเอกสาร:</td>
            <td>Issue No: ปรับปรุงครั้งที่: A</td>
            <td>Rev. No.: เปลี่ยนแปลงครั้งที่: 1</td>
            <td>Issue Date: เริ่มใช้: {{ now()->format('d/m/Y') }}</td>
        </tr>
    </table>

    <!-- Info -->
    <table style="width: auto; margin-bottom: 10px;">
        <tr>
            <td style="padding: 5px 15px;"><strong>แผนก:</strong> {{ $department }}</td>
        </tr>
    </table>

    <!-- Main Table -->
    <table>
        <thead>
            <tr>
                <th style="width: 5%;">ลำดับ</th>
                <th style="width: 12%;">Code No</th>
                <th style="width: 15%;">Name</th>
                <th style="width: 15%;">Size</th>
                <th style="width: 10%;">Serial No</th>
                <th style="width: 10%;">Cal Date</th>
                <th style="width: 8%;">ผลการ CAL</th>
                <th style="width: 5%;">Level</th>
                <th style="width: 10%;">Remark</th>
                <th style="width: 10%;">Next Cal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($instruments as $index => $instrument)
            <tr>
                <td>{{ $index + 1 }}</td>
                <td class="text-left">{{ $instrument->code_no }}</td>
                <td class="text-left">{{ $instrument->toolType?->name }}</td>
                <td>{{ $instrument->toolType?->size }}</td>
                <td>{{ $instrument->serial_no ?? 'N/A' }}</td>
                <td>{{ $instrument->calibrationRecords->first()?->cal_date?->format('d-M-y') ?? '-' }}</td>
                <td>{{ $instrument->calibrationRecords->first()?->result ?? '-' }}</td>
                <td>{{ $instrument->calibrationRecords->first()?->level ?? '-' }}</td>
                <td class="text-left">{{ $instrument->calibrationRecords->first()?->remark ?? '-' }}</td>
                <td>{{ $instrument->next_cal_date?->format('d-M-y') ?? '-' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Signature Section -->
    <div class="signature-section" style="display: flex; justify-content: space-between; margin-top: 40px;">
        <div style="text-align: center; width: 45%;">
            <p>พน. ตรวจสอบฯ ............................................</p>
        </div>
        <div style="text-align: center; width: 45%;">
            <p>พศ.ประกันคุณภาพ ............................................</p>
        </div>
    </div>
</body>
</html>
