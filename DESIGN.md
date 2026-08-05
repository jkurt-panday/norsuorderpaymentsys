# University Accounting Portal - Design Specification

This document details the color palette, typography, and visual patterns extracted from the Stitch design "Payment of Order System".

## Color Palette

The project uses the **Azure Blue and Lily White** aesthetic:

| Token | Hex Value | Description |
| :--- | :--- | :--- |
| `primary` (Azure Blue) | `#005ab7` | Main brand color for accents, headings, active states |
| `primary-container` | `#0072e5` | Key container background color for highlights, primary buttons |
| `on-primary` | `#ffffff` | Text/icon color on primary backgrounds |
| `background` / `surface` | `#f7f9ff` | Overall application background (Lily White gradient base) |
| `surface-container-lowest` | `#ffffff` | Card, container, and field inputs background |
| `on-surface` | `#091d2e` | Primary text and icons on surfaces |
| `on-surface-variant` | `#414754` | Secondary/muted text and icons |
| `outline-variant` | `#c1c6d7` | Light borders and separators |
| `outline` | `#717786` | Medium borders and input borders |
| `error` | `#ba1a1a` | Validation error text and border outlines |

## Typography

### Font Families
- **Display / Headlines**: `Source Serif 4`
- **Body / Interface**: `Inter`
- **Labels / Technical Details**: `JetBrains Mono`

### Font Sizes & Weights
- **`headline-md`**: `32px` | Line-height `40px` | Font-weight `600` (Used for Page Headers)
- **`headline-sm`**: `24px` | Line-height `32px` | Font-weight `600` (Used for Card Titles)
- **`body-md`**: `16px` | Line-height `24px` | Font-weight `400` (Standard Body Text)
- **`body-sm`**: `14px` | Line-height `20px` | Font-weight `400` (Muted/Secondary/Validation Text)
- **`label-md`**: `12px` | Line-height `16px` | Letter-spacing `0.05em` | Font-weight `500` (Labels and Form Field Headers)

## Layout & Border Radius
- **Card**: Centered elevated panel with custom soft shadows (`shadow-xl`) and rounded corners (`rounded-xl` or `0.5rem`).
- **Inputs**: Solid rounded-lg (`0.25rem`) input borders with material icon indicators.
- **Buttons**: Pill-shaped/fully-rounded (`rounded-full`) login trigger matching brand identity guidelines.
