"""Screenshots of the JustSilver admin (dashboard widget, settings, booking calendar, login page).

    python wp-dev/admin-screenshots.py

Logs in to the local test WordPress with the local-only test user and writes PNGs to docs/screenshots/.
Also fails on JavaScript errors or PHP warnings on those screens.
"""
import pathlib
import re
import sys

from playwright.sync_api import sync_playwright

BASE = "http://localhost:8080"
OUT = pathlib.Path(__file__).resolve().parent.parent / "docs" / "screenshots"
OUT.mkdir(parents=True, exist_ok=True)
problems = []


def php_warnings(page, where):
    html = page.content()
    found = re.findall(r"<b>(Warning|Notice|Deprecated|Fatal error)</b>:[^<]{0,160}", html)
    if "_load_textdomain_just_in_time" in html:
        found.append("textdomain notice")
    for f in found:
        problems.append(f"{where}: {f}")


with sync_playwright() as p:
    browser = p.chromium.launch()
    ctx = browser.new_context(viewport={"width": 1400, "height": 950}, device_scale_factor=1.25, reduced_motion="reduce", locale="da-DK")
    page = ctx.new_page()
    page.on("pageerror", lambda e: problems.append(f"JS: {e}"))

    page.goto(BASE + "/wp-login.php", wait_until="networkidle")
    php_warnings(page, "login")
    page.screenshot(path=str(OUT / "admin-login.png"))
    print("saved admin-login")
    page.fill("#user_login", "admin")
    page.fill("#user_pass", "admin")
    page.click("#wp-submit")
    page.wait_for_url("**/wp-admin/**")

    page.goto(BASE + "/wp-admin/", wait_until="networkidle")
    php_warnings(page, "dashboard")
    page.evaluate("document.getElementById('welcome-panel') && document.getElementById('welcome-panel').classList.add('hidden')")
    page.locator("#ns_dashboard").screenshot(path=str(OUT / "admin-dashboard.png"))
    print("saved admin-dashboard")

    page.goto(BASE + "/wp-admin/admin.php?page=justsilver", wait_until="networkidle")
    php_warnings(page, "settings")
    page.locator(".ns-settings").screenshot(path=str(OUT / "admin-settings.png"))
    print("saved admin-settings")

    page.goto(BASE + "/wp-admin/edit.php?post_type=ns_booking&page=ns-calendar", wait_until="networkidle")
    php_warnings(page, "calendar")
    page.locator(".ns-cal").screenshot(path=str(OUT / "admin-calendar.png"))
    print("saved admin-calendar")

    browser.close()

print("Problems:", *problems, sep="\n  ") if problems else print("No PHP warnings or JS errors")
sys.exit(1 if problems else 0)
