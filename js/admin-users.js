/**
 * Admin Users Page - Firebase Version
 */

let users = [];

document.addEventListener('DOMContentLoaded', async function() {
    // Check admin authentication
    const isAuthenticated = await checkAdminAuth();
    if (!isAuthenticated) {
        return;
    }

    // Setup logout
    const logoutLink = document.getElementById('logout-link');
    if (logoutLink) {
        logoutLink.addEventListener('click', function(e) {
            e.preventDefault();
            handleAdminLogout();
        });
    }

    // Load users
    await loadUsers();

    // Setup form handlers
    document.getElementById('addUserForm').addEventListener('submit', handleAddUser);
    document.getElementById('editUserForm').addEventListener('submit', handleEditUser);
    document.getElementById('deleteUserForm').addEventListener('submit', handleDeleteUser);

    // Close modals when clicking outside
    window.onclick = function(event) {
        const addModal = document.getElementById('addUserModal');
        const editModal = document.getElementById('editUserModal');
        const deleteModal = document.getElementById('deleteUserModal');
        
        if (event.target === addModal) {
            closeAddUserModal();
        }
        if (event.target === editModal) {
            closeEditUserModal();
        }
        if (event.target === deleteModal) {
            closeDeleteUserModal();
        }
    };
});

/**
 * Load all users from Firebase
 */
async function loadUsers() {
    try {
        if (!window.FirebaseService || !window.FirebaseService.isOnline()) {
            showAlert('Firebase service not available. Please refresh the page.', 'danger');
            return;
        }

        const usersSnapshot = await window.firebaseDatabase.ref('users').once('value');
        const usersData = usersSnapshot.val() || {};
        
        users = Object.entries(usersData).map(([username, userData], index) => ({
            user_id: index + 1,
            username: username,
            password: userData.password || '',
            role: userData.role || 'cashier',
            cash_balance: parseFloat(userData.cash_balance || 0),
            created_at: userData.created_at || null,
            last_login: userData.last_login || null
        })).sort((a, b) => {
            const dateA = a.created_at ? new Date(a.created_at) : new Date(0);
            const dateB = b.created_at ? new Date(b.created_at) : new Date(0);
            return dateB - dateA;
        });

        displayUsers(users);
    } catch (error) {
        console.error('Error loading users:', error);
        showAlert('Error loading users: ' + error.message, 'danger');
    }
}

/**
 * Display users in table
 */
function displayUsers(usersList) {
    const tbody = document.getElementById('users-table-body');
    
    if (usersList.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center">No users found.</td></tr>';
        return;
    }

    let html = '';
    usersList.forEach(user => {
        const createdDate = user.created_at ? new Date(user.created_at).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'N/A';
        const lastLoginDate = user.last_login ? new Date(user.last_login).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : 'Never';
        
        const roleBadgeClass = user.role === 'admin' ? 'badge-danger' : user.role === 'cashier' ? 'badge-success' : 'badge-secondary';
        
        html += `
            <tr>
                <td>${user.user_id}</td>
                <td>${user.username}</td>
                <td><span class="badge ${roleBadgeClass}">${user.role}</span></td>
                <td>$${user.cash_balance.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                <td>${createdDate}</td>
                <td>${lastLoginDate}</td>
                <td>
                    <button class="btn btn-primary btn-sm" onclick="openEditUserModal('${user.username}', '${user.role}')">
                        <i class="fas fa-edit"></i> Edit
                    </button>
                    <button class="btn btn-danger btn-sm" onclick="openDeleteUserModal('${user.username}')">
                        <i class="fas fa-trash"></i> Delete
                    </button>
                </td>
            </tr>
        `;
    });
    tbody.innerHTML = html;
}

/**
 * Handle add user form submission
 */
async function handleAddUser(e) {
    e.preventDefault();
    
    const username = document.getElementById('username').value.trim();
    const password = document.getElementById('password').value.trim();
    const role = document.getElementById('role').value;
    const initialBalance = parseFloat(document.getElementById('initial_balance').value) || 0;

    // Validate
    if (username.length !== 12 || !/^\d{12}$/.test(username)) {
        showAlert('Username must be exactly 12 digits.', 'danger');
        return;
    }

    if (password.length !== 6 || !/^\d{6}$/.test(password)) {
        showAlert('Password must be exactly 6 digits.', 'danger');
        return;
    }

    try {
        // Check if user exists
        const userSnapshot = await window.firebaseDatabase.ref(`users/${username}`).once('value');
        if (userSnapshot.exists()) {
            showAlert('Username already exists.', 'danger');
            return;
        }

        // Create user in Firebase
        const userData = {
            password: password, // In production, this should be hashed
            role: role,
            cash_balance: initialBalance,
            created_at: new Date().toISOString(),
            last_login: null
        };

        await window.firebaseDatabase.ref(`users/${username}`).set(userData);

        // Create transaction if initial balance > 0
        if (initialBalance > 0 && window.FirebaseTransactions && window.FirebaseTransactions.isInitialized()) {
            await window.FirebaseTransactions.createTransaction({
                user_id: username,
                amount: initialBalance,
                balance_after: initialBalance,
                transaction_type: 'admin',
                description: 'Initial balance setup'
            });
        }

        showAlert('User added successfully.', 'success');
        closeAddUserModal();
        document.getElementById('addUserForm').reset();
        await loadUsers();

    } catch (error) {
        console.error('Error adding user:', error);
        showAlert('Error adding user: ' + error.message, 'danger');
    }
}

/**
 * Handle edit user form submission
 */
async function handleEditUser(e) {
    e.preventDefault();
    
    const username = document.getElementById('edit_username').value.trim();
    const role = document.getElementById('edit_role').value;
    const password = document.getElementById('edit_password').value.trim();

    try {
        const userRef = window.firebaseDatabase.ref(`users/${username}`);
        const updates = {
            role: role
        };

        // Update password if provided
        if (password) {
            if (password.length !== 6 || !/^\d{6}$/.test(password)) {
                showAlert('Password must be exactly 6 digits.', 'danger');
                return;
            }
            updates.password = password; // In production, this should be hashed
        }

        await userRef.update(updates);
        showAlert('User updated successfully.', 'success');
        closeEditUserModal();
        await loadUsers();

    } catch (error) {
        console.error('Error updating user:', error);
        showAlert('Error updating user: ' + error.message, 'danger');
    }
}

/**
 * Handle delete user form submission
 */
async function handleDeleteUser(e) {
    e.preventDefault();
    
    const username = document.getElementById('delete_username').textContent.trim();

    try {
        // Check if user has transactions
        const transactionsSnapshot = await window.firebaseDatabase.ref('transactions')
            .orderByChild('user_id')
            .equalTo(username)
            .once('value');

        if (transactionsSnapshot.exists()) {
            showAlert('Cannot delete user with transactions. Consider deactivating instead.', 'warning');
            closeDeleteUserModal();
            return;
        }

        // Delete user from Firebase
        await window.firebaseDatabase.ref(`users/${username}`).remove();
        showAlert('User deleted successfully.', 'success');
        closeDeleteUserModal();
        await loadUsers();

    } catch (error) {
        console.error('Error deleting user:', error);
        showAlert('Error deleting user: ' + error.message, 'danger');
    }
}

/**
 * Open add user modal
 */
function openAddUserModal() {
    document.getElementById('addUserModal').style.display = 'block';
}

/**
 * Close add user modal
 */
function closeAddUserModal() {
    document.getElementById('addUserModal').style.display = 'none';
    document.getElementById('addUserForm').reset();
}

/**
 * Open edit user modal
 */
function openEditUserModal(username, role) {
    document.getElementById('edit_username').value = username;
    document.getElementById('edit_role').value = role;
    document.getElementById('edit_password').value = '';
    document.getElementById('editUserModal').style.display = 'block';
}

/**
 * Close edit user modal
 */
function closeEditUserModal() {
    document.getElementById('editUserModal').style.display = 'none';
    document.getElementById('editUserForm').reset();
}

/**
 * Open delete user modal
 */
function openDeleteUserModal(username) {
    document.getElementById('delete_username').textContent = username;
    document.getElementById('deleteUserModal').style.display = 'block';
}

/**
 * Close delete user modal
 */
function closeDeleteUserModal() {
    document.getElementById('deleteUserModal').style.display = 'none';
}

