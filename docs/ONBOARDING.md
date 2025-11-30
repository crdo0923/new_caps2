# Onboarding / Guided Tour (SmartStudy)

This project now has a skippable onboarding modal and an optional guided tour that highlights the dashboard's main UI features.

Files added/changed
- `dashboard.php` — added onboarding modal, guided tour overlay and client-side logic.
- `ajax_onboarding.php` — endpoint to mark onboarding seen and to reset (re-enable) onboarding for a user.
- `php/migrate_add_onboarding.php` — safe migration helper to add `onboarding_seen` TINYINT(1) column to `users` table.
- `settings.php`, `js/settings.js` — added "Show tutorial again" button in Settings which resets onboarding and opens the dashboard tour.

How it works
- A user is considered "new" when they have no points, no study_minutes and no tasks; the dashboard will show the onboarding modal automatically.
- The onboarding modal has a `Don't show again` checkbox — when used the preference is persisted to the `users.onboarding_seen` column (if available) or saved in session as fallback.
- Users can re-run the tour from Settings → "Show tutorial again". Settings will call the onboarding endpoint to reset the flag and redirect to `dashboard.php?start_tour=1` to start the tour.

Migration (recommended)
1. Backup your database.
2. From a browser open the migration helper:

   http://localhost/new_caps/php/migrate_add_onboarding.php

   - If the column doesn't exist you can click "Run Migration" on that page.

Manual test checklist
- As a new user (or after resetting `onboarding_seen`):
  1. Log in to the dashboard.
  2. The onboarding modal should appear.
  3. Choose "Start using app" to close the modal and start the guided tour.
  4. Finish the tour and confirm it no longer auto-shows.
- From Settings:
  1. Click "Show tutorial again"
  2. Confirm, and Settings will reset your onboarding state and redirect you to dashboard where the guided tour automatically starts.

Notes
- If the migration script cannot add the column (permissions/hosted DB), the AJAX endpoint will fallback to a session-based preference so the UX remains functional.
- The guided tour is intentionally minimal and lightweight — you can extend the steps and content easily inside `dashboard.php` in the `getTourSteps()` function.

If you'd like I can add:
- A Settings toggle to permanently store the preference (already stored when DB column exists).
- A re-run button inside the Profile or Dashboard so users don't need to go to Settings.
- A step-by-step tooltip system that attaches to specific DOM elements across pages (extended tour system).

Recent fix
-----------
- Fixed: a stray closing brace in the dashboard's inline JS caused a syntax error which prevented the guided tour from running. If you previously saw the tour fail to start, update to the latest code and retry the steps below.

Quick verification
------------------
1. Log in as a user that has either no activity (new user) OR run the Settings → "Show tutorial again" flow.
2. From Settings click "Show tutorial again" and confirm the dialog.
3. You should be redirected to `dashboard.php?start_tour=1` and the guided tour overlay should appear and progress through steps.
4. If the tour doesn't start, open the browser DevTools console and look for syntax errors (e.g. "Unexpected token '}'"). If you see one, please update your local copy to the latest code.
