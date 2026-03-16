let searchTimeout;
let currentPage = 1;

document.getElementById('searchInput').addEventListener('input', function() {
    const searchTerm = this.value.trim();
    currentPage = 1;
    
    clearTimeout(searchTimeout);
    document.querySelector('.search-loading').style.display = 'block';
    
    searchTimeout = setTimeout(() => {
        performSearch(searchTerm, currentPage);
    }, 300);
});

function performSearch(searchTerm, page = 1) {
    fetch(`equipments.php?ajax_search=1&search=${encodeURIComponent(searchTerm)}&page=${page}`)
        .then(response => response.json())
        .then(data => {
            updateTable(data.data);
            updatePagination(data.pagination, data.total, data.data.length);
            document.querySelector('.search-loading').style.display = 'none';
        })
        .catch(error => {
            console.error('Search error:', error);
            document.querySelector('.search-loading').style.display = 'none';
        });
}

function updateTable(equipments) {
    const tbody = document.getElementById('equipmentTableBody');
    const noResults = document.getElementById('noResults');
    
    if (equipments.length === 0) {
        tbody.innerHTML = '';
        noResults.style.display = 'block';
        return;
    }
    
    noResults.style.display = 'none';
    
    let html = '';
    equipments.forEach(eq => {
        const photoHtml = eq.photo ? 
            `<img src="../uploads/${escapeHtml(eq.photo)}" width="50" class="rounded">` : 
            'N/A';
            
        html += `
            <tr>
                <td>${escapeHtml(eq.name || 'N/A')}</td>
                <td>${photoHtml}</td>
                <td>${escapeHtml(eq.category || 'N/A')}</td>
                <td>${Number(eq.price || 0).toFixed(2)}</td>
                <td>${escapeHtml(eq.quantity || 0)}</td>
                <td>${escapeHtml(eq.stock || 0)}</td>
                <td>
                    <button class="btn btn-sm btn-warning" onclick="openEditModal(
                        '${eq.id}',
                        '${escapeHtml(eq.name || '', true)}',
                        '${escapeHtml(eq.category || '')}',
                        '${eq.price || 0}',
                        '${eq.quantity || 0}',
                        '${eq.stock || 0}',
                        '${escapeHtml(eq.photo || '')}'
                    )"><i class="fas fa-edit"></i></button>
                    
                    <a href="?delete_id=${eq.id}" class="btn btn-sm btn-danger"
                       onclick="return confirm('Are you sure you want to delete this equipment?');">
                       <i class="fas fa-trash-alt"></i></a>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

function updatePagination(paginationHtml, total, currentCount) {
    document.getElementById('paginationContainer').innerHTML = paginationHtml;
    document.getElementById('totalInfo').textContent = `Showing ${currentCount} of ${total} equipments`;
    
    document.querySelectorAll('#paginationContainer a').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const url = new URL(this.href);
            const page = url.searchParams.get('page') || 1;
            currentPage = parseInt(page);
            const searchTerm = document.getElementById('searchInput').value.trim();
            performSearch(searchTerm, currentPage);
        });
    });
}

function escapeHtml(text, quotes = false) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    let escaped = div.innerHTML;
    if (quotes) {
        escaped = escaped.replace(/'/g, '&#39;').replace(/"/g, '&quot;');
    }
    return escaped;
}

function openEditModal(id, name, category, price, quantity, stock, photo) {
    document.getElementById('edit_id').value = id;
    document.getElementById('edit_name').value = name;
    document.getElementById('edit_price').value = price;
    document.getElementById('edit_quantity').value = quantity;
    document.getElementById('edit_stock').value = stock;
    
    let select = document.getElementById('edit_category_id');
    for(let i = 0; i < select.options.length; i++) {
        if(select.options[i].text == category) {
            select.value = select.options[i].value;
            break;
        }
    }
    
    document.getElementById('edit_photo_preview').innerHTML = photo ? 
        '<img src="../uploads/' + photo + '" width="80">' : 'N/A';
    
    var modal = new bootstrap.Modal(document.getElementById('editEquipmentModal'));
    modal.show();
}

document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('searchInput').value = '';
});