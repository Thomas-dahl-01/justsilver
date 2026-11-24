"""Browser test of the interactive effects on the local test WordPress (http://localhost:8080).

    python wp-dev/effects-test.py

Checks each effect actually runs with motion on, that nothing runs with reduced motion,
and that there are no JavaScript errors. Exit code 1 on any failure.
"""
import subprocess
import sys

from playwright.sync_api import sync_playwright

BASE = "http://localhost:8080"
fails = []


def check(cond, label):
    print(("  ✓ " if cond else "  ✗ ") + label)
    if not cond:
        fails.append(label)


def wp(*args):
    subprocess.run(["docker", "compose", "run", "--rm", "-T", "cli", *args], cwd=__file__.rsplit("\\", 1)[0].rsplit("/", 1)[0],
                   capture_output=True, env={**__import__("os").environ, "MSYS_NO_PATHCONV": "1"})


with sync_playwright() as p:
    browser = p.chromium.launch()
    errors = []

    # ---------------- Motion on ----------------
    ctx = browser.new_context(viewport={"width": 1280, "height": 900})
    page = ctx.new_page()
    page.on("pageerror", lambda e: errors.append(str(e)))
    page.on("console", lambda m: errors.append(m.text) if m.type == "error" else None)
    page.goto(BASE + "/soelv-forside/", wait_until="networkidle")
    page.wait_for_timeout(600)

    print("Hero")
    check(page.locator(".ns-hero canvas.ns-dust").count() == 1, "silver dust canvas is added")
    px1 = page.evaluate("(() => { const c = document.querySelector('.ns-dust'); return c.toDataURL().length; })()")
    page.wait_for_timeout(400)
    px2 = page.evaluate("(() => { const c = document.querySelector('.ns-dust'); return c.toDataURL().length; })()")
    check(px1 != px2, "dust particles are moving")
    check(page.locator(".ns-hero h1 .ns-word").count() >= 4, "headline split into animated words")
    hero = page.locator(".ns-hero").bounding_box()
    page.mouse.move(hero["x"] + hero["width"] * 0.9, hero["y"] + hero["height"] * 0.2)
    page.wait_for_timeout(300)
    t = page.evaluate("document.querySelector('.ns-coin').style.translate")
    check(t not in ("", "0 0", "0px 0px"), f"coin follows the mouse (translate {t})")
    check("%" in page.evaluate("getComputedStyle(document.querySelector('.ns-hero')).getPropertyValue('--mx')"), "spotlight follows the mouse")

    print("Live price")
    check(page.locator(".ns-hero__price [data-ns-live] .ns-odo__d").count() >= 3, "price rendered as rolling digits")
    wp("justsilver", "price", "10750")
    page.evaluate("document.dispatchEvent(new Event('visibilitychange'))")
    page.wait_for_timeout(1500)
    label = page.evaluate("document.querySelector('.ns-hero__price [data-ns-live]').getAttribute('aria-label')")
    flashed = page.evaluate("document.querySelector('.ns-hero__price [data-ns-live]').className")
    check(label == "10,75", f"price updates live without reload ({label})")
    check("ns-flash--up" in flashed, "price flashes green when it rises")
    wp("justsilver", "price", "10500")

    print("Cards & buttons")
    cat = page.locator(".ns-cat").first
    cat.scroll_into_view_if_needed()
    page.wait_for_timeout(2500)
    b = cat.bounding_box()
    page.mouse.move(b["x"] + b["width"] * 0.8, b["y"] + b["height"] * 0.2, steps=5)
    page.wait_for_timeout(250)
    check("rotateY" in page.evaluate("document.querySelector('.ns-cat').style.transform"), "category card tilts in 3D")
    check(page.evaluate("getComputedStyle(document.querySelector('.ns-cat__icon svg *')).strokeDashoffset") in ("0", "0px"), "category icon has drawn itself")
    btn = page.locator(".ns-sell .ns-btn").first
    btn.scroll_into_view_if_needed()
    bb = btn.bounding_box()
    page.mouse.move(bb["x"] + 10, bb["y"] + 10)
    page.mouse.move(bb["x"] + bb["width"] - 6, bb["y"] + 4, steps=5)
    page.wait_for_timeout(300)
    tr = page.evaluate("document.querySelector('.ns-sell .ns-btn').style.translate")
    check(tr not in ("", "0px 0px") and all(abs(float(v.replace('px', ''))) <= 8 for v in tr.split()), f"button is magnetic, but only a little ({tr})")
    rng = page.locator(".ns-chart__ranges button").first
    rng.scroll_into_view_if_needed()
    rb = rng.bounding_box()
    page.mouse.move(rb["x"] + 10, rb["y"] + 10)
    page.mouse.down()
    check(page.locator(".ns-chart__ranges .ns-ripple").count() == 1, "click ripple appears")
    page.mouse.up()
    check(page.evaluate("getComputedStyle(document.querySelector('.ns-card'), '::before').animationName") == "ns-border-spin", "rotating border on cards")
    page.mouse.wheel(0, 1500)
    page.wait_for_timeout(300)
    check(page.evaluate("document.querySelector('.ns-progress-bar').style.transform") not in ("", "scaleX(0.0000)"), "scroll progress bar moves")

    print("Add to cart")
    page.goto(BASE + "/product-category/soelv/soelvbarrer/", wait_until="networkidle")
    add = page.locator("a.add_to_cart_button.ajax_add_to_cart").first
    add.scroll_into_view_if_needed()
    add.click()
    page.wait_for_selector(".ns-fly-coin", timeout=8000)
    check(True, "silver coin flies towards the basket")
    page.wait_for_timeout(1100)
    check(page.locator(".site-header-cart .ns-bump, .site-header-cart.ns-bump").count() >= 1, "basket bumps when the coin lands")
    ctx.close()

    # ---------------- Reduced motion ----------------
    print("Reduced motion")
    ctx = browser.new_context(viewport={"width": 1280, "height": 900}, reduced_motion="reduce")
    page = ctx.new_page()
    page.on("pageerror", lambda e: errors.append(str(e)))
    page.goto(BASE + "/soelv-forside/", wait_until="networkidle")
    check(page.locator(".ns-dust").count() == 0, "no dust canvas")
    check(page.locator(".ns-word").count() == 0, "headline not split")
    check(page.locator(".ns-progress-bar").count() == 0, "no progress bar")
    check(page.locator("[data-ns-live] .ns-odo__d").count() >= 3, "live price still works")
    ctx.close()
    browser.close()

print("JavaScript errors:", errors or "none")
if errors:
    fails.append("javascript errors")
print(f"\n{len(fails)} failed" if fails else "\nAll effect checks passed")
sys.exit(1 if fails else 0)
