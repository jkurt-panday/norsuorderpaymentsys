# NORSU Order of Payment System - Design Specification

Design system for the **NORSU (Negros Oriental State University) Order of Payment System** — a Laravel + Inertia + React accounting portal for submitting, processing, and tracking order-of-payment requests and student ledgers.

## Stack

| Layer | Technology |
| :--- | :--- |
| Frontend | React 19 + TypeScript, Inertia.js v3, Tailwind CSS v4 |
| UI components | shadcn/ui (Radix UI + Base UI) |
| Icons | lucide-react, @untitledui/icons |
| Charts | Recharts |
| Toasts | Sonner |
| Fonts | `@fontsource` (self-hosted) + Google Fonts for public/PDF views |

## Color Palette

The project uses the **NORSU Core Azure** brand on a **Lily White** background.

### Core Tokens (`:root` in `resources/css/app.css`)

| Token | Hex Value | Description |
| :--- | :--- | :--- |
| `--background` | `#f4f6fc` | Application background (Lily White) |
| `--foreground` | `#0f1e36` | Primary text / icons (deep navy) |
| `--primary` | `#007bff` | **NORSU Core Azure** — brand color, active states |
| `--primary-foreground` | `#ffffff` | Text/icons on primary surfaces |
| `--border` / `--input` | `#d5e0f2` | Light borders, inputs, separators |
| `--ring` | `#007bff` | Focus rings / selection |
| `--radius` | `0.5rem` | Base border radius |

### App Shell (sidebar)

| Token | Hex Value | Usage |
| :--- | :--- | :--- |
| Sidebar background | `#003f7d` | Deep azure sidebar, white text |
| Sidebar hover | `#ffffff / 5%` | Row hover state |
| Active nav item | `#0078d4` | Highlighted current section |
| Sidebar borders | `#0078d4 / 20%` | Dividers within sidebar |
| Main content bg | `#fafaf5` | Content area behind cards |

### Auth Split Layout

| Token | Hex Value | Usage |
| :--- | :--- | :--- |
| Brand panel | `#007bff` → `#0f1e36 / 60%` | Left gradient brand panel |
| Form panel | `#f4f6fc` | Right form panel (Lily White) |

### Status / Semantic Colors

Used for badges and data visualization.

| Status | Color |
| :--- | :--- |
| Approved | Emerald (`bg-emerald-100 text-emerald-800`) |
| Cancelled / Error | Rose (`bg-rose-100 text-rose-800`) |
| Pending | Amber (`bg-amber-100 text-amber-900`) |
| Unprocessed / Neutral | Slate (`bg-slate-100 text-slate-800`) |
| Destructive | `--destructive` (red) |

### Toast Colors (Sonner)

| Type | Background | Border | Text |
| :--- | :--- | :--- | :--- |
| Success | `#003f7d` | `#002d5b` | `#f8fafc` |
| Error | `#c2410c` | `#9a3412` | `#f8fafc` |
| Warning | `#f59e0b` | `#d97706` | `#0f172a` |
| Info | `#0f172a` | `#334155` | `#f8fafc` |

> **Note:** The dark theme is currently disabled in `app.css`. The app renders light-only, with hardcoded brand colors in the app shell and auth layouts so they stay on-brand regardless of appearance preference.

## Typography

### Font Families

| Role | Family |
| :--- | :--- |
| Body / Interface | **Instrument Sans** (self-hosted via `@fontsource`) |
| UI / Headings | **Geist Variable** (`@fontsource-variable/geist`) |
| Technical / Mono labels | System `font-mono` (JetBrains Mono loaded for public/PDF views) |

### Sizes & Weights

| Class | Size / Line-height | Weight | Usage |
| :--- | :--- | :--- | :--- |
| Page heading | `text-2xl` (24px) | Semibold | Page headers / section titles |
| Card title | `text-base` (16px) | Semibold | Card and table headings |
| Body | `text-sm` (14px) | Normal | Tables, cards, forms |
| Muted text | `text-sm` | Normal, `text-slate-500` | Descriptions, secondary labels |
| Label / table header | `text-xs` (12px) | Medium | Column headers, badges |

## Layout & Components

### App Shell (`AppSidebarLayout`)
- **Sidebar**: full-height deep azure (`#003f7d`) with white text; contains the finance logo, navigation, and a logout confirm.
  - Top-level nav items and collapsible submenus (Graduate Ledger, Law Ledger).
  - Active item uses `#0078d4` background with a soft shadow.
- **Header**: fixed `h-16` bar with sidebar trigger, vertical separator, and breadcrumbs.
- **Content**: scrollable area on `#fafaf5`, padded `p-4 md:p-8`.

### Auth Layouts
- **Split layout**: azure brand panel (with NORSU watermark/monogram and quote) + Lily White centered form panel.
- **Card layout**: centered elevated card with soft shadows and rounded corners.

### Public Pages
- **Submit Form** (`/public/form`): full-screen multi-field form — personal/office info, contact details, amount, request type (New / Re-issue), membership + payment option comboboxes, and drag-and-drop supporting document upload with animated progress.
- **Success** (`/public/success`): confirmation page with a prominent mono reference number.

### Reusable Patterns
- **Cards**: white panels, `rounded-3xl`, `border-slate-200`, `shadow-sm`, section headers with bottom border.
- **Stat cards**: colored background tiles (`bg-slate-900`, `bg-amber-100`, etc.) with value, label, and date subtitle.
- **Charts**: Recharts cards — pie (status breakdown), line (30-day volume), bar (requests by membership).
- **Status badges**: `inline-flex rounded-full px-3 py-1 text-xs font-semibold`.
- **Buttons**: pill / fully-rounded primary buttons; ghost/outline for secondary actions.
- **Tables**: sticky table headers, `divide-y` rows, muted secondary text.
- **Dialogs**: confirmation dialogs (e.g., logout, deletes) via AlertDialog.

## Print & PDF Styles

- **Receipts**: `@page { size: A4 portrait; margin: 8mm }`.
- Utility classes: `.print-container` (flush layout), `.print-card` (border only, no shadow, `break-inside: avoid`), `.no-print` (hide action buttons), `.print-header` / `.print-content` (tightened spacing for one-page fit).
- **PDF statements** rendered via Blade: `student-ledger-statement.blade.php` and `law-student-ledger-statement.blade.php`.
