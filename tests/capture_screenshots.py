#!/usr/bin/env python3
"""Captures screenshots of every main function by driving the real UI.
Usage:  pip install playwright && playwright install chromium
        python3 tests/capture_screenshots.py https://your-site-url owner 'Owner@123' docs/screenshots
Works against the local app or the live website."""
import sys, os, datetime, tempfile
from playwright.sync_api import sync_playwright
BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8080").rstrip("/")
USER = sys.argv[2] if len(sys.argv) > 2 else "owner"
PASS = sys.argv[3] if len(sys.argv) > 3 else "Owner@123"
OUT = sys.argv[4] if len(sys.argv) > 4 else "docs/screenshots"
os.makedirs(OUT, exist_ok=True)
future = (datetime.date.today() + datetime.timedelta(days=90)).isoformat()
n = 0
def shot(page, name, full=True):
    global n; n += 1
    page.wait_for_load_state("networkidle")
    page.screenshot(path=f"{OUT}/{n:02d}_{name}.png", full_page=full); print("saved", n, name)

with sync_playwright() as p:
    b = p.chromium.launch(); ctx = b.new_context(viewport={"width": 1280, "height": 800}); pg = ctx.new_page()
    pg.on("dialog", lambda d: d.accept())
    pg.goto(BASE + "/index.php"); shot(pg, "login")
    pg.fill("#username", USER); pg.fill("#password", "wrong-password"); pg.click("main form button[type=submit]"); shot(pg, "login_error")
    pg.fill("#username", USER); pg.fill("#password", PASS); pg.click("main form button[type=submit]"); shot(pg, "dashboard")
    pg.goto(BASE + "/products.php"); shot(pg, "inventory_list")
    pg.goto(BASE + "/products.php?status=LOW"); shot(pg, "inventory_filter_low")
    pg.goto(BASE + "/product_form.php"); pg.fill("#product_name", "Duplicate SKU Test"); pg.select_option("#category_id", index=1); pg.fill("#cost_price", "-5"); pg.fill("#reorder_level", "abc"); pg.fill("#sku", "SNK-001"); pg.click("main form button[type=submit]"); shot(pg, "add_product_validation")
    pg.goto(BASE + "/product_form.php"); pg.fill("#product_name", "Sample Product"); pg.fill("#sku", "DEMO-001"); pg.select_option("#category_id", index=1)
    pg.fill("#cost_price", "12.50"); pg.fill("#selling_price", "18"); pg.fill("#reorder_level", "5"); pg.check("input[name=has_expiry]"); pg.click("main form button[type=submit]"); shot(pg, "product_detail")
    pg.goto(BASE + "/stock_move.php"); pg.select_option("#product", label="Sample Product (DEMO-001)"); pg.select_option("#type", "in"); pg.fill("#qty", "20"); pg.fill("#expiry", future); pg.fill("#reason", "Delivery from supplier"); shot(pg, "stock_in_form")
    pg.click("main form button[type=submit]"); shot(pg, "stock_in_saved")
    pg.select_option("#product", label="Sample Product (DEMO-001)"); pg.select_option("#type", "out"); pg.fill("#qty", "999"); pg.click("main form button[type=submit]"); shot(pg, "stock_out_blocked")
    pg.goto(BASE + "/movements.php"); shot(pg, "movement_log")
    pg.goto(BASE + "/suppliers.php"); shot(pg, "suppliers")
    pg.goto(BASE + "/purchase_orders.php"); shot(pg, "reorder_suggestions")
    pg.click("text=Create purchase order(s)"); pg.wait_for_load_state("networkidle")
    if "po_view" not in pg.url:
        pg.goto(BASE + "/purchase_orders.php?tab=history"); shot(pg, "po_history"); pg.click("text=Open >> nth=0")
    shot(pg, "po_draft")
    pg.click("text=Mark as Ordered"); pg.wait_for_load_state("networkidle"); shot(pg, "po_ordered_receive")
    for d in pg.query_selector_all("input[type=date]"): d.fill(future)
    pg.click("text=Receive order and update stock"); shot(pg, "po_received")
    pg.goto(BASE + "/reports.php?type=lowstock"); shot(pg, "report_lowstock")
    pg.goto(BASE + "/reports.php?type=expiry"); shot(pg, "report_expiry")
    pg.goto(BASE + "/reports.php?type=wastage"); shot(pg, "report_wastage")
    pg.goto(BASE + "/notes.php"); pg.fill("#note_text", "Delivery arrived at 3 PM. Cola 1.5L restocked, please check the expiry dates."); pg.click("main form button[type=submit]"); shot(pg, "shift_notes")
    bad = os.path.join(tempfile.gettempdir(), "bad_sales.csv"); open(bad, "w").write("sku,quantity,date\nNOPE-99,2,\nBEV-001,abc,\n")
    pg.goto(BASE + "/import_sales.php"); pg.set_input_files("#csv", bad); pg.click("main form button[type=submit]"); shot(pg, "import_sales_errors")
    pg.goto(BASE + "/users.php"); shot(pg, "users_owner")
    pg.goto(BASE + "/categories.php"); shot(pg, "categories")
    m = b.new_context(viewport={"width": 390, "height": 800}, device_scale_factor=2); mp = m.new_page()
    mp.goto(BASE + "/index.php"); mp.fill("#username", USER); mp.fill("#password", PASS); mp.click("main form button[type=submit]"); shot(mp, "mobile_dashboard")
    b.close()
