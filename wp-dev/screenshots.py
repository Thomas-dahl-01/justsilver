"""Take README screenshots from the local test WordPress (http://localhost:8080).

    pip install playwright && python -m playwright install chromium
    python wp-dev/screenshots.py

Writes PNG files to docs/screenshots/. Uses reduced motion so animations are in their final state.
"""
import pathlib
import sys

from playwright.sync_api import sync_playwright

BASE = "http://localhost:8080"
OUT = pathlib.Path(__file__).resolve().parent.parent / "docs" / "screenshots"
OUT.mkdir(parents=True, exist_ok=True)
errors = []


def shot(page, selector, name, pad=0):
    el = page.locator(selector).first
    el.scroll_into_view_if_needed()
    page.wait_for_timeout(300)
    el.screenshot(path=str(OUT / f"{name}.png"))
    print("saved", name)


def shot_top(page, selector, name, height):
    """Screenshot the top part of a (tall) element."""
    el = page.locator(selector).first
    el.evaluate("e => e.scrollIntoView({block: 'start'})")
    page.wait_for_timeout(300)
    b = el.bounding_box()
    page.screenshot(path=str(OUT / f"{name}.png"), clip={"x": b["x"], "y": b["y"], "width": b["width"], "height": min(height, b["height"])})
    print("saved", name)


with sync_playwright() as p:
    browser = p.chromium.launch()
    ctx = browser.new_context(viewport={"width": 1280, "height": 900}, device_scale_factor=1.5, reduced_motion="reduce", locale="da-DK")
    page = ctx.new_page()
    page.on("console", lambda m: errors.append(m.text) if m.type == "error" else None)
    page.on("pageerror", lambda e: errors.append(str(e)))

    page.goto(BASE + "/soelv-forside/", wait_until="networkidle")
    shot(page, ".ns-hero", "hero", 0)
    shot(page, ".ns-cats", "categories", 4)
    plot = page.locator(".ns-chart__plot").first
    plot.scroll_into_view_if_needed()
    b = plot.bounding_box()
    page.mouse.move(b["x"] + b["width"] * 0.72, b["y"] + b["height"] / 2)
    shot(page, ".ns-chart", "price-chart", 4)
    page.fill("#ns_s_vaegt", "250")
    page.select_option("#ns_s_finhed", "0.925")
    page.wait_for_timeout(300)
    shot(page, ".ns-duo", "sell-and-alert", 4)
    shot(page, ".ns-stores", "stores", 4)

    page.goto(BASE + "/product/valcambi-soelvbarre-100-g/", wait_until="networkidle")
    shot_top(page, "div.product", "product", 600)

    page.goto(BASE + "/product-category/soelv/soelvmoenter/", wait_until="networkidle")
    shot_top(page, "ul.products", "category", 520)

    page.goto(BASE + "/book-tid/?ns_vare=18", wait_until="networkidle")
    page.locator(".ns-choice").first.click()
    page.wait_for_load_state("networkidle")
    page.locator(".ns-day").nth(1).click()
    page.wait_for_load_state("networkidle")
    page.locator(".ns-slot").nth(2).click()
    page.wait_for_load_state("networkidle")
    shot_top(page, ".ns-booking", "booking", 820)

    browser.close()

if errors:
    print("Console errors:", *errors, sep="\n  ")
    sys.exit(1)
print("No console errors")
