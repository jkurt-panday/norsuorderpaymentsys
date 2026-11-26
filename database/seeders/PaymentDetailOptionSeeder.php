<?php

namespace Database\Seeders;

use App\Models\PaymentDetailOption;
use Illuminate\Database\Seeder;

class PaymentDetailOptionSeeder extends Seeder
{
    /**
     * Seed the available payment detail options.
     */
    public function run(): void
    {
        $paymentDescriptions = [
            'Comprehensive Exam - Doctorate',
            'Comprehensive Exam - Masters',
            'Donations',
            'IGP FABLAB',
            'IGP Royalty Fee of NORSU Logo',
            'IGP Sale of Agricultural Produce',
            'IGP Sale of Beverages and Meals',
            'IGP Sale of Bid Docs',
            'IGP Sale of Merchandise',
            'IGP Service Revenue of Innovation and Technology Support Office',
            'IGP Service Revenue of Printing and Photocopying',
            'IGP Service Revenue of Rental Facilities',
            'IGP Water Refilling',
            'Payment of Disallowances',
            'Payment for Graduate School - Balance in full',
            'Payment of Graduate School - Down payment',
            'Payment of Performance Security',
            'Payment for Off Semester/Summer/Special Class',
            'Payment for School of Law - Balance in full',
            'Payment for School of Law - Down payment',
            'Protest Fee',
            'Receipt of Externally Funded Projects',
            'Receipt of Scholarship Fund',
            'Refund of Cash Advance',
            'Refund of Externally Funded Projects',
            'Refund of Overpayment of Salaries and Wages',
            'Refund of Overpayment of Labor and Wages',
            'Refund of Overpayment of Honoraria',
            'Refund of Overpayment of Professional Services',
            'Refund of Unexpended/Unreleased Scholarship Grant',
            'Sale of Bid Security',
            'Sale of Minutes of the Meeting',
            'Water and Electricity Consumption-Contractors',
            'Water and Electricity Consumption-Concessionaires',
            'GSIS- premium (personal share)',
            'IGP Service Revenue for security services rendered during rental of facility',
            'Overage',
            'Payment for Undergraduate (6th yr.) - Balance in full',
            'Payment for Undergraduate (6th yr.) - Downpayment',
            'Refund of Erroneous Payments charged from fund',
            'Payment representing to cover the campus "Common Funds"',
            'Proceeds from Disposal: Live Animals',
            'Proceeds from Disposal: Equipment',
            'Proceeds from Disposal: Scrap',
            'Proceeds from Disposal: Furniture and Fixtures/ Books',
            'Proceeds from Disposal: Semi-expendable Equipment',
            'Proceeds from Disposal: Semi-expendable Furniture and Fixtures/ Books',
        ];

        foreach ($paymentDescriptions as $paymentDescription) {
            PaymentDetailOption::updateOrCreate([
                'payment_desc' => $paymentDescription,
            ]);
        }

        $this->command?->info('Successfully seeded: Payment Detail Options');
    }
}
