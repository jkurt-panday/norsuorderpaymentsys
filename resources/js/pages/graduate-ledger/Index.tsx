import { Head } from '@inertiajs/react';
import React from 'react';

export default function Index() {
    return (
        <div className="p-8">
            <Head title="Graduate Ledger" />
            <h1 className="text-2xl font-bold text-[#091d2e]">Graduate Ledger Page</h1>
            <p className="text-slate-500 mt-2">Your brand new ledger view is successfully connected!</p>
        </div>
    );
}