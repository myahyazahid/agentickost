@php
    use App\Support\Reports\ReportRowKind;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $sheet->title }}</title>
    <style>
        @page { margin: 32px 36px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; line-height: 1.4; }
        h1 { font-size: 15px; margin: 0 0 2px; }
        .muted { color: #4b5563; margin-bottom: 14px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 4px 6px; text-align: left; vertical-align: top; }
        th { background: #f3f4f6; border-bottom: 1px solid #9ca3af; }
        td { border-bottom: 1px solid #e5e7eb; }
        td.money, th.money { text-align: right; white-space: nowrap; }
        tr.heading td { font-weight: bold; padding-top: 10px; border-bottom: 1px solid #9ca3af; }
        tr.total td { font-weight: bold; border-top: 1px solid #6b7280; }
        .note { margin-top: 14px; color: #4b5563; }
    </style>
</head>
<body>
    <h1>{{ $sheet->title }}</h1>
    <div class="muted">{{ $sheet->subtitle }}</div>

    <table>
        <thead>
            <tr>
                @foreach ($sheet->columns as $column)
                    <th class="{{ $column->isMoney ? 'money' : '' }}">{{ $column->label }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($sheet->rows as $row)
                <tr class="{{ $row->kind === ReportRowKind::Line ? '' : $row->kind->value }}">
                    @foreach ($sheet->columns as $key => $column)
                        <td class="{{ $column->isMoney ? 'money' : '' }}">{{ $format($column, $row->cells[$key] ?? null) }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    @if ($sheet->note)
        <p class="note">{{ $sheet->note }}</p>
    @endif
</body>
</html>
