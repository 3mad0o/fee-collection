<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $statement->formatted_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; color: #111827; font-size: 13px; }
        .header { margin-bottom: 24px; }
        .title { font-size: 26px; font-weight: bold; }
        .muted { color: #6b7280; }
        .box { border: 1px solid #e5e7eb; padding: 12px; margin-bottom: 16px; border-radius: 6px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th, td { border: 1px solid #e5e7eb; padding: 8px; text-align: left; }
        th { background: #f3f4f6; }
        .right { text-align: right; }
    </style>
</head>
<body>
<div class="header">
    <div class="title">INVOICE</div>
    <div class="muted">Number: {{ $statement->formatted_number }}</div>
    <div class="muted">Date: {{ optional($statement->date)->format('Y-m-d H:i') }}</div>
</div>

<div class="box">
    <strong>Customer</strong><br>
    {{ class_basename($accountable::class) }} #{{ $accountable?->getKey() }}
</div>

<div class="box">
    <strong>Description</strong><br>
    {{ $statement->description ?: '-' }}
</div>

<table>
    <thead>
    <tr>
        <th>Item</th>
        <th class="right">Amount</th>
    </tr>
    </thead>
    <tbody>
    <tr>
        <td>Invoice {{ $statement->formatted_number }}</td>
        <td class="right">{{ number_format((float) $statement->amount, 2) }}</td>
    </tr>
    </tbody>
</table>

<div class="right" style="margin-top: 14px;">
    <strong>Total: {{ number_format((float) $statement->amount, 2) }}</strong>
</div>
</body>
</html>
