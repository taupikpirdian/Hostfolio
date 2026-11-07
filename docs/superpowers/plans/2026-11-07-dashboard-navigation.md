# Dashboard and Navigation Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Refactor the authenticated Hostfolio dashboard and sidebar to match the supplied reference while keeping all existing workflows accessible.

**Architecture:** Use a custom Filament dashboard page backed by scoped Eloquent aggregates and existing report services. Add four concise Filament overview pages and hide resource pages from sidebar discovery while linking to them from those pages.

**Tech Stack:** Laravel 13, Filament 5, Livewire, Blade, Tailwind CSS 4, MySQL.

**Spec:** `docs/superpowers/specs/2026-11-07-dashboard-navigation-design.md`

## Global Constraints

- Keep `/` as the authenticated dashboard and guest redirect `/login`.
- Keep exactly five sidebar destinations: Dashboard, Servers, Services, Billing, Settings.
- Use persisted data; do not add mock/sample dashboard metrics.
- Preserve current resource routes, authorization, and business workflows.
- No new chart or UI dependencies.

## Review Focus

- Empty database: dashboard sections render clear zero/empty states.
- User without inventory access: inventory values and links are not exposed.
- User without billing access: financial data and links are not exposed.
- Long service names and mobile viewport: layout remains usable without horizontal page overflow.
- Service search/status filters: pagination count and result rows stay consistent.

---

### Task 1: Consolidate sidebar navigation

**Files:**
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Modify: Filament resource classes under `app/Filament/Resources/`
- Create: `app/Filament/Pages/ServersOverview.php`
- Create: `app/Filament/Pages/ServicesOverview.php`
- Create: `app/Filament/Pages/SettingsOverview.php`
- Create: matching overview Blade views under `resources/views/filament/pages/`

- [ ] Configure Workspace and Configuration groups in the reference order.
- [ ] Register five destinations only; suppress individual resource navigation entries.
- [ ] Add overview pages with permission-aware links to existing CRUD pages and workflows.

### Task 2: Build the reference dashboard

**Files:**
- Create: `app/Filament/Pages/Dashboard.php`
- Create: `resources/views/filament/pages/dashboard.blade.php`
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Modify: `resources/css/app.css` only if panel theme styling requires it.

- [ ] Provide inventory counts, unpaid balances, period financial summary, monthly cashflow, near-term renewals, physical infrastructure, and hosted-service rows from persisted data.
- [ ] Render the reference hierarchy responsively with Indonesian currency/date formatting, empty states, search, status filter, and pagination.
- [ ] Keep the root URL as the dashboard and preserve permission boundaries.

### Task 3: Final integration review

**Files:**
- Review: changed panel, page, resource, and Blade files.

- [ ] Confirm only the five requested navigation labels are registered and all existing resource destinations remain linked.
- [ ] Run syntax/style checks and inspect the final diff; do not run application tests unless requested.
