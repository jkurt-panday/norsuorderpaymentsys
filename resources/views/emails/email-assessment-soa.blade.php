<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1e293b;">
    <p>Dear {{ $recipientName ?? ($assessment->first_name . ' ' . $assessment->last_name) }},</p>

    <p>
        Please find attached your Statement of Account
        (Reference No. <strong>{{ $assessment->reference_number }}</strong>)
        for {{ $assessment->enrolled_under }} {{ $assessment->semester }} {{ $assessment->sy_last_attended }}.
    </p>

    @if (!empty($note))
        <p>{!! nl2br(e($note)) !!}</p>
    @endif

    <p>This statement includes your billed charges and payments for the requested term.</p>

    <p>
        Regards,<br>
        NORSU Accounting Office
    </p>
</body>
</html>