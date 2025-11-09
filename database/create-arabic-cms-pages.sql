-- Create Arabic CMS pages for SILA store

-- First, let's create the history page
INSERT INTO cms_page (title, identifier, content, content_heading, meta_keywords, meta_description, is_active, sort_order, layout_update_xml, custom_theme, custom_root_template, custom_layout_update_xml, layout_update_selected, meta_title) 
VALUES 
('تاريخ الشركة', 'sila_history', '<div class="about-us">
    <div class="hero-section">
        <h1>تاريخ الشركة</h1>
    </div>
    
    <div class="content-section">
        <p>تأسست شركة SILA في عام 2025 بهدف تقديم حلول مكتبية وتقنيّة متكاملة في الجزائر. تسعى الشركة لتلبية احتياجات المؤسسات الحكومية والخاصة والمستهلكين من خلال مجموعة واسعة من المنتجات عالية الجودة.</p>
        
        <h2> بداياتنا </h2>
        <p>بدأت رحلتنا برؤية واضحة لتصبح رائدة في مجال توزيع المنتجات المكتبية والتقنية في السوق الجزائري. منذ البداية، ركزنا على توفير منتجات موثوقة وخدمة عملاء استثنائية.</p>
        
        <h2> نمونا </h2>
        <p>على مر السنوات، قمنا بتوسيع مجموعتنا من المنتجات لتغطي مختلف المجالات مثل:</p>
        <ul>
            <li>المنتجات المكتبية</li>
            <li>المنتجات المدرسية</li>
            <li>الفنون والحرف اليدوية</li>
            <li>المنتجات التقنية</li>
            <li>أدوات البناء والديكور</li>
            <li>منتجات الترفيه والهوايات</li>
        </ul>
        
        <h2> قيمنا </h2>
        <p>نؤمن بأهمية الجودة والابتكار في كل ما نقوم به. نسعى دائماً لتقديم أفضل الحلول لعملائنا مع الحفاظ على التزامنا بالتميز والنزاهة في جميع تعاملاتنا.</p>
        
        <h2> مستقبلنا </h2>
        <p>ننظر إلى المستقبل بثقة و نطمح لتوسيع نطاق خدماتنا ومنتجاتنا لتلبية احتياجات السوق المتنامية. نواصل الاستثمار في التقنيات الحديثة وتطوير فريق عملنا لضمان استمرار نمونا وتطورنا.</p>
    </div>
</div>', 'تاريخ الشركة', 'sila, history, company', 'تعرف على تاريخ شركة SILA ورحلة نمونا في السوق الجزائري', 1, 0, NULL, NULL, '1column', NULL, NULL, 'تاريخ الشركة');

-- Create home page for SILA store
INSERT INTO cms_page (title, identifier, content, content_heading, meta_keywords, meta_description, is_active, sort_order, layout_update_xml, custom_theme, custom_root_template, custom_layout_update_xml, layout_update_selected, meta_title) 
VALUES 
('الرئيسية', 'home', '<div class="home-page">
    <div class="hero-banner">
        <h1>أهلاً بك في SILA</h1>
        <p>أفضل الحلول المكتبية والتقنية في الجزائر</p>
    </div>
    
    <div class="featured-categories">
        <h2>فئات المنتجات</h2>
        <div class="category-grid">
            <div class="category-item">
                <h3>المنتجات المكتبية</h3>
                <p>مجموعة شاملة من الأدوات والمستلزمات المكتبية</p>
            </div>
            
            <div class="category-item">
                <h3>المنتجات المدرسية</h3>
                <p>كل ما يحتاجه الطلاب والمعلمين للعام الدراسي</p>
            </div>
            
            <div class="category-item">
                <h3>الفنون والحرف</h3>
                <p>أدوات ومستلزمات الفنون والحرف اليدوية</p>
            </div>
            
            <div class="category-item">
                <h3>المنتجات التقنية</h3>
                <p>أحدث الأجهزة والمستلزمات التقنية</p>
            </div>
        </div>
    </div>
    
    <div class="about-section">
        <h2>لماذا SILA؟</h2>
        <ul>
            <li>جودة عالية للمنتجات</li>
            <li>أسعار تنافسية</li>
            <li>خدمة عملاء ممتازة</li>
            <li>توصيل سريع وموثوق</li>
            <li>garantie على المنتجات</li>
        </ul>
    </div>
</div>', 'أهلاً بك في SILA', 'sila, home, office supplies, algeria', 'أهلاً بك في SILA - أفضل الحلول المكتبية والتقنية في الجزائر', 1, 0, NULL, NULL, '1column', NULL, NULL, 'الرئيسية');

-- Get the page IDs for the newly created pages
SET @history_page_id = LAST_INSERT_ID() - 1;
SET @home_page_id = LAST_INSERT_ID();

-- Assign these pages to the SILA store (store_id = 6)
INSERT INTO cms_page_store (page_id, store_id) VALUES 
(@history_page_id, 6),
(@home_page_id, 6);

-- Set Arabic locale for SILA store
INSERT INTO core_config_data (scope, scope_id, path, value) VALUES 
('stores', 6, 'general/locale/code', 'ar_DZ'),
('stores', 6, 'general/locale/timezone', 'Africa/Algiers');

-- Enable store switcher if not already enabled
INSERT INTO core_config_data (scope, scope_id, path, value) VALUES 
('default', 0, 'web/url/use_store', '1')
ON DUPLICATE KEY UPDATE value = '1';

-- Set SILA store base URLs
INSERT INTO core_config_data (scope, scope_id, path, value) VALUES 
('websites', 4, 'web/unsecure/base_url', 'https://technostationery.com/sila/'),
('websites', 4, 'web/secure/base_url', 'https://technostationery.com/sila/')
ON DUPLICATE KEY UPDATE value = VALUES(value);