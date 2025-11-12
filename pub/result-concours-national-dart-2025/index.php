    <div class="container">
        <div class="controls">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="searchInput" placeholder="Rechercher par nom d'artiste, titre d'œuvre...">
            </div>
            <div class="filter-box">
                <select id="wilayaFilter">
                    <option value="">Toutes les wilayas</option>
                    <?php foreach (array_keys($wilayaStats) as $wilaya): ?>
                        <option value="<?php echo htmlspecialchars($wilaya); ?>"><?php echo htmlspecialchars($wilaya); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-box">
                <select id="dimensionFilter">
                    <option value="">Toutes les dimensions</option>
                    <?php foreach (array_keys($dimensionStats) as $dimension): ?>
                        <option value="<?php echo htmlspecialchars($dimension); ?>"><?php echo htmlspecialchars($dimension); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-box">
                <select id="ratingFilter">
                    <option value="">Toutes les notes</option>
                    <option value="5">5 étoiles</option>
                    <option value="4">4 étoiles et plus</option>
                    <option value="3">3 étoiles et plus</option>
                    <option value="2">2 étoiles et plus</option>
                    <option value="1">1 étoile et plus</option>
                </select>
            </div>
            <div class="filter-box">
                <select id="categoryFilter">
                    <option value="">Toutes les catégories</option>
                    <option value="1 meter square">1 meter square</option>
                    <option value="2 meter square">2 meter square</option>
                    <option value="3 meter square">3 meter square</option>
                    <option value="other">Other</option>
                </select>
            </div>
            <div class="filter-box">
                <select id="sortBy">
                    <option value="newest" <?php echo ($sortOrder === 'newest') ? 'selected' : ''; ?>>Plus récents</option>
                    <option value="oldest" <?php echo ($sortOrder === 'oldest') ? 'selected' : ''; ?>>Plus anciens</option>
                    <option value="rating_high" <?php echo ($sortOrder === 'rating_high') ? 'selected' : ''; ?>>Note: Décroissant</option>
                    <option value="rating_low" <?php echo ($sortOrder === 'rating_low') ? 'selected' : ''; ?>>Note: Croissant</option>
                </select>
            </div>
            <div class="view-options">
                <label>Affichage:</label>
                <button id="viewCards" class="view-btn active" data-view="cards">
                    <i class="fas fa-th-large"></i>
                </button>
                <button id="viewTable" class="view-btn" data-view="table">
                    <i class="fas fa-table"></i>
                </button>
            </div>
        </div>