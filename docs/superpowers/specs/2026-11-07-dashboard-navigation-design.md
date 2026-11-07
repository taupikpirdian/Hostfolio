# Dashboard and Navigation Design

## Intent

Refactor the authenticated Hostfolio interface to match the supplied dashboard reference. `/` remains the dashboard, and the sidebar contains exactly five destinations: Dashboard, Servers, Services, Billing, and Settings.

## Goals

- Present an at-a-glance inventory and billing dashboard using persisted application data.
- Include financial metrics and cashflow by month, upcoming renewals, infrastructure inventory, and a searchable/paged hosted-services table.
- Keep all existing CRUD, reporting, import, renewal, and settings capabilities reachable from the five destinations.
- Keep the Filament 5, Livewire, Blade, and existing Tailwind v4 stack.

## Non-goals

- No public landing page, external monitoring, or sample/demo financial values.
- No schema or business workflow changes.
- No new frontend framework or chart dependency; use an accessible CSS bar chart.

## Approaches

1. Retheme the existing stats widgets. Lowest change, but cannot provide the complete reference layout or consolidate navigation cleanly.
2. Build a custom Filament dashboard and four navigation overview pages, linking to existing resources. Chosen: matches the reference while preserving the existing CRUD implementation.
3. Replace Filament with a standalone SPA. Rejected because it duplicates authentication, authorization, and CRUD already implemented in Filament.

## Design

The panel groups Dashboard, Servers, Services, and Billing under Workspace, and Settings under Configuration. Resource pages stay routable and are linked from overview pages but do not register separate sidebar entries. The root dashboard uses real Eloquent aggregates and report rows, with empty states when no data exists. A custom Blade dashboard uses responsive cards, a monthly cashflow chart rendered with CSS, upcoming renewals, infrastructure summaries, and a filtered services table. The four overview pages provide clear links to the existing resource lists and workflows.

## Constraints and decisions

- Preserve `/` as the panel dashboard and `/login` for guests.
- Display Indonesian rupiah/date formatting consistent with Hostfolio's Asia/Jakarta locale.
- Keep page access checks aligned with inventory, billing, and settings permissions.
- The supplied screenshot is the visual source of truth for hierarchy, spacing, colors, and the five menu labels.
