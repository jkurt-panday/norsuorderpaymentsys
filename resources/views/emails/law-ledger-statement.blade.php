<!DOCTYPE html>
<html>
<body style="font-family: Arial, sans-serif; color: #1e293b;">
    <p>Dear {{ $recipientName ?? 'Student' }},</p>

    <p>
        Please find attached your Statement of Account issued by the
        NORSU Accounting Office. This statement reflects your assessed
        charges, payments, and adjustments recorded in the Law School ledger.
    </p>

    @if (!empty($note))
        <p>{!! nl2br(e($note)) !!}</p>
    @endif

    @if (!empty($examPeriod))
        <p class="text-sm text-slate-500">
            Exam period: {{ $examPeriod }}.
            @if (!empty($examDeadline))
                Payment deadline: {{ \Carbon\Carbon::parse($examDeadline)->format('F j, Y') }}.
            @endif
        </p>
    @endif

    <p>
        Should you have any questions regarding your balance, please visit the
        Accounting Office with your valid ID.
    </p>

    <p>
        Regards,<br>
        NORSU Accounting Office
    </p>
</body>
</html>
