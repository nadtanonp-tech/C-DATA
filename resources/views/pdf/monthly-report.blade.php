<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Monthly Report</title>
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
            padding: 20px;
        }
        .header {
            border: 1px solid #000;
            margin-bottom: 20px;
        }
        .header-row {
            display: flex;
            border-bottom: 1px solid #000;
        }
        .header-cell {
            padding: 5px 10px;
            border-right: 1px solid #000;
        }
        .header-cell:last-child {
            border-right: none;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #000;
            padding: 5px;
            text-align: center;
            font-size: 12px;
        }
        th {
            background-color: #f0f0f0;
        }
        .text-left {
            text-align: left;
        }
        .section-title {
            font-weight: bold;
            margin: 10px 0;
        }
        .info-box {
            border: 1px solid #000;
            padding: 5px 10px;
            margin-bottom: 10px;
            display: inline-block;
        }
        .signature-section {
            margin-top: 50px;
            text-align: right;
        }
        .signature-line {
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <table style="margin-bottom: 20px;">
        <tr>
            <td rowspan="3" style="width: 40%; text-align: left;">
                <strong>The Siam Sanitary Fittings Co.,Ltd</strong><br>
                บริษัท สยามซานิทารีฟิตติ้งส์ จำกัด
            </td>
            <td style="width: 20%;">Doc. No.:</td>
            <td colspan="2">I - FM - CL - MY - 0002</td>
        </tr>
        <tr>
            <td>รหัสเอกสาร:</td>
            <td style="width: 15%;"><strong>DQP-484</strong></td>
            <td style="width: 15%;">หน้าที่ 1/1</td>
        </tr>
        <tr>
            <td colspan="3">
                <strong>Document Title:</strong> ใบรายงานสรุปผลการสอบเทียบประจำเดือน
            </td>
        </tr>
        <tr>
            <td style="text-align: left;">ชื่อเอกสาร:</td>
            <td>Issue No:</td>
            <td>Rev. No.:</td>
            <td>Issue Date:</td>
        </tr>
        <tr>
            <td></td>
            <td>ปรับปรุงครั้งที่: A</td>
            <td>เปลี่ยนแปลงครั้งที่: 2</td>
            <td>เริ่มใช้: {{ now()->format('d/m/Y') }}</td>
        </tr>
    </table>

    <!-- Info Section -->
    <table style="width: auto; margin-bottom: 15px;">
        <tr>
            <td style="border: 1px solid #000; padding: 5px 15px;">
                <strong>Month:</strong> {{ $startDate->format('F Y') }}
            </td>
        </tr>
        <tr>
            <td style="border: 1px solid #000; padding: 5px 15px;">
                <strong>Status:</strong> Plan
            </td>
        </tr>
    </table>

    @foreach($data as $typeName => $items)
    <table style="width: auto; margin-bottom: 10px;">
        <tr>
            <td style="border: 1px solid #000; padding: 5px 15px;">
                <strong>Type:</strong> {{ $typeName }}
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th>Department</th>
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
                <td class="text-left">{{ $item->department }}</td>
                <td>{{ $item->plan_count }}</td>
                <td>{{ $item->cal_count }}</td>
                <td>{{ $item->cal_percent }}</td>
                <td>{{ $item->level_a }}</td>
                <td>{{ $item->level_a_percent }}</td>
                <td>{{ $item->level_b }}</td>
                <td>{{ $item->level_b_percent }}</td>
                <td>{{ $item->level_c }}</td>
                <td>{{ $item->level_c_percent }}</td>
                <td>{{ $item->remain_count }}</td>
                <td>{{ $item->remain_percent }}</td>
            </tr>
            <tr>
                <td class="text-left" colspan="12">
                    <strong>Remark:</strong> {{ $item->remark ?? '-' }}
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @endforeach

    <!-- Signature Section -->
    <div class="signature-section">
        <p>พน. ตรวจสอบฯ ............................................</p>
        <br>
        <p>ผศ.ประกันคุณภาพ ............................................</p>
    </div>
</body>
</html>
