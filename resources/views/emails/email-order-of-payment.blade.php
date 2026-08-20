<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1e293b;">
    <p>Dear {{ $recipientName ?? ($formInput->firstname_or_office . ' ' . $formInput->lastname_or_agency) }},</p>

    <p>
        Please find attached your Order of Payment
        (Reference No. <strong>{{ $formInput->reference_number }}</strong>)
        for the amount of <strong>₱{{ number_format($formInput->amount, 2) }}</strong>.
    </p>

    @if (!empty($note))
        <p>{!! nl2br(e($note)) !!}</p>
    @endif

    <p>Two copies are attached:</p>
    <ul>
        <li>A5 portrait — for individual filing</li>
        <li>Legal landscape — 3-up copies (Payor's / Cash Unit's / Accounting Unit's)</li>
    </ul>

    <p>Please present this at the Cash / Treasury Unit to complete payment.</p>

    <p>
        Regards,<br>
        NORSU Accounting Office
    </p>
</body>
</html>