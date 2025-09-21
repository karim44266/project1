<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<title>Enhanced Products Navigation</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
/* Enhanced Products Panel Styles */
.products-panel {
  position: fixed;
  top: -100%;
  left: 0;
  width: 100%;
  background: linear-gradient(135deg, #fff 0%, #f8f9fa 100%);
  box-shadow: 0 8px 32px rgba(0,0,0,0.15);
  transition: top .4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  z-index: 9999;
  padding: 25px 15px;
  border-bottom: 3px solid #00ffcc;
}

.products-panel.active {
  top: 70px; /* Adjust based on your header height */
}

.panel-inner {
  max-width: 1400px;
  margin: 0 auto;
  position: relative;
  min-height: 400px;
}

.panel-close {
  position: absolute;
  right: 15px;
  top: 0px;
  font-size: 32px;
  background: none;
  border: none;
  cursor: pointer;
  color: #666;
  transition: all 0.3s ease;
  z-index: 10;
}

.panel-close:hover {
  color: #ff4444;
  transform: scale(1.1);
}

/* Navigation breadcrumb */
.panel-breadcrumb {
  display: flex;
  align-items: center;
  gap: 10px;
  margin-bottom: 20px;
  padding: 10px 15px;
  background: rgba(0,255,204,0.1);
  border-radius: 8px;
  font-size: 14px;
  color: #666;
}

.breadcrumb-item {
  cursor: pointer;
  color: #00ffcc;
  font-weight: 500;
  transition: color 0.3s;
}

.breadcrumb-item:hover {
  color: #00d4aa;
}

.breadcrumb-separator {
  color: #999;
}

/* Main content area */
.panel-content {
  position: relative;
  min-height: 350px;
}

/* Categories Grid */
.categories-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
  gap: 20px;
  opacity: 1;
  transition: opacity 0.3s ease;
}

.categories-grid.hidden {
  opacity: 0;
  pointer-events: none;
}

.category-card {
  background: white;
  border-radius: 15px;
  padding: 20px;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
  box-shadow: 0 4px 15px rgba(0,0,0,0.08);
  border: 2px solid transparent;
  position: relative;
  overflow: hidden;
}

.category-card::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(0,255,204,0.1), transparent);
  transition: left 0.6s ease;
}

.category-card:hover::before {
  left: 100%;
}

.category-card:hover {
  transform: translateY(-8px);
  box-shadow: 0 8px 30px rgba(0,255,204,0.2);
  border-color: #00ffcc;
}

.category-card img {
  width: 100%;
  height: 120px;
  object-fit: cover;
  border-radius: 10px;
  margin-bottom: 15px;
  transition: transform 0.3s ease;
}

.category-card:hover img {
  transform: scale(1.05);
}

.category-card h3 {
  margin: 0;
  font-size: 16px;
  font-weight: 600;
  color: #333;
  margin-bottom: 8px;
}

.category-card .series-count {
  font-size: 12px;
  color: #666;
  background: #f0f0f0;
  padding: 4px 8px;
  border-radius: 12px;
  display: inline-block;
}

/* Series Grid */
.series-grid {
  display: none;
  grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
  gap: 18px;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.series-grid.active {
  display: grid;
  opacity: 1;
}

.series-card {
  background: white;
  border-radius: 12px;
  padding: 18px;
  text-align: center;
  cursor: pointer;
  transition: all 0.3s ease;
  box-shadow: 0 3px 12px rgba(0,0,0,0.08);
  border: 2px solid transparent;
}

.series-card:hover {
  transform: translateY(-5px);
  box-shadow: 0 6px 25px rgba(0,255,204,0.15);
  border-color: #00ffcc;
}

.series-card img {
  width: 100%;
  height: 100px;
  object-fit: cover;
  border-radius: 8px;
  margin-bottom: 12px;
}

.series-card h4 {
  margin: 0;
  font-size: 14px;
  font-weight: 600;
  color: #333;
  margin-bottom: 6px;
}

.series-card .products-count {
  font-size: 11px;
  color: #666;
  background: #f0f0f0;
  padding: 3px 6px;
  border-radius: 10px;
  display: inline-block;
}

/* Products Grid */
.products-grid {
  display: none;
  grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
  gap: 16px;
  opacity: 0;
  transition: opacity 0.3s ease;
  max-height: 300px;
  overflow-y: auto;
}

.products-grid.active {
  display: grid;
  opacity: 1;
}

.product-card {
  background: white;
  border-radius: 10px;
  padding: 15px;
  text-align: center;
  transition: all 0.3s ease;
  box-shadow: 0 2px 10px rgba(0,0,0,0.08);
  border: 2px solid transparent;
}

.product-card:hover {
  transform: translateY(-3px);
  box-shadow: 0 5px 20px rgba(0,255,204,0.15);
  border-color: #00ffcc;
}

.product-card img {
  width: 100%;
  height: 80px;
  object-fit: cover;
  border-radius: 6px;
  margin-bottom: 10px;
}

.product-card h5 {
  margin: 0;
  font-size: 12px;
  font-weight: 600;
  color: #333;
  margin-bottom: 5px;
  line-height: 1.3;
}

.product-card .price {
  font-size: 11px;
  color: #00ffcc;
  font-weight: bold;
}

/* Loading animation */
.loading {
  display: flex;
  justify-content: center;
  align-items: center;
  height: 200px;
  opacity: 0;
  transition: opacity 0.3s ease;
}

.loading.active {
  opacity: 1;
}

.spinner {
  width: 40px;
  height: 40px;
  border: 4px solid #f0f0f0;
  border-top: 4px solid #00ffcc;
  border-radius: 50%;
  animation: spin 1s linear infinite;
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

/* Back button */
.back-button {
  display: none;
  align-items: center;
  gap: 8px;
  background: #00ffcc;
  color: #1a1a1a;
  border: none;
  padding: 10px 20px;
  border-radius: 25px;
  cursor: pointer;
  font-weight: 600;
  transition: all 0.3s ease;
  margin-bottom: 20px;
}

.back-button.active {
  display: inline-flex;
}

.back-button:hover {
  background: #00d4aa;
  transform: translateX(-3px);
}

/* Responsive */
@media (max-width: 768px) {
  .products-panel.active {
    top: 60px;
  }
  
  .categories-grid {
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 15px;
  }
  
  .series-grid {
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 12px;
  }
  
  .products-grid {
    grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
    gap: 12px;
  }
  
  .panel-inner {
    padding: 0 10px;
  }
}

/* Animation classes */
.slide-in-left {
  animation: slideInLeft 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

.slide-in-right {
  animation: slideInRight 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
}

@keyframes slideInLeft {
  from {
    opacity: 0;
    transform: translateX(-30px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}

@keyframes slideInRight {
  from {
    opacity: 0;
    transform: translateX(30px);
  }
  to {
    opacity: 1;
    transform: translateX(0);
  }
}
</style>
</head>
<body>

<!-- Enhanced Products Panel -->
<div id="productsPanel" class="products-panel" aria-hidden="true">
  <div class="panel-inner">
    <button id="productsPanelClose" class="panel-close" aria-label="Fermer">&times;</button>
    
    <!-- Breadcrumb Navigation -->
    <div class="panel-breadcrumb">
      <span class="breadcrumb-item" id="breadcrumbHome">
        <i class="fas fa-home"></i> Accueil
      </span>
      <span class="breadcrumb-separator" id="breadcrumbSep1" style="display:none;">/</span>
      <span class="breadcrumb-item" id="breadcrumbCategory" style="display:none;"></span>
      <span class="breadcrumb-separator" id="breadcrumbSep2" style="display:none;">/</span>
      <span class="breadcrumb-item" id="breadcrumbSeries" style="display:none;"></span>
    </div>

    <!-- Back Button -->
    <button class="back-button" id="backButton">
      <i class="fas fa-arrow-left"></i> Retour
    </button>

    <!-- Panel Content -->
    <div class="panel-content">
      <!-- Loading Animation -->
      <div class="loading" id="loadingSpinner">
        <div class="spinner"></div>
      </div>

      <!-- Categories Grid (Default View) -->
      <div class="categories-grid" id="categoriesGrid">
        <!-- Categories will be populated here -->
      </div>

      <!-- Series Grid -->
      <div class="series-grid" id="seriesGrid">
        <!-- Series will be populated here -->
      </div>

      <!-- Products Grid -->
      <div class="products-grid" id="productsGrid">
        <!-- Products will be populated here -->
      </div>
    </div>
  </div>
</div>

<script>
// Enhanced Products Panel Navigation
class ProductsNavigation {
  constructor() {
    this.currentView = 'categories'; // categories, series, products
    this.currentCategory = null;
    this.currentSeries = null;
    this.isLoading = false;
    
    this.initElements();
    this.loadCategories();
    this.bindEvents();
  }
  
  initElements() {
    this.panel = document.getElementById('productsPanel');
    this.closeBtn = document.getElementById('productsPanelClose');
    this.backBtn = document.getElementById('backButton');
    this.loadingSpinner = document.getElementById('loadingSpinner');
    
    // Grids
    this.categoriesGrid = document.getElementById('categoriesGrid');
    this.seriesGrid = document.getElementById('seriesGrid');
    this.productsGrid = document.getElementById('productsGrid');
    
    // Breadcrumb elements
    this.breadcrumbHome = document.getElementById('breadcrumbHome');
    this.breadcrumbCategory = document.getElementById('breadcrumbCategory');
    this.breadcrumbSeries = document.getElementById('breadcrumbSeries');
    this.breadcrumbSep1 = document.getElementById('breadcrumbSep1');
    this.breadcrumbSep2 = document.getElementById('breadcrumbSep2');
  }
  
  bindEvents() {
    // Panel toggle
    document.getElementById('productsToggle')?.addEventListener('click', (e) => {
      e.preventDefault();
      this.togglePanel();
    });
    
    // Close panel
    this.closeBtn?.addEventListener('click', () => this.closePanel());
    
    // Back navigation
    this.backBtn?.addEventListener('click', () => this.goBack());
    
    // Breadcrumb navigation
    this.breadcrumbHome?.addEventListener('click', () => this.showCategories());
    this.breadcrumbCategory?.addEventListener('click', () => {
      if (this.currentCategory) {
        this.showSeries(this.currentCategory.id, this.currentCategory.name);
      }
    });
    
    // Close on outside click or Escape
    window.addEventListener('click', (e) => {
      if (this.panel?.classList.contains('active') && 
          !e.target.closest('#productsPanel') && 
          !e.target.closest('#productsToggle')) {
        this.closePanel();
      }
    });
    
    window.addEventListener('keydown', (e) => {
      if (e.key === 'Escape' && this.panel?.classList.contains('active')) {
        this.closePanel();
      }
    });
  }
  
  togglePanel() {
    if (this.panel?.classList.contains('active')) {
      this.closePanel();
    } else {
      this.openPanel();
    }
  }
  
  openPanel() {
    this.panel?.classList.add('active');
    this.panel?.setAttribute('aria-hidden', 'false');
    if (this.currentView === 'categories') {
      this.loadCategories();
    }
  }
  
  closePanel() {
    this.panel?.classList.remove('active');
    this.panel?.setAttribute('aria-hidden', 'true');
  }
  
  showLoading() {
    this.isLoading = true;
    this.loadingSpinner?.classList.add('active');
    this.hideAllGrids();
  }
  
  hideLoading() {
    this.isLoading = false;
    this.loadingSpinner?.classList.remove('active');
  }
  
  hideAllGrids() {
    this.categoriesGrid?.classList.remove('active');
    this.seriesGrid?.classList.remove('active');
    this.productsGrid?.classList.remove('active');
  }
  
  updateBreadcrumb() {
    // Hide all breadcrumb elements first
    this.breadcrumbSep1.style.display = 'none';
    this.breadcrumbSep2.style.display = 'none';
    this.breadcrumbCategory.style.display = 'none';
    this.breadcrumbSeries.style.display = 'none';
    
    if (this.currentView === 'series' && this.currentCategory) {
      this.breadcrumbSep1.style.display = 'inline';
      this.breadcrumbCategory.style.display = 'inline';
      this.breadcrumbCategory.textContent = this.currentCategory.name;
    }
    
    if (this.currentView === 'products' && this.currentCategory && this.currentSeries) {
      this.breadcrumbSep1.style.display = 'inline';
      this.breadcrumbSep2.style.display = 'inline';
      this.breadcrumbCategory.style.display = 'inline';
      this.breadcrumbSeries.style.display = 'inline';
      this.breadcrumbCategory.textContent = this.currentCategory.name;
      this.breadcrumbSeries.textContent = this.currentSeries.name;
    }
    
    // Show/hide back button
    if (this.currentView !== 'categories') {
      this.backBtn?.classList.add('active');
    } else {
      this.backBtn?.classList.remove('active');
    }
  }
  
  goBack() {
    if (this.currentView === 'products') {
      this.showSeries(this.currentCategory.id, this.currentCategory.name);
    } else if (this.currentView === 'series') {
      this.showCategories();
    }
  }
  
  async loadCategories() {
    this.currentView = 'categories';
    this.currentCategory = null;
    this.currentSeries = null;
    this.updateBreadcrumb();
    
    this.showLoading();
    
    try {
      // Simulate API call - replace with your actual fetch
      await this.delay(300);
      
      // Sample categories data - replace with your actual data
      const categories = [
        { id: 1, name: 'Éclairage LED', image: 'led_category.jpg', seriesCount: 5 },
        { id: 2, name: 'Luminaires', image: 'luminaires.jpg', seriesCount: 3 },
        { id: 3, name: 'Accessoires', image: 'accessories.jpg', seriesCount: 4 },
        { id: 4, name: 'Solutions Smart', image: 'smart.jpg', seriesCount: 2 }
      ];
      
      this.renderCategories(categories);
      
    } catch (error) {
      console.error('Error loading categories:', error);
    } finally {
      this.hideLoading();
    }
  }
  
  renderCategories(categories) {
    this.hideAllGrids();
    
    this.categoriesGrid.innerHTML = categories.map(category => `
      <div class="category-card" onclick="productsNav.showSeries(${category.id}, '${category.name}')">
        <img src="uploads/${category.image}" alt="${category.name}" onerror="this.src='https://via.placeholder.com/200x120/f0f0f0/999?text=No+Image'">
        <h3>${category.name}</h3>
        <span class="series-count">${category.seriesCount} série${category.seriesCount > 1 ? 's' : ''}</span>
      </div>
    `).join('');
    
    this.categoriesGrid.classList.add('active');
    this.categoriesGrid.classList.add('slide-in-left');
  }
  
  async showSeries(categoryId, categoryName) {
    this.currentView = 'series';
    this.currentCategory = { id: categoryId, name: categoryName };
    this.updateBreadcrumb();
    
    this.showLoading();
    
    try {
      await this.delay(300);
      
      // Sample series data - replace with your actual fetch
      const series = [
        { id: 1, name: 'Série Premium', image: 'premium.jpg', productsCount: 8 },
        { id: 2, name: 'Série Eco', image: 'eco.jpg', productsCount: 12 },
        { id: 3, name: 'Série Pro', image: 'pro.jpg', productsCount: 6 },
        { id: 4, name: 'Série Standard', image: 'standard.jpg', productsCount: 15 }
      ];
      
      this.renderSeries(series);
      
    } catch (error) {
      console.error('Error loading series:', error);
    } finally {
      this.hideLoading();
    }
  }
  
  renderSeries(series) {
    this.hideAllGrids();
    
    this.seriesGrid.innerHTML = series.map(serie => `
      <div class="series-card" onclick="productsNav.showProducts(${serie.id}, '${serie.name}')">
        <img src="uploads/${serie.image}" alt="${serie.name}" onerror="this.src='https://via.placeholder.com/160x100/f0f0f0/999?text=No+Image'">
        <h4>${serie.name}</h4>
        <span class="products-count">${serie.productsCount} produit${serie.productsCount > 1 ? 's' : ''}</span>
      </div>
    `).join('');
    
    this.seriesGrid.classList.add('active');
    this.seriesGrid.classList.add('slide-in-right');
  }
  
  async showProducts(seriesId, seriesName) {
    this.currentView = 'products';
    this.currentSeries = { id: seriesId, name: seriesName };
    this.updateBreadcrumb();
    
    this.showLoading();
    
    try {
      await this.delay(300);
      
      // Sample products data - replace with your actual fetch
      const products = [
        { id: 1, name: 'LED Strip 5m', image: 'strip1.jpg', price: '29.99' },
        { id: 2, name: 'LED Bulb E27', image: 'bulb1.jpg', price: '12.50' },
        { id: 3, name: 'LED Panel 60x60', image: 'panel1.jpg', price: '89.90' },
        { id: 4, name: 'LED Spot GU10', image: 'spot1.jpg', price: '8.75' },
        { id: 5, name: 'LED Tube T8', image: 'tube1.jpg', price: '15.25' },
        { id: 6, name: 'LED Downlight', image: 'down1.jpg', price: '24.99' }
      ];
      
      this.renderProducts(products);
      
    } catch (error) {
      console.error('Error loading products:', error);
    } finally {
      this.hideLoading();
    }
  }
  
  renderProducts(products) {
    this.hideAllGrids();
    
    this.productsGrid.innerHTML = products.map(product => `
      <div class="product-card" onclick="window.location.href='product.php?id=${product.id}'">
        <img src="uploads/${product.image}" alt="${product.name}" onerror="this.src='https://via.placeholder.com/160x80/f0f0f0/999?text=No+Image'">
        <h5>${product.name}</h5>
        <div class="price">${product.price} DT</div>
      </div>
    `).join('');
    
    this.productsGrid.classList.add('active');
    this.productsGrid.classList.add('slide-in-left');
  }
  
  // Helper function for delays
  delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}

// Initialize the navigation system
const productsNav = new ProductsNavigation();
</script>

</body>
</html>