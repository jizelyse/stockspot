#!/usr/bin/env python3
"""StockSpot automated functional tests (black-box, over HTTP).
Usage: python3 tests/run_tests.py [base_url]   -- run against a freshly seeded database.
Needs the `mysql` client for a few database assertions (user root, database stockspot)."""
import sys, re, json, urllib.request, urllib.parse, http.cookiejar, uuid, datetime, subprocess
BASE = (sys.argv[1] if len(sys.argv) > 1 else "http://127.0.0.1:8080").rstrip("/")
results = []

class Client:
    def __init__(self):
        self.jar = http.cookiejar.CookieJar()
        self.op = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(self.jar))
    def req(self, path, data=None, files=None, raw=None):
        url = BASE + "/" + path.lstrip("/")
        body = None; headers = {}
        if raw is not None: body = raw
        elif files:
            b = uuid.uuid4().hex; parts = []
            for k, v in (data or {}).items(): parts.append(f'--{b}\r\nContent-Disposition: form-data; name="{k}"\r\n\r\n{v}\r\n'.encode())
            for k, (fn, content) in files.items(): parts.append(f'--{b}\r\nContent-Disposition: form-data; name="{k}"; filename="{fn}"\r\nContent-Type: text/csv\r\n\r\n'.encode() + content.encode() + b"\r\n")
            parts.append(f"--{b}--\r\n".encode()); body = b"".join(parts); headers["Content-Type"] = f"multipart/form-data; boundary={b}"
        elif data is not None: body = urllib.parse.urlencode(data).encode()
        try:
            r = self.op.open(urllib.request.Request(url, data=body, headers=headers)); return r.status, r.read().decode("utf-8", "replace"), r.geturl()
        except urllib.error.HTTPError as e: return e.code, e.read().decode("utf-8", "replace"), url
    def token(self, path):
        _, html, _ = self.req(path); m = re.search(r'name="csrf" value="([a-f0-9]+)"', html); return m.group(1) if m else ""
    def post(self, path, data, token_page=None, files=None):
        d = dict(data); d["csrf"] = self.token(token_page or path); return self.req(path, d, files)

def check(tid, area, desc, cond, detail=""):
    results.append({"id": tid, "area": area, "test": desc, "result": "PASS" if cond else "FAIL", "detail": detail})
    print(("PASS" if cond else "FAIL"), tid, desc, ("-> " + detail) if (detail and not cond) else "")

import os, shlex
def sql(q): return subprocess.run(shlex.split(os.environ.get("STOCKSPOT_MYSQL", "mysql -uroot")) + ["-N", "stockspot", "-e", q], capture_output=True, text=True).stdout.strip()

o = Client(); s = Client(); anon = Client()
# ---- Authentication & access control
st, html, url = anon.req("dashboard.php"); check("T01", "Access control", "Visiting a page without logging in redirects to login", "Staff Login" in html)
st, html, url = anon.post("index.php", {"username": "owner", "password": "wrong"}); check("T02", "Authentication", "Wrong password is rejected with an error message", "Incorrect username or password" in html)
st, html, url = anon.post("index.php", {"username": "", "password": ""}); check("T03", "Authentication", "Empty login fields are rejected", "Please enter your username" in html)
st, html, url = o.post("index.php", {"username": "owner", "password": "Owner@123"}); check("T04", "Authentication", "Owner can log in and reaches the dashboard", "Low-stock alerts" in html)
st, html, url = s.post("index.php", {"username": "staff", "password": "Staff@123"}); check("T05", "Authentication", "Staff can log in", "Low-stock alerts" in html)
st, html, _ = s.req("users.php"); check("T06", "Access control", "Staff cannot open the Users page (403)", st == 403)
st, html, _ = o.req("users.php"); check("T07", "Access control", "Owner can open the Users page", st == 200 and "Add user" in html)
st, html, _ = o.req("dashboard.php"); check("T08", "Dashboard", "Dashboard lists low-stock items (Cola 1.5L)", "Cola 1.5L" in html)
check("T09", "Dashboard", "Dashboard lists expiring item (Corned Beef 150g)", "Corned Beef 150g" in html)
st, html, _ = anon.req("products.php"); check("T10", "Access control", "Inventory page requires login", "Staff Login" in html)
anon2 = Client(); anon2.req("index.php")
st, html, _ = anon2.req("index.php", {"username": "owner", "password": "Owner@123"}); check("T11", "Security", "POST without CSRF token is rejected (HTTP 419)", st == 419)
# ---- Inventory
st, html, _ = o.req("products.php?q=cola"); check("T12", "Inventory", "Search finds products by name", "Cola 1.5L" in html and "Potato Chips" not in html)
st, html, _ = o.req("products.php?status=LOW"); check("T13", "Inventory", "Status filter shows only LOW products", "Cola 1.5L" in html and "Bottled Water" not in html)
st, html, _ = o.req("products.php?cat=2"); check("T14", "Inventory", "Category filter works", "Bottled Water" in html and "Potato Chips" not in html)
good = {"product_name": "Test Product Alpha", "sku": "tst-001", "category_id": "1", "supplier_id": "1", "unit": "pc", "cost_price": "10.50", "selling_price": "15", "reorder_level": "5", "has_expiry": "1"}
st, html, _ = o.post("product_form.php", {**good, "product_name": ""}); check("T15", "Validation", "Product with empty name is rejected", "Product name is required" in html)
st, html, _ = o.post("product_form.php", {**good, "cost_price": "-5"}); check("T16", "Validation", "Negative price is rejected", "valid cost price" in html)
st, html, _ = o.post("product_form.php", {**good, "reorder_level": "abc"}); check("T17", "Validation", "Non-numeric reorder level is rejected", "Reorder level must be a whole number" in html)
st, html, _ = o.post("product_form.php", {**good, "sku": "SNK-001"}); check("T18", "Validation", "Duplicate SKU is rejected", "already used" in html)
st, html, url = o.post("product_form.php", good); check("T19", "Inventory", "Valid product is created (SKU upper-cased)", "Test Product Alpha" in html and "TST-001" in html, url)
pid = sql("SELECT product_id FROM products WHERE sku='TST-001'")
st, html, _ = o.post(f"product_form.php?id={pid}", {**good, "sku": "TST-001", "product_name": "Test Product Beta"}); check("T20", "Inventory", "Product can be edited", "Test Product Beta" in html)
xss = '<script>alert(1)</script>'
o.post("product_form.php", {**good, "sku": "XSS-1", "product_name": xss})
st, html, _ = o.req("products.php?q=XSS"); check("T21", "Security", "HTML in product name is escaped (no XSS)", xss not in html and "&lt;script&gt;" in html)
st, html, _ = o.req("product_view.php?id=99999"); check("T22", "Validation", "Unknown product id returns 404", st == 404)
# ---- Stock movements
def move(c, **kw): return c.post("stock_move.php", kw)
st, html, _ = move(o, product=pid, type="in", qty="0", expiry=""); check("T23", "Validation", "Quantity 0 is rejected", "at least 1" in html)
st, html, _ = move(o, product=pid, type="in", qty="-4", expiry=""); check("T24", "Validation", "Negative quantity is rejected", "at least 1" in html)
st, html, _ = move(o, product=pid, type="in", qty="10", expiry=""); check("T25", "Validation", "Stock in for expiring product requires expiry date", "Please enter it" in html)
past = (datetime.date.today() - datetime.timedelta(days=3)).isoformat()
st, html, _ = move(o, product=pid, type="in", qty="10", expiry=past); check("T26", "Validation", "Past expiry date is rejected", "in the past" in html)
d1 = (datetime.date.today() + datetime.timedelta(days=60)).isoformat(); d2 = (datetime.date.today() + datetime.timedelta(days=20)).isoformat()
st, html, _ = move(o, product=pid, type="in", qty="10", expiry=d1); check("T27", "Stock", "Stock in creates a batch", sql(f"SELECT SUM(quantity_on_hand) FROM stock_batches WHERE product_id={pid}") == "10")
move(o, product=pid, type="in", qty="5", expiry=d2)
st, html, _ = move(s, product=pid, type="out", qty="7", reason="Sold")
batches = sql(f"SELECT CONCAT(quantity_on_hand,'@',expiry_date) FROM stock_batches WHERE product_id={pid} ORDER BY expiry_date").split("\n")
check("T28", "Stock", "Stock out uses earliest-expiry batch first (FEFO)", batches == [f"0@{d2}", f"8@{d1}"], str(batches))
st, html, _ = move(s, product=pid, type="out", qty="999"); check("T29", "Validation", "Stock out above available stock is blocked", "Not enough stock" in html)
check("T30", "Stock", "Blocked stock out leaves stock unchanged", sql(f"SELECT SUM(quantity_on_hand) FROM stock_batches WHERE product_id={pid}") == "8")
st, html, _ = move(s, product=pid, type="waste", qty="1", reason=""); check("T31", "Validation", "Waste without a reason is rejected", "give a reason" in html)
move(s, product=pid, type="waste", qty="1", reason="Damaged")
check("T32", "Stock", "Waste reduces stock and is logged as WASTE", sql(f"SELECT COUNT(*) FROM stock_movements WHERE product_id={pid} AND type='WASTE' AND quantity=-1") == "1" and sql(f"SELECT SUM(quantity_on_hand) FROM stock_batches WHERE product_id={pid}") == "7")
move(o, product=pid, type="adj_minus", qty="2", reason="Recount")
check("T33", "Stock", "Negative adjustment reduces stock", sql(f"SELECT SUM(quantity_on_hand) FROM stock_batches WHERE product_id={pid}") == "5")
move(o, product=pid, type="adj_plus", qty="3", reason="Recount", expiry=d1)
check("T34", "Stock", "Positive adjustment adds stock", sql(f"SELECT SUM(quantity_on_hand) FROM stock_batches WHERE product_id={pid}") == "8")
check("T35", "Audit", "Every movement records the user who made it (2 different users)", sql(f"SELECT COUNT(DISTINCT user_id) FROM stock_movements WHERE product_id={pid}") == "2")
check("T36", "Audit", "Sum of movements equals sum of batch quantities", sql(f"SELECT SUM(quantity) FROM stock_movements WHERE product_id={pid}") == sql(f"SELECT SUM(quantity_on_hand) FROM stock_batches WHERE product_id={pid}"))
st, html, _ = o.req("movements.php?type=WASTE"); check("T37", "Movement log", "Movement log filter by type works", "Damaged" in html and "Recount" not in html)
st, html, _ = o.req(f"product_view.php?id={pid}"); check("T38", "Inventory", "Product detail page shows batches and movements", "Batches" in html and "Recent movements" in html)
sql(f"UPDATE products SET reorder_level=50 WHERE product_id={pid}")
st, html, _ = o.req("dashboard.php"); check("T39", "Dashboard", "Product at/below reorder level appears in low-stock alerts", "Test Product Beta" in html)
# ---- Suppliers
st, html, _ = o.post("supplier_form.php", {"supplier_name": "", "contact_number": "", "address": ""}); check("T40", "Validation", "Supplier without name is rejected", "Supplier name is required" in html)
st, html, _ = o.post("supplier_form.php", {"supplier_name": "Test Supplier", "contact_number": "abc!!", "address": ""}); check("T41", "Validation", "Invalid supplier phone number is rejected", "Contact number may only" in html)
st, html, _ = o.post("supplier_form.php", {"supplier_name": "Test Supplier", "contact_number": "0917 123 4567", "address": "Cainta"}); check("T42", "Suppliers", "Valid supplier is created", "Test Supplier" in html)
sup = sql("SELECT supplier_id FROM suppliers WHERE supplier_name='Test Supplier'")
st, html, _ = o.post("suppliers.php", {"id": "1"}, "suppliers.php"); check("T43", "Suppliers", "Supplier linked to products cannot be deleted", "Cannot delete" in html and sql("SELECT COUNT(*) FROM suppliers WHERE supplier_id=1") == "1")
st, html, _ = s.post("suppliers.php", {"id": sup}, "suppliers.php"); check("T44", "Access control", "Staff cannot delete a supplier (403)", st == 403)
o.post("suppliers.php", {"id": sup}, "suppliers.php"); check("T45", "Suppliers", "Owner can delete an unused supplier", sql(f"SELECT COUNT(*) FROM suppliers WHERE supplier_id={sup}") == "0")
# ---- Purchase orders
st, html, _ = o.req("purchase_orders.php"); check("T46", "Purchase orders", "Reorder suggestions list low-stock products by supplier", "Cola 1.5L" in html and "Sunrise Beverage" in html)
cola = sql("SELECT product_id FROM products WHERE sku='BEV-001'"); cola2 = sql("SELECT product_id FROM products WHERE sku='BEV-002'"); sn = sql("SELECT product_id FROM products WHERE sku='SNK-002'")
tok = o.token("purchase_orders.php")
def post_po(pairs):
    r = o.op.open(urllib.request.Request(BASE + "/purchase_orders.php", data=urllib.parse.urlencode([("csrf", tok)] + pairs).encode())); return r.status, r.read().decode()
st, html = post_po([("sel[]", cola2), (f"qty[{cola2}]", "0")]); check("T47", "Validation", "Suggested order with quantity 0 is rejected", "at least 1" in html)
post_po([("sel[]", cola2), ("sel[]", sn), (f"qty[{cola2}]", "24"), (f"qty[{sn}]", "30")])
check("T48", "Purchase orders", "Creating from suggestions makes one draft PO per supplier", sql("SELECT COUNT(*) FROM purchase_orders WHERE status='Draft'") == "2", sql("SELECT COUNT(*) FROM purchase_orders"))
po = sql(f"SELECT po.po_id FROM purchase_orders po JOIN po_items i ON i.po_id=po.po_id WHERE i.product_id={cola2}")
o.post(f"po_view.php?id={po}", {"action": "order"}); check("T49", "Purchase orders", "Draft PO can be marked as Ordered", sql(f"SELECT status FROM purchase_orders WHERE po_id={po}") == "Ordered")
item = sql(f"SELECT po_item_id FROM po_items WHERE po_id={po}")
st, html, _ = o.post(f"po_view.php?id={po}", {"action": "receive", f"recv[{item}]": "x", f"exp[{item}]": d1}); check("T50", "Validation", "Receiving with an invalid quantity is rejected", "quantity received" in html)
before = sql(f"SELECT COALESCE(SUM(quantity_on_hand),0) FROM stock_batches WHERE product_id={cola2}")
o.post(f"po_view.php?id={po}", {"action": "receive", f"recv[{item}]": "24", f"exp[{item}]": d1}); after = sql(f"SELECT SUM(quantity_on_hand) FROM stock_batches WHERE product_id={cola2}")
check("T51", "Purchase orders", "Receiving a PO adds stock; the batch is linked to the PO item", int(after) == int(before) + 24 and sql(f"SELECT COUNT(*) FROM stock_batches WHERE po_item_id={item}") == "1", f"{before}->{after}")
check("T52", "Purchase orders", "Received PO status becomes Received", sql(f"SELECT status FROM purchase_orders WHERE po_id={po}") == "Received")
o.post(f"po_view.php?id={po}", {"action": "receive", f"recv[{item}]": "24", f"exp[{item}]": d1})
check("T53", "Purchase orders", "Receiving the same PO twice does not double the stock", sql(f"SELECT SUM(quantity_on_hand) FROM stock_batches WHERE product_id={cola2}") == after)
st, html, _ = o.req("po_new.php?supplier=2"); check("T54", "Purchase orders", "Manual PO page lists the supplier's products", "Cola 1.5L" in html)
# ---- Import
b0 = sql(f"SELECT SUM(quantity_on_hand) FROM stock_batches WHERE product_id={cola}")
st, html, _ = o.post("import_sales.php", {}, files={"csv": ("bad.csv", "sku,quantity\nNOPE-1,2\nBEV-001,abc\n")}); check("T55", "Import", "CSV with unknown SKU / bad quantity is rejected with line numbers", "Line 2" in html and "Line 3" in html)
st, html, _ = o.post("import_sales.php", {}, files={"csv": ("big.csv", "sku,quantity\nBEV-001,99999\n")}); check("T56", "Import", "CSV that would oversell is rejected", "Not enough stock" in html)
check("T57", "Import", "Rejected import changes nothing (all-or-nothing)", sql(f"SELECT SUM(quantity_on_hand) FROM stock_batches WHERE product_id={cola}") == b0)
st, html, _ = o.post("import_sales.php", {}, files={"csv": ("x.txt", "sku,quantity\nBEV-001,1\n")}); check("T58", "Import", "Non-CSV file is rejected", "Only .csv" in html)
o.post("import_sales.php", {}, files={"csv": ("ok.csv", "sku,quantity,date\nBEV-001,2,\nSNK-001,1,\n")}); check("T59", "Import", "Valid CSV reduces stock", int(b0) - int(sql(f"SELECT SUM(quantity_on_hand) FROM stock_batches WHERE product_id={cola}")) == 2)
# ---- Reports / notes / users / account
for i, (t, label) in enumerate([("lowstock", "Low-stock"), ("expiry", "Expiry"), ("movements", "Movement summary"), ("wastage", "Wastage")]):
    st, html, _ = o.req(f"reports.php?type={t}"); check(f"T6{i}", "Reports", f"{label} report loads", st == 200 and "<table" in html)
st, html, _ = o.req("reports.php?type=lowstock&export=csv"); check("T64", "Reports", "Report can be downloaded as CSV", html.startswith("SKU,Product"))
st, html, _ = s.post("notes.php", {"note_text": ""}); check("T65", "Shift notes", "Empty note is rejected", "write a note" in html)
st, html, _ = s.post("notes.php", {"note_text": "Test handover note <b>bold</b>"}); check("T66", "Shift notes", "Staff can post a note (HTML is escaped)", "Test handover note" in html and "<b>bold</b>" not in html)
st, html, _ = o.post("users.php", {"action": "add", "full_name": "Sub Staff", "username": "sub1", "role": "Staff", "password": "short"}); check("T67", "Validation", "Weak password is rejected when adding a user", "at least 8 characters" in html)
st, html, _ = o.post("users.php", {"action": "add", "full_name": "Sub Staff", "username": "sub1", "role": "Staff", "password": "Substitute1"}); check("T68", "Users", "Owner can add a substitute staff user", "Sub Staff" in html)
sub = Client(); st, html, _ = sub.post("index.php", {"username": "sub1", "password": "Substitute1"}); check("T69", "Users", "New user can log in", "Low-stock alerts" in html)
uid = sql("SELECT user_id FROM users WHERE username='sub1'")
o.post("users.php", {"action": "toggle", "id": uid}); sub2 = Client(); st, html, _ = sub2.post("index.php", {"username": "sub1", "password": "Substitute1"}); check("T70", "Users", "Deactivated user can no longer log in", "Incorrect username" in html)
st, html, _ = s.post("account.php", {"current": "wrong", "new": "NewPass123", "confirm": "NewPass123"}); check("T71", "Account", "Password change needs the correct current password", "Current password is incorrect" in html)
st, html, _ = s.post("account.php", {"current": "Staff@123", "new": "NewPass123", "confirm": "NewPass123"}); check("T72", "Account", "Password can be changed", "Password changed" in html)
lk = Client()
for _ in range(5): lk.post("index.php", {"username": "owner", "password": "bad"})
st, html, _ = lk.post("index.php", {"username": "owner", "password": "Owner@123"}); check("T73", "Security", "Login is temporarily locked after 5 failed attempts", "Too many failed attempts" in html)
st, html, _ = o.post("logout.php", {}, "dashboard.php"); st, html, _ = o.req("dashboard.php"); check("T74", "Authentication", "Logout ends the session", "Staff Login" in html)
st, html, _ = Client().post("index.php", {"username": "' OR '1'='1' -- ", "password": "x"}); check("T75", "Security", "SQL injection attempt in login does not authenticate", "Incorrect username" in html)

p = sum(1 for r in results if r["result"] == "PASS")
print(f"\n{p}/{len(results)} passed")
json.dump(results, open("tests/results.json", "w"), indent=1)
sys.exit(0 if p == len(results) else 1)
