#!/usr/bin/env python3
"""E2E Auth flow tests for KeyHome Next.js frontend.

Complementary to ``scripts/frontend_panel_smoke.py`` (which covers landing
+ login). This script covers the auth sub-flows that the smoke does NOT:

* **Forgot password request** — fills the email, submits, asserts that the
  page reaches either a success state ("lien de réinitialisation envoyé")
  or a deterministic error (rate-limit / unknown email). The form is
  actually submitted because no real side-effect is required beyond sending
  an email to the configured ``MAIL_MAILER`` backend.

* **Register page render** — navigates to ``/register``, asserts the
  expected fields are visible (email, password, etc.), fills the form
  partially, but **does NOT submit**. Registering would create a real user
  + send an OTP, and Clerk-driven OTP verification needs a dedicated
  strategy (mail catcher / Clerk test mode) covered in a future iteration.

* **Login sanity check** — quickly re-runs the existing client + owner
  login flows from ``frontend_panel_smoke.py`` if their env vars are set,
  so a single command validates "the whole auth surface".

The same env vars and helpers as the smoke script are reused.

Run from the repo root with Next dev managed by ``with_server.py``::

    source scripts/.venv-smoke/bin/activate
    python3 .agents/skills/webapp-testing/scripts/with_server.py \\
        --server "cd keyhome-frontend-next && npm run dev" \\
        --port 3000 \\
        --timeout 120 \\
        -- env FRONTEND_SMOKE_BASE=http://127.0.0.1:3000 \\
            scripts/.venv-smoke/bin/python3 scripts/e2e_auth_flow.py

Or against an already-running server::

    source scripts/.venv-smoke/bin/activate
    FRONTEND_SMOKE_BASE=http://127.0.0.1:3000 \\
        scripts/.venv-smoke/bin/python3 scripts/e2e_auth_flow.py

Environment variables (all optional unless noted):

* ``FRONTEND_SMOKE_BASE`` — frontend origin, default ``http://localhost:3000``.
* ``FRONTEND_SMOKE_NETWORK_IDLE_MS`` — default ``120000``.
* ``FRONTEND_SMOKE_NETWORKIDLE_AFTER_LOAD_MS`` — default ``10000``.
* ``FRONTEND_SMOKE_AUTH_TIMEOUT_MS`` — default ``120000``.
* ``E2E_FORGOT_PASSWORD_EMAIL`` — email used in the forgot-password flow.
  Defaults to ``e2e-nonexistent@keyhome.test`` (no real user, expects
  generic success message because backends typically don't disclose
  whether the email exists).
* ``E2E_SKIP_REGISTER`` — set to ``1`` to skip the register page check.
* ``E2E_SKIP_FORGOT_PASSWORD`` — set to ``1`` to skip the forgot-password
  flow.
* ``E2E_SKIP_LOGIN`` — set to ``1`` to skip the login sanity check.
* ``SMOKE_CLIENT_EMAIL`` / ``SMOKE_CLIENT_PASSWORD`` — when both set, the
  client login flow is exercised.
* ``SMOKE_OWNER_EMAIL`` / ``SMOKE_OWNER_PASSWORD`` — when both set, the
  owner login flow is exercised.

Exit code:
* ``0`` if all enabled flows pass.
* ``1`` if any flow fails (with details on stderr).
"""
from __future__ import annotations

import os
import sys
from pathlib import Path

# Reuse the helpers + login flows already implemented in the smoke script
# (kept here to a single source of truth for selectors / retry logic).
REPO_ROOT = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(REPO_ROOT / "scripts"))

from playwright.sync_api import Error as PlaywrightError  # noqa: E402
from playwright.sync_api import TimeoutError as PlaywrightTimeoutError  # noqa: E402
from playwright.sync_api import expect  # noqa: E402
from playwright.sync_api import sync_playwright  # noqa: E402

import frontend_panel_smoke as smoke  # noqa: E402

BASE = smoke.BASE
ARTIFACTS = smoke.ARTIFACTS
NETWORK_IDLE_MS = smoke.NETWORK_IDLE_MS
AUTH_TIMEOUT_MS = smoke.AUTH_TIMEOUT_MS

FORGOT_PASSWORD_EMAIL = os.environ.get(
    "E2E_FORGOT_PASSWORD_EMAIL", "e2e-nonexistent@keyhome.test"
).strip()
SKIP_REGISTER = os.environ.get("E2E_SKIP_REGISTER") == "1"
SKIP_FORGOT_PASSWORD = os.environ.get("E2E_SKIP_FORGOT_PASSWORD") == "1"
SKIP_LOGIN = os.environ.get("E2E_SKIP_LOGIN") == "1"


# ---------------------------------------------------------------------------
# Forgot-password flow
# ---------------------------------------------------------------------------
def flow_forgot_password(page) -> None:
    """Fill the forgot-password form, submit, expect success OR a known error.

    The backend may return either:
    * Success message (200) — typical when email exists or backend uses
      ``always-success`` policy to avoid email enumeration.
    * 4xx error visible in ``#forgot-password-error`` — e.g. rate-limited,
      validation, malformed email.

    Either deterministic outcome is acceptable for this E2E (we're
    validating the page works, not the server policy).
    """
    masked = smoke.mask_email(FORGOT_PASSWORD_EMAIL)
    print(f"\n→ flow_forgot_password (email={masked})", file=sys.stderr)

    smoke._goto_with_retries(
        page,
        f"{BASE}/forgot-password",
        wait_until="domcontentloaded",
        timeout=NETWORK_IDLE_MS,
    )
    smoke.wait_load_then_networkidle(page, "/forgot-password")

    # Page heading
    page.get_by_text("Mot de passe oublié", exact=True).first.wait_for(
        state="visible", timeout=30_000
    )

    # Fill email + submit
    email_input = page.get_by_label("Adresse email", exact=True)
    if not email_input.count():
        email_input = page.get_by_role("textbox").first
    email_input.fill(FORGOT_PASSWORD_EMAIL)

    submit = page.get_by_role("button", name="Envoyer le lien", exact=False)
    if not submit.count():
        submit = page.get_by_role("button").filter(has_text="Envoyer").first
    expect(submit).to_be_enabled(timeout=AUTH_TIMEOUT_MS)
    submit.click()

    # Wait for either success Alert OR error Alert (#forgot-password-error)
    try:
        page.wait_for_function(
            """
            () => {
                const alerts = document.querySelectorAll('[role=alert], .MuiAlert-root');
                return Array.from(alerts).some(a => a.textContent && a.textContent.trim().length > 5);
            }
            """,
            timeout=30_000,
        )
    except PlaywrightTimeoutError as exc:
        raise RuntimeError(
            "forgot-password: no alert (success or error) appeared within 30s"
        ) from exc

    error_visible = page.locator("#forgot-password-error").is_visible()
    page.screenshot(
        path=str(ARTIFACTS / "e2e-forgot-password.png"), full_page=True
    )
    state = "error alert visible (deterministic)" if error_visible else "success state"
    print(
        f"OK: forgot-password reached {state} -> {ARTIFACTS / 'e2e-forgot-password.png'}",
        file=sys.stderr,
    )


# ---------------------------------------------------------------------------
# Register page render check (no submission)
# ---------------------------------------------------------------------------
def flow_register_page_renders(page) -> None:
    """Navigate to /register and assert the form is rendered + interactive.

    We intentionally do NOT submit: registering would create a real user
    and trigger Clerk OTP delivery. Submission is deferred to a dedicated
    flow once we choose an OTP strategy (Clerk test mode / mail catcher /
    manual pause).
    """
    print("\n→ flow_register_page_renders", file=sys.stderr)
    smoke._goto_with_retries(
        page,
        f"{BASE}/register",
        wait_until="domcontentloaded",
        timeout=NETWORK_IDLE_MS,
    )
    smoke.wait_load_then_networkidle(page, "/register")

    # Common register fields (French labels). We tolerate variants because
    # the register page is a stepper and may not show all fields at once.
    expected_any = [
        "Adresse email",
        "Email",
        "Mot de passe",
        "Nom",
        "Prénom",
        "Téléphone",
    ]
    found: list[str] = []
    for label in expected_any:
        loc = page.get_by_label(label, exact=True)
        if loc.count() and loc.first.is_visible():
            found.append(label)

    if not found:
        page.screenshot(
            path=str(ARTIFACTS / "e2e-register-page-missing-fields.png"),
            full_page=True,
        )
        raise RuntimeError(
            "register: no expected field labels found (page may have failed to render). "
            f"Screenshot: {ARTIFACTS / 'e2e-register-page-missing-fields.png'}"
        )

    # Try to fill the first available field as a smoke of interactivity.
    label = found[0]
    page.get_by_label(label, exact=True).first.fill("e2e-test@keyhome.test")

    # Submit button must exist (we don't click it though).
    submit_btn = page.get_by_role("button", name="Créer mon compte", exact=False)
    if not submit_btn.count():
        submit_btn = page.get_by_role("button", name="S'inscrire", exact=False)
    if not submit_btn.count():
        submit_btn = page.get_by_role("button", name="Continuer", exact=False)
    if not submit_btn.count():
        raise RuntimeError(
            "register: no recognized submit/continue button found (Créer mon compte / S'inscrire / Continuer)"
        )

    page.screenshot(
        path=str(ARTIFACTS / "e2e-register-page.png"), full_page=True
    )
    print(
        f"OK: register page renders ({len(found)} fields found, first filled, submit present) "
        f"-> {ARTIFACTS / 'e2e-register-page.png'}",
        file=sys.stderr,
    )


# ---------------------------------------------------------------------------
# Login sanity (delegated to the smoke helpers)
# ---------------------------------------------------------------------------
def flow_login_sanity(page) -> None:
    """Re-use the smoke helpers to run client + owner login when env vars are set."""
    client_email = os.environ.get("SMOKE_CLIENT_EMAIL", "").strip()
    client_password = os.environ.get("SMOKE_CLIENT_PASSWORD", "")
    owner_email = os.environ.get("SMOKE_OWNER_EMAIL", "").strip()
    owner_password = os.environ.get("SMOKE_OWNER_PASSWORD", "")

    if client_email and client_password:
        print("\n→ flow_login_sanity: client", file=sys.stderr)
        smoke.smoke_client_authenticated(
            page, email=client_email, password=client_password
        )
    else:
        print(
            "\n↷ flow_login_sanity: client skipped (SMOKE_CLIENT_EMAIL/PASSWORD not set)",
            file=sys.stderr,
        )

    if owner_email and owner_password:
        print("\n→ flow_login_sanity: owner", file=sys.stderr)
        smoke.smoke_owner_authenticated(
            page, email=owner_email, password=owner_password
        )
    else:
        print(
            "↷ flow_login_sanity: owner skipped (SMOKE_OWNER_EMAIL/PASSWORD not set)",
            file=sys.stderr,
        )


# ---------------------------------------------------------------------------
# Main
# ---------------------------------------------------------------------------
def main() -> int:
    ARTIFACTS.mkdir(parents=True, exist_ok=True)

    if smoke.is_smoke_port_free():
        print(
            f"ERROR: nothing is listening on {BASE} — start the Next dev server first "
            "(npm run dev in keyhome-frontend-next) or use with_server.py.",
            file=sys.stderr,
        )
        return 1

    failures: list[tuple[str, str]] = []
    flows: list[tuple[str, callable, bool]] = [
        ("forgot-password", flow_forgot_password, SKIP_FORGOT_PASSWORD),
        ("register-render", flow_register_page_renders, SKIP_REGISTER),
        ("login-sanity", flow_login_sanity, SKIP_LOGIN),
    ]

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        try:
            for name, fn, skip in flows:
                if skip:
                    print(f"↷ {name}: skipped via env", file=sys.stderr)
                    continue
                # Fresh context per flow to avoid stale cookies between
                # anonymous flows (forgot/register) and authenticated ones.
                ctx = browser.new_context()
                page = ctx.new_page()
                try:
                    fn(page)
                except (RuntimeError, AssertionError, PlaywrightError) as exc:
                    failures.append((name, str(exc)))
                    print(f"FAIL: {name}: {exc}", file=sys.stderr)
                finally:
                    ctx.close()
        finally:
            browser.close()

    print("", file=sys.stderr)
    if failures:
        print(f"❌ {len(failures)} flow(s) failed:", file=sys.stderr)
        for name, err in failures:
            print(f"  - {name}: {err}", file=sys.stderr)
        return 1

    print("✅ All enabled auth E2E flows passed.", file=sys.stderr)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
