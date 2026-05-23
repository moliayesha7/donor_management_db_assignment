<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Thank you for your donation</title>
</head>
<body style="font-family: Arial, Helvetica, sans-serif; line-height: 1.5; color: #222; max-width: 600px; margin: 0 auto;">
    <p>Dear {{ $donor->name }},</p>

    <p>Thank you for your generous donation. Your contribution has been received successfully and will support our work.</p>

    <table style="border-collapse: collapse; margin: 16px 0;">
        <tr>
            <td style="padding: 6px 12px; background: #f5f5f5; border: 1px solid #ddd;"><strong>Receipt Number</strong></td>
            <td style="padding: 6px 12px; border: 1px solid #ddd;">{{ $receipt }}</td>
        </tr>
        <tr>
            <td style="padding: 6px 12px; background: #f5f5f5; border: 1px solid #ddd;"><strong>Amount</strong></td>
            <td style="padding: 6px 12px; border: 1px solid #ddd;">{{ number_format((float) $amount, 2) }}</td>
        </tr>
        @if ($project)
            <tr>
                <td style="padding: 6px 12px; background: #f5f5f5; border: 1px solid #ddd;"><strong>Project</strong></td>
                <td style="padding: 6px 12px; border: 1px solid #ddd;">{{ $project->name }}</td>
            </tr>
        @endif
        <tr>
            <td style="padding: 6px 12px; background: #f5f5f5; border: 1px solid #ddd;"><strong>Date</strong></td>
            <td style="padding: 6px 12px; border: 1px solid #ddd;">{{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</td>
        </tr>
    </table>

    <p>If you have any questions about this donation, please reply to this email referencing your receipt number.</p>

    <p>With gratitude,<br>The Team</p>
</body>
</html>
