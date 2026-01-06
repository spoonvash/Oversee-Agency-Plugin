<?php
if (!defined('ABSPATH')) exit;
if (!is_user_logged_in() || !current_user_can('oversee_manage_kb')) { wp_safe_redirect(oversee_admin_url()); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>KB Articles - <?php echo esc_html(oversee_get_portal_title()); ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link rel="stylesheet" href="<?php echo esc_url(OVERSEE_PLUGIN_URL . 'assets/css/admin.css?v=2.0.1'); ?>">
</head>
<body class="admin-body">
<?php include OVERSEE_TEMPLATES_PATH . '/partials/admin-sidebar.php'; ?>
<main class="main">
<div class="topbar">
<button class="hamburger-btn" onclick="toggleMobileSidebar()"><i class="fa-solid fa-bars"></i></button>
<h2>Knowledge Base</h2>
<div class="topbar-right">
<button class="btn btn-secondary" onclick="showModal('categoryModal')"><i class="fa-solid fa-folder-plus"></i> New Category</button>
<a href="<?php echo esc_url(oversee_admin_url('articles/new')); ?>" class="btn btn-primary"><i class="fa-solid fa-plus"></i> New Article</a>
</div>
</div>
<div class="content">
<div class="stats-row">
<div class="stat-card open"><div><div class="stat-value" id="statTotal">-</div><div class="stat-label">Total Articles</div></div></div>
<div class="stat-card resolved"><div><div class="stat-value" id="statPublished">-</div><div class="stat-label">Published</div></div></div>
<div class="stat-card new"><div><div class="stat-value" id="statDraft">-</div><div class="stat-label">Drafts</div></div></div>
<div class="stat-card high"><div><div class="stat-value" id="statCategories">-</div><div class="stat-label">Categories</div></div></div>
</div>
<div class="articles-tabs">
<button class="articles-tab active" data-tab="articles"><i class="fa-solid fa-file-lines"></i> Articles <span class="count" id="articlesCount">0</span></button>
<button class="articles-tab" data-tab="categories"><i class="fa-solid fa-folder"></i> Categories <span class="count" id="categoriesCount">0</span></button>
</div>
<div id="tab-articles" class="tab-content active">
<div class="card">
<div class="articles-toolbar">
<div class="articles-search"><i class="fa-solid fa-search"></i><input type="text" id="searchInput" placeholder="Search articles..."></div>
<select class="filter-select" id="filterCategory"><option value="">All Categories</option></select>
<select class="filter-select" id="filterStatus"><option value="">All Status</option><option value="published">Published</option><option value="draft">Draft</option></select>
</div>
<div id="articlesList"><div class="loading-state"><i class="fa-solid fa-spinner fa-spin"></i><span>Loading...</span></div></div>
</div>
</div>
<div id="tab-categories" class="tab-content">
<div class="card">
<div class="info-banner"><i class="fa-solid fa-circle-info"></i><p>Categories help organize your knowledge base.</p></div>
<div class="categories-grid" id="categoriesList"><div class="loading-state"><i class="fa-solid fa-spinner fa-spin"></i></div></div>
</div>
</div>
</div>
</main>
<div class="modal" id="categoryModal">
<div class="modal-backdrop" onclick="closeModal('categoryModal')"></div>
<div class="modal-content">
<div class="modal-header"><h3 id="categoryModalTitle">New Category</h3><button class="modal-close" onclick="closeModal('categoryModal')"><i class="fa-solid fa-times"></i></button></div>
<form id="categoryForm">
<input type="hidden" id="categoryId">
<div class="modal-body">
<div class="form-group"><label>Name *</label><input type="text" id="categoryName" required></div>
<div class="form-group"><label>Description</label><textarea id="categoryDescription" rows="3"></textarea></div>
<div class="form-group"><label>Icon</label><input type="text" id="categoryIcon" value="fa-solid fa-folder"></div>
</div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('categoryModal')">Cancel</button><button type="submit" class="btn btn-primary">Save</button></div>
</form>
</div>
</div>
<div class="modal" id="deleteModal">
<div class="modal-backdrop" onclick="closeModal('deleteModal')"></div>
<div class="modal-content modal-sm">
<div class="modal-header"><h3>Confirm Delete</h3><button class="modal-close" onclick="closeModal('deleteModal')"><i class="fa-solid fa-times"></i></button></div>
<div class="modal-body"><p id="deleteMessage"></p></div>
<div class="modal-footer"><button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button><button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button></div>
</div>
</div>
<div class="toast" id="toast"><i class="fa-solid fa-check-circle"></i><span id="toastMessage"></span></div>
<script>
var WP_NONCE='<?php echo wp_create_nonce("wp_rest"); ?>';
var REST_URL='<?php echo esc_url(rest_url("oversee/v1")); ?>';
var EDIT_URL='<?php echo esc_url(oversee_admin_url("articles/edit/")); ?>';
var NEW_URL='<?php echo esc_url(oversee_admin_url("articles/new")); ?>';
var categories=[],articles=[],searchTimeout,deleteType,deleteId;
document.addEventListener('DOMContentLoaded',function(){initTabs();initSearch();initFilters();initForms();loadCategories();loadArticles()});
function initTabs(){document.querySelectorAll('.articles-tab').forEach(function(t){t.addEventListener('click',function(){var n=this.getAttribute('data-tab');document.querySelectorAll('.tab-content').forEach(function(c){c.classList.remove('active')});document.querySelectorAll('.articles-tab').forEach(function(b){b.classList.remove('active')});document.getElementById('tab-'+n).classList.add('active');this.classList.add('active')})})}
function initSearch(){document.getElementById('searchInput').addEventListener('keyup',function(){clearTimeout(searchTimeout);searchTimeout=setTimeout(loadArticles,300)})}
function initFilters(){document.getElementById('filterCategory').addEventListener('change',loadArticles);document.getElementById('filterStatus').addEventListener('change',loadArticles)}
function initForms(){document.getElementById('categoryForm').addEventListener('submit',function(e){e.preventDefault();saveCategory()})}
function api(method,endpoint,data){var opts={method:method,headers:{'Content-Type':'application/json','X-WP-Nonce':WP_NONCE}};if(data)opts.body=JSON.stringify(data);return fetch(REST_URL+endpoint,opts).then(function(r){if(!r.ok)throw new Error('API Error');return r.json()})}
function loadCategories(){api('GET','/kb/categories?include_remote=false').then(function(d){categories=d.categories||d||[];renderCategories();populateCategoryFilter();document.getElementById('categoriesCount').textContent=categories.length;document.getElementById('statCategories').textContent=categories.length}).catch(function(){document.getElementById('categoriesList').innerHTML='<div class="empty-state"><i class="fa-solid fa-exclamation-circle"></i><p>Failed to load</p></div>'})}
function renderCategories(){var c=document.getElementById('categoriesList'),h='';for(var i=0;i<categories.length;i++){var cat=categories[i];h+='<div class="category-card" data-slug="'+esc(cat.slug)+'">';h+='<div class="category-icon"><i class="'+esc(cat.icon||'fa-solid fa-folder')+'"></i></div>';h+='<div class="category-info"><div class="category-name">'+esc(cat.name)+'</div>';h+='<div class="category-desc">'+esc(cat.description||'No description')+'</div>';h+='<div class="category-meta">'+(cat.article_count||0)+' articles</div></div>';h+='<div class="category-actions"><button onclick="event.stopPropagation();editCategory('+cat.id+')"><i class="fa-solid fa-edit"></i></button>';h+='<button class="danger" onclick="event.stopPropagation();confirmDelete(\'category\','+cat.id+',\''+esc(cat.name)+'\')"><i class="fa-solid fa-trash"></i></button></div></div>'}h+='<div class="add-category-card" onclick="showModal(\'categoryModal\')"><i class="fa-solid fa-plus"></i><span>Add Category</span></div>';c.innerHTML=h;document.querySelectorAll('.category-card[data-slug]').forEach(function(card){card.addEventListener('click',function(){filterByCategory(this.getAttribute('data-slug'))})})}
function populateCategoryFilter(){var s=document.getElementById('filterCategory'),h='<option value="">All Categories</option>';for(var i=0;i<categories.length;i++)h+='<option value="'+esc(categories[i].slug)+'">'+esc(categories[i].name)+'</option>';s.innerHTML=h}
function filterByCategory(slug){document.getElementById('filterCategory').value=slug;document.querySelector('.articles-tab[data-tab="articles"]').click();loadArticles()}
function loadArticles(){var c=document.getElementById('articlesList');c.innerHTML='<div class="loading-state"><i class="fa-solid fa-spinner fa-spin"></i></div>';var cat=document.getElementById('filterCategory').value,st=document.getElementById('filterStatus').value,q=document.getElementById('searchInput').value,url='/kb/articles?per_page=50';if(cat)url+='&category='+cat;if(st)url+='&status='+st;if(q)url+='&search='+encodeURIComponent(q);api('GET',url).then(function(d){articles=d.articles||d||[];renderArticles();updateStats()}).catch(function(){c.innerHTML='<div class="empty-state"><i class="fa-solid fa-exclamation-circle"></i><h4>Failed to load</h4></div>'})}
function renderArticles(){var c=document.getElementById('articlesList');document.getElementById('articlesCount').textContent=articles.length;if(!articles.length){c.innerHTML='<div class="empty-state"><i class="fa-solid fa-file-lines"></i><h4>No articles</h4><a href="'+NEW_URL+'" class="btn btn-primary">Create Article</a></div>';return}var h='<table class="articles-table"><thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Updated</th><th>Actions</th></tr></thead><tbody>';for(var i=0;i<articles.length;i++){var a=articles[i];h+='<tr><td><div class="article-title-cell"><div class="article-icon"><i class="fa-solid fa-file-lines"></i></div><div><a href="'+EDIT_URL+a.id+'" class="article-title">'+esc(a.title)+'</a><div class="article-slug">/'+esc(a.slug||a.id)+'</div></div></div></td>';h+='<td>'+esc(a.category_name||a.category_slug||'-')+'</td>';h+='<td><span class="status-pill '+a.status+'"><i class="fa-solid fa-circle"></i> '+(a.status==='published'?'Published':'Draft')+'</span></td>';h+='<td>'+timeAgo(a.updated_at)+'</td>';h+='<td><div class="action-btns"><a href="'+EDIT_URL+a.id+'" class="action-btn"><i class="fa-solid fa-edit"></i></a><button class="action-btn danger" onclick="confirmDelete(\'article\','+a.id+',\''+esc(a.title).replace(/'/g,"\\'")+'\')"><i class="fa-solid fa-trash"></i></button></div></td></tr>'}h+='</tbody></table>';c.innerHTML=h}
function updateStats(){var p=0,d=0;for(var i=0;i<articles.length;i++)articles[i].status==='published'?p++:d++;document.getElementById('statTotal').textContent=articles.length;document.getElementById('statPublished').textContent=p;document.getElementById('statDraft').textContent=d}
function editCategory(id){var cat;for(var i=0;i<categories.length;i++)if(categories[i].id==id){cat=categories[i];break}if(!cat)return;document.getElementById('categoryId').value=cat.id;document.getElementById('categoryName').value=cat.name;document.getElementById('categoryDescription').value=cat.description||'';document.getElementById('categoryIcon').value=cat.icon||'fa-solid fa-folder';document.getElementById('categoryModalTitle').textContent='Edit Category';showModal('categoryModal')}
function saveCategory(){var id=document.getElementById('categoryId').value,data={name:document.getElementById('categoryName').value,description:document.getElementById('categoryDescription').value,icon:document.getElementById('categoryIcon').value};api(id?'PUT':'POST','/kb/categories'+(id?'/'+id:''),data).then(function(){closeModal('categoryModal');resetCategoryForm();loadCategories();showToast('Category saved!')}).catch(function(){showToast('Failed to save',true)})}
function resetCategoryForm(){document.getElementById('categoryId').value='';document.getElementById('categoryForm').reset();document.getElementById('categoryModalTitle').textContent='New Category'}
function confirmDelete(type,id,name){deleteType=type;deleteId=id;document.getElementById('deleteMessage').innerHTML='Delete <strong>'+esc(name)+'</strong>?';document.getElementById('confirmDeleteBtn').onclick=executeDelete;showModal('deleteModal')}
function executeDelete(){api('DELETE','/kb/'+(deleteType==='category'?'categories':'articles')+'/'+deleteId).then(function(){closeModal('deleteModal');deleteType==='category'?loadCategories():loadArticles();showToast('Deleted!')}).catch(function(){showToast('Failed',true)})}
function showModal(id){document.getElementById(id).classList.add('show')}
function closeModal(id){document.getElementById(id).classList.remove('show');if(id==='categoryModal')resetCategoryForm()}
function showToast(msg,err){var t=document.getElementById('toast');t.querySelector('i').className=err?'fa-solid fa-exclamation-circle':'fa-solid fa-check-circle';document.getElementById('toastMessage').textContent=msg;t.classList.toggle('error',err);t.classList.add('show');setTimeout(function(){t.classList.remove('show')},3000)}
function esc(s){if(!s)return'';var d=document.createElement('div');d.textContent=s;return d.innerHTML}
function timeAgo(ds){if(!ds)return'-';var d=new Date(ds),n=new Date(),s=Math.floor((n-d)/1000);if(s<60)return'Just now';if(s<3600)return Math.floor(s/60)+'m ago';if(s<86400)return Math.floor(s/3600)+'h ago';if(s<604800)return Math.floor(s/86400)+'d ago';return d.toLocaleDateString()}
</script>
</body>
</html>
