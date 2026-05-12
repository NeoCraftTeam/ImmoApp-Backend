#!/usr/bin/env python3
"""Minimal Playwright smoke: customer landing + owner login (+ unauth dashboard redirect).

Optional authenticated flows (skipped unless **both** credentials env vars are set):

* ``SMOKE_OWNER_EMAIL`` / ``SMOKE_OWNER_PASSWORD`` — owner email/password login at
  ``/owner/login``, then assert dashboard; screenshot ``artifacts/smoke-owner-dashboard-auth.png``.
* ``SMOKE_CLIENT_EMAIL`` / ``SMOKE_CLIENT_PASSWORD`` — customer login at ``/login``, then assert
  ``/home`` (or shell); screenshot ``artifacts/smoke-client-home-auth.png``.

Emails are **never** logged in plain text (masked). Passwords are never printed.

Run with Next dev managed by with_server.py (from repo root):

  source scripts/.venv-smoke/bin/activate
  python3 .agents/skills/webapp-testing/scripts/with_server.py \\
    --server "cd keyhome-frontend-next && npm run dev" \\
    --port 3000 \\
    --timeout 120 \\
    -- env FRONTEND_SMOKE_BASE=http://127.0.0.1:3000 \\
      scripts/.venv-smoke/bin/python3 scripts/frontend_panel_smoke.py

Next.js 16 allows only one ``next dev`` per project directory. Stop any
orphan instance (or reuse it and run the Playwright script alone without
spawning a second server).

Manual server: in ``keyhome-frontend-next``, run ``npm run dev`` (port 3000), then:

  source scripts/.venv-smoke/bin/activate
  python3 scripts/frontend_panel_smoke.py

Prereqs (venv recommended on macOS PEP 668):

  python3 -m venv scripts/.venv-smoke
  source scripts/.venv-smoke/bin/activate
  pip install -r scripts/requirements-playwright-smoke.txt
  python -m playwright install chromium

Environment variables
---------------------
**Base / timing**

* ``FRONTEND_SMOKE_BASE`` — default ``http://localhost:3000``
* ``FRONTEND_SMOKE_NETWORK_IDLE_MS`` — default ``120000`` (passed to Playwright for load/networkidle attempts)
* ``FRONTEND_SMOKE_NETWORKIDLE_AFTER_LOAD_MS`` — default ``10000``; extra wait for networkidle after ``load`` (often never settles under ``next dev``)
* ``FRONTEND_SMOKE_AUTH_TIMEOUT_MS`` — default ``120000``; max wait for post-login navigation / dashboard shell

**Optional auth (each pair is all-or-nothing)**

* ``SMOKE_OWNER_EMAIL`` / ``SMOKE_OWNER_PASSWORD`` — run owner login smoke when both set
* ``SMOKE_CLIENT_EMAIL`` / ``SMOKE_CLIENT_PASSWORD`` — run customer login smoke when both set

**Other**

* ``NEXT_PUBLIC_API_URL`` — optional; not required for these public routes in typical dev

Caveats:
  * ``next dev`` keeps HMR connections open — ``networkidle`` frequently times out. This script still *calls*
    ``wait_for_load_state('networkidle')`` (per automation guideline) with a short timeout after
    ``domcontentloaded``; continues if the network never idles (typical with HMR).
  * Prefer ``next build && next start`` (port 3000) if you need a true network-idle gate.
  * Clerk / Turnstile / API errors may still allow these shells; assertions use on-page French copy and password input.
  * When both owner and client auth runs are enabled, the client flow uses a **fresh browser context** so the
    owner session does not block the customer login page.
"""
from __future__ import annotations

import os
import socket
import sys
import urllib.parse
from pathlib import Path

from playwright.sync_api import Error as PlaywrightError
from playwright.sync_api import TimeoutError as PlaywrightTimeoutError
from playwright.sync_api import expect
from playwright.sync_api import sync_playwright


def _goto_with_retries(page, url: str, *, wait_until: str, timeout: int, attempts: int = 8) -> None:
    delay_ms = 1500
    for attempt in range(1, attempts + 1):
        try:
            page.goto(url, wait_until=wait_until, timeout=timeout)
            return
        except PlaywrightError:
            if attempt == attempts:
                raise
            page.wait_for_timeout(delay_ms)


REPO_ROOT = Path(__file__).resolve().parent.parent
ARTIFACTS = REPO_ROOT / "artifacts"
BASE = os.environ.get("FRONTEND_SMOKE_BASE", "http://localhost:3000").rstrip("/")
NETWORK_IDLE_MS = int(os.environ.get("FRONTEND_SMOKE_NETWORK_IDLE_MS", "120000"))
NETWORKIDLE_AFTER_LOAD_MS = int(
    os.environ.get("FRONTEND_SMOKE_NETWORKIDLE_AFTER_LOAD_MS", "10000")
)
AUTH_TIMEOUT_MS = int(os.environ.get("FRONTEND_SMOKE_AUTH_TIMEOUT_MS", "120000"))


def wait_load_then_networkidle(page, label: str) -> None:
    """Wait for best-effort networkidle after navigation reached ``load``."""
    try:
        page.wait_for_load_state("networkidle", timeout=NETWORKIDLE_AFTER_LOAD_MS)
    except PlaywrightTimeoutError:
        print(
            f"NOTE: networkidle not reached for {label} within "
            f"{NETWORKIDLE_AFTER_LOAD_MS}ms (common with Next.js dev). Continuing.",
            file=sys.stderr,
        )


def mask_email(email: str) -> str:
    """Redact PII for logs (never print full address)."""
    email = email.strip()
    if "@" not in email:
        return "***"
    local, _, domain = email.partition("@")
    if not local:
        return f"***@{_mask_domain(domain)}"
    redacted_local = (local[0] + "***") if len(local) > 1 else "***"
    return f"{redacted_local}@{_mask_domain(domain)}"


def _mask_domain(domain: str) -> str:
    domain = domain.strip().lower()
    if not domain:
        return "***"
    parts = domain.split(".")
    if len(parts) >= 2:
        tld = parts[-1]
        head = parts[0]
        return (head[0] + "***" if head else "***") + "." + tld
    return domain[0] + "***" if len(domain) > 1 else "***"


def _smoke_port_from_base() -> tuple[str, int] | None:
    try:
        parsed = urllib.parse.urlsplit(BASE if "://" in BASE else f"//{BASE}", scheme="http")
    except ValueError:
        return None
    host = parsed.hostname
    port = parsed.port
    if not host:
        return None
    if port is None:
        port = 443 if parsed.scheme == "https" else 80
    return host, port


def is_smoke_port_free() -> bool:
    """Return True if nothing accepts TCP on the FRONTEND_SMOKE_BASE host:port."""
    info = _smoke_port_from_base()
    if info is None:
        return False
    host, port = info
    try:
        with socket.create_connection((host, port), timeout=1.0):
            return False
    except OSError:
        return True


def _login_error_visible(page) -> bool:
    return (
        page.locator("#owner-login-error").is_visible()
        or page.locator("#login-error").is_visible()
    )


def _fill_and_submit_login(page, *, email: str, password: str, heading_text: str) -> None:
    page.get_by_text(heading_text, exact=True).first.wait_for(state="visible", timeout=60_000)
    page.get_by_label("Adresse email", exact=True).fill(email)
    page.get_by_label("Mot de passe", exact=True).fill(password)
    submit = page.get_by_role("button", name="Se connecter", exact=True)
    try:
        expect(submit).to_be_enabled(timeout=AUTH_TIMEOUT_MS)
    except AssertionError as exc:
        raise RuntimeError(
            "Login submit button stayed disabled — Turnstile may require a token, or config not resolved."
        ) from exc
    submit.click()


def smoke_owner_authenticated(page, *, email: str, password: str) -> None:
    masked = mask_email(email)
    print(f"Running owner authenticated smoke for {masked}", file=sys.stderr)
    _goto_with_retries(
        page, f"{BASE}/owner/login", wait_until="domcontentloaded", timeout=NETWORK_IDLE_MS
    )
    wait_load_then_networkidle(page, "owner /owner/login (auth)")
    _fill_and_submit_login(page, email=email, password=password, heading_text="Connexion propriétaire")
    try:
        page.wait_for_function(
            "() => !window.location.pathname.includes('/owner/login')",
            timeout=AUTH_TIMEOUT_MS,
        )
    except PlaywrightTimeoutError as exc:
        if _login_error_visible(page):
            raise RuntimeError(
                f"Owner login failed for {masked} (error alert visible; still on login)."
            ) from exc
        raise RuntimeError(
            f"Owner login: timed out leaving /owner/login (url={page.url!r})."
        ) from exc
    pending = page.url
    if "verify-otp" in pending:
        raise RuntimeError(
            f"Owner login for {masked} redirected to OTP verification; complete verification manually."
        )
    if "/owner/login" in pending:
        raise RuntimeError(f"Owner login: still on login page (url={pending!r}).")
    _goto_with_retries(page, f"{BASE}/owner/dashboard", wait_until="domcontentloaded", timeout=NETWORK_IDLE_MS)
    wait_load_then_networkidle(page, "owner /owner/dashboard (authenticated)")
    try:
        page.get_by_text("Suivez vos annonces", exact=True).first.wait_for(state="visible", timeout=60_000)
    except PlaywrightTimeoutError as exc:
        raise RuntimeError(
            "Owner dashboard shell not detected (missing expected French copy)."
        ) from exc
    page.screenshot(path=str(ARTIFACTS / "smoke-owner-dashboard-auth.png"), full_page=True)
    print(f"OK: owner auth screenshot -> {ARTIFACTS / 'smoke-owner-dashboard-auth.png'}", file=sys.stderr)


def smoke_client_authenticated(page, *, email: str, password: str) -> None:
    masked = mask_email(email)
    print(f"Running client authenticated smoke for {masked}", file=sys.stderr)
    _goto_with_retries(page, f"{BASE}/login", wait_until="domcontentloaded", timeout=NETWORK_IDLE_MS)
    wait_load_then_networkidle(page, "client /login (auth)")
    _fill_and_submit_login(page, email=email, password=password, heading_text="Bienvenue")
    try:
        page.wait_for_function(
            "() => !window.location.pathname.endsWith('/login')",
            timeout=AUTH_TIMEOUT_MS,
        )
    except PlaywrightTimeoutError as exc:
        if _login_error_visible(page):
            raise RuntimeError(
                f"Client login failed for {masked} (error alert visible; still on login)."
            ) from exc
        raise RuntimeError(
            f"Client login: timed out leaving /login (url={page.url!r})."
        ) from exc
    pending = page.url
    if "/verify-email" in pending or "verify-otp" in pending:
        raise RuntimeError(
            f"Client login for {masked} redirected to email/OTP verification; complete verification manually."
        )
    if pending.rstrip("/").endswith("/login"):
        raise RuntimeError(f"Client login: still on login page (url={pending!r}).")
    _goto_with_retries(page, f"{BASE}/home", wait_until="domcontentloaded", timeout=NETWORK_IDLE_MS)
    wait_load_then_networkidle(page, "client /home (authenticated)")
    page.screenshot(path=str(ARTIFACTS / "smoke-client-home-auth.png"), full_page=True)
    print(f"OK: client auth screenshot -> {ARTIFACTS / 'smoke-client-home-auth.png'}", file=sys.stderr)


def main() -> int:
    ARTIFACTS.mkdir(parents=True, exist_ok=True)

    owner_email = os.environ.get("SMOKE_OWNER_EMAIL", "").strip()
    owner_password = os.environ.get("SMOKE_OWNER_PASSWORD", "")
    client_email = os.environ.get("SMOKE_CLIENT_EMAIL", "").strip()
    client_password = os.environ.get("SMOKE_CLIENT_PASSWORD", "")

    run_owner_auth = bool(owner_email and owner_password)
    run_client_auth = bool(client_email and client_password)

    if not run_owner_auth:
        print(
            "SKIP: owner authenticated smoke (SMOKE_OWNER_EMAIL and SMOKE_OWNER_PASSWORD not both set)",
            file=sys.stderr,
        )
    if not run_client_auth:
        print(
            "SKIP: client authenticated smoke (SMOKE_CLIENT_EMAIL and SMOKE_CLIENT_PASSWORD not both set)",
            file=sys.stderr,
        )

    with sync_playwright() as p:
        browser = p.chromium.launch(headless=True)
        try:
            page = browser.new_page(viewport={"width": 1280, "height": 720})

            # Customer: public landing (stable without API for basic shell).
            _goto_with_retries(page, f"{BASE}/", wait_until="domcontentloaded", timeout=NETWORK_IDLE_MS)
            wait_load_then_networkidle(page, "customer /")
            page.screenshot(
                path=str(ARTIFACTS / "smoke-customer-landing.png"),
                full_page=True,
            )
            html = page.content()
            if "KeyHome" not in html and "keyhome" not in html.lower():
                raise AssertionError("customer landing: expected KeyHome branding in HTML")

            # Owner: public login (no auth).
            _goto_with_retries(page, f"{BASE}/owner/login", wait_until="domcontentloaded", timeout=NETWORK_IDLE_MS)
            wait_load_then_networkidle(page, "owner /owner/login")
            page.screenshot(
                path=str(ARTIFACTS / "smoke-owner-login.png"),
                full_page=True,
            )
            heading = page.get_by_text("Connexion propriétaire", exact=True)
            heading.first.wait_for(state="visible", timeout=60_000)
            if page.locator('input[type="password"]').count() < 1:
                raise AssertionError("owner login: expected a password field")

            # Owner: dashboard without session should redirect to login (client-side).
            _goto_with_retries(page, f"{BASE}/owner/dashboard", wait_until="domcontentloaded", timeout=NETWORK_IDLE_MS)
            wait_load_then_networkidle(page, "owner /owner/dashboard unauthenticated")
            page.screenshot(
                path=str(ARTIFACTS / "smoke-owner-dashboard-unauth.png"),
                full_page=True,
            )
            final_url = page.url
            if "/owner/login" not in final_url:
                raise AssertionError(
                    "unauthenticated /owner/dashboard: expected URL to contain "
                    f"/owner/login, got {final_url!r}"
                )

            if run_owner_auth:
                try:
                    smoke_owner_authenticated(page, email=owner_email, password=owner_password)
                except Exception as exc:
                    print(f"ERROR: owner authenticated smoke failed: {exc}", file=sys.stderr)
                    return 1

            if run_client_auth:
                client_ctx = browser.new_context(viewport={"width": 1280, "height": 720})
                client_page = client_ctx.new_page()
                try:
                    smoke_client_authenticated(
                        client_page, email=client_email, password=client_password
                    )
                except Exception as exc:
                    print(f"ERROR: client authenticated smoke failed: {exc}", file=sys.stderr)
                    return 1
                finally:
                    client_ctx.close()

            print("OK: screenshots ->", ARTIFACTS.resolve(), file=sys.stderr)
            return 0
        finally:
            browser.close()


if __name__ == "__main__":
    raise SystemExit(main())
