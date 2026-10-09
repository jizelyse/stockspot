-- Sample data for demonstration. All products and quantities are fictional.
-- Demo logins:  owner / Owner@123   and   staff / Staff@123   (change after first login!)
SET NAMES utf8mb4;
INSERT INTO users (full_name, username, password_hash, role) VALUES
 ('Shop Owner (Demo)', 'owner', '$2y$10$0QBCh0t8ZstPtTJ5s1Polek4jou/oEWVZP/RQcIRfMVV2OgvG/gwm', 'Owner'),
 ('Staff Member (Demo)', 'staff', '$2y$10$t8.h4igdXDVwW/hbU92hveGyr09PF8ELa/egZydQgik.ZytfgBE9e', 'Staff');

INSERT INTO categories (category_name) VALUES ('Snacks'),('Beverages'),('Canned & Instant Food'),('Household'),('Personal Care'),('Frozen');

INSERT INTO suppliers (supplier_name, contact_number, address) VALUES
 ('Metro Wholesale Trading', '0917 000 0001', 'Cainta, Rizal'),
 ('Sunrise Beverage Distributors', '0917 000 0002', 'Taytay, Rizal'),
 ('HomeCare Supplies Co.', '0917 000 0003', 'Antipolo, Rizal');

INSERT INTO products (category_id, supplier_id, sku, product_name, unit, cost_price, selling_price, reorder_level, has_expiry) VALUES
 (1,1,'SNK-001','Potato Chips 60g','pack',18.00,25.00,12,1),
 (1,1,'SNK-002','Chocolate Wafer Bar','pc',6.50,10.00,20,1),
 (1,1,'SNK-003','Butter Cookies 200g','pack',32.00,45.00,8,1),
 (2,2,'BEV-001','Bottled Water 500ml','bottle',9.00,15.00,24,1),
 (2,2,'BEV-002','Cola 1.5L','bottle',48.00,65.00,12,1),
 (2,2,'BEV-003','3-in-1 Coffee Sachet','sachet',4.50,7.00,50,1),
 (3,1,'CAN-001','Instant Noodles Cup','cup',14.00,20.00,18,1),
 (3,1,'CAN-002','Sardines in Tomato Sauce 155g','can',17.00,24.00,15,1),
 (3,1,'CAN-003','Corned Beef 150g','can',38.00,52.00,10,1),
 (4,3,'HOU-001','Dishwashing Liquid 250ml','bottle',28.00,40.00,6,0),
 (4,3,'HOU-002','Laundry Detergent Powder 70g','sachet',7.00,10.00,30,0),
 (4,3,'HOU-003','Tissue Roll (2-ply)','roll',12.00,18.00,16,0),
 (5,3,'PER-001','Shampoo Sachet','sachet',4.00,7.00,40,1),
 (5,3,'PER-002','Bath Soap 90g','bar',20.00,30.00,12,1),
 (6,1,'FRZ-001','Frozen Hotdog 500g','pack',95.00,125.00,6,1),
 (6,1,'FRZ-002','Chicken Nuggets 400g','pack',110.00,145.00,5,1);

-- Batches (dates are relative to the day the seed is loaded so the demo always shows realistic alerts)
INSERT INTO stock_batches (product_id, quantity_on_hand, date_received, expiry_date) VALUES
 (1,30,CURDATE()-INTERVAL 20 DAY,CURDATE()+INTERVAL 90 DAY),
 (2,8,CURDATE()-INTERVAL 15 DAY,CURDATE()+INTERVAL 60 DAY),
 (3,5,CURDATE()-INTERVAL 30 DAY,CURDATE()+INTERVAL 10 DAY),
 (4,60,CURDATE()-INTERVAL 10 DAY,CURDATE()+INTERVAL 180 DAY),
 (5,6,CURDATE()-INTERVAL 12 DAY,CURDATE()+INTERVAL 120 DAY),
 (6,120,CURDATE()-INTERVAL 25 DAY,CURDATE()+INTERVAL 200 DAY),
 (7,10,CURDATE()-INTERVAL 18 DAY,CURDATE()+INTERVAL 45 DAY),
 (8,24,CURDATE()-INTERVAL 18 DAY,CURDATE()+INTERVAL 300 DAY),
 (9,14,CURDATE()-INTERVAL 18 DAY,CURDATE()+INTERVAL 5 DAY),
 (10,12,CURDATE()-INTERVAL 40 DAY,NULL),
 (11,3,CURDATE()-INTERVAL 40 DAY,NULL),
 (12,30,CURDATE()-INTERVAL 22 DAY,NULL),
 (13,80,CURDATE()-INTERVAL 22 DAY,CURDATE()+INTERVAL 240 DAY),
 (14,18,CURDATE()-INTERVAL 22 DAY,CURDATE()+INTERVAL 150 DAY),
 (15,9,CURDATE()-INTERVAL 7 DAY,CURDATE()+INTERVAL 20 DAY),
 (16,4,CURDATE()-INTERVAL 7 DAY,CURDATE()+INTERVAL 30 DAY);

INSERT INTO stock_movements (product_id, batch_id, user_id, type, quantity, reason, moved_at)
 SELECT product_id, batch_id, 1, 'IN', quantity_on_hand, 'Opening stock (sample data)', date_received FROM stock_batches;
INSERT INTO stock_movements (product_id, batch_id, user_id, type, quantity, reason, moved_at) VALUES
 (4,4,2,'OUT',-6,'Sold',NOW()-INTERVAL 1 DAY),
 (7,7,2,'OUT',-5,'Sold',NOW()-INTERVAL 1 DAY),
 (2,2,2,'WASTE',-2,'Damaged packaging',NOW()-INTERVAL 2 DAY);

INSERT INTO shift_notes (user_id, note_text, shift_date) VALUES
 (2,'Cola 1.5L is almost out - please reorder from Sunrise Beverage Distributors.',CURDATE()),
 (1,'Check the expiry of the corned beef display this week.',CURDATE()-INTERVAL 1 DAY);
