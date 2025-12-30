# Design System Reference Guide

## 🎨 Color Palette

### Primary Colors
```css
--primary-50:  #eff6ff  /* Light blue backgrounds, active states */
--primary-100: #bfdbfe  /* Borders for info elements */
--primary-500: #3b82f6  /* Primary actions, buttons, links */
--primary-600: #2563eb  /* Hover states for primary actions */
--primary-700: #1e40af  /* Dark blue text on light backgrounds */
```

### Success Colors
```css
--success-100: #d1fae5  /* Success alert backgrounds */
--success-500: #10b981  /* Success buttons, icons */
--success-600: #059669  /* Success button hover */
--success-700: #065f46  /* Success alert text */
```

### Danger Colors
```css
--danger-100: #fee2e2  /* Error alert backgrounds */
--danger-500: #ef4444  /* Danger buttons, error states */
--danger-600: #dc2626  /* Danger button hover */
--danger-700: #991b1b  /* Error alert text */
```

### Warning Colors
```css
--warning-500: #f59e0b  /* Warning buttons, badges */
--warning-600: #d97706  /* Warning button hover */
```

### Neutral/Gray Scale
```css
--gray-50:  #f9fafb  /* Input backgrounds, table headers */
--gray-100: #f3f4f6  /* Hover backgrounds, separators */
--gray-200: #e5e7eb  /* Borders, dividers */
--gray-300: #d1d5db  /* Outline button borders */
--gray-400: #9ca3af  /* Muted icons, placeholders */
--gray-500: #6b7280  /* Secondary text */
--gray-600: #4b5563  /* Body text */
--gray-700: #374151  /* Headers, labels */
--gray-800: #1f2937  /* Dark headings */
--gray-900: #111827  /* Darkest text, primary headings */
```

### Background Colors
```css
--bg-page:      #f8f9fa  /* Main page background */
--bg-card:      #ffffff  /* Cards, tables, containers */
--bg-input:     #f9fafb  /* Input fields */
--bg-input-focus: #ffffff /* Input focus state */
```

## 📏 Spacing Scale

```css
--space-1: 4px
--space-2: 8px   /* Icons, inline elements */
--space-3: 12px  /* Form elements, buttons */
--space-4: 16px  /* Section spacing */
--space-5: 20px  /* Component padding */
--space-6: 24px  /* Page sections */
--space-8: 32px
--space-10: 40px
--space-16: 64px /* Large sections */
```

## 🔤 Typography

### Font Family
```css
font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
```

### Font Sizes
```css
--text-xs:   0.6875rem  /* 11px - Tiny badges */
--text-sm:   0.8125rem  /* 13px - Labels, small text */
--text-base: 0.875rem   /* 14px - Body text */
--text-md:   0.9375rem  /* 15px - Inputs, prominent text */
--text-lg:   1.5rem     /* 24px - Stats, numbers */
--text-xl:   1.75rem    /* 28px - Page titles */
```

### Font Weights
```css
--font-normal:    400  /* Regular text */
--font-medium:    500  /* Body, subtle emphasis */
--font-semibold:  600  /* Labels, button text */
--font-bold:      700  /* Headings, page titles */
```

### Letter Spacing
```css
--tracking-tight:  -0.02em  /* Large headings */
--tracking-normal:  0em     /* Body text */
--tracking-wide:    0.01em  /* Labels */
--tracking-wider:   0.03em  /* Uppercase headers */
```

## 🎯 Border Radius

```css
--radius-sm:    8px   /* Inputs, small buttons */
--radius-md:    10px  /* Search bar, alerts */
--radius-lg:    12px  /* Cards, containers */
--radius-full:  20px  /* Pills, badges */
--radius-round: 50%   /* Avatar, icons */
```

## 🪟 Shadows

```css
/* Focus Ring */
box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);

/* Button Hover - Blue */
box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);

/* Button Hover - Green */
box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);

/* Button Hover - Red */
box-shadow: 0 4px 12px rgba(239, 68, 68, 0.3);

/* Pagination Active */
box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);
```

## 🔲 Borders

```css
/* Standard Border */
border: 1px solid #e5e7eb;

/* Strong Border (inputs, buttons) */
border: 2px solid #e5e7eb;

/* Focus Border */
border: 2px solid #3b82f6;

/* Table Separator */
border-bottom: 1px solid #f3f4f6;

/* Table Header */
border-bottom: 2px solid #e5e7eb;

/* Section Separator */
border-top: 2px solid #f3f4f6;
```

## ⏱️ Transitions

```css
/* Standard Transition */
transition: all 0.15s ease-in-out;

/* Fast Transition */
transition: all 0.1s ease-in-out;

/* Slow Transition */
transition: all 0.2s ease-in-out;
```

## 🎛️ Component Reference

### Primary Button
```css
background: #3b82f6;
color: #ffffff;
padding: 10px 18px;
border-radius: 8px;
font-weight: 500;
gap: 8px;

/* Hover */
background: #2563eb;
transform: translateY(-1px);
box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
```

### Success Button
```css
background: #10b981;
color: #ffffff;
padding: 10px 18px;
border-radius: 8px;
font-weight: 500;

/* Hover */
background: #059669;
transform: translateY(-1px);
box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
```

### Outline Button
```css
background: transparent;
border: 1px solid #d1d5db;
color: #6b7280;
padding: 10px 18px;
border-radius: 8px;

/* Hover */
background: #f9fafb;
border-color: #9ca3af;
color: #374151;
```

### Small Button
```css
padding: 6px 12px;
font-size: 0.8125rem;
```

### Search Input
```css
width: 100%;
padding: 12px 16px 12px 44px;
border: 2px solid #e5e7eb;
border-radius: 10px;
font-size: 0.9375rem;
background-color: #f9fafb;

/* Focus */
border-color: #3b82f6;
background-color: #ffffff;
box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
```

### Filter Input
```css
padding: 10px 14px;
border: 2px solid #e5e7eb;
border-radius: 8px;
font-size: 0.875rem;
background-color: #ffffff;

/* Focus */
border-color: #3b82f6;
box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
```

### Badge
```css
display: inline-flex;
align-items: center;
gap: 4px;
background: #3b82f6;
color: #ffffff;
padding: 2px 10px;
border-radius: 20px;
font-size: 0.6875rem;
font-weight: 600;
letter-spacing: 0.02em;
```

### Card/Container
```css
background: #ffffff;
padding: 24px;
border-radius: 12px;
border: 1px solid #e5e7eb;
```

### Table Header
```css
padding: 14px 16px;
background: #f9fafb;
font-weight: 600;
color: #374151;
font-size: 0.8125rem;
letter-spacing: 0.03em;
text-transform: uppercase;
border-bottom: 2px solid #e5e7eb;
```

### Table Cell
```css
padding: 14px 16px;
border-bottom: 1px solid #f3f4f6;
font-size: 0.875rem;
color: #374151;

/* Row Hover */
background-color: #f9fafb;
box-shadow: inset 0 0 0 1px #e5e7eb;
```

### Alert Success
```css
background-color: #d1fae5;
color: #065f46;
border: 2px solid #10b981;
padding: 14px 18px;
border-radius: 10px;
```

### Alert Danger
```css
background-color: #fee2e2;
color: #991b1b;
border: 2px solid #ef4444;
padding: 14px 18px;
border-radius: 10px;
```

### Pagination Button
```css
min-width: 40px;
height: 40px;
padding: 8px 12px;
border: 2px solid #e5e7eb;
background: #ffffff;
border-radius: 8px;
font-weight: 500;
color: #4b5563;

/* Active */
background: #3b82f6;
color: #ffffff;
border-color: #3b82f6;
box-shadow: 0 2px 8px rgba(59, 130, 246, 0.3);

/* Hover */
border-color: #3b82f6;
color: #3b82f6;
```

## 📱 Responsive Breakpoints

```css
/* Mobile */
@media (max-width: 768px) {
  /* Stacked layouts */
  /* Larger touch targets */
  /* Full-width buttons */
}

/* Tablet */
@media (min-width: 769px) and (max-width: 1024px) {
  /* Adjusted grid columns */
}

/* Desktop */
@media (min-width: 1025px) {
  /* Full multi-column layouts */
}
```

## 🎯 Icon Sizes

```css
--icon-xs:  14px  /* Inline text icons */
--icon-sm:  16px  /* Buttons, labels */
--icon-md:  18px  /* Search icon */
--icon-lg:  24px  /* Headers */
--icon-xl:  48px  /* Empty states */
```

## ✨ Quick Copy Snippets

### Blue Focus Ring
```css
outline: none;
border-color: #3b82f6;
box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
```

### Hover Lift Effect
```css
transform: translateY(-1px);
box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
```

### Subtle Row Hover
```css
background-color: #f9fafb;
box-shadow: inset 0 0 0 1px #e5e7eb;
```

### Uppercase Label
```css
font-size: 0.8125rem;
font-weight: 600;
color: #374151;
letter-spacing: 0.03em;
text-transform: uppercase;
```

## 🎨 Usage Guidelines

### When to Use Primary Blue
- Call-to-action buttons
- Active states
- Important badges
- Focus indicators
- Links

### When to Use Success Green
- Success messages
- Confirmation buttons
- Positive indicators
- "Save" actions

### When to Use Danger Red
- Delete buttons
- Error messages
- Critical warnings
- Destructive actions

### When to Use Gray
- Neutral actions
- Disabled states
- Secondary information
- Borders and dividers

## 🔍 Accessibility

### Contrast Ratios
- Body text (#374151) on white: 8.9:1 ✅
- Headers (#111827) on white: 15.8:1 ✅
- Blue (#3b82f6) on white: 3.4:1 (large text only)
- White on Blue (#3b82f6): 4.5:1 ✅

### Focus Indicators
All interactive elements have visible focus states using the blue ring.

### Touch Targets
Minimum 40px × 40px for all clickable elements (pagination, buttons).

---

**Note:** This design system is based on modern 2025 UI/UX best practices with no gradients, clean aesthetics, and excellent accessibility.
