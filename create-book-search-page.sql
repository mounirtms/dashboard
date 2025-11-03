-- Create book search CMS page for SILA store
INSERT INTO cms_page (title, identifier, content, content_heading, meta_keywords, meta_description, is_active, sort_order, layout_update_xml, custom_theme, custom_root_template, custom_layout_update_xml, layout_update_selected, meta_title) 
VALUES 
('بحث الكتب', 'book_search', '<div class="book-search-page">
    <div class="hero-section">
        <h1>بحث الكتب</h1>
        <p>ابحث في مجموعتنا الواسعة من الكتب والمنشورات</p>
    </div>
    
    <div class="search-section">
        <div class="container">
            <form class="book-search-form">
                <div class="search-field">
                    <input type="text" placeholder="ابحث عن كتاب، مؤلف، أو موضوع..." class="search-input" />
                    <button type="submit" class="search-button">بحث</button>
                </div>
                
                <div class="search-filters">
                    <div class="filter-group">
                        <label for="category">الفئة:</label>
                        <select id="category">
                            <option value="">جميع الفئات</option>
                            <option value="school">كتب مدرسية</option>
                            <option value="office">كتب مكتبية</option>
                            <option value="art">فنون وحرف</option>
                            <option value="tech">تقنية</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="level">المستوى:</label>
                        <select id="level">
                            <option value="">جميع المستويات</option>
                            <option value="primary">ابتدائي</option>
                            <option value="middle">متوسط</option>
                            <option value="high">ثانوي</option>
                            <option value="university">جامعي</option>
                        </select>
                    </div>
                </div>
            </form>
        </div>
    </div>
    
    <div class="featured-books">
        <div class="container">
            <h2 class="section-title">كتب مميزة</h2>
            <div class="books-grid">
                <div class="book-item">
                    <div class="book-image">
                        <img src="{{media url=wysiwyg/sila.PNG}}" alt="كتاب مميز" />
                    </div>
                    <div class="book-info">
                        <h3>كتاب متميز 1</h3>
                        <p class="book-description">وصف مختصر للكتاب المتميز الأول في المجموعة</p>
                        <a href="#" class="book-link">عرض التفاصيل</a>
                    </div>
                </div>
                
                <div class="book-item">
                    <div class="book-image">
                        <img src="{{media url=wysiwyg/sila.PNG}}" alt="كتاب مميز" />
                    </div>
                    <div class="book-info">
                        <h3>كتاب متميز 2</h3>
                        <p class="book-description">وصف مختصر للكتاب المتميز الثاني في المجموعة</p>
                        <a href="#" class="book-link">عرض التفاصيل</a>
                    </div>
                </div>
                
                <div class="book-item">
                    <div class="book-image">
                        <img src="{{media url=wysiwyg/sila.PNG}}" alt="كتاب مميز" />
                    </div>
                    <div class="book-info">
                        <h3>كتاب متميز 3</h3>
                        <p class="book-description">وصف مختصر للكتاب المتميز الثالث في المجموعة</p>
                        <a href="#" class="book-link">عرض التفاصيل</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="popular-categories">
        <div class="container">
            <h2 class="section-title">فئات شائعة</h2>
            <div class="categories-grid">
                <div class="category-item">
                    <h3>الكتب المدرسية</h3>
                    <p>مجموعة شاملة من الكتب المدرسية لجميع المستويات</p>
                </div>
                
                <div class="category-item">
                    <h3>الكتب المكتبية</h3>
                    <p>منشورات وكتب للمكاتب والشركات</p>
                </div>
                
                <div class="category-item">
                    <h3>كتب الفنون</h3>
                    <p>كتب متخصصة في الفنون والحرف اليدوية</p>
                </div>
                
                <div class="category-item">
                    <h3>كتب التقنية</h3>
                    <p>أحدث المنشورات في مجال التقنية والبرمجيات</p>
                </div>
            </div>
        </div>
    </div>
</div>', 'بحث الكتب', 'sila, books, search, algeria', 'ابحث في مجموعتنا الواسعة من الكتب والمنشورات في SILA', 1, 0, NULL, NULL, '1column', NULL, NULL, 'بحث الكتب');

-- Get the page ID for the newly created page
SET @book_search_page_id = LAST_INSERT_ID();

-- Assign this page to the SILA store (store_id = 6)
INSERT INTO cms_page_store (page_id, store_id) VALUES 
(@book_search_page_id, 6);