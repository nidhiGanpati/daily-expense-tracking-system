// API Base URL
const API_URL = 'api';

// Global variables
let currentUser = null;
let expenses = [];
let categoryChart = null;
let trendChart = null;

// Initialize app
document.addEventListener('DOMContentLoaded', function() {
    checkSession();
    setupEventListeners();
    setDefaultDate();
});

// Setup event listeners
function setupEventListeners() {
    // Auth forms
    document.getElementById('loginFormElement').addEventListener('submit', handleLogin);
    document.getElementById('registerFormElement').addEventListener('submit', handleRegister);
    document.getElementById('showRegister').addEventListener('click', showRegisterForm);
    document.getElementById('showLogin').addEventListener('click', showLoginForm);
    
    // Dashboard actions
    document.getElementById('logoutBtn').addEventListener('click', handleLogout);
    document.getElementById('addExpenseBtn').addEventListener('click', () => openExpenseModal());
    document.getElementById('setBudgetBtn').addEventListener('click', openBudgetModal);
    document.getElementById('filterBtn').addEventListener('click', filterExpenses);
    document.getElementById('clearFilterBtn').addEventListener('click', clearFilters);
    document.getElementById('exportBtn').addEventListener('click', exportReport);
    
    // Forms
    document.getElementById('expenseForm').addEventListener('submit', handleExpenseSubmit);
    document.getElementById('budgetForm').addEventListener('submit', handleBudgetSubmit);
    
    // Modal close
    document.querySelectorAll('.close').forEach(btn => {
        btn.addEventListener('click', closeModals);
    });
    
    // Search
    document.getElementById('searchInput').addEventListener('input', debounce(searchExpenses, 500));
}

// Check if user is logged in
async function checkSession() {
    try {
        const response = await fetch(`${API_URL}/auth.php?action=check`);
        const data = await response.json();
        
        if (data.success) {
            currentUser = data.user;
            showDashboard();
            loadDashboardData();
        }
    } catch (error) {
        console.error('Session check failed:', error);
    }
}

// Handle login
async function handleLogin(e) {
    e.preventDefault();
    
    const email = document.getElementById('loginEmail').value;
    const password = document.getElementById('loginPassword').value;
    
    try {
        const response = await fetch(`${API_URL}/auth.php?action=login`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            currentUser = data.user;
            showDashboard();
            loadDashboardData();
        } else {
            showAlert(data.message, 'error');
        }
    } catch (error) {
        showAlert('Login failed. Please try again.', 'error');
    }
}

// Handle registration
async function handleRegister(e) {
    e.preventDefault();
    
    const username = document.getElementById('registerUsername').value;
    const email = document.getElementById('registerEmail').value;
    const password = document.getElementById('registerPassword').value;
    
    try {
        const response = await fetch(`${API_URL}/auth.php?action=register`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, email, password })
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('Registration successful! Please login.', 'success');
            showLoginForm();
        } else {
            showAlert(data.message, 'error');
        }
    } catch (error) {
        showAlert('Registration failed. Please try again.', 'error');
    }
}

// Handle logout
async function handleLogout() {
    try {
        await fetch(`${API_URL}/auth.php?action=logout`);
        currentUser = null;
        document.getElementById('dashboard').classList.add('hidden');
        document.getElementById('authContainer').classList.remove('hidden');
    } catch (error) {
        console.error('Logout failed:', error);
    }
}

// Show/hide forms
function showRegisterForm(e) {
    e.preventDefault();
    document.getElementById('loginForm').classList.add('hidden');
    document.getElementById('registerForm').classList.remove('hidden');
}

function showLoginForm(e) {
    if (e) e.preventDefault();
    document.getElementById('registerForm').classList.add('hidden');
    document.getElementById('loginForm').classList.remove('hidden');
}

function showDashboard() {
    document.getElementById('authContainer').classList.add('hidden');
    document.getElementById('dashboard').classList.remove('hidden');
    document.getElementById('userName').textContent = currentUser.username;
}

// Load dashboard data
async function loadDashboardData() {
    await loadExpenses();
    await loadStats();
    await loadBudgets();
}

// Load expenses
async function loadExpenses() {
    try {
        const response = await fetch(`${API_URL}/expenses.php?action=all`);
        const data = await response.json();
        
        if (data.success) {
            expenses = data.data;
            displayExpenses(expenses);
        }
    } catch (error) {
        console.error('Failed to load expenses:', error);
    }
}

// Display expenses in table
function displayExpenses(expenseList) {
    const tbody = document.getElementById('expenseTableBody');
    tbody.innerHTML = '';
    
    if (expenseList.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No expenses found</td></tr>';
        return;
    }
    
    expenseList.forEach(expense => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${formatDate(expense.expense_date)}</td>
            <td><span class="badge">${expense.category}</span></td>
            <td>${expense.description || '-'}</td>
            <td>$${parseFloat(expense.amount).toFixed(2)}</td>
            <td>${expense.payment_method}</td>
            <td>
                <button class="btn btn-edit" onclick="editExpense(${expense.expense_id})">Edit</button>
                <button class="btn btn-danger" onclick="deleteExpense(${expense.expense_id})">Delete</button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

// Load statistics
async function loadStats() {
    try {
        const response = await fetch(`${API_URL}/expenses.php?action=stats`);
        const data = await response.json();
        
        if (data.success) {
            updateSummaryCards(data);
            updateCharts(data);
        }
    } catch (error) {
        console.error('Failed to load stats:', error);
    }
}

// Update summary cards
function updateSummaryCards(data) {
    document.getElementById('totalExpenses').textContent = `$${parseFloat(data.total).toFixed(2)}`;
    document.getElementById('totalTransactions').textContent = expenses.length;
    
    // Calculate this month
    const now = new Date();
    const monthExpenses = expenses.filter(e => {
        const expDate = new Date(e.expense_date);
        return expDate.getMonth() === now.getMonth() && expDate.getFullYear() === now.getFullYear();
    });
    
    const monthTotal = monthExpenses.reduce((sum, e) => sum + parseFloat(e.amount), 0);
    document.getElementById('monthExpenses').textContent = `$${monthTotal.toFixed(2)}`;
}

// Update charts
function updateCharts(data) {
    // Category Chart
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    
    if (categoryChart) {
        categoryChart.destroy();
    }
    
    categoryChart = new Chart(categoryCtx, {
        type: 'pie',
        data: {
            labels: data.categories.map(c => c.category),
            datasets: [{
                data: data.categories.map(c => c.total),
                backgroundColor: [
                    '#667eea', '#764ba2', '#f093fb', '#4facfe',
                    '#43e97b', '#fa709a', '#fee140', '#30cfd0'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { position: 'bottom' }
            }
        }
    });
    
    // Trend Chart
    const trendCtx = document.getElementById('trendChart').getContext('2d');
    
    if (trendChart) {
        trendChart.destroy();
    }
    
    trendChart = new Chart(trendCtx, {
        type: 'line',
        data: {
            labels: data.trends.map(t => t.month),
            datasets: [{
                label: 'Monthly Spending',
                data: data.trends.map(t => t.total),
                borderColor: '#667eea',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: { display: false }
            },
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}

// Open expense modal
function openExpenseModal(expense = null) {
    document.getElementById('expenseModal').classList.remove('hidden');
    document.getElementById('expenseModalTitle').textContent = expense ? 'Edit Expense' : 'Add Expense';
    
    if (expense) {
        document.getElementById('expenseId').value = expense.expense_id;
        document.getElementById('expenseAmount').value = expense.amount;
        document.getElementById('expenseCategory').value = expense.category;
        document.getElementById('expenseDescription').value = expense.description;
        document.getElementById('expenseDate').value = expense.expense_date;
        document.getElementById('paymentMethod').value = expense.payment_method;
    } else {
        document.getElementById('expenseForm').reset();
        document.getElementById('expenseId').value = '';
        setDefaultDate();
    }
}

// Edit expense
function editExpense(id) {
    const expense = expenses.find(e => e.expense_id == id);
    if (expense) {
        openExpenseModal(expense);
    }
}

// Delete expense
async function deleteExpense(id) {
    if (!confirm('Are you sure you want to delete this expense?')) return;
    
    try {
        const response = await fetch(`${API_URL}/expenses.php?id=${id}`, {
            method: 'DELETE'
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('Expense deleted successfully', 'success');
            loadDashboardData();
        } else {
            showAlert(data.message, 'error');
        }
    } catch (error) {
        showAlert('Failed to delete expense', 'error');
    }
}

// Handle expense submit
async function handleExpenseSubmit(e) {
    e.preventDefault();
    
    const expenseId = document.getElementById('expenseId').value;
    const expenseData = {
        expense_id: expenseId,
        amount: document.getElementById('expenseAmount').value,
        category: document.getElementById('expenseCategory').value,
        description: document.getElementById('expenseDescription').value,
        expense_date: document.getElementById('expenseDate').value,
        payment_method: document.getElementById('paymentMethod').value
    };
    
    const method = expenseId ? 'PUT' : 'POST';
    
    try {
        const response = await fetch(`${API_URL}/expenses.php`, {
            method: method,
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(expenseData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert(expenseId ? 'Expense updated' : 'Expense added', 'success');
            closeModals();
            loadDashboardData();
        } else {
            showAlert(data.message, 'error');
        }
    } catch (error) {
        showAlert('Failed to save expense', 'error');
    }
}

// Budget functions
function openBudgetModal() {
    document.getElementById('budgetModal').classList.remove('hidden');
    setDefaultBudgetDates();
}

async function handleBudgetSubmit(e) {
    e.preventDefault();
    
    const budgetData = {
        budget_amount: document.getElementById('budgetAmount').value,
        budget_period: document.getElementById('budgetPeriod').value,
        start_date: document.getElementById('budgetStartDate').value,
        end_date: document.getElementById('budgetEndDate').value
    };
    
    try {
        const response = await fetch(`${API_URL}/budgets.php`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(budgetData)
        });
        
        const data = await response.json();
        
        if (data.success) {
            showAlert('Budget set successfully', 'success');
            closeModals();
            loadBudgets();
        } else {
            showAlert(data.message, 'error');
        }
    } catch (error) {
        showAlert('Failed to set budget', 'error');
    }
}

async function loadBudgets() {
    try {
        const response = await fetch(`${API_URL}/budgets.php`);
        const data = await response.json();
        
        if (data.success && data.data.length > 0) {
            const budget = data.data[0];
            document.getElementById('budgetRemaining').textContent = `$${parseFloat(budget.remaining).toFixed(2)}`;
        }
    } catch (error) {
        console.error('Failed to load budgets:', error);
    }
}

// Filter and search
async function filterExpenses() {
    const category = document.getElementById('categoryFilter').value;
    const startDate = document.getElementById('startDate').value;
    const endDate = document.getElementById('endDate').value;
    
    let url = `${API_URL}/expenses.php?action=filter`;
    if (category) url += `&category=${category}`;
    if (startDate) url += `&start_date=${startDate}`;
    if (endDate) url += `&end_date=${endDate}`;
    
    try {
        const response = await fetch(url);
        const data = await response.json();
        
        if (data.success) {
            displayExpenses(data.data);
        }
    } catch (error) {
        console.error('Filter failed:', error);
    }
}

function clearFilters() {
    document.getElementById('categoryFilter').value = '';
    document.getElementById('startDate').value = '';
    document.getElementById('endDate').value = '';
    document.getElementById('searchInput').value = '';
    loadExpenses();
}

async function searchExpenses() {
    const search = document.getElementById('searchInput').value;
    
    try {
        const response = await fetch(`${API_URL}/expenses.php?action=filter&search=${search}`);
        const data = await response.json();
        
        if (data.success) {
            displayExpenses(data.data);
        }
    } catch (error) {
        console.error('Search failed:', error);
    }
}

// Export report
function exportReport() {
    const csv = generateCSV(expenses);
    downloadCSV(csv, 'expense_report.csv');
}

function generateCSV(data) {
    const headers = ['Date', 'Category', 'Description', 'Amount', 'Payment Method'];
    const rows = data.map(e => [
        e.expense_date,
        e.category,
        e.description || '',
        e.amount,
        e.payment_method
    ]);
    
    return [headers, ...rows].map(row => row.join(',')).join('\n');
}

function downloadCSV(csv, filename) {
    const blob = new Blob([csv], { type: 'text/csv' });
    const url = window.URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    a.click();
}

// Utility functions
function closeModals() {
    document.querySelectorAll('.modal').forEach(modal => {
        modal.classList.add('hidden');
    });
}

function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;
    
    document.querySelector('.main-content').prepend(alertDiv);
    
    setTimeout(() => alertDiv.remove(), 3000);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
}

function setDefaultDate() {
    const today = new Date().toISOString().split('T')[0];
    document.getElementById('expenseDate').value = today;
}

function setDefaultBudgetDates() {
    const now = new Date();
    const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
    const lastDay = new Date(now.getFullYear(), now.getMonth() + 1, 0);
    
    document.getElementById('budgetStartDate').value = firstDay.toISOString().split('T')[0];
    document.getElementById('budgetEndDate').value = lastDay.toISOString().split('T')[0];
}

function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}