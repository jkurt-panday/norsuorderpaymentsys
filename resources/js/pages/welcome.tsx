import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowUpRight,
    ClipboardList,
    Database,
    FileCheck2,
    FileText,
    LayoutDashboard,
    LogIn,
    Mail,
    MapPin,
    Send,
    UserRoundCog,
    Users,
} from 'lucide-react';
import { dashboard, home, login } from '@/routes';
import { create as requestAssessment } from '@/routes/public/assessmentform';
import { create as requestOrderOfPayment } from '@/routes/public/opform';
import type { Auth } from '@/types';

const processes = {
    payment: {
        label: 'Order of Payment',
        description:
            'From online submission to a completed Order of Payment in three clear steps.',
        steps: [
            {
                title: 'Request a payment',
                description:
                    'Complete and submit the public Order of Payment form.',
                icon: Send,
            },
            {
                title: 'Staff process your request',
                description:
                    'The Accounting Office reviews and prepares your request.',
                icon: UserRoundCog,
            },
            {
                title: 'Receive your Order of Payment',
                description: 'Get the completed document when it is ready.',
                icon: FileCheck2,
            },
        ],
    },
    assessment: {
        label: 'Assessment',
        description:
            'From submitting your details to receiving your student assessment in four clear steps.',
        steps: [
            {
                title: 'Request an assessment',
                description:
                    'Complete and submit the public student assessment form.',
                icon: Send,
            },
            {
                title: 'The system fetches your records',
                description:
                    'Your available student account records are gathered for review.',
                icon: Database,
            },
            {
                title: 'Staff review your request',
                description:
                    'The Accounting Office verifies your details and prepares the assessment.',
                icon: UserRoundCog,
            },
            {
                title: 'Receive your assessment',
                description:
                    'Receive your prepared student account assessment by email.',
                icon: FileCheck2,
            },
        ],
    },
} as const;

type ProcessType = keyof typeof processes;

export default function Welcome() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const [selectedProcess, setSelectedProcess] =
        useState<ProcessType>('payment');
    const [showTeam, setShowTeam] = useState(false);
    const process = processes[selectedProcess];

    return (
        <>
            <Head title="Order of Payment System">
                <meta
                    name="description"
                    content="Request an Order of Payment or student assessment from the NORSU Accounting Office."
                />
            </Head>

            <div className="min-h-screen bg-[#061b36] text-white selection:bg-sky-300 selection:text-[#061b36]">
                <a
                    href="#main-content"
                    className="fixed top-3 left-3 z-50 -translate-y-20 rounded-lg bg-white px-4 py-2 font-semibold text-[#064b9b] shadow-xl transition-transform focus:translate-y-0 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300"
                >
                    Skip to main content
                </a>

                <header className="sticky top-0 z-40 border-b border-white/20 bg-[#064b9b]/75 shadow-[0_12px_35px_-22px_rgba(0,20,50,0.85)] backdrop-blur-xl backdrop-saturate-150">
                    <div className="mx-auto flex max-w-[90rem] items-center justify-between gap-4 px-5 py-4 sm:px-8 lg:px-8">
                        <Link
                            href={home()}
                            aria-label="NORSU Order of Payment System home"
                            className="flex min-w-0 items-center gap-3 rounded-lg focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300"
                        >
                            <img
                                src="/norsu.png"
                                alt=""
                                className="size-9 shrink-0 object-contain sm:size-10"
                            />
                            <span className="min-w-0">
                                <span className="block font-heading text-sm font-medium text-white sm:text-base">
                                    Negros Oriental State University
                                </span>
                                <span className="block text-xs text-blue-200">
                                    Order of Payment System
                                </span>
                            </span>
                        </Link>

                        <nav
                            aria-label="Landing page sections"
                            className="hidden items-center gap-5 text-sm text-blue-100 md:flex lg:gap-7"
                        >
                            <a
                                href="#hero"
                                className="rounded-md px-1 py-2 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300"
                            >
                                About
                            </a>
                            <a
                                href="#features"
                                className="rounded-md px-1 py-2 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300"
                            >
                                Features
                            </a>
                            <a
                                href="#contact"
                                className="rounded-md px-1 py-2 hover:text-white focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300"
                            >
                                Contact
                            </a>
                        </nav>

                        <Link
                            href={auth.user ? dashboard() : login()}
                            className="inline-flex min-h-10 shrink-0 items-center gap-2 rounded-lg border border-white/25 bg-white/10 px-3 py-2 text-sm font-medium text-white shadow-[inset_0_1px_0_rgba(255,255,255,0.12)] transition-colors hover:border-white/40 hover:bg-white/20 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300 sm:px-4"
                        >
                            {auth.user ? (
                                <LayoutDashboard
                                    aria-hidden="true"
                                    className="size-4"
                                />
                            ) : (
                                <LogIn aria-hidden="true" className="size-4" />
                            )}
                            <span className="hidden sm:inline">
                                {auth.user ? 'Open dashboard' : 'Staff sign in'}
                            </span>
                            <span className="sm:hidden">
                                {auth.user ? 'Dashboard' : 'Staff'}
                            </span>
                        </Link>
                    </div>
                </header>

                <div className="relative isolate overflow-hidden lg:min-h-[650px]">
                    <img
                        src="/images/norsu-campus.jpg.webp"
                        alt=""
                        className="absolute inset-0 -z-30 size-full object-cover object-center"
                    />
                    <div className="absolute inset-0 -z-20 bg-[linear-gradient(110deg,rgba(4,24,50,0.98)_5%,rgba(5,57,117,0.88)_55%,rgba(1,32,72,0.74)_100%)]" />
                    <div className="absolute -top-40 right-[8%] -z-10 size-[34rem] rounded-full bg-sky-300/20 blur-[110px]" />

                    <main id="main-content">
                        <section
                            id="hero"
                            className="mx-auto grid max-w-[90rem] scroll-mt-20 items-center gap-12 px-5 py-16 sm:px-8 lg:min-h-[650px] lg:grid-cols-[minmax(0,1fr)_minmax(420px,0.78fr)] lg:px-8 lg:py-20"
                        >
                            <div className="max-w-2xl">
                                <h1 className="max-w-[16ch] font-heading text-4xl leading-[1.05] font-bold tracking-[-0.04em] text-balance sm:text-5xl lg:text-6xl">
                                    Request your Order of Payment online
                                </h1>
                                <p className="mt-6 max-w-[62ch] text-lg leading-8 text-blue-100 sm:text-xl">
                                    Submit an Order of Payment or request a
                                    student assessment online—no office visit
                                    needed to get started.
                                </p>
                            </div>

                            <div
                                id="services"
                                aria-label="Accounting Office online services"
                                className="overflow-hidden rounded-[1.75rem] border border-white/20 bg-white/10 shadow-[0_30px_90px_-30px_rgba(0,10,35,0.85)] backdrop-blur-2xl"
                            >
                                <div className="border-b border-white/15 px-6 py-5 sm:px-8">
                                    <h2 className="font-heading text-xl font-semibold text-white sm:text-2xl">
                                        Start a public request
                                    </h2>
                                </div>

                                <Link
                                    href={requestAssessment()}
                                    className="group flex items-start gap-4 border-b border-white/15 px-6 py-7 transition-colors hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300 focus-visible:ring-inset sm:px-8"
                                >
                                    <span className="flex size-12 shrink-0 items-center justify-center rounded-xl border border-sky-200/25 bg-sky-300/15 text-sky-200">
                                        <ClipboardList
                                            aria-hidden="true"
                                            className="size-6"
                                        />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block font-heading text-xl font-semibold">
                                            Request an Assessment
                                        </span>
                                        <span className="mt-2 block text-sm leading-6 text-blue-200">
                                            Ask the Accounting Office to prepare
                                            a student account assessment.
                                        </span>
                                    </span>
                                    <ArrowUpRight
                                        aria-hidden="true"
                                        className="size-5 shrink-0 text-sky-300 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 motion-reduce:transition-none"
                                    />
                                </Link>

                                <Link
                                    href={requestOrderOfPayment()}
                                    className="group flex items-start gap-4 px-6 py-7 transition-colors hover:bg-white/10 focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300 focus-visible:ring-inset sm:px-8"
                                >
                                    <span className="flex size-12 shrink-0 items-center justify-center rounded-xl border border-sky-200/25 bg-sky-300/15 text-sky-200">
                                        <FileText
                                            aria-hidden="true"
                                            className="size-6"
                                        />
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block font-heading text-xl font-semibold">
                                            Request an Order of Payment
                                        </span>
                                        <span className="mt-2 block text-sm leading-6 text-blue-200">
                                            For university fees, services, and
                                            other approved transactions.
                                        </span>
                                    </span>
                                    <ArrowUpRight
                                        aria-hidden="true"
                                        className="size-5 shrink-0 text-sky-300 transition-transform group-hover:translate-x-0.5 group-hover:-translate-y-0.5 motion-reduce:transition-none"
                                    />
                                </Link>
                            </div>
                        </section>
                    </main>
                </div>

                <section
                    id="features"
                    aria-labelledby="features-heading"
                    className="scroll-mt-20 bg-white py-20 text-[#10233f] lg:py-24"
                >
                    <div className="mx-auto max-w-[90rem] px-5 sm:px-8 lg:px-8">
                        <div>
                            <div className="max-w-4xl">
                                <h2
                                    id="features-heading"
                                    className="font-heading text-3xl font-bold tracking-[-0.03em] sm:text-5xl"
                                >
                                    How your request moves
                                </h2>
                                <p className="mt-5 min-h-16 max-w-4xl leading-8 text-slate-600 sm:min-h-8">
                                    {process.description}
                                </p>
                            </div>

                            <div
                                role="group"
                                aria-label="Choose a service process"
                                className="mt-8 grid w-full max-w-md grid-cols-2 rounded-xl border border-[#b9d3ef] bg-[#eef5ff] p-1.5"
                            >
                                {(Object.keys(processes) as ProcessType[]).map(
                                    (type) => (
                                        <button
                                            key={type}
                                            type="button"
                                            aria-pressed={
                                                selectedProcess === type
                                            }
                                            onClick={() =>
                                                setSelectedProcess(type)
                                            }
                                            className={`min-h-11 rounded-lg px-3 py-2 text-center text-sm font-semibold transition-colors focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0d51b5] focus-visible:ring-offset-2 sm:px-6 ${
                                                selectedProcess === type
                                                    ? 'bg-[#064b9b] text-white'
                                                    : 'text-[#294765] hover:bg-white'
                                            }`}
                                        >
                                            {processes[type].label}
                                        </button>
                                    ),
                                )}
                            </div>

                            <ol
                                aria-live="polite"
                                aria-label={`${process.label} process`}
                                className={`relative mt-12 grid gap-8 before:absolute before:top-8 before:bottom-8 before:left-8 before:w-px before:bg-[#9fc4ee] sm:mt-14 lg:gap-10 lg:before:top-8 lg:before:right-8 lg:before:bottom-auto lg:before:left-8 lg:before:h-px lg:before:w-auto ${
                                    selectedProcess === 'assessment'
                                        ? 'lg:grid-cols-4'
                                        : 'lg:grid-cols-3'
                                }`}
                            >
                                {process.steps.map((step, index) => (
                                    <li
                                        key={step.title}
                                        className="relative grid grid-cols-[4rem_1fr] gap-5 lg:grid-cols-1"
                                    >
                                        <span
                                            aria-hidden="true"
                                            className="relative z-10 flex size-16 items-center justify-center rounded-full border border-[#9fc4ee] bg-white text-[#064b9b] shadow-[0_14px_35px_-22px_rgba(13,81,181,0.8)]"
                                        >
                                            <step.icon className="size-7" />
                                        </span>
                                        <div>
                                            <p className="font-heading text-sm font-semibold text-[#0d51b5]">
                                                Step {index + 1}
                                            </p>
                                            <h3 className="mt-1 font-heading text-lg font-semibold whitespace-nowrap xl:text-xl">
                                                {step.title}
                                            </h3>
                                            <p className="mt-2 text-base leading-7 text-slate-600">
                                                {step.description}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        </div>
                    </div>
                </section>

                <section
                    id="contact"
                    aria-labelledby="contact-heading"
                    className="scroll-mt-20 bg-[#064b9b] py-20 text-white lg:flex lg:min-h-[650px] lg:items-center lg:py-20"
                >
                    <div className="mx-auto grid max-w-[90rem] gap-10 px-5 sm:px-8 lg:grid-cols-[0.8fr_1fr] lg:items-center lg:gap-20 lg:px-8">
                        <div className="divide-y divide-white/20 border-y border-white/20">
                            <div className="flex items-start gap-4 py-7">
                                <span className="flex size-12 shrink-0 items-center justify-center rounded-full bg-white text-[#064b9b]">
                                    <MapPin
                                        aria-hidden="true"
                                        className="size-6"
                                    />
                                </span>
                                <div>
                                    <h3 className="font-heading text-lg font-semibold">
                                        Accounting Office
                                    </h3>
                                    <p className="mt-2 leading-7 text-blue-100">
                                        Negros Oriental State University
                                        <br />
                                        Main Campus
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-start gap-4 py-7">
                                <span className="flex size-12 shrink-0 items-center justify-center rounded-full bg-white text-[#064b9b]">
                                    <Mail
                                        aria-hidden="true"
                                        className="size-6"
                                    />
                                </span>
                                <div>
                                    <h3 className="font-heading text-lg font-semibold">
                                        Email
                                    </h3>
                                    <a
                                        href="mailto:accounting.norsu@norsu.edu.ph"
                                        className="mt-2 inline-block text-blue-100 underline decoration-white/40 underline-offset-4 transition-colors hover:text-white hover:decoration-white focus:outline-none focus-visible:ring-2 focus-visible:ring-sky-300"
                                    >
                                        accounting.norsu@norsu.edu.ph
                                    </a>
                                </div>
                            </div>
                        </div>

                        <div className="max-w-3xl lg:justify-self-end">
                            <h2
                                id="contact-heading"
                                className="font-heading text-3xl font-bold tracking-[-0.03em] sm:text-5xl"
                            >
                                Need help with your request?
                            </h2>
                            <p className="mt-6 max-w-[62ch] text-lg leading-8 text-blue-100">
                                Visit the Accounting Office for assistance with
                                an existing request. Keep your reference number
                                ready so staff can locate it quickly.
                            </p>
                        </div>
                    </div>
                </section>

                <section
                    aria-labelledby="team-heading"
                    className="bg-white py-12 text-[#10233f] sm:py-14"
                >
                    <div className="mx-auto grid max-w-[90rem] items-center gap-8 px-5 sm:px-8 lg:grid-cols-[1fr_auto] lg:gap-16 lg:px-8">
                        <div className="max-w-3xl">
                            <h2
                                id="team-heading"
                                className="font-heading text-2xl font-bold tracking-[-0.02em] sm:text-3xl"
                            >
                                About the Team
                            </h2>
                            <p className="mt-4 max-w-[68ch] leading-7 text-slate-600">
                                This system was designed and developed by NORSU
                                student interns in collaboration with the
                                Accounting Office.
                            </p>
                            <button
                                type="button"
                                aria-expanded={showTeam}
                                aria-controls="team-details"
                                onClick={() =>
                                    setShowTeam((visible) => !visible)
                                }
                                className="mt-5 inline-flex min-h-11 items-center justify-center rounded-lg bg-[#064b9b] px-5 py-2.5 font-semibold text-white transition-colors hover:bg-[#053d80] focus:outline-none focus-visible:ring-2 focus-visible:ring-[#0d51b5] focus-visible:ring-offset-2"
                            >
                                {showTeam ? 'Hide team' : 'Meet the team'}
                            </button>
                            <div
                                id="team-details"
                                hidden={!showTeam}
                                className="mt-6 border-t border-slate-200 pt-5"
                            >
                                <p className="max-w-[68ch] leading-7 text-slate-600">
                                    Student interns contributed to the system’s
                                    interface, workflows, ledger tools, testing,
                                    and continued improvement with guidance from
                                    the Accounting Office.
                                </p>
                            </div>
                        </div>

                        <span className="flex size-28 items-center justify-center justify-self-start rounded-full bg-[#eaf3ff] text-[#0d51b5] sm:size-32 lg:justify-self-end">
                            <Users
                                aria-hidden="true"
                                className="size-14 sm:size-16"
                            />
                        </span>
                    </div>
                </section>

                <footer className="border-t border-white/10 bg-[#061b36] px-5 py-9 text-sm text-blue-200 sm:px-8 lg:px-12">
                    <div className="mx-auto flex max-w-[90rem] flex-col justify-between gap-8 sm:flex-row sm:items-center">
                        <div className="flex items-center gap-4">
                            <img
                                src="/norsu.png"
                                alt="Negros Oriental State University"
                                className="size-14 shrink-0 object-contain"
                            />
                            <p className="font-heading text-base leading-6 font-semibold text-white">
                                <span className="block">Our Hope</span>
                                <span className="block">Our Pride</span>
                                <span className="block">Our Future</span>
                            </p>
                        </div>
                        <div className="space-y-1 sm:text-right">
                            <p>
                                Negros Oriental State University · Accounting
                                Office
                            </p>
                            <p>Main Campus</p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
