# Prompt: Systematic Bug Hunt & Debugging — Enterprise Edition

> **Use case:** You have a bug report, an error message, or something just "feels wrong" in your app. These prompts implement enterprise-grade debugging with risk assessment, verification checkpoints, and rollback procedures.

---

## Prompt A: Debug a Specific Error (Enhanced)

> **Token Budget:** ~2,000 tokens
> **Execution Time:** 2-4 minutes
> **Difficulty:** Intermediate
> **Requires:** Error message, codebase access
> **Outputs:** Root cause, fix, test case, prevention measures

```
I have an error in my application and I need your help debugging it systematically.

══════════════════════════════════════════════════════════════
CONTEXT
══════════════════════════════════════════════════════════════
- Application: [describe your app in one sentence]
- Tech stack: [e.g., React 19, TypeScript, tRPC, PostgreSQL]
- Environment: [development / staging / production]
- When it happens: [e.g., "when a user clicks the submit button on the order form"]
- How often: [every time / intermittent / only on mobile / only in production]
- Recent changes: [describe any code changes made before the bug appeared, or "none"]
- User impact: [how many users affected, what functionality is broken]

══════════════════════════════════════════════════════════════
THE ERROR
══════════════════════════════════════════════════════════════
[Paste the full error message, stack trace, or console output here. Include everything.]

══════════════════════════════════════════════════════════════
EXPECTED BEHAVIOR
══════════════════════════════════════════════════════════════
[What should happen instead?]

══════════════════════════════════════════════════════════════
ATTEMPTS SO FAR
══════════════════════════════════════════════════════════════
[List anything you've already attempted, or "nothing yet"]

══════════════════════════════════════════════════════════════
DEBUGGING WORKFLOW
══════════════════════════════════════════════════════════════

PHASE 1: ERROR INTERPRETATION
1. Explain what this error message means in plain language.
2. Identify which file, function, and line the error originates from.
3. Map the call chain that led to this error:
   [caller] → [function A] → [function B] → [error location]

PHASE 2: ROOT CAUSE ANALYSIS
List every possible cause, ranked by probability:

| Rank | Possible Cause | Probability | How to Verify | Evidence Needed |
|------|---------------|-------------|---------------|-----------------|
| 1 | | High/Med/Low | | |
| 2 | | | | |
| 3 | | | | |

PHASE 3: FIX IMPLEMENTATION
For the most likely cause:

BEFORE (current code):
```[language]
[paste current code]
```

AFTER (fixed code):
```[language]
[show complete fixed code]
```

EXPLANATION:
- Why this fix works: [explain]
- What was wrong: [explain]

PHASE 4: RISK ASSESSMENT
Before applying this fix:

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Fix breaks other functionality | | | |
| Fix doesn't solve the problem | | | |
| Fix introduces new bug | | | |

PHASE 5: VERIFICATION CHECKLIST
After applying the fix:
- [ ] Error no longer occurs
- [ ] Expected behavior works
- [ ] No regression in related features
- [ ] Edge cases handled
- [ ] Test added for this case

PHASE 6: PREVENTION
1. Test case to catch this bug in the future:
   ```[language]
   [test code]
   ```

2. Defensive coding patterns to prevent similar issues:
   - [pattern 1]
   - [pattern 2]

3. Code review checklist addition:
   - [ ] [new check item]

PHASE 7: RELATED ISSUES
Are there other places in the codebase where the same pattern exists?

| File | Location | Same Bug? | Action Needed |
|------|----------|-----------|---------------|
| | | Yes/No | |

══════════════════════════════════════════════════════════════
CONFIDENCE ASSESSMENT
══════════════════════════════════════════════════════════════
- Confidence this is the root cause: [1-10]
- Confidence this fix will work: [1-10]
- If either < 8, what additional information would help:
```

---

## Prompt B: Reproduce a Reported Bug (Enhanced)

> **Token Budget:** ~1,500 tokens
> **Execution Time:** 1-3 minutes
> **Difficulty:** Intermediate
> **Requires:** User bug report, codebase access
> **Outputs:** Reproduction steps, code paths, debugging instrumentation

```
A user reported a bug and I need to reproduce it before I can fix it.

══════════════════════════════════════════════════════════════
USER REPORT
══════════════════════════════════════════════════════════════
[Paste the bug report, user complaint, or support ticket here]

══════════════════════════════════════════════════════════════
APPLICATION CONTEXT
══════════════════════════════════════════════════════════════
- Application: [describe your app]
- Tech stack: [your stack]
- The feature involved: [which part of the app]
- Recent deployments: [any recent changes to this area]

══════════════════════════════════════════════════════════════
REPRODUCTION WORKFLOW
══════════════════════════════════════════════════════════════

PHASE 1: FACT EXTRACTION
Extract and organize the key facts:

| Fact Category | Information | Confidence | Source |
|----------------|-------------|------------|--------|
| User action | [what they did] | High/Med/Low | [quote from report] |
| Expected result | [what should happen] | | |
| Actual result | [what happened] | | |
| Environment | [device/browser/OS] | | |
| Frequency | [always/sometimes/once] | | |
| User ID | [if available] | | |
| Timestamp | [when it occurred] | | |

Missing information (need to ask user):
- [question 1]
- [question 2]

PHASE 2: REPRODUCTION PLAN
Write the exact steps to reproduce:

| Step | Action | Expected Result | Data/Input |
|------|--------|-----------------|------------|
| 1 | | | |
| 2 | | | |
| 3 | | | |
| 4 | | | |

Prerequisites:
- Account state: [logged in, specific subscription, etc.]
- Data conditions: [specific records must exist]
- Timing factors: [race conditions, scheduled jobs, etc.]

PHASE 3: CODE PATH ANALYSIS
Based on the user's description:

Execution Flow (expected):
[Entry point] → [Handler] → [Service] → [Database] → [Response]

Execution Flow (likely broken):
[Entry point] → [Handler] → [???] → [ERROR]

Files likely involved:
| File | Function | Why Suspected |
|------|----------|---------------|
| | | |

PHASE 4: DEBUGGING INSTRUMENTATION
Add these diagnostic tools:

CONSOLE.LOG STATEMENTS:
```[language]
// In [file], line [X]
console.log('[context]', { [variables to log] });
```

BREAKPOINTS:
- File: [path], Line: [X], Condition: [if any]
- File: [path], Line: [X], Condition: [if any]

NETWORK INSPECTOR:
- Watch for: [endpoint URLs]
- Check: [request payload, response, timing]

DATABASE QUERIES:
- Enable query logging
- Watch for: [specific tables/queries]

PHASE 5: VERIFICATION
After attempting reproduction:
- [ ] Bug successfully reproduced
- [ ] Steps documented for future reference
- [ ] Error captured (screenshot, logs, stack trace)
- [ ] Root cause identified or narrowed down

══════════════════════════════════════════════════════════════
CONFIDENCE ASSESSMENT
══════════════════════════════════════════════════════════════
- Confidence in reproduction steps: [1-10]
- If < 8, what additional information is needed:
```

---

## Prompt C: Full Codebase Bug Sweep (Enhanced)

> **Token Budget:** ~5,000 tokens
> **Execution Time:** 5-10 minutes
> **Difficulty:** Advanced
> **Requires:** Full codebase access
> **Outputs:** Categorized bug list with severity, risk matrix, fix recommendations

```
I want you to perform a thorough bug sweep of my codebase. Look for potential bugs, not just existing errors. Act as a QA engineer trying to break the application.

══════════════════════════════════════════════════════════════
CODEBASE CONTEXT
══════════════════════════════════════════════════════════════
- Application: [describe your app]
- Tech stack: [your stack]
- Areas of concern: [e.g., "payments", "authentication", "data consistency", or "everything"]
- Recent changes: [any recent refactoring or new features]
- Known issues: [existing bugs you're aware of]

══════════════════════════════════════════════════════════════
BUG SWEEP CATEGORIES
══════════════════════════════════════════════════════════════

CATEGORY 1: DATA INTEGRITY ISSUES (Severity Weight: 10)
- Race conditions (two users modifying the same data simultaneously)
- Missing database transactions where multiple writes should be atomic
- Orphaned records (foreign key references to deleted rows)
- Missing input validation or sanitization
- Integer overflow or precision loss in financial calculations
- Missing cascade delete rules

CATEGORY 2: AUTHENTICATION & AUTHORIZATION (Severity Weight: 10)
- Endpoints that should require authentication but don't
- Missing role-based access checks (can a regular user access admin routes?)
- Session/token expiration handling
- CSRF protection gaps
- IDOR (Insecure Direct Object Reference) vulnerabilities
- Privilege escalation paths

CATEGORY 3: ERROR HANDLING (Severity Weight: 8)
- Unhandled promise rejections or uncaught exceptions
- Missing try/catch blocks around external API calls
- Error messages that leak sensitive information to the client
- Missing fallback UI for error states
- Silent failures (catch blocks that swallow errors)

CATEGORY 4: EDGE CASES (Severity Weight: 6)
- What happens with empty inputs, null values, or undefined?
- What happens with extremely long strings or very large numbers?
- What happens when the database is unreachable?
- What happens when an external API times out?
- What happens when the user's session expires mid-action?
- Unicode/special character handling

CATEGORY 5: CONCURRENCY & TIMING (Severity Weight: 9)
- Double-submit on forms (user clicks button twice)
- Stale data displayed after another user makes changes
- Race conditions in booking/reservation systems
- Cron jobs or scheduled tasks that could overlap
- Optimistic locking missing where needed

CATEGORY 6: MOBILE & CROSS-BROWSER (Severity Weight: 5)
- Touch target sizes too small (< 44px)
- Horizontal overflow on small screens
- Features that depend on APIs not available in all browsers
- Keyboard navigation and accessibility issues
- Responsive design breakpoints

══════════════════════════════════════════════════════════════
OUTPUT FORMAT
══════════════════════════════════════════════════════════════

For each issue found, provide:

BUG REPORT:
| ID | Category | Severity | File | Line | Description |
|----|----------|----------|------|------|-------------|
| B1 | | | | | |

RISK MATRIX:
| Bug ID | Likelihood (1-5) | Impact (1-5) | Risk Score | Priority |
|--------|------------------|--------------|------------|----------|
| B1 | | | L×I | P0/P1/P2/P3 |

Risk Score Legend:
- 1-6: Low (P3) - Fix when convenient
- 7-12: Medium (P2) - Fix this sprint
- 13-20: High (P1) - Fix this week
- 21-25: Critical (P0) - Fix immediately

DETAILED FIX:
For each bug, provide:
- File: [exact path]
- Line: [line number]
- Bug: [one-line description]
- Impact: [what breaks in production]
- Reproduction: [steps to trigger]
- Fix: [exact code change]
- Test: [test case to add]

══════════════════════════════════════════════════════════════
VERIFICATION CHECKLIST
══════════════════════════════════════════════════════════════
After sweep is complete:
- [ ] All categories checked
- [ ] All files in concern areas reviewed
- [ ] Risk scores calculated for each bug
- [ ] Fixes provided for all P0 and P1 bugs
- [ ] Tests suggested for critical paths

══════════════════════════════════════════════════════════════
SUMMARY METRICS
══════════════════════════════════════════════════════════════
| Metric | Count |
|--------|-------|
| Total bugs found | |
| P0 (Critical) | |
| P1 (High) | |
| P2 (Medium) | |
| P3 (Low) | |
| Files affected | |
| Estimated fix time | |
```

---

## Prompt D: Root Cause Analysis (Post-Mortem) (Enhanced)

> **Token Budget:** ~2,500 tokens
> **Execution Time:** 3-5 minutes
> **Difficulty:** Advanced
> **Requires:** Incident details, timeline, logs
> **Outputs:** Complete post-mortem document with timeline, root cause, prevention plan

```
We had an incident in production and I need to perform a root cause analysis.

══════════════════════════════════════════════════════════════
INCIDENT SUMMARY
══════════════════════════════════════════════════════════════
- What happened: [describe the incident]
- When it started: [date/time with timezone]
- When it was resolved: [date/time with timezone]
- Duration: [total downtime or degraded service time]
- Impact: [how many users affected, what functionality was broken, revenue impact]
- How it was discovered: [monitoring alert, user report, internal testing, etc.]
- Immediate fix applied: [what was done to stop the bleeding]
- Who responded: [team members involved]

══════════════════════════════════════════════════════════════
POST-MORTEM DOCUMENT
══════════════════════════════════════════════════════════════

SECTION 1: EXECUTIVE SUMMARY
Write a 3-5 sentence summary suitable for stakeholders:
- What happened (non-technical)
- How long it lasted
- How many users were affected
- What was done to fix it
- What will be done to prevent recurrence

SECTION 2: TIMELINE
Reconstruct the sequence of events:

| Time (UTC) | Event | Source | Action Taken |
|------------|-------|--------|--------------|
| | Incident trigger | [logs/metrics] | |
| | First symptom detected | [alert/user report] | |
| | Team notified | [pagerduty/slack] | |
| | Diagnosis started | | |
| | Root cause identified | | |
| | Fix implemented | | |
| | Service restored | | |
| | Post-incident review scheduled | | |

SECTION 3: ROOT CAUSE ANALYSIS
Use the "5 Whys" technique:

Why 1: Why did the incident occur?
Answer: [direct cause]
↓
Why 2: Why did [direct cause] happen?
Answer: [deeper cause]
↓
Why 3: Why did [deeper cause] happen?
Answer: [even deeper cause]
↓
Why 4: Why did [even deeper cause] happen?
Answer: [systemic issue]
↓
Why 5: Why did [systemic issue] exist?
Answer: [fundamental root cause]

ROOT CAUSE CLASSIFICATION:
- [ ] Code bug
- [ ] Infrastructure failure
- [ ] Configuration error
- [ ] Process failure
- [ ] Capacity issue
- [ ] Third-party dependency
- [ ] Security incident
- [ ] Other: [specify]

SECTION 4: CONTRIBUTING FACTORS
What made this worse or delayed detection/resolution:

| Factor | Impact | How to Address |
|--------|--------|----------------|
| Missing alert | [delayed detection by X min] | |
| Insufficient logging | [harder to diagnose] | |
| Missing runbook | [longer time to fix] | |
| Code review gap | [bug not caught] | |
| Test coverage gap | [bug not caught in CI] | |
| Documentation outdated | [confusion during fix] | |

SECTION 5: IMPACT ANALYSIS

USER IMPACT:
- Users affected: [number or percentage]
- Features unavailable: [list]
- Data loss: [yes/no, details if yes]
- User-visible symptoms: [describe]

BUSINESS IMPACT:
- Revenue impact: [estimate if applicable]
- Customer trust impact: [high/medium/low]
- SLA breach: [yes/no, details if yes]
- Compliance impact: [any regulatory concerns]

SECTION 6: PREVENTION PLAN
Specific actions to prevent recurrence:

| Action | Owner | Priority | Due Date | Status |
|--------|-------|----------|----------|--------|
| | | P0/P1/P2 | | Pending |
| | | | | |
| | | | | |

For each action, include:
- What will be done
- Why it will prevent this issue
- How to verify it's implemented correctly

SECTION 7: DETECTION & RESPONSE IMPROVEMENTS

MONITORING:
- New alerts to add: [specific metrics and thresholds]
- Existing alerts to tune: [adjustments needed]
- Dashboards to create: [what visibility is missing]

LOGGING:
- Logs to add: [specific events to log]
- Log level adjustments: [what needs more/less detail]
- Log aggregation improvements: [any gaps]

RUNBOOKS:
- New runbooks needed: [for what scenarios]
- Existing runbooks to update: [which ones]

SECTION 8: PROCESS IMPROVEMENTS

DEPLOYMENT PROCESS:
- [ ] Change: [what should change]
- [ ] Reason: [why]

CODE REVIEW PROCESS:
- [ ] Change: [what should change]
- [ ] Reason: [why]

ON-CALL PROCESS:
- [ ] Change: [what should change]
- [ ] Reason: [why]

INCIDENT RESPONSE:
- [ ] Change: [what should change]
- [ ] Reason: [why]

SECTION 9: LESSONS LEARNED

WHAT WENT WELL:
- [positive aspect 1]
- [positive aspect 2]

WHAT COULD BE IMPROVED:
- [area for improvement 1]
- [area for improvement 2]

WHAT WAS SURPRISING:
- [unexpected finding]

SECTION 10: ACTION ITEMS TRACKER

| # | Action | Type | Owner | Due | Status |
|---|--------|------|-------|-----|--------|
| 1 | | Prevention | | | |
| 2 | | Detection | | | |
| 3 | | Process | | | |
| 4 | | Documentation | | | |

══════════════════════════════════════════════════════════════
VERIFICATION CHECKLIST
══════════════════════════════════════════════════════════════
- [ ] Timeline is complete and accurate
- [ ] Root cause identified (not just symptom)
- [ ] All contributing factors listed
- [ ] Prevention actions are specific and assigned
- [ ] Action items have owners and due dates
- [ ] Document suitable for sharing with stakeholders
```

---

## Prompt E: Production-Readiness Audit (The Nuclear Option) (Enhanced)

> **Token Budget:** ~10,000+ tokens
> **Execution Time:** 10-30 minutes
> **Difficulty:** Expert
> **Requires:** Full codebase access, production environment details
> **Outputs:** Complete audit report with risk matrix, fixes, verification, rollback procedures

This is the most aggressive prompt in the library. It assumes 10,000 real users hitting your app simultaneously with real money. Use it before launch, before fundraising demos, or after any major feature release.

```
You are a senior backend engineer doing a production-readiness audit. This is NOT a code review — it's a failure-mode analysis. Assume 10,000 real users hitting this app simultaneously with real money.

══════════════════════════════════════════════════════════════
AUDIT SCOPE
══════════════════════════════════════════════════════════════
- Application: [describe your app]
- Tech stack: [full stack details]
- Target scale: [expected users/requests per second]
- Audit mode: [full codebase / files changed since commit X / specific feature]
- Pass number: [1/2/3 if doing multiple passes]

══════════════════════════════════════════════════════════════
AUDIT CATEGORIES (IN ORDER OF SEVERITY)
══════════════════════════════════════════════════════════════

CATEGORY 1: MONEY BUGS (P0 — Revenue Loss)
Check EVERY instance:
- Payment flows: Can a user pay and not get what they paid for? Can they get it without paying?
- Overselling: Can stock/seats go negative under concurrent purchases?
- Coupon/promo abuse: Can codes be used more times than allowed? TOCTOU on redemption counts?
- Subscription state: Is plan status checked atomically? Can a user access premium features after expiry?
- Currency handling: Floating-point math on money? Wrong currency codes accepted?
- Webhook idempotency: Can a payment webhook fire twice and double-credit?
- Refund flows: Can refunds be processed multiple times?
- Invoice generation: Can invoices be duplicated or have wrong amounts?

CATEGORY 2: DATA ISOLATION (P0 — Legal/Trust)
Check EVERY query that takes an ID from the client:
- Can User A see/modify User B's data?
- Are admin-only fields exposed in public API responses?
- Do public endpoints (shareable pages, forms, profiles) leak private data?
- Is tenant/user ID always derived from the authenticated session, never from request params?
- Can deleted data still be accessed via direct ID reference?
- Are soft-deleted records properly filtered?

CATEGORY 3: AUTH & SESSION (P0 — Security)
Check EVERY endpoint:
- Can auth be bypassed on any endpoint? Missing middleware?
- Do role checks (admin, staff, owner) actually verify against the database or just trust the JWT?
- Multi-tab behavior: Does changing subscription/role in one tab break others?
- Can an expired/cancelled user still access paid features due to stale cache?
- IDOR: Can changing an ID in the URL/request give access to another user's resources?
- Session fixation vulnerabilities?
- Password reset token expiration and single-use?
- API key/token rotation mechanisms?

CATEGORY 4: DATABASE UNDER LOAD (P1 — Scalability)
Check EVERY query:
- N+1 queries: Loops that run a query per item instead of batch/join
- Missing indexes: Columns used in WHERE/JOIN/ORDER BY without indexes
- Unbounded queries: SELECT without LIMIT on user-facing endpoints
- Full table scans on large tables (analytics, logs, notifications)
- Connection pool exhaustion: Long-running transactions holding connections
- Missing pagination on list endpoints
- COUNT(*) on large tables without caching
- Write amplification from unnecessary updates

CATEGORY 5: RACE CONDITIONS (P1 — Data Corruption)
Check EVERY read-then-write pattern:
- Read-then-write patterns without transactions (check count → increment count)
- Concurrent form submissions, event registrations, stock purchases
- Optimistic UI that doesn't handle server-side conflicts
- Bulk operations that partially fail and leave inconsistent state
- Missing row-level locks where needed
- Deadlock potential from lock ordering
- UUID collision handling (if using client-generated IDs)

CATEGORY 6: WEBHOOK & EXTERNAL SERVICE FAILURES (P1 — Silent Failures)
Check EVERY external call:
- What happens when the payment provider is down? Does the user see an error or get stuck?
- Are webhook handlers idempotent? Can they be safely retried?
- Do external API calls have timeouts? What happens on timeout?
- Are failed webhooks logged and retryable?
- Circuit breakers implemented?
- Retry logic with exponential backoff?
- Dead letter queues for failed operations?
- Graceful degradation when services are unavailable?

CATEGORY 7: ERROR HANDLING (P2 — Debugging)
Check EVERY catch block:
- Catch blocks that swallow errors silently (catch(e) {})
- Generic "Something went wrong" errors that give no diagnostic info in logs
- Missing error boundaries that crash the whole app
- Stack traces or internal paths leaked to the client
- Error context not logged (user ID, request ID, timestamp)
- No error categorization for alerting
- Missing Sentry/error tracking integration

CATEGORY 8: DATA INTEGRITY (P2 — Correctness)
Check EVERY input/output:
- Required fields that can be empty/null due to missing validation
- Enum values accepted from client without server-side validation
- Date/time without timezone handling
- String fields without length limits (can a user submit 10MB in a text field?)
- Number fields without bounds checking
- Email validation (format + deliverability)
- URL validation (protocol + domain)
- File upload validation (type + size + content)

CATEGORY 9: EMAIL & NOTIFICATIONS (P2 — Reputation)
Check EVERY notification:
- HTML injection in user-supplied content rendered in emails
- Email sends in the request path (blocking response on SMTP)
- Missing unsubscribe mechanisms
- Notification dedup: Can the same notification fire 100 times?
- Rate limiting on notifications per user
- Bounce handling and list hygiene
- SPF/DKIM/DMARC configuration

CATEGORY 10: DEPLOYMENT & CONFIG (P2 — Operational)
Check EVERY config:
- Secrets in source code or client bundles
- Missing CORS configuration
- No rate limiting on auth/payment/form endpoints
- Health check endpoints that don't actually verify database connectivity
- Missing Content-Security-Policy headers
- Environment variable validation on startup
- Feature flag rollback capability
- Database migration rollback scripts

══════════════════════════════════════════════════════════════
OUTPUT FORMAT
══════════════════════════════════════════════════════════════

BUG REPORT TABLE:
| ID | Category | Severity | File | Line | Bug | Impact |
|----|----------|----------|------|------|-----|--------|
| E1 | | P0/P1/P2 | | | | |

RISK MATRIX:
| Bug ID | Likelihood (1-5) | Impact (1-5) | Risk Score | Priority | SLA |
|--------|------------------|--------------|------------|----------|-----|
| E1 | | | L×I | P0=now | Fix now |
| E2 | | | | P1=24h | |
| E3 | | | | P2=1wk | |

DETAILED FIX FOR EACH BUG:
```[language]
// File: [path], Line: [X]
// BEFORE:
[show current code]

// AFTER:
[show fixed code]

// EXPLANATION:
[why this fix works]
```

VERIFICATION CHECKLIST FOR EACH FIX:
- [ ] Fix implemented correctly
- [ ] Unit test added for this case
- [ ] Integration test passes
- [ ] No regression in existing tests
- [ ] Documentation updated
- [ ] Deployed to staging
- [ ] Verified on staging
- [ ] Ready for production

ROLLBACK PROCEDURE FOR EACH FIX:
If this fix causes issues in production:
1. Revert commit: [commit hash]
2. Or apply this rollback:
```[language]
[rollback code]
```
3. Verify: [verification steps]
4. Notify: [who to alert]

══════════════════════════════════════════════════════════════
POST-AUDIT VERIFICATION
══════════════════════════════════════════════════════════════
After all fixes:
- [ ] TypeScript check passes: 0 errors
- [ ] Full test suite passes: 0 failures
- [ ] Lint check passes: 0 errors
- [ ] Build succeeds
- [ ] All P0 bugs fixed
- [ ] All P1 bugs fixed or documented as accepted risk
- [ ] P2 bugs prioritized for next sprint

══════════════════════════════════════════════════════════════
CONFIDENCE ASSESSMENT
══════════════════════════════════════════════════════════════
- Confidence in audit completeness: [1-10]
- Confidence in fixes: [1-10]
- Remaining risk areas (if any):
```

### How to Use Prompt E

| Scenario | Command |
|----------|---------|
| First audit | "Audit my entire codebase" |
| After deploy | "Audit only files changed since [commit hash]" |
| Before launch | "Do 3 passes — each pass should find bugs the previous missed" |
| New feature | "Audit only category 1-5 on this new feature: [describe feature]" |
| Specific concern | "Deep dive on category [X]: [specific concern]" |

### Stack-Specific Context Template

Add this at the end of the prompt for your specific stack:

```
══════════════════════════════════════════════════════════════
STACK CONTEXT
══════════════════════════════════════════════════════════════
Database: [ORM + driver + provider]
- [specific quirks, e.g., "postgres.js uses .count not .rowCount"]
- [performance considerations, e.g., "serverless = cold starts possible"]

Auth: [provider]
- [how to get userId, e.g., "ctx.auth.userId, never trust client"]
- [session handling specifics]

API: [framework]
- [middleware patterns]
- [validation approach]

Payments: [provider]
- [webhook behavior]
- [idempotency requirements]

Frontend: [framework + build tool]
- [common pitfalls, e.g., "stale closures in effects"]
- [state management specifics]

External Services:
- [service 1]: [failure modes]
- [service 2]: [failure modes]
```

---

## Prompt F: Incident Response Runbook Generator (NEW)

> **Token Budget:** ~2,000 tokens
> **Execution Time:** 2-3 minutes
> **Difficulty:** Advanced
> **Requires:** System architecture, known failure modes
> **Outputs:** Runbook for specific incident type

```
I need you to generate an incident response runbook for a specific failure mode.

══════════════════════════════════════════════════════════════
SYSTEM CONTEXT
══════════════════════════════════════════════════════════════
- Application: [describe your app]
- Architecture: [high-level architecture diagram]
- Critical dependencies: [list services that must be up]
- Monitoring tools: [Datadog, Sentry, PagerDuty, etc.]
- Communication channels: [Slack channels, email lists]

══════════════════════════════════════════════════════════════
INCIDENT TYPE
══════════════════════════════════════════════════════════════
[e.g., "Database connection pool exhaustion", "Payment webhook failure", "Authentication service down"]

══════════════════════════════════════════════════════════════
RUNBOOK STRUCTURE
══════════════════════════════════════════════════════════════

SECTION 1: IDENTIFICATION
How to recognize this incident:
- Alert name: [specific alert that fires]
- Symptoms: [what users see]
- Metrics to check: [specific dashboards/graphs]
- Logs to search: [specific log patterns]

SECTION 2: SEVERITY CLASSIFICATION
| Severity | Criteria | Response Time | Escalation |
|----------|----------|---------------|------------|
| SEV1 | [criteria] | 15 min | [who] |
| SEV2 | [criteria] | 1 hour | [who] |
| SEV3 | [criteria] | 4 hours | [who] |

SECTION 3: IMMEDIATE ACTIONS
Step-by-step first response:
1. [ ] [action 1]
2. [ ] [action 2]
3. [ ] [action 3]

Commands to run:
```bash
[diagnostic commands]
```

SECTION 4: DIAGNOSIS
Decision tree for root cause:
```
IF [symptom A] THEN → [possible cause 1] → check [specific thing]
IF [symptom B] THEN → [possible cause 2] → check [specific thing]
```

SECTION 5: REMEDIATION
For each possible cause, the fix:

CAUSE 1: [description]
- Fix: [exact steps]
- Commands: [exact commands]
- Verification: [how to confirm fix worked]

CAUSE 2: [description]
- Fix: [exact steps]
- Commands: [exact commands]
- Verification: [how to confirm fix worked]

SECTION 6: ROLLBACK
If remediation makes things worse:
- Rollback command: [exact command]
- Verification: [how to confirm rollback worked]

SECTION 7: COMMUNICATION
Templates for updates:

INTERNAL (Slack):
```
🚨 INCIDENT: [title]
Status: [Investigating/Identified/Monitoring/Resolved]
Impact: [user impact]
Current action: [what we're doing]
Next update: [time]
```

EXTERNAL (Status page):
```
[Service] is currently experiencing [issue].
Impact: [user-facing description].
We are investigating and will provide updates every [X] minutes.
```

SECTION 8: POST-INCIDENT
After resolution:
- [ ] Create post-mortem (use Prompt D)
- [ ] Update this runbook with lessons learned
- [ ] Add missing alerts/monitoring
- [ ] Schedule prevention work
```

---

## When to Use (Decision Matrix)

| Situation | Prompt | Why |
|-----------|--------|-----|
| Specific error message/stack trace | A: Debug Specific Error | Systematic root cause analysis |
| User bug report, can't reproduce | B: Reproduce Reported Bug | Structured reproduction plan |
| Proactive quality check | C: Full Codebase Bug Sweep | Comprehensive category-based sweep |
| After production incident | D: Root Cause Analysis | Complete post-mortem document |
| Before launch/major release | E: Production-Readiness Audit | Nuclear-grade failure mode analysis |
| Creating incident documentation | F: Incident Response Runbook | Repeatable response procedures |

---

## Quick Reference Card

```
ERROR → A: Debug Specific Error
REPORT → B: Reproduce Reported Bug
PREVENT → C: Full Codebase Bug Sweep
INCIDENT → D: Root Cause Analysis
LAUNCH → E: Production-Readiness Audit
RUNBOOK → F: Incident Response Runbook
```
