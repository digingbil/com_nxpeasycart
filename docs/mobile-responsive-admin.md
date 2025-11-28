# Mobile-Responsive Admin UI

The NXP Easy Cart admin panel is fully responsive and optimized for mobile devices. This document describes the responsive design implementation and the techniques used to ensure usability across all screen sizes.

## Overview

The admin SPA transforms from a traditional desktop layout to a mobile-friendly card-based interface at tablet and mobile breakpoints. All interactive elements meet accessibility guidelines for touch targets (44px minimum).

## Breakpoints

| Breakpoint | Width   | Description                             |
| ---------- | ------- | --------------------------------------- |
| Desktop    | > 768px | Full table layouts, side-by-side panels |
| Tablet     | ≤ 768px | Card-based tables, stacked layouts      |
| Mobile     | ≤ 480px | Compact cards, full-width modals        |

## Responsive Features

### Navigation

- **Desktop**: Horizontal tab navigation with pill-style active states
- **Tablet/Mobile**: Horizontal scrolling navigation with touch-friendly momentum scrolling
- Hidden scrollbars for cleaner appearance (`scrollbar-width: none`)

### Panel Headers

- **Desktop**: Flexbox layout with title/lead on left, actions on right
- **Tablet**: Stacked layout - title block above action buttons
- **Mobile**: Full-width action buttons stacked vertically

### Tables → Card Layout

At tablet breakpoints (≤768px), all data tables transform into card-based layouts:

**Desktop Table View:**

```
┌────────┬──────────┬───────┬───────┬─────────┬──────────┐
│ Order  │ Customer │ Total │ State │ Updated │ Actions  │
├────────┼──────────┼───────┼───────┼─────────┼──────────┤
│ #12345 │ john@... │ $99   │ New   │ Today   │ [▼][Btn] │
└────────┴──────────┴───────┴───────┴─────────┴──────────┘
```

**Mobile Card View:**

```
┌──────────────────────────────────┐
│ #12345                           │  ← Primary identifier
├──────────────────────────────────┤
│ Customer    │ john@example.com   │
│ Total       │ $99.00             │  ← Label : Value pairs
│ State       │ [New]              │
│ Updated     │ Nov 28, 2025       │
├──────────────────────────────────┤
│ [▼ Select State]                 │
│ [Apply State Change]             │  ← Stacked actions
└──────────────────────────────────┘
```

#### Implementation Details

1. **Hidden Table Headers**: `thead { display: none }` at mobile breakpoints
2. **Card Styling**: Each `<tr>` becomes a flex column with rounded borders and subtle background
3. **Data Labels**: `data-label` attributes on `<td>` elements provide context via CSS `::before` pseudo-elements
4. **Primary Cell**: First meaningful cell (order number, product title) is emphasized with larger font and bottom border
5. **Actions Section**: Separated at bottom with top border, buttons stack vertically on mobile

### Modals

- **Desktop**: Centered dialogs with max-width constraints
- **Tablet**: Near-full-width dialogs
- **Mobile**: Full-screen modals with no border radius for seamless appearance

### Touch Targets

All interactive elements meet the 44px minimum touch target size recommended by iOS Human Interface Guidelines:

| Element                    | Mobile Size                       |
| -------------------------- | --------------------------------- |
| Buttons                    | min-height: 44px                  |
| Icon buttons (Edit/Delete) | 44px × 44px                       |
| Checkboxes                 | 1.5rem × 1.5rem                   |
| Select dropdowns           | min-height: 44px                  |
| Form inputs                | min-height: 44px, font-size: 16px |

The 16px font size on inputs prevents iOS Safari from auto-zooming when focusing form fields.

### Settings Panel

- **Tabs**: Horizontal scroll on mobile with touch momentum
- **Form Grids**: Collapse from multi-column to single-column
- **Action Buttons**: Stack vertically on mobile
- **Color Pickers**: Wrap to accommodate smaller screens

### Dashboard

- **Metrics Grid**:
    - Desktop: Auto-fit grid (4+ columns)
    - Tablet: 2 columns
    - Mobile: Single column
- **Checklist**: Compact padding, wrapped items

## CSS Architecture

### Custom Properties

All responsive styles use the existing CSS custom properties for theming consistency:

```css
.nxp-ec-admin-table tr {
    background: var(--nxp-ec-surface-alt);
    border: 1px solid var(--nxp-ec-border);
}
```

### Scoped vs Global Styles

- **Global styles** (`admin-main.css`): Base responsive rules for common components
- **Scoped styles** (Vue `<style scoped>`): Component-specific responsive overrides

### Media Query Organization

Responsive styles are organized mobile-first within each component:

```css
/* Base styles (all screens) */
.component { ... }

/* Tablet breakpoint */
@media (max-width: 768px) {
    .component { ... }
}

/* Mobile breakpoint */
@media (max-width: 480px) {
    .component { ... }
}
```

## Components with Responsive Styles

| Component        | Card Layout | Touch Targets | Stacked Actions |
| ---------------- | ----------- | ------------- | --------------- |
| OrdersPanel      | ✓           | ✓             | ✓               |
| ProductTable     | ✓           | ✓             | ✓               |
| CustomersPanel   | ✓           | ✓             | ✓               |
| CouponsPanel     | ✓           | ✓             | ✓               |
| CategoryPanel    | ✓           | ✓             | ✓               |
| LogsPanel        | ✓           | ✓             | ✓               |
| SettingsPanel    | N/A         | ✓             | ✓               |
| DashboardPanel   | N/A         | ✓             | ✓               |
| OnboardingWizard | N/A         | ✓             | ✓               |
| ProductEditor    | N/A         | ✓             | ✓               |

## Testing Responsive Layouts

### Browser DevTools

1. Open Chrome/Firefox DevTools (F12)
2. Toggle device toolbar (Ctrl+Shift+M)
3. Test at these widths: 320px, 375px, 480px, 768px, 1024px

### Recommended Test Devices

- iPhone SE (375px) - Small mobile
- iPhone 14 (390px) - Standard mobile
- iPad Mini (768px) - Tablet breakpoint edge
- iPad (1024px) - Large tablet

### Visual Checklist

- [ ] Navigation scrolls horizontally without overflow
- [ ] Tables display as cards with readable labels
- [ ] All buttons are easily tappable (no mis-taps)
- [ ] Modals don't extend beyond viewport
- [ ] Form inputs don't trigger zoom on iOS
- [ ] No horizontal overflow on any panel

## Build Output

The responsive styles add approximately 8KB to the CSS bundle:

```
admin.css: ~39 kB (gzip: ~6.6 kB)
```

This includes:

- Base responsive rules in `admin-main.css`
- Scoped responsive styles in each Vue component
- Data-label pseudo-element styles for card layouts

## Future Improvements

Potential enhancements for future releases:

1. **Swipe gestures**: Swipe-to-delete or swipe-to-edit on card items
2. **Pull-to-refresh**: Native-feeling refresh on mobile
3. **Bottom navigation**: Optional bottom nav bar for frequently accessed sections
4. **Collapsible sidebar**: Drawer-style navigation for complex admin workflows
5. **Offline support**: Service worker for basic offline viewing of cached data
