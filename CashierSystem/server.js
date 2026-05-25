const express = require('express');
const mysql = require('mysql2/promise');
const cors = require('cors');
require('dotenv').config();

const app = express();
app.use(cors());
app.use(express.json());

// ==========================================
// DATABASE CONNECTION
// ==========================================
const pool = mysql.createPool({
    host: process.env.DB_HOST || '127.0.0.1',
    user: 'root',
    password: process.env.DB_PASSWORD || "",
    database: 'manaklay_db',
    waitForConnections: true,
    connectionLimit: 10,
    queueLimit: 0
});

// ==========================================
// 1. AUTH & USERS ENDPOINTS
// ==========================================

app.post('/api/login', async(req, res) => {
    const { username, password } = req.body;
    try {
        const [users] = await pool.query(
            'SELECT user_id, username, full_name, role FROM users WHERE username = ? AND password = ?', [username, password]
        );
        if (users.length > 0) res.json({ success: true, user: users[0] });
        else res.status(401).json({ success: false, message: 'Invalid credentials' });
    } catch (error) {
        res.status(500).json({ success: false, error: error.message });
    }
});

app.get('/api/users', async(req, res) => {
    try {
        const [rows] = await pool.query('SELECT user_id, username, full_name, email, role, created_at FROM users');
        res.json({ success: true, data: rows });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.get('/api/users/:id', async(req, res) => {
    try {
        const [rows] = await pool.query('SELECT user_id, username, full_name, email, role, created_at FROM users WHERE user_id = ?', [req.params.id]);
        res.json({ success: true, data: rows[0] });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.post('/api/users', async(req, res) => {
    const { username, password, full_name, email, role } = req.body;
    try {
        const [result] = await pool.query(
            'INSERT INTO users (username, password, full_name, email, role) VALUES (?, ?, ?, ?, ?)', [username, password, full_name, email, role || 'staff']
        );
        res.json({ success: true, id: result.insertId });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.put('/api/users/:id', async(req, res) => {
    const { username, password, full_name, email, role } = req.body;
    try {
        await pool.query(
            'UPDATE users SET username=?, password=?, full_name=?, email=?, role=? WHERE user_id=?', [username, password, full_name, email, role, req.params.id]
        );
        res.json({ success: true, message: 'User updated' });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.delete('/api/users/:id', async(req, res) => {
    try {
        await pool.query('DELETE FROM users WHERE user_id = ?', [req.params.id]);
        res.json({ success: true, message: 'User deleted' });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

// ==========================================
// 2. PRODUCT CATEGORIES ENDPOINTS
// ==========================================

app.get('/api/product-categories', async(req, res) => {
    try {
        const [rows] = await pool.query('SELECT * FROM product_categories');
        res.json({ success: true, data: rows });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.post('/api/product-categories', async(req, res) => {
    const { category_name, description } = req.body;
    try {
        const [result] = await pool.query('INSERT INTO product_categories (category_name, description) VALUES (?, ?)', [category_name, description]);
        res.json({ success: true, id: result.insertId });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.put('/api/product-categories/:id', async(req, res) => {
    const { category_name, description } = req.body;
    try {
        await pool.query('UPDATE product_categories SET category_name=?, description=? WHERE category_id=?', [category_name, description, req.params.id]);
        res.json({ success: true, message: 'Category updated' });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.delete('/api/product-categories/:id', async(req, res) => {
    try {
        await pool.query('DELETE FROM product_categories WHERE category_id = ?', [req.params.id]);
        res.json({ success: true, message: 'Category deleted' });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

// ==========================================
// 3. PRODUCTS ENDPOINTS
// ==========================================

app.get('/api/products', async(req, res) => {
    try {
        const [rows] = await pool.query(`
            SELECT p.*, c.category_name 
            FROM products p
            LEFT JOIN product_categories c ON p.category_id = c.category_id
        `);
        res.json({ success: true, data: rows });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.get('/api/products/:id', async(req, res) => {
    try {
        const [rows] = await pool.query('SELECT * FROM products WHERE product_id = ?', [req.params.id]);
        res.json({ success: true, data: rows[0] });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.post('/api/products', async(req, res) => {
    const { category_id, product_name, description, unit_price, cost_price, stock_quantity, reorder_level } = req.body;
    try {
        const [result] = await pool.query(
            `INSERT INTO products (category_id, product_name, description, unit_price, cost_price, stock_quantity, reorder_level) 
             VALUES (?, ?, ?, ?, ?, ?, ?)`, [category_id, product_name, description, unit_price, cost_price, stock_quantity || 0, reorder_level || 10]
        );
        res.json({ success: true, id: result.insertId });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.put('/api/products/:id', async(req, res) => {
    const { category_id, product_name, description, unit_price, cost_price, stock_quantity, reorder_level } = req.body;
    try {
        await pool.query(
            `UPDATE products SET category_id=?, product_name=?, description=?, unit_price=?, cost_price=?, stock_quantity=?, reorder_level=? 
             WHERE product_id=?`, [category_id, product_name, description, unit_price, cost_price, stock_quantity, reorder_level, req.params.id]
        );
        res.json({ success: true, message: 'Product updated' });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.delete('/api/products/:id', async(req, res) => {
    try {
        await pool.query('DELETE FROM products WHERE product_id = ?', [req.params.id]);
        res.json({ success: true, message: 'Product deleted' });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

// ==========================================
// 4. PRODUCT TRANSACTIONS (IN/OUT/ADJUST)
// ==========================================

app.get('/api/transactions', async(req, res) => {
    try {
        const [rows] = await pool.query('SELECT * FROM product_transactions ORDER BY transaction_date DESC');
        res.json({ success: true, data: rows });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.post('/api/transactions', async(req, res) => {
    const { product_id, transaction_type, quantity, unit_price, notes, user_id } = req.body;
    try {
        const [result] = await pool.query(
            'INSERT INTO product_transactions (product_id, transaction_type, quantity, unit_price, notes, user_id) VALUES (?, ?, ?, ?, ?, ?)', [product_id, transaction_type, quantity, unit_price, notes, user_id]
        );
        res.json({ success: true, id: result.insertId });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

// ==========================================
// 5. ORDERS & ORDER ITEMS
// ==========================================

app.get('/api/orders', async(req, res) => {
    try {
        const [rows] = await pool.query('SELECT * FROM orders ORDER BY order_date DESC');
        res.json({ success: true, data: rows });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.get('/api/orders/:id', async(req, res) => {
    try {
        const [order] = await pool.query('SELECT * FROM orders WHERE order_id = ?', [req.params.id]);
        const [items] = await pool.query('SELECT * FROM order_items WHERE order_id = ?', [req.params.id]);
        res.json({ success: true, order: order[0], items });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

// The specialized POST route that handles Order AND Order Items together
app.post('/api/orders', async(req, res) => {
    const { total_amount, paid_amount, payment_method, notes, user_id, items } = req.body;
    const connection = await pool.getConnection();
    await connection.beginTransaction();

    try {
        const [orderResult] = await connection.query(
            'INSERT INTO orders (total_amount, paid_amount, payment_method, notes, user_id) VALUES (?, ?, ?, ?, ?)', [total_amount, paid_amount, payment_method, notes, user_id]
        );
        const orderId = orderResult.insertId;

        for (let item of items) {
            await connection.query(
                'INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal, specifications) VALUES (?, ?, ?, ?, ?, ?)', [orderId, item.product_id, item.quantity, item.unit_price, item.subtotal, item.specifications || null]
            );
            await connection.query('UPDATE products SET stock_quantity = stock_quantity - ? WHERE product_id = ?', [item.quantity, item.product_id]);
        }
        await connection.commit();
        res.json({ success: true, message: 'Order created', orderId });
    } catch (error) {
        await connection.rollback();
        res.status(500).json({ success: false, error: error.message });
    } finally {
        connection.release();
    }
});

app.delete('/api/orders/:id', async(req, res) => {
    try {
        // Order items are deleted automatically if ON DELETE CASCADE is set. 
        // If not, you must delete order_items first, then the order.
        await pool.query('DELETE FROM order_items WHERE order_id = ?', [req.params.id]);
        await pool.query('DELETE FROM orders WHERE order_id = ?', [req.params.id]);
        res.json({ success: true, message: 'Order deleted' });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

// ==========================================
// 6. EXPENSES CATEGORIES
// ==========================================

app.get('/api/expenses-categories', async(req, res) => {
    try {
        const [rows] = await pool.query('SELECT * FROM expenses_categories');
        res.json({ success: true, data: rows });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.post('/api/expenses-categories', async(req, res) => {
    const { category_name, description } = req.body;
    try {
        const [result] = await pool.query('INSERT INTO expenses_categories (category_name, description) VALUES (?, ?)', [category_name, description]);
        res.json({ success: true, id: result.insertId });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.put('/api/expenses-categories/:id', async(req, res) => {
    const { category_name, description } = req.body;
    try {
        await pool.query('UPDATE expenses_categories SET category_name=?, description=? WHERE category_id=?', [category_name, description, req.params.id]);
        res.json({ success: true, message: 'Expense category updated' });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.delete('/api/expenses-categories/:id', async(req, res) => {
    try {
        await pool.query('DELETE FROM expenses_categories WHERE category_id = ?', [req.params.id]);
        res.json({ success: true, message: 'Expense category deleted' });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

// ==========================================
// 7. EXPENSES
// ==========================================

app.get('/api/expenses', async(req, res) => {
    try {
        const [rows] = await pool.query('SELECT * FROM expenses ORDER BY expense_date DESC');
        res.json({ success: true, data: rows });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.post('/api/expenses', async(req, res) => {
    const { category_id, expense_date, description, amount, reference, user_id, notes } = req.body;
    try {
        const [result] = await pool.query(
            'INSERT INTO expenses (category_id, expense_date, description, amount, reference, user_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?)', [category_id, expense_date, description, amount, reference, user_id, notes]
        );
        res.json({ success: true, id: result.insertId });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.put('/api/expenses/:id', async(req, res) => {
    const { category_id, expense_date, description, amount, reference, user_id, notes } = req.body;
    try {
        await pool.query(
            'UPDATE expenses SET category_id=?, expense_date=?, description=?, amount=?, reference=?, user_id=?, notes=? WHERE expense_id=?', [category_id, expense_date, description, amount, reference, user_id, notes, req.params.id]
        );
        res.json({ success: true, message: 'Expense updated' });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.delete('/api/expenses/:id', async(req, res) => {
    try {
        await pool.query('DELETE FROM expenses WHERE expense_id = ?', [req.params.id]);
        res.json({ success: true, message: 'Expense deleted' });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

// ==========================================
// 8. REPORTS
// ==========================================

app.get('/api/reports', async(req, res) => {
    try {
        const [rows] = await pool.query('SELECT * FROM reports ORDER BY created_at DESC');
        res.json({ success: true, data: rows });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.post('/api/reports', async(req, res) => {
    const { report_name, file_path, start_date, end_date } = req.body;
    try {
        const [result] = await pool.query(
            'INSERT INTO reports (report_name, file_path, start_date, end_date) VALUES (?, ?, ?, ?)', [report_name, file_path, start_date, end_date]
        );
        res.json({ success: true, id: result.insertId });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

app.delete('/api/reports/:id', async(req, res) => {
    try {
        await pool.query('DELETE FROM reports WHERE report_id = ?', [req.params.id]);
        res.json({ success: true, message: 'Report record deleted' });
    } catch (error) { res.status(500).json({ success: false, error: error.message }); }
});

// ==========================================
// START SERVER
// ==========================================
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    console.log(`API Server running on port ${PORT}`);
});