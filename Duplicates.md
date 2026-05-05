Files #Public-bookings.js
Excavated fifteen instances of dead code, duplication, and architectural inconsistencies
Excavated fifteen instances of dead code, duplication, and architectural inconsistencies
Let me read through the full public-booking.js file carefully to audit it.


Full audit of public-booking.js

Continue audit

Continue audit - state updates, fetch functions

Continue audit - render functions and helpers

Continue - renderProviderProfileCard, renderServiceCard, and all render helpers

Continue audit - remaining render functions and utilities

Continue audit - end of file, utility functions
Now let me look at the missing section between 1160-1240 and the renderRescheduleStage:


Fill in truncated section - renderLookupStage end and renderManageSummary
Now I have a complete picture of the file. Let me compile all my findings:

1. DEAD CODE: safeJson (line 1998-2004) This function is defined but never called anywhere in the file. All API calls use apiRequest() which returns the parsed payload directly. safeJson is completely unused.

2. DUPLICATE LOGIC: formatSlotLabel vs formatSlotSummary

formatSlotLabel (line 1805): builds "9:00 AM – 10:00 AM" — time range only, requires both start and end.
formatSlotSummary (line 1819): builds "Wed, Apr 30, 9:00 AM – 10:00 AM" — date + time range.
These share nearly identical internals. Both create Intl.DateTimeFormat with hour12, timeZone, same options. formatSlotLabel is only called in renderSlotSection (line 1476), as a fallback when slot.label isn't present. formatSlotSummary is called in multiple places. formatSlotLabel could be a thin wrapper calling formatSlotSummary with different options, or better: formatSlotSummary could be refactored to accept an option for whether to include the date. The duplication is the Intl.DateTimeFormat setup code repeated verbatim.

3. DUPLICATE LOGIC: formatDateSelectLabel and formatDatePillLabel (lines 1962-1969) formatDatePillLabel is literally just return formatDateSelectLabel(dateStr). It's a pass-through wrapper with no differentiation. The comment says "Keep as a function declaration... to avoid TDZ during first render" but this is about hoisting, and since formatDateSelectLabel itself is also a function declaration it would be hoisted too. This is a redundant wrapper.

Wait, actually both are function declarations so both would be hoisted. The TDZ issue only applies to const/let. Both are function declarations so they're fully hoisted. The comment is misleading — there's no TDZ issue here. formatDatePillLabel is dead weight.

4. DUPLICATE ERROR HANDLING PATTERN in selectAppointmentFromSearch vs handleLookupSubmit …

Show more
Done
Good read on the full 2,057 lines. Here's what I found, grouped by severity.

Dead code
safeJson — line 1998, never called

js
async function safeJson(response) {
  try { return await response.json(); } catch (error) { return null; }
}
All API calls go through apiRequest() which already returns the parsed payload. safeJson has no callers anywhere in the file. Safe to delete.

formatDatePillLabel — line 1967, pure pass-through

js
function formatDatePillLabel(dateStr) {
  return formatDateSelectLabel(dateStr);
}
The comment claims it avoids TDZ, but both functions are function declarations — fully hoisted, no TDZ risk. This is just a wrapper for itself. The single call site (renderDatePickerField, line 1586) can call formatDateSelectLabel directly. Delete formatDatePillLabel.

Duplicate logic
formatSlotLabel vs formatSlotSummary — lines 1805 and 1819

Both functions instantiate identical Intl.DateTimeFormat formatters with the same use12h, appTz, hour, minute, hour12, timeZone options. The only difference is formatSlotSummary prepends a date label. formatSlotLabel (called once, line 1476) should just call formatSlotSummary and strip the date prefix — or formatSlotSummary should accept a { includeDate } option. Right now you have ~25 lines of copied formatter setup.

duration vs durationMinutes double-mapping — lines 410–411

js
duration: svc.durationMin ?? svc.duration_min ?? svc.duration,
durationMinutes: svc.durationMin ?? svc.duration_min ?? svc.duration,
Both fields resolve to identical values via the same chain. renderServiceCard (line 1438) reads service.duration ?? service.durationMinutes — so durationMinutes is never reached because duration is always set first. One of these fields is vestigial. Pick duration as canonical, remove durationMinutes from the mapping, and update renderServiceCard to read only service.duration.

Identical catch block pair in handleManageCancel — lines 294–309

js
catch (error) {
  if (error instanceof SubmissionError) {
    updateManage(prev => ({ ...prev, cancelSubmitting: false, cancelError: error.message }));
    return;
  }
  updateManage(prev => ({ ...prev, cancelSubmitting: false, cancelError: error.message ?? '...' }));
}
Both arms do the same state update. The only difference is a ?? fallback string on the generic branch, but SubmissionError always has message set. Collapse into one:

js
catch (error) {
  updateManage(prev => ({
    ...prev,
    cancelSubmitting: false,
    cancelError: error.message ?? 'Unable to cancel the booking.',
  }));
}
Same pattern in handleSubmit — lines 820–835

The two catch arms do identical updateDraft calls differing only in errors: { ...error.details } on the SubmissionError path. Extract a shared handler or collapse with const details = error instanceof SubmissionError ? error.details : {}.

resolveAppBaseUrl re-implements url-helpers.js:getBaseUrl()

resolveAppBaseUrl (line 1887) checks ctx.appBaseUrl → window.__BASE_URL__ → ctx.bookingBaseUrl — the same resolution chain as getBaseUrl() in url-helpers.js. That module is not imported here. Either import and delegate, or document explicitly that this file intentionally runs standalone without the shared module.

Orphans
today variable — line 27

js
const today = new Date();
const defaultDate = today.toISOString().slice(0, 10);
today is referenced only on the next line and never again. Inline it: const defaultDate = new Date().toISOString().slice(0, 10).

UI_CLASSES.cardBase inconsistently applied in renderManageSummary — lines 1254–1264

The "Current time" <div> uses ${UI_CLASSES.cardBase}. The "Provider" and "Service" <div>s directly below it hardcode the identical class string rounded-2xl border border-slate-200 px-4 py-3. If UI_CLASSES.cardBase changes, those two cards won't update with it.

Double updateDraft call in handleDateChange — lines 446–465

When a date is selected that isn't in the prefetched calendar, the function calls updateDraft twice — once to write resolvedLocation, and again to write appointmentDate + slots. This forces two render cycles. Merge both into a single updateDraft call.

Summary table
Item	Line(s)	Type	Action
safeJson	1998	Dead function	Delete
formatDatePillLabel	1967	Dead wrapper	Delete, use formatDateSelectLabel directly
formatSlotLabel dupe internals	1805	Duplicate logic	Delegate to formatSlotSummary
durationMinutes field	411	Orphan field	Remove, duration is canonical
handleManageCancel catch arms	294–309	Duplicate catch	Collapse to one arm
handleSubmit catch arms	820–835	Duplicate catch	Collapse with details extraction
resolveAppBaseUrl vs url-helpers.js	1887	Re-implementation	Import or document the split
today variable	27	Orphan var	Inline
Hardcoded card classes in renderManageSummary	1258, 1262	Stale constant bypass	Use UI_CLASSES.cardBase
Double updateDraft in handleDateChange	446–465	Extra render cycle	Merge into one call



